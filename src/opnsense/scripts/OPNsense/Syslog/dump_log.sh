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
    dump_logs | ${GET} -n ${NUMBER}
fi