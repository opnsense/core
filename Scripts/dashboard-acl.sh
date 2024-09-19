#!/bin/sh

# Copyright (c) 2024 Franco Fichtner <franco@opnsense.org>
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

ACLDIR=src/opnsense/mvc/app/models
WIDGETDIR=src/opnsense/www/js/widgets

ACLS=$(
	if [ -d ${ACLDIR} ]; then find -s ${ACLDIR} -name "ACL.xml"; fi
	if [ -d "${1}" ]; then find -s ${1}/${ACLDIR} -name "ACL.xml"; fi
)
METADATA=$(if [ -d ${WIDGETDIR}/Metadata ]; then find -s ${WIDGETDIR}/Metadata -name "*.xml"; fi)
WIDGETS=$(if [ -d ${WIDGETDIR} ]; then find -s ${WIDGETDIR} -name "*.js"; fi)

for WIDGET in ${WIDGETS}; do
	FILENAME=$(basename ${WIDGET})
	if [ -z "${FILENAME%Base*}" ]; then
		# ignore base classes
		continue
	fi

	ENDPOINTS=$( (grep -o 'this\.ajaxCall([^,)]*' ${WIDGET} | cut -c 15-; \
	    grep -o 'super\.openEventSource([^,)]*' ${WIDGET} | cut -c 23-) | \
	    tr -d "'" | tr -d '`' | sed 's:\$.*:*:' | sort -u)

	if [ -z "${ENDPOINTS}" ]; then
		echo "No endpoints found for ${WIDGET}"
		exit 1
	fi

	REGISTERED=

	for METAFILE in ${METADATA}; do
		if grep -q "<filename>${FILENAME}</filename>" ${METAFILE}; then
			REGISTERED=$(xmllint ${METAFILE} --xpath '//*[filename="'"${FILENAME}"'"]//endpoints//endpoint' | \
			    sed -e 's:^[^>]*>::' -e 's:<[^<]*$::' | sort)
			break
		fi
	done

	if [ -z "${REGISTERED}" ]; then
		echo "Did not find metadata for ${WIDGET}"
		exit 1
	fi

	if [ "${REGISTERED}" != "${ENDPOINTS}" ]; then
		echo "Registered widget endpoints do not match:"
		echo "<<<<<<< ${WIDGET}"
		echo "${ENDPOINTS}"
		echo ========
		echo "${REGISTERED}"
		echo ">>>>>>> ${METAFILE}"
		exit 1
	fi

	for ENDPOINT in ${ENDPOINTS}; do
		# do a full match here
		PATTERN=${ENDPOINT#/}
		if ! grep -Fq "<pattern>${PATTERN}</pattern>" ${ACLS}; then
			if [ -z "${PATTERN%%*/\*}" ]; then
				# already a wildcard match, allow lower path
				PATTERN=${PATTERN%/*}
			fi
			# do a wildcard match as a fallback
			PATTERN=${PATTERN%/*}/*
			if ! grep -Fq "<pattern>${PATTERN}</pattern>" ${ACLS}; then
				echo "Unknown ACL for ${WIDGET}:"
				echo ${ENDPOINT}
				exit 1
			fi
		fi
	done
done
