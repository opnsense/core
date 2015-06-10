all:

install:
	@cp ${.CURDIR}/+PRE_DEINSTALL ${DESTDIR}
	@cp ${.CURDIR}/+POST_INSTALL ${DESTDIR}
	@cp ${.CURDIR}/+MANIFEST ${DESTDIR}
	@mkdir -p ${DESTDIR}/usr/local/etc/pkg/repos
	@cp ${.CURDIR}/origin.conf.sample ${DESTDIR}/usr/local/etc/pkg/repos
	@echo "@sample /usr/local/etc/pkg/repos/origin.conf.sample"
	@cp ${.CURDIR}/pkg.conf ${DESTDIR}/usr/local/etc
	@echo /usr/local/etc/pkg.conf
	@cp ${.CURDIR}/config.xml.sample ${DESTDIR}/usr/local/etc
	@echo "@sample /usr/local/etc/config.xml.sample"
	# XXX needs generic approach to allow custom fingerprints
	@mkdir -p ${DESTDIR}/usr/local/etc/pkg/fingerprints/OPNsense/trusted
	@cp ${.CURDIR}/trusted/pkg.opnsense.org.20150402 \
	    ${DESTDIR}/usr/local/etc/pkg/fingerprints/OPNsense/trusted
	@echo /usr/local/etc/pkg/fingerprints/OPNsense/trusted/pkg.opnsense.org.20150402

.PHONY: install
