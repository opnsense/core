#!/bin/sh

CORE=$(opnsense-version -n)

if [ -z "${CORE}" ]; then
	echo "Could not determine core package name."
	exit 1
fi

if [ -z "$(/usr/local/sbin/pkg-static query %n ${CORE})" ]; then
	echo "Core package \"${CORE}\" not known to package database."
	exit 1
fi

echo "Passed all upgrade tests."

exit 0
