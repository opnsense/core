<?php

/*
	Copyright (C) 2015 Franco Fichtner <franco@opnsense.org>
	Ported from config.inc by Erik Kristensen
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

/****f* config/encrypted_configxml
 * NAME
 *   encrypted_configxml - Checks to see if config.xml is encrypted and if so, prompts to unlock.
 * INPUTS
 *   None
 * RESULT
 *   $config	- rewrites config.xml without encryption
 ******/
function encrypted_configxml()
{
	global $g, $config;

	$config_xml = '/conf/config.xml';

	if (!file_exists($config_xml)) {
		return;
	}

	if (!file_exists('/var/run/booting')) {
		return;
	}

	$configtxt = file_get_contents($config_xml);
	if(tagfile_deformat($configtxt, $configtxt, 'config.xml')) {
		$fp = fopen('php://stdin', 'r');
		$data = "";
		echo "\n\n*** Encrypted config.xml detected ***\n";
		while($data == "") {
			echo "\nEnter the password to decrypt config.xml: ";
			$decrypt_password = chop(fgets($fp));
			$data = decrypt_data($configtxt, $decrypt_password);
			if (!strstr($data, '<pfsense>')) {
				$data = '';
			}
			if($data) {
				$fd = fopen($config_xml . '.tmp', 'w');
				fwrite($fd, $data);
				fclose($fd);
				exec("/bin/mv {$config_xml}.tmp {$config_xml}");
				echo "\n" . gettext("Config.xml unlocked.") . "\n";
				fclose($fp);
			} else {
				echo "\n" . gettext("Invalid password entered.  Please try again.") . "\n";
			}
		}
	}
}

/****f* config/parse_config
 * NAME
 *   parse_config - Read in config.cache or config.xml if needed and return $config array
 * INPUTS
 *   $parse       - boolean to force parse_config() to read config.xml and generate config.cache
 * RESULT
 *   $config      - array containing all configuration variables
 ******/
function parse_config($parse = false)
{
	global $g, $config_parsed;

	$config_xml = '/conf/config.xml';
	$lockkey = lock('config');
	$config_parsed = false;

	if (!file_exists($config_xml) || filesize($config_xml) == 0) {
		$last_backup = discover_last_backup();
		if ($last_backup) {
			log_error(gettext("No config.xml found, attempting last known config restore."));
			file_notice("config.xml", gettext("No config.xml found, attempting last known config restore."), "pfSenseConfigurator", "");
			restore_backup("/conf/backup/{$last_backup}");
		} else {
			log_error(gettext('No config.xml found, attempting to restore factory config.'));
			restore_backup('/usr/local/etc/config.xml');
		}
	}


	// Check for encrypted config.xml
	encrypted_configxml();

	if (!$parse) {
		if (file_exists('/tmp/config.cache')) {
			$config = unserialize(file_get_contents('/tmp/config.cache'));
			if (is_null($config))
				$parse = true;
		} else
			$parse = true;
	}
	if ($parse == true) {
		if (!file_exists($config_xml)) {
			log_error("No config.xml found, attempting last known config restore.");
			file_notice("config.xml", "No config.xml found, attempting last known config restore.", "pfSenseConfigurator", "");
			$last_backup = discover_last_backup();
			if ($last_backup) {
				restore_backup("/conf/backup/{$last_backup}");
			} else {
				log_error(gettext('No config.xml found, attempting to restore factory config.'));
				restore_backup('/usr/local/etc/config.xml');
			}
		}
		$config = parse_xml_config($config_xml, array($g['xml_rootobj'], 'pfsense'));
		if ($config == -1) {
			$last_backup = discover_last_backup();
			if ($last_backup)
				restore_backup("/conf/backup/{$last_backup}");
			else {
				log_error(gettext('No config.xml found, attempting to restore factory config.'));
				restore_backup('/usr/local/etc/config.xml');
			}
		}
		generate_config_cache($config);
	}


	$config_parsed = true;
	unlock($lockkey);

	alias_make_table($config);

	return $config;
}

