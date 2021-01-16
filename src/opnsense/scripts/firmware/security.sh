#!/bin/sh

# Copyright (C) 2016-2021 Franco Fichtner <franco@opnsense.org>
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
PIPEFILE="/tmp/pkg_upgrade.pipe"
TEE="/usr/bin/tee -a"

: > ${LOCKFILE}
rm -f ${PIPEFILE}
mkfifo ${PIPEFILE}

echo "***GOT REQUEST TO AUDIT SECURITY***" >> ${LOCKFILE}
${TEE} ${LOCKFILE} < ${PIPEFILE} &
echo "Currently running $(opnsense-version) at $(date)" > ${PIPEFILE}
${TEE} ${LOCKFILE} < ${PIPEFILE} &
pkg audit -F 2>&1 > ${PIPEFILE}
sleep 1 # give the system time to flush the buffer to console
echo '***DONE***' >> ${LOCKFILE}
