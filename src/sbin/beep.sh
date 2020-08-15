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
		/usr/local/bin/beep -l 350 -f 392 -D 100 -n -l 350 -f 392 -D 100 -n -l 350 -f 392 -D 100 -n -l 250 -f 311.1 -D 100 -n -l 25 -f 466.2 -D 100 -n -l 350 -f 392 -D 100 -n -l 250 -f 311.1 -D 100 -n -l 25 -f 466.2 -D 100 -n -l 700 -f 392 -D 100 -n -l 350 -f 587.32 -D 100 -n -l 350 -f 587.32 -D 100 -n -l 350 -f 587.32 -D 100 -n -l 250 -f 622.26 -D 100 -n -l 25 -f 466.2 -D 100 -n -l 350 -f 369.99 -D 100 -n -l 250 -f 311.1 -D 100 -n -l 25 -f 466.2 -D 100 -n -l 700 -f 392 -D 100 -n -l 350 -f 784 -D 100 -n -l 250 -f 392 -D 100 -n -l 25 -f 392 -D 100 -n -l 350 -f 784 -D 100 -n -l 250 -f 739.98 -D 100 -n -l 25 -f 698.46 -D 100 -n -l 25 -f 659.26 -D 100 -n -l 25 -f 622.26 -D 100 -n -l 50 -f 659.26 -D 400 -n -l 25 -f 415.3 -D 200 -n -l 350 -f 554.36 -D 100 -n -l 250 -f 523.25 -D 100 -n -l 25 -f 493.88 -D 100 -n -l 25 -f 466.16 -D 100 -n -l 25 -f 440 -D 100 -n -l 50 -f 466.16 -D 400 -n -l 25 -f 311.13 -D 200 -n -l 350 -f 369.99 -D 100 -n -l 250 -f 311.13 -D 100 -n -l 25 -f 392 -D 100 -n -l 350 -f 466.16 -D 100 -n -l 250 -f 392 -D 100 -n -l 25 -f 466.16 -D 100 -n -l 700 -f 587.32 -D 100 -n -l 350 -f 784 -D 100 -n -l 250 -f 392 -D 100 -n -l 25 -f 392 -D 100 -n -l 350 -f 784 -D 100 -n -l 250 -f 739.98 -D 100 -n -l 25 -f 698.46 -D 100 -n -l 25 -f 659.26 -D 100 -n -l 25 -f 622.26 -D 100 -n -l 50 -f 659.26 -D 400 -n -l 25 -f 415.3 -D 200 -n -l 350 -f 554.36 -D 100 -n -l 250 -f 523.25 -D 100 -n -l 25 -f 493.88 -D 100 -n -l 25 -f 466.16 -D 100 -n -l 25 -f 440 -D 100 -n -l 50 -f 466.16 -D 400 -n -l 25 -f 311.13 -D 200 -n -l 350 -f 392 -D 100 -n -l 250 -f 311.13 -D 100 -n -l 25 -f 466.16 -D 100 -n -l 300 -f 392.00 -D 150 -n -l 250 -f 311.13 -D 100 -n -l 25 -f 466.16 -D 100 -n -l 700 -f 392
	elif [ "${COMMAND}" = "stop" ]; then
		/usr/local/bin/beep -p 600 $NOTELENGTH
		/usr/local/bin/beep -p 800 $NOTELENGTH
		/usr/local/bin/beep -p 500 $NOTELENGTH
		/usr/local/bin/beep -p 400 $NOTELENGTH
		/usr/local/bin/beep -p 400 $NOTELENGTH
	fi
fi
