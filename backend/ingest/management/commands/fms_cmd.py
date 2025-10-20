# ingest/management/commands/fms_cmd.py
"""
Send a single MQTT command to a console (with proper TLS support).

Examples
--------
# Firmware update
python manage.py fms_cmd \
    --device 34742A \
    --cmd    firmware \
    --value  https://fw.ehonenergytech.com.au/v2.5.0.bin \
    --retain

# Push strapping charts
python manage.py fms_cmd \
    --device 34742A \
    --cmd    charts \
    --value  https://fw.ehonenergytech.com.au/34742A_charts.zip
"""
from __future__ import annotations

import json
import logging
import os
import configparser
import threading
import time
import ssl
from urllib.parse import urlparse
from typing import Tuple, Optional, Set, Dict, Any

LOG = logging.getLogger(__name__)

import paho.mqtt.client as mqtt
from django.core.management.base import BaseCommand, CommandError

# ────────────────────────────────────────────────────────────────────────────
# Configuration
CONFIG_PATH = os.environ.get("EHON_CONFIG_PATH", "/home/ehon/config.ini")
CFG = configparser.ConfigParser()
if not CFG.read(CONFIG_PATH):
    raise CommandError(f"Cannot read {CONFIG_PATH}")

# Read [mqttfms] section
if "mqttfms" in CFG:
    MQ = CFG["mqttfms"]
else:
    raise CommandError("Config must contain [mqttfms] section")

# ────────────────────────────────────────────────────────────────────────────
# Helpers: endpoint normalization + TLS setup

def _normalize_endpoint(raw_host: Optional[str], raw_port) -> Tuple[str, int, Optional[str]]:
    """
    Accept either:
      - host: 'ehonenergytech.com.au'
      - URL : 'mqtts://ehonenergytech.com.au:8883'
    and return (host, port, scheme).
    """
    host = (raw_host or "").strip()
    scheme = None
    port = int(raw_port) if raw_port not in (None, "", 0) else None

    if host.startswith(("mqtt://", "mqtts://", "ssl://", "tls://", "tcp://")):
        u = urlparse(host)
        if not u.hostname:
            raise ValueError(f"Bad broker_host: {host}")
        host = u.hostname
        if u.port:
            port = u.port
        scheme = u.scheme

    if port is None:
        port = 8883 if scheme in ("mqtts", "ssl", "tls") else 1883

    return host, int(port), scheme


def _use_tls(cfg: configparser.SectionProxy, port: int, scheme: Optional[str]) -> bool:
    v = str(cfg.get("tls_enable", "")).strip().lower()
    return (
        v in ("1", "true", "yes", "on")
        or scheme in ("mqtts", "ssl", "tls")
        or port == 8883
    )


def _build_tls_context(cfg: configparser.SectionProxy) -> ssl.SSLContext:
    """
    Strict TLS client context:
    - Verifies server certificate + hostname
    - Uses OS trust store (works with public CAs like Let's Encrypt)
    Optional INI keys:
      tls_ca_file      = /path/to/ca-bundle.crt
      tls_min_version  = TLSv1.2 | TLSv1.3
      tls_client_cert  = /path/to/client.crt   (mTLS only)
      tls_client_key   = /path/to/client.key   (mTLS only)
      tls_key_pass     = <optional password>
    """
    ctx = ssl.create_default_context()  # verify_mode=CERT_REQUIRED, check_hostname=True

    cafile = cfg.get("tls_ca_file")
    if cafile:
        ctx.load_verify_locations(cafile=cafile)

    minv = (cfg.get("tls_min_version") or "").upper().replace(".", "")
    if minv in ("TLSV13", "TLS1_3"):
        ctx.minimum_version = ssl.TLSVersion.TLSv1_3
    else:
        ctx.minimum_version = ssl.TLSVersion.TLSv1_2

    cert = cfg.get("tls_client_cert")
    key  = cfg.get("tls_client_key")
    if cert and key:
        ctx.load_cert_chain(certfile=cert, keyfile=key, password=cfg.get("tls_key_pass"))

    return ctx


def _apply_auth_and_tls(cli: mqtt.Client, cfg: configparser.SectionProxy, host: str, port: int, scheme: Optional[str]) -> None:
    """Apply username/password and TLS (if needed) to the client."""
    user = cfg.get("username")
    if user:
        cli.username_pw_set(user, cfg.get("password") or "")

    if _use_tls(cfg, port, scheme):
        ctx = _build_tls_context(cfg)
        cli.tls_set_context(ctx)
        cli.tls_insecure_set(False)  # Don't disable verification

# ────────────────────────────────────────────────────────────────────────────
# Paho client factory compatible with v1.x and v2.x

def _new_client(client_id: str) -> mqtt.Client:
    kwargs: Dict[str, Any] = {"client_id": client_id, "protocol": mqtt.MQTTv311}
    # In paho-mqtt >= 2.0, use the classic callback API.
    if hasattr(mqtt, "CallbackAPIVersion"):
        kwargs["callback_api_version"] = mqtt.CallbackAPIVersion.VERSION1
    return mqtt.Client(**kwargs)

# ────────────────────────────────────────────────────────────────────────────
# Publishing utilities

