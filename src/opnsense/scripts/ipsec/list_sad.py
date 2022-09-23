#!/usr/local/bin/python3

"""
    Copyright (c) 2022 Ad Schellevis <ad@opnsense.org>
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
import hashlib
import subprocess
import ujson

if __name__ == '__main__':
    result = {'records': []}
    payload = subprocess.run(['/sbin/setkey', '-D'], capture_output=True, text=True).stdout.strip()

    # split setkey data in sections so we can support line wraps more easily
    sections = {}
    this_section = None
    for line in payload.split("\n"):
        if not line.startswith("\t") and not line.startswith(" "):
            this_section = "%08d" % (int(this_section) + 1 if this_section else 1)
            sections[this_section] = [line]
        elif this_section and line.startswith("\t"):
            sections[this_section].append(line)
        elif this_section and len(sections[this_section]) > 0:
            seq = len(sections[this_section]) - 1
            sections[this_section][seq] = "%s%s" % (sections[this_section][seq], line)

    for section in sections:
        sad_entry = {"src": "", "dst": "", "satype": "", "spi": ""}
        for seqid, line in enumerate(sections[section]):
            parts = line.split()
            if seqid == 0:
                # add tunnel src / dst
                keyparts = line.split()
                sad_entry["src"] = keyparts[0]
                sad_entry["dst"] = keyparts[1]
                if len(keyparts) > 2 and keyparts[2].find("["):
                    sad_entry["nat"] = keyparts[2][1:-1]
            elif seqid == 1:
                # satype and mode
                for pid, part in enumerate(parts):
                    if pid == 0:
                        sad_entry["satype"] = part
                    elif part.startswith("spi"):
                        sad_entry[part.split('=')[0]] = part.split('(')[-1].split(')')[0][2:]
                    elif part.find('=') > -1:
                        sad_entry[part.split('=')[0]] = part.split('=')[1].split("(")[0]
            elif line.startswith("\tE:"):
                sad_entry["alg_enc"] = parts[1]
                sad_entry["m_enc"] = parts[2:]
            elif line.startswith("\tA:"):
                sad_entry["alg_auth"] = parts[1]
                sad_entry["m_auth"] = parts[2:]
            elif line.count("\t") > 1 and line.count(":") > 0:
                for item in line.split("\t"):
                    if item.find(":") > 0:
                        if line.startswith("\tcreated") or line.startswith("\tdiff"):
                            keyname = "addtime_%s" % item.split(":")[0]
                        elif line.startswith("\tlast"):
                            keyname = "usetime_%s" % item.split(":")[0]
                        elif line.startswith("\tcurrent"):
                            keyname = "bytes_%s" % item.split(":")[0]
                        elif line.startswith("\tallocated") and item.find('allocated') == -1:
                            keyname = "allocated_%s" % item.split(":")[0]
                        else:
                            keyname = item.split(":")[0]
                        sad_entry[keyname] = item[item.find(":")+1:].split("(")[0].strip()
            elif line.find("=") > 0:
                for part in parts:
                    if part.find("=") > 0:
                        sad_entry[part.split("=")[0]] = part.split("=")[1].strip()

        # unique id
        sad_entry['id'] = hashlib.md5(
            ("%(src)s-%(dst)s-%(satype)s-%(spi)s" % sad_entry).encode()
        ).hexdigest()

        # format output
        for key in sad_entry:
            if sad_entry[key] == "":
                sad_entry[key] = None
            elif type(sad_entry[key]) is str and sad_entry[key].isdigit() and key not in ['spi']:
                sad_entry[key] = int(sad_entry[key])

        result['records'].append(sad_entry)

    print(ujson.dumps(result))
