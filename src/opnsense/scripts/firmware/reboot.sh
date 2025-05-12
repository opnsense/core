#!/bin/sh

# Copyright (C) 2018-2023 Franco Fichtner <franco@opnsense.org>
# All rights reserved.
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

. /usr/local/opnsense/scripts/firmware/config.sh

WANT_REBOOT=1

DUMMY_UPDATE=$(${PKG} update 2>&1)

LQUERY=$(${PKG} query %v opnsense-update 2> /dev/null)
RQUERY=$(${PKG} rquery %v opnsense-update 2> /dev/null)

# We do not check for downloads here to make sure the actual version
# we want is actually there.  The whole point of this script is a
# hint to the console to reboot.  Worst case we are wrong and the
# reboot doesn't happen.  The GUI offers precise info about it.

if [ -n "${LQUERY}" -a -n "${RQUERY}" -a "${LQUERY%%_*}" != "${RQUERY%%_*}" ]; then
	WANT_REBOOT=0
elif opnsense-update -bk -c; then
	WANT_REBOOT=0
fi

COREPKG=$(opnsense-version -n)

LQUERY=$(${PKG} query %v ${COREPKG} 2> /dev/null)
RQUERY=$(${PKG} rquery %v ${COREPKG} 2> /dev/null)

# Additionally return the next version number if an update to the
# core package is available.  We want to use it to display additional
# information in the shell menu including the matching changelog.

if [ -n "${LQUERY}" -a -n "${RQUERY}" ]; then
	if [ "$(${PKG} version -t ${LQUERY} ${RQUERY})" = "<" ]; then
		echo ${RQUERY%%_*}
	fi
fi

ALWAYS_REBOOT=$(/usr/local/sbin/pluginctl -g system.firmware.reboot)
if [ "${ALWAYS_REBOOT}" = "1" ]; then
	WANT_REBOOT=0
fi

# success is reboot:
exit ${WANT_REBOOT}
