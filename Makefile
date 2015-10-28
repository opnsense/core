PKG!=		which pkg || echo true
PAGER?=		less

all:
	@cat ${.CURDIR}/README.md | ${PAGER}

force:

mount: force
	@${.CURDIR}/scripts/version.sh > \
	    ${.CURDIR}/src/opnsense/version/opnsense
	mount_unionfs ${.CURDIR}/src /usr/local
	@service configd restart

umount: force
	umount -f "<above>:${.CURDIR}/src"
	@service configd restart

remount: umount mount

CORE_COMMIT!=	${.CURDIR}/scripts/version.sh
CORE_VERSION=	${CORE_COMMIT:C/-.*$//1}
CORE_HASH=	${CORE_COMMIT:C/^.*-//1}

.if "${FLAVOUR}" == LibreSSL
CORE_REPOSITORY?=	libressl
.else
CORE_REPOSITORY?=	latest
.endif
CORE_PACKAGESITE?=	http://pkg.opnsense.org

CORE_NAME?=		opnsense-devel
CORE_ORIGIN?=		opnsense/${CORE_NAME}
CORE_COMMENT?=		OPNsense development package
CORE_MAINTAINER?=	franco@opnsense.org
CORE_WWW?=		https://opnsense.org/
CORE_MESSAGE?=		Follow the brave badger!
CORE_DEPENDS?=		apinger \
			ataidle \
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
			igmpproxy \
			isc-dhcp42-client \
			isc-dhcp42-relay \
			isc-dhcp42-server \
			lighttpd \
			minicron \
			miniupnpd \
			mpd4 \
			mpd5 \
			ntp \
			openssh-portable \
			openvpn \
			opnsense-update \
			pecl-radius \
			pftop \
			phalcon \
			php-pfSense \
			php-suhosin \
			php-xdebug \
			php56 \
			php56-bcmath \
			php56-bz2 \
			php56-ctype \
			php56-curl \
			php56-dom \
			php56-filter \
			php56-gettext \
			php56-hash \
			php56-json \
			php56-ldap \
			php56-mbstring \
			php56-mcrypt \
			php56-mysql \
			php56-openssl \
			php56-pdo \
			php56-pdo_sqlite \
			php56-session \
			php56-simplexml \
			php56-sockets \
			php56-sqlite3 \
			php56-tokenizer \
			php56-xml \
			php56-zlib \
			py27-Jinja2 \
			py27-requests \
			py27-sqlite3 \
			py27-ujson \
			python27 \
			radvd \
			rate \
			relayd \
			rrdtool12 \
			smartmontools \
			squid \
			sshlockout_pf \
			strongswan \
			sudo \
			suricata \
			syslogd \
			unbound \
			wol \
			zip

manifest: force
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
		${PKG} query '  %n: { version: "%v", origin: "%o" }' \
		    $${CORE_DEPEND}; \
	done
	@echo "}"

name: force
	@echo ${CORE_NAME}

depends: force
	@echo ${CORE_DEPENDS}

scripts: force
	@mkdir -p ${DESTDIR}
	@cp -v -- +PRE_DEINSTALL +POST_INSTALL ${DESTDIR}
	@sed -i '' -e "s/%%CORE_COMMIT%%/${CORE_COMMIT}/g" \
	    ${DESTDIR}/+POST_INSTALL

install: force
	@${MAKE} -C ${.CURDIR}/contrib install DESTDIR=${DESTDIR}
	@${MAKE} -C ${.CURDIR}/lang install DESTDIR=${DESTDIR}
	@${MAKE} -C ${.CURDIR}/src install DESTDIR=${DESTDIR} \
	    CORE_PACKAGESITE=${CORE_PACKAGESITE} \
	    CORE_REPOSITORY=${CORE_REPOSITORY}

plist: force
	@${MAKE} -C ${.CURDIR}/contrib plist
	@${MAKE} -C ${.CURDIR}/lang plist
	@${MAKE} -C ${.CURDIR}/src plist

lint: force
	find ${.CURDIR}/src ${.CURDIR}/lang/dynamic/helpers \
	    ! -name "*.xml" ! -name "*.xml.sample" ! -name "*.eot" \
	    ! -name "*.svg" ! -name "*.woff" ! -name "*.woff2" \
	    ! -name "*.otf" ! -name "*.png" ! -name "*.js" \
	    ! -name "*.scss" ! -name "*.py" ! -name "*.ttf" \
	    ! -name "*.tgz" -type f -print0 | xargs -0 -n1 php -l

sweep: force
	find ${.CURDIR}/src ! -name "*.min.*" ! -name "*.svg" \
	    ! -name "*.map" -type f -print0 | \
	    xargs -0 -n1 scripts/cleanfile
	find ${.CURDIR}/lang -type f -print0 | \
	    xargs -0 -n1 scripts/cleanfile
	find ${.CURDIR}/scripts -type f -print0 | \
	    xargs -0 -n1 scripts/cleanfile

style: force
	@(phpcs --tab-width=4 --standard=PSR2 ${.CURDIR}/src/opnsense/mvc \
	    || true) > ${.CURDIR}/.style.out
	@echo -n "Total number of style warnings: "
	@grep '| WARNING' ${.CURDIR}/.style.out | wc -l
	@echo -n "Total number of style errors:   "
	@grep '| ERROR' ${.CURDIR}/.style.out | wc -l
	@cat ${.CURDIR}/.style.out
	@rm ${.CURDIR}/.style.out

stylefix: force
	phpcbf --standard=PSR2 ${.CURDIR}/src/opnsense/mvc || true

setup: force
	${.CURDIR}/src/etc/rc.php_ini_setup

health: force
	# check test script output and advertise a failure...
	[ "`${.CURDIR}/src/etc/rc.php_test_run`" == "FCGI-PASSED PASSED" ]

clean:
	git reset --hard HEAD && git clean -xdqf .

.PHONY: force
