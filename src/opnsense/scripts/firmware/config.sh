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
LICENSEDIR="/usr/local/share/licenses"
PIPEFILE="/tmp/pkg_upgrade.pipe"
FLOCK="/usr/local/bin/flock"
SELF=$(basename ${0%.sh})
PKG="/usr/local/sbin/pkg"
TEE="/usr/bin/tee -a"
PRODUCT="OPNsense"

# accepted commands for launcher.sh
COMMANDS="
bogons
changelog
check
connection
details
health
install
lock
query
reboot
reinstall
remove
resync
security
sync
unlock
update
upgrade
"

output_request()
{
	: > ${LOCKFILE}

	rm -f ${PIPEFILE}
	mkfifo ${PIPEFILE}

	echo ""***GOT REQUEST TO ${1}***"" >> ${LOCKFILE}
	echo "Currently running $(opnsense-version) at $(date)" >> ${LOCKFILE}
}

output_txt()
{
	DO_OPT=
	DO_OUT=

	while getopts no: OPT; do
		case ${OPT} in
		n)
			DO_OPT="-n"
			;;
		o)
			DO_OUT=${OPTARG}
			;;
		*)
			# ignore unknown
			;;
		esac
	done

	shift $((OPTIND - 1))

	echo ${DO_OPT} "${1}" | ${TEE} ${LOCKFILE} ${DO_OUT}
}

output_cmd()
{
	DO_CMD=
	DO_OUT=

	while getopts o: OPT; do
		case ${OPT} in
		o)
			DO_OUT=${OPTARG}
			;;
		*)
			# ignore unknown
			;;
		esac
	done

	shift $((OPTIND - 1))

	for ARG in "${@}"; do
		# transform first to trap replacements
		ARG="$(echo "${ARG}")"

		# single quote will not execute for safety
		if [ -z "${ARG##*"'"*}" ]; then
			output_txt "firmware: safety violation in argument during ${REQUEST}"
			return 1
		fi

		# append safely to argument in single quotes
		DO_CMD="${DO_CMD} '${ARG}'"
	done

	# pipe needed for grabbing the command return value
	${TEE} ${LOCKFILE} ${DO_OUT} < ${PIPEFILE} &

	# also capture stderr in this case
	eval "(${DO_CMD}) 2>&1" > ${PIPEFILE}
}

output_done()
{
	echo '***DONE***' >> ${LOCKFILE}
	exit 0
}

output_reboot()
{
	echo '***REBOOT***' >> ${LOCKFILE}
	sleep 5
	/usr/local/etc/rc.reboot
}

# if output is requested clear file and set new request right away
if [ -n "${REQUEST}" ]; then
	output_request "${REQUEST}"
fi

# initialize environment to operate in
env_init()
{
	if [ -n "$(opnsense-update -x)" -o -e /var/run/development ]; then
		if [ -n "${REQUEST}" ]; then
			output_txt "Strict TLS 1.3 and CRL checking is enabled."
		fi

		# business mirror compliance requires
		# disabling the use of TLS below 1.3
		export SSL_NO_TLS1="yes"
		export SSL_NO_TLS1_1="yes"
		export SSL_NO_TLS1_2="yes"

		# refresh CRL files for libfetch consumption...
		HOSTS=$(/usr/local/opnsense/scripts/firmware/hostnames.sh)
		if /usr/local/opnsense/scripts/system/update-crl-fetch.py ${HOSTS}; then
			/usr/local/opnsense/scripts/system/certctl.py rehash
		fi

		# ...and then tell libfetch to verify from trust store
		export SSL_CA_CERT_PATH="/etc/ssl/certs"
		export SSL_CRL_OPTIONAL="yes"
		export SSL_CRL_VERIFY="yes"
	fi
}

for COMMAND in ${COMMANDS}; do
	if [ "${SELF}" = ${COMMAND} ]; then
		env_init
		break;
	fi
done
