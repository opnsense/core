#!/bin/sh

SURICATA_DIRS="/var/log/suricata /usr/local/etc/suricata/conf.d"

for SURICATA_DIR in ${SURICATA_DIRS}; do
	mkdir -p ${SURICATA_DIR}
	chown -R root:wheel ${SURICATA_DIR}
	chmod -R 0700 ${SURICATA_DIR}
done

# make sure we can load our yaml file if we don't have rules installed yet
touch /usr/local/etc/suricata/installed_rules.yaml

# make sure the file with the prefixes of the dynamic IPv6 interfaces exists and is current
/usr/local/opnsense/scripts/suricata/dynamic_homenet.php

# always load ipdivert so inline mode is available
kldload -q ipdivert || true
