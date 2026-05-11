#!/bin/sh

# Copyright (C) 2018 Martin Wasley <mjwasley@gmail.com>
# Copyright (c) 2017-2026 Franco Fichtner <franco@opnsense.org>
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions are met:
#
# 1. Redistributions of source code must retain the above copyright notice,
#    this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright
#    notice, this list of conditions and the following disclaimer in the
#    documentation and/or other materials provided with the distribution.
#
# THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
# INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
# AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
# AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
# OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
# SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
# INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
# CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
# ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
# POSSIBILITY OF SUCH DAMAGE.

IFNAME=${1}
ROUTER=${2}

if [ -z "${IFNAME}" ]; then
    echo "Nothing to do."
    exit 0
fi

if grep -q "^interface ${IFNAME} " /var/etc/radvd.conf; then
       /usr/bin/logger -t dhcp6c "rtsold_script: rejecting dhcp6c"
       echo "Rejecting own configuration."
       exit 0
fi

DONEFILE="/tmp/rtsold.${IFNAME}.done"
CONFFILE="/var/etc/dhcp6c.conf"
PIDFILE="/var/run/dhcp6c.pid"

if [ ! -f "${CONFFILE}" ]; then
    /usr/bin/logger -t dhcp6c "rtsold_script: skipping dhcp6c"
    exit 0
fi

get_var()
{
    VAR=${1}

    grep "^#${VAR}=" "${CONFFILE}" | head -n 1 | tr -d "#${VAR}="
}

if [ -n "${ROUTER}" ]; then
    # Note that the router file can be written by ppp-linkup.sh or
    # this script so only replace information if it was supplied
    /usr/local/sbin/ifctl -i "${IFNAME}" -6rd -a "${ROUTER}"
else
    # in this case we are the failsafe start if no RA was sent
    sleep "$(get_var RATIMEOUT)"
    if [ -f "${DONEFILE}" ]; then
        # normal RA came through
        exit 0
    fi
fi

if [ -f "${PIDFILE}" ]; then
    if ! /bin/pkill -0 -F "${PIDFILE}"; then
        rm -f "${PIDFILE}"
    fi
fi

if [ -f "${PIDFILE}" ]; then
    /usr/bin/logger -t dhcp6c "rtsold_script: reloading dhcp6c"
    /bin/pkill -HUP -F "${PIDFILE}"
else
    /usr/bin/logger -t dhcp6c "rtsold_script: starting dhcp6c"
    /usr/local/sbin/dhcp6c $(get_var EXTRAOPTS) -c "${CONFFILE}" -p "${PIDFILE}"
fi

touch "${DONEFILE}"
