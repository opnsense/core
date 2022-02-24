#!/bin/sh

# Copyright (c) 2022 Franco Fichtner <franco@opnsense.org>
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

# -a attach contents
# -d undo contents
# -v print contents

DO="-v"
IP=

case "${1}" in
-a)
	DO="-a"
	shift
	IP=${1}
	shift
	;;
-d)
	DO="-d"
	shift
	;;
-v)
	DO="-v"
	shift
	;;
-*)
	echo "unknwon option '${1}'"
	exit 1
	;;
*)
	;;
esac

IF=${1}
AF=inet
EX=

if [ "${2:-4}" = 6 ]; then
	AF=inet6
	EX=v6
fi

FILE="/tmp/${IF:?}_nameserver${EX}"

if [ ! -f ${FILE} -a ${DO} != "-a" ]; then
	return
fi

case "${DO}" in
-a)
	echo "${IP}" >> ${FILE}
	;;
-d)
	for IP in $(cat ${FILE}); do
		# flush routes here to make sure they are recycled properly
		route delete -"${AF}" "${IP}"
	done
	rm -f ${FILE}
	;;
-v)
	cat ${FILE}
	;;
*)
	;;
esac
