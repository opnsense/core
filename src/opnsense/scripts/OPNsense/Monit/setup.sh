#!/bin/sh

# change permissions of the monit configuration file
chmod 600 /usr/local/etc/monitrc || exit 1
exit 0
