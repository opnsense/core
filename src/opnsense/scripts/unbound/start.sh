#!/bin/sh

# Copyright (c) 2020-2021 Deciso B.V.
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

set -e

# prepare and startup unbound, so we can easily background it

chroot -u unbound -g unbound / /usr/local/sbin/unbound-anchor -a /var/unbound/root.key

if [ ! -f /var/unbound/unbound_control.key ]; then
    chroot -u unbound -g unbound / /usr/local/sbin/unbound-control-setup -d /var/unbound
fi

for FILE in $(find /var/unbound/etc -depth 1); do
	rm -rf ${FILE}
done

for FILE in $(find /usr/local/etc/unbound.opnsense.d -depth 1 -name '*.conf'); do
	cp ${FILE} /var/unbound/etc/
done

chown -R unbound:unbound /var/unbound

/usr/local/sbin/unbound -c /var/unbound/unbound.conf
/usr/local/opnsense/scripts/unbound/cache.sh load
