# Copyright (c) 2014-2018 Franco Fichtner <franco@opnsense.org>
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

.include "Mk/defaults.mk"

WANTS=		p5-File-Slurp pear-PHP_CodeSniffer phpunit6

.for WANT in ${WANTS}
want-${WANT}:
	@${PKG} info ${WANT} > /dev/null
.endfor

CORE_COMMIT!=	${.CURDIR}/Scripts/version.sh
CORE_VERSION=	${CORE_COMMIT:C/-.*$//1}
CORE_HASH=	${CORE_COMMIT:C/^.*-//1}

CORE_ABI?=	18.1
CORE_ARCH?=	${ARCH}
CORE_OPENVPN?=	# empty
CORE_PHP?=	71
CORE_PY?=	27

_FLAVOUR!=	if [ -f ${OPENSSL} ]; then ${OPENSSL} version; fi
FLAVOUR?=	${_FLAVOUR:[1]}

.if "${FLAVOUR}" == OpenSSL || "${FLAVOUR}" == ""
CORE_REPOSITORY?=	${CORE_ABI}/latest
.elif "${FLAVOUR}" == LibreSSL
CORE_REPOSITORY?=	${CORE_ABI}/libressl
.else
CORE_REPOSITORY?=	${FLAVOUR}
.endif

CORE_NAME?=		opnsense-devel
CORE_TYPE?=		development
CORE_MESSAGE?=		Insert Name Here

CORE_MAINTAINER?=	franco@opnsense.org
CORE_PACKAGESITE?=	http://pkg.opnsense.org
CORE_ORIGIN?=		opnsense/${CORE_NAME}
CORE_COMMENT?=		OPNsense ${CORE_TYPE} package
CORE_WWW?=		https://opnsense.org/

CORE_DEPENDS_amd64?=	beep bsdinstaller
CORE_DEPENDS_i386?=	${CORE_DEPENDS_amd64}

CORE_DEPENDS?=		${CORE_DEPENDS_${CORE_ARCH}} \
			apinger \
			ca_root_nss \
			choparp \
			cpustats \
			dhcp6 \
			dhcpleases \
			dnsmasq \
			expiretable \
			filterlog \
			ifinfo \
			flock \
			flowd \
			hostapd \
			isc-dhcp43-client \
			isc-dhcp43-relay \
			isc-dhcp43-server \
			lighttpd \
			mpd5 \
			ntp \
			openssh-portable \
			openvpn${CORE_OPENVPN} \
			opnsense-lang \
			opnsense-update \
			pam_opnsense \
			pecl-radius \
			pftop \
			phalcon \
			php${CORE_PHP}-ctype \
			php${CORE_PHP}-curl \
			php${CORE_PHP}-dom \
			php${CORE_PHP}-filter \
			php${CORE_PHP}-gettext \
			php${CORE_PHP}-hash \
			php${CORE_PHP}-intl \
			php${CORE_PHP}-json \
			php${CORE_PHP}-ldap \
			php${CORE_PHP}-mcrypt \
			php${CORE_PHP}-openssl \
			php${CORE_PHP}-pdo \
			php${CORE_PHP}-session \
			php${CORE_PHP}-simplexml \
			php${CORE_PHP}-sockets \
			php${CORE_PHP}-sqlite3 \
			php${CORE_PHP}-xml \
			php${CORE_PHP}-zlib \
			py${CORE_PY}-Jinja2 \
			py${CORE_PY}-dnspython \
			py${CORE_PY}-ipaddress \
			py${CORE_PY}-netaddr \
			py${CORE_PY}-requests \
			py${CORE_PY}-sqlite3 \
			py${CORE_PY}-ujson \
			radvd \
			rate \
			rrdtool12 \
			samplicator \
			squid \
			sshlockout_pf \
			strongswan \
			sudo \
			suricata \
			syslog-ng \
			syslogd \
			unbound \
			wpa_supplicant \
			zip

WRKDIR?=${.CURDIR}/work
WRKSRC?=${WRKDIR}/src
PKGDIR?=${WRKDIR}/pkg

mount:
	@if [ ! -f ${WRKDIR}/.mount_done ]; then \
	    echo -n "Enabling core.git live mount..."; \
	    echo "${CORE_COMMIT}" > \
	        ${.CURDIR}/src/opnsense/version/opnsense; \
	    mount_unionfs ${.CURDIR}/src ${LOCALBASE}; \
	    touch ${WRKDIR}/.mount_done; \
	    echo "done"; \
	    service configd restart; \
	fi

umount:
	@if [ -f ${WRKDIR}/.mount_done ]; then \
	    echo -n "Disabling core.git live mount..."; \
	    umount -f "<above>:${.CURDIR}/src"; \
	    rm ${.CURDIR}/src/opnsense/version/opnsense; \
	    rm ${WRKDIR}/.mount_done; \
	    echo "done"; \
	    service configd restart; \
	fi

manifest:
	@echo "name: \"${CORE_NAME}\""
	@echo "version: \"${CORE_VERSION}\""
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

name:
	@echo ${CORE_NAME}

depends:
	@echo ${CORE_DEPENDS}

PKG_SCRIPTS=	+PRE_INSTALL +POST_INSTALL \
		+PRE_UPGRADE +POST_UPGRADE \
		+PRE_DEINSTALL +POST_DEINSTALL

scripts:
.for PKG_SCRIPT in ${PKG_SCRIPTS}
	@if [ -e ${.CURDIR}/${PKG_SCRIPT} ]; then \
		cp -v -- ${.CURDIR}/${PKG_SCRIPT} ${DESTDIR}/; \
		sed -i '' -e "s/%%CORE_COMMIT%%/${CORE_COMMIT}/g" \
		    -e "s/%%CORE_NAME%%/${CORE_NAME}/g" \
		    -e "s/%%CORE_ABI%%/${CORE_ABI}/g" \
		    ${DESTDIR}/${PKG_SCRIPT}; \
	fi
.endfor

install:
	@${MAKE} -C ${.CURDIR}/contrib install DESTDIR=${DESTDIR}
	@${MAKE} -C ${.CURDIR}/src install DESTDIR=${DESTDIR} \
	    CORE_NAME=${CORE_NAME} CORE_ABI=${CORE_ABI} \
	    CORE_PACKAGESITE=${CORE_PACKAGESITE} \
	    CORE_REPOSITORY=${CORE_REPOSITORY}

bootstrap:
	@${MAKE} -C ${.CURDIR}/src install-bootstrap DESTDIR=${DESTDIR} \
	    NO_SAMPLE=please CORE_PACKAGESITE=${CORE_PACKAGESITE} \
	    CORE_NAME=${CORE_NAME} CORE_ABI=${CORE_ABI} \
	    CORE_REPOSITORY=${CORE_REPOSITORY}

plist:
	@(${MAKE} -C ${.CURDIR}/contrib plist && \
	    ${MAKE} -C ${.CURDIR}/src plist) | sort

plist-fix:
	@${MAKE} DESTDIR=${DESTDIR} plist > ${.CURDIR}/plist

plist-check:
	@${MAKE} DESTDIR=${DESTDIR} plist > ${WRKDIR}/plist.new
	@cat ${.CURDIR}/plist > ${WRKDIR}/plist.old
	@if ! diff -uq ${WRKDIR}/plist.old ${WRKDIR}/plist.new > /dev/null ; then \
		diff -u ${WRKDIR}/plist.old ${WRKDIR}/plist.new || true; \
		echo ">>> Package file lists do not match.  Please run 'make plist-fix'." >&2; \
		rm ${WRKDIR}/plist.*; \
		exit 1; \
	fi
	@rm ${WRKDIR}/plist.*

metadata:
	@mkdir -p ${DESTDIR}
	@${MAKE} DESTDIR=${DESTDIR} scripts
	@${MAKE} DESTDIR=${DESTDIR} manifest > ${DESTDIR}/+MANIFEST
	@${MAKE} DESTDIR=${DESTDIR} plist > ${DESTDIR}/plist

package-check:
	@if [ -f ${WRKDIR}/.mount_done ]; then \
		echo ">>> Cannot continue with live mount.  Please run 'make umount'." >&2; \
		exit 1; \
	fi

package: package-check clean-work
.for CORE_DEPEND in ${CORE_DEPENDS}
	@if ! ${PKG} info ${CORE_DEPEND} > /dev/null; then ${PKG} install -yA ${CORE_DEPEND}; fi
.endfor
	@${MAKE} DESTDIR=${WRKSRC} FLAVOUR=${FLAVOUR} metadata
	@${MAKE} DESTDIR=${WRKSRC} FLAVOUR=${FLAVOUR} install
	@PORTSDIR=${.CURDIR} ${PKG} create -v -m ${WRKSRC} -r ${WRKSRC} \
	    -p ${WRKSRC}/plist -o ${PKGDIR}

upgrade-check:
	@if ! ${PKG} info ${CORE_NAME} > /dev/null; then \
		echo ">>> Cannot find package.  Please run 'opnsense-update -t ${CORE_NAME}'" >&2; \
		exit 1; \
	fi

upgrade: plist-check upgrade-check clean-package package
	@${PKG} delete -fy ${CORE_NAME}
	@${PKG} add ${PKGDIR}/*.txz
	@/usr/local/etc/rc.restart_webgui

update:
	@${GIT} pull

lint: plist-check
	find ${.CURDIR}/src ${.CURDIR}/Scripts \
	    -name "*.sh" -type f -print0 | xargs -0 -n1 sh -n
	find ${.CURDIR}/src ${.CURDIR}/Scripts \
	    -name "*.xml*" -type f -print0 | xargs -0 -n1 xmllint --noout
	find ${.CURDIR}/src \
	    ! -name "*.xml" ! -name "*.xml.sample" ! -name "*.eot" \
	    ! -name "*.svg" ! -name "*.woff" ! -name "*.woff2" \
	    ! -name "*.otf" ! -name "*.png" ! -name "*.js" \
	    ! -name "*.scss" ! -name "*.py" ! -name "*.ttf" \
	    ! -name "*.tgz" ! -name "*.xml.dist" \
	    -type f -print0 | xargs -0 -n1 php -l

sweep:
	find ${.CURDIR}/src -type f -name "*.map" -print0 | \
	    xargs -0 -n1 rm
	if grep -nr sourceMappingURL= ${.CURDIR}/src; then \
		echo "Mentions of sourceMappingURL must be removed"; \
		exit 1; \
	fi
	find ${.CURDIR}/src ! -name "*.min.*" ! -name "*.svg" \
	    ! -name "*.ser" -type f -print0 | \
	    xargs -0 -n1 ${.CURDIR}/Scripts/cleanfile
	find ${.CURDIR}/Scripts -type f -print0 | \
	    xargs -0 -n1 ${.CURDIR}/Scripts/cleanfile
	find ${.CURDIR} -type f -depth 1 -print0 | \
	    xargs -0 -n1 ${.CURDIR}/Scripts/cleanfile

STYLEDIRS?=	src/etc/inc/plugins.inc.d src/opnsense

style: want-pear-PHP_CodeSniffer
	@: > ${WRKDIR}/style.out
.for STYLEDIR in ${STYLEDIRS}
	@(phpcs --standard=ruleset.xml ${.CURDIR}/${STYLEDIR} \
	    || true) >> ${WRKDIR}/style.out
.endfor
	@echo -n "Total number of style warnings: "
	@grep '| WARNING' ${WRKDIR}/style.out | wc -l
	@echo -n "Total number of style errors:   "
	@grep '| ERROR' ${WRKDIR}/style.out | wc -l
	@cat ${WRKDIR}/style.out | ${PAGER}
	@rm ${WRKDIR}/style.out

style-fix: want-pear-PHP_CodeSniffer
.for STYLEDIR in ${STYLEDIRS}
	phpcbf --standard=ruleset.xml ${.CURDIR}/${STYLEDIR} || true
.endfor

license: want-p5-File-Slurp
	@${.CURDIR}/Scripts/license > ${.CURDIR}/LICENSE

dhparam:
.for BITS in 1024 2048 4096
	${OPENSSL} dhparam -out \
	    ${.CURDIR}/src/etc/dh-parameters.${BITS} ${BITS}
.endfor

test: want-phpunit6
	@cd ${.CURDIR}/src/opnsense/mvc/tests && \
	    phpunit --configuration PHPunit.xml

clean-package:
	@rm -rf ${PKGDIR}

clean-src:
	@${GIT} reset -q ${.CURDIR}/src && \
	    ${GIT} checkout -f ${.CURDIR}/src && \
	    ${GIT} clean -xdqf ${.CURDIR}/src

clean-work:
	@rm -rf ${WRKSRC}

clean: clean-package clean-src clean-work

.PHONY: license plist
