#!/bin/sh

CORE=$(opnsense-version -n)
PKG="/usr/local/sbin/pkg-static"

if [ -z "${CORE}" ]; then
	echo "Could not determine core package name."
	exit 1
fi

if [ ! -f "${PKG}" ]; then
	echo "No package manager is installed to perform upgrades."
	exit 1
fi

if [ -z "$(${PKG} query %n ${CORE})" ]; then
	echo "Core package \"${CORE}\" not known to package database."
	exit 1
fi

if [ "$(${PKG} query %R pkg)" = "FreeBSD" ]; then
	echo "The Package manager \"pkg\" is incompatible and needs a reinstall."
	exit 1
fi

echo "Passed all upgrade tests."

exit 0