function generate_config_cache($config)
{
	$configcache = fopen('/tmp/config.cache', 'w');
	fwrite($configcache, serialize($config));
	fclose($configcache);
	unset($configcache);
}

function discover_last_backup()
{
	$backups = glob('/conf/backup/*.xml');
	$last_backup = '';
	$last_mtime = 0;

	foreach($backups as $backup) {
		if (filemtime($backup) > $last_mtime) {
			$last_mtime = filemtime($backup);
			$last_backup = $backup;
		}
	}

	return basename($last_backup);
}

function restore_backup($file)
{
	global $g;

	if (file_exists($file)) {
		conf_mount_rw();
		@unlink('/tmp/config.cache');
		copy($file, '/conf/config.xml');
		disable_security_checks();
		log_error(sprintf(gettext('%1$s is restoring the configuration %2$s'), $g['product_name'], $file));
		file_notice("config.xml", sprintf(gettext('%1$s is restoring the configuration %2$s'), $g['product_name'], $file), "pfSenseConfigurator", "");
		conf_mount_ro();
	}
}

/****f* config/parse_config_bootup
 * NAME
 *   parse_config_bootup - Bootup-specific configuration checks.
 * RESULT
 *   null
 ******/
function parse_config_bootup()
{
	global $config, $g;

	$config_xml = '/conf/config.xml';

	$lockkey = lock('config');
	if (!file_exists($config_xml)) {
		if (file_exists("/var/run/booting")) {
			if (strstr($g['platform'], "cdrom")) {
				/* try copying the default config. to the floppy */
				echo gettext("Resetting factory defaults...") . "\n";
				reset_factory_defaults(true);
				if (!file_exists($config_xml)) {
					echo gettext("No XML configuration file found - using factory defaults.\n" .
								 "Make sure that the configuration floppy disk with the conf/config.xml\n" .
								 "file is inserted. If it isn't, your configuration changes will be lost\n" .
								 "on reboot.\n");
				}
			} else {
				$last_backup = discover_last_backup();
				if($last_backup) {
					log_error("No config.xml found, attempting last known config restore.");
					file_notice("config.xml", gettext("No config.xml found, attempting last known config restore."), "pfSenseConfigurator", "");
					restore_backup("/conf/backup/{$last_backup}");
				}
				if (!file_exists($config_xml)) {
					echo sprintf(gettext("XML configuration file not found.  %s cannot continue booting."), $g['product_name']) . "\n";
					unlock($lockkey);
					mwexec("/sbin/halt");
					exit;
				}
				log_error("Last known config found and restored.  Please double check your configuration file for accuracy.");
				file_notice("config.xml", gettext("Last known config found and restored.  Please double check your configuration file for accuracy."), "pfSenseConfigurator", "");
			}
		} else {
			unlock($lockkey);
			log_error(gettext("Could not find a usable configuration file! Exiting...."));
			exit(0);
		}
	}

	if (filesize($config_xml) == 0) {
		$last_backup = discover_last_backup();
		if ($last_backup) {
			log_error(gettext("No config.xml found, attempting last known config restore."));
			file_notice("config.xml", gettext("No config.xml found, attempting last known config restore."), "pfSenseConfigurator", "");
			restore_backup("/conf/backup/{$last_backup}");
		} else {
			log_error(gettext('No config.xml found, attempting to restore factory config.'));
			restore_backup('/usr/local/etc/config.xml');
		}
	}
	unlock($lockkey);

	$config = parse_config(true);

	if ((float)$config['version'] > (float)$g['latest_config']) {
		echo <<<EOD


*******************************************************************************
* WARNING!                                                                    *
* The current configuration has been created with a newer version of {$g['product_name']}  *
* than this one! This can lead to serious misbehavior and even security       *
* holes! You are urged to either upgrade to a newer version of {$g['product_name']} or     *
* revert to the default configuration immediately!                            *
*******************************************************************************


EOD;
		}

	/* make alias table (for faster lookups) */
	alias_make_table($config);
}

