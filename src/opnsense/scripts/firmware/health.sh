#!/bin/sh

# Copyright (C) 2017-2021 Franco Fichtner <franco@opnsense.org>
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
MTREE="mtree -e -p /"
PIPEFILE="/tmp/pkg_upgrade.pipe"
TEE="/usr/bin/tee -a"
TMPFILE=/tmp/pkg_check.exclude
UPSTREAM="OPNsense"

: > ${LOCKFILE}
rm -f ${PIPEFILE}
mkfifo ${PIPEFILE}

MTREE_PATTERNS="
./etc/group
./etc/hosts
./etc/master.passwd
./etc/motd
./etc/newsyslog.conf
./etc/pam.d/sshd
./etc/pam.d/system
./etc/passwd
./etc/pwd.db
./etc/rc
./etc/rc.shutdown
./etc/remote
./etc/shells
./etc/spwd.db
./etc/ttys
./usr/share/man/mandoc.db
./usr/share/openssl/man/mandoc.db
"

GREP_PATTERNS=

for PATTERN in ${MTREE_PATTERNS}; do
	GREP_PATTERNS="$(echo "${GREP_PATTERNS}${PATTERN} missing")
"
done

VERSION=$(opnsense-update -v)

set_check()
{
	SET=${1}

	VER=$(opnsense-version -v ${SET})

	${TEE} ${LOCKFILE} < ${PIPEFILE} &
	echo ">>> Check installed ${SET} version" > ${PIPEFILE}

	if [ -z "${VER}" -o -z "${VERSION}" ]; then
		${TEE} ${LOCKFILE} < ${PIPEFILE} &
		echo "Failed to determine version info." > ${PIPEFILE}
	elif [ "${VER}" != "${VERSION}" ]; then
		${TEE} ${LOCKFILE} < ${PIPEFILE} &
		echo "Version ${VER} is incorrect, expected: ${VERSION}" > ${PIPEFILE}
	else
		${TEE} ${LOCKFILE} < ${PIPEFILE} &
		echo "Version ${VER} is correct." > ${PIPEFILE}
	fi

	FILE=/usr/local/opnsense/version/${SET}.mtree

	if [ ! -f ${FILE} ]; then
		${TEE} ${LOCKFILE} < ${PIPEFILE} &
		echo "Cannot verify ${SET}: missing ${FILE}" > ${PIPEFILE}
		return
	fi

	if [ ! -f ${FILE}.sig ]; then
		${TEE} ${LOCKFILE} < ${PIPEFILE} &
		echo "Cannot verify ${SET}: missing ${FILE}.sig" > ${PIPEFILE}
	elif ! opnsense-verify -q ${FILE}; then
		${TEE} ${LOCKFILE} < ${PIPEFILE} &
		echo "Cannot verify ${SET}: invalid ${FILE}.sig" > ${PIPEFILE}
	fi

	${TEE} ${LOCKFILE} < ${PIPEFILE} &
	echo ">>> Check for missing or altered ${SET} files" > ${PIPEFILE}

	echo "${MTREE_PATTERNS}" > ${TMPFILE}

	MTREE_OUT=$(${MTREE} -X ${TMPFILE} < ${FILE} 2>&1)
	MTREE_RET=${?}

	MTREE_OUT=$(echo "${MTREE_OUT}" | grep -Fvx "${GREP_PATTERNS}")
	MTREE_MIA=$(echo "${MTREE_OUT}" | grep -c ' missing$')

	if [ ${MTREE_RET} -eq 0 ]; then
		if [ "${MTREE_MIA}" = "0" ]; then
			${TEE} ${LOCKFILE} < ${PIPEFILE} &
			echo "No problems detected." > ${PIPEFILE}
		else
			${TEE} ${LOCKFILE} < ${PIPEFILE} &
			echo "Missing files: ${MTREE_MIA}" > ${PIPEFILE}
			${TEE} ${LOCKFILE} < ${PIPEFILE} &
			echo "${MTREE_OUT}" > ${PIPEFILE}
		fi
	else
		${TEE} ${LOCKFILE} < ${PIPEFILE} &
		echo "Error ${MTREE_RET} ocurred." > ${PIPEFILE}
		${TEE} ${LOCKFILE} < ${PIPEFILE} &
		echo "${MTREE_OUT}" > ${PIPEFILE}
	fi

	rm ${TMPFILE}
}

