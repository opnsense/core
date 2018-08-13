#!/bin/sh

SQUID_DIRS="/var/log/squid /var/run/squid /var/squid /var/squid/cache /var/squid/ssl /var/squid/logs"

for SQUID_DIR in ${SQUID_DIRS}; do
    mkdir -p ${SQUID_DIR}
    chown -R squid:squid ${SQUID_DIR}
    chmod -R 750 ${SQUID_DIR}
done
/usr/sbin/pw groupmod proxy -m squid
/usr/local/sbin/squid -z -N > /dev/null 2>&1

# remove ssl certificate store in case the user changed the CA
if [ -f /usr/local/etc/squid/ca.pem.id ]; then
    current_cert=`cat /usr/local/etc/squid/ca.pem.id`
    if [ -d /var/squid/ssl_crtd ]; then
        if [ -f /var/squid/ssl_crtd.id ]; then
          running_cert=`cat /var/squid/ssl_crtd.id`
        else
          running_cert=""
        fi
        if [ "$current_cert" != "$running_cert" ]; then
            rm -rf /var/squid/ssl_crtd
        fi
    fi
fi

# create ssl certificate store, in case sslbump is enabled we need this
if [ ! -d /var/squid/ssl_crtd ]; then
    /usr/local/libexec/squid/ssl_crtd -c -s /var/squid/ssl_crtd > /dev/null 2>&1
    chown -R squid:squid /var/squid/ssl_crtd
    chmod -R 750 /var/squid/ssl_crtd
    if [ -f /usr/local/etc/squid/ca.pem.id ]; then
        cat /usr/local/etc/squid/ca.pem.id > /var/squid/ssl_crtd.id
    fi
fi

# generate SSL bump certificate
/usr/local/opnsense/scripts/proxy/generate_cert.php > /dev/null 2>&1

# on bootup, wait some time before executing squid. For some reason ipfw might interfere with squid startup
if [ -f /var/run/booting ]; then
   sleep 2
fi
