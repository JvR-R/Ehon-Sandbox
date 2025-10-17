"""
Handle messages published on  gateway/transactions
"""

import logging
from datetime import datetime
from django.db import transaction as db_tx
from django.utils import timezone

from accounts.models import Console, ClientTransaction as Tx   # ← added Console

LOG = logging.getLogger("gateway.transactions")


def handle(serial: str | None, payload: dict) -> None:
    """
    Example payload:
    {"piusiSerial":207090,"date":"280725","time":"0517",
     "driverId":"2","odo":"6","rego":"6","driver":"ALAN","volume":"19.15"}
    """
    if not serial:
        LOG.error("topic missing device_id segment")
        return

    console = Console.objects.filter(device_id=serial).only("uid").first()

    if console is None:
        LOG.warning("unknown console device_id=%s – message discarded", serial)
        return
    uid = console.uid

    # ── timestamp comes from the payload (yyMMdd HHmm) ──────────────────
    try:
        ts = datetime.strptime(payload["date"] + payload["time"], "%d%m%y%H%M")
    except Exception as exc:
        LOG.error("bad date/time in payload %r: %s", payload, exc)
        return

    tank_no = int(payload.get("tank_no", 1))        # default to 1 if absent

    try:
        with db_tx.atomic():
            Tx.objects.create(
                uid_id            = uid,
                fms_id            = payload["piusiSerial"],
                transaction_date  = ts.date(),
                transaction_time  = ts.time(),
                card_number       = payload["driverId"],
                card_holder_name  = payload["driver"],
                odometer          = payload["odo"],
                registration      = payload["rego"],
                dispensed_volume  = float(payload["volume"]),
                tank_id           = tank_no,
                pump_id           = tank_no,
                actions           = "DISPENSE",  # Required field
            )
    except Exception as exc:
        LOG.error("transaction insert failed (uid=%s): %s", uid, exc, exc_info=True)
        return

    LOG.info("✅ processed transaction uid=%s vol=%s L", uid, payload["volume"])


# MQTT topic filter ➜ handler
TOPICS = {
    # "gateway/transactions": handle,
    "gateway/+/transactions": handle,   # enable if you later include <serial>
}
