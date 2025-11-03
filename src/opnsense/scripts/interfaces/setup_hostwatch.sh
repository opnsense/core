#!/bin/sh

mkdir /var/db/hostwatch 2>/dev/null
mkdir /var/run/hostwatch 2>/dev/null
chown -R hostd:hostd /var/db/hostwatch
chown -R hostd:hostd /var/run/hostwatch
