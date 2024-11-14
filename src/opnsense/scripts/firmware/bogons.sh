#!/bin/sh

# Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>
# Copyright (C) 2005 Scott Ullrich <sullrich@gmail.com>
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions are met:
#
# 1. Redistributions of source code must retain the above copyright notice,
#    this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright
#    notice, this list of conditions and the following disclaimer in the
#    documentation and/or other materials provided with the distribution.
#
# THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
# INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
# AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
# AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
# OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
# SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
# INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
# CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
# ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
# POSSIBILITY OF SUCH DAMAGE.

. /usr/local/opnsense/scripts/firmware/config.sh

URL="$(opnsense-update -X)/sets/bogons.txz"
DESTDIR="/usr/local/etc"
WORKDIR="/tmp/bogons"
FETCH="fetch -qT 30"

echo "bogons update starting" | logger

rm -rf ${WORKDIR}
mkdir -p ${WORKDIR}

${FETCH} -o ${WORKDIR}/bogons.txz.sig "${URL}.sig"
${FETCH} -o ${WORKDIR}/bogons.txz "${URL}"

if [ ! -f ${WORKDIR}/bogons.txz ]; then
    echo "bogons update cannot download ${URL}" | logger
    exit 1
elif ! opnsense-verify -q ${WORKDIR}/bogons.txz; then
    echo "bogons update cannot verify ${URL}" | logger
    exit 1
elif ! tar -C ${WORKDIR} -xJf ${WORKDIR}/bogons.txz; then
    echo "bogons update cannot extract ${URL}" | logger
    exit 1
fi

ENTRIES_MAX=`pfctl -s memory | awk '/table-entries/ { print $4 }'`
ENTRIES_TOT=`pfctl -vvsTables | awk '/Addresses/ {s+=$2}; END {print s}'`
ENTRIES_V4=`pfctl -vvsTables | awk '/-\tbogons$/ {getline; print $2}'`
LINES_V4=`wc -l ${WORKDIR}/fullbogons-ipv4.txt | awk '{ print $1 }'`
if [ $ENTRIES_MAX -gt $((2*ENTRIES_TOT-${ENTRIES_V4:-0}+LINES_V4)) ]; then
    # private and pseudo-private networks will be excluded
    # as they are being operated by a separate GUI option
    egrep -v "^100.64.0.0/10|^192.168.0.0/16|^172.16.0.0/12|^10.0.0.0/8" ${WORKDIR}/fullbogons-ipv4.txt > ${DESTDIR}/bogons
    RESULT=`/sbin/pfctl -t bogons -T replace -f ${DESTDIR}/bogons 2>&1`
    echo "$RESULT" | awk '{ print "Bogons V4 file updated: " $0 }' | logger
else
    echo "Not updating IPv4 bogons (increase table-entries limit)" | logger
fi

ENTRIES_MAX=`pfctl -s memory | awk '/table-entries/ { print $4 }'`
ENTRIES_TOT=`pfctl -vvsTables | awk '/Addresses/ {s+=$2}; END {print s}'`
BOGONS_V6_TABLE_COUNT=`pfctl -sTables | grep ^bogonsv6$ | wc -l | awk '{ print $1 }'`
ENTRIES_TOT=`pfctl -vvsTables | awk '/Addresses/ {s+=$2}; END {print s}'`
LINES_V6=`wc -l ${WORKDIR}/fullbogons-ipv6.txt | awk '{ print $1 }'`
if [ $BOGONS_V6_TABLE_COUNT -gt 0 ]; then
    ENTRIES_V6=`pfctl -vvsTables | awk '/-\tbogonsv6$/ {getline; print $2}'`
    if [ $ENTRIES_MAX -gt $((2*ENTRIES_TOT-${ENTRIES_V6:-0}+LINES_V6)) ]; then
        egrep -iv "^fc00::/7" ${WORKDIR}/fullbogons-ipv6.txt > ${DESTDIR}/bogonsv6
        RESULT=`/sbin/pfctl -t bogonsv6 -T replace -f ${DESTDIR}/bogonsv6 2>&1`
        echo "$RESULT" | awk '{ print "Bogons V6 file updated: " $0 }' | logger
    else
        echo "Not saving or updating IPv6 bogons (increase table-entries limit)" | logger
    fi
else
    if [ $ENTRIES_MAX -gt $((2*ENTRIES_TOT+LINES_V6)) ]; then
        egrep -iv "^fc00::/7" ${WORKDIR}/fullbogons-ipv6.txt > ${DESTDIR}/bogonsv6
        echo "Not updating IPv6 bogons table because IPv6 Allow is off" | logger
    else
        echo "Not saving IPv6 bogons table (IPv6 Allow is off and table-entries limit is potentially too low)" | logger
    fi
fi

echo "update bogons is ending the update cycle" | logger
