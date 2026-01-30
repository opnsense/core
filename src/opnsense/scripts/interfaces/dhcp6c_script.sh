#!/bin/sh

# Copyright (c) 2017-2020 Martin Wasley <mjwasley@gmail.com>
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

FORCE=

if [ -z "${REASON}" -o -z "${IFNAME}" ]; then
    /usr/bin/logger -t dhcp6c "dhcp6c_script: missing REASON or IFNAME"
fi

case ${REASON} in
INFOREQ|REBIND|RENEW|REQUEST|SOLICIT)
    /usr/bin/logger -t dhcp6c "dhcp6c_script: ${REASON} on ${IFNAME} executing"

    IFCFILE="/tmp/dhcp6c.${IFNAME}.ifconfig"
    ENVFILE="/tmp/dhcp6c.${IFNAME}.env"

    if [ "$(pluginctl -g OPNsense.Interfaces.settings.dhcp6_debug)" = "1" ]; then
        ifconfig -L > "${IFCFILE}"
        env | sort > "${ENVFILE}"
    else
        rm -f "${IFCFILE}" "${ENVFILE}"
    fi

    ARGS=
    for NAMESERVER in ${new_domain_name_servers}; do
        ARGS="${ARGS} -a ${NAMESERVER}"
    done
    /usr/local/sbin/ifctl -i "${IFNAME}" -6nd ${ARGS}

    ARGS=
    for DOMAIN in ${new_domain_name}; do
        ARGS="${ARGS} -a ${DOMAIN}"
    done
    /usr/local/sbin/ifctl -i "${IFNAME}" -6sd ${ARGS}

    if [ ${REASON} = "REQUEST" -o ${REASON} = "SOLICIT" ]; then
        /usr/bin/logger -t dhcp6c "dhcp6c_script: ${REASON} on ${IFNAME} connected to server"
        FORCE=${REASON}

        # can safely clear the prefix during connect
        /usr/local/sbin/ifctl -i "${IFNAME}" -6pd
    fi

    if [ ${REASON} != "INFOREQ" -a -n "${PDINFO}" ]; then
        ARGS=
        for PD in ${PDINFO}; do
            ARGS="${ARGS} -a ${PD}"
        done

        if /usr/local/sbin/ifctl -i "${IFNAME}" -6pu ${ARGS}; then
            /usr/bin/logger -t dhcp6c "dhcp6c_script: ${REASON} on ${IFNAME} prefix now ${PDINFO}"

            if [ -z "${FORCE}" ]; then
                FORCE=${REASON}
            fi
        fi
    fi

    /usr/local/sbin/configctl -d interface newipv6 "${IFNAME}" ${FORCE}
    ;;
EXIT|RELEASE)
    /usr/bin/logger -t dhcp6c "dhcp6c_script: ${REASON} on ${IFNAME} executing"

    /usr/local/sbin/ifctl -i "${IFNAME}" -6nd
    /usr/local/sbin/ifctl -i "${IFNAME}" -6sd
    /usr/local/sbin/ifctl -i "${IFNAME}" -6pd

    /usr/local/sbin/configctl -d interface newipv6 "${IFNAME}"
    ;;
*)
    /usr/bin/logger -t dhcp6c "dhcp6c_script: ${REASON} on ${IFNAME} ignored"
    ;;
esac
