"""
Handle messages published on  gateway/dips
"""

import logging
from decimal import Decimal
from django.utils import timezone
from django.db import transaction as db_tx

from accounts.models import Tanks, DipreadHistoric, Console        # ← adjust import path if needed

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
        LOG.warning("unknown console device_id=%s – message discarded", raw_id)
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

                # ── 1. UPDATE Tanks -------------------------------------------------
                updated = (
                    Tanks.objects
                         .filter(uid_id=uid, tank_id=tank_id)
                         .update(
                             dipr_date      = data["date"],
                             dipr_time      = data["time"],
                             current_volume = Decimal(data["vol"]),
                             ullage         = Decimal(data["ull"]),
                             volume_height  = Decimal(data["h"]),
                         )
                )
                if updated == 0:
                    LOG.warning("no Tanks row for uid=%s tank=%s", uid, tank_id)
                # else :
                #     LOG.warning("✅Updated row for uid=%s tank=%s", uid, tank_id)

                # ── 2. INSERT DipreadHistoric -------------------------------------
                DipreadHistoric.objects.create(
                    uid              = uid,
                    transaction_date = data["date"],
                    transaction_time = data["time"],
                    tank_id          = tank_id,
                    current_volume   = Decimal(data["vol"]),
                    ullage           = Decimal(data["ull"]),
                    # the table has many optional columns we don’t fill yet
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



# MQTT topic filter ➜ handler
TOPICS = {
    # "gateway/dips": handle,
    "gateway/+/dips": handle,   # enable if topics later include the serial segment
}
