#!/bin/sh

# Copyright (c) 2016-2023 Franco Fichtner <franco@opnsense.org>
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

. /usr/local/opnsense/scripts/firmware/config.sh

set -e

DESTDIR="/usr/local/opnsense/changelog"
FETCH="fetch -qT 30"

changelog_remove()
{
	mkdir -p ${DESTDIR}

	for FILE in $(find ${DESTDIR} -depth 1 \! -name 'changelog.txz*'); do
		rm -rf ${FILE}
	done

	echo '[]' > ${DESTDIR}/index.json
}

changelog_checksum()
{
	echo $(sha256 -q "${1}" 2> /dev/null || true)
}

changelog_url()
{
	echo "$(opnsense-update -X)/sets/changelog.txz"
}

changelog_fetch()
{
	mkdir -p ${DESTDIR}

	URL=$(changelog_url)

	${FETCH} -mo ${DESTDIR}/changelog.txz "${URL}"
	${FETCH} -o ${DESTDIR}/changelog.txz.sig "${URL}.sig"

	opnsense-verify -q ${DESTDIR}/changelog.txz

	changelog_remove

	tar -C ${DESTDIR} -xJf ${DESTDIR}/changelog.txz
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
elif [ "${COMMAND}" = "cron" ]; then
	# pause for at least 10 minutes spread out over the next 12 hours
	sleep $(jot -r 1 600 43800)
	changelog_fetch
elif [ "${COMMAND}" = "remove" ]; then
	changelog_remove
elif [ "${COMMAND}" = "list" ]; then
	changelog_show index.json
elif [ "${COMMAND}" = "url" ]; then
	changelog_url
elif [ "${COMMAND}" = "html" -a -n "${VERSION}" ]; then
	changelog_show "$(basename ${VERSION}).htm"
elif [ "${COMMAND}" = "text" -a -n "${VERSION}" ]; then
	changelog_show "$(basename ${VERSION}).txt"
elif [ "${COMMAND}" = "date" -a -n "${VERSION}" ]; then
	# added here for completeness, but called directly in backend action
	/usr/local/opnsense/scripts/firmware/changelog-date.php "$(basename ${VERSION})"
fi