/****f* config/conf_mount_rw
 * NAME
 *   conf_mount_rw - Mount filesystems read/write.
 * RESULT
 *   null
 ******/
/* mount flash card read/write */
function conf_mount_rw() {
	global $g, $config;

	/* do not mount on cdrom platform */
	if($g['platform'] == "cdrom" or $g['platform'] == "pfSense")
		return;

	if ((refcount_reference(1000) > 1) && is_writable("/"))
		return;

	$status = mwexec("/sbin/mount -u -w -o sync,noatime {$g['cf_path']}");
	if($status <> 0) {
		if(file_exists("/var/run/booting"))
			echo gettext("Disk is dirty.  Running fsck -y") . "\n";
		mwexec("/sbin/fsck -y {$g['cf_path']}");
		$status = mwexec("/sbin/mount -u -w -o sync,noatime {$g['cf_path']}");
	}

	/*    if the platform is soekris or wrap or pfSense, lets mount the
	 *    compact flash cards root.
         */
	$status = mwexec("/sbin/mount -u -w -o sync,noatime /");
	/* we could not mount this correctly.  kick off fsck */
	if($status <> 0) {
		log_error(gettext("File system is dirty.  Launching FSCK for /"));
		mwexec("/sbin/fsck -y /");
		$status = mwexec("/sbin/mount -u -w -o sync,noatime /");
	}

	mark_subsystem_dirty('mount');
}

/****f* config/conf_mount_ro
 * NAME
 *   conf_mount_ro - Mount filesystems readonly.
 * RESULT
 *   null
 ******/
function conf_mount_ro() {
	global $g, $config;

	/* Do not trust $g['platform'] since this can be clobbered during factory reset. */
	$platform = trim(file_get_contents("/usr/local/etc/platform"));
	/* do not umount on cdrom or pfSense platforms */
	if($platform == "cdrom" or $platform == "pfSense")
		return;

	if (refcount_unreference(1000) > 0)
		return;

	if(isset($config['system']['nanobsd_force_rw']))
		return;

	if(file_exists("/var/run/booting"))
		return;

	clear_subsystem_dirty('mount');
	/* sync data, then force a remount of /cf */
	pfSense_sync();
	mwexec("/sbin/mount -u -r -f -o sync,noatime {$g['cf_path']}");
	mwexec("/sbin/mount -u -r -f -o sync,noatime /");
}

/****f* config/convert_config
 * NAME
 *   convert_config - Attempt to update config.xml.
 * DESCRIPTION
 *   convert_config() reads the current global configuration
 *   and attempts to convert it to conform to the latest
 *   config.xml version. This allows major formatting changes
 *   to be made with a minimum of breakage.
 * RESULT
 *   null
 ******/
/* convert configuration, if necessary */
function convert_config() {
	global $config, $g;
	$now = date("H:i:s");
	log_error(sprintf(gettext("Start Configuration upgrade at %s, set execution timeout to 15 minutes"), $now));
	//ini_set("max_execution_time", "900");

	/* special case upgrades */
	/* fix every minute crontab bogons entry */
	if (is_array($config['cron'])) {
		$cron_item_count = count($config['cron']['item']);
		for($x=0; $x<$cron_item_count; $x++) {
			if(stristr($config['cron']['item'][$x]['command'], 'rc.update_bogons')) {
				if($config['cron']['item'][$x]['hour'] == "*" ) {
				$config['cron']['item'][$x]['hour'] = "3";
					write_config(gettext("Updated bogon update frequency to 3am"));
					log_error(gettext("Updated bogon update frequency to 3am"));
				}
			}
		}
	}
	if ($config['version'] == $g['latest_config'])
		return;		/* already at latest version */

	// Save off config version
	$prev_version = $config['version'];

	include_once('auth.inc');
	include_once('upgrade_config.inc');

	/* Loop and run upgrade_VER_to_VER() until we're at current version */
	while ($config['version'] < $g['latest_config']) {
		$cur = $config['version'] * 10;
		$next = $cur + 1;
		$migration_function = sprintf('upgrade_%03d_to_%03d', $cur, $next);
		if (function_exists($migration_function)) {
			$migration_function();
		}
		$config['version'] = sprintf('%.1f', $next / 10);
	}

	$now = date("H:i:s");
	log_error(sprintf(gettext("Ended Configuration upgrade at %s"), $now));

	if ($prev_version != $config['version'])
		write_config(sprintf(gettext('Upgraded config version level from %1$s to %2$s'), $prev_version, $config['version']));
}

