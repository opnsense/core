#!/bin/sh

# Copyright (C) 2017-2019 Franco Fichtner <franco@opnsense.org>
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
./etc/shells
./etc/spwd.db
./etc/ttys
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
		echo "Version ${VER} is incorrect: expected ${VERSION}" >> ${PKG_PROGRESS_FILE}
	else
		echo "Version ${VER} is correct." >> ${PKG_PROGRESS_FILE}
	fi

	echo ">>> Check for missing or altered ${SET} files" >> ${PKG_PROGRESS_FILE}

	FILE=/usr/local/opnsense/version/${SET}.mtree

	if [ ! -f ${FILE} ]; then
		echo "Cannot verify ${SET}: missing ${FILE}" >> ${PKG_PROGRESS_FILE}
		return
	fi

	echo "${MTREE_PATTERNS}" > ${TMPFILE}

	MTREE_OUT=$(${MTREE} -X ${TMPFILE} < ${FILE} 2>&1)
	MTREE_RET=${?}

	MTREE_OUT=$(echo "${MTREE_OUT}" | grep -Fvx "${GREP_PATTERNS}")
	MTREE_MIA=$(echo "${MTREE_OUT}" | grep -c ' missing$')

	if [ ${MTREE_RET} -eq 0 ]; then
		if [ "${MTREE_MIA}" = "0" ]; then
			echo "No problems detected." >> ${PKG_PROGRESS_FILE} 2>&1
		else
			echo "Missing files: ${MTREE_MIA}" >> ${PKG_PROGRESS_FILE} 2>&1
			echo "${MTREE_OUT}" >> ${PKG_PROGRESS_FILE} 2>&1
		fi
	else
		echo "Error ${MTREE_RET} ocurred." >> ${PKG_PROGRESS_FILE} 2>&1
		echo "${MTREE_OUT}" >> ${PKG_PROGRESS_FILE} 2>&1
	fi

	rm ${TMPFILE}
}

echo "***GOT REQUEST TO AUDIT HEALTH***" >> ${PKG_PROGRESS_FILE}
set_check kernel
set_check base
echo ">>> Check for and install missing package dependencies" >> ${PKG_PROGRESS_FILE}
pkg check -da >> ${PKG_PROGRESS_FILE} 2>&1
echo ">>> Check for missing or altered package files" >> ${PKG_PROGRESS_FILE}
pkg check -sa >> ${PKG_PROGRESS_FILE} 2>&1
echo '***DONE***' >> ${PKG_PROGRESS_FILE}
