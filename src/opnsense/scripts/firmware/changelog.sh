#!/bin/sh

# Copyright (c) 2016-2017 Franco Fichtner <franco@opnsense.org>
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions
# are met:
#
# 1. Redistributions of source code must retain the above copyright
#    notice, this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright
#    notice, this list of conditions and the following disclaimer in the
#    documentation and/or other materials provided with the distribution.
#
# THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND
# ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
# IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
# ARE DISCLAIMED.  IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE
# FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
# DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
# OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
# HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
# LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
# OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
# SUCH DAMAGE.

set -e

DESTDIR="/usr/local/opnsense/changelog"
WORKDIR="/tmp/changelog"
FETCH="fetch -qT 5"

changelog_remove()
{
	mkdir -p ${DESTDIR}
	touch ${DESTDIR}/index.json
	rm -rf ${DESTDIR}/*
}

changelog_fetch()
{
	CORE_ABI=$(opnsense-version -a)
	SYS_ABI=$(opnsense-verify -a)

	URL="https://pkg.opnsense.org/${SYS_ABI}/${CORE_ABI}/sets/changelog.txz"

	rm -rf ${WORKDIR}
	mkdir -p ${WORKDIR}

	${FETCH} -o ${WORKDIR}/changelog.txz.sig "${URL}.sig"
	${FETCH} -o ${WORKDIR}/changelog.txz "${URL}"
	opnsense-verify -q ${WORKDIR}/changelog.txz

	changelog_remove

	tar -C ${DESTDIR} -xJf ${WORKDIR}/changelog.txz
}

changelog_show()
{
	FILE="${DESTDIR}/${1}"

	if [ -f "${FILE}" ]; then
		cat "${FILE}"
	fi
}

COMMAND=${1}
VERSION=${2}

if [ "${COMMAND}" = "fetch" ]; then
	changelog_fetch
elif [ "${COMMAND}" = "remove" ]; then
	changelog_remove
elif [ "${COMMAND}" = "list" ]; then
	changelog_show index.json
elif [ "${COMMAND}" = "html" -a -n "${VERSION}" ]; then
	changelog_show "$(basename ${VERSION}).htm"
elif [ "${COMMAND}" = "text" -a -n "${VERSION}" ]; then
	changelog_show "$(basename ${VERSION}).txt"
fi
