#!/bin/sh

# Copyright (c) 2022 Maurice Walker <maurice@walker.earth>
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

# This script is to be invoked by rtsold when a Router Advertisement
# with RDNSS / DNSSL options is encountered. It extracts interface,
# router and DNS information from arguments and STDIN.

if [ -z "${2}" ]; then
	echo "Nothing to do."
	exit 0
fi

# ${2} is 'ifname:slaac:[RA-source-address]', where 'ifname' is the
# interface the RA was received on.  Keep the ":slaac" suffix to create
# an interface fallback configuration handled through ifctl(8) utility.

ifname=${2%%:[*}
rasrca=${2##*:[}
rasrca=${rasrca%]}

# ${1} indicates whether DNS information should be added or deleted.

if [ "${1}" = "-a" ]; then
	# rtsold sends a resolv.conf(5) file to STDIN of this script
	while IFS=' ' read -r type value; do
		if [ "${type}" = "nameserver" ]; then
			# in:  nameserver 2001:db8::1
			#      nameserver 2001:db8::2
			#      nameserver 2001:db8::3
			# out: -a 2001:db8::1 -a 2001:db8::2 -a 2001:db8::3
			nameservers="${nameservers} -a ${value}"
		elif [ "${type}" = "search" ]; then
			# in:  search example.com example.net example.org
			# out: -a example.com -a example.net -a example.org
			for entry in $value; do
				searchlist="${searchlist} -a ${entry}"
			done
		fi
	done

	/usr/local/sbin/ifctl -i ${ifname} -6nd ${nameservers}
	/usr/local/sbin/ifctl -i ${ifname} -6sd ${searchlist}
	/usr/local/sbin/ifctl -i ${ifname} -6rd -a ${rasrca}
elif [ "${1}" = "-d" ]; then
	/usr/local/sbin/ifctl -i ${ifname} -6nd
	/usr/local/sbin/ifctl -i ${ifname} -6sd
	/usr/local/sbin/ifctl -i ${ifname} -6rd
fi

# wait for DAD to complete to avoid tentative address
sleep "$(($(sysctl -n net.inet6.ip6.dad_count) + 1))"

# remove slaac suffix here to reload correct interface
/usr/local/sbin/configctl -d interface newipv6 ${ifname%%:slaac}
