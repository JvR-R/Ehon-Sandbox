"""
Handle   fms/<serial>/env   packets.

Payload example (signal/GPS optional):
    {"tCPU":43.24, "vCPU":3.31, "vIn":24.3, "signal_percent": null, "gps_lat": null, "gps_lon": null}
"""

import logging
from django import db
from django.utils import timezone
from accounts.models import Console, ConsoleEnv     # adjust import path

LOG = logging.getLogger("fms.env")

def handle(serial: str | None, payload: dict) -> None:
    db.close_old_connections()                 # drops stale MySQL sockets

    if not serial:
        LOG.error("env topic missing serial")
        return

    console = Console.objects.filter(device_id=serial).only("uid", "console_coordinates").first()
    if console is None:
        LOG.warning("unknown console %s – message ignored", serial)
        return

    gps_lat = payload.get("gps_lat")
    gps_lon = payload.get("gps_lon")
    
    try:
        ConsoleEnv.objects.create(
            uid   = console,
            tcpu  = payload.get("tCPU"),
            vcpu  = payload.get("vCPU"),
            vin   = payload.get("vIn"),
            rssi  = payload.get("signal_percent"),
            gps_lat  = gps_lat,
            gps_lon  = gps_lon,
            # recorded_at auto-set
        )
    except Exception as exc:
        LOG.error("env insert failed for %s: %s", serial, exc, exc_info=True)
        return

    # Update console_coordinates if both GPS values are not null
    if gps_lat is not None and gps_lon is not None:
        try:
            console.console_coordinates = f"{gps_lat},{gps_lon}"
            console.save(update_fields=["console_coordinates"])
            LOG.info("📍 updated GPS coordinates for console %s: %s,%s", serial, gps_lat, gps_lon)
        except Exception as exc:
            LOG.error("console_coordinates update failed for %s: %s", serial, exc, exc_info=True)

    # LOG.info("🌡️ env sample saved for console %s (%s)", serial, timezone.now())

# MQTT topic map
TOPICS = {"fms/+/env": handle}
