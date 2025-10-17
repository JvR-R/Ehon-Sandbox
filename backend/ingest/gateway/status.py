"""
Handle heartbeat messages on  gateway/<device_id>/status
Payload example:  {"connected": []}
"""

import logging
from django.utils import timezone
from accounts.models import Console            # adjust import path if needed

LOG = logging.getLogger("gateway.status")


def handle(serial: str | None, payload: dict) -> None:
    # serial  = second segment of the topic, e.g. "34742A" in
    #           gateway/34742A/status              (already extracted by gateway.py)

    if not serial:
        LOG.error("status topic missing device‑id segment")
        return

    now = timezone.now()

    try:
        updated = (
            Console.objects
                   .filter(device_id=serial)
                   .update(
                       last_conndate = now.date(),
                       last_conntime = now.time(),
                   )
        )
        if updated == 0:
            LOG.warning("no Console row found for device_id=%s", serial)
            return
    except Exception as exc:
        LOG.error("status update failed for device_id=%s: %s", serial, exc, exc_info=True)
        return

    LOG.info("✅ heartbeat recorded for console %s", serial)


# MQTT topic filter ➜ handler
TOPICS = {
    "gateway/+/status": handle,      # matches every console’s heartbeat
}
