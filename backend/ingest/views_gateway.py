# views_gateway.py
import json
import re
import logging
from django.http import JsonResponse, HttpResponseBadRequest
from django.views.decorators.http import require_POST
from django.views.decorators.csrf import csrf_exempt
from django.db import connection

from ingest.management.commands.gateway_cmd import (
    mqtt_publish,
    mqtt_publish_raw,
    mqtt_publish_raw_and_wait,
)



log = logging.getLogger(__name__)

# ────────────────────────────────────────────────────────────────────
# Helpers: normalize/validate console device_id (MAC/UID-like)
_HEX = re.compile(r'^[0-9A-F]+$')

def _normalize_id(s: str) -> str:
    """Strip separators and upper-case."""
    return s.replace(':', '').replace('-', '').upper()

def _is_valid_console_id(s: str | None) -> bool:
    """
    Accept only 6 or 12 hex chars (digits allowed).
    This allows IDs like '344494' and '3519BA'.
    It rejects date-like '20250812' (length 8) and non-hex.
    """
    if not s or not isinstance(s, str):
        return False
    n = _normalize_id(s)
    if len(n) not in (6, 12):
        return False
    return bool(_HEX.match(n))

def _select_device_id_by_uid(uid) -> str | None:
    """Look up console.device_id by console.uid and validate it."""
    with connection.cursor() as cur:
        cur.execute("SELECT device_id FROM console WHERE uid=%s", [str(uid)])
        row = cur.fetchone()
    if not row:
        return None
    dev = (row[0] or "").strip()
    return _normalize_id(dev) if _is_valid_console_id(dev) else None

def _select_device_id_by_tank_device_id(td_id) -> str | None:
    """Look up console.device_id via tank_device.id -> console.uid join."""
    with connection.cursor() as cur:
        cur.execute("""
            SELECT c.device_id
              FROM tank_device td
              JOIN console c ON c.uid = td.uid
             WHERE td.id = %s
        """, [td_id])
        row = cur.fetchone()
    if not row:
        return None
    dev = (row[0] or "").strip()
    return _normalize_id(dev) if _is_valid_console_id(dev) else None

def _resolve_device_id(data: dict) -> str | None:
    """
    Resolution order:
      1) device_id provided:
         a) if valid 6/12-hex -> use it as the topic id
         b) if all digits but NOT valid length -> treat as uid (back-compat)
      2) uid field -> console.device_id
      3) tank_device_id -> console.uid -> device_id
    Returns normalized console id (upper-case, no separators) or None.
    """
    # 1) direct device_id?
    if "device_id" in data and data["device_id"] is not None:
        s = str(data["device_id"]).strip()
        if _is_valid_console_id(s):
            return _normalize_id(s)
        # back-compat: numeric but wrong length → likely a UID
        if s.isdigit():
            mac = _select_device_id_by_uid(s)
            if mac:
                return mac
        # else fall through

    # 2) explicit uid
    uid = data.get("uid")
    if uid is not None:
        mac = _select_device_id_by_uid(uid)
        if mac:
            return mac

    # 3) via tank_device_id
    td = data.get("tank_device_id")
    if td is not None:
        mac = _select_device_id_by_tank_device_id(td)
        if mac:
            return mac

    return None

# ────────────────────────────────────────────────────────────────────
@csrf_exempt
@require_POST
def send_gateway_cmd(request):
    try:
        data = json.loads(request.body or "{}")
    except ValueError:
        return HttpResponseBadRequest("invalid JSON")

    device_id = _resolve_device_id(data)
    if not device_id:
        log.warning("Gateway CMD: could not resolve console device_id from payload=%s", data)
        return HttpResponseBadRequest("could not resolve a valid console device_id")

    # Known OK/FAIL tokens per response sub-topic
    _OK_FAIL = {
        "chart":    ({"charts_enq_ok", "chart_ok", "charts_ok"},
                     {"charts_enq_fail", "chart_fail", "charts_fail"}),
        "products": ({"products_ok"}, {"products_fail"}),
        "tanks":    ({"tanks_ok"},    {"tanks_fail"}),
        "ports":    ({"ports_ok"},    {"ports_fail"}),
    }

    # One-message exact JSON (payload_raw is already serialized in the client)
    if "payload_raw" in data:
        wait_for = (data.get("wait_for") or "").strip()
        if wait_for:
            okv, failv = _OK_FAIL.get(wait_for, (set(), set()))
            timeout = int(data.get("wait_timeout", 30))
            result = mqtt_publish_raw_and_wait(
                device_id=device_id,
                payload_str=data["payload_raw"],
                resp_suffix=wait_for,
                ok_values=okv,
                fail_values=failv,
                timeout=timeout,
            )
            # ok = True ONLY if we matched an explicit OK token
            return JsonResponse({
                "ok": (result["ok"] is True),
                "matched": result["ok"],  # True/False/None
                "got": result["got"],
                "reply": {"topic": result["topic"], "payload": result["payload"]},
                "elapsed": round(result["elapsed"], 3),
            })

        # no wait requested → fire-and-forget
        mqtt_publish_raw(device_id, data["payload_raw"], qos=1, retain=False)
        return JsonResponse({"ok": True})

    # Back-compat single {cmd: value}
    cmd = data.get("cmd")
    val = data.get("value")
    if not (cmd and val):
        return HttpResponseBadRequest("payload_raw OR (cmd,value) required")

    mqtt_publish(device_id, cmd, val)
    return JsonResponse({"ok": True})

