#!/bin/sh

export PATH=/bin:/usr/bin:/usr/local/bin:/sbin:/usr/sbin:/usr/local/sbin

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

if [ "${2}" = "inet" ]; then
	/usr/local/sbin/ifctl -i ${1} -4nd ${DNS1} ${DNS2}
	/usr/local/sbin/ifctl -i ${1} -4rd ${ROUTER}
	/usr/local/opnsense/scripts/interfaces/ppp-ipv6.php ${1} 4
	/usr/local/sbin/configctl -d interface newip ${1} force
elif [ "${2}" = "inet6" ]; then
	/usr/local/sbin/ifctl -i ${1} -6nd ${DNS1} ${DNS2}
	/usr/local/sbin/ifctl -i ${1} -6rd ${ROUTER}
	if ! /usr/local/opnsense/scripts/interfaces/ppp-ipv6.php ${1} 6; then
		# trigger event here since no higher layer IPv6 will trigger it
		/usr/local/sbin/configctl -d interface newipv6 ${1} force
	fi
fi

touch /tmp/${1}_uptime

exit 0
