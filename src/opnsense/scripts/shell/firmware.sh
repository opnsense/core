#!/bin/sh

# Copyright (c) 2015-2021 Franco Fichtner <franco@opnsense.org>
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

UPGRADE="/usr/local/opnsense/firmware-upgrade"
PROMPT="[y/N]"
NAME="y"
ARGS=

echo -n "Fetching change log information, please wait... "
if /usr/local/opnsense/scripts/firmware/changelog.sh fetch; then
	echo "done"
fi

echo
echo "This will automatically fetch all available updates, apply them,"
echo "and reboot if necessary."
echo

if [ -f ${UPGRADE} ]; then
	NAME=$(cat ${UPGRADE})

	echo "A major firmware upgrade is available for this installation: ${NAME}"
	echo
	echo "Make sure you have read the release notes and migration guide before"
	echo "attempting this upgrade.  Around 500MB will need to be downloaded and"
	echo "require 1000MB of free space.  Continue with this major upgrade by"
	echo "typing the major upgrade version number displayed above."
	echo
	echo "Minor updates may be available, answer 'y' to run them instead."
	echo

	PROMPT="[${NAME}/y/N]"
elif /usr/local/opnsense/scripts/firmware/reboot.sh; then
	echo "This update requires a reboot."
	echo
fi

read -p "Proceed with this action? ${PROMPT}: " YN

case ${YN} in
[yY])
	;;
${NAME})
	ARGS="upgrade ${NAME}"
	;;
[sS])
	echo
	/usr/local/opnsense/scripts/firmware/launcher.sh security
	echo
	read -p "Press any key to return to menu." WAIT
	exit 0
	;;
[hH])
	echo
	/usr/local/opnsense/scripts/firmware/launcher.sh health
	echo
	read -p "Press any key to return to menu." WAIT
	exit 0
	;;
*)
	exit 0
	;;
esac

echo

/usr/local/etc/rc.firmware ${ARGS}
