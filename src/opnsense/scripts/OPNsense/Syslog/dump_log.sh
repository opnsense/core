#!/bin/sh

MASK=$1

[ "${MASK}" = "" ] && echo "No target" && exit 0;

for FILE in $( ls -r ${MASK}* ); do
    GZ=`file ${FILE} | grep -c gzip`
    if [ "${GZ}" = "1" ]; then
	zcat ${FILE}
    else
	cat ${FILE}
    fi
done;
