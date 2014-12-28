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

setup:
	${.CURDIR}/src/etc/rc.php_ini_setup

clean:
	git reset --hard HEAD && git clean -xdqf .

.PHONY: mount umount install lint sweep setup clean
