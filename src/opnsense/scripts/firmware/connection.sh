#!/bin/sh

# Copyright (C) 2021 Franco Fichtner <franco@opnsense.org>
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

LOCKFILE="/tmp/pkg_upgrade.progress"
TEE="/usr/bin/tee -a"

: > ${LOCKFILE}

URL=$(opnsense-update -M)
HOST=${URL#*://}
HOST=${HOST%%/*}
IPV4=$(host -t A ${HOST} | head -n 1 | cut -d\  -f4)
IPV6=$(host -t AAAA ${HOST} | head -n 1 | cut -d\  -f5)

echo "***GOT REQUEST TO AUDIT CONNECTIVITY***" >> ${LOCKFILE}
echo "Currently running $(opnsense-version) at $(date)" >> ${LOCKFILE}
echo "Checking connectivity for host: ${HOST}" | ${TEE} ${LOCKFILE}
if [ -n "${IPV4}" -a -z "${IPV4%%*.*}" ]; then
	(ping -c4 ${IPV4} 2>&1) | ${TEE} ${LOCKFILE}
else
	echo "No IPv4 address could be found." | ${TEE} ${LOCKFILE}
fi
if [ -n "${IPV6}" -a -z "${IPV6%%*:*}" ]; then
	(ping6 -c4 ${IPV6} 2>&1) | ${TEE} ${LOCKFILE}
else
	echo "No IPv6 address could be found." | ${TEE} ${LOCKFILE}
fi
(pkg update -f 2>&1) | ${TEE} ${LOCKFILE}
echo '***DONE***' >> ${LOCKFILE}
