#!/bin/sh

export PATH=/bin:/usr/bin:/usr/local/bin:/sbin:/usr/sbin:/usr/local/sbin

if [ "${2}" = "inet" ]; then
	rm -f /tmp/${1}_nameserver /tmp/${1}_router

	if [ -n "${4}" ]; then
		echo ${4} > /tmp/${1}_router
	fi

	if echo "${6}" | grep -q dns1; then
		DNS1=$(echo "${6}" | awk '{print $2}')
		echo "${DNS1}" >> /tmp/${1}_nameserver
		route delete "${DNS1}" ${4}
		route add "${DNS1}" ${4}
	fi

	if echo "${7}" | grep -q dns2; then
		DNS2=$(echo "${7}" | awk '{print $2}')
		echo "${DNS2}" >> /tmp/${1}_nameserver
		route delete "${DNS2}" ${4}
		route add "${DNS2}" ${4}
	fi

	/usr/local/sbin/configctl -d interface newip ${1}
elif [ "${2}" = "inet6" ]; then
	rm -f /tmp/${1}_nameserverv6 /tmp/${1}_routerv6

	if [ -n "${4}" ]; then
		echo ${4} | cut -d% -f1 > /tmp/${1}_routerv6
	fi

	if echo "${6}" | grep -q dns1; then
		DNS1=$(echo "${6}" | awk '{print $2}')
		echo "${DNS1}" >> /tmp/${1}_nameserverv6
		route delete -inet6 "${DNS1}" ${4}
		route add -inet6 "${DNS1}" ${4}
	fi

	if echo "${7}" | grep -q dns2; then
		DNS2=$(echo "${7}" | awk '{print $2}')
		echo "${DNS2}" >> /tmp/${1}_nameserverv6
		route delete -inet6 "${DNS2}" ${4}
		route add -inet6 "${DNS2}" ${4}
	fi

	/usr/local/sbin/configctl -d interface newipv6 ${1}
fi

touch /tmp/${1}_uptime

exit 0
