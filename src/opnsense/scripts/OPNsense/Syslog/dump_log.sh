#!/bin/sh

MASK=$1
LIMIT=$2

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

if [ "${NUMBER}" = "" -o "${NUMBER}" = "0" ]; then
	dump_logs
else
	dump_logs | tail -c ${NUMBER}
fi
