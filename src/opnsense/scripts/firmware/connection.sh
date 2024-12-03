#!/bin/sh

# Copyright (C) 2021-2024 Franco Fichtner <franco@opnsense.org>
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

REQUEST="AUDIT CONNECTIVITY"

. /usr/local/opnsense/scripts/firmware/config.sh

URL=$(opnsense-update -M)
POPT="-c4 -s1500"

HOST=${URL#*://}
HOST=${HOST%%/*}

IPV4=$(host -t A ${HOST} | head -n 1 | cut -d\  -f4)
IPV6=$(host -t AAAA ${HOST} | head -n 1 | cut -d\  -f5)

# do not taint existing repository state by using an isolated database folder
export PKG_DBDIR=/tmp/firmware.repo.check
rm -rf ${PKG_DBDIR}
mkdir -p ${PKG_DBDIR}

if [ -n "${IPV4}" -a -z "${IPV4%%*.*}" ]; then
	output_txt "Checking connectivity for host: ${HOST} -> ${IPV4}"
	output_cmd ping -4 ${POPT} "${IPV4}"
	output_txt "Checking connectivity for repository (IPv4): ${URL}"
	output_cmd ${PKG} -4 update -f
else
	output_txt "No IPv4 address could be found for host: ${HOST}"
fi

if [ -n "${IPV6}" -a -z "${IPV6%%*:*}" ]; then
	output_txt "Checking connectivity for host: ${HOST} -> ${IPV6}"
	output_cmd ping -6 ${POPT} "${IPV6}"
	output_txt "Checking connectivity for repository (IPv6): ${URL}"
	output_cmd ${PKG} -6 update -f
else
	output_txt "No IPv6 address could be found for host: ${HOST}"
fi

for HOST in $(/usr/local/opnsense/scripts/firmware/hostnames.sh); do
	output_txt "Checking server certificate for host: ${HOST}"
	# XXX -crl_check and -crl_check_all are possible but -CRL pass is not working
	echo | output_cmd openssl s_client -quiet -no_ign_eof "${HOST}:443"
done

output_done
