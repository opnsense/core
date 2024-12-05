#!/bin/sh

export PATH=/bin:/usr/bin:/usr/local/bin:/sbin:/usr/sbin:/usr/local/sbin

IF="${1}"
AF="${2}"

DNS1=
if echo "${6}" | grep -q dns1; then
	DNS1="-a $(echo "${6}" | awk '{print $2}')"
fi

DNS2=
if echo "${7}" | grep -q dns2; then
	DNS2="-a $(echo "${7}" | awk '{print $2}')"
fi

ROUTER=
if [ -n "${4}" ]; then
	# XXX if this is link-local why bother stripping the required scope?
	ROUTER="-a $(echo ${4} | cut -d% -f1)"
fi

/usr/bin/logger -t ppp "ppp-linkup: executing on ${IF} for ${AF}"

if [ "${AF}" = "inet" ]; then
	/usr/local/sbin/ifctl -i ${IF} -4nd ${DNS1} ${DNS2}
	/usr/local/sbin/ifctl -i ${IF} -4rd ${ROUTER}
	/usr/local/opnsense/scripts/interfaces/ppp-ipv6.php ${IF} 4
	/usr/local/sbin/configctl -d interface newip ${IF} force
elif [ "${AF}" = "inet6" ]; then
	/usr/local/sbin/ifctl -i ${IF} -6nd ${DNS1} ${DNS2}
	/usr/local/sbin/ifctl -i ${IF} -6rd ${ROUTER}
	if ! /usr/local/opnsense/scripts/interfaces/ppp-ipv6.php ${IF} 6; then
		# trigger event here since no higher layer IPv6 will trigger it
		/usr/local/sbin/configctl -d interface newipv6 ${IF} force
	fi
fi

touch /tmp/${IF}_uptime

exit 0
