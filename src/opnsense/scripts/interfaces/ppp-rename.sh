#!/bin/sh

# Copyright (c) 2023 Franco Fichtner <franco@opnsense.org>
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

# look for <unnamed> netgraph node to rename since mpd5 does not do it

if [ -z "${1}" -o -z "${2}" -o -z "${3}" ]; then
	return
fi

INTERFACE=${1}_link
NG_DEVICE=$(echo ${2} | sed 's/[.:]/_/g')
NG_TYPE=${3}

for TRY in 1 1 1 1 1 1 1 1 1 1; do
	ngctl list | while read _NAME NAME _TYPE TYPE _ID ID MORE; do
		if [ "${NAME}" != "<unnamed>" -o "${TYPE}" != "${NG_TYPE}" ]; then
			continue
		fi
		if [ "$(ngctl info "[${ID}]:" | grep -c ${INTERFACE})" = "0" ]; then
			continue
		fi
		ngctl name "[${ID}]:" "${NG_DEVICE}"
		return
	done

	sleep ${TRY}
done
