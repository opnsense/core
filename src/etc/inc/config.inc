<?php

/*
	Copyright (C) 2004-2010 Scott Ullrich
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/


// Set the memory limit to 128M on i386.  When someone has something like 500+ tunnels
// the parser needs quite a bit of ram.   Do not remove this line unless you
// know what you are doing.  If in doubt, check with dev@ _/FIRST/_!
if(!$ARCH)
	$ARCH = php_uname("m");

// Set memory limit to 256M on amd64.
if($ARCH == "amd64")
	ini_set("memory_limit","256M");
else
	ini_set("memory_limit","128M");

/* include globals from notices.inc /utility/XML parser files */
require_once("notices.inc");
require_once("util.inc");
require_once("IPv6.inc");
require_once('config.lib.inc');
require_once("xmlparse.inc");
require_once("crypt.inc");

/* read platform */
if (file_exists("/usr/local/etc/platform")) {
	$g['platform'] = chop(file_get_contents("/usr/local/etc/platform"));
} else {
	$g['platform'] = "unknown";
}

/* if /debugging exists, lets set $debugging
   so we can output more information */
if(file_exists("/debugging")) {
	$debugging = true;
	$g['debug'] = true;
}

if(file_exists("/var/run/booting") && file_exists("/conf/config.xml")) {
	$config_contents = file_get_contents("/conf/config.xml");
	if(stristr($config_contents, "<m0n0wall>") == true) {
		/* user has just upgraded to m0n0wall, replace root xml tags */
		log_error(gettext("Upgrading m0n0wall configuration to pfSense... "));
		$config_contents = str_replace("m0n0wall","pfsense", $config_contents);
		if (!config_validate('/conf/config.xml'))
			log_error(gettext("ERROR!  Could not convert m0n0wall -> pfsense in config.xml"));
		$fd = fopen("/conf/config.xml", "w");
		fwrite($fd, $config_contents);
		fclose($fd);
	}
}

/* if our config file exists bail out, we're already set. */
else if (file_exists("/var/run/booting") && !file_exists('/conf/config.xml')) {
	/* find the device where config.xml resides and write out an fstab */
	unset($cfgdevice);
	/* check if there's already an fstab (NFS booting?) */
	if (!file_exists('/etc/fstab')) {
		if (strstr($g['platform'], "cdrom")) {
			/* config is on floppy disk for CD-ROM version */
			$cfgdevice = $cfgpartition = "fd0";
			$_gb = exec('/sbin/dmesg -a', $dmesg);
			if(preg_match("/da0/", $dmesg) == true) {
				$cfgdevice = $cfgpartition = "da0" ;
				if (mwexec("/sbin/mount -r /dev/{$cfgdevice} /cf")) {
					/* could not mount, fallback to floppy */
					$cfgdevice = $cfgpartition = "fd0";
				}
			}
			$cfgfstype = "msdosfs";
			echo gettext("CDROM build") . "\n";
			echo "   " . gettext("CFG:") . " {$cfgpartition}\n";
			echo "   " . gettext("CFG:") . " {$cfgpartition}\n";
			echo "  " . gettext("TYPE:") . " {$cfgfstype}\n";
		} else {
			/* probe kernel known disks until we find one with config.xml */
			$disks = explode(" ", get_single_sysctl("kern.disks"));
			foreach ($disks as $mountdisk) {
				/* skip mfs mounted filesystems */
				if (strstr($mountdisk, "md"))
					continue;
				if (mwexec("/sbin/mount -r /dev/{$mountdisk}a /cf") == 0) {
					if (file_exists('/conf/config.xml')) {
						/* found it */
						$cfgdevice = $mountdisk;
						$cfgpartition = $cfgdevice . "a";
						$cfgfstype = "ufs";
						printf(gettext("Found configuration on %s.%s"), $cfgdevice, "\n");
					}

					mwexec("/sbin/umount -f /cf");

					if ($cfgdevice)
						break;
				}
				if (mwexec("/sbin/mount -r /dev/{$mountdisk}d /cf") == 0) {
					if (file_exists('/conf/config.xml')) {
						/* found it */
						$cfgdevice = $mountdisk;
						$cfgpartition = $cfgdevice . "d";
						$cfgfstype = "ufs";
						printf(gettext("Found configuration on %s.%s"), $cfgdevice, "\n");
					}

					mwexec("/sbin/umount -f /cf");

					if ($cfgdevice)
						break;
				}
			}
		}
		if (!$cfgdevice) {
			$last_backup = discover_last_backup();
			if($last_backup) {
				log_error(gettext("No config.xml found, attempting last known config restore."));
				file_notice("config.xml", gettext("No config.xml found, attempting last known config restore."), "pfSenseConfigurator", "");
				restore_backup("/conf/backup/{$last_backup}");
			} else {
				log_error(gettext("No config.xml or config backups found, resetting to factory defaults."));
				restore_backup('/usr/local/etc/config.xml');
			}
		}

		/* write device name to a file for rc.firmware */
		$fd = fopen("{$g['varetc_path']}/cfdevice", "w");
		fwrite($fd, $cfgdevice . "\n");
		fclose($fd);

		/* write out an fstab */
		$fd = fopen('/etc/fstab', 'w');

		$fstab = "/dev/{$cfgpartition} /cf {$cfgfstype} ro,noatime 1 1\n";
		$fstab .= "proc /proc procfs rw 0 0\n";

		fwrite($fd, $fstab);
		fclose($fd);
	}
	/* mount all filesystems */
	mwexec("/sbin/mount -a");
}

$config = parse_config();

/* set timezone */
$timezone = $config['system']['timezone'];
if (!$timezone)
	$timezone = "Etc/UTC";
date_default_timezone_set("$timezone");

if($config_parsed == true) {
	/* process packager manager custom rules */
	if(is_dir("/usr/local/pkg/parse_config")) {
		run_plugins("/usr/local/pkg/parse_config/");
	}
}

?>
