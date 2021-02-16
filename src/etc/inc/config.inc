<?php

/*
 * Copyright (C) 2015-2017 Franco Fichtner <franco@opnsense.org>
 * Copyright (C) 2009 Erik Kristensen <erik@erikkristensen.com>
 * Copyright (C) 2004-2010 Scott Ullrich <sullrich@gmail.com>
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

/* XXX make this a getter function for traceability */
$g = json_decode(file_get_contents('/usr/local/opnsense/version/core'), true);

openlog($g['product_id'], LOG_ODELAY, LOG_USER);
register_shutdown_function('closelog');

require_once("xmlparse.inc");
require_once("notices.inc");
require_once("legacy_bindings.inc");
require_once("certs.inc");

/*
 * Hook up the plugin system which consists of several low-profile
 * functions that can be called from within our backend code when
 * they exist.
 */
require_once('plugins.inc');

/**
 * parse config into array and return
 */
function load_config_from_file($filename)
{
    return OPNsense\Core\Config::getInstance()->toArrayFromFile($filename, listtags());
}

/****f* config/parse_config
 * NAME
 *   parse_config - Read in config.xml if needed and return $config array
 * RESULT
 *   $config      - array containing all configuration variables
 ******/
function parse_config()
{
    global $aliastable;

    $cnf = OPNsense\Core\Config::getInstance();

    // return config data as array, use old "listags" construction to mark certain elements as array (even if they're not recurring)
    $config = $cnf->toArray(listtags());

    $aliastable = array();

    foreach ((new \OPNsense\Firewall\Alias())->aliasIterator() as $alias) {
        if (strncmp($alias['type'], 'url', 3) !== 0) {
            $aliastable[$alias['name']] = implode(" ", explode("\n", $alias['content']));
        } else {
            $aliastable[$alias['name']] = "";
        }
    }

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
function convert_config($verbose = false)
{
    global $config;

    if (!isset($config['revision'])) {
        /* force a revision tag for proper handling in config history */
        write_config('Factory configuration', false);
    }

    /* chain the new migration into this function call */
    $cmd = '/usr/local/opnsense/mvc/script/run_migrations.php';
    if ($verbose) {
        passthru($cmd);
    } else {
        mwexecf($cmd);
    }

    /* factory reset on release type and plugins if necessary */
    $cmd = '/usr/local/opnsense/scripts/firmware/register.php resync_factory';
    if ($verbose) {
        passthru($cmd);
    } else {
        mwexecf($cmd);
    }

    /* reload the config as it was rewritten and saved in the script context */
    OPNsense\Core\Config::getInstance()->forceReload();
    $config = parse_config();
}

/****f* config/write_config
 * NAME
 *   write_config - Backup and write the firewall configuration.
 * DESCRIPTION
 *   write_config() handles backing up the current configuration,
 *   applying changes, and regenerating the configuration cache.
 * INPUTS
 *   $desc  - string containing the a description of configuration changes
 *   $backup  - boolean: do not back up current configuration if false.
 * RESULT
 *   null
 ******/
/* save the system configuration */
function write_config($desc = '', $backup = true)
{
    global $config;

    if (!empty($_SERVER['REMOTE_ADDR'])) {
        if (!empty($_SESSION['Username']) && ($_SESSION['Username'] != 'root')) {
            $user = getUserEntry($_SESSION['Username']);
            if (is_array($user) && userHasPrivilege($user, "user-config-readonly")) {
                // okay, it's not very nice to check permissions here, but let's make it explicit while we do...
                log_error("WARNING: User {$_SESSION['Username']} may not write config (user-config-readonly set)");
                return false;
            }
        }
    }

    plugins_interfaces();

    $cnf = OPNsense\Core\Config::getInstance();
    $cnf->fromArray($config);
    $revision_info = make_config_revision_entry($desc);

    try {
        $cnf->save($revision_info, $backup);
    } catch (OPNsense\Core\ConfigException $e) {
        // write failure
        syslog(LOG_ERR, 'WARNING: Config contents could not be saved. Could not open file!');
        return -1;
    }

    /* on succesfull save, serialize config back to global */
    $config = $cnf->toArray(listtags());

    return $config;
}

function config_restore($conffile)
{
    global $config;

    if (!file_exists($conffile)) {
        return 1;
    }

    $cnf = OPNsense\Core\Config::getInstance();
    $cnf->backup();
    $cnf->restoreBackup($conffile);

    disable_security_checks();

    $config = parse_config();

    write_config(sprintf('Reverted to %s', array_pop(explode('/', $conffile))), false);

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

function &config_read_array()
{
    global $config;

    $current = &$config;

    foreach (func_get_args() as $key) {
        if (!isset($current[$key]) || !is_array($current[$key])) {
            $current[$key] = array();
        }
        $current = &$current[$key];
    }

    return $current;
}

function make_config_revision_entry($desc = '')
{
    if (!empty($_SESSION['Username'])) {
        $username = $_SESSION['Username'];
    } else {
        $username = '(' . trim(shell_exec('/usr/bin/whoami')) . ')';
    }

    if (!empty($_SERVER['REMOTE_ADDR'])) {
        $username .= '@' . $_SERVER['REMOTE_ADDR'];
    }

    if (empty($desc)) {
        $desc = sprintf('%s made changes', $_SERVER['SCRIPT_NAME']);
    }

    $revision = array();
    $revision['username'] = $username;
    $revision['time'] = microtime(true);
    $revision['description'] = $desc;

    return $revision;
}

/**
 * find list of registered interfaces
 * @param array $filters list of filters to apply
 * @return array interfaces
 */
function legacy_config_get_interfaces($filters = array())
{
    $interfaces = array();

    foreach (config_read_array('interfaces') as $ifname => $iface) {
        // undo stupid listags() turning our item into a new array, preventing certain names to be used as interface.
        // see src/etc/inc/xmlparse.inc
        if (isset($iface[0])) {
            $iface = $iface[0];
        }

        // apply filters
        $iface_match = true;
        foreach ($filters as $filter_key => $filter_value) {
            if ($filter_key == 'enable' && isset($iface[$filter_key])) {
                $field_value = true;
            } else {
                $field_value = isset($iface[$filter_key]) ? $iface[$filter_key] : false;
            }
            if ($field_value != $filter_value) {
                $iface_match = false;
                break;
            }
        }

        if ($iface_match && !empty($iface)) {
            $iface['descr'] = !empty($iface['descr']) ? $iface['descr'] : strtoupper($ifname);
            $iface['ipaddrv6'] = !empty($iface['ipaddrv6']) ? $iface['ipaddrv6'] : null;
            $iface['ipaddr'] = !empty($iface['ipaddr']) ? $iface['ipaddr'] : null;
            $interfaces[$ifname] = $iface;
        }
    }

    uasort($interfaces, function ($a, $b) {
        return strnatcmp($a['descr'], $b['descr']);
    });

    return $interfaces;
}

/**
 * parse stored json content, return empty when not found or expired
 */
function get_cached_json_content($filename, $ttl = 3600)
{
    $result = null;
    if (file_exists($filename)) {
        $fstat = stat($filename);
        if ((time() - $fstat['mtime']) < $ttl) {
            $result = json_decode(file_get_contents($filename), true);
        }
    }
    return $result;
}

$config = parse_config();

/* set timezone */
$timezone = $config['system']['timezone'];
if (!$timezone) {
    $timezone = 'Etc/UTC';
}

date_default_timezone_set($timezone);
