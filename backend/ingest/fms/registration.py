"""
Process legacy registration packets published on
    fms/registration            (no serial in topic)
and the new format
    fms/<serial>/registration   (serial in topic)

Payload example
    {"id":"34742A","date":"2025-07-31","time":"08:56:13"}
"""

import logging
from datetime import datetime, timedelta
from django.db import transaction, connection, close_old_connections
from django.db.utils import OperationalError
from django.utils import timezone
from accounts.models import Console, Sites, Timezones        # adjust import path if needed
from ingest.management.commands.fms_cmd import mqtt_publish_raw

LOG = logging.getLogger("fms.registration")


def _refresh_db_connection() -> None:
    """Ensure we have a live DB connection for long-running workers.

    Close old/stale connections and proactively reconnect.
    """
    try:
        close_old_connections()
        # Proactively attempt to establish connection; safe if already open
        connection.ensure_connection()
    except Exception:
        # Let the caller's error handling/logging deal with failures later
        pass


def _perform_registration(serial: str, date, time, fw) -> tuple[Console, bool]:
    """Execute the registration DB transaction and return (console, created)."""
    with transaction.atomic():
        # Format current datetime for bootup field with +10 hours: "2025-10-01 T 03:10:06"
        bootup_timestamp = (timezone.now() + timedelta(hours=10)).strftime("%Y-%m-%d T %H:%M:%S")
        
        console, created = Console.objects.get_or_create(
            device_id=serial,
            defaults={
                "device_type":    30,
                "console_status": "In Stock",
                "firmware":     fw,
                "man_data": date,
                "bootup": bootup_timestamp,
            },
        )

        # heartbeat (also refreshes existing rows)
        console.last_conndate = date
        console.last_conntime = time
        console.bootup = bootup_timestamp
        if fw and fw != console.firmware:       # ignore if missing or unchanged
            console.firmware = fw
            fields = ["last_conndate", "last_conntime", "bootup", "firmware"]
        else:
            fields = ["last_conndate", "last_conntime", "bootup"]

        # Do not modify console_status on updates; leave existing value as-is

        console.save(update_fields=fields)
        return console, created


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
    """Publish TIME:YYYY-MM-DD HH:MM to fms/<device_id> using site local time."""
    try:
        # Send blank message first
        mqtt_publish_raw(device_id, "", qos=1, retain=False)
        LOG.info("📤 sent blank message to fms/%s", device_id)
        
        local_dt = _compute_site_local_dt(console)
        payload = f"TIME:{local_dt.strftime('%Y-%m-%d %H:%M:%S')}"
        mqtt_publish_raw(device_id, payload, qos=1, retain=False)
        LOG.info("🕒 sent %s to fms/%s", payload, device_id)
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
    _refresh_db_connection()
    try:
        console, created = _perform_registration(serial, date, time, fw)
    except OperationalError as exc:
        # One-time retry for MySQL "Server has gone away" (2006)
        try:
            error_code = exc.args[0] if isinstance(exc.args, tuple | list) and exc.args else None
        except Exception:
            error_code = None

        if error_code == 2006:
            try:
                connection.close()
            except Exception:
                pass
            _refresh_db_connection()
            try:
                console, created = _perform_registration(serial, date, time, fw)
            except Exception as exc2:
                LOG.error("registration failed for %s after reconnect: %s", serial, exc2, exc_info=True)
                return
        else:
            LOG.error("registration failed for %s: %s", serial, exc, exc_info=True)
            return
    except Exception as exc:
        LOG.error("registration failed for %s: %s", serial, exc, exc_info=True)
        return

    LOG.info("✅ console %s %s", serial, "registered" if created else "updated")

    # ── 2.  Send local TIME command back to the console ------------------------
    _send_site_time(console.device_id or serial, console)


# MQTT topic → handler map
TOPICS = {
    "fms/registration":        handle,   # legacy
    # "fms/+/registration":      handle,   # new
}
