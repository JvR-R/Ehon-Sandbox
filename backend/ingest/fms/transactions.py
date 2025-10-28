"""
Handle messages published on  fms/transactions
"""

import logging
from datetime import datetime
from django.db import transaction as db_tx
from django.utils import timezone

from accounts.models import Console, ClientTransaction as Tx   # ← added Console

LOG = logging.getLogger("fms.transactions")


def handle(serial: str | None, payload: dict) -> None:
    """
    Example payload:
    {"data":{"driverID":0,"vehicleID":0,"driverName":"","rego":"","project_num":"",
     "odo":0,"pump_num":1,"pulses":27,"Stop_method":2,
     "startDateTime":"2025-10-20 10:52:39","endDateTime":"2025-10-20 10:53:12",
     "volume":1.8120805369127517,"startDip":0,"endDip":0}}
    """
    if not serial:
        LOG.error("topic missing device_id segment")
        return

    console = Console.objects.filter(device_id=serial).only("uid").first()

    if console is None:
        LOG.warning("unknown console device_id=%s – message discarded", serial)
        return
    uid = console.uid

    # Extract data from the nested "data" object
    data = payload.get("transaction", {})

    # ── timestamp comes from startDateTime (YYYY-MM-DD HH:MM:SS) ──────────────────
    try:
        ts = datetime.strptime(data["startDateTime"], "%Y-%m-%d %H:%M:%S")
    except Exception as exc:
        LOG.error("bad date/time in payload %r: %s", payload, exc)
        return

    pump_num = int(data.get("pump_num", 1))        # default to 1 if absent

    try:
        with db_tx.atomic():
            Tx.objects.create(
                uid_id            = uid,
                fms_id            = uid,  # Use device_id as fms_id
                transaction_date  = ts.date(),
                transaction_time  = ts.time(),
                card_number       = data.get("driverID"),
                card_holder_name  = data.get("driverName", ""),
                odometer          = data.get("odo", 0),
                registration      = data.get("rego", ""),
                dispensed_volume  = float(data.get("volume", 0)),
                tank_id           = pump_num,
                pump_id           = pump_num,
                stop_method       = data.get("Stop_method"),
                pulses            = data.get("pulses"),
                startDateTime     = data.get("startDateTime"),  # Store as string
                endDateTime       = data.get("endDateTime"),    # Store as string
                startDip          = data.get("startDip"),
                endDip            = data.get("endDip"),
                actions           = "DISPENSE",  # Required field
            )
    except Exception as exc:
        LOG.error("transaction insert failed (uid=%s): %s", uid, exc, exc_info=True)
        return

    LOG.info("✅ processed transaction uid=%s vol=%s L", uid, data.get("volume", 0))


# MQTT topic filter ➜ handler
TOPICS = {
    # "fms/transactions": handle,
    "fms/+/transactions": handle,   # enable if you later include <serial>
}
