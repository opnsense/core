#!/bin/sh

# Copyright (C) 2017-2024 Franco Fichtner <franco@opnsense.org>
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

REQUEST="AUDIT HEALTH"

. /usr/local/opnsense/scripts/firmware/config.sh

TMPFILE=/tmp/pkg_check.exclude
MTREE="mtree -e -p /"
CMD=${1}

MTREE_PATTERNS="
./.cshrc
./.profile
./etc/cron.d/at
./etc/csh.cshrc
./etc/group
./etc/hosts
./etc/master.passwd
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
./etc/ssl/openssl.cnf
./etc/ttys
./root/.cshrc
./root/.profile
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

	output_txt ">>> Check installed ${SET} version"

	if [ -z "${VER}" -o -z "${VERSION}" ]; then
		output_txt "Failed to determine version info."
	elif [ "${VER}" != "${VERSION}" ]; then
		output_txt "Version ${VER} is incorrect, expected: ${VERSION}"
	else
		output_txt "Version ${VER} is correct."
	fi

	FILE=/usr/local/opnsense/version/${SET}.mtree

	if [ ! -f ${FILE} ]; then
		output_txt "Cannot verify ${SET}: missing ${FILE}"
		return
	fi

	if [ ! -f ${FILE}.sig ]; then
		output_txt "Unverified consistency check for ${SET}: missing ${FILE}.sig"
	elif ! opnsense-verify -q ${FILE}; then
		output_txt "Unverified consistency check for ${SET}: invalid ${FILE}.sig"
	fi

	output_txt ">>> Check for missing or altered ${SET} files"

	echo "${MTREE_PATTERNS}" > ${TMPFILE}

	MTREE_OUT=$(${MTREE} -X ${TMPFILE} < ${FILE} 2>&1)
	MTREE_RET=${?}

	MTREE_OUT=$(echo "${MTREE_OUT}" | grep -Fvx "${GREP_PATTERNS}")
	MTREE_MIA=$(echo "${MTREE_OUT}" | grep -c ' missing$')

	if [ ${MTREE_RET} -eq 0 ]; then
		if [ "${MTREE_MIA}" = "0" ]; then
			output_txt "No problems detected."
		else
			output_txt "Missing files: ${MTREE_MIA}"
			output_txt "${MTREE_OUT}"
		fi
	else
		output_txt "Error ${MTREE_RET} occurred."
		output_txt "${MTREE_OUT}"
	fi

	rm ${TMPFILE}
}

core_check()
{
	output_txt ">>> Check for core packages consistency"

	CORE=$(opnsense-version -n)
	PROGRESS=

	if [ -z "${CORE}" ]; then
		output_txt "Could not determine core package name."
		return
	fi

	if [ -z "$(${PKG} query %n ${CORE})" ]; then
		output_txt "Core package \"${CORE}\" not known to package database."
		return
	fi

	output_txt "Core package \"${CORE}\" at $(opnsense-version -v) has $(${PKG} query %#d ${CORE}) dependencies to check."

	for DEP in $( (echo ${CORE}; ${PKG} query %dn ${CORE}) | sort -u); do
		if [ -z "${PROGRESS}" ]; then
			output_txt -n "Checking packages: ."
			PROGRESS=1
		else
			output_txt -n "."
		fi

		read REPO LVER AUTO VITA << EOF
$(${PKG} query "%R %v %a %V" ${DEP})
EOF

		if [ -z "${REPO}${LVER}${AUTO}${VITA}" ]; then
			if [ -n "${PROGRESS}" ]; then
				output_txt
			fi
			output_txt "Package not installed: ${DEP}"
			PROGRESS=
			continue
		fi

		if [ "${REPO}" != ${PRODUCT} ]; then
			if [ -n "${PROGRESS}" ]; then
				output_txt
			fi
			output_txt "${DEP}-${LVER} repository mismatch: ${REPO}"
			PROGRESS=
		fi

		RVER=$(${PKG} rquery -r ${PRODUCT} %v ${DEP} 2> /dev/null)
		if [ -z "${RVER}" ]; then
			if [ -n "${PROGRESS}" ]; then
				output_txt
			fi
			output_txt "${DEP}-${LVER} has no upstream equivalent"
			PROGRESS=
		elif [ "${RVER}" != "${LVER}" ]; then
			if [ -n "${PROGRESS}" ]; then
				output_txt
			fi
			output_txt "${DEP}-${LVER} version mismatch, expected ${RVER}"
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
				output_txt
			fi
			output_txt "${DEP}-${LVER} is ${AUTOSET} to automatic"
			PROGRESS=
		fi

		if [ "${VITA}" != ${VITAEXPECT} ]; then
			if [ -n "${PROGRESS}" ]; then
				output_txt
			fi
			output_txt "${DEP}-${LVER} is ${VITASET} to vital"
			PROGRESS=
		fi
	done

	if [ -n "${PROGRESS}" ]; then
		output_txt " done"
	fi
}

output_txt ">>> Root file system: $(mount | awk '$3 == "/" { print $1 }')"

if [ -z "${CMD}" -o "${CMD}" = "kernel" ]; then
	set_check kernel
fi

if [ -z "${CMD}" -o "${CMD}" = "base" ]; then
	set_check base
fi

if [ -z "${CMD}" -o "${CMD}" = "repos" ]; then
	output_txt ">>> Check installed repositories"
	output_cmd opnsense-verify -l
fi

if [ -z "${CMD}" -o "${CMD}" = "plugins" ]; then
	output_txt ">>> Check installed plugins"
	PLUGINS=$(${PKG} query -g '%n %v' 'os-*' 2>&1)
	if [ -n "${PLUGINS}" ]; then
		output_txt "${PLUGINS}"
	else
		output_txt "No plugins found."
	fi
fi

if [ -z "${CMD}" -o "${CMD}" = "locked" ]; then
	output_txt ">>> Check locked packages"
	LOCKED=$(${PKG} lock -lq 2>&1)
	if [ -n "${LOCKED}" ]; then
		output_txt "${LOCKED}"
	else
		output_txt "No locks found."
	fi
fi

if [ -z "${CMD}" -o "${CMD}" = "packages" ]; then
	output_txt ">>> Check for missing package dependencies"
	output_cmd ${PKG} check -dan

	output_txt ">>> Check for missing or altered package files"
	output_cmd ${PKG} check -sa
fi

if [ -z "${CMD}" -o "${CMD}" = "core" ]; then
	core_check
fi

output_done
