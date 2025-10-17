"""
Handle messages published on  gateway/<serial>/delivery
Place in: ingest/gateway/delivery.py
"""

import logging
from decimal import Decimal
from django.utils import timezone
from django.db import transaction as db_tx

from accounts.models import (
    Tanks,
    DipreadHistoric,
    DeliveryHistoric,   # new table for the END event
    Console,
)

LOG = logging.getLogger("gateway.delivery")


def handle(serial: str | None, payload: dict) -> None:
    """
    Payload examples
    ----------------
    {"event":"start","tank":1,"date":"2025-07-30","time":"15:26:26","start_vol":571}
    {"event":"end","tank":1,"date":"2025-07-30","time":"15:27:03",
     "start_vol":571,"end_vol":1145,"delivered":573}
    """
    if not serial:
        LOG.error("topic missing device_id segment")
        return

    console = Console.objects.filter(device_id=serial).only("uid").first()
    if console is None:
        LOG.warning("unknown console device_id=%s – message discarded", serial)
        return

    uid       = console.uid
    tank_id   = int(payload.get("tank", -1))
    event     = payload.get("event")
    now       = timezone.now()
    date, tim = now.date(), now.time()

    if event not in ("start", "end"):
        LOG.error("unrecognised delivery event=%s for uid=%s tank=%s", event, uid, tank_id)
        return

    # Pick the correct volume field for each event
    vol      = Decimal(payload["start_vol"]) if event == "start" else Decimal(payload["end_vol"])
    delivered = Decimal(payload.get("delivered", 0))

    try:
        with db_tx.atomic():

            # ── 1. UPDATE Tanks ---------------------------------------------------
            updated = (
                Tanks.objects
                     .filter(uid_id=uid, tank_id=tank_id)
                     .update(
                         dipr_date      = payload["date"],
                         dipr_time      = payload["time"],
                         current_volume = vol,
                     )
            )
            if updated == 0:
                LOG.warning("no Tanks row for uid=%s tank=%s", uid, tank_id)

            # ── 2. INSERT DipreadHistoric ---------------------------------------
            #   - ullage is mandatory, fallback to 0 when unknown
            DipreadHistoric.objects.create(
                uid              = uid,
                transaction_date = payload["date"],
                transaction_time = payload["time"],
                tank_id          = tank_id,
                current_volume   = vol,
                ullage           = Decimal("0"),
            )

            # ── 3. INSERT DeliveryHistoric  (only on END) ------------------------
            if event == "end":
                # Check tank capacity and skip if delivery is less than 3% of capacity
                tank = Tanks.objects.filter(uid_id=uid, tank_id=tank_id).only('capacity').first()
                if tank and tank.capacity:
                    capacity_threshold = Decimal(tank.capacity) * Decimal("0.03")  # 3% of capacity
                    if delivered < capacity_threshold:
                        LOG.info("⏭️  Delivery skipped for uid=%s tank=%s: delivery=%s < 3%% of capacity (%s < %s)", 
                               uid, tank_id, delivered, delivered, capacity_threshold)
                    else:
                        DeliveryHistoric.objects.create(
                            uid              = uid,
                            transaction_date = payload["date"],
                            transaction_time = payload["time"],
                            tank_id          = tank_id,
                            current_volume   = vol,
                            delivery         = delivered,
                        )
                        LOG.info("✅ DeliveryHistoric created: delivery=%s >= 3%% of capacity (%s)", 
                               delivered, capacity_threshold)
                else:
                    # If tank not found or capacity is null, log warning but still create record
                    LOG.warning("Tank capacity not found for uid=%s tank=%s, creating DeliveryHistoric anyway", uid, tank_id)
                    DeliveryHistoric.objects.create(
                        uid              = uid,
                        transaction_date = payload["date"],
                        transaction_time = payload["time"],
                        tank_id          = tank_id,
                        current_volume   = vol,
                        delivery         = delivered,
                    )

    except Exception as exc:
        LOG.error("delivery processing failed (uid=%s tank=%s): %s", uid, tank_id, exc, exc_info=True)
        return

    LOG.info("✅ %s event processed for console %s tank %s, payload: %s", event, uid, tank_id, payload)
    

# MQTT topic filter ➜ handler
TOPICS = {
    "gateway/+/delivery": handle,
}
