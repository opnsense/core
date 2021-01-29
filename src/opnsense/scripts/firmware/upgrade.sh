#!/bin/sh

# Copyright (C) 2015-2017 Franco Fichtner <franco@opnsense.org>
# Copyright (C) 2014 Deciso B.V.
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

PKG_PROGRESS_FILE=/tmp/pkg_upgrade.progress
PACKAGE=$1
REBOOT=

# Truncate upgrade progress file
: > ${PKG_PROGRESS_FILE}

echo "***GOT REQUEST TO UPGRADE: $PACKAGE***" >> ${PKG_PROGRESS_FILE}

if [ "$PACKAGE" == "all" ]; then
	# update all installed packages
	opnsense-update -p >> ${PKG_PROGRESS_FILE} 2>&1
	# restart the web server
	/usr/local/etc/rc.restart_webgui >> ${PKG_PROGRESS_FILE} 2>&1
	# if we can update base, we'll do that as well
	if opnsense-update -c >> ${PKG_PROGRESS_FILE} 2>&1; then
		if opnsense-update -bk >> ${PKG_PROGRESS_FILE} 2>&1; then
			REBOOT=1
		fi
	fi
elif [ "$PACKAGE" == "maj" ]; then
	# extract info for major upgrade
	UPGRADE="/usr/local/opnsense/firmware-upgrade"
	NAME=unknown
	if [ -f ${UPGRADE} ]; then
		NAME=$(cat ${UPGRADE})
	fi
	# perform first half of major upgrade
	# (download all + kernel install)
	# XXX use opnsense-update -uR at least unless we can imply -R later
	if opnsense-update -ur "${NAME}" >> ${PKG_PROGRESS_FILE} 2>&1; then
		REBOOT=1
	fi
	# second half reboots multiple times,
	# but will snap the GUI back when done
elif [ "$PACKAGE" == "pkg" ]; then
	pkg upgrade -y $PACKAGE >> ${PKG_PROGRESS_FILE} 2>&1
else
	echo "Cannot update $PACKAGE" >> ${PKG_PROGRESS_FILE}
fi

if [ -n "${REBOOT}" ]; then
	echo '***REBOOT***' >> ${PKG_PROGRESS_FILE}
	# give the frontend some time to figure out that a reboot is coming
	sleep 5
	/usr/local/etc/rc.reboot
fi

echo '***DONE***' >> ${PKG_PROGRESS_FILE}
