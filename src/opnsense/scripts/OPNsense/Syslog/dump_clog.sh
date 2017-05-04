#!/bin/sh

MASK=$1

[ "${MASK}" = "" ] && echo "No target" && exit 0;

[ ! -f ${MASK} ] && echo "No logfile" && exit 0;

/usr/local/sbin/clog ${MASK}
