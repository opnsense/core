#!/bin/sh

/sbin/pfctl -k $ifconfig_pool_remote_ip
/sbin/pfctl -K $ifconfig_pool_remote_ip

exit 0
