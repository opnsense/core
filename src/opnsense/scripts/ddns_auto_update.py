#!/usr/bin/env python3
import base64
import hashlib
import json
import os
import re
import socket
import subprocess
import sys
import time
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET
from datetime import datetime

CONFIG_PATH = "/conf/config.xml"
STATE_PATH = "/var/db/ddns_state.json"
DEFAULT_QUERY_URL = "https://api.ipify.org"
DEFAULT_TEMPLATE = "https://ddns.afraid.org/dynamic/update.php?{token}"
DAILY_FALLBACK_SECONDS = 24 * 60 * 60
TIMEOUT = 15
FALLBACK_RESOLVERS = [
    "https://api.ipify.org",
    "http://checkip.amazonaws.com",
    "http://ipinfo.io/ip",
    "http://icanhazip.com/",
    "http://ifconfig.me/ip",
    "http://ident.me/",
    "http://myexternalip.com/raw",
    "http://checkip.dns.he.net/",
    "http://bot.whatismyipaddress.com/",
    "http://domains.google.com/checkip",
    "http://ipecho.net/plain",
    "http://ddns.afraid.org/dynamic/check.php",
]


def log_line(message):
    ts = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    print(f"{ts} | {message}")


def read_config():
    tree = ET.parse(CONFIG_PATH)
    root = tree.getroot()
    general = root.find("./OPNsense/DDNS/general")
    if general is None:
        general = root.find("./OPNsense/DDNS/general")
    if general is None:
        return {
            "enabled": "0",
            "intervalMinutes": "5",
            "queryUrl": DEFAULT_QUERY_URL,
            "tokenUpdateUrl": DEFAULT_TEMPLATE,
            "ownIp": "",
            "token": "",
        }

    def text(name, default=""):
        node = general.find(name)
        return (node.text or "").strip() if node is not None and node.text is not None else default

    return {
        "enabled": text("enabled", "0"),
        "intervalMinutes": text("intervalMinutes", "5") or "5",
        "queryUrl": text("queryUrl", DEFAULT_QUERY_URL) or DEFAULT_QUERY_URL,
        "tokenUpdateUrl": text("tokenUpdateUrl", DEFAULT_TEMPLATE) or DEFAULT_TEMPLATE,
        "ownIp": text("ownIp", ""),
        "token": text("token", ""),
    }


def load_state():
    if not os.path.exists(STATE_PATH):
        return {}
    try:
        with open(STATE_PATH, "r", encoding="utf-8") as handle:
            return json.load(handle)
    except Exception:
        return {}


def save_state(state):
    os.makedirs(os.path.dirname(STATE_PATH), exist_ok=True)
    tmp = STATE_PATH + ".tmp"
    with open(tmp, "w", encoding="utf-8") as handle:
        json.dump(state, handle)
    os.replace(tmp, STATE_PATH)


def http_get(url):
    req = urllib.request.Request(url, headers={"User-Agent": "OPNsense-DDNS-Auto/1.0"})
    with urllib.request.urlopen(req, timeout=TIMEOUT) as resp:
        body = resp.read().decode("utf-8", errors="replace")
        code = resp.getcode() or 0
    return code, body


def resolve_ip(config):
    own_ip = config["ownIp"].strip()
    if own_ip:
        return own_ip

    primary = (config.get("queryUrl", "") or "").strip()
    urls = [primary] if primary else []
    for resolver in FALLBACK_RESOLVERS:
        if resolver not in urls:
            urls.append(resolver)

    errors = []
    for idx, url in enumerate(urls):
        try:
            code, body = http_get(url)
            if code >= 400:
                errors.append(f"{url}: HTTP {code}")
                continue
            match = re.search(r"\b((25[0-5]|2[0-4]\d|1?\d?\d)\.){3}(25[0-5]|2[0-4]\d|1?\d?\d)\b", body)
            if not match:
                errors.append(f"{url}: no IPv4")
                continue
            if idx > 0:
                log_line(f"ddns-auto: fallback resolver used: {url}")
            return match.group(0)
        except Exception as err:
            errors.append(f"{url}: {err}")

    preview = " | ".join(errors[:3]) if errors else "unknown"
    raise RuntimeError(f"IP query failed ({preview})")


