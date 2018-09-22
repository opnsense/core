# Copyright (c) 2016-2018 Franco Fichtner <franco@opnsense.org>
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

OPENSSL?=	${LOCALBASE}/bin/openssl

PKG!=		which pkg || echo true
GIT!=		which git || echo true
ARCH!=		uname -p

REPLACEMENTS=	CORE_ABI \
		CORE_ARCH \
		CORE_COPYRIGHT_HOLDER \
		CORE_COPYRIGHT_WWW \
		CORE_COPYRIGHT_YEARS \
		CORE_FLAVOUR \
		CORE_HASH \
		CORE_MAINTAINER \
		CORE_NAME \
		CORE_PACKAGESITE \
		CORE_PRODUCT \
		CORE_REPOSITORY \
		CORE_VERSION \
		CORE_WWW

MAKE_REPLACE=	# empty
SED_REPLACE=	# empty

.for REPLACEMENT in ${REPLACEMENTS}
MAKE_REPLACE+=	${REPLACEMENT}="${${REPLACEMENT}}"
SED_REPLACE+=	-e "s=%%${REPLACEMENT}%%=${${REPLACEMENT}}=g"
.endfor
