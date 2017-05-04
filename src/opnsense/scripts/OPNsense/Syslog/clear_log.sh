#!/bin/sh

MASK=$1

[ "${MASK}" = "" ] && exit 0;

[ ! -f ${MASK} ] && exit 0;

rm ${MASK}.*
truncate -c -s 0 ${MASK}
