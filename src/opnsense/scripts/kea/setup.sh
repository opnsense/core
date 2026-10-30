#!/bin/sh

# for RAM disks before starting kea_prefix_watcher.py
/usr/bin/install -d -m 0750 /var/log/kea
/usr/local/sbin/pluginctl -c kea_sync
