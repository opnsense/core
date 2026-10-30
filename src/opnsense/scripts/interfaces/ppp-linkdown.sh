#!/bin/sh

export PATH=/bin:/usr/bin:/usr/local/bin:/sbin:/usr/sbin:/usr/local/sbin

IF="${1}"
AF="${2}"

/usr/bin/logger -t ppp "ppp-linkdown: executing on ${IF} for ${AF}"

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

exit 0
