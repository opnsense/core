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

MTREE="mtree -e -p /"
PKG_PROGRESS_FILE=/tmp/pkg_upgrade.progress
TMPFILE=/tmp/pkg_check.exclude
UPSTREAM="OPNsense"

# Truncate upgrade progress file
: > ${PKG_PROGRESS_FILE}

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

	echo ">>> Check installed ${SET} version" >> ${PKG_PROGRESS_FILE}

	if [ -z "${VER}" -o -z "${VERSION}" ]; then
		echo "Failed to determine version info." >> ${PKG_PROGRESS_FILE}
	elif [ "${VER}" != "${VERSION}" ]; then
		echo "Version ${VER} is incorrect, expected: ${VERSION}" >> ${PKG_PROGRESS_FILE}
	else
		echo "Version ${VER} is correct." >> ${PKG_PROGRESS_FILE}
	fi

	FILE=/usr/local/opnsense/version/${SET}.mtree

	if [ ! -f ${FILE} ]; then
		echo "Cannot verify ${SET}: missing ${FILE}" >> ${PKG_PROGRESS_FILE}
		return
	fi

	if [ ! -f ${FILE}.sig ]; then
		echo "Cannot verify ${SET}: missing ${FILE}.sig" >> ${PKG_PROGRESS_FILE}
	elif ! opnsense-verify -q ${FILE}; then
		echo "Cannot verify ${SET}: invalid ${FILE}.sig" >> ${PKG_PROGRESS_FILE}
	fi

	echo ">>> Check for missing or altered ${SET} files" >> ${PKG_PROGRESS_FILE}

	echo "${MTREE_PATTERNS}" > ${TMPFILE}

	MTREE_OUT=$(${MTREE} -X ${TMPFILE} < ${FILE} 2>&1)
	MTREE_RET=${?}

	MTREE_OUT=$(echo "${MTREE_OUT}" | grep -Fvx "${GREP_PATTERNS}")
	MTREE_MIA=$(echo "${MTREE_OUT}" | grep -c ' missing$')

	if [ ${MTREE_RET} -eq 0 ]; then
		if [ "${MTREE_MIA}" = "0" ]; then
			echo "No problems detected." >> ${PKG_PROGRESS_FILE}
		else
			echo "Missing files: ${MTREE_MIA}" >> ${PKG_PROGRESS_FILE}
			echo "${MTREE_OUT}" >> ${PKG_PROGRESS_FILE}
		fi
	else
		echo "Error ${MTREE_RET} ocurred." >> ${PKG_PROGRESS_FILE}
		echo "${MTREE_OUT}" >> ${PKG_PROGRESS_FILE}
	fi

	rm ${TMPFILE}
}

core_check()
{
	echo ">>> Check for core packages consistency" >> ${PKG_PROGRESS_FILE}

	CORE=$(opnsense-version -n)
	PROGRESS=

	if [ -z "$(pkg query %n ${CORE})" ]; then
		echo "Core package \"${CORE}\" not known to package database." >> ${PKG_PROGRESS_FILE}
		return
	fi

	echo "Core package \"${CORE}\" has $(pkg query %#d ${CORE}) dependencies to check." >> ${PKG_PROGRESS_FILE}

	for DEP in $( (echo ${CORE}; pkg query %dn ${CORE}) | sort -u); do
		if [ -z "${PROGRESS}" ]; then
			echo -n "Checking packages: ." >> ${PKG_PROGRESS_FILE}
			PROGRESS=1
		else
			echo -n "." >> ${PKG_PROGRESS_FILE}
		fi

		read REPO LVER AUTO VITA << EOF
$(pkg query "%R %v %a %V" ${DEP})
EOF

		if [ "${REPO}" != ${UPSTREAM} ]; then
			if [ -n "${PROGRESS}" ]; then
				echo >> ${PKG_PROGRESS_FILE}
			fi
			echo "${DEP}-${LVER} repository mismatch: ${REPO}" >> ${PKG_PROGRESS_FILE}
			PROGRESS=
		fi

		RVER=$(pkg rquery -r ${UPSTREAM} %v ${DEP})
		if [ -z "${RVER}" ]; then
			if [ -n "${PROGRESS}" ]; then
				echo >> ${PKG_PROGRESS_FILE}
			fi
			echo "${DEP}-${LVER} has no upstream equivalent" >> ${PKG_PROGRESS_FILE}
			PROGRESS=
		elif [ "${RVER}" != "${LVER}" ]; then
			if [ -n "${PROGRESS}" ]; then
				echo >> ${PKG_PROGRESS_FILE}
			fi
			echo "${DEP}-${LVER} version mismatch, expected ${RVER}" >> ${PKG_PROGRESS_FILE}
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
				echo >> ${PKG_PROGRESS_FILE}
			fi
			echo "${DEP}-${LVER} is ${AUTOSET} to automatic" >> ${PKG_PROGRESS_FILE}
			PROGRESS=
		fi

		if [ "${VITA}" != ${VITAEXPECT} ]; then
			if [ -n "${PROGRESS}" ]; then
				echo >> ${PKG_PROGRESS_FILE}
			fi
			echo "${DEP}-${LVER} is ${VITASET} to vital" >> ${PKG_PROGRESS_FILE}
			PROGRESS=
		fi
	done

	if [ -n "${PROGRESS}" ]; then
		echo " done" >> ${PKG_PROGRESS_FILE}
	fi
}

echo "***GOT REQUEST TO AUDIT HEALTH***" >> ${PKG_PROGRESS_FILE}

echo "Currently running $(opnsense-version) at $(date)" >> ${PKG_PROGRESS_FILE}

set_check kernel
set_check base

echo ">>> Check for missing package dependencies" >> ${PKG_PROGRESS_FILE}
pkg check -dan >> ${PKG_PROGRESS_FILE} 2>&1

echo ">>> Check for missing or altered package files" >> ${PKG_PROGRESS_FILE}
pkg check -sa >> ${PKG_PROGRESS_FILE} 2>&1

core_check

echo '***DONE***' >> ${PKG_PROGRESS_FILE}
