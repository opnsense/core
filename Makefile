# Copyright (c) 2014-2025 Franco Fichtner <franco@opnsense.org>
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

all:
	@cat ${.CURDIR}/README.md | ${PAGER}

.include "Mk/version.mk"

.include "Mk/defaults.mk"
.include "Mk/common.mk"
.include "Mk/git.mk"
.include "Mk/lint.mk"
.include "Mk/style.mk"
.include "Mk/sweep.mk"

.for REPLACEMENT in ABI PHP PYTHON
. if empty(CORE_${REPLACEMENT})
.  warning Cannot build without CORE_${REPLACEMENT} set
. endif
CORE_MAKE+=	CORE_${REPLACEMENT}=${CORE_${REPLACEMENT}}
.endfor

_CORE_NEXT=	${CORE_ABI:C/\./ /}
.if ${_CORE_NEXT:[2]} == 7 # community
CORE_NEXT!=	expr ${_CORE_NEXT:[1]} + 1
CORE_NEXT:=	${CORE_NEXT}.1
.elif ${_CORE_NEXT:[2]} == 10 # business
CORE_NEXT!=	expr ${_CORE_NEXT:[1]} + 1
CORE_NEXT:=	${CORE_NEXT}.4
CORE_SPACER=	no
.elif ${_CORE_NEXT:[2]} == 1 # community
CORE_NEXT=	${_CORE_NEXT:[1]}
CORE_NEXT:=	${CORE_NEXT}.7
.elif ${_CORE_NEXT:[2]} == 4 # business
CORE_NEXT=	${_CORE_NEXT:[1]}
CORE_NEXT:=	${CORE_NEXT}.10
.else
.error Unsupported minor version for CORE_ABI=${CORE_ABI}
.endif

.if exists(${GIT}) && exists(${GITVERSION}) && exists(${.CURDIR}/.git)
. if ${CORE_TYPE:M[Dd][Ee][Vv]*}
_NEXTBETA!=	${GIT} tag -l ${CORE_NEXT}.b
.  if !empty(_NEXTBETA)
_NEXTMATCH=	--match=${CORE_NEXT}.b
.  else
_NEXTALPHA!=	${GIT} tag -l ${CORE_NEXT}.a
.   if !empty(_NEXTALPHA)
_NEXTMATCH=	--match=${CORE_NEXT}.a
.   else
_NEXTDEVEL!=	${GIT} tag -l ${CORE_ABI}\*
.    if !empty(_NEXTDEVEL)
_NEXTMATCH=	--match=${CORE_ABI}\*
.    endif
.   endif
.  endif
. elif ${CORE_TYPE:M[Bb][Uu][Ss]*}
_NEXTMATCH=	'' # XXX verbatim match for now
. else
_NEXTSTABLE!=	${GIT} tag -l ${CORE_ABI}\*
.  if !empty(_NEXTSTABLE)
_NEXTMATCH=	--match=${CORE_ABI}\*
.  endif
. endif
. if empty(_NEXTMATCH)
. error Did not find appropriate tag for CORE_ABI=${CORE_ABI}
. endif
CORE_COMMIT!=	${GITVERSION} ${_NEXTMATCH}
.endif

CORE_COMMIT?=	unknown 0 undefined
CORE_VERSION?=	${CORE_COMMIT:[1]}
CORE_REVISION?=	${CORE_COMMIT:[2]}
CORE_HASH?=	${CORE_COMMIT:[3]}

_CORE_SERIES=	${CORE_VERSION:S/./ /g}
CORE_SERIES?=	${_CORE_SERIES:[1]}.${_CORE_SERIES:[2]}
.if empty(CORE_SPACER)
CORE_SERIES_FW=	${CORE_SERIES:S/$/ /1}
.else
CORE_SERIES_FW=	${CORE_SERIES}
.endif

.if "${CORE_REVISION}" != "" && "${CORE_REVISION}" != "0"
CORE_PKGVERSION=	${CORE_VERSION}_${CORE_REVISION}
.else
CORE_PKGVERSION=	${CORE_VERSION}
.endif

CORE_PYTHON_DOT=	${CORE_PYTHON:C/./&./1}

CORE_COMMENT?=		${CORE_PRODUCT} ${CORE_TYPE} release
CORE_MAINTAINER?=	project@opnsense.org
CORE_ORIGIN?=		opnsense/${CORE_NAME}
CORE_PACKAGESITE?=	https://pkg.opnsense.org
CORE_PRODUCT?=		OPNsense
CORE_REPOSITORY?=	${CORE_ABI}/latest
CORE_WWW?=		https://opnsense.org/
CORE_USER?=		wwwonly
CORE_UID?=		789
CORE_GROUP?=		${CORE_USER}
CORE_GID?=		${CORE_UID}

