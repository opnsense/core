#!/usr/bin/env python3

"""
    Copyright (c) 2023-2026 Ad Schellevis <ad@opnsense.org>
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
     this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
     notice, this list of conditions and the following disclaimer in the
     documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
"""
# Python implementation to re-resolve dns entries, for reference see:
# https://github.com/WireGuard/wireguard-tools/tree/master/contrib/reresolve-dns

from typing import Tuple, Union, List
import sys
import glob
import os
import time
import subprocess
import logging
from logging.handlers import RotatingFileHandler
import argparse


def create_logger(log_file: str) -> logging.Logger:
    logging.basicConfig(
        level=logging.DEBUG,
        format="%(asctime)s [%(levelname)s] %(message)s",
        handlers=[
            RotatingFileHandler(
                log_file, encoding="utf-8", maxBytes=10240, backupCount=4
            ),
            logging.StreamHandler(),
        ],
    )
    return logging.getLogger()


def runner(cmd: Union[List[str], str]) -> Tuple[bool, str]:
    try:
        logger.debug("Running command: {}".format(cmd))
        child = subprocess.Popen(
            cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, encoding="utf-8"
        )
        stdout, stderr = child.communicate(timeout=60)
        child.wait(timeout=60)
        if child.returncode == 0:
            return True, stdout
        logger.error(
            "Command {} failed with exit code {}:\n{}".format(
                cmd, child.returncode, stderr
            )
        )
    except subprocess.TimeoutExpired as exc:
        logger.error("Command {} took too long: {}".format(cmd, exc))
    except subprocess.SubprocessError as exc:
        logger.error("Command {} failed: {}".format(cmd, exc))
    return False, None


def get_handshakes() -> dict:
    logger.debug("Getting handshakes")
    # wg show all latest-handshakes produces one line per peer in for of
    # iface pubkey epoch-of-last-handshake

    handshakes = {}
    ts_now = time.time()

    result, content = runner(["/usr/bin/wg", "show", "all", "latest-handshakes"])
    if not result:
        return handshakes

    for line in content.split("\n"):
        parts = line.split()
        if len(parts) == 3 and parts[2].isdigit():
            elapsed_time = ts_now - int(parts[2])
            handshakes["%s-%s" % (parts[0], parts[1])] = elapsed_time
            logger.info(
                "Last handshake for interface {} was {:.2f} seconds ago".format(
                    parts[0], elapsed_time
                )
            )
    return handshakes


def check_recent_handshakes(threshold: int, conf_file_path: str) -> bool:
    successful_run = True
    handshakes = get_handshakes()
    globs = glob.glob(conf_file_path.rstrip("/") + "/*.conf")
    if not globs:
        logger.warning(
            "It seems that there are no config file candidates in {}".format(
                conf_file_path
            )
        )
        return False
    for filename in globs:
        this_peer = {}
        ifname = os.path.basename(filename).split(".")[0]
        logger.info("Checking handshake threshold for interface {}".format(ifname))
        with open(filename, "r", encoding="utf-8") as fhandle:
            for line in fhandle:
                if line.startswith("[Peer]"):
                    this_peer = {"ifname": ifname}
                elif line.lower().startswith("publickey"):
                    this_peer["PublicKey"] = line.split("=", 1)[1].strip()
                elif line.lower().startswith("endpoint"):
                    this_peer["Endpoint"] = line.split("=", 1)[1].strip()

                if "Endpoint" in this_peer and "PublicKey" in this_peer:
                    peer_key = "%(ifname)s-%(PublicKey)s" % this_peer
                    if handshakes.get(peer_key, 999) > threshold:
                        logger.info(
                            "Trying to reset connection to peer {}".format(
                                this_peer["Endpoint"]
                            )
                        )
                        # skip if there has been a handshake recently
                        result, _ = runner(
                            [
                                "/usr/bin/wg",
                                "set",
                                ifname,
                                "peer",
                                this_peer["PublicKey"],
                                "endpoint",
                                this_peer["Endpoint"],
                            ],
                        )
                        if not result:
                            logger.error(
                                "Failed to reset peer {} on interface {}".format(
                                    this_peer["Endpoint"], ifname
                                )
                            )
                            successful_run = False
                    this_peer = {}
    return successful_run


if __name__ == "__main__":
    default_logfile = "/var/log/{}.log".format(os.path.basename(__file__))
    threshold = 135
    configdir = "/usr/local/etc/wireguard"

    parser = argparse.ArgumentParser(
        prog=__file__, description="DNS Watchguard script for Wireguard"
    )

    parser.add_argument(
        "-t",
        "--threshold",
        type=int,
        dest="threshold",
        default=None,
        required=False,
        help="Max seconds allowed before retriggering a wireguard reload, defaults to {} seconds".format(
            threshold
        ),
    )

    parser.add_argument(
        "-c",
        "--configdir",
        type=str,
        dest="configdir",
        default=None,
        required=False,
        help="Path to wireguard configuration directory, defaults {}".format(configdir),
    )

    parser.add_argument(
        "--logfile",
        type=str,
        dest="logfile",
        default=None,
        required=False,
        help="Path to logfile, defaults to {}".format(default_logfile),
    )
    args = parser.parse_args()

    if args.threshold:
        threshold = args.threshold
    if args.configdir:
        configdir = args.configdir

    if args.logfile:
        logger = create_logger(args.logfile)
    else:
        logger = create_logger(default_logfile)

    logger.info(
        "Running wireguard watchdog with a threshhold of {} seconds".format(threshold)
    )

    try:
        sys.exit(0) if check_recent_handshakes(threshold, configdir) else sys.exit(1)
    except Exception as exc:
        logger.critical("Failed to run: {}".format(exc))
        sys.exit(1)
