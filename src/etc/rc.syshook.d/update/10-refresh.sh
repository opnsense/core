#!/bin/sh

# refresh relevant configuration files
/usr/local/etc/rc.configure_firmware

# background the cleanup job to avoid blocking
daemon /usr/local/etc/rc.syshook.d/upgrade/90-cleanup.sh
