# ingest/gateway/tanks.py
"""
Process   gateway/<serial>/tanks/<tankNum>   packets.

• INSERT / UPDATE  Tanks              (capacity, product, offset_tank, site_id, client_id)
• INSERT / UPDATE  alarms_config      (HH/H/L/LL + client_id)
• INSERT / UPDATE  config_ehon_gateway (shape, H/W/D, probe_id, enabled, raw_bias_counts)
• Create a stub Sites row when needed.
• Create a stub TankDevice row when needed (to serve as config_ehon_gateway PK).

Notes
-----
- Tanks row is located by (uid_id, tank_id) only. All mutable fields go in `defaults`.
- config_ehon_gateway is keyed by tank_device_id (OneToOne with tank_device.id).
- We DO NOT include offset_tank in the lookup predicate; it is updated via defaults.
"""

import logging
from django.db import transaction

from accounts.models import (
    Console,
    ConsoleAssociation,
    Sites,
    Tanks,
    AlarmsConfig,
    # The following two models should map your existing tables 1:1:
    #   tank_device(id PK, tank_uid, uid, role, port, installed_at...)
    #   config_ehon_gateway(tank_device_id PK → tank_device.id, ...)
    TankDevice,
    ConfigEhonGateway,
)

LOG = logging.getLogger("gateway.tanks")

# ── mapping tables ─────────────────────────────────────────────────────────
ALARM_FIELD = {
    "HIGH_HIGH": "crithigh_alarm",
    "HIGH":      "high_alarm",
    "LOW":       "low_alarm",
    "LOW_LOW":   "critlow_alarm",
}

PRODUCT_FIELD = {
    "Diesel":  "1",
    "ULP 91":  "2",
    "ULP 95":  "3",
    "ULP 98":  "4",
    "AdBlue":  "5",
    "Water":   "7",
}

# ───────────────────────────────────────────────────────────────────────────
def _as_bool(v) -> bool:
    if isinstance(v, bool):
        return v
    s = str(v).strip().lower()
    return s in ("1", "true", "yes", "y", "on")

def _as_int(v, default=0) -> int:
    try:
        return int(v)
    except Exception:
        return default

def _as_float(v, default=0.0) -> float:
    try:
        return float(v)
    except Exception:
        return default

