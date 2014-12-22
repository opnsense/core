#!/bin/sh
pkg upgrade -y > /tmp/pkg_upgrade.progress &
echo '***DONE***' >> /tmp/pkg_upgrade.progress
rm /tmp/pkg_upgrade.progress

