PAGER?=		less

all:
	@cat ${.CURDIR}/README.md | ${PAGER}

force:

mount: force
	@${.CURDIR}/scripts/version.sh > \
	    ${.CURDIR}/src/opnsense/version/opnsense
	/sbin/mount_unionfs ${.CURDIR}/src /usr/local

umount: force
	/sbin/umount -f "<above>:${.CURDIR}/src"

install: force
	# move all sources to their destination
	@mkdir -p ${DESTDIR}/usr/local
	@cp -r ${.CURDIR}/src/* ${DESTDIR}/usr/local
	# invoke pkg(8) bootstraping
	@make -C ${.CURDIR}/pkg install
	# invoke translation glue
	@make -C ${.CURDIR}/lang install
	# invoke third-party tools
	@make -C ${.CURDIR}/contrib install
	# finally pretty-print a list of files present
	@(cd ${.CURDIR}/src; find * -type f) | while read FILE; do \
		if [ $${FILE%%.sample} != $${FILE} ]; then \
			xargs -n1 printf "@sample /usr/local/%s\n"; \
		else \
			xargs -n1 printf "/usr/local/%s\n"; \
		fi; \
	done

lint: force
	find ${.CURDIR}/src ${.CURDIR}/lang/dynamic/helpers \
	    ! -name "*.xml" ! -name "*.eot" \
	    ! -name "*.svg" ! -name "*.woff" ! -name "*.woff2" \
	    ! -name "*.otf" ! -name "*.png" ! -name "*.js" \
	    ! -name "*.scss" ! -name "*.py" ! -name "*.ttf" \
	    ! -name "*.tgz" -type f -print0 | xargs -0 -n1 php -l

sweep: force
	find ${.CURDIR}/src ! -name "*.min.*" ! -name "*.svg" \
	    ! -name "*.map" -type f -print0 | \
	    xargs -0 -n1 scripts/cleanfile
	find ${.CURDIR}/pkg -type f -print0 | \
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
