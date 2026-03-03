# Copyright (c) 2023-2026 Franco Fichtner <franco@opnsense.org>
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions
# are met:
#
# 1. Redistributions of source code must retain the above copyright
#    notice, this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright
#    notice, this list of conditions and the following disclaimer in the
#    documentation and/or other materials provided with the distribution.
#
# THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND
# ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
# IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
# ARE DISCLAIMED.  IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE
# FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
# DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
# OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
# HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
# LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
# OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
# SUCH DAMAGE.

CORE_ABIS?=	26.1
CORE_ADDITIONS?=#empty
CORE_MESSAGE?=	One step ahead, one step behind it, now you gotta run to get even
CORE_NAME?=	opnsense
# adjust src/root/boot/lua/logo-hourglass.lua.in accordingly:
CORE_NICKNAME?=	Witty Woodpecker
CORE_TYPE?=	community
# plugins that were migrated to core are here
CORE_CONFLICTS?=firewall wireguard wireguard-go

CORE_COMMENT?=		${CORE_PRODUCT} ${CORE_TYPE} release
CORE_MAINTAINER?=	project@opnsense.org
CORE_ORIGIN?=		opnsense/${CORE_NAME}
CORE_PACKAGESITE?=	https://pkg.opnsense.org
CORE_PRODUCT?=		OPNsense
CORE_WWW?=		https://opnsense.org/
CORE_USER?=		wwwonly
CORE_UID?=		789
CORE_GROUP?=		${CORE_USER}
CORE_GID?=		${CORE_UID}

CORE_DEPENDS_aarch64?=	py${CORE_PYTHON}-duckdb \
			py${CORE_PYTHON}-numpy \
			py${CORE_PYTHON}-pandas \
			suricata

CORE_DEPENDS_amd64?=	beep \
			${CORE_DEPENDS_aarch64}

CORE_DEPENDS?=		ca_root_nss \
			choparp \
			cpustats \
			dhcp6c \
			dhcrelay \
			dnsmasq \
			dpinger \
			filterlog \
			flock \
			flowd \
			hostapd \
			hostwatch \
			ifinfo \
			iftop \
			kea \
			lighttpd \
			monit \
			mpd5 \
			ntp \
			openssh-portable \
			openvpn \
			opnsense-installer \
			opnsense-lang \
			opnsense-update \
			pam_opnsense \
			pftop \
			php${CORE_PHP}-ctype \
			php${CORE_PHP}-curl \
			php${CORE_PHP}-dom \
			php${CORE_PHP}-filter \
			php${CORE_PHP}-gettext \
			php${CORE_PHP}-ldap \
			php${CORE_PHP}-pcntl \
			php${CORE_PHP}-pdo \
			php${CORE_PHP}-pear-Crypt_CHAP \
			php${CORE_PHP}-pecl-radius \
			php${CORE_PHP}-phalcon \
			php${CORE_PHP}-phpseclib \
			php${CORE_PHP}-session \
			php${CORE_PHP}-simplexml \
			php${CORE_PHP}-sockets \
			php${CORE_PHP}-sqlite3 \
			php${CORE_PHP}-xml \
			php${CORE_PHP}-zlib \
			pkg \
			py${CORE_PYTHON}-Jinja2 \
			py${CORE_PYTHON}-dnspython \
			py${CORE_PYTHON}-jq \
			py${CORE_PYTHON}-ldap3 \
			py${CORE_PYTHON}-requests \
			py${CORE_PYTHON}-sqlite3 \
			py${CORE_PYTHON}-ujson \
			py${CORE_PYTHON}-vici \
			radvd \
			rrdtool \
			samplicator \
			strongswan \
			sudo \
			syslog-ng \
			unbound \
			wpa_supplicant \
			zip \
			${CORE_ADDITIONS} \
			${CORE_DEPENDS_${CORE_ARCH}}

CORE_COPYRIGHT_HOLDER?=	Deciso B.V.
CORE_COPYRIGHT_WWW?=	https://www.deciso.com/
CORE_COPYRIGHT_YEARS?=	2014-2026
