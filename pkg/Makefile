all:

install:
	# main pkg(8) glue
	@cp ${.CURDIR}/+PRE_DEINSTALL ${DESTDIR}
	@cp ${.CURDIR}/+POST_INSTALL ${DESTDIR}
	@cp ${.CURDIR}/+MANIFEST ${DESTDIR}
	# create needed root direction
	@mkdir -p ${DESTDIR}/usr/local/etc/pkg
	# available repository configurations
	@cp -r ${.CURDIR}/repos ${DESTDIR}/usr/local/etc/pkg/
	@(cd ${.CURDIR}/repos; find * -type f) | \
	    xargs -n1 printf "/usr/local/etc/pkg/repos/%s\n"
	# available vendor fingerprints
	@cp -r ${.CURDIR}/fingerprints ${DESTDIR}/usr/local/etc/pkg/
	@(cd ${.CURDIR}/fingerprints; find * -type f) | \
	    xargs -n1 printf "/usr/local/etc/pkg/fingerprints/%s\n"

.PHONY: install