/****f* config/safe_write_file
 * NAME
 *   safe_write_file - Write a file out atomically
 * DESCRIPTION
 *   safe_write_file() Writes a file out atomically by first writing to a
 *   temporary file of the same name but ending with the pid of the current
 *   process, them renaming the temporary file over the original.
 * INPUTS
 *   $filename  - string containing the filename of the file to write
 *   $content   - string containing the file content to write to file
 *   $force_binary      - boolean denoting whether we should force binary
 *   mode writing.
 * RESULT
 *   boolean - true if successful, false if not
 ******/
function safe_write_file($file, $content, $force_binary) {
	$tmp_file = $file . "." . getmypid();
	$write_mode = $force_binary ? "wb" : "w";

	$fd = fopen($tmp_file, $write_mode);
	if (!$fd) {
		// Unable to open temporary file for writing
		return false;
        }
	if (!fwrite($fd, $content)) {
		// Unable to write to temporary file
		fclose($fd);
		return false;
	}
	fflush($fd);
	fclose($fd);

	if (!rename($tmp_file, $file)) {
		// Unable to move temporary file to original
		@unlink($tmp_file);
		return false;
	}

	// Sync file before returning
	pfSense_sync();

	return true;
}

/****f* config/write_config
 * NAME
 *   write_config - Backup and write the firewall configuration.
 * DESCRIPTION
 *   write_config() handles backing up the current configuration,
 *   applying changes, and regenerating the configuration cache.
 * INPUTS
 *   $desc	- string containing the a description of configuration changes
 *   $backup	- boolean: do not back up current configuration if false.
 * RESULT
 *   null
 ******/
/* save the system configuration */
function write_config($desc = 'Unknown', $backup = true)
{
	global $config, $g;

	$config_xml = '/conf/config.xml';

	if (!empty($_SERVER['REMOTE_ADDR'])) {
		if (!session_id()) {
			@session_start();
		}
		if (!empty($_SESSION['Username']) && ($_SESSION['Username'] != 'root')) {
			$user = getUserEntry($_SESSION['Username']);
			if (is_array($user) && userHasPrivilege($user, "user-config-readonly")) {
				session_write_close();
				return false;
			}
		}
	}

	if (!isset($argc)) {
		session_write_close();
	}

	if ($backup) {
		backup_config();
	}

	$config['revision'] = make_config_revision_entry($desc);

	conf_mount_rw();
	$lockkey = lock('config', LOCK_EX);

	/* generate configuration XML */
	$xmlconfig = dump_xml_config($config, $g['xml_rootobj']);

	/* write new configuration */
	if (!safe_write_file('/conf/config.xml', $xmlconfig, false)) {
		log_error(gettext("WARNING: Config contents could not be save. Could not open file!"));
		unlock($lockkey);
		file_notice('config.xml', sprintf("%s\n", gettext('Unable to open /conf/config.xml for writing in write_config()')));
		return -1;
	}

	cleanup_backupcache(true);

	/* re-read configuration */
	/* NOTE: We assume that the file can be parsed since we wrote it. */
	$config = parse_xml_config($config_xml, $g['xml_rootobj']);
	if ($config == -1) {
		copy($config_xml, $config_xml . '.bad');
		$last_backup = discover_last_backup();
		if ($last_backup) {
			restore_backup("/conf/backup/{$last_backup}");
			$config = parse_xml_config($config_xml, $g['xml_rootobj']);
			if (file_exists("/var/run/booting")) {
				echo "\n\n ************** WARNING **************";
				echo "\n\n Configuration could not be validated. A previous configuration was restored. \n";
				echo "\n The failed configuration file has been saved as {$config_xml}.bad \n\n";
			}
		} else
			log_error(gettext("Could not restore config.xml."));
	} else
		generate_config_cache($config);

	unlock($lockkey);

	unlink_if_exists("/usr/local/pkg/pf/carp_sync_client.php");

	/* tell kernel to sync fs data */
	conf_mount_ro();

	/* sync carp entries to other firewalls */
	carp_sync_client();

	if(is_dir("/usr/local/pkg/write_config")) {
		/* process packager manager custom rules */
		run_plugins("/usr/local/pkg/write_config/");
	}

	return $config;
}

