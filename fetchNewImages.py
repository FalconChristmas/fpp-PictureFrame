#!/usr/bin/env python3
#
# fpp-PictureFrame: fetch image attachments from an IMAP mailbox.
#
# Replaces the original fetchNewImages.php, which relied on PHP's ext/imap.
# ext/imap was removed in PHP 8.4 (and its c-client backend is gone from
# Debian trixie / FPP10), so this is a stdlib-only Python rewrite -- no
# third-party packages, no composer, no extra apt dependencies.
#
# Behavior is preserved from the PHP version: connect to the IMAP mailbox,
# walk each message, honor the sender allow-list (with per-sender folders),
# save image attachments (.jpg/.jpeg/.png/.gif) into the FPP images dir, then
# delete any message we pulled images from.

import email
import email.policy
import email.utils
import grp
import imaplib
import json
import os
import pwd
import re
import ssl
import subprocess
import sys
import time
from datetime import datetime

IMAGE_DIR = "/home/fpp/media/images"
SETTINGS_FILE = "/home/fpp/media/settings"
PLUGIN_INI = "/home/fpp/media/config/plugin.fpp-PictureFrame"
PLUGIN_JSON = "/home/fpp/media/config/plugin.fpp-PictureFrame.json"

FPP_LOG_ERR = 1
FPP_LOG_WARN = 2
FPP_LOG_INFO = 3
FPP_LOG_DEBUG = 4
FPP_LOG_EXCESSIVE = 5

_LOG_LEVEL = FPP_LOG_WARN


def log(level, msg):
    if _LOG_LEVEL >= level:
        print(datetime.now().strftime("%Y-%m-%d %H:%M:%S") + " " + msg, flush=True)


def parse_fpp_ini(path):
    """Parse an FPP-style `key = "value"` config file (no [section] header)."""
    out = {}
    try:
        with open(path, "r", encoding="utf-8", errors="replace") as fh:
            for line in fh:
                line = line.strip()
                if not line or line[0] in "#;" or "=" not in line:
                    continue
                key, val = line.split("=", 1)
                out[key.strip()] = val.strip().strip('"')
    except FileNotFoundError:
        pass
    return out


def is_email(addr):
    return re.match(r"^[^@\s]+@[^@\s]+\.[^@\s]+$", addr) is not None


def build_sender_rules(json_settings):
    """Return (regex_list, folder_list) parallel arrays from the senders config.

    Mirrors the PHP logic: a full address matches exactly; a bare domain or
    "@domain" matches any user at that domain.
    """
    regexes, folders = [], []
    for sender in json_settings.get("senders", []):
        addr = sender.get("email", "")
        folder = sender.get("folder", "")
        if is_email(addr):
            regexes.append(re.escape(addr))
            folders.append(folder)
            log(FPP_LOG_DEBUG, "Sender Email  : %s" % addr)
        elif is_email("blah" + addr):          # "@example.com"
            regexes.append(".*" + re.escape(addr))
            folders.append(folder)
            log(FPP_LOG_DEBUG, "Sender Domain : %s" % addr)
        elif is_email("blah@" + addr):         # "example.com"
            regexes.append(".*@" + re.escape(addr))
            folders.append(folder)
            log(FPP_LOG_DEBUG, "Sender Domain : %s" % addr)
    return regexes, folders


def match_sender(from_addrs, sender_regexes):
    """Return the folder index for the first matching rule, -1 to allow all
    (no rules configured), or -2 for an unmatched (rejected) sender."""
    if not sender_regexes:
        return -1
    for addr in from_addrs:
        for idx, rgx in enumerate(sender_regexes):
            if re.search(rgx, addr, re.IGNORECASE):
                log(FPP_LOG_DEBUG, "Sender '%s' matches '%s'" % (addr, rgx))
                return idx
    return -2


def connect_imap(host, port, username, password):
    if port == 143:
        # Plaintext port: upgrade with STARTTLS.
        imap = imaplib.IMAP4(host, port)
        imap.starttls(ssl.create_default_context())
    else:
        imap = imaplib.IMAP4_SSL(host, port, ssl_context=ssl.create_default_context())
    imap.login(username, password)
    return imap


