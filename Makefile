all:

force:

mount: force
	@${.CURDIR}/scripts/version.sh > \
	    ${.CURDIR}/src/opnsense/version/opnsense
	/sbin/mount_unionfs ${.CURDIR}/src /usr/local

umount: force
	/sbin/umount -f "<above>:${.CURDIR}/src"

install: force
	# invoke pkg(8) bootstraping
	@make -C ${.CURDIR}/pkg install
	# move all sources to their destination
	@mkdir -p ${DESTDIR}/usr/local
	@cp -r ${.CURDIR}/src/* ${DESTDIR}/usr/local
	# disable warnings for production systems
	@sed -i '' -e 's/E_STRICT/E_STRICT | E_WARNING/g' \
	    ${DESTDIR}/usr/local/etc/rc.php_ini_setup
	# finally pretty-print a list of files present
	@(cd ${.CURDIR}/src; find * -type f \
	    ! -name "*.po" ! -name "*.pot") | \
	    xargs -n1 printf "/usr/local/%s\n"

lint: force
	find ${.CURDIR}/src ! -name "*.xml" ! -name "*.eot" \
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

# translation glue
XGETTEXT=	xgettext -L PHP --from-code=UTF-8 -F --strict --debug
MSGFMT=		msgfmt --strict
LOCALEDIR=	${.CURDIR}/src/share/locale
POT=		${LOCALEDIR}/en_US/LC_MESSAGES/OPNsense.pot
PO!=		ls ${LOCALEDIR}/*/LC_MESSAGES/OPNsense.po

.SUFFIXES:	.po .mo

.po.mo: force
	${MSGFMT} -o ${.TARGET} ${.IMPSRC}

bootstrap: ${PO:S/.po/.mo/g}

translate: force
	@: > ${POT}
	scripts/translate/collect.py
	find src | xargs ${XGETTEXT} -j -o ${POT}

clean:
	git reset --hard HEAD && git clean -xdqf .

.PHONY: force
