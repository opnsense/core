all:

install:
	@mkdir -p ${DESTDIR}/usr/local/etc/pkg
	@cp -vr ${.CURDIR}/repos ${DESTDIR}/usr/local/etc/pkg/
	@cp -vr ${.CURDIR}/fingerprints ${DESTDIR}/usr/local/etc/pkg/

plist:
	@(cd ${.CURDIR}/repos; find * -type f) | \
	    xargs -n1 printf "/usr/local/etc/pkg/repos/%s\n"
	@(cd ${.CURDIR}/fingerprints; find * -type f) | \
	    xargs -n1 printf "/usr/local/etc/pkg/fingerprints/%s\n"

.PHONY: install plist
