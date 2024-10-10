#!/bin/sh

# Copyright (C) 2024 Franco Fichtner <franco@opnsense.org>
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

# source of common configuration related subroutines and variables

LOCKFILE=${LOCKFILE:-/tmp/pkg_upgrade.progress}
BASEDIR="/usr/local/opnsense/scripts/firmware"
PIPEFILE="/tmp/pkg_upgrade.pipe"
FLOCK="/usr/local/bin/flock"
SELF=$(basename ${0%.sh})
TEE="/usr/bin/tee -a"
PRODUCT="OPNsense"

# accepted commands for launcher.sh
COMMANDS="
changelog
check
connection
health
install
lock
reinstall
remove
resync
security
sync
unlock
update
upgrade
"

# initialize environment to operate in
env_init()
{
	# XXX move modifications to this spot
}

for COMMAND in ${COMMANDS}; do
	if [ "${SELF}" = ${COMMAND} ]; then
		env_init
		break;
	fi
done
