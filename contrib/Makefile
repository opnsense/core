all:

install:
	# bootstrap needed root directories
	@mkdir -p ${DESTDIR}/usr/local/share
	# openvpn client exporter files
	@cp -r ${.CURDIR}/openvpn ${DESTDIR}/usr/local/share/
	@(cd ${.CURDIR}/openvpn; find * -type f) | \
	    xargs -n1 printf "/usr/local/share/openvpn/%s\n"
	# mobile broadband provider database
	@cp -r ${.CURDIR}/mobile-broadband-provider-info \
	    ${DESTDIR}/usr/local/share/
	@(cd ${.CURDIR}/mobile-broadband-provider-info; find * -type f) | \
	    xargs -n1 printf \
	    "/usr/local/share/mobile-broadband-provider-info/%s\n"

.PHONY: install
