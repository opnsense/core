all:

CORE_NAME=	opnsense-devel

name:
	@echo ${CORE_NAME}

scripts:
	@mkdir -p ${DESTDIR}
	@cp -v ${.CURDIR}/+PRE_DEINSTALL ${DESTDIR}
	@cp -v ${.CURDIR}/+POST_INSTALL ${DESTDIR}

manifest:
	@echo "name: ${CORE_NAME}"
	@echo "version: \"%%REPO_VERSION%%\""
	@echo "origin: opnsense/${CORE_NAME}"
	@echo "comment: \"OPNsense core development package\""
	@echo "desc: XXX"
	@echo "maintainer: franco@opnsense.org"
	@echo "www: https://opnsense.org/"
	@echo "prefix: /"
	@echo "deps: {"
	@echo "%%REPO_DEPENDS%%"
	@echo "}"

install:
	@mkdir -p ${DESTDIR}/usr/local/etc/pkg
	@cp -vr ${.CURDIR}/repos ${DESTDIR}/usr/local/etc/pkg/
	@cp -vr ${.CURDIR}/fingerprints ${DESTDIR}/usr/local/etc/pkg/

plist:
	@(cd ${.CURDIR}/repos; find * -type f) | \
	    xargs -n1 printf "/usr/local/etc/pkg/repos/%s\n"
	@(cd ${.CURDIR}/fingerprints; find * -type f) | \
	    xargs -n1 printf "/usr/local/etc/pkg/fingerprints/%s\n"

.PHONY: install plist scripts manifest
