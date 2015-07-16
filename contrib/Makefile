all:

install:
	@mkdir -p ${DESTDIR}/usr/local/share
	@cp -vr ${.CURDIR}/openvpn ${DESTDIR}/usr/local/share/
	@cp -vr ${.CURDIR}/mobile-broadband-provider-info \
	    ${DESTDIR}/usr/local/share/

plist:
	@(cd ${.CURDIR}/openvpn; find * -type f) | \
	    xargs -n1 printf "/usr/local/share/openvpn/%s\n"
	@(cd ${.CURDIR}/mobile-broadband-provider-info; find * -type f) | \
	    xargs -n1 printf \
	    "/usr/local/share/mobile-broadband-provider-info/%s\n"

.PHONY: install plist
