#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2026 Konstantinos Spartalis <cspartalis@potatonetworks.com>
 * Copyright (C) 2023 Deciso B.V.
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

require_once('script/load_phalcon.php');
require_once("config.inc");
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_STRICT & ~E_DEPRECATED);

global $config;
$config = \parse_config();
require_once("util.inc");
require_once("interfaces.inc");
require_once("rrd.inc");
require_once("filter.inc");
require_once("system.inc");
require_once("console.inc");
require_once("auth.inc");

try {

function restore_config_section($section_sets, $new_contents)
{
    require_once("config.inc");
    global $config;

    $tmpxml = tempnam(sys_get_temp_dir(), 'opn_backup_');
    $xml = null;

    try {
        file_put_contents($tmpxml, $new_contents);
        $xml = \load_config_from_file($tmpxml);
    } catch (\Exception $e) {
        syslog(LOG_ERR, 'Backup restoration failed to parse XML: ' . $e->getMessage());
    } finally {
        @unlink($tmpxml);
    }

    if (!is_array($xml)) {
        return false;
    }

    $restored = [];
    $failed = [];

    foreach ($section_sets as $section_set) {
        $sections = explode(',', $section_set);
        $found = [];

        foreach ($sections as $section) {
            $new = &$xml;
            $path = explode('.', $section);
            $target = array_pop($path);

            foreach ($path as $node) {
                if (!isset($new[$node])) {
                    continue 2;
                }
                $new = &$new[$node];
            }

            if (isset($new[$target])) {
                $found[] = $section;
            }
        }

        if (!count($found)) {
            $failed[] = $section_set;
            continue;
        }

        foreach (array_diff($sections, $found) as $section) {
            $old = &$config;
            $path = explode('.', $section);
            $target = array_pop($path);

            foreach ($path as $node) {
                if (!isset($old[$node])) {
                    continue 2;
                }
                $old = &$old[$node];
            }

            if (isset($old[$target])) {
                unset($old[$target]);
                $restored[] = $section;
            }
        }

        foreach ($found as $section) {
            $old = &$config;
            $new = &$xml;
            $path = explode('.', $section);
            $target = array_pop($path);

            foreach ($path as $node) {
                if (!isset($new[$node])) {
                    continue 2;
                }
                $new = &$new[$node];
                if (!isset($old[$node])) {
                    $old[$node] = [];
                }
                $old = &$old[$node];
            }

            if (isset($new[$target])) {
                $old[$target] = $new[$target];
                $restored[] = $section;
            }
        }
    }

    if (count($restored) && !count($failed)) {
        \OPNsense\Core\Config::getInstance()->save(sprintf('Restored sections (%s) of config file', join(',', $restored)));
        \convert_config();
    }

    return $failed;
}

$filename = isset($argv[1]) ? $argv[1] : null;

if (empty($filename) || !file_exists($filename)) {
    echo json_encode(["status" => "failed", "message" => "No valid configuration payload provided."]);
    exit(1);
}

$params = json_decode(file_get_contents($filename), true);

if (empty($params) || !isset($params['conffile']) || !file_exists($params['conffile'])) {
    echo json_encode(["status" => "failed", "message" => "Missing or invalid config extraction parameters."]);
    exit(1);
}

$data = file_get_contents($params['conffile']);
if (empty($data)) {
    echo json_encode(["status" => "failed", "message" => "Could not read uploaded config file."]);
    exit(1);
}

$restoreareas = !empty($params['restorearea']) ? $params['restorearea'] : [];
$do_reboot = !empty($params['rebootafterrestore']);

if (!empty($restoreareas)) {
    $ret = restore_config_section($restoreareas, $data);
    if ($ret === false) {
        echo json_encode(['status' => 'failed', 'message' => gettext('The selected config file could not be parsed.')]);
    } elseif (count($ret)) {
        echo json_encode(['status' => 'failed', 'message' => gettext('At least one requested restore area could not be found.')]);
    } else {
        global $config;
        if (!empty($config['rrddata'])) {
            \rrd_import();
            unset($config['rrddata']);
            \OPNsense\Core\Config::getInstance()->save('Restored configuration area (RRD data imported)');
        }
        if ($do_reboot) {
        }
        echo json_encode(['status' => 'success', 'message' => gettext("The configuration area has been restored."), 'reboot' => $do_reboot]);
    }
} else {
    /* full config restore */
    global $config;
    $cfieldnames = [
        'usevirtualterminal',
        'primaryconsole',
        'secondaryconsole',
        'serialspeed',
        'serialusb',
        'disableconsolemenu'
    ];
    $csettings = [];
    foreach ($cfieldnames as $fieldname) {
        $csettings[$fieldname] = $config['system'][$fieldname] ?? null;
    }

    $cnf = \OPNsense\Core\Config::getInstance();
    $restore_file = '/tmp/config_restore.xml';
    file_put_contents($restore_file, $data);
    if ($cnf->restoreBackup($restore_file)) {
        @unlink($restore_file);
        $config = \parse_config();
        $flush = false;
        if (!empty($params['keepconsole'])) {
            foreach ($csettings as $fieldname => $fieldcontent) {
                if ($fieldcontent === null && isset($config[$fieldname])) {
                    unset($config[$fieldname]);
                } else {
                    $config['system'][$fieldname] = $fieldcontent;
                }
            }
            $flush = true;
        }
        if (!empty($config['rrddata'])) {
            \rrd_import();
            unset($config['rrddata']);
            $flush = true;
        }
        if ($flush) {
            \OPNsense\Core\Config::getInstance()->save('Restored full configuration');
        }
        if (!empty($params['flush_history'])) {
            mwexecf('/usr/local/opnsense/scripts/system/flush_config_history');
            \OPNsense\Core\Config::getInstance()->save('System restore flushed local history');
        }
        if (\is_interface_mismatch(false)) {
            $do_reboot = false;
            echo json_encode(['status' => 'success', 'message' => gettext("The interface configuration was restored but physical interfaces could not be matched. No automatic reboot was performed."), 'reboot' => false]);
            exit(0);
        }
        echo json_encode(['status' => 'success', 'message' => gettext("The configuration has been restored."), 'reboot' => $do_reboot]);
    } else {
        echo json_encode(['status' => 'failed', 'message' => gettext("The configuration could not be restored.")]);
    }
}
} catch (\Throwable $e) {
    echo json_encode(["status" => "failed", "message" => "Fatal Error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine()]);
    exit(0);
}
