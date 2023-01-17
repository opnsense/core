#!/bin/sh

# Copyright (c) 2020-2022 Deciso B.V.
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions
# are met:
#
# 1. Redistributions of source code must retain the above copyright
#    notice, this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright
#    notice, this list of conditions and the following disclaimer in the
#    documentation and/or other materials provided with the distribution.
#
# THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND
# ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
# IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
# ARE DISCLAIMED.  IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE
# FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
# DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
# OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
# HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
# LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
# OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
# SUCH DAMAGE.

# prepare and startup unbound, so we can easily background it

DOMAIN=${1}

for FILE in $(find /var/unbound/etc -depth 1); do
	rm -rf ${FILE}
done

# XXX remove obsolete file, last used in 22.7
rm -f /usr/local/etc/unbound.opnsense.d/dnsbl.conf

# copy includes
for FILE in $(find /usr/local/etc/unbound.opnsense.d -depth 1 -name '*.conf'); do
	cp ${FILE} /var/unbound/etc/
done

# check config
cd /var/unbound/
# if trust anchor file specified and key file is missing or damaged, run unbound-anchor
if ! check_output=$(/usr/local/sbin/unbound-checkconf /var/unbound/unbound.conf 2>&1); then
	if ! $(echo "$check_output" | grep -q 'root.key'); then
		# checkconf error but not trust anchor related. there is nothing we can do
		msg="$(echo "$check_output" | tr '\n' ';')"
		logger -s -t unbound -p user.error "unbound-checkconf error. output was: $msg"
		exit 1
	else
		# anchor file specified but the root key is missed or corrupted
		logger -s -t unbound -p user.debug "unbound-checkconf error. dnssec root key is missing or corrupted. update the key"
		# unbound-anchor has undefined behaviour if file is corrupted, start clean
		rm -f /var/unbound/root.key

		# if we are in forwarding mode, prefer to use the configured system nameservers
		if [ -s /var/unbound/resolv.conf.root ]; then
			OPT_RESOLVE="-Rf /var/unbound/resolv.conf.root"
		fi

		# unbound-anchor exits with 1 on failover, since we would still like to start unbound,
		# always let this succeed
		chroot -u unbound -g unbound / /usr/local/sbin/unbound-anchor -a /var/unbound/root.key ${OPT_RESOLVE}
	fi
fi

if [ ! -f /var/unbound/unbound_control.key ]; then
	chroot -u unbound -g unbound / /usr/local/sbin/unbound-control-setup -d /var/unbound
fi

chown -R unbound:unbound /var/unbound

if ! start_output=$(/usr/local/sbin/unbound -c /var/unbound/unbound.conf 2>&1); then
        msg="$(echo "$start_output" | tr '\n' ';')"
	logger -s -t unbound -p user.error "unbound start failed. output was: $msg"
	exit 1
fi

/usr/local/opnsense/scripts/unbound/cache.sh load

if [ -n "${DOMAIN}" ]; then
	/usr/local/opnsense/scripts/dhcp/unbound_watcher.py --domain ${DOMAIN}
fi

if [ -f /var/unbound/data/stats ]; then
    /usr/sbin/daemon -f -S -m 2 -s err -l local4 -T unbound /usr/local/opnsense/scripts/unbound/logger.py
fi
