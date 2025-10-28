"""
Handle messages published on  gateway/dips
"""

import logging
from decimal import Decimal
from django.utils import timezone
from django.db import transaction as db_tx

from accounts.models import Tanks, DipreadHistoric, Console, Sites, DeliveryHistoric
from accounts.tasks import send_level_alert_email
from datetime import datetime, timedelta

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

                # ── 1. GET PREVIOUS VOLUME FOR DELIVERY DETECTION -----------------
                current_volume = Decimal(data["vol"])
                ullage = Decimal(data["ull"])
                
                # Get tank info before updating
                tank = (
                    Tanks.objects
                    .filter(uid_id=uid, tank_id=tank_id)
                    .only('current_volume', 'capacity', 'site_id')
                    .first()
                )
                
                previous_volume = tank.current_volume if tank and tank.current_volume else Decimal(0)
                capacity = tank.capacity if tank else 0
                site_id = tank.site_id if tank else None

                # ── 2. UPDATE Tanks -------------------------------------------------
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

                # ── 3. INSERT DipreadHistoric -------------------------------------
                DipreadHistoric.objects.create(
                    uid              = uid,
                    transaction_date = data["date"],
                    transaction_time = data["time"],
                    tank_id          = tank_id,
                    current_volume   = current_volume,
                    ullage           = ullage,
                    # the table has many optional columns we don't fill yet
                )

                # ── 4. CHECK FOR LOW LEVEL ALERT -----------------------------------
                check_and_send_level_alert(uid, tank_id, current_volume, ullage)

                # ── 5. DETECT DELIVERY -----------------------------------------
                detect_and_record_delivery(
                    uid=uid,
                    tank_id=tank_id,
                    site_id=site_id,
                    previous_volume=previous_volume,
                    current_volume=current_volume,
                    capacity=capacity,
                    transaction_date=data["date"],
                    transaction_time=data["time"]
                )

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


def detect_and_record_delivery(
    uid: int,
    tank_id: int,
    site_id: int | None,
    previous_volume: Decimal,
    current_volume: Decimal,
    capacity: int,
    transaction_date,
    transaction_time
) -> None:
    """
    Detect if a dip reading indicates a delivery (volume increase >= 2% of capacity).
    If detected, either create a new delivery record or update an existing one
    within a 1-hour window on the same day.
    
    Args:
        uid: Console UID
        tank_id: Tank ID
        site_id: Site ID
        previous_volume: Previous volume reading
        current_volume: New volume reading
        capacity: Tank capacity in liters
        transaction_date: Date of the reading (can be string or date object)
        transaction_time: Time of the reading (can be string or time object)
    """
    try:
        # Skip if no capacity data
        if not capacity or capacity <= 0:
            return
        
        # Calculate volume increase
        volume_increase = current_volume - previous_volume
        
        # Check if increase is at least 2% of tank capacity
        delivery_threshold = Decimal(capacity) * Decimal("0.02")
        
        if volume_increase < delivery_threshold:
            # Not a delivery - volume increase is too small
            return
        
        LOG.info(
            "🚚 DELIVERY DETECTED: uid=%s tank=%s, volume increased by %.2f L "
            "(threshold: %.2f L, capacity: %d L)",
            uid, tank_id, volume_increase, delivery_threshold, capacity
        )
        
        # Parse date and time if they are strings
        from datetime import date, time
        
        if isinstance(transaction_date, str):
            transaction_date = date.fromisoformat(transaction_date)
        if isinstance(transaction_time, str):
            transaction_time = time.fromisoformat(transaction_time)
        
        # Combine date and time for comparison
        current_datetime = datetime.combine(transaction_date, transaction_time)
        
        # Look for existing delivery within 1-hour window on the same day
        time_window_start = current_datetime - timedelta(hours=1)
        time_window_end = current_datetime + timedelta(hours=1)
        
        # Query for existing delivery on the same day within time window
        existing_delivery = (
            DeliveryHistoric.objects
            .filter(
                uid=uid,
                tank_id=tank_id,
                transaction_date=transaction_date
            )
            .order_by('-transaction_time')
            .first()
        )
        
        if existing_delivery:
            # Check if within 1-hour window
            existing_datetime = datetime.combine(
                existing_delivery.transaction_date,
                existing_delivery.transaction_time
            )
            
            time_diff = abs((current_datetime - existing_datetime).total_seconds())
            
            if time_diff <= 3600:  # 3600 seconds = 1 hour
                # Update existing delivery
                new_delivery_volume = existing_delivery.delivery + int(volume_increase)
                
                DeliveryHistoric.objects.filter(
                    delivery_id=existing_delivery.delivery_id
                ).update(
                    delivery=new_delivery_volume,
                    current_volume=current_volume,
                    transaction_time=transaction_time  # Update to latest time
                )
                
                LOG.info(
                    "✅ Updated existing delivery #%s: added %.2f L, "
                    "new total: %d L (uid=%s tank=%s)",
                    existing_delivery.delivery_id,
                    volume_increase,
                    new_delivery_volume,
                    uid,
                    tank_id
                )
                return
        
        # No existing delivery in time window - create new record
        new_delivery = DeliveryHistoric.objects.create(
            uid=uid,
            tank_id=tank_id,
            site_id=site_id,
            transaction_date=transaction_date,
            transaction_time=transaction_time,
            current_volume=current_volume,
            delivery=int(volume_increase)
        )
        
        LOG.info(
            "✅ Created new delivery record #%s: %.2f L delivered "
            "(uid=%s tank=%s)",
            new_delivery.delivery_id,
            volume_increase,
            uid,
            tank_id
        )
        
    except Exception as exc:
        LOG.error(
            "Failed to detect/record delivery for uid=%s tank=%s: %s",
            uid, tank_id, exc, exc_info=True
        )


# MQTT topic filter ➜ handler
TOPICS = {
    # "gateway/dips": handle,
    "gateway/+/dips": handle,   # enable if topics later include the serial segment
}
