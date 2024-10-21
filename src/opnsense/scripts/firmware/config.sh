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
	CRL_FILE=/tmp/libfetch_crl.$(date +"%y%m%d%H")

	# clean up old CRL files if found
	for FILE in $(find /tmp/ -name "libfetch_crl.*"); do
		if [ "${FILE}" != "${CRL_FILE}" ]; then
			rm ${FILE}
		fi
	done

	if [ -n "$(opnsense-update -x)" ]; then
		# business mirror compliance requires
		# disabling the use of TLS below 1.3
		export SSL_NO_TLS1="yes"
		export SSL_NO_TLS1_1="yes"
		export SSL_NO_TLS1_2="yes"

		# refresh CRL file for libfetch consumption
		if [ ! -s ${CRL_FILE} ]; then
			# collect firmware-relevant hostnames using TLS
			# in order to prepare a matching CRL bundle
			HOSTS=$(/usr/local/opnsense/scripts/firmware/hostnames.sh)
			CRL_TMP=$(mktemp -q /tmp/libfetch_tmp.XXXXXX)

			if /usr/local/opnsense/scripts/system/update-crl-fetch.py \
			    ${HOSTS} > ${CRL_TMP}; then
				mv ${CRL_TMP} ${CRL_FILE}
			else
				# in case of problems clear the file and leave an
				# empty one for the next run also in order to let
				# libfetch complain about the missing CRLs
				rm ${CRL_TMP}
				: > ${CRL_FILE}
			fi
		fi

		# CRL file is ready for use now
		export SSL_CRL_FILE="${CRL_FILE}"
	else
		# unused so ok to remove
		rm ${CRL_FILE}
	fi
}

for COMMAND in ${COMMANDS}; do
	if [ "${SELF}" = ${COMMAND} ]; then
		env_init
		break;
	fi
done
