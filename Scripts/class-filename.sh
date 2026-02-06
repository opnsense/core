#!/bin/sh

# Copyright (c) 2026 Franco Fichtner <franco@opnsense.org>
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

RET=0

for FILE in $(find src -name "*.php"); do
	CLASS=$(grep ^class ${FILE} | awk '{ print $2 }')
	if [ -z "${CLASS}" ]; then
		continue
	fi
	MULTI=
	OK=
	for _CLASS in ${CLASS}; do
		if [ "$(basename ${FILE})" == "${_CLASS}.php" ]; then
			OK=${_CLASS}
		else
			MULTI="${_CLASS} ${MULTI}"
		fi
	done
	if [ -z "${OK}" ]; then
		echo "${FILE}: error: does not match class name" ${CLASS}
		RET=1
	elif [ -n "${MULTI}" ]; then
		echo "${FILE}: warning: has additional classes" ${MULTI}
	fi
done

exit ${RET}
