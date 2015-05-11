all:

mount:
	@${.CURDIR}/scripts/version.sh > \
	    ${.CURDIR}/src/opnsense/version/opnsense
	/sbin/mount_unionfs ${.CURDIR}/src /usr/local

umount:
	/sbin/umount -f "<above>:${.CURDIR}/src"

install:
	# invoke pkg(8) bootstraping
	@make -C ${.CURDIR}/pkg install
	# move all sources to their destination
	@mkdir -p ${DESTDIR}/usr/local
	@cp -r ${.CURDIR}/src/* ${DESTDIR}/usr/local
	# disable warnings for production systems
	@sed -i '' -e 's/E_STRICT/E_STRICT | E_WARNING/g' \
	    ${DESTDIR}/usr/local/etc/rc.php_ini_setup
	# finally pretty-print a list of files present
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

OPNSENSE_POT=	src/share/locale/en/LC_MESSAGES/OPNsense.pot

translate:
	@: > ${.CURDIR}/${OPNSENSE_POT}
	find src | xargs xgettext -j -L PHP --from-code=UTF-8 -F \
	    --strict --debug -o ${.CURDIR}/${OPNSENSE_POT}

clean:
	git reset --hard HEAD && git clean -xdqf .

.PHONY: mount umount install lint sweep style setup health clean