/****f* config/reset_factory_defaults
 * NAME
 *   reset_factory_defaults - Reset the system to its default configuration.
 * RESULT
 *   integer	- indicates completion
 ******/
function reset_factory_defaults($lock = false)
{
	global $g;

	conf_mount_rw();
	if (!$lock)
		$lockkey = lock('config', LOCK_EX);

	/* create conf directory, if necessary */
	safe_mkdir('/conf');

	/* clear out /conf */
	$dh = opendir('/conf');
	while ($filename = readdir($dh)) {
		if (($filename != '.') && ($filename != '..')) {
			unlink_if_exists('/conf/' . $filename);
		}
	}
	closedir($dh);
	unlink_if_exists('/tmp/config.cache');

	/* copy default configuration */
	copy('/usr/local/etc/config.xml', '/conf/config.xml');

	disable_security_checks();

	/* call the wizard */
	touch("/conf/trigger_initial_wizard");
	if (!$lock)
		unlock($lockkey);
	conf_mount_ro();
	setup_serial_port();
	return 0;
}

function config_restore($conffile) {
	global $config, $g;

	if (!file_exists($conffile))
		return 1;

	backup_config();

	conf_mount_rw();

	$lockkey = lock('config', LOCK_EX);

	unlink_if_exists('/tmp/config.cache');
	copy($conffile, '/conf/config.xml');

	disable_security_checks();

	unlock($lockkey);

	$config = parse_config(true);

	conf_mount_ro();

	write_config(gettext("Reverted to") . " " . array_pop(explode("/", $conffile)) . ".", false);

	return 0;
}

function config_install($conffile)
{
	if (!file_exists($conffile)) {
		return 1;
	}

	if (!config_validate($conffile)) {
		return 1;
	}

	if (file_exists('/var/run/booting')) {
		echo gettext("Installing configuration...") . "\n";
	} else {
		log_error(gettext("Installing configuration ...."));
	}

	conf_mount_rw();
	$lockkey = lock('config', LOCK_EX);

	copy($conffile, '/conf/config.xml');
	disable_security_checks();
	@unlink('/tmp/config.cache');

	unlock($lockkey);
	conf_mount_ro();

	return 0;
}

/*
 * Disable security checks for DNS rebind and HTTP referrer until next time
 * they pass (or reboot), to aid in preventing accidental lockout when
 * restoring settings like hostname, domain, IP addresses, and settings
 * related to the DNS rebind and HTTP referrer checks.
 * Intended for use when restoring a configuration or directly
 * modifying config.xml without an unconditional reboot.
 */
function disable_security_checks()
{
	touch('/tmp/disable_security_checks');
}

/* Restores security checks.  Should be called after all succeed. */
function restore_security_checks()
{
	@unlink('/tmp/disable_security_checks');
}

