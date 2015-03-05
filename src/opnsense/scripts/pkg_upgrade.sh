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

PKG_PROGRESS_FILE=/tmp/pkg_upgrade.progress

# Truncate upgrade progress file
: > ${PKG_PROGRESS_FILE}

if [ -z "$pkg_running" ]; then
	echo "***GOT REQUEST TO UPGRADE: $package***" >> ${PKG_PROGRESS_FILE}
	if [ "$package" == "all" ]; then
	    # start pkg upgrade
	    echo '***STARTING UPGRADE***' >> ${PKG_PROGRESS_FILE}
		pkg upgrade -y >> ${PKG_PROGRESS_FILE}
		echo '***CHECKING FOR MORE UPGRADES, CAN TAKE 30 SECONDS***' >> ${PKG_PROGRESS_FILE}
		/usr/local/opnsense/scripts/pkg_updatecheck.sh
		echo '***DONE***' >> ${PKG_PROGRESS_FILE}
	else
		# start pkg upgrade
	    echo '***STARTING UPGRADE - ONE PACKAGE***' >> ${PKG_PROGRESS_FILE}
		pkg upgrade -y $package >> ${PKG_PROGRESS_FILE}
		echo '***CHECKING FOR MORE UPGRADES, CAN TAKE 30 SECONDS***' >> ${PKG_PROGRESS_FILE}
		/usr/local/opnsense/scripts/pkg_updatecheck.sh
		echo '***DONE***' >> ${PKG_PROGRESS_FILE}
	fi
	# remove no longer referenced packages
	pkg autoremove -y >> ${PKG_PROGRESS_FILE}
	# regenerate php.ini and restart the web server
	/usr/local/etc/rc.php_ini_setup >> ${PKG_PROGRESS_FILE}
	/usr/local/opnsense/service/configd_ctl.py 'service restart webgui' >> ${PKG_PROGRESS_FILE}
else
	echo 'Upgrade already in progress' >> ${PKG_PROGRESS_FILE}
	echo '***DONE***' >> ${PKG_PROGRESS_FILE}
fi