CORE_COPYRIGHT_HOLDER?=	Deciso B.V.
CORE_COPYRIGHT_WWW?=	https://www.deciso.com/
CORE_COPYRIGHT_YEARS?=	2014-2025

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
			ifinfo \
			iftop \
			isc-dhcp44-server \
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

.for CONFLICT in ${CORE_CONFLICTS}
CORE_CONFLICTS+=	${CONFLICT}-devel
.endfor

# assume conflicts are just for plugins
CORE_CONFLICTS:=	${CORE_CONFLICTS:S/^/os-/g:O}

mount:
	@if [ ! -f ${WRKDIR}/.mount_done ]; then \
	    echo -n "Enabling core live mount..."; \
	    sed ${SED_REPLACE} ${.CURDIR}/src/${VERSIONFILE}.in > \
	        ${.CURDIR}/src/${VERSIONFILE}; \
	    mount_unionfs ${.CURDIR}/src ${LOCALBASE}; \
	    touch ${WRKDIR}/.mount_done; \
	    echo "done"; \
	    service configd restart; \
	fi

umount:
	@if [ -f ${WRKDIR}/.mount_done ]; then \
	    echo -n "Disabling core live mount..."; \
	    umount -f "<above>:${.CURDIR}/src"; \
	    rm ${WRKDIR}/.mount_done; \
	    echo "done"; \
	    service configd restart; \
	fi

manifest-check:
	# check if all annotations are in the version file
.for REPLACEMENT in ${REPLACEMENTS}
	@grep -q '\"${REPLACEMENT}\": \"%%${REPLACEMENT}%%\"' ${.CURDIR}/src/${VERSIONFILE}.in || \
	    (echo "Could not find ${REPLACEMENT} in version file"; exit 1)
.endfor

manifest:
	@echo "name: \"${CORE_NAME}\""
	@echo "version: \"${CORE_PKGVERSION}\""
	@echo "origin: \"${CORE_ORIGIN}\""
	@echo "comment: \"${CORE_COMMENT}\""
	@echo "desc: \"${CORE_HASH}\""
	@echo "maintainer: \"${CORE_MAINTAINER}\""
	@echo "www: \"${CORE_WWW}\""
	@echo "message: \"${CORE_MESSAGE}\""
	@echo "categories: [ \"sysutils\", \"www\" ]"
	@echo "licenselogic: \"single\""
	@echo "licenses: [ \"BSD2CLAUSE\" ]"
	@echo "prefix: ${LOCALBASE}"
	@echo "vital: true"
	@echo "deps: {"
	@for CORE_DEPEND in ${CORE_DEPENDS}; do \
		if ! ${PKG} query '  %n: { version: "%v", origin: "%o" }' \
		    $${CORE_DEPEND}; then \
			echo ">>> Missing dependency: $${CORE_DEPEND}" >&2; \
			exit 1; \
		fi; \
	done
	@echo "}"
	@if [ -f ${WRKSRC}${LOCALBASE}/${VERSIONFILE} ]; then \
	    echo "annotations $$(cat ${WRKSRC}${LOCALBASE}/${VERSIONFILE})"; \
	fi

.if ${.TARGETS:Mupgrade}
# lighter package format for quick completion
PKG_FORMAT?=	-f tar
.endif

PKG_SCRIPTS=	+PRE_INSTALL +POST_INSTALL \
		+PRE_UPGRADE +POST_UPGRADE \
		+PRE_DEINSTALL +POST_DEINSTALL

scripts:
.for PKG_SCRIPT in ${PKG_SCRIPTS}
	@if [ -f ${.CURDIR}/${PKG_SCRIPT} ]; then \
		sed ${SED_REPLACE} -- ${.CURDIR}/${PKG_SCRIPT} > \
		    ${DESTDIR}/${PKG_SCRIPT}; \
	fi
.endfor

install:
	@${CORE_MAKE} -C ${.CURDIR}/contrib install DESTDIR=${DESTDIR}
	@${CORE_MAKE} -C ${.CURDIR}/src install DESTDIR=${DESTDIR} ${MAKE_REPLACE}
.if exists(${LOCALBASE}/opnsense/www/index.php)
	# try to update the current system if it looks like one
	@touch ${LOCALBASE}/opnsense/www/index.php
	@${PLUGINCTL} -cq cache_flush
.endif

