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

DO_CONTENTS=
DO_DELETE=
DO_MODE=
DO_VERBOSE=

AF=
MD=
EX=
IF=

# default to IPv4 with nameserver mode
set -- -4 -n ${@}

while getopts 46a:di:nprsV OPT; do
	case ${OPT} in
	4)
		AF=inet
		EX=
		;;
	6)
		AF=inet6
		EX=v6
		;;
	a)
		DO_CONTENTS="${DO_CONTENTS} ${OPTARG}"
		;;
	d)
		DO_DELETE="-d"
		;;
	i)
		IF=${OPTARG}
		;;
	n)
		DO_MODE="-n"
		MD="nameserver"
		;;
	p)
		DO_MODE="-p"
		MD="prefix"
		;;
	r)
		DO_MODE="-r"
		MD="router"
		;;
	s)
		DO_MODE="-s"
		MD="searchdomain"
		;;
	V)
		DO_VERBOSE="-V"
		;;
	*)
		echo "Usage: man ${0##*/}" >&2
		exit 1
		;;
	esac
done

if [ -n "${DO_VERBOSE}" ]; then
	set -x
fi

FILE="/tmp/${IF:-*}_${MD}${EX}"

if [ -z "${IF}" ]; then
	# list all interfaces that have the requested file
	for FOUND in $(find -s /tmp -name "${FILE#/tmp/}"); do
		FOUND=${FOUND#/tmp/}
		echo ${FOUND%%_*}
	done

	# wait for further user input using "-i"
	exit 0
fi

if [ -n "${DO_DELETE}" ]; then
        if [ "${DO_MODE}" = "-n" -a -f ${FILE} ]; then
		for CONTENT in $(cat ${FILE}); do
			# flush routes here to make sure they are recycled properly
			route delete -${AF} "${CONTENT}"
		done
	fi
	rm -f ${FILE}
fi

for CONTENT in ${DO_CONTENTS}; do
	echo "${CONTENT}" >> ${FILE}
done

if [ -z "${DO_CONTENTS}${DO_DELETE}" -a -f ${FILE} ]; then
	cat ${FILE}
fi
