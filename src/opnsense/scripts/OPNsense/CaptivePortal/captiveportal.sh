#!/bin/sh

# setup chroot environment for lighttpd
if [ ! -d /var/captiveportal ]; then
    mkdir /var/captiveportal
fi
