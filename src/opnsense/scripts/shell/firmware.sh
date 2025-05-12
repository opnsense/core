#!/bin/sh

# Copyright (c) 2015-2025 Franco Fichtner <franco@opnsense.org>
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

# From this shell script never execute any remote work prior to user
# consent.  The first action is the unconditional changelog fetch after
# script invoke.  After that we opportunistically run the selected major
# "upgrade"/minor "update" request as it appears to be available.
#
# Except for the reboot check, we never inspect the incoming integrity
# of the update: in case there is none available the respective function
# will tell us itself.  With this we shield the firmware shell run from
# the complexity of GUI/API updates so that bugs are most likely avoided.

LAUNCHER="/usr/local/opnsense/scripts/firmware/launcher.sh -S"
RELEASE=$(opnsense-update -vR)
PROMPT="y/N"
CHANGELOG=
ARGS=

run_action()
{
	echo
	if ! ${LAUNCHER} ${1}; then
		echo "A firmware action is currently in progress."
	fi
	echo
	read -p "Press any key to return to menu." WAIT
}

echo -n "Fetching change log information, please wait... "
if ${LAUNCHER} -u changelog fetch; then
	echo "done"
fi

echo
echo "This will automatically fetch all available updates and apply them."
echo

if [ -n "${RELEASE}" ]; then
	echo "A major firmware upgrade is available for this installation: ${RELEASE}"
	echo
	echo "Make sure you have read the release notes and migration guide before"
	echo "attempting this upgrade.  Approx. 1000MB will need to be downloaded and"
	echo "require 2000MB of free space to unpack.  Continue with this major upgrade"
	echo "by typing the major upgrade version number displayed above."
	echo
	echo "Minor updates may be available, answer 'y' to run them instead."
	echo

	PROMPT="${RELEASE}/${PROMPT}"
elif CHANGELOG=$(${LAUNCHER} -u reboot); then
	echo "This update requires a reboot."
	echo
fi

read -p "Proceed with this action? [${PROMPT}]: " YN

case ${YN} in
[yY])
	;;
${RELEASE:-y})
	ARGS="upgrade ${RELEASE}"
	CHANGELOG=${RELEASE}
	;;
[sS])
	run_action security
	exit 0
	;;
[hH])
	run_action health
	exit 0
	;;
[cC])
	run_action connection
	exit 0
	;;
[fF])
	run_action cleanup
	exit 0
	;;
*)
	exit 0
	;;
esac

echo

if [ -n "${CHANGELOG}" ]; then
	CHANGELOG=$(configctl firmware changelog text ${CHANGELOG})
fi
if [ -n "${CHANGELOG}" ]; then
	echo "${CHANGELOG}" | less
	echo
fi

/usr/local/etc/rc.firmware ${ARGS}
