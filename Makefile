all:

mount:
	/sbin/mount_unionfs ${.CURDIR}/src /usr/local

umount:
	/sbin/umount -f "<above>:${.CURDIR}/src"

install:
	# hardcode package meta files to catch mishaps
	@cp ${.CURDIR}/pkg/+PRE_DEINSTALL ${DESTDIR}
	@cp ${.CURDIR}/pkg/+POST_INSTALL ${DESTDIR}
	@cp ${.CURDIR}/pkg/+MANIFEST ${DESTDIR}
	# move all sources to their destination
	@mkdir -p ${DESTDIR}/usr/local
	@cp -r ${.CURDIR}/src/* ${DESTDIR}/usr/local
	# bootstrap pkg(8) files that are not in sources
	@mkdir -p ${DESTDIR}/usr/local/etc/pkg/repos
	@cp ${.CURDIR}/pkg/OPNsense.conf ${DESTDIR}/usr/local/etc/pkg/repos
	@echo /usr/local/etc/pkg/repos/OPNsense.conf
	@cp ${.CURDIR}/pkg/pkg.conf ${DESTDIR}/usr/local/etc
	@echo /usr/local/etc/pkg.conf
	# and finally pretty-print a list of files present
	@(cd ${.CURDIR}/src; find * -type f) | \
	    xargs -n1 printf "/usr/local/%s\n"

lint:
	find ${.CURDIR}/src ! -name "*.xml" ! -name "*.eot" \
	    ! -name "*.svg" ! -name "*.woff" ! -name "*.woff2" \
	    ! -name "*.otf" ! -name "*.png" ! -name "*.js" \
	    ! -name "*.scss" ! -name "*.py" ! -name "*.ttf" \
	    ! -name "*.tgz" -type f -print0 | xargs -0 -n1 php -l

sweep:
	find ${.CURDIR}/src ! -name "*.min.*" ! -name "*.svg" \
	    ! -name "*.map" -type f -print0 | \
	    xargs -0 -n1 scripts/cleanfile
	find ${.CURDIR}/pkg -type f -print0 | \
	    xargs -0 -n1 scripts/cleanfile

style:
	@(phpcs --tab-width=4 --standard=PSR2 ${.CURDIR}/src/opnsense/mvc \
	    || true) > ${.CURDIR}/.style.out
	@echo -n "Total number of style warnings: "
	@grep '| WARNING' ${.CURDIR}/.style.out | wc -l
	@echo -n "Total number of style errors:   "
	@grep '| ERROR' ${.CURDIR}/.style.out | wc -l
	@cat ${.CURDIR}/.style.out
	@rm ${.CURDIR}/.style.out

setup:
	${.CURDIR}/src/etc/rc.php_ini_setup

health:
	# check test script output and advertise a failure...
	[ "`${.CURDIR}/src/etc/rc.php_test_run`" == "FCGI-PASSED PASSED" ]

clean:
	git reset --hard HEAD && git clean -xdqf .

.PHONY: mount umount install lint sweep style setup health clean