/* Returns status of security check temporary disable. */
function security_checks_disabled()
{
	return file_exists('/tmp/disable_security_checks');
}

function config_validate($conffile) {

	global $g, $xmlerr;

	$xml_parser = xml_parser_create();

	if (!($fp = fopen($conffile, "r"))) {
		$xmlerr = gettext("XML error: unable to open file");
		return false;
	}

	while ($data = fread($fp, 4096)) {
		if (!xml_parse($xml_parser, $data, feof($fp))) {
			$xmlerr = sprintf(gettext('%1$s at line %2$d'),
						xml_error_string(xml_get_error_code($xml_parser)),
						xml_get_current_line_number($xml_parser));
			return false;
		}
	}
	xml_parser_free($xml_parser);

	fclose($fp);

	return true;
}

function cleanup_backupcache($lock = false) {
	global $g;
	$i = false;

	$revisions = get_config_backup_count();

	if (!$lock)
		$lockkey = lock('config');

	conf_mount_rw();

	$backups = get_backups();
	if ($backups) {
		$baktimes = $backups['versions'];
		unset($backups['versions']);
	} else {
		$backups = array();
		$baktimes = array();
	}
	$newbaks = array();
	$bakfiles = glob('/conf/backup/config-*');
	$tocache = array();

	foreach($bakfiles as $backup) { // Check for backups in the directory not represented in the cache.
		$backupsize = filesize($backup);
		if($backupsize == 0) {
			unlink($backup);
			continue;
		}
		$tocheck = array_shift(explode('.', array_pop(explode('-', $backup))));
		if(!in_array($tocheck, $baktimes)) {
			$i = true;
			$newxml = parse_xml_config($backup, array($g['xml_rootobj'], 'pfsense'));
			if($newxml == "-1") {
				log_error(sprintf(gettext("The backup cache file %s is corrupted.  Unlinking."), $backup));
				unlink($backup);
				log_error(sprintf(gettext("The backup cache file %s is corrupted.  Unlinking."), $backup));
				continue;
			}
			if($newxml['revision']['description'] == "")
				$newxml['revision']['description'] = "Unknown";
			if($newxml['version'] == "")
				$newxml['version'] = "?";
			$tocache[$tocheck] = array('description' => $newxml['revision']['description'], 'version' => $newxml['version'], 'filesize' => $backupsize);
		}
	}
	foreach($backups as $checkbak) {
		if(count(preg_grep('/' . $checkbak['time'] . '/i', $bakfiles)) != 0) {
			$newbaks[] = $checkbak;
		} else {
			$i = true;
			if(file_exists("/var/run/booting")) print " " . $tocheck . "r";
		}
	}
	foreach($newbaks as $todo) $tocache[$todo['time']] = array('description' => $todo['description'], 'version' => $todo['version'], 'filesize' => $todo['filesize']);
	if(is_int($revisions) and (count($tocache) > $revisions)) {
		$toslice = array_slice(array_keys($tocache), 0, $revisions);
		foreach($toslice as $sliced)
			$newcache[$sliced] = $tocache[$sliced];
		foreach($tocache as $version => $versioninfo) {
			if(!in_array($version, array_keys($newcache))) {
				unlink_if_exists('/conf/backup/config-' . $version . '.xml');
			}
		}
		$tocache = $newcache;
	}
	$bakout = fopen('/conf/backup/backup.cache', 'w');
	fwrite($bakout, serialize($tocache));
	fclose($bakout);
	conf_mount_ro();

	if (!$lock)
		unlock($lockkey);
}

