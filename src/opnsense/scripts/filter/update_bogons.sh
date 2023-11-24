#!/bin/sh

URL="$(opnsense-update -X)/sets/bogons.txz"
DESTDIR="/usr/local/etc"
WORKDIR="/tmp/bogons"
FETCH="fetch -qT 30"
RETRIES=3

COMMAND=${1}

echo "bogons update starting" | logger

while [ ${RETRIES} -gt 0 ]; do
    if [ "${COMMAND}" = "cron" ]; then
        VALUE=$(jot -r 1 1 900)
        echo "bogons update is sleeping for ${VALUE} seconds" | logger
        sleep ${VALUE}
    fi

    echo "bogons update is beginning the update cycle" | logger

    rm -rf ${WORKDIR}
    mkdir -p ${WORKDIR}

    ${FETCH} -o ${WORKDIR}/bogons.txz.sig "${URL}.sig"
    ${FETCH} -o ${WORKDIR}/bogons.txz "${URL}"

    if [ ! -f ${WORKDIR}/bogons.txz ]; then
        echo "bogons update cannot download ${URL}" | logger
    elif ! opnsense-verify -q ${WORKDIR}/bogons.txz; then
        echo "bogons update cannot verify ${URL}" | logger
    elif ! tar -C ${WORKDIR} -xJf ${WORKDIR}/bogons.txz; then
        echo "bogons update cannot extract ${URL}" | logger
    else
        break
    fi

    if [ "${COMMAND}" = "cron" ]; then
        RETRIES=$((RETRIES - 1))
    else
        RETRIES=0
    fi
done

if [ ${RETRIES} -eq 0 ]; then
    echo "update bogons is aborting the update cycle" | logger
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
    echo "$RESULT" | awk '{ print "Bogons V4 file downloaded: " $0 }' | logger
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
        echo "$RESULT" | awk '{ print "Bogons V6 file downloaded: " $0 }' | logger
    else
        echo "Not saving or updating IPv6 bogons (increase table-entries limit)" | logger
    fi
else
    if [ $ENTRIES_MAX -gt $((2*ENTRIES_TOT+LINES_V6)) ]; then
        egrep -iv "^fc00::/7" ${WORKDIR}/fullbogons-ipv6.txt > ${DESTDIR}/bogonsv6
        echo "Bogons V6 file downloaded but not updating IPv6 bogons table because IPv6 Allow is off" | logger
    else
        echo "Not saving IPv6 bogons table (IPv6 Allow is off and table-entries limit is potentially too low)" | logger
    fi
fi

echo "update bogons is ending the update cycle" | logger