# ───────────────────────────────────────────────────────────────────────────
def handle(serial: str | None, payload: dict) -> None:
    if not serial:
        LOG.error("tanks topic missing serial")
        return

    console = Console.objects.filter(device_id=serial).only("uid").first()
    if console is None:
        LOG.warning("unknown console %s – message ignored", serial)
        return

    uid     = console.uid
    tank_id = _as_int(payload.get("id"), 0)
    if tank_id <= 0:
        LOG.error("tank message without valid id – payload=%s", payload)
        return

    # treat True/False/1/0/"true"/"false"
    enabled = _as_bool(payload.get("enabled", True))
    if not enabled:
        LOG.info("tank %s for console %s has enabled=False – skipped", tank_id, serial)
        return

    # Use float for offset; your model should be FloatField or DecimalField
    offset = _as_float(payload.get("offset"), 0.0)

    # ── grab client_id from ConsoleAssociation (fallback 0) ────────────────
    assoc = (
        ConsoleAssociation.objects
        .filter(uid_id=uid)
        .only("client_id")
        .first()
    )
    client_id_val = assoc.client_id if assoc and assoc.client_id else 0

    # ── choose / create site_id to store on INSERT ─────────────────────────
    site_id_for_tank = (
        Tanks.objects.filter(uid_id=uid)
             .values_list("site_id", flat=True).order_by().first()
        or Sites.objects.filter(uid_id=uid)
             .values_list("site_id", flat=True).order_by().first()
    )
    if site_id_for_tank is None:
        site = Sites.objects.create(
            uid_id      = uid,
            client_id   = client_id_val,
            site_name   = f"AUTO-{serial}",
            site_status = "AUTO",
        )
        site_id_for_tank = site.site_id

    # ── unpack alarms ------------------------------------------------------
    alarm_defaults = {v: 0 for v in ALARM_FIELD.values()}
    for a in payload.get("alarms", []):
        fld = ALARM_FIELD.get(a.get("type"))
        if fld:
            alarm_defaults[fld] = _as_int(a.get("level"), 0)
    alarm_defaults["alarm_enable"] = 0           # column is NOT NULL

    product_code = _as_int(PRODUCT_FIELD.get(payload.get("product"), "0"), 0)

    # ── normalize gateway-config fields -----------------------------------
    shape   = _as_int(payload.get("shape"), 0)
    height  = _as_int(payload.get("height"), 0)
    width   = _as_int(payload.get("width"), 0)
    depth   = _as_int(payload.get("depth"), 0)
    probe   = payload.get("probeId")
    probe   = _as_int(probe, None) if probe is not None else None
    raw_bias = _as_int(payload.get("raw_bias_counts"), 0)

    # ── upsert inside one transaction -------------------------------------
    try:
        with transaction.atomic():

            # Tanks ---------------------------------------------------------
            Tanks.objects.update_or_create(
                uid_id   = uid,
                tank_id  = tank_id,
                defaults = {
                    "site_id":     site_id_for_tank,
                    "client_id":   client_id_val,
                    "capacity":    payload.get("capacity"),
                    # "tank_shape": payload.get("shape"),  # store geometry in config table instead
                    # "height":     payload.get("height"),
                    # "width":      payload.get("width"),
                    # "depth":      payload.get("depth"),
                    "product_id":  product_code,
                    "alert_flag":  0,
                    "offset_flag": 0,
                    "offset_tank": offset,   # ← important: stays in defaults (update) not lookup
                },
            )

            # retrieve the just-upserted Tanks row to resolve tank_uid
            tank_obj = Tanks.objects.only("tank_uid", "site_id").get(uid_id=uid, tank_id=tank_id)

            # alarms_config -------------------------------------------------
            AlarmsConfig.objects.update_or_create(
                uid      = uid,                 # integer FK in your schema
                tank_id  = tank_id,
                site_id  = site_id_for_tank,
                defaults = alarm_defaults | {"client_id": client_id_val},
            )

            # TankDevice (stub if missing) ----------------------------------
            # A stable PK (id) is needed as the PK/OneToOne for config_ehon_gateway.
            td, _ = TankDevice.objects.get_or_create(
                uid      = uid,
                tank_uid = tank_obj.tank_uid,
                defaults = {"role": "other"},  # or "gateway" if device_type==30 upstream
            )

            # config_ehon_gateway -------------------------------------------
            # Anchor by tank_device (PK). All other identifying fields belong in defaults
            # so changes are updated rather than causing duplicate PK conflicts.
            ConfigEhonGateway.objects.update_or_create(
                tank_device = td,
                defaults = {
                    "tank_uid":        tank_obj,     # FK by to_field="tank_uid"
                    "uid":             console,      # Console FK by to_field="uid"
                    "tank_id":         tank_id,      # logical 1..N
                    "shape":           shape,
                    "height":          height,
                    "width":           width,
                    "depth":           depth,
                    "probe_id":        probe,
                    "enabled":         enabled,
                    "offset":          offset,       # from payload offset field
                    "raw_bias_counts": raw_bias,
                    # updated_at is auto_now on the model
                },
            )

    except Exception as exc:
        LOG.error("tanks processing failed for %s tank %s: %s",
                  serial, tank_id, exc, exc_info=True)
        # Log the full payload for debugging
        LOG.error("Failed payload was: %s", payload)
        return

    LOG.info("✅ tank %s for console %s processed, payload %s", tank_id, serial, payload)


# MQTT topic map
TOPICS = {"gateway/+/tanks/+": handle}