core_check()
{
	${TEE} ${LOCKFILE} < ${PIPEFILE} &
	echo ">>> Check for core packages consistency" > ${PIPEFILE}

	CORE=$(opnsense-version -n)
	PROGRESS=

	if [ -z "$(pkg query %n ${CORE})" ]; then
		${TEE} ${LOCKFILE} < ${PIPEFILE} &
		echo "Core package \"${CORE}\" not known to package database." > ${PIPEFILE}
		return
	fi

	${TEE} ${LOCKFILE} < ${PIPEFILE} &
	echo "Core package \"${CORE}\" has $(pkg query %#d ${CORE}) dependencies to check." > ${PIPEFILE}

	for DEP in $( (echo ${CORE}; pkg query %dn ${CORE}) | sort -u); do
		${TEE} ${LOCKFILE} < ${PIPEFILE} &
		if [ -z "${PROGRESS}" ]; then
			echo -n "Checking packages: ." > ${PIPEFILE}
			PROGRESS=1
		else
			echo -n "." > ${PIPEFILE}
		fi

		read REPO LVER AUTO VITA << EOF
$(pkg query "%R %v %a %V" ${DEP})
EOF

		if [ "${REPO}" != ${UPSTREAM} ]; then
			if [ -n "${PROGRESS}" ]; then
				${TEE} ${LOCKFILE} < ${PIPEFILE} &
				echo > ${PIPEFILE}
			fi
			${TEE} ${LOCKFILE} < ${PIPEFILE} &
			echo "${DEP}-${LVER} repository mismatch: ${REPO}" > ${PIPEFILE}
			PROGRESS=
		fi

		RVER=$(pkg rquery -r ${UPSTREAM} %v ${DEP})
		if [ -z "${RVER}" ]; then
			if [ -n "${PROGRESS}" ]; then
				${TEE} ${LOCKFILE} < ${PIPEFILE} &
				echo > ${PIPEFILE}
			fi
			${TEE} ${LOCKFILE} < ${PIPEFILE} &
			echo "${DEP}-${LVER} has no upstream equivalent" > ${PIPEFILE}
			PROGRESS=
		elif [ "${RVER}" != "${LVER}" ]; then
			if [ -n "${PROGRESS}" ]; then
				${TEE} ${LOCKFILE} < ${PIPEFILE} &
				echo > ${PIPEFILE}
			fi
			${TEE} ${LOCKFILE} < ${PIPEFILE} &
			echo "${DEP}-${LVER} version mismatch, expected ${RVER}" > ${PIPEFILE}
			PROGRESS=
		fi

		AUTOEXPECT=1
		AUTOSET="not set"
		VITAEXPECT=0
		VITASET="set"

		if [ ${DEP} = ${CORE} ]; then
			AUTOEXPECT=0
			AUTOSET="set"
			VITAEXPECT=1
			VITASET="not set"
		elif [ ${DEP} = "pkg" ]; then
			AUTOEXPECT=0
			AUTOSET="set"
		fi

		if [ "${AUTO}" != ${AUTOEXPECT} ]; then
			if [ -n "${PROGRESS}" ]; then
				${TEE} ${LOCKFILE} < ${PIPEFILE} &
				echo > ${PIPEFILE}
			fi
			${TEE} ${LOCKFILE} < ${PIPEFILE} &
			echo "${DEP}-${LVER} is ${AUTOSET} to automatic" > ${PIPEFILE}
			PROGRESS=
		fi

		if [ "${VITA}" != ${VITAEXPECT} ]; then
			if [ -n "${PROGRESS}" ]; then
				${TEE} ${LOCKFILE} < ${PIPEFILE} &
				echo > ${PIPEFILE}
			fi
			${TEE} ${LOCKFILE} < ${PIPEFILE} &
			echo "${DEP}-${LVER} is ${VITASET} to vital" > ${PIPEFILE}
			PROGRESS=
		fi
	done

	if [ -n "${PROGRESS}" ]; then
		${TEE} ${LOCKFILE} < ${PIPEFILE} &
		echo " done" > ${PIPEFILE}
	fi
}

echo "***GOT REQUEST TO AUDIT HEALTH***" >> ${LOCKFILE}

${TEE} ${LOCKFILE} < ${PIPEFILE} &
echo "Currently running $(opnsense-version) at $(date)" > ${PIPEFILE}

set_check kernel
set_check base

${TEE} ${LOCKFILE} < ${PIPEFILE} &
echo ">>> Check for missing package dependencies" > ${PIPEFILE}
${TEE} ${LOCKFILE} < ${PIPEFILE} &
pkg check -dan > ${PIPEFILE} 2>&1

${TEE} ${LOCKFILE} < ${PIPEFILE} &
echo ">>> Check for missing or altered package files" > ${PIPEFILE}
${TEE} ${LOCKFILE} < ${PIPEFILE} &
pkg check -sa > ${PIPEFILE} 2>&1

core_check

sleep 1 # give the system time to flush the buffer to console

echo '***DONE***' >> ${LOCKFILE}
