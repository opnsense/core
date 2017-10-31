#!/bin/sh

MASK=$1
NUMBER=$2
FILTER=$4

[ "${MASK}" = "" ] && echo "No target" && exit 0;

[ ! -f ${MASK} ] && echo "No logfile" && exit 0;

[ "${FILTER}" = "" ] && FILTER="//"

dump_logs()
{
    for FILE in $( ls -r ${MASK}* ); do
        GZ=`file ${FILE} | grep -c gzip`
        if [ "${GZ}" = "1" ]; then
	        zcat ${FILE} | awk "${FILTER}"
        else
	        cat ${FILE} | awk "${FILTER}"
        fi
    done;
}

if [ "${NUMBER}" = "" -o "${NUMBER}" = "0" ]; then
    dump_logs
else
    dump_logs | tail -n ${NUMBER}
fi