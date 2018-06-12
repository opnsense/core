#!/bin/sh

if [ "$script_type" = "client-disconnect" ]; then
	/sbin/pfctl -k $ifconfig_pool_remote_ip
	/sbin/pfctl -K $ifconfig_pool_remote_ip
	/usr/local/etc/inc/plugins.inc.d/openvpn/ovpn_cleanup_cso.php $1
fi

exit 0
