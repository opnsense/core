#!/bin/sh

pkg_running=`ps -x | grep "pkg " | grep -v "grep"`
if [ "$pkg_running" == "" ]; then
	if [ -f /tmp/pkg_upgrade.progress ]; then
		# Remove leftovers from previous upgrade first
		rm /tmp/pkg_upgrade.progress
	fi
      # start pkg upgrade
	pkg upgrade -y > /tmp/pkg_upgrade.progress &
	echo '***DONE***' >> /tmp/pkg_upgrade.progress
else
	echo 'Upgrade already in progress'
	echo '***DONE***'
fi


