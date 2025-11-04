# ingest/gateway/cc.py
"""
Process gateway/<serial>/cc packets.

• INSERT fuel_quality records for contamination control readings

Topic pattern: gateway/<serial>/cc
Payload example:
{
  "date": "2025-11-04",
  "time": "12:36:10",
  "readings": [
    {
      "tank": 1,
      "iso4": 11,
      "iso6": 9,
      "iso14": 3,
      "particles": 12,
      "bubbles": 25,
      "shapes": {
        "cutting": 0,
        "sliding": 0,
        "fatigue": 0,
        "fibre": 0,
        "air": 0,
        "unknown": 0
      },
      "tempC": 31.3
    }
  ]
}

Notes
-----
- Each reading in the readings array creates one fuel_quality row
- Maps JSON fields to database columns:
  - iso4 -> particle_4um
  - iso6 -> particle_6um
  - iso14 -> particle_14um
  - bubbles -> fq_bubbles
  - shapes.cutting -> fq_cutting
  - shapes.sliding -> fq_sliding
  - shapes.fatigue -> fq_fatigue
  - shapes.fibre -> fq_fibre
  - shapes.air -> fq_air
  - shapes.unknown -> fq_unknown
  - tempC -> fq_temp
"""

import logging
from django.db import transaction

from accounts.models import (
    Console,
    ConsoleAssociation,
    FuelQuality,
)

LOG = logging.getLogger("gateway.cc")

# ───────────────────────────────────────────────────────────────────────────
def _as_int(v, default=None):
    """Convert to int with fallback default."""
    try:
        return int(v) if v is not None else default
    except (ValueError, TypeError):
        return default

def _as_float(v, default=None):
    """Convert to float with fallback default."""
    try:
        return float(v) if v is not None else default
    except (ValueError, TypeError):
        return default

# ───────────────────────────────────────────────────────────────────────────
def handle(serial: str | None, payload: dict) -> None:
    """
    Process contamination control (CC) readings from gateway.
    
    Args:
        serial: Gateway serial number (from MQTT topic segment 2)
        payload: Dict with 'date', 'time', and 'readings' array
    """
    if not serial:
        LOG.error("cc topic missing serial")
        return

    console = Console.objects.filter(device_id=serial).only("uid").first()
    if console is None:
        LOG.warning("unknown console %s – message ignored", serial)
        return

    uid = console.uid
    
    # Get client_id from ConsoleAssociation
    assoc = (
        ConsoleAssociation.objects
        .filter(uid_id=uid)
        .only("client_id")
        .first()
    )
    client_id_val = assoc.client_id if assoc and assoc.client_id else None

    # Extract date and time from payload
    fq_date = payload.get("date")
    fq_time = payload.get("time")
    
    if not fq_date or not fq_time:
        LOG.error("cc message missing date or time – payload=%s", payload)
        return

    # Process each reading
    readings = payload.get("readings", [])
    if not readings:
        LOG.warning("cc message has no readings – payload=%s", payload)
        return

    try:
        with transaction.atomic():
            for reading in readings:
                tank_id = _as_int(reading.get("tank"))
                if tank_id is None:
                    LOG.warning("skipping reading without tank id: %s", reading)
                    continue

                # Extract shape data
                shapes = reading.get("shapes", {})
                
                # Create FuelQuality record
                FuelQuality.objects.create(
                    client_id=client_id_val,
                    fq_date=fq_date,
                    fq_time=fq_time,
                    uid=uid,
                    tank_id=tank_id,
                    particle_4um=_as_int(reading.get("iso4")),
                    particle_6um=_as_int(reading.get("iso6")),
                    particle_14um=_as_int(reading.get("iso14")),
                    fq_bubbles=_as_int(reading.get("bubbles")),
                    fq_cutting=_as_int(shapes.get("cutting")),
                    fq_sliding=_as_int(shapes.get("sliding")),
                    fq_fatigue=_as_int(shapes.get("fatigue")),
                    fq_fibre=_as_int(shapes.get("fibre")),
                    fq_air=_as_int(shapes.get("air")),
                    fq_unknown=_as_int(shapes.get("unknown")),
                    fq_temp=_as_float(reading.get("tempC")),
                )
                
                LOG.info(
                    "✅ fuel_quality record saved: console=%s uid=%s tank_id=%s date=%s time=%s",
                    serial, uid, tank_id, fq_date, fq_time
                )

    except Exception as exc:
        LOG.error(
            "cc processing failed for %s: %s",
            serial, exc, exc_info=True
        )
        LOG.error("Failed payload was: %s", payload)
        return

    LOG.info(
        "✅ cc data processed for console %s: %d reading%s saved",
        serial, len(readings), "s" if len(readings) != 1 else ""
    )


# MQTT topic map
TOPICS = {"gateway/+/cc": handle}

