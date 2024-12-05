#!/bin/sh

export PATH=/bin:/usr/bin:/usr/local/bin:/sbin:/usr/sbin:/usr/local/sbin

IF="${1}"
AF="${2}"
IP="${3}"
GW=

DEFAULTGW=$(route -n get -${AF} default | grep gateway: | awk '{print $2}')

ngctl shutdown ${IF}:

if [ "${AF}" = "inet" ]; then
	/usr/local/sbin/ifctl -i ${IF} -4nd
	/usr/local/sbin/ifctl -i ${IF} -4rd

	/usr/local/sbin/configctl -d interface newip ${IF}
elif [ "${AF}" = "inet6" ]; then
	# remove previous SLAAC addresses as the ISP may
	# not respond to these in the upcoming session
	/usr/local/sbin/ifctl -i ${IF} -f

	/usr/local/sbin/ifctl -i ${IF} -6nd
	/usr/local/sbin/ifctl -i ${IF} -6rd

	/usr/local/sbin/configctl -d interface newipv6 ${IF}
fi

UPTIME=$(/usr/local/opnsense/scripts/interfaces/ppp-uptime.sh ${IF})
if [ -n "${UPTIME}" -a -f "/conf/${IF}.log" ]; then
	echo $(date -j +%Y.%m.%d-%H:%M:%S) ${UPTIME} >> /conf/${IF}.log
fi

rm -f /tmp/${IF}_uptime

exit 0
