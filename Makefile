all:

mount:
	mount_unionfs ${.CURDIR}/usr/local /usr/local

umount:
	umount -f "<above>:${.CURDIR}/usr/local"

install:
	mkdir -p ${DESTDIR}/usr/local
	cp -r ${.CURDIR}/usr/local/* ${DESTDIR}/usr/local

lint:
	find ${.CURDIR}/usr/local -name "*.class" -print0 | xargs -0 -n1 php -l
	find ${.CURDIR}/usr/local -name "*.inc" -print0 | xargs -0 -n1 php -l
	find ${.CURDIR}/usr/local -name "*.php" -print0 | xargs -0 -n1 php -l

sweep:
	find ${.CURDIR}/usr/local ! -name "*.min.*" \
	    ! -name "*.map" -type f -print0 | \
	    xargs -0 -n1 scripts/cleanfile

setup:
	${.CURDIR}/usr/local/etc/rc.php_ini_setup

clean:
	git reset --hard HEAD && git clean -xdqf .

.PHONY: mount umount install lint sweep setup clean