def decrypt_token_if_needed(value):
    raw = (value or "").strip()
    if not raw:
        return ""
    if not raw.startswith("enc:v1:"):
        return raw

    blob = raw[len("enc:v1:"):]
    try:
        payload = base64.b64decode(blob)
    except Exception:
        return ""
    if len(payload) <= 16:
        return ""

    iv = payload[:16]
    cipher = payload[16:]
    host = socket.gethostname() or "opnsense"
    key_hex = hashlib.sha256((host + "|ddns-token-key-v1").encode("utf-8")).hexdigest()

    try:
        proc = subprocess.run(
            ["openssl", "enc", "-d", "-aes-256-cbc", "-K", key_hex, "-iv", iv.hex(), "-nosalt"],
            input=cipher,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            check=False,
        )
        if proc.returncode != 0:
            return ""
        return proc.stdout.decode("utf-8", errors="ignore").strip()
    except Exception:
        return ""


def build_update_url(token, ip, template):
    raw = decrypt_token_if_needed(token).strip()
    if not raw:
        raise RuntimeError("Token missing")

    if raw.lower().startswith("http://") or raw.lower().startswith("https://"):
        return raw.replace("{ip}", urllib.parse.quote(ip, safe=""))

    url = template
    token_enc = urllib.parse.quote(raw.strip("/"), safe="")
    ip_enc = urllib.parse.quote(ip, safe="")

    if "{token}" in url:
        url = url.replace("{token}", token_enc)
    elif "%s" in url:
        url = url % token_enc
    else:
        url = url.rstrip("/") + "/" + token_enc + "/"

    return url.replace("{ip}", ip_enc)


def should_update(current_ip, state, config):
    now = int(time.time())
    last_ip = state.get("last_ip", "")
    last_success = int(state.get("last_success_ts", 0) or 0)

    try:
        interval = int((config.get("intervalMinutes", "5") or "5").strip())
    except Exception:
        interval = 5

    if interval < 1:
        interval = 1

    if not last_success:
        return True, "initial_sync"
    if current_ip != last_ip:
        return True, "ip_changed"

    interval_seconds = interval * 60
    if now - last_success >= interval_seconds:
        return True, "interval_elapsed"
    if now - last_success >= DAILY_FALLBACK_SECONDS:
        return True, "daily_fallback"
    return False, "interval_not_elapsed"


def main():
    force = "--force" in sys.argv
    config = read_config()
    if config["enabled"] != "1":
        log_line("ddns-auto: disabled")
        return 0

    state = load_state()

    try:
        ip = resolve_ip(config)
    except Exception as err:
        log_line(f"ddns-auto: resolve ip failed: {err}")
        return 1

    do_update, reason = should_update(ip, state, config)
    if force:
        do_update = True
        reason = "forced"
    if not do_update:
        log_line(f"ddns-auto: no update needed ({reason})")
        return 0

    try:
        url = build_update_url(config["token"], ip, config["tokenUpdateUrl"])
        code, body = http_get(url)
    except Exception as err:
        log_line(f"ddns-auto: update failed: {err}")
        return 1

    normalized = body.strip().lower()
    no_change = ("has not changed" in normalized) or ("is current" in normalized) or ("no update needed" in normalized)
    bad = ("badauth" in normalized) or ("911" in normalized) or (("error" in normalized) and not no_change)
    if code >= 400 or bad:
        log_line(f"ddns-auto: provider rejected update (reason={reason}, http={code}, body={body[:200]})")
        return 1

    state["last_ip"] = ip
    state["last_success_ts"] = int(time.time())
    state["last_reason"] = reason
    save_state(state)
    log_line(f"ddns-auto: updated (reason={reason}, ip={ip}, http={code})")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
