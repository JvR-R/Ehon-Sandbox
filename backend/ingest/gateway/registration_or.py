"""
Process legacy registration packets published on
    gateway/registration            (no serial in topic)
and the new format
    gateway/<serial>/registration   (serial in topic)

Payload example
    {"id":"34742A","date":"2025-07-31","time":"08:56:13"}
"""

import logging
from datetime import datetime, timedelta
from django.db import transaction
from django.utils import timezone
from accounts.models import Console, Sites, Timezones        # adjust import path if needed
from ingest.management.commands.gateway_cmd import mqtt_publish_raw

LOG = logging.getLogger("gateway.registration")


def _parse_ts(p: dict):
    """Return (date, time) – fall back to *now* if fields are bad/missing."""
    try:
        ts = datetime.strptime(f"{p['date']} {p['time']}", "%Y-%m-%d %H:%M:%S")
    except Exception:
        ts = timezone.localtime()
    return ts.date(), ts.time()


def _compute_site_local_dt(console: Console) -> datetime:
    """Return current datetime adjusted by site timezone offset.

    Falls back to current UTC if site/timezone is missing.
    """
    try:
        site = Sites.objects.filter(uid=console).first()
        if not site or not site.time_zone:
            return timezone.now()

        tz_id_str = str(site.time_zone).strip()
        if not tz_id_str.isdigit():
            return timezone.now()

        tz = Timezones.objects.filter(id=int(tz_id_str)).values("utc_offset").first()
        if not tz:
            return timezone.now()

        offset = (tz.get("utc_offset") or "").strip()
        # Expect formats like "+10:00", "-04:30", or "0:00"
        sign = 1
        if offset.startswith("-"):
            sign = -1
        offset_clean = offset.lstrip("+-")
        try:
            hours_str, mins_str = offset_clean.split(":", 1)
            hours = int(hours_str or 0)
            mins = int(mins_str or 0)
        except Exception:
            return timezone.now()

        return timezone.now() + timedelta(hours=sign * hours, minutes=sign * mins)
    except Exception:
        # Be resilient – never break registration on TZ issues
        return timezone.now()


def _send_site_time(device_id: str, console: Console) -> None:
    """Publish TIME:YYYY-MM-DD HH:MM to gateway/<device_id> using site local time."""
    try:
        local_dt = _compute_site_local_dt(console)
        payload = f"TIME:{local_dt.strftime('%Y-%m-%d %H:%M:%S')}"
        mqtt_publish_raw(device_id, payload, qos=1, retain=False)
        LOG.info("🕒 sent %s to gateway/%s", payload, device_id)
    except Exception as exc:
        LOG.error("failed to send TIME command to %s: %s", device_id, exc, exc_info=True)


def handle(serial: str | None, payload: dict) -> None:
    # ── 0.  Determine the serial -------------------------------------------------
    if not serial:                         # legacy topic → use id in body
        serial = payload.get("id")
    if not serial:
        LOG.error("registration message without id – payload=%s", payload)
        return

    date, time = _parse_ts(payload)
    fw = payload.get("fw")
    # ── 1.  Insert / update console row -----------------------------------------
    try:
        with transaction.atomic():
            console, created = Console.objects.get_or_create(
                device_id=serial,                  # <— lands in console.device_id
                defaults={                         # fill mandatory NON-NULL cols
                    "device_type":    30,
                    "console_status": "On site",
                    "firmware":     fw,
                    "man_data": date,
                },
            )

            # heartbeat (also refreshes existing rows)
            console.last_conndate = date
            console.last_conntime = time
            if fw and fw != console.firmware:       # ignore if missing or unchanged
                console.firmware = fw
                fields = ["last_conndate", "last_conntime", "firmware"]
            else:
                fields = ["last_conndate", "last_conntime"]

            console.save(update_fields=fields)
    except Exception as exc:
        LOG.error("registration failed for %s: %s", serial, exc, exc_info=True)
        return

    LOG.info("✅ console %s %s", serial, "registered" if created else "updated")

    # ── 2.  Send local TIME command back to the console ------------------------
    _send_site_time(console.device_id or serial, console)


# MQTT topic → handler map
TOPICS = {
    "gateway/registration":        handle,   # legacy
    # "gateway/+/registration":      handle,   # new
}
