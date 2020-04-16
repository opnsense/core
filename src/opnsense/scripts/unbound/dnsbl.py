#!/usr/local/bin/python3

# DNS BL script
# Copyright (c) 2020 Petr Kejval <petr.kejval6@gmail.com>

# Downloads blacklisted domains from user specified URLs and "compile" them into unbound.conf compatible file

# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions
# are met:
#
# 1. Redistributions of source code must retain the above copyright
#    notice, this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright
#    notice, this list of conditions and the following disclaimer in the
#    documentation and/or other materials provided with the distribution.
#
# THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND
# ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
# IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
# ARE DISCLAIMED.  IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE
# FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
# DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
# OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
# HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
# LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
# OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
# SUCH DAMAGE.

import re, urllib3, threading, subprocess

re_blacklist = re.compile(r'(^127\.0\.0\.1[\s]+|^0\.0\.0\.0[\s]+)([0-9a-z_.-]+)(?:\s|$)|^([0-9a-z_.-]+)(?:\s|$)', re.I)
re_whitelist = re.compile(r'$^') # default - match nothing
blacklist = set()
urls = set()

predefined_lists = {
    "aa": "https://adaway.org/hosts.txt",
    "ag": "https://justdomains.github.io/blocklists/lists/adguarddns-justdomains.txt",
    "bla": "https://blocklist.site/app/dl/ads",
    "blf": "https://blocklist.site/app/dl/fraud",
    "blp": "https://blocklist.site/app/dl/phishing",
    "ca": "http://sysctl.org/cameleon/hosts",
    "el": "https://justdomains.github.io/blocklists/lists/easylist-justdomains.txt",
    "ep": "https://justdomains.github.io/blocklists/lists/easyprivacy-justdomains.txt",
    "nc": "https://justdomains.github.io/blocklists/lists/nocoin-justdomains.txt",
    "rw": "https://ransomwaretracker.abuse.ch/downloads/RW_DOMBL.txt",
    "mw": "http://malwaredomains.lehigh.edu/files/justdomains",
    "pa": "https://raw.githubusercontent.com/chadmayfield/my-pihole-blocklists/master/lists/pi_blocklist_porn_all.list",
    "pt": "https://raw.githubusercontent.com/chadmayfield/pihole-blocklists/master/lists/pi_blocklist_porn_top1m.list",
    "sa": "https://s3.amazonaws.com/lists.disconnect.me/simple_ad.txt",
    "sb": "https://raw.githubusercontent.com/StevenBlack/hosts/master/hosts",
    "st": "https://s3.amazonaws.com/lists.disconnect.me/simple_tracking.txt",
    "ws": "https://raw.githubusercontent.com/crazy-max/WindowsSpyBlocker/master/data/hosts/spy.txt",
    "wsu": "https://raw.githubusercontent.com/crazy-max/WindowsSpyBlocker/master/data/hosts/update.txt",
    "wse": "https://raw.githubusercontent.com/crazy-max/WindowsSpyBlocker/master/data/hosts/extra.txt",
    "yy": "http://pgl.yoyo.org/adservers/serverlist.php?hostformat=hosts&mimetype=plaintext"
}

def add_to_blacklist(domain):
    """ Checks if domain is present in whitelist. If not, domain is addded to BL set. """
    match = re_whitelist.match(domain)
    if not match:
        blacklist.add(domain)

def parse_line(line):
    """ Checks if line matches re_blacklist. If so, tries add domain to BL set. """
    global blacklist
    line = line.replace('\\t', " ")
    line = line.replace('\\r', "")
    match = re_blacklist.match(line)
    if match:
        if match.group(2) != None:
            add_to_blacklist(match.group(2))
        elif match.group(3) != None:
            add_to_blacklist(match.group(3))

def process_url(url):
    """ Reads and parses blacklisted domains from URL into BL set. """
    print(f"Processing BL items from: {url}")

    try:
        http = urllib3.PoolManager(timeout=5.0)
        r = http.request('GET', url, retries=2)

        if r.status == 200:
            for line in str(r.data).split('\\n'):
                parse_line(line)
    except Exception as e:
        print(str(e))

def save_config_file():
    """ Saves blacklist in unbound.conf format """
    print(f"Saving {len(blacklist)} blacklisted domains into dnsbl.conf")

    try:
        with open("/var/unbound/etc/dnsbl.conf", 'w') as file:
            # No domains found or DNSBL is disabled
            if (len(blacklist) == 0):
                file.write("")
            else:
                file.write('server:\n')
                for line in blacklist:
                    #file.write('local-zone: "' + str(line) + '" static\n')
                    file.write('local-data: "' + str(line) + ' A 0.0.0.0"\n')
    except Exception as e:
        print(str(e))
        exit(1)

def load_list(path, separator=None):
    """ Reads file with specified path into set to ensure unique values.
    Splits lines with defined separator. If sperator==None no split is performed. """
    result = set()

    try:
        with open(path, 'r') as file:
            for line in file.readlines():
                if not separator == None:
                    for element in line.split(separator):
                        result.add(element.replace('\n', ''))
                else:
                    result.add(line.replace('\n', ''))
    except Exception as e:
        print(str(e))

    return result

def load_whitelist():
    """ Loads user defined whitelist in regex format and compiles it. """
    print("Loading whitelist")
    global re_whitelist
    wl = load_list('/var/unbound/etc/whitelist.inc', ',')
    wl.add(r'.*localhost$')
    wl.add(r'^(?![a-zA-Z\d]).*') # Exclude domains NOT starting with alphanumeric char
    print(f"Loaded {len(wl)} whitelist items")

    try:
        re_whitelist = re.compile('|'.join(wl), re.I)
    except Exception as e:
        print(f"Whitelist regex compile failed: {str(e)}")

def load_blacklists():
    """ Loads user defined blacklists URLs. """
    print("Loading blacklists URLs")
    global urls
    urls = load_list('/var/unbound/etc/lists.inc', ',')
    print(f"Loaded {len(urls)} blacklists URLs")

def load_predefined_lists():
    """ Loads user chosen predefined lists """
    print("Loading predefined lists URLs")
    global urls
    lists = load_list('/var/unbound/etc/dnsbl.inc')
    types = set()

    for first in lists:
        first = str(first).split('=')[1]
        first = str(first).replace('"', '').replace('\n', '')
        first = first.split(',')
        for type in first:
            types.add(type)
        break

    print(f"Loaded {len(types)} predefined blacklists URLs")

    for type in types:
        try:
            urls.add(predefined_lists[type])
        except KeyError:
            continue
        except Exception as e:
            print(str(e))

if __name__ == "__main__":
    # Prepare lists from config files
    load_whitelist()
    load_blacklists()
    load_predefined_lists()

    # Start processing BLs in threads
    threads = [threading.Thread(target=process_url, args=(url,)) for url in urls]
    for t in threads:
        t.start()
    for t in threads:
        t.join()

    save_config_file()

    print("Restarting unbound service")
    subprocess.Popen(["pluginctl", "-s", "unbound", "restart"])
    exit(0)
