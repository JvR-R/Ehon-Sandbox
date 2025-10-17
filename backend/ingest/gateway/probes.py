# ingest/gateway/probes.py
"""
Process   gateway/<serial>/probes/<tankNum>   packets.

• UPDATE  config_ehon_gateway.probe_conn based on connection status

Topic pattern: gateway/<serial>/probes/<tankNum>
Payload: {"id": <tankNum>, "conn": true/false}

Example:
    gateway/348423/probes/1: {"id": 1, "conn":false}
    gateway/348423/probes/2: {"id": 2, "conn":true}

Notes
-----
- Updates probe_conn to 1 if conn=true, 0 if conn=false
- Filters records by uid (from serial) and tank_id (from payload 'id' field)
- The tank_id is extracted from payload.get("id"), same as tanks.py
"""

import logging
from django.db import transaction

from accounts.models import (
    Console,
    ConfigEhonGateway,
)

LOG = logging.getLogger("gateway.probes")

# ───────────────────────────────────────────────────────────────────────────
def _as_bool(v) -> bool:
    """Convert various representations to boolean."""
    if isinstance(v, bool):
        return v
    s = str(v).strip().lower()
    return s in ("1", "true", "yes", "y", "on")

def _as_int(v, default=0) -> int:
    """Convert to int with fallback default."""
    try:
        return int(v)
    except Exception:
        return default

# ───────────────────────────────────────────────────────────────────────────
def _handle_probe_message(serial: str, tank_id: int, payload: dict) -> None:
    """
    Handle probe connection status updates.
    
    Args:
        serial: Gateway serial number (from MQTT topic)
        tank_id: Tank ID (from MQTT topic path after probes/)
        payload: Dict with 'conn' field (true/false)
    """
    console = Console.objects.filter(device_id=serial).only("uid").first()
    if console is None:
        LOG.warning("unknown console %s – message ignored", serial)
        return

    uid = console.uid
    
    # Get connection status from payload
    conn = payload.get("conn", False)
    probe_conn_value = 1 if _as_bool(conn) else 0

    # ── update config_ehon_gateway.probe_conn ---------------------------------
    try:
        with transaction.atomic():
            # Update config_ehon_gateway records matching this uid and tank_id
            updated_count = ConfigEhonGateway.objects.filter(
                uid=console,
                tank_id=tank_id
            ).update(
                probe_conn=probe_conn_value
            )
            
            if updated_count == 0:
                LOG.warning(
                    "no config_ehon_gateway records found for console %s (uid=%s) tank_id=%s",
                    serial, uid, tank_id
                )
                return
            
            LOG.info(
                "✅ probe for console %s tank_id=%s: probe_conn=%s (%d record%s updated)",
                serial, tank_id, probe_conn_value, updated_count,
                "s" if updated_count != 1 else ""
            )

    except Exception as exc:
        LOG.error(
            "probe processing failed for %s tank_id %s: %s",
            serial, tank_id, exc, exc_info=True
        )
        LOG.error("Failed payload was: %s", payload)
        return


# ───────────────────────────────────────────────────────────────────────────
def handle(serial: str | None, payload: dict) -> None:
    """
    Wrapper handler compatible with the standard gateway dispatcher.
    This function is called by the MQTT gateway dispatcher.
    
    Args:
        serial: Gateway serial number (from MQTT topic segment 2)
        payload: Dict with 'conn' field and 'id' field (tank_id)
    """
    if not serial:
        LOG.error("probes topic missing serial")
        return
    
    # Get tank_id from payload, same as tanks.py does
    tank_id = _as_int(payload.get("id"), 0)
    
    if tank_id <= 0:
        LOG.error("probe message without valid id – payload=%s", payload)
        return
    
    _handle_probe_message(serial, tank_id, payload)


# MQTT topic map
TOPICS = {"gateway/+/probes/+": handle}

