#!/bin/sh

# Copyright (c) 2017-2021 Franco Fichtner <franco@opnsense.org>
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

if [ ! -d /conf/backup ]; then
	echo "No backups available."
	exit 0
fi

BACKUPS="$(cd /conf/backup; find . -name "config-*.xml")"

if [ -z "${BACKUPS}" ]; then
	echo "No backups available."
	exit 0
fi

DATED=

for BACKUP in ${BACKUPS}; do
	DATETIME=${BACKUP#*config-}
	DATETIME=${DATETIME%.xml}
	SUBSEC=${DATETIME#*.}
	DATETIME=${DATETIME%.*}
	# write a line with all required info that is prefixed
	# with a sortable time stamp for our next step...
	DATED="${DATED}$(date -r ${DATETIME} '+%Y-%m-%dT%H:%M:%S').${SUBSEC} ${DATETIME} ${BACKUP}
"
done

SORTED="$(echo -n "${DATED}" | sort -r | head -n 19)"
INDEX=0
RESTORE=

while [ -z "${RESTORE}" ]; do
	echo "${SORTED}" | while read SORT DATETIME BACKUP; do
		if [ ${INDEX} -ne 0 ]; then
			# carefully crafted whitespace pattern with
			# embedded alignment tab, edit carefully
			echo "    ${INDEX}.	$(date -r ${DATETIME})"
		fi
		INDEX=$((INDEX+1))
	done

	echo
	read -p "Select backup to restore or leave blank to exit: " SELECT

	if [ -z "${SELECT}" ]; then
		exit 0
	fi

	RESTORE="$(echo "${SORTED}" | while read SORT DATETIME BACKUP; do
		if [ ${INDEX} -ne 0 -a ${INDEX} = "${SELECT}" ]; then
			echo "${BACKUP}"
			break
		fi
		INDEX=$((INDEX+1))
	done)"

	echo
done

cp /conf/backup/${RESTORE} /conf/config.xml

read -p "Do you want to reboot to apply the backup cleanly? [y/N]: " YN

case ${YN} in
[yY])
	/usr/local/etc/rc.reboot
	;;
*)
	/usr/local/etc/rc.reload_all
	;;
esac
