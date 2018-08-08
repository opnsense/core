#!/bin/sh

if [ "$script_type" = "client-disconnect" ]; then
	/sbin/pfctl -k $ifconfig_pool_remote_ip
	/sbin/pfctl -K $ifconfig_pool_remote_ip
fi

exit 0
