#!/bin/sh

# Copyright (C) 2014 Deciso B.V.
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions are met:
#
# 1. Redistributions of source code must retain the above copyright notice,
#    this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright
#    notice, this list of conditions and the following disclaimer in the
#    documentation and/or other materials provided with the distribution.
#
# THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
# INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
# AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
# AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
# OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
# SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
# INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
# CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
# ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
# POSSIBILITY OF SUCH DAMAGE.

CPWORKDIR="/var/captiveportal"
CPDEFAULTTEMPLATE="/usr/local/opnsense/scripts/OPNsense/CaptivePortal/htdocs_default"

# extract all zones from captive portal configuration
CPZONES=`cat /usr/local/etc/captiveportal.conf | grep "\[zone_" | sed 's/\[zone_//' | sed 's/\]//'`

# initialize captiveportal work directory
if [ ! -d $CPWORKDIR ]; then
  mkdir -p $CPWORKDIR
fi

# startup API dispatcher, forwards captive portal api request to shared OPNsense API
if [ -f /var/run/lighttpd-api-dispatcher.pid ]; then
  echo "Stopping API dispatcher"
  /bin/pkill -TERM -F /var/run/lighttpd-api-dispatcher.pid
fi
echo "Starting API dispatcher"
/usr/local/sbin/lighttpd -f /var/etc/lighttpd-api-dispatcher.conf

# startup / bootstrap zones
for zoneid in $CPZONES
do
    # bootstrap captiveportal jail
    zonedirname="zone$zoneid"
    echo "Install : zone $zoneid"
    if [ -d $CPWORKDIR/$zonedirname/tmp ]; then
        # remove temp (flush)
        rm -rf $CPWORKDIR/$zonedirname/tmp
    fi
    mkdir $CPWORKDIR/$zonedirname/tmp
    chmod 770 $CPWORKDIR/$zonedirname/tmp
    chown www:www $CPWORKDIR/$zonedirname/tmp

    # sync default template
    /usr/local/bin/rsync -a $CPDEFAULTTEMPLATE/* $CPWORKDIR/$zonedirname/htdocs/

    # todo, overlay custom user layout if available

    # stop running instance
    zonepid="/var/run/lighttpd-cp-zone-$zoneid.pid"
    if [ -f $zonepid ]; then
        echo "Stop : zone $zoneid"
        /bin/pkill -TERM -F /var/run/lighttpd-cp-zone-$zoneid.pid
        rm /var/run/lighttpd-cp-zone-$zoneid.pid
    fi

    # start new instance
    echo "Start : zone $zoneid"
    /usr/local/sbin/lighttpd -f /var/etc/lighttpd-cp-zone-$zoneid.conf
done


# cleanup removed zones
for installed_zoneid in `ls $CPWORKDIR |  sed 's/zone//g'`
do
    if [ -d $CPWORKDIR/zone$installed_zoneid ]; then
        for zoneid in $CPZONES
        do
            is_installed=0
            if [ "$zoneid" -eq "$installed_zoneid" ]; then
                is_installed=1
            fi
            if [ "$is_installed" -eq 0 ]; then
                echo "Uninstall : zone $installed_zoneid"
                # todo, insert rm
            fi
        done
    fi
done
