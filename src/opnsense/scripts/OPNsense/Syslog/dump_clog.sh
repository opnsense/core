#!/bin/sh

MASK=$1
NUMBER=$2
REVERSE=$3
FILTER=$4

[ "${MASK}" = "" ] && echo "No target" && exit 0;

[ ! -f ${MASK} ] && echo "No logfile" && exit 0;

if [ "${REVERSE}" = "" -o "${REVERSE}" = "0" ]; then
    GET="head"
else
    GET="tail -r"
fi

[ "${FILTER}" = "" ] && FILTER="//"

if [ "${NUMBER}" = "" -o "${NUMBER}" = "0" ]; then
    /usr/local/sbin/clog ${MASK} | awk "${FILTER}"
else
    /usr/local/sbin/clog ${MASK} | awk "${FILTER}" | ${GET} -n ${NUMBER}
fi