collect:
	@(cd ${.CURDIR}/src; find * -type f) | while read FILE; do \
		if [ -f ${DESTDIR}${LOCALBASE}/$${FILE} ]; then \
			tar -C ${DESTDIR}${LOCALBASE} -cpf - $${FILE} | \
			    tar -C ${.CURDIR}/src -xpf -; \
		fi; \
	done

bootstrap:
	@${CORE_MAKE} -C ${.CURDIR}/src install-bootstrap DESTDIR=${DESTDIR} \
	    NO_SAMPLE=please ${MAKE_REPLACE}

plist:
	@(${CORE_MAKE} -C ${.CURDIR}/contrib plist && \
	    ${CORE_MAKE} -C ${.CURDIR}/src plist) | sort

plist-fix:
	@${CORE_MAKE} DESTDIR=${DESTDIR} plist > ${.CURDIR}/plist

metadata:
	@mkdir -p ${DESTDIR}
	@${CORE_MAKE} DESTDIR=${DESTDIR} scripts
	@${CORE_MAKE} DESTDIR=${DESTDIR} manifest > ${DESTDIR}/+MANIFEST
	@${CORE_MAKE} DESTDIR=${DESTDIR} plist > ${DESTDIR}/plist

package-check:
	@if [ -f ${WRKDIR}/.mount_done ]; then \
		echo ">>> Cannot continue with live mount.  Please run 'make umount'." >&2; \
		exit 1; \
	fi

package: lint-plist manifest-check package-check clean-wrksrc
.for CORE_DEPEND in ${CORE_DEPENDS}
	@if ! ${PKG} info ${CORE_DEPEND} > /dev/null; then ${PKG} install -yfA ${CORE_DEPEND}; fi
.endfor
	@echo -n ">>> Staging files for ${CORE_NAME}-${CORE_PKGVERSION}..."
	@${CORE_MAKE} DESTDIR=${WRKSRC} install
	@echo " done"
	@echo ">>> Generated version info for ${CORE_NAME}-${CORE_PKGVERSION}:"
	@cat ${WRKSRC}${LOCALBASE}/${VERSIONFILE}
	@echo -n ">>> Generating metadata for ${CORE_NAME}-${CORE_PKGVERSION}..."
	@${CORE_MAKE} DESTDIR=${WRKSRC} metadata
	@echo " done"
	@echo ">>> Packaging files for ${CORE_NAME}-${CORE_PKGVERSION}:"
	@PORTSDIR=${.CURDIR} ${PKG} create ${PKG_FORMAT} -v -m ${WRKSRC} \
	    -r ${WRKSRC} -p ${WRKSRC}/plist -o ${PKGDIR}

upgrade-check:
	@if ! ${PKG} info ${CORE_NAME} > /dev/null; then \
		echo ">>> Cannot find package.  Please run 'opnsense-update -t ${CORE_NAME}'" >&2; \
		exit 1; \
	fi
	@if [ "$$(${VERSIONBIN} -vH)" = "${CORE_PKGVERSION} ${CORE_HASH}" ]; then \
		echo "Installed version already matches ${CORE_PKGVERSION} ${CORE_HASH}" >&2; \
		exit 1; \
	fi

upgrade: upgrade-check clean-pkgdir package
	@${PKG} delete -fy ${CORE_NAME} || true
	@${PKG} add ${PKGDIR}/*.pkg
	@${PLUGINCTL} -c webgui

glint: sweep plist-fix lint

license:
	@${.CURDIR}/Scripts/license > ${.CURDIR}/LICENSE

sync: license plist-fix

migrate:
	@${PLUGINCTL} -m

validate:
	@${PLUGINCTL} -v

# XXX we should stop treating AclConfig dir as the test's actual /conf dir
TEST_NO_CLOBBER=	${TESTDIR}/app/models/OPNsense/ACL/AclConfig/config.xml

test:
.if exists(${TESTDIR})
	@if [ "$$(${VERSIONBIN} -v)" != "${CORE_PKGVERSION}" ]; then \
		echo "Installed version does not match, expected ${CORE_PKGVERSION}"; \
		exit 1; \
	fi
	@cd ${TESTDIR} && cp ${TEST_NO_CLOBBER} ${TEST_NO_CLOBBER}.save && \
	    phpunit || true; rm -rf ${TESTDIR}/.phpunit.result.cache \
	    ${TESTDIR}/app/models/OPNsense/ACL/AclConfig/backup; \
	    mv ${TEST_NO_CLOBBER}.save ${TEST_NO_CLOBBER}
.endif

clean: clean-pkgdir clean-wrksrc clean-mfcdir checkout

.PHONY: license plist
