#!/bin/sh

# refresh relevant configuration files
/usr/local/etc/rc.configure_firmware

# background the cleanup job to avoid blocking
daemon -f opnsense-update -Fs
