all:

install:
	# main pkg(8) glue
	@cp ${.CURDIR}/+PRE_DEINSTALL ${DESTDIR}
	@cp ${.CURDIR}/+POST_INSTALL ${DESTDIR}
	@cp ${.CURDIR}/+MANIFEST ${DESTDIR}
	# config.xml factory reset file
	@cp ${.CURDIR}/config.xml.sample ${DESTDIR}/usr/local/etc
	@echo "@sample /usr/local/etc/config.xml.sample"
	# main package repository configuration
	@mkdir -p ${DESTDIR}/usr/local/etc/pkg/repos
	@cp ${.CURDIR}/origin.conf.sample ${DESTDIR}/usr/local/etc/pkg/repos
	@echo "@sample /usr/local/etc/pkg/repos/origin.conf.sample"
	# available fingerprints
	@cp -r ${.CURDIR}/fingerprints ${DESTDIR}/usr/local/etc/pkg/
	@(cd ${.CURDIR}/fingerprints; find * -type f) | \
	    xargs -n1 printf "/usr/local/etc/pkg/fingerprints/%s\n"
	# XXX CONSERVATIVE_UPGRADE is not very helpful
	@cp ${.CURDIR}/pkg.conf ${DESTDIR}/usr/local/etc
	@echo /usr/local/etc/pkg.conf

.PHONY: install
