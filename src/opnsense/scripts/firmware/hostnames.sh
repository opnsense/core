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

# collect HTTPS URLs related to firmware and provide the
# deduplicated host names thereof for further processing

URLS=$(opnsense-update -M; opnsense-update -X)

# Make a few assumptions about plugged package repositories:
#
# * grab the "url" key, delimited with double quotes
# * remove the spurious "pkg+" prefix to treat it as raw HTTP(S)
# * match config name against known enabled repos

REPOS=$(opnsense-verify -l | awk '{ print $1 }')

for CONF in $(find /etc/pkg /usr/local/etc/pkg/repos -name '*.conf' -type f); do
	for REPO in ${REPOS}; do
		if [ "${REPO}.conf" = "$(basename ${CONF})" ]; then
			URL=$(grep 'url:.*"' ${CONF})
			if [ -n "${URL}" ]; then
				URL=${URL#*'"'}
				URL=${URL#pkg+}
				URLS="${URLS}
${URL%%'"'*}"
			fi
			continue 2
		fi
	done
done

for HOST in $( (for URL in ${URLS}; do
	if [ -n "${URL##https://*}" ]; then
		continue
	fi

	HOST=${URL#*://}
	echo ${HOST%%/*}

done) | sort -u); do echo ${HOST}; done
