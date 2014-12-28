all:

mount:
	mount_unionfs ${.CURDIR}/src /usr/local

umount:
	umount -f "<above>:${.CURDIR}/src"

install:
	@mkdir -p ${DESTDIR}/usr/local
	@cp -r ${.CURDIR}/src/* ${DESTDIR}/usr/local
	@(cd ${.CURDIR}/src; find * -type f) | \
	    xargs -n1 printf "/usr/local/%s\n"

lint:
	find ${.CURDIR}/src -name "*.class" -print0 | xargs -0 -n1 php -l
	find ${.CURDIR}/src -name "*.inc" -print0 | xargs -0 -n1 php -l
	find ${.CURDIR}/src -name "*.php" -print0 | xargs -0 -n1 php -l

sweep:
	find ${.CURDIR}/src/www ! -name "*.min.*" ! -name "*.svg" \
	    ! -name "*.map" -type f -print0 | \
	    xargs -0 -n1 scripts/cleanfile

style:
	@(phpcs --tab-width=4 --standard=PSR2 ${.CURDIR}/src/opnsense \
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

.PHONY: mount umount install lint sweep style setup clean
