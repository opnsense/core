#!/bin/sh

# refresh relevant configuration files
/usr/local/etc/rc.configure_firmware

# a copy of this cleanup exists in 90-cleanup.sh
daemon /bin/sh -s << EOF
# remove our stale pyc files not handled by pkg
find /usr/local/opnsense -type f -name "*.pyc" -delete

for DIR in /boot /usr/libexec/bsdinstall /usr/local; do
	# remove spurious files from pkg
	find \${DIR} ! \( -type d \) -a \
	    \( -name "*.pkgsave" -o -name ".pkgtemp.*" \) -delete

        # processs spurious directories from pkg
        # (may not be empty so -delete does not work)
	find \${DIR} -type d -name ".pkgtemp.*" -print0 | xargs -0 -n1 rm -r
done
EOF
