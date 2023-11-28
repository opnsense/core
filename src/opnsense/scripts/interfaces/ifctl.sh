#!/bin/sh

# Copyright (c) 2022-2023 Franco Fichtner <franco@opnsense.org>
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

DO_COMMAND=
DO_CONTENTS=
DO_VERBOSE=

FILES="nameserver prefix router searchdomain"

AF=
MD=
EX=
IF=

flush_routes()
{
	if [ ! -f ${FILE} ]; then
		return
	fi

	case ${MD} in
	prefix)
		# flush null route to delegated prefix
		for CONTENT in $(cat ${FILE}); do
			if [ "${CONTENT##*/}" != "64" ]; then
				route delete -${AF} "${CONTENT}"
			fi
		done
		;;
	*)
		;;
	esac
}

# default to IPv4 with nameserver mode
set -- -4 -n ${@}

while getopts 46a:cdi:lnOprsV OPT; do
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
	c)
		DO_COMMAND="-c"
		MD="${FILES}"
		;;
	d)
		DO_COMMAND="-d"
		;;
	i)
		IF=${OPTARG}
		;;
	l)
		DO_COMMAND="-l"
		;;
	n)
		MD="nameserver"
		;;
	O)
		DO_COMMAND="-O"
		;;
	p)
		MD="prefix"
		;;
	r)
		MD="router"
		;;
	s)
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

if [ "${DO_COMMAND}" = "-c" ]; then
	if [ -z "${IF}" ]; then
		echo "Clearing requires interface option" >&2
		exit 1
	fi

	HAVE_ROUTE=

	# iterate through possible files for cleanup
	for MD in ${FILES}; do
		for IFC in ${IF} ${IF}:slaac; do
			FILE="/tmp/${IFC}_${MD}${EX}"
			if [ ! -f ${FILE} ]; then
				continue
			fi
			if [ "${MD}" = "router" ]; then
				HAVE_ROUTE=1
			fi
			flush_routes
			rm -f ${FILE}
		done
	done

        # XXX legacy behaviour originating from interface_bring_down()
	/usr/sbin/arp -d -i ${IF} -a

	exit 0
elif [ "${DO_COMMAND}" = "-O" ]; then
	if [ -z "${IF}" ]; then
		echo "Dumping requires interface option" >&2
		exit 1
	fi

	# iterate through possible files to print its data (ignore -4/6)
	for EX in '' v6; do
		for MD in ${FILES}; do
			for IFC in ${IF} ${IF}:slaac; do
				FILE="/tmp/${IFC}_${MD}${EX}"
				if [ ! -f ${FILE} ]; then
					continue
				fi
				echo -n "${FILE}:"
				for CONTENT in $(cat ${FILE}); do
				    echo -n " ${CONTENT}"
				done
				echo
			done
		done
	done

	exit 0
elif [ "${DO_COMMAND}" = "-l" ]; then
	if [ -z "${IF}" ]; then
		EX=".*"
		IF="[^:]*"
	fi

	MATCHES=$(find /tmp -regex ".*/${IF}:.*_${MD}${EX}"; find /tmp -regex ".*/${IF}_${MD}${EX}")
	RESULTS=

	for MATCH in ${MATCHES}; do
		FILE=${MATCH##*/}
		IF=${FILE%_*}
		IF=${IF%%:*}
		IF=$(echo ${IF} | sed -e 's/[.:]/__/g')
		MD=${FILE##*_}

		# suffix :slaac matched before plain interface
		# so we can export the resulting file name first
		# and overwrite later
		if [ -z "$(eval echo \${${IF}_${MD}})" ]; then
			RESULTS="${RESULTS} ${IF}_${MD}"
		fi
		eval export ${IF}_${MD}='${MATCH}'
	done

	for RESULT in ${RESULTS}; do
		eval echo \${${RESULT}}
	done

	exit 0
fi

FILE="/tmp/${IF:-*}_${MD}${EX}"

if [ -z "${IF}" ]; then
	RESULTS=

	# list all interfaces that have the requested file
	for FOUND in $(find -s /tmp -name ${FILE#/tmp/}); do
		FOUND=${FOUND#/tmp/}
		FOUND=${FOUND%_*}
		FOUND=${FOUND%:*}
		IF=$(echo ${FOUND} | sed -e 's/[.:]/__/g')
		if [ -z "$(eval echo \${${IF}_found})" ]; then
			RESULTS="${RESULTS} ${IF}_found"
		fi
		eval export ${IF}_found='${FOUND}'
	done

	# only list possible interfaces for user to choose from
	for RESULT in ${RESULTS}; do
		eval echo \${${RESULT}}
	done

	exit 0
fi

if [ "${DO_COMMAND}" = "-d" ]; then
	flush_routes
	rm -f ${FILE}
fi

for CONTENT in ${DO_CONTENTS}; do
	echo "${CONTENT}" >> ${FILE}
	# null route handling for delegated prefix
	if [ ${MD} = "prefix" -a "${CONTENT##*/}" != "64" ]; then
		route add -${AF} -blackhole ${CONTENT} ::1
	fi
done

if [ -n "${DO_COMMAND}${DO_CONTENTS}" ]; then
	exit 0
fi

if [ ! -f ${FILE} ]; then
	# move to :slaac interface suffix if not found
	FILE="/tmp/${IF}:slaac_${MD}${EX}"
fi

# if nothing else could be done display data
if [ -f ${FILE} ]; then
	cat ${FILE}
fi
