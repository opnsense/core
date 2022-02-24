#!/bin/sh

export PATH=/bin:/usr/bin:/usr/local/bin:/sbin:/usr/sbin:/usr/local/sbin

if [ "${2}" = "inet" ]; then
	rm -f /tmp/${1}_router

	if [ -n "${4}" ]; then
		echo ${4} > /tmp/${1}_router
	fi

	/usr/local/opnsense/scripts/interfaces/nameserver.sh -d {1} 4

	if echo "${6}" | grep -q dns1; then
		DNS1=$(echo "${6}" | awk '{print $2}')
		/usr/local/opnsense/scripts/interfaces/nameserver.sh -a ${DNS1} {1} 4
	fi

	if echo "${7}" | grep -q dns2; then
		DNS2=$(echo "${7}" | awk '{print $2}')
		/usr/local/opnsense/scripts/interfaces/nameserver.sh -a ${DNS2} {1} 4
	fi

	/usr/local/sbin/configctl -d interface newip ${1}
elif [ "${2}" = "inet6" ]; then
	rm -f /tmp/${1}_routerv6

	if [ -n "${4}" ]; then
		echo ${4} | cut -d% -f1 > /tmp/${1}_routerv6
	fi

	/usr/local/opnsense/scripts/interfaces/nameserver.sh -d {1} 6

	if echo "${6}" | grep -q dns1; then
		DNS1=$(echo "${6}" | awk '{print $2}')
		/usr/local/opnsense/scripts/interfaces/nameserver.sh -a ${DNS1} {1} 6
	fi

	if echo "${7}" | grep -q dns2; then
		DNS2=$(echo "${7}" | awk '{print $2}')
		/usr/local/opnsense/scripts/interfaces/nameserver.sh -a ${DNS2} {1} 6
	fi

	/usr/local/sbin/configctl -d interface newipv6 ${1}
fi

touch /tmp/${1}_uptime

exit 0
