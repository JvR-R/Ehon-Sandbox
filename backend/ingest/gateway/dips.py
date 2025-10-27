"""
Handle messages published on  gateway/dips
"""

import logging
from decimal import Decimal
from django.utils import timezone
from django.db import transaction as db_tx

from accounts.models import Tanks, DipreadHistoric, Console, Sites
from accounts.tasks import send_level_alert_email

LOG = logging.getLogger("gateway.dips")


def handle(serial: str | None, payload: dict) -> None:
    """
    Example payload:
    {"1":{"vol":564,"ull":2436,"h":564,"alarm":null}}
    * id       ⟶ console uid  (FK → Tanks.uid_id)
    * "1"      ⟶ tank_id      (FK → Tanks.tank_id)
    * vol      ⟶ current_volume / dipread_historic.current_volume
    * ull      ⟶ ullage       / dipread_historic.ullage
    """
    if not serial:
        LOG.error("topic missing device_id segment")
        return

    console = Console.objects.filter(device_id=serial).only("uid").first()

    if console is None:
        LOG.warning("unknown console device_id=%s – message discarded", serial)
        return

    uid = console.uid 
    if uid is None:
        LOG.error("dip payload missing 'id': %r", payload)
        return

    now = timezone.now()
    date, time = now.date(), now.time()

    for tank_key, data in payload.items():
        tank_id = int(tank_key)

        try:
            with db_tx.atomic():

                # ── 1. UPDATE Tanks -------------------------------------------------
                current_volume = Decimal(data["vol"])
                ullage = Decimal(data["ull"])
                
                updated = (
                    Tanks.objects
                         .filter(uid_id=uid, tank_id=tank_id)
                         .update(
                             dipr_date      = data["date"],
                             dipr_time      = data["time"],
                             current_volume = current_volume,
                             ullage         = ullage,
                             volume_height  = Decimal(data["h"]),
                         )
                )
                if updated == 0:
                    LOG.warning("no Tanks row for uid=%s tank=%s", uid, tank_id)
                # else :
                #     LOG.warning("✅Updated row for uid=%s tank=%s", uid, tank_id)

                # ── 2. INSERT DipreadHistoric -------------------------------------
                DipreadHistoric.objects.create(
                    uid              = uid,
                    transaction_date = data["date"],
                    transaction_time = data["time"],
                    tank_id          = tank_id,
                    current_volume   = current_volume,
                    ullage           = ullage,
                    # the table has many optional columns we don't fill yet
                )

                # ── 3. CHECK FOR LOW LEVEL ALERT -----------------------------------
                check_and_send_level_alert(uid, tank_id, current_volume, ullage)

        except Exception as exc:
            LOG.error("dip processing failed (uid=%s tank=%s): %s",
                      uid, tank_id, exc, exc_info=True)
            return

        # LOG.info(
        #     "✅✅ dips processed for console %s (%s tank-reading%s) on %s %s",
        #     uid,
        #     len(payload),
        #     "" if len(payload) == 1 else "s",
        #     data.get("date", now.date()),
        #     data.get("time", now.time()),
        # )


def check_and_send_level_alert(uid: int, tank_id: int, 
                               current_volume: Decimal, ullage: Decimal) -> None:
    """
    Check if tank level is below threshold and send alert email if needed.
    Also resets alert_flag when volume recovers above threshold.
    
    Args:
        uid: Console UID
        tank_id: Tank ID
        current_volume: Current volume in liters
        ullage: Ullage in liters
    """
    try:
        # Get tank with related site info in a single query
        tank = (
            Tanks.objects
            .select_related('uid')  # Console FK
            .filter(uid_id=uid, tank_id=tank_id)
            .only(
                'level_alert', 'alert_flag', 'alert_type', 'current_percent', 
                'site_id', 'tank_name', 'uid__uid'
            )
            .first()
        )
        
        if not tank:
            LOG.warning("Tank not found for level alert check: uid=%s tank=%s", uid, tank_id)
            return
        
        # Check if alert_type is configured (0 means not set up)
        if tank.alert_type is None or tank.alert_type == 0:
            # Alert not configured for this tank, skip
            return
        
        # No threshold set - skip
        if tank.level_alert is None:
            return
        
        # === AUTO-RESET: Volume recovered above threshold ===
        if current_volume >= tank.level_alert and tank.alert_flag != 0:
            # Volume is back above threshold and alert was previously sent
            # Reset alert_flag to 0 so it can alert again if level drops
            Tanks.objects.filter(
                uid_id=uid, 
                tank_id=tank_id
            ).update(alert_flag=0)
            LOG.info("✅ Alert flag reset to 0 for uid=%s tank=%s (volume %.2f >= threshold %s)",
                    uid, tank_id, current_volume, tank.level_alert)
            return
        
        # === SEND ALERT: Volume below threshold and ready to send ===
        # alert_flag logic: 0 = ready to send, non-zero = already sent
        if tank.alert_flag != 0 or current_volume >= tank.level_alert:
            # Either already sent or volume is OK - no alert needed
            return
        
        # Get site information for email
        LOG.debug("Fetching site info for uid=%s site_id=%s", uid, tank.site_id)
        site = (
            Sites.objects
            .filter(uid_id=uid, site_id=tank.site_id)
            .only('name', 'email', 'phone')
            .first()
        )
        
        if not site:
            LOG.warning("❌ Site not found for level alert: uid=%s site_id=%s", uid, tank.site_id)
            return
        
        # Check if we have an email to send to
        if not site.email:
            LOG.warning("❌ No email configured for site %s (uid=%s)", site.name or tank.site_id, uid)
            return
        
        # Prepare alert data
        site_name = site.name or f"Site {tank.site_id}"
        current_percent = tank.current_percent or 0.0
        
        LOG.warning(
            "⚠️  LEVEL ALERT TRIGGERED for %s Tank %s: vol=%.2f < threshold=%s (%.2f%%) - Email: %s",
            site_name, tank_id, current_volume, tank.level_alert, current_percent, site.email
        )
        
        try:
            # Send email asynchronously via Celery
            LOG.warning("📧 Queueing email task to Celery...")
            task_result = send_level_alert_email.delay(
                site_name=site_name,
                tank_id=tank_id,
                current_volume=str(current_volume),
                current_percent=str(current_percent),
                ullage=str(ullage),
                receiver_email=site.email,
                cc_emails=None,  # Add CC emails if needed
            )
            LOG.warning("✅ Email task queued successfully: task_id=%s", task_result.id)
            
        except Exception as email_exc:
            LOG.error("❌ Failed to queue email task: %s", email_exc, exc_info=True)
            # Don't return - still update alert_flag
        
        # Update alert_flag to 1 to prevent spam (alert already sent)
        # Will be reset to 0 when volume goes back above threshold
        updated_count = Tanks.objects.filter(
            uid_id=uid, 
            tank_id=tank_id
        ).update(alert_flag=1)
        
        LOG.warning("✅ Alert flag updated to 1 for uid=%s tank=%s (updated %d row(s))", 
                   uid, tank_id, updated_count)
        
    except Exception as exc:
        LOG.error("Failed to process level alert for uid=%s tank=%s: %s",
                 uid, tank_id, exc, exc_info=True)


# MQTT topic filter ➜ handler
TOPICS = {
    # "gateway/dips": handle,
    "gateway/+/dips": handle,   # enable if topics later include the serial segment
}
