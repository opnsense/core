#!/usr/local/bin/python3

"""
    Copyright (c) 2026 Deciso B.V.
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
    --------------------------------------------------------------------------------------------------------------
    Returns disk usage in JSON
"""

import subprocess
import json

def format_blocks(blocks, show_unit=True):
    bytes_value = blocks * 512

    units = ["B", "K", "M", "G", "T", "P", "E"]
    unit_index = 0
    value = bytes_value

    while value >= 1024 and unit_index < len(units) - 1:
        value /= 1024
        unit_index += 1

    if value >= 100:
        formatted = f"{value:.0f}"
    elif value >= 10:
        formatted = f"{value:.1f}"
    else:
        formatted = f"{value:.2f}"

    if show_unit:
        formatted += units[unit_index]

    return formatted


def disk_info():
    result = {}

    process = subprocess.run(
        ["/bin/df", "-aT", "--libxo", "json"],
        capture_output=True,
        text=True,
        check=True
    )

    disk_info = json.loads(process.stdout)

    storage_info = disk_info.get("storage-system-information")

    if storage_info:
        result["devices"] = []

        for fs in storage_info.get("filesystem", []):
            fs_type = fs["type"].strip()

            result["devices"].append({
                "device": fs["name"],
                "type": fs_type,
                "total": format_blocks(fs["total-blocks"]),
                "total_bytes": fs["total-blocks"] * 512,
                "used": format_blocks(fs["used-blocks"]),
                "used_bytes": fs["used-blocks"] * 512,
                "available": format_blocks(fs["available-blocks"]),
                "available_bytes": fs["available-blocks"] * 512,
                "used_pct": fs["used-percent"],
                "mountpoint": fs["mounted-on"],
            })

    return result

if __name__ == "__main__":
    result = disk_info()
    print(json.dumps(result, indent=4))
