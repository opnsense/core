#!/bin/sh

pkg_running=`ps -x | grep "pkg " | grep -v "grep"`
if [ "$pkg_running" == "" ]; then
	if [ -f /tmp/pkg_upgrade.progress ]
		# Remove leftovers from previous upgrade first
		rm /tmp/pkg_upgrade.progress
	fi
      # start pkg upgrade
	pkg upgrade -n > /tmp/pkg_upgrade.progress & # Need to st this to -y for production, now set to -n for testing purpose
	echo '***DONE***' >> /tmp/pkg_upgrade.progress
else
	echo 'Upgrade already in progress'
	echo '***DONE***'
fi


