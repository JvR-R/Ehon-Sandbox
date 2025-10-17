# ingest/gateway/ports.py
"""
Handle  gateway/<serial>/ports   messages.

Incoming payload example:
[
  {"index":0,"mode":1,"tankNum":1,"fms":0},
  {"index":1,"mode":3,"tankNum":0,"fms":207090}
]
"""
import logging
from django.db import transaction as db_tx
from accounts.models import Console, Tanks

LOG = logging.getLogger("gateway.ports")

# ── mappings ---------------------------------------------------------------
UART_COL  = {0: "uart5", 1: "uart3"}          # index -> Console column
UART_CODE = {0: 0, 1: 201, 2: 202, 3: 104}   # mode  -> value to store
GAUGE_TYPE = {1: 201, 2: 202}                # OCIO / Modbus only
GAUGE_UART = {0: 5, 1: 3}                    # index -> uart code

# ───────────────────────────────────────────────────────────────────────────
def handle(serial: str | None, payload: list[dict]) -> None:
    if not serial:
        LOG.error("ports topic missing serial")
        return

    console = Console.objects.filter(device_id=serial).first()
    if not console:
        LOG.warning("unknown console %s – message ignored", serial)
        return

    try:
        with db_tx.atomic():

            # ── 1. Update Console UARTs ---------------------------------------
            dirty_cols = []
            for port in payload:
                idx  = int(port.get("index", -1))
                mode = int(port.get("mode", 0))

                col = UART_COL.get(idx)
                if col and getattr(console, col, None) != UART_CODE.get(mode, 0):
                    setattr(console, col, UART_CODE[mode])
                    dirty_cols.append(col)

            if dirty_cols:
                console.save(update_fields=dirty_cols)

            # ── 2. Update Tanks (OCIO / Modbus only) --------------------------
            for port in payload:
                mode     = int(port.get("mode", 0))
                tank_id  = int(port.get("tankNum", 0))
                idx      = int(port.get("index", -1))

                # skip Piusi (mode 3) or ghost tanks (tankNum 0)
                if tank_id == 0 or mode not in GAUGE_TYPE:
                    continue

                tank = Tanks.objects.filter(uid=console, tank_id=tank_id).first()
                if not tank:
                    LOG.warning("no Tanks row for uid=%s tank=%s", console.uid, tank_id)
                    continue

                tank.tank_gauge_type = GAUGE_TYPE[mode]
                tank.tank_gauge_uart = str(GAUGE_UART[idx])
                LOG.info("uid=%s tank=%s idx=%s ⇢ gauge_uart=%s",console.uid, tank_id, idx, tank.tank_gauge_uart)

                tank.save(update_fields=["tank_gauge_type", "tank_gauge_uart"])

    except Exception as exc:
        LOG.error("ports processing failed for %s: %s", serial, exc, exc_info=True)
    else:
        LOG.info("✅ ports processed for console %s", serial)


# MQTT topic map
TOPICS = {"gateway/+/ports": handle}
