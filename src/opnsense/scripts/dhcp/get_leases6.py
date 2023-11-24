#!/usr/local/bin/python3

"""
    Copyright (c) 2023 Deciso B.V.
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

    --------------------------------------------------------------------------------------
    list dhcpv6 leases
"""
import ujson
import calendar
import datetime
import time
import argparse
import re

def parse_date(ymd, hms):
    dt = '%s %s' % (ymd, hms)
    try:
        return calendar.timegm(datetime.datetime.strptime(dt, "%Y/%m/%d %H:%M:%S;").timetuple())
    except ValueError:
        return None

def parse_iaaddr_iaprefix(input):
    """
    parse either an iaaddr or iaprefix segment. return a tuple
    containing the type parsed and the corresponding segment
    """
    seg_type = input[0].split()[0]
    segment = dict()
    segment[seg_type] = input[0].split()[1]
    for line in input[1:]:
        parts = line.split()
        field_name = parts[0] if len(parts) > 0 else ''
        field_value = None
        if field_name == 'binding':
            segment[field_name] = parts[2].strip(';')
        elif field_name in ('preferred-life', 'max-life'):
            field_value = parts[1].strip(';')
        elif field_name == 'ends' and len(parts) >= 4:
            field_value = parse_date(parts[2], parts[3])

        if field_value is not None:
            segment[field_name] = field_value

    return (seg_type, segment)

def parse_iaid_duid(input):
    """
    parse the combined IAID_DUID value. This is provided in octal format.
    non-printable characters are provided as octal escapes.
    We return the hex representation of the raw IAID_DUID value, the IAID integer,
    as well as the separated DUID value in a dict. The IAID_DUID value is
    used to uniquely identify a lease, so this value should be used to determine the last
    relevant entry in the leases file.
    """
    input = input[1:-1] # strip double quotes
    parsed = []
    i = 0
    while i < len(input):
        c = input[i]
        if c == '\\':
            next_c = input[i + 1]
            if next_c == '\\' or next == '"':
                parsed.append("%02x" % ord(next_c))
                i += 1
            elif next_c.isnumeric():
                octal_to_decimal = int(input[i+1:i+4], 8)
                parsed.append("%02x" % octal_to_decimal)
                i += 3
        else:
            parsed.append("%02x" % ord(c))
        i += 1

    return {
        'iaid': int(''.join(reversed(parsed[0:4])), 16),
        'duid': ":".join([str(a) for a in parsed[4:]]),
        'iaid_duid': ":".join([str(a) for a in parsed])
    }

def parse_lease(lines):
    """
    Parse a DHCPv6 lease. We return a two-tuple containing the combined iaid_duid
    and the lease. a single lease may contain multiple addresses/prefixes.
    """
    lease = dict()
    cur_segment = []
    addresses = []
    prefixes = []
    # make sure any whitespace between the iaid_duid and the double quotes is removed
    first = re.sub(r'"\s*([^"]*?)\s*"', r'"\1"', lines[0]).split()[1]
    iaid_duid = parse_iaid_duid(first)
    lease['lease_type'] = lines[0].split()[0]
    lease.update(iaid_duid)

    for line in lines:
        parts = line.split()

        if parts[0] == 'cltt' and len(parts) >= 3:
            cltt = parse_date(parts[2], parts[3])
            lease['cltt'] = cltt

        if parts[0] == 'iaaddr' or parts[0] == 'iaprefix':
            cur_segment.append(line)
        elif len(line) > 1 and line[0] == ' ' and '}' in line and len(cur_segment) > 0:
            cur_segment.append(line)
            (segment_type, segment) = parse_iaaddr_iaprefix(cur_segment)
            if segment_type == 'iaaddr':
                addresses.append(segment)
            if segment_type == 'iaprefix':
                prefixes.append(segment)
            cur_segment = []
        elif len(cur_segment) > 0:
            cur_segment.append(line)

    # ia_ta/ia_na (addresses) and ia_pd (prefixes) are mutually exclusive.
    if addresses:
        lease['addresses'] = addresses
    if prefixes:
        lease['prefixes'] = prefixes

    return (iaid_duid['iaid_duid'], lease)

if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--inactive', help='include inactive leases', default='0', type=str)
    args = parser.parse_args()
    leasefile = '/var/dhcpd/var/db/dhcpd6.leases'
    result = []
    cur_lease = []
    last_leases = dict()

    try:
        with open(leasefile, 'r') as leasef:
            for line in leasef:
                if len(line) > 5 and (line[0:5] == 'ia-ta' or line[0:5] == 'ia-na' or line[0:5] == 'ia-pd'):
                    cur_lease.append(line)
                elif len(line) > 1 and line[0] == '}' and len(cur_lease) > 0:
                    cur_lease.append(line)
                    parsed_lease = parse_lease(cur_lease)
                    last_leases[parsed_lease[0]] = parsed_lease[1]
                    cur_lease = []
                elif len(cur_lease) > 0:
                    cur_lease.append(line)
    except IOError:
        pass

    for lease in last_leases.values():
        if args.inactive == '1':
            result.append(lease)
        else:
            for key in ('addresses', 'prefixes'):
                if key in lease:
                    for i in range(len(lease[key])):
                        segment = lease[key][i]
                        if not ('ends' in segment and segment['ends'] is not None and segment['ends'] > time.time()):
                            del lease[key][i]
                if key in lease and lease[key]:
                    result.append(lease)

    print(ujson.dumps(result))
