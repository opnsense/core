# Copyright (c) 2014-2016 Franco Fichtner <franco@opnsense.org>
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

PKG!=		which pkg || echo true
GIT!=		which git || echo true
PAGER?=		less

all:
	@cat ${.CURDIR}/README.md | ${PAGER}

force:

WANTS=		git pear-PHP_CodeSniffer phpunit

.for WANT in ${WANTS}
want-${WANT}: force
	@${PKG} info ${WANT} > /dev/null
.endfor

.if ${GIT} != true
CORE_COMMIT!=	${.CURDIR}/scripts/version.sh
CORE_VERSION=	${CORE_COMMIT:C/-.*$//1}
CORE_HASH=	${CORE_COMMIT:C/^.*-//1}
.endif

CORE_ABI?=	16.7

.if "${FLAVOUR}" == OpenSSL || "${FLAVOUR}" == ""
CORE_REPOSITORY?=	${CORE_ABI}/latest
.elif "${FLAVOUR}" == LibreSSL
CORE_REPOSITORY?=	${CORE_ABI}/libressl
.else
CORE_REPOSITORY?=	${FLAVOUR}
.endif

CORE_PACKAGESITE?=	http://pkg.opnsense.org

CORE_NAME?=		opnsense-devel
CORE_FAMILY?=		development
CORE_ORIGIN?=		opnsense/${CORE_NAME}
CORE_COMMENT?=		OPNsense ${CORE_FAMILY} package
CORE_MAINTAINER?=	franco@opnsense.org
CORE_WWW?=		https://opnsense.org/
CORE_MESSAGE?=		ACME delivery for the crafty coyote!
CORE_DEPENDS?=		apinger \
			beep \
			bind910 \
			bsdinstaller \
			bsnmp-regex \
			bsnmp-ucd \
			ca_root_nss \
			choparp \
			cpustats \
			dhcp6 \
			dhcpleases \
			dnsmasq \
			expiretable \
			filterdns \
			filterlog \
			ifinfo \
			flock \
			flowd \
			igmpproxy \
			isc-dhcp43-client \
			isc-dhcp43-relay \
			isc-dhcp43-server \
			lighttpd \
			miniupnpd \
			mpd5 \
			ngattach \
			ntp \
			openssh-portable \
			openvpn \
			opnsense-lang \
			opnsense-update \
			p7zip \
			pecl-radius \
			pftop \
			phalcon \
			php-suhosin \
			php56 \
			php56-ctype \
			php56-curl \
			php56-dom \
			php56-filter \
			php56-gettext \
			php56-hash \
			php56-json \
			php56-ldap \
			php56-mcrypt \
			php56-openssl \
			php56-pdo \
			php56-session \
			php56-simplexml \
			php56-sockets \
			php56-sqlite3 \
			php56-xml \
			php56-zlib \
			py27-Jinja2 \
			py27-netaddr \
			py27-requests \
			py27-sqlite3 \
			py27-ujson \
			python27 \
			radvd \
			rate \
			relayd \
			rrdtool12 \
			samplicator \
			squid \
			sshlockout_pf \
			strongswan \
			sudo \
			suricata \
			syslogd \
			unbound \
			wol

WRKDIR?=${.CURDIR}/work
WRKSRC=	${WRKDIR}/src
PKGDIR=	${WRKDIR}/pkg

mount: want-git
	@if [ ! -f ${WRKDIR}/.mount_done ]; then \
	    echo -n "Enabling core.git live mount..."; \
	    echo "${CORE_COMMIT}" > \
	        ${.CURDIR}/src/opnsense/version/opnsense; \
	    mount_unionfs ${.CURDIR}/src /usr/local; \
	    touch ${WRKDIR}/.mount_done; \
	    echo "done"; \
	    service configd restart; \
	fi

umount: force
	@if [ -f ${WRKDIR}/.mount_done ]; then \
	    echo -n "Disabling core.git live mount..."; \
	    umount -f "<above>:${.CURDIR}/src"; \
	    rm ${.CURDIR}/src/opnsense/version/opnsense; \
	    rm ${WRKDIR}/.mount_done; \
	    echo "done"; \
	    service configd restart; \
	fi


manifest: want-git
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
	@echo "prefix: /usr/local"
	@echo "deps: {"
	@for CORE_DEPEND in ${CORE_DEPENDS}; do \
		if ! ${PKG} query '  %n: { version: "%v", origin: "%o" }' \
		    $${CORE_DEPEND}; then \
			echo ">>> Missing dependency: $${CORE_DEPEND}" >&2; \
			exit 1; \
		fi; \
	done
	@echo "}"

name: force
	@echo ${CORE_NAME}

depends: force
	@echo ${CORE_DEPENDS}

PKG_SCRIPTS=	+PRE_INSTALL +POST_INSTALL \
		+PRE_UPGRADE +POST_UPGRADE \
		+PRE_DEINSTALL +POST_DEINSTALL

scripts: want-git
.for PKG_SCRIPT in ${PKG_SCRIPTS}
	@if [ -e ${.CURDIR}/${PKG_SCRIPT} ]; then \
		cp -v -- ${.CURDIR}/${PKG_SCRIPT} ${DESTDIR}/; \
		sed -i '' -e "s/%%CORE_COMMIT%%/${CORE_COMMIT}/g" \
		    -e "s/%%CORE_NAME%%/${CORE_NAME}/g" \
		    -e "s/%%CORE_ABI%%/${CORE_ABI}/g" \
		    ${DESTDIR}/${PKG_SCRIPT}; \
	fi
.endfor

install: force
	@${MAKE} -C ${.CURDIR}/contrib install DESTDIR=${DESTDIR}
	@${MAKE} -C ${.CURDIR}/src install DESTDIR=${DESTDIR} \
	    CORE_NAME=${CORE_NAME} CORE_ABI=${CORE_ABI} \
	    CORE_PACKAGESITE=${CORE_PACKAGESITE} \
	    CORE_REPOSITORY=${CORE_REPOSITORY}

bootstrap: force
	@${MAKE} -C ${.CURDIR}/src install_bootstrap DESTDIR=${DESTDIR} \
	    NO_SAMPLE=please CORE_PACKAGESITE=${CORE_PACKAGESITE} \
	    CORE_NAME=${CORE_NAME} CORE_ABI=${CORE_ABI} \
	    CORE_REPOSITORY=${CORE_REPOSITORY}

plist: force
	@${MAKE} -C ${.CURDIR}/contrib plist
	@${MAKE} -C ${.CURDIR}/src plist

metadata: force
	@mkdir -p ${DESTDIR}
	@${MAKE} DESTDIR=${DESTDIR} scripts
	@${MAKE} DESTDIR=${DESTDIR} manifest > ${DESTDIR}/+MANIFEST
	@${MAKE} DESTDIR=${DESTDIR} plist > ${DESTDIR}/plist

package-keywords: force
	@if [ ! -f /usr/ports/Keywords/sample.ucl ]; then \
		mkdir -p /usr/ports/Keywords; \
		cd /usr/ports/Keywords; \
		fetch https://raw.githubusercontent.com/opnsense/ports/master/Keywords/sample.ucl; \
	fi
	@echo ">>> Installed /usr/ports/Keywords/sample.ucl"

package: force
	@if [ -f ${WRKDIR}/.mount_done ]; then \
		echo ">>> Cannot continue with live mount.  Please run 'make umount'." >&2; \
		exit 1; \
	fi
	@if [ ! -f /usr/ports/Keywords/sample.ucl ]; then \
		echo ">>> Missing required file(s).  Please run 'make package-keywords'" >&2; \
		exit 1; \
	fi
	@rm -rf ${WRKSRC} ${PKGDIR}
	@${MAKE} DESTDIR=${WRKSRC} FLAVOUR=${FLAVOUR} metadata
	@${MAKE} DESTDIR=${WRKSRC} FLAVOUR=${FLAVOUR} install
	@${PKG} create -v -m ${WRKSRC} -r ${WRKSRC} \
	    -p ${WRKSRC}/plist -o ${PKGDIR}
	@echo -n "Sucessfully built "
	@cd ${PKGDIR}; find . -name "*.txz" | cut -c3-

upgrade: package
	${PKG} delete -y ${CORE_NAME}
	@${PKG} add ${PKGDIR}/*.txz
	@/usr/local/etc/rc.restart_webgui

lint: force
	find ${.CURDIR}/src ${.CURDIR}/scripts \
	    -name "*.sh" -type f -print0 | xargs -0 -n1 sh -n
	find ${.CURDIR}/src ${.CURDIR}/scripts \
	    -name "*.xml" -type f -print0 | xargs -0 -n1 xmllint --noout
	find ${.CURDIR}/src \
	    ! -name "*.xml" ! -name "*.xml.sample" ! -name "*.eot" \
	    ! -name "*.svg" ! -name "*.woff" ! -name "*.woff2" \
	    ! -name "*.otf" ! -name "*.png" ! -name "*.js" \
	    ! -name "*.scss" ! -name "*.py" ! -name "*.ttf" \
	    ! -name "*.tgz" ! -name "*.xml.dist" \
	    -type f -print0 | xargs -0 -n1 php -l

sweep: force
	find ${.CURDIR}/src ! -name "*.min.*" ! -name "*.svg" \
	    ! -name "*.map" ! -name "*.ser" -type f -print0 | \
	    xargs -0 -n1 scripts/cleanfile
	find ${.CURDIR}/scripts -type f -print0 | \
	    xargs -0 -n1 scripts/cleanfile

style: want-pear-PHP_CodeSniffer
	@(phpcs --tab-width=4 --standard=PSR2 ${.CURDIR}/src/opnsense/mvc \
	    || true) > ${.CURDIR}/.style.out
	@echo -n "Total number of style warnings: "
	@grep '| WARNING' ${.CURDIR}/.style.out | wc -l
	@echo -n "Total number of style errors:   "
	@grep '| ERROR' ${.CURDIR}/.style.out | wc -l
	@cat ${.CURDIR}/.style.out
	@rm ${.CURDIR}/.style.out

stylefix: want-pear-PHP_CodeSniffer
	phpcbf --standard=PSR2 ${.CURDIR}/src/opnsense/mvc || true

setup: force
	${.CURDIR}/src/etc/rc.php_ini_setup

health: force
	# check test script output and advertise a failure...
	[ "`${.CURDIR}/src/etc/rc.php_test_run`" == "FCGI-PASSED PASSED" ]

clean: want-git
	${GIT} reset --hard HEAD && ${GIT} clean -xdqf .

.PHONY: force
