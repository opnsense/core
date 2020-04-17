#!/bin/sh

COMMAND=${1}
NOTELENGTH=25

if [ -f /conf/config.xml ]; then
	if [ "$(/usr/bin/grep -c disablebeep /conf/config.xml)" != "0" ]; then
		exit;
	fi
fi

if [ -c "/dev/speaker" ]; then
	if [ "${COMMAND}" = "start" ]; then
		/usr/local/bin/beep -p 500 $NOTELENGTH
		/usr/local/bin/beep -p 400 $NOTELENGTH
		/usr/local/bin/beep -p 600 $NOTELENGTH
		/usr/local/bin/beep -p 800 $NOTELENGTH
		/usr/local/bin/beep -p 800 $NOTELENGTH
	elif [ "${COMMAND}" = "stop" ]; then
		/usr/local/bin/beep -p 600 $NOTELENGTH
		/usr/local/bin/beep -p 800 $NOTELENGTH
		/usr/local/bin/beep -p 500 $NOTELENGTH
		/usr/local/bin/beep -p 400 $NOTELENGTH
		/usr/local/bin/beep -p 400 $NOTELENGTH
	fi
fi
