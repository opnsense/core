#!/bin/sh

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

package=$1
# Check if another pkg process is already running
pkg_running=`ps -x | grep "pkg " | grep -v "grep" | grep -v "pkg_upgrade.sh"`

if [ -f /tmp/pkg_upgrade.progress ]; then
	# Remove leftovers from previous upgrade first
	rm /tmp/pkg_upgrade.progress
fi

# Create upgrade progress file first
touch /tmp/pkg_upgrade.progress
if [ -z "$pkg_running" ]; then
	echo "***GOT REQUEST TO UPGRADE: $package***" >> /tmp/pkg_upgrade.progress
	if [ "$package" == "all" ]; then
	    # start pkg upgrade
	    echo '***STARTING UPGRADE***' >> /tmp/pkg_upgrade.progress
		pkg upgrade -y >> /tmp/pkg_upgrade.progress
		echo '***CHECKING FOR MORE UPGRADES, CAN TAKE 30 SECONDS***' >> /tmp/pkg_upgrade.progress
		/usr/local/opnsense/scripts/pkg_updatecheck.sh
		echo '***DONE***' >> /tmp/pkg_upgrade.progress
	else
		# start pkg upgrade
	    echo '***STARTING UPGRADE - ONE PACKAGE***' >> /tmp/pkg_upgrade.progress
		pkg upgrade -y $package >> /tmp/pkg_upgrade.progress
		echo '***CHECKING FOR MORE UPGRADES, CAN TAKE 30 SECONDS***' >> /tmp/pkg_upgrade.progress
		/usr/local/opnsense/scripts/pkg_updatecheck.sh
		echo '***DONE***' >> /tmp/pkg_upgrade.progress
	fi
else
	echo 'Upgrade already in progress' >> /tmp/pkg_upgrade.progress
	echo '***DONE***' >> /tmp/pkg_upgrade.progress
fi