def mqtt_publish(
    device_id: str,
    cmd: str,
    value: str,
    *,
    qos: int = 1,
    retain: bool = True,
) -> None:
    """Publish a single JSON payload {cmd: value} to ``fms/<device_id>``."""
    payload = json.dumps({cmd: value})
    cli = _new_client("svc_sender")

    host, port, scheme = _normalize_endpoint(MQ.get("broker_host"), MQ.get("broker_port"))
    _apply_auth_and_tls(cli, MQ, host, port, scheme)

    cli.connect(host=host, port=int(port), keepalive=60)

    cli.loop_start()
    try:
        info = cli.publish(f"fms/{device_id}", payload, qos=qos, retain=retain)
        if qos:
            info.wait_for_publish(timeout=5)
    finally:
        cli.loop_stop()
        cli.disconnect()


def mqtt_publish_raw(device_id: str, payload_str: str, qos: int = 1, retain: bool = False) -> None:
    """Publish a pre-serialized JSON string verbatim."""
    cli = _new_client("svc_sender")

    host, port, scheme = _normalize_endpoint(MQ.get("broker_host"), MQ.get("broker_port"))
    _apply_auth_and_tls(cli, MQ, host, port, scheme)

    cli.connect(host, int(port), 60)
    cli.loop_start()
    try:
        info = cli.publish(f"fms/{device_id}", payload_str, qos=qos, retain=retain)
        if qos:
            info.wait_for_publish(timeout=5)
    finally:
        cli.loop_stop()
        cli.disconnect()


def mqtt_publish_raw_and_wait(
    device_id: str,
    payload_str: str,
    resp_suffix: str,
    *,
    ok_values: Set[str] | None = None,
    fail_values: Set[str] | None = None,
    timeout: int = 12,
    qos: int = 1,
    retain: bool = False,
) -> dict:
    """
    Publish payload_str to fms/<device_id>, then wait up to `timeout` seconds
    for a reply on fms/<device_id>/<resp_suffix>.

    Returns:
      {
        "got": bool,         # got any reply on the response topic
        "ok": True|False|None,
        "topic": str | None,
        "payload": str | None,
        "elapsed": float
      }
    """
    cli = _new_client("svc_sender")

    host, port, scheme = _normalize_endpoint(MQ.get("broker_host"), MQ.get("broker_port"))
    _apply_auth_and_tls(cli, MQ, host, port, scheme)

    target_topic = f"fms/{device_id}/{resp_suffix}"
    got_evt = threading.Event()
    reply: Dict[str, Optional[str]] = {"topic": None, "payload": None}
    t0 = time.time()

    def on_message(c, ud, msg):
        if msg.topic == target_topic:
            try:
                payload = msg.payload.decode("utf-8", "ignore")
            except Exception:
                payload = ""
            reply["topic"] = msg.topic
            reply["payload"] = payload.strip()
            got_evt.set()

    cli.on_message = on_message
    cli.connect(host, int(port), 60)
    cli.loop_start()
    try:
        # Subscribe BEFORE publish to avoid missing a fast reply
        cli.subscribe(target_topic, qos=qos)

        # Publish the command
        info = cli.publish(f"fms/{device_id}", payload_str, qos=qos, retain=retain)
        if qos:
            info.wait_for_publish(timeout=5)

        # Block until reply or timeout
        got_evt.wait(timeout)
        elapsed = time.time() - t0
    finally:
        cli.loop_stop()
        cli.disconnect()

    verdict: Optional[bool] = None
    if reply["payload"] is not None:
        p = reply["payload"].strip().lower()
        ok_set   = {s.lower() for s in (ok_values  or set())}
        fail_set = {s.lower() for s in (fail_values or set())}
        LOG.debug(f"Checking response: payload='{p}', ok_set={ok_set}, fail_set={fail_set}")
        if ok_set and p in ok_set:
            verdict = True
            LOG.debug(f"Matched OK: {p} in {ok_set}")
        elif fail_set and p in fail_set:
            verdict = False
            LOG.debug(f"Matched FAIL: {p} in {fail_set}")
        else:
            LOG.warning(f"No match: '{p}' not in ok_set={ok_set} or fail_set={fail_set}")

    result = {
        "got": got_evt.is_set(),
        "ok": verdict,
        "topic": reply["topic"],
        "payload": reply["payload"],
        "elapsed": elapsed,
    }
    LOG.info(f"mqtt_publish_raw_and_wait result: {result}")
    return result

# ────────────────────────────────────────────────────────────────────────────
class Command(BaseCommand):
    """Django management command wrapper around :pyfunc:`mqtt_publish`."""

    help = "Publish a command to fms/<device_id> (single top-level key)."

    def add_arguments(self, parser):
        parser.add_argument("--device", required=True, help="Console UID, e.g. 34742A")
        parser.add_argument("--cmd", required=True, help="Top-level key, e.g. firmware, charts, config")
        parser.add_argument("--value", required=True, help="Value for that key (URL, JSON, etc.)")
        parser.add_argument("--retain", action="store_true", help="Retain the MQTT message")
        parser.add_argument("--qos", type=int, default=1, choices=[0, 1, 2], help="MQTT QoS (default 1)")

    def handle(self, *args, **opts):
        mqtt_publish(
            device_id=opts["device"].strip(),
            cmd=opts["cmd"].strip(),
            value=opts["value"].strip(),
            qos=opts["qos"],
            retain=opts["retain"],
        )
        self.stdout.write(
            self.style.SUCCESS(
                f"Sent {{{opts['cmd']}:{opts['value']}}} "
                f"to fms/{opts['device']} (qos={opts['qos']}, retain={opts['retain']})"
            )
        )
