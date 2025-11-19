#!/bin/sh

for DIR in /var/db/hostwatch /var/run/hostwatch; do
	mkdir -p ${DIR}
	chown -R hostd:hostd ${DIR}
done
