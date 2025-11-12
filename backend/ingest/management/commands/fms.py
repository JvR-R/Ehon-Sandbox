"""
Unified MQTT listener for "fms/#" topics
Place this file at ingest/management/commands/fms.py
Run with:  python manage.py fms --config /home/javier/config.ini
"""

from concurrent.futures import ThreadPoolExecutor
from django.core.management.base import BaseCommand
import configparser
import json
import logging
from logging.handlers import RotatingFileHandler
import paho.mqtt.client as mqtt
from django import db
from django.db import connection
from django.utils import timezone
import ssl
from urllib.parse import urlparse
from pathlib import Path
import threading

# All device-specific handlers live in ingest/fms/*.py
from ingest.fms import HANDLERS   # HANDLERS = {filter: function, …}

# ───────────────────────────────────────────────────────────────────────────────
LOG  = logging.getLogger("fms")              # main logger for this service
ERR  = logging.getLogger("fms.err")          # error channel
TXLOG = logging.getLogger("fms.transactions")# handler-specific logger name used by transactions.py

# --- file logging: /var/log/fms/{fms.log,fms.err}
_LOG_BASE = Path("/var/log/fms")
_LOG_BASE.mkdir(parents=True, exist_ok=True)

for lg in (LOG, ERR, TXLOG, logging.getLogger()):  # also clear root to avoid double handlers
    lg.handlers = []

LOG.setLevel(logging.INFO)
ERR.setLevel(logging.WARNING)    # ERR captures WARNING+ERROR
TXLOG.setLevel(logging.WARNING)  # let handler errors bubble into fms.err

_fmt = logging.Formatter("%(asctime)s [%(levelname)s] %(message)s")
_fh_main = RotatingFileHandler(_LOG_BASE / "fms.log", maxBytes=10*1024*1024, backupCount=5, encoding="utf-8")
_fh_main.setFormatter(_fmt)
LOG.addHandler(_fh_main)

_fh_err  = RotatingFileHandler(_LOG_BASE / "fms.err",  maxBytes=10*1024*1024, backupCount=5, encoding="utf-8")
_fh_err.setFormatter(_fmt)
ERR.addHandler(_fh_err)
TXLOG.addHandler(_fh_err)        # IMPORTANT: make transactions go to fms.err

_stderr = logging.StreamHandler()   # show ERRORs on stderr if running foreground
_stderr.setLevel(logging.ERROR)
_stderr.setFormatter(_fmt)
for lg in (LOG, ERR, TXLOG):
    lg.addHandler(_stderr)

EXEC = ThreadPoolExecutor(max_workers=10)


# Per-device rotating logs under management/logs/fms/<MAC>.log (2MB per file)
LOG_DIR = (Path(__file__).resolve().parent.parent / "logs" / "fms")
_DEVICE_LOGGERS: dict[str, logging.Logger] = {}
_DEVICE_LOGGERS_LOCK = threading.Lock()


def get_device_logger(device_id: str) -> logging.Logger | None:
    """
    Return a logger for the given device id (MAC/serial) that writes to
    management/logs/fms/<ID>.log with size-based rotation (1MB, 5 backups).
    Colons and dashes are removed; ID is uppercased.
    """
    if not device_id:
        return None
    s = str(device_id).strip().replace(":", "").replace("-", "").upper()
    if not s:
        return None
    with _DEVICE_LOGGERS_LOCK:
        logger = _DEVICE_LOGGERS.get(s)
        if logger is not None:
            return logger
        try:
            LOG_DIR.mkdir(parents=True, exist_ok=True)
        except Exception:
            LOG.exception("Failed to create log directory %s", LOG_DIR)
            return None
        logger = logging.getLogger(f"device_log.{s}")
        logger.setLevel(logging.INFO)
        logger.propagate = False
        handler = RotatingFileHandler(
            str(LOG_DIR / f"{s}.log"),
            maxBytes=2 * 1024 * 1024,
            backupCount=5,
            encoding="utf-8",
        )
        handler.setFormatter(logging.Formatter("%(asctime)s %(message)s"))
        logger.addHandler(handler)
        _DEVICE_LOGGERS[s] = logger
        return logger


def load_cfg(path: str) -> dict:
    """Read [mqttfms] section from an INI file."""
    cfg = configparser.ConfigParser()
    if not cfg.read(path):
        raise FileNotFoundError(f"Cannot read config {path}")
    if "mqttfms" in cfg:
        return cfg["mqttfms"]
    else:
        raise KeyError("Config file must contain [mqttfms] section")


