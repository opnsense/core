#!/bin/sh

export PATH=/bin:/usr/bin:/usr/local/bin:/sbin:/usr/sbin:/usr/local/sbin

IF="${1}"
AF="${2}"
IP="${3}"
GW=

DEFAULTGW=$(route -n get -${AF} default | grep gateway: | awk '{print $2}')

ngctl shutdown ${IF}:

if [ "${AF}" = "inet" ]; then
	if [ -f /tmp/${IF}up -a -f /conf/${IF}.log ]; then
		seconds=$((`date -j +%s` - `/usr/bin/stat -f %m /tmp/${IF}up`))
		/usr/local/opnsense/scripts/interfaces/ppp-log-uptime.sh $seconds ${IF} &
	fi

	if [ -s "/tmp/${IF}_defaultgw" ]; then
		GW=$(head -n 1 /tmp/${IF}_defaultgw)
	fi
	if [ -n "${GW}" -a "${DEFAULTGW}" = "${GW}" ]; then
		echo "Removing stale PPPoE gateway ${GW} on ${AF}" | logger -t ppp-linkdown
		route delete -${AF} default "${GW}"
	fi

	if [ -f "/var/etc/nameserver_${IF}" ]; then
		# Remove old entries
		for nameserver in $(cat /var/etc/nameserver_${IF}); do
			route delete ${nameserver}
		done
		rm -f /var/etc/nameserver_${IF}
	fi

	# Do not remove gateway used during filter reload.
	rm -f /tmp/${IF}_router /tmp/${IF}up /tmp/${IF}_ip
elif [ "${AF}" = "inet6" ]; then
	if [ -s "/tmp/${IF}_defaultgwv6" ]; then
		GW=$(head -n 1 /tmp/${IF}_defaultgwv6)
	fi
	if [ -n "${GW}" -a "${DEFAULTGW}" = "${GW}" ]; then
		echo "Removing stale PPPoE gateway ${GW} on ${AF}" | logger -t ppp-linkdown
		route delete -${AF} default "${GW}"
	fi

	if [ -f "/var/etc/nameserver_v6${IF}" ]; then
		# Remove old entries
		for nameserver in $(cat /var/etc/nameserver_v6${IF}); do
			route delete ${nameserver}
		done
		rm -f /var/etc/nameserver_v6${IF}
	fi

	# Do not remove gateway used during filter reload.
	rm -f /tmp/${IF}_routerv6 /tmp/${IF}upv6 /tmp/${IF}_ipv6

	# flush stale IPv6 addresses since mpd5 will not do it
	php << EOF
<?
require_once 'util.inc';
require_once 'interfaces.inc';
interfaces_addresses_flush('${IF}', 6);
EOF
fi

daemon -f /usr/local/opnsense/service/configd_ctl.py dns reload

exit 0