def save_attachment(payload, filename, folder_idx, sender_folders):
    prefix = datetime.now().strftime("%Y%m%d") + "-"
    if (folder_idx >= 0 and sender_folders[folder_idx] != ""
            and os.path.isdir(os.path.join(IMAGE_DIR, sender_folders[folder_idx]))):
        path = os.path.join(IMAGE_DIR, sender_folders[folder_idx], prefix + filename)
    else:
        path = os.path.join(IMAGE_DIR, prefix + filename)

    log(FPP_LOG_INFO, "Saving File: %s" % path)
    with open(path, "wb") as fh:
        fh.write(payload)

    # Best-effort ownership/permissions (chown needs root; ignore if not).
    try:
        os.chown(path, pwd.getpwnam("fpp").pw_uid, grp.getgrnam("fpp").gr_gid)
    except (PermissionError, KeyError, OSError):
        pass
    try:
        os.chmod(path, 0o644)
    except OSError:
        pass

    subprocess.run(["sync"], check=False)
    time.sleep(5)


def process_message(imap, num, sender_regexes, sender_folders):
    typ, msg_data = imap.fetch(num, "(RFC822)")
    if typ != "OK" or not msg_data or not msg_data[0]:
        log(FPP_LOG_WARN, "Could not fetch message %s" % num.decode())
        return

    msg = email.message_from_bytes(msg_data[0][1], policy=email.policy.default)
    from_addrs = [a for _, a in email.utils.getaddresses(msg.get_all("From", []))]

    folder_idx = match_sender(from_addrs, sender_regexes)
    if folder_idx < -1:
        log(FPP_LOG_WARN, "Sender %s is not in the list of valid senders"
            % (from_addrs[0] if from_addrs else "(unknown)"))
        return

    downloaded = False
    for part in msg.walk():
        filename = part.get_filename()
        if filename and re.search(r"\.(jpg|jpeg|png|gif)$", filename, re.IGNORECASE):
            payload = part.get_payload(decode=True)   # decodes base64 / quoted-printable
            if payload:
                save_attachment(payload, filename, folder_idx, sender_folders)
                downloaded = True

    if downloaded:
        imap.store(num, "+FLAGS", "\\Deleted")


def main():
    global _LOG_LEVEL

    settings = parse_fpp_ini(SETTINGS_FILE)
    _LOG_LEVEL = {
        "error": FPP_LOG_ERR, "warn": FPP_LOG_WARN, "info": FPP_LOG_INFO,
        "debug": FPP_LOG_DEBUG, "excess": FPP_LOG_EXCESSIVE,
    }.get(settings.get("LogLevel_Plugin", ""), FPP_LOG_WARN)

    ps = parse_fpp_ini(PLUGIN_INI)
    host = ps.get("pfemailserver", "")
    username = ps.get("pfemailuser", "")
    password = ps.get("pfemailpass", "")
    mailbox = ps.get("pfmailbox", "INBOX")
    port = int(ps.get("pfemailport", "993") or "993")

    if not host or not username or not password:
        print("ERROR: One or more of pfemailserver, pfemailuser, pfemailpass is not set")
        return 0

    try:
        with open(PLUGIN_JSON, "r", encoding="utf-8", errors="replace") as fh:
            json_settings = json.load(fh)
    except (FileNotFoundError, json.JSONDecodeError):
        json_settings = {}

    sender_regexes, sender_folders = build_sender_rules(json_settings)

    log(FPP_LOG_DEBUG, "Host    : %s" % host)
    log(FPP_LOG_DEBUG, "Port    : %d" % port)
    log(FPP_LOG_DEBUG, "Mailbox : %s" % mailbox)

    try:
        imap = connect_imap(host, port, username, password)
    except (imaplib.IMAP4.error, ssl.SSLError, OSError) as exc:
        print("Error opening connection: %s" % exc)
        return 0

    try:
        imap.select(mailbox)
        typ, data = imap.search(None, "ALL")
        nums = data[0].split() if (typ == "OK" and data and data[0]) else []
        log(FPP_LOG_DEBUG, "Number of messages: %d" % len(nums))

        for num in nums:
            log(FPP_LOG_INFO, "Checking email #%s" % num.decode())
            process_message(imap, num, sender_regexes, sender_folders)

        imap.expunge()
    finally:
        try:
            imap.logout()
        except Exception:
            pass
    return 0


if __name__ == "__main__":
    sys.exit(main())
