#!/bin/sh

if [ -f /tmp/${1}_uptime ]; then
	echo $((`date -j +%s` - `/usr/bin/stat -f %m /tmp/${1}_uptime`))
fi
