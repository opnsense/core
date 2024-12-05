#!/bin/sh

# Copyright (C) 2015-2024 Franco Fichtner <franco@opnsense.org>
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

REQUEST="INSTALL"

. /usr/local/opnsense/scripts/firmware/config.sh

PACKAGE=${1}

if [ "${PACKAGE#os-}" != "${PACKAGE}" ]; then
	COREPKG=$(opnsense-version -n)
	COREVER=$(opnsense-version -v)
	REPOVER=$(${PKG} rquery %v ${COREPKG})

	# plugins must pass a version check on up-to-date core package
	if [ "$(${PKG} version -t ${COREVER} ${REPOVER})" = "<" ]; then
		output_txt "Installation out of date. The update to ${COREPKG}-${REPOVER} is required."
		output_done
	fi
fi

output_cmd ${PKG} install -y "${PACKAGE}"
output_cmd ${BASEDIR}/register.php install "${PACKAGE}"
output_cmd ${PKG} autoremove -y

output_done
