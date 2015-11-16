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

require_once("globals.inc");
require_once("xmlparse.inc");
require_once("crypt.inc");
require_once("notices.inc");
require_once("legacy_bindings.inc");
require_once('upgrade_config.inc');
require_once("certs.inc");

/* make a global alias table (for faster lookups) */
function alias_make_table($config)
{
	global $aliastable;

	$aliastable = array();

	if (isset($config['aliases']['alias'])) {
		foreach ($config['aliases']['alias'] as $alias) {
			if ($alias['name']) {
				$aliastable[$alias['name']] = isset($alias['address']) ? $alias['address'] : null;
			}
		}
	}
}

/****f* config/parse_config
 * NAME
 *   parse_config - Read in config.xml if needed and return $config array
 * RESULT
 *   $config      - array containing all configuration variables
 ******/
function parse_config()
{
	$cnf = OPNsense\Core\Config::getInstance();
	if (!$cnf->isValid()) {
		// there was an issue with loading the config, try to restore the last backup
		$backups = $cnf->getBackups();
		if (count($backups) > 0) {
			// load last backup
			syslog(LOG_ERR,	gettext('No valid config.xml found, attempting last known config restore.'));
			$cnf->restoreBackup($backups[0]);
		} else {
			// we don't have backups, try to load the default
			syslog(LOG_ERR, gettext('No valid config.xml found, attempting to restore factory config.'));
			$cnf->restoreBackup('/usr/local/etc/config.xml');
		}
	}

	// return config data as array, use old "listags" construction to mark certain elements as array (even if they're not recurring)
	$config=$cnf->toArray(listtags());

	/* make alias table (for faster lookups) */
	alias_make_table($config);

	return $config;
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
function convert_config()
{
	global $config, $g;

	if (!isset($config['revision'])) {
		/* force a revision tag for proper handling in config history */
		write_config('Factory configuration', false);
	}

	if ($config['version'] == $g['latest_config']) {
		/* already at latest version */
		return;
	}

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

	// Save off config version
	$prev_version = $config['version'];
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

	if ($prev_version != $config['version']) {
		write_config(sprintf(gettext('Upgraded config version level from %s to %s'), $prev_version, $config['version']));
	}
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
	global $config;

	if (!empty($_SERVER['REMOTE_ADDR'])) {
		if (session_status() == PHP_SESSION_NONE) {
			session_start();
		}
		if (!empty($_SESSION['Username']) && ($_SESSION['Username'] != 'root')) {
			$user = getUserEntry($_SESSION['Username']);
			if (is_array($user) && userHasPrivilege($user, "user-config-readonly")) {
				session_write_close();
				// okay, it's not very nice to check permissions here, but let's make it explicit while we do...
				log_error(gettext("WARNING: User")." ".$_SESSION['Username']." ".gettext("may not write config (user-config-readonly set)"));
				return false;
			}
		}
		session_write_close();
	}

	if (!isset($config['version'])) {
		// Examine config.xml, if for some strange reason the content is unexpected : exit directly.
		log_error(gettext("WARNING: Corrupt config!"));
		return -1;
	}

	$cnf = OPNsense\Core\Config::getInstance();
	$cnf->fromArray($config);
	$revision_info = make_config_revision_entry($desc);
	try {
		$cnf->save($revision_info, $backup);
	} catch (OPNsense\Core\ConfigException $e) {
		// write failure
		syslog(LOG_ERR, gettext('WARNING: Config contents could not be saved. Could not open file!'));
		return -1;
	}

	/* sync carp entries to other firewalls */
	if ( isset($config['hasync']['synchronizetoip']) && trim($config['hasync']['synchronizetoip']) != "") {
	    configd_run('filter sync load');
	}

	/* cleanup backups */
	cleanup_backups();

	// on succesfull save, serialize config back to global.
	$config = $cnf->toArray(listtags());
	return $config;

}

/****f* config/reset_factory_defaults
 * NAME
 *   reset_factory_defaults - Reset the system to its default configuration.
 ******/
function reset_factory_defaults($sync = true)
{
	mwexec('/bin/rm -r /conf/*');
	disable_security_checks();
	setup_serial_port(false);

	/* as we go through a special case directly reboot */
	$shutdown_cmd = '/sbin/shutdown -or now';
	if ($sync) {
		mwexec($shutdown_cmd);
	} else {
		mwexec_bg($shutdown_cmd);
	}
}

function config_restore($conffile)
{
	global $config;

	if (!file_exists($conffile))
		return 1;

	$cnf = OPNsense\Core\Config::getInstance();
	$cnf->backup();
	$cnf->restoreBackup($conffile);

	disable_security_checks();

	$config = parse_config();

	write_config(gettext("Reverted to") . " " . array_pop(explode("/", $conffile)) . ".", false);

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

/**
 * remove old backups
 */
function cleanup_backups()
{
	global $config;
	$i = false;

	if (isset($config['system']['backupcount']) && is_numeric($config['system']['backupcount']) && ($config['system']['backupcount'] >= 0)) {
		$revisions = intval($config['system']['backupcount']);
	} else {
		$revisions = 30;
	}

	$cnf = OPNsense\Core\Config::getInstance();

	$cnt=1;
	foreach ($cnf->getBackups() as $filename) {
		if ($cnt > $revisions ) {
			unlink($filename);
		}
		++$cnt ;
	}
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


function make_config_revision_entry($desc = null, $override_user = null)
{
	global $config;

	if (empty($override_user)) {
		if (empty($_SESSION["Username"])) {
			$username = getenv("USER");
			if (empty($conuser) || $conuser == "root")
				$username = "(system)";
		} else {
			$username = $_SESSION["Username"];
		}
		if (!empty($_SERVER['REMOTE_ADDR'])) {
			$username .= '@' . $_SERVER['REMOTE_ADDR'];
		}
	} else {
		$username = $override_user;
	}

	$revision = array();
	$revision['username'] = $username;
	$revision['time'] = microtime(true);
	if ($desc == null || $desc == 'Unknown') {
		$revision['description'] = sprintf(gettext("%s made unknown change"), $_SERVER['SCRIPT_NAME']);
	} else {
		$revision['description'] = $desc;
	}

	return $revision;
}

/**
 * backup config to google drive and return current file list (/ info)
 *
 */
function backup_to_google_drive()
{
	$cnf = OPNsense\Core\Config::getInstance();
	if ($cnf->isValid()) {
		$config = $cnf->object();
		if (isset($config->system->remotebackup) && isset($config->system->remotebackup->GDriveEnabled) && $config->system->remotebackup->GDriveEnabled == "on") {
			try {
				$client = new Google\API\Drive();
				$client->login($config->system->remotebackup->GDriveEmail->__toString(),
					$config->system->remotebackup->GDriveP12key->__toString());
			} catch (Exception $e) {
				log_error("error connecting to Google Drive");
				return array();
			}

			// backup source data to local strings (plain/encrypted)
			$confdata = file_get_contents('/conf/config.xml');
			$confdata_enc = encrypt_data($confdata, $config->system->remotebackup->GDrivePassword->__toString());
			tagfile_reformat($confdata_enc, $confdata_enc, "config.xml");


			// read filelist (config-*.xml)
			try {
				$files = $client->listFiles($config->system->remotebackup->GDriveFolderID->__toString());
			} catch (Exception $e) {
				log_error("error while fetching filelist from Google Drive");
				return array();
			}

			$configfiles = array();
			foreach ($files as $file) {
				if (fnmatch("config-*.xml", $file['title'])) {
					$configfiles[$file['title']] = $file;
				}
			}
			krsort($configfiles);


			// backup new file if changed (or if first in backup)
			$target_filename = "config-" . time() . ".xml";
			if (count($configfiles) > 1) {
				// compare last backup with current, only save new
				$bck_data_enc_in = $client->download($configfiles[array_keys($configfiles)[0]]);
				$bck_data_enc = "";
				tagfile_deformat($bck_data_enc_in, $bck_data_enc, "config.xml");
				$bck_data = decrypt_data($bck_data_enc, $config->system->remotebackup->GDrivePassword->__toString());
				if ($bck_data == $confdata) {
					$target_filename = null;
				}
			}
			if (!is_null($target_filename)) {
				log_error("backup configuration as " . $target_filename);
				$configfiles[$target_filename] = $client->upload($config->system->remotebackup->GDriveFolderID->__toString(),
					$target_filename, $confdata_enc);
				krsort($configfiles);
			}

			// cleanup old files
			if (isset($config->system->remotebackup->GDriveBackupCount) && is_numeric($config->system->remotebackup->GDriveBackupCount->__toString())) {
				$fcount = 0;
				foreach ($configfiles as $filename => $file) {
					if ($fcount >= $config->system->remotebackup->GDriveBackupCount->__toString()) {
						log_error("remove " . $filename . " from Google Drive");
						try {
							$client->delete($file);
						} catch (Google_Service_Exception $e) {
							log_error("unable to remove " . $filename . " from Google Drive");
						}
					}
					$fcount++;
				}
			}

			// return filelist
			return $configfiles;
		}
	}

	// not configured / issue, return empty list
	return array();
}
