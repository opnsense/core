#!/bin/sh

if [ "$1" == "all" ]; then
    echo "flush all local netflow data"
    /usr/local/etc/rc.d/flowd_aggregate stop
    /usr/local/etc/rc.d/flowd stop
    rm  /var/netflow/*.sqlite
    rm /var/log/flowd.log*
    /usr/local/etc/rc.d/flowd start
    /usr/local/etc/rc.d/flowd_aggregate start
else
    echo "not flushing local netflow data, provide all as parameter to do so"
fi
