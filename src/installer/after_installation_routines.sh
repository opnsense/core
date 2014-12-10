#!/bin/sh

# Copy the current running systems config.xml to the target installation area.
mkdir -p /mnt/cf/conf
cp -r /cf/conf/* /mnt/cf/conf/
touch /mnt/cf/conf/trigger_initial_wizard

# Updating boot loader
echo autoboot_delay=\"3\" >> /mnt/boot/loader.conf
echo vm.kmem_size=\"435544320\"  >> /mnt/boot/loader.conf
echo vm.kmem_size_max=\"535544320\"  >> /mnt/boot/loader.conf

echo kern.ipc.nmbclusters=\"0\" >> /mnt/boot/loader.conf

# Hide usbus# from network interfaces list on pfSense >= 2.1
VERSION=`head -n 1 /mnt/etc/version | cut -c 1-3`; if [ "${VERSION}" != "1.2" -a "${VERSION}" != "2.0" ]; then echo hw.usb.no_pf=\"1\" >> /mnt/boot/loader.conf; fi;

# Set platform back to pfSense to prevent freesbie_1st from running
echo "pfSense" > /mnt/usr/local/etc/platform

# Let parent script know that a install really happened
touch /tmp/install_complete

chmod a-w /mnt/boot/loader.rc
chflags schg /mnt/boot/loader.rc

mkdir -p /mnt/var/installer_logs
cp /tmp/install.disklabel /mnt/var/installer_logs
cp /tmp/install.disklabel* /mnt/var/installer_logs
cp /tmp/installer.log /mnt/var/installer_logs
cp /tmp/install-session.sh /mnt/var/installer_logs
cp /tmp/new.fdisk /mnt/var/installer_logs

mkdir -p /mnt/var/db/pkg
cd /var/db/pkg ; tar -cpf - . | (cd /mnt/var/db/pkg ; tar -xpf -)

# If the platform is vmware, lets do some fixups.
if [ -f /var/IS_VMWARE ]; then echo "" >> /mnt/etc/sysctl.conf; echo "kern.timecounter.hardware=i8254" >> /mnt/etc/sysctl.conf;  echo kern.hz="100" >> /mnt/boot/loader.conf; fi;

# Fixup permissions on installed files
if [ -f /etc/installed_filesystem.mtree ]; then /usr/sbin/mtree -U -e -q -f /etc/installed_filesystem.mtree -p /mnt/ > /mnt/conf/mtree.log; fi;

#Sync disks
/bin/sync
