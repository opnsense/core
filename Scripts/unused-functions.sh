#!/bin/sh

# Copyright (c) 2025 Franco Fichtner <franco@opnsense.org>
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

PLUGINS="configure cron devices firewall interfaces run services syslog xmlrpc_sync"

if [ -n "${1}" ]; then
	cd "${1}"
fi

TESTDIR=src

DIRS=${TESTDIR}
NOTE="(did not check plugins)"

if [ -d ../plugins ]; then
	DIRS="${DIRS} ../plugins"
	NOTE="(cross-checked plugins)"
fi

grep -Eor '^function &?[^( ]+' ${TESTDIR} | tr ':' ' ' | tr -d '&' | while read FILE X FUNC; do
	BASEDIR=$(dirname ${FILE})
	BASEDIR=$(basename ${BASEDIR})
	MODULE=$(basename ${FILE})
	MODULE=${MODULE%.inc}

	# exclude indirect plugin calls not otherwise referenced
	if [ "${BASEDIR}" = "plugins.inc.d" ]; then
		for PLUGIN in ${PLUGINS}; do
			if [ "${FUNC}" = "${MODULE}_${PLUGIN}" ]; then
				continue 2
			fi
		done
	fi

	# exclude xmlrpc magic
	if [ "${BASEDIR}" = "xmlrpc" ]; then
		if [ "${FUNC}" = "xmlrpc_publishable_${MODULE}" ]; then
			continue 2
		fi
	fi

	# exclude obvious and non-obvious ways to call a function
	USED=$(grep -Fr "${FUNC}" ${DIRS} | grep -F \
	    -e "${FUNC}(" \
	    -e "'${FUNC}'" \
	    -e "'${FUNC}:" \
	    -e "${FUNC}.bind" \
	    -e "${FUNC}.call" \
	    -e ".keyup(${FUNC})" \
	    -e "= ${FUNC};" \
	    | wc -l | awk '{ print $1 }')
	if [ ${USED} -le 1 ]; then
		echo "${FUNC}() appears unused" ${NOTE}
	elif [ ${USED} -eq 2 ]; then
		# XXX if a match happens in the same file it's probably already considered ;)
		#echo "${FUNC}() only used once, consider refactor"
		:
	else
		USED=$((USED - 1))
		#echo "${FUNC}() used ${USED} times"
	fi
done
