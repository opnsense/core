#!/bin/sh

# refresh relevant configuration files
/usr/local/etc/rc.configure_firmware

# refresh /etc/os-release
opnsense-version -o

# remove our stale pyc files not handled by pkg
find /usr/local/opnsense -type f -name "*.pyc" -delete
