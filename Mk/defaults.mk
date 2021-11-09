# Copyright (c) 2016-2021 Franco Fichtner <franco@opnsense.org>
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

LOCALBASE?=	/usr/local
PAGER?=		less

PKG=		${LOCALBASE}/sbin/pkg
.if ! exists(${PKG})
PKG=		true
.endif
GIT!=		which git || echo true

GITVERSION=	${.CURDIR}/Scripts/version.sh

_CORE_ARCH!=	uname -p
CORE_ARCH?=	${_CORE_ARCH}

OPENSSL=	${LOCALBASE}/bin/openssl

.if ! defined(CORE_FLAVOUR)
.if exists(${OPENSSL})
_CORE_FLAVOUR!=	${OPENSSL} version
CORE_FLAVOUR?=	${_CORE_FLAVOUR:[1]}
.else
.warning "Detected 'Base' flavour is not currently supported"
CORE_FLAVOUR?=	Base
.endif
.endif

PHPBIN=		${LOCALBASE}/bin/php

.if exists(${PHPBIN})
_CORE_PHP!=	${PHPBIN} -v
CORE_PHP?=	${_CORE_PHP:[2]:S/./ /g:[1..2]:tW:S/ //}
.endif

VERSIONBIN=	${LOCALBASE}/sbin/opnsense-version

.if exists(${VERSIONBIN})
_CORE_ABI!=	${VERSIONBIN} -a
CORE_ABI?=	${_CORE_ABI}
.endif

PYTHONLINK=	${LOCALBASE}/bin/python3

.if exists(${PYTHONLINK})
_CORE_PYTHON!=	${PYTHONLINK} -V
CORE_PYTHON?=	${_CORE_PYTHON:[2]:S/./ /g:[1..2]:tW:S/ //}
.endif

.if exists(${PKG})
_CORE_SYSLOGNG!=${PKG} query %v syslog-ng
CORE_SYSLOGNG?=	${_CORE_SYSLOGNG:S/./ /g:[1..2]:tW:S/ /./g}
.endif

REPLACEMENTS=	CORE_ABI \
		CORE_ARCH \
		CORE_COMMIT \
		CORE_COPYRIGHT_HOLDER \
		CORE_COPYRIGHT_WWW \
		CORE_COPYRIGHT_YEARS \
		CORE_FLAVOUR \
		CORE_HASH \
		CORE_MAINTAINER \
		CORE_NAME \
		CORE_NEXT \
		CORE_NICKNAME \
		CORE_PACKAGESITE \
		CORE_PKGVERSION \
		CORE_PRODUCT \
		CORE_PYTHON_DOT \
		CORE_REPOSITORY \
		CORE_SERIES \
		CORE_SYSLOGNG \
		CORE_VERSION \
		CORE_WWW

MAKE_REPLACE=	# empty
SED_REPLACE=	# empty

.for REPLACEMENT in ${REPLACEMENTS}
MAKE_REPLACE+=	${REPLACEMENT}="${${REPLACEMENT}}"
SED_REPLACE+=	-e "s=%%${REPLACEMENT}%%=${${REPLACEMENT}}=g"
.endfor
