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

if [ "${2}" = "inet" ]; then
	if [ -n "${4}" ]; then
		echo ${4} > /tmp/${1}_router
	else
		rm -f /tmp/${1}_router
	fi

	/usr/local/opnsense/scripts/interfaces/nameserver.sh -i ${1} -4 -d ${DNS1} ${DNS2}

	/usr/local/sbin/configctl -d interface newip ${1}
elif [ "${2}" = "inet6" ]; then
	if [ -n "${4}" ]; then
		# XXX if this is link local why bother stripping the required scope?
		echo ${4} | cut -d% -f1 > /tmp/${1}_routerv6
	else
		rm -f /tmp/${1}_routerv6
	fi

	/usr/local/opnsense/scripts/interfaces/nameserver.sh -i ${1} -6 -d ${DNS1} ${DNS2}

	/usr/local/sbin/configctl -d interface newipv6 ${1}
fi

touch /tmp/${1}_uptime

exit 0
