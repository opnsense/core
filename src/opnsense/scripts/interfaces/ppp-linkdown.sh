#!/bin/sh

export PATH=/bin:/usr/bin:/usr/local/bin:/sbin:/usr/sbin:/usr/local/sbin

IF="${1}"
AF="${2}"
IP="${3}"
GW=

DEFAULTGW=$(route -n get -${AF} default | grep gateway: | awk '{print $2}')

ngctl shutdown ${IF}:

if [ "${AF}" = "inet" ]; then
	if [ -s "/tmp/${IF}_defaultgw" ]; then
		GW=$(head -n 1 /tmp/${IF}_defaultgw)
	fi
	if [ -n "${GW}" -a "${DEFAULTGW}" = "${GW}" ]; then
		echo "Removing stale PPPoE gateway ${GW} on ${AF}" | logger -t ppp-linkdown
		route delete -${AF} default "${GW}"
	fi

	if [ -f "/tmp/nameserver_${IF}" ]; then
		# Remove old entries
		for nameserver in $(cat /tmp/nameserver_${IF}); do
			route delete ${nameserver}
		done
		rm -f /tmp/nameserver_${IF}
	fi

	rm -f /tmp/${IF}_router
elif [ "${AF}" = "inet6" ]; then
	if [ -s "/tmp/${IF}_defaultgwv6" ]; then
		GW=$(head -n 1 /tmp/${IF}_defaultgwv6)
	fi

	if [ -n "${GW}" -a "${DEFAULTGW}" = "${GW}" ]; then
		echo "Removing stale PPPoE gateway ${GW} on ${AF}" | logger -t ppp-linkdown
		route delete -${AF} default "${GW}"
	fi

	if [ -f "/tmp/nameserver_v6${IF}" ]; then
		for nameserver in $(cat /tmp/nameserver_v6${IF}); do
			route delete ${nameserver}
		done
		rm -f /tmp/nameserver_v6${IF}
	fi

	rm -f /tmp/${IF}_routerv6

	# remove previous SLAAC addresses as the ISP may
	# not respond to these in the upcoming session
	ifconfig ${IF} | grep -e autoconf -e deprecated | while read FAMILY ADDR MORE; do
		ifconfig ${IF} ${FAMILY} ${ADDR} -alias
	done
fi

/usr/local/sbin/configctl -d dns reload

UPTIME=$(opnsense/scripts/interfaces/ppp-uptime.sh ${IF})
if [ -n "${UPTIME}" -a -f "/conf/${IF}.log" ]; then
	echo $(date -j +%Y.%m.%d-%H:%M:%S) ${UPTIME} >> /conf/${IF}.log
fi

rm -f /tmp/${IF}_uptime

exit 0
