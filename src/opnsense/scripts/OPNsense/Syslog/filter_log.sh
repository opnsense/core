#!/bin/sh

MASK=$1
NUMBER=$2
FILTER=$3

[ "${MASK}" = "" ] && echo "No target" && exit 0;

[ ! -f ${MASK} ] && echo "No logfile" && exit 0;

dump_logs()
{
    for FILE in $( ls -r ${MASK}* ); do
        GZ=`file ${FILE} | grep -c gzip`
        if [ "${GZ}" = "1" ]; then
	        zcat ${FILE}
        else
	        cat ${FILE}
        fi
    done;
}

dump_logs | awk "${FILTER}" | tail -n ${NUMBER}