def normalize_endpoint(raw_host: str, raw_port) -> tuple[str, int, str | None]:
    """
    Accept either:
      - host: 'ehonenergytech.com.au'
      - URL:  'mqtts://ehonenergytech.com.au:8883'
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
        port = 8885 if scheme in ("mqtts", "ssl", "tls") else 1885

    return host, int(port), scheme


def should_use_tls(cfg: dict, port: int, scheme: str | None) -> bool:
    v = str(cfg.get("tls_enable", "")).strip().lower()
    if v in ("1", "true", "yes", "on"):
        return True
    if scheme in ("mqtts", "ssl", "tls"):
        return True
    if port == 8885:
        return True
    return False


def build_tls_context(cfg: dict) -> ssl.SSLContext:
    """
    Build a strict TLS client context that verifies server cert + hostname
    using the OS trust store (Let's Encrypt etc. work by default).
    Optional INI keys:
      tls_ca_file      = /path/to/ca-bundle.crt
      tls_min_version  = TLSv1.2 | TLSv1.3
      tls_client_cert  = /path/to/client.crt   (mTLS only)
      tls_client_key   = /path/to/client.key   (mTLS only)
      tls_key_pass     = optional-password
    """
    ctx = ssl.create_default_context()  # PROTOCOL_TLS_CLIENT, verify_hostname=True
    cafile = cfg.get("tls_ca_file")
    if cafile:
        ctx.load_verify_locations(cafile=cafile)

    minv = (cfg.get("tls_min_version") or "").upper().replace(".", "")
    if minv == "TLSV13" or minv == "TLS1_3":
        ctx.minimum_version = ssl.TLSVersion.TLSv1_3
    else:
        ctx.minimum_version = ssl.TLSVersion.TLSv1_2

    # mTLS (optional)
    cert = cfg.get("tls_client_cert")
    key  = cfg.get("tls_client_key")
    if cert and key:
        ctx.load_cert_chain(certfile=cert, keyfile=key, password=cfg.get("tls_key_pass"))

    return ctx


def _normalize_device_id(s: str | None) -> str | None:
    if not s:
        return None
    return str(s).strip().replace(":", "").replace("-", "").upper() or None


def new_mqtt_client(client_id: str, clean_session: bool = False) -> mqtt.Client:
    """
    Create an MQTT client compatible with paho-mqtt v1.x and v2.x.
    """
    kwargs = {"client_id": client_id, "clean_session": clean_session, "protocol": mqtt.MQTTv311}
    # In paho-mqtt >= 2.0, use the classic callback API.
    if hasattr(mqtt, "CallbackAPIVersion"):
        kwargs["callback_api_version"] = mqtt.CallbackAPIVersion.VERSION1
    return mqtt.Client(**kwargs)


def configure_client(cli: mqtt.Client, cfg: dict, host: str) -> None:
    """
    Apply auth and TLS settings to an already-created client.
    """
    user = cfg.get("username")
    if user:
        cli.username_pw_set(user, cfg.get("password") or "")

    # TLS?
    # We decide outside and only call this when TLS is needed.
    ctx = build_tls_context(cfg)
    cli.tls_set_context(ctx)
    cli.tls_insecure_set(False)   # do NOT disable verification

    # SNI/hostname verification uses the 'host' passed to connect() — nothing else to do.


def dispatch(topic: str, payload: dict, client: mqtt.Client) -> None:
    """
    Route a message to the first handler whose filter matches the topic.
    Clear the retained copy ONLY AFTER the handler succeeds.
    """
    db.close_old_connections()
    for flt, func in HANDLERS.items():
        if mqtt.topic_matches_sub(flt, topic):
            parts  = topic.split("/")
            if len(parts) >= 3:                 # fms/<id>/...
                serial = parts[1]
            else:                               # fms/...
                serial = payload.get("id")      # may be None if body also changed

            # Inject tank/probe number from topic for handlers that don't include it in payload
            # Example: fms/<serial>/probes/<num> with payload {"conn": false}
            # We add payload["id"] = <num> so handlers can read it consistently like tanks.py
            if len(parts) >= 4 and "id" not in payload:
                try:
                    payload["id"] = int(parts[3])
                except (ValueError, IndexError):
                    pass
            # process in a background thread and ACK (clear retained) only on success
            norm_serial = _normalize_device_id(serial) or ""

            def _run_and_ack():
                try:
                    # Close stale connections before handler runs (critical for long-running workers)
                    db.close_old_connections()
                    func(norm_serial, payload)   # your handler (DB insert, etc.)
                    try:
                        client.publish(topic, payload=None, qos=0, retain=True)  # clear retained only after success
                    except Exception:
                        ERR.exception("Failed to clear retained for %s", topic)
                    # Log the most relevant identifier from the payload (id, tank, probe, etc.)
                    identifier = payload.get("id") or payload.get("tank") or payload.get("probe") or "N/A"
                    LOG.info("Processed %s id=%s", topic, identifier)
                except Exception:
                    # Handler failed: DO NOT clear retained; log the exception
                    ERR.exception("Handler failed for %s", topic)

            EXEC.submit(_run_and_ack)
            return
    LOG.warning("No handler found for topic %s", topic)


def bump_last_conn(serial: str | None) -> None:
    """
    Update console.last_conndate / last_conntime for the given device_id.
    serial is the <id> segment from 'fms/<id>/(...)'.
    """
    if not serial:
        return
    s = str(serial).strip().replace(":", "").replace("-", "").upper()
    try:
        # use local time; trim microseconds for plain TIME columns
        now = timezone.localtime()
        d = now.date().isoformat()                      # 'YYYY-MM-DD'
        t = now.time().replace(microsecond=0).isoformat()  # 'HH:MM:SS'
        with connection.cursor() as cur:
            cur.execute(
                """
                UPDATE console
                   SET last_conndate = %s,
                       last_conntime = %s
                 WHERE device_id = %s
                """,
                [d, t, s],
            )
    except Exception:
        LOG.exception("Failed to bump last_conn for device_id=%s", s)
    finally:
        # be nice to Django's connection pool in a long-running process
        db.close_old_connections()


# ────────────────────────── Django management command ─────────────────────────
class Command(BaseCommand):
    help = "Listen on fms/# and save incoming MQTT messages"

    def add_arguments(self, parser):
        parser.add_argument(
            "--config",
            required=True,
            help="Path to INI file that contains [mqttfms] section",
        )

    def handle(self, *args, **opts):
        cfg = load_cfg(opts["config"])

        raw_host = cfg.get("broker_host", "127.0.0.1")
        raw_port = cfg.get("broker_port", 8885)
        host, port, scheme = normalize_endpoint(raw_host, raw_port)

        use_tls = should_use_tls(cfg, port, scheme)

        cli = new_mqtt_client(
            client_id=cfg.get("client_name", "backend_fms_listener"),
            clean_session=False,          # keep queued QoS1/2 while offline
        )
        cli.enable_logger(LOG)

        if use_tls:
            configure_client(cli, cfg, host)
        else:
            # still set username/password if provided
            user = cfg.get("username")
            if user:
                cli.username_pw_set(user, cfg.get("password") or "")

        def on_connect(c, u, f, rc):
            LOG.info("Connected (RC=%s); subscribing to fms/#", rc)
            c.subscribe("fms/#", qos=0)

        def on_message(c, u, msg):
            # ignore empty messages (including retained messages we cleared)
            if not msg.payload:
                return
            # Log ALL received messages for debugging
            LOG.info("Received message on topic: %s", msg.topic)
            try:
                parts = msg.topic.split("/")
                if len(parts) >= 2 and parts[0] == "fms":
                    bump_last_conn(parts[1])  # <id>
            except Exception:
                ERR.exception("Error updating last_conn from topic %s", msg.topic)

            # Per-device raw message logging: "topic: payload" with timestamp
            try:
                parts = msg.topic.split("/")
                if len(parts) >= 3 and parts[0] == "fms":
                    device_id = parts[1]
                    dev_log = get_device_logger(device_id)
                    if dev_log is not None:
                        try:
                            payload_text = msg.payload.decode("utf-8", errors="replace")
                        except Exception:
                            payload_text = repr(msg.payload)
                        dev_log.info("%s: %s", msg.topic, payload_text)
            except Exception:
                ERR.exception("Error writing per-device log for topic %s", msg.topic)

            try:
                data = json.loads(msg.payload)
            except json.JSONDecodeError:
                # Fallback: try to parse Python dict format (single quotes)
                try:
                    import ast
                    payload_str = msg.payload.decode('utf-8') if isinstance(msg.payload, bytes) else str(msg.payload)
                    data = ast.literal_eval(payload_str)
                    LOG.warning("Parsed Python dict format on %s (should be JSON): %r", msg.topic, msg.payload)
                except (ValueError, SyntaxError):
                    # Allow known plain-text keepalive/acks (not JSON) without logging errors
                    payload_str = msg.payload.decode('utf-8', 'ignore') if isinstance(msg.payload, bytes) else str(msg.payload)
                    ack = payload_str.strip().upper()
                    if (
                        ack in {'OK','PONG','ACK','READY'} or
                        ack.endswith('_OK') or ack.endswith('_FAIL')
                    ):
                        return  # ignore quietly
                    ERR.error("Invalid JSON/dict format on %s: %r", msg.topic, msg.payload)
                    return
            dispatch(msg.topic, data, c)

        cli.on_connect  = on_connect
        cli.on_message  = on_message

        LOG.info("Connecting to %s:%s …%s", host, port, " (TLS)" if use_tls else "")
        cli.connect(host, port, keepalive=60)
        cli.loop_forever()

