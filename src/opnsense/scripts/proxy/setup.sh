#!/bin/sh

SQUID_DIRS="/var/log/squid /var/run/squid /var/squid /var/squid/cache /var/squid/logs"

for SQUID_DIR in ${SQUID_DIRS}; do
	mkdir -p ${SQUID_DIR}
	chown -R squid:squid ${SQUID_DIR}
	chmod -R 750 ${SQUID_DIR}
done

/usr/local/sbin/squid -z