function get_backups()
{
	if (file_exists('/conf/backup/backup.cache')) {
		$confvers = unserialize(file_get_contents('/conf/backup/backup.cache'));
		$bakvers = array_keys($confvers);
		$toreturn = array();
		sort($bakvers);
		//	$bakvers = array_reverse($bakvers);
		foreach(array_reverse($bakvers) as $bakver)
			$toreturn[] = array('time' => $bakver, 'description' => $confvers[$bakver]['description'], 'version' => $confvers[$bakver]['version'], 'filesize' => $confvers[$bakver]['filesize']);
	} else {
		return false;
	}
	$toreturn['versions'] = $bakvers;
	return $toreturn;
}

function backup_config()
{
	conf_mount_rw();

	/* Create backup directory if needed */
	safe_mkdir('/conf/backup');

	if ($config['revision']['time'] == '') {
		$baktime = 0;
	} else {
		$baktime = $config['revision']['time'];
	}

	if ($config['revision']['description'] == '') {
		$bakdesc = 'Unknown';
	} else {
		$bakdesc = $config['revision']['description'];
	}

	$bakver = ($config['version'] == '') ? '?' : $config['version'];
	$bakfilename = '/conf/backup/config-' . $baktime . '.xml';
	copy('/conf/config.xml', $bakfilename);

	if (file_exists('/conf/backup/backup.cache')) {
		$backupcache = unserialize(file_get_contents('/conf/backup/backup.cache'));
	} else {
		$backupcache = array();
	}

	$backupcache[$baktime] = array('description' => $bakdesc, 'version' => $bakver, 'filesize' => filesize($bakfilename));
	$bakout = fopen('/conf/backup/backup.cache', 'w');
	fwrite($bakout, serialize($backupcache));
	fclose($bakout);

	conf_mount_ro();

	return true;
}

function set_device_perms() {
	$devices = array(
		'pf'	=> array(	'user'	=> 'root',
					'group'	=> 'proxy',
					'mode'	=> 0660),
		);

	foreach ($devices as $name => $attr) {
		$path = "/dev/$name";
		if (file_exists($path)) {
			chown($path, $attr['user']);
			chgrp($path, $attr['group']);
			chmod($path, $attr['mode']);
		}
	}
}

function get_config_user() {
	if (empty($_SESSION["Username"])) {
		$username = getenv("USER");
		if (empty($conuser) || $conuser == "root")
			$username = "(system)";
	} else
		$username = $_SESSION["Username"];

	if (!empty($_SERVER['REMOTE_ADDR']))
		$username .= '@' . $_SERVER['REMOTE_ADDR'];

	return $username;
}

function make_config_revision_entry($desc = null, $override_user = null) {
	if (empty($override_user))
		$username = get_config_user();
	else
		$username = $override_user;

	$revision = array();

	if (time() > mktime(0, 0, 0, 9, 1, 2004))       /* make sure the clock settings are plausible */
		$revision['time'] = time();

	/* Log the running script so it's not entirely unlogged what changed */
	if ($desc == "Unknown")
		$desc = sprintf(gettext("%s made unknown change"), $_SERVER['SCRIPT_NAME']);
	if (!empty($desc))
		$revision['description'] = "{$username}: " . $desc;
	$revision['username'] = $username;
	return $revision;
}

function get_config_backup_count() {
	global $config, $g;
	if (isset($config['system']['backupcount']) && is_numeric($config['system']['backupcount']) && ($config['system']['backupcount'] >= 0)) {
		return intval($config['system']['backupcount']);
	} elseif ($g['platform'] == "nanobsd") {
		return 5;
	} else {
		return 30;
	}
}

function pfSense_clear_globals() {
	global $config, $FilterIfList, $GatewaysList, $filterdns, $aliases, $aliastable;

	if (isset($FilterIfList))
		unset($FilterIfList);

	if (isset($GatewaysList))
		unset($GatewaysList);

	/* Used for the hostname dns resolver */
	if (isset($filterdns))
		unset($filterdns);

	/* Used for aliases and interface macros */
	if (isset($aliases))
		unset($aliases);
	if (isset($aliastable))
		unset($aliastable);

	unset($config);
}

register_shutdown_function('pfSense_clear_globals');

?>
