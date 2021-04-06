#!/bin/sh

# Copyright (C) 2017-2021 Franco Fichtner <franco@opnsense.org>
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

LIC="BSD2CLAUSE"
OS="HardenedBSD"
REPO="OPNsense"
SEP=${1:-","}

read BN BV BS << EOF
$(opnsense-version -nvs base)
EOF

read KN KV KS << EOF
$(opnsense-version -nvs kernel)
EOF

BL=0
KL=0

opnsense-update -Tb || BL=1
opnsense-update -Tk || KL=1

cat << EOF
${BN}${SEP}${BV}${SEP}${OS} userland set${SEP}${BS}${SEP}${BL}${SEP}${LIC}${SEP}${REPO}${SEP}
${KN}${SEP}${KV}${SEP}${OS} kernel set${SEP}${KS}${SEP}${KL}${SEP}${LIC}${SEP}${REPO}${SEP}
EOF
