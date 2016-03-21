#!/bin/sh

SQUID_DIRS="/var/log/squid /var/run/squid /var/squid /var/squid/cache /var/squid/ssl /var/squid/logs"

for SQUID_DIR in ${SQUID_DIRS}; do
	mkdir -p ${SQUID_DIR}
	chown -R squid:squid ${SQUID_DIR}
	chmod -R 750 ${SQUID_DIR}
done
/usr/sbin/pw groupmod proxy -m squid
/usr/local/sbin/squid -z > /dev/null 2>&1

# wait a moment before exit, running squid -z and squid start without time between them sometimes results in
# some vague errors.
sleep 1

# create ssl certificate store, in case sslbump is enabled we need this
if [ ! -d /var/squid/ssl_crtd ]; then
/usr/local/libexec/squid/ssl_crtd -c -s /var/squid/ssl_crtd > /dev/null 2>&1
chown -R squid:squid /var/squid/ssl_crtd
chmod -R 750 /var/squid/ssl_crtd
fi

# generate SSL bump certificate
/usr/local/opnsense/scripts/proxy/generate_cert.php
