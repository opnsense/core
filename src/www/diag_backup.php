<?php

/*
    Copyright (C) 2014 Deciso B.V.
    Copyright (C) 2004-2009 Scott Ullrich <sullrich@gmail.com>
    Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
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

require_once("guiconfig.inc");
require_once("interfaces.inc");
require_once("filter.inc");
require_once("services.inc");
require_once("rrd.inc");
require_once("system.inc");

/**
 * restore config section
 * @param string $section_name config section name
 * @param string $new_contents xml content
 * @return bool status
 */
function restore_config_section($section_name, $new_contents)
{
    global $config;

    $tmpxml = '/tmp/tmpxml';

    file_put_contents($tmpxml, $new_contents);
    $xml = load_config_from_file($tmpxml);
    @unlink($tmpxml);

    if (!is_array($xml) || !isset($xml[$section_name])) {
        return false;
    }

    $config[$section_name] = $xml[$section_name];

    write_config(sprintf('Restored section %s of config file', $section_name));
    convert_config();

    disable_security_checks();

    return true;
}

$areas = array(
    'OPNsense' => gettext('OPNsense Additions'),	/* XXX need specifics */
    'aliases' => gettext('Aliases'),
    'bridges' => gettext('Bridge Devices'),
    'ca' => gettext('SSL Certificate Authorities'),
    'cert' => gettext('SSL Certificates'),
    'dhcpd' => gettext('DHCP Server'),
    'dhcpdv6' => gettext('DHCPv6 Server'),
    'dhcrelay' => gettext('DHCP Relay'),
    'dhcrelay6' => gettext('DHCPv6 Relay'),
    'dnsmasq' => gettext('Dnsmasq DNS'),
    'dyndnses' => gettext('Dynamic DNS'),
    'dnsupdates' => gettext('RFC 2136'),
    'filter' => gettext('Firewall Rules'),
    'gateways' => gettext('Gateways'),
    'gifs' => gettext('GIF Devices'),
    'igmpproxy' => gettext('IGMP Proxy'),
    'installedpackages' => gettext('Universal Plug and Play'),	/* XXX only one, reduce depth! */
    'interfaces' => gettext('Interfaces'),
    'ipsec' => gettext('IPsec'),
    'laggs' => gettext('LAGG Devices'),
    'load_balancer' => gettext('Load Balancer'),
    'nat' => gettext('Network Address Translation'),
    'notifications' => gettext('System Notifications'),
    'ntpd' => gettext('Network Time'),
    'opendns' => gettext('DNS Filter'),
    'openvpn' => gettext('OpenVPN'),
    'ppps' => gettext('Point-to-Point Devices'),
    'pptpd' => gettext('PPTP Server'),
    'proxyarp' => gettext('Proxy ARP'),
    'rrddata' => gettext('RRD Data'),
    'snmpd' => gettext('SNMP Server'),
    'staticroutes' => gettext('Static routes'),
    'sysctl' => gettext('System tunables'),
    'syslog' => gettext('Syslog'),
    'system' => gettext('System'),
    'unbound' => gettext('Unbound DNS'),
    'vlans' => gettext('VLAN Devices'),
    'widgets' => gettext('Dashboard Widgets'),
    'wireless' => gettext('Wireless Devices'),
    'wol' => gettext('Wake on LAN'),
);

$do_reboot = false;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();
    $pconfig['GDriveEnabled'] = isset($config['system']['remotebackup']['GDriveEnabled']) ? $config['system']['remotebackup']['GDriveEnabled'] : null;
    $pconfig['GDriveEmail'] = isset($config['system']['remotebackup']['GDriveEmail']) ? $config['system']['remotebackup']['GDriveEmail'] : null;
    $pconfig['GDriveP12key'] = isset($config['system']['remotebackup']['GDriveP12key']) ? $config['system']['remotebackup']['GDriveP12key'] : null;
    $pconfig['GDriveFolderID'] = isset($config['system']['remotebackup']['GDriveFolderID']) ? $config['system']['remotebackup']['GDriveFolderID'] : null;
    $pconfig['GDriveBackupCount'] = isset($config['system']['remotebackup']['GDriveBackupCount']) ? $config['system']['remotebackup']['GDriveBackupCount'] : null;
    $pconfig['GDrivePassword'] = isset($config['system']['remotebackup']['GDrivePassword']) ? $config['system']['remotebackup']['GDrivePassword'] : null;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    $pconfig = $_POST;

    if (!empty($_POST['restore'])) {
        $mode = "restore";
    } elseif (!empty($_POST['download'])) {
        $mode = "download";
    } elseif (!empty($_POST['setup_gdrive'])) {
        $mode = "setup_gdrive";
    } else {
        $mode = false;
    }

    if ($mode == "download") {
        if (!empty($_POST['encrypt']) && (empty($_POST['encrypt_password']) || empty($_POST['encrypt_passconf']))) {
            $input_errors[] = gettext("You must supply and confirm the password for encryption.");
        } elseif (!empty($_POST['encrypt']) && $_POST['encrypt_password'] != $_POST['encrypt_passconf']) {
            $input_errors[] = gettext("The supplied 'Password' and 'Confirm' field values must match.");
        }
        if (count($input_errors) == 0) {
            $host = "{$config['system']['hostname']}.{$config['system']['domain']}";
            $name = "config-{$host}-".date("YmdHis").".xml";
            $data = "";

            /* backup entire configuration */
            $data = file_get_contents('/conf/config.xml');

            /* backup RRD data */
            if (empty($_POST['donotbackuprrd'])) {
                $rrd_data_xml = rrd_export();
                $closing_tag = "</opnsense>";
                $data = str_replace($closing_tag, $rrd_data_xml . $closing_tag, $data);
            }

            if (!empty($_POST['encrypt'])) {
                $data = encrypt_data($data, $_POST['encrypt_password']);
                tagfile_reformat($data, $data, "config.xml");
            }

            $size = strlen($data);
            header("Content-Type: application/octet-stream");
            header("Content-Disposition: attachment; filename={$name}");
            header("Content-Length: $size");
            if (isset($_SERVER['HTTPS'])) {
                header('Pragma: ');
                header('Cache-Control: ');
            } else {
                header("Pragma: private");
                header("Cache-Control: private, must-revalidate");
            }
            echo $data;
            exit;
        }
    } elseif ($mode == "restore") {
        // unpack data and perform validation
        $data = null;
        if (!empty($_POST['decrypt']) && (empty($_POST['decrypt_password']) || empty($_POST['decrypt_passconf']))) {
            $input_errors[] = gettext("You must supply and confirm the password for decryption.");
        } elseif (!empty($_POST['decrypt']) && $_POST['decrypt_password'] != $_POST['decrypt_passconf']) {
            $input_errors[] = gettext("The supplied 'Password' and 'Confirm' field values must match.");
        }
        /* read the file contents */
        if (is_uploaded_file($_FILES['conffile']['tmp_name'])) {
            $data = file_get_contents($_FILES['conffile']['tmp_name']);
            if(empty($data)) {
                log_error(sprintf('Warning, could not read file %s', $_FILES['conffile']['tmp_name']));
                $input_errors[] = sprintf(gettext("Warning, could not read file %s"), $_FILES['conffile']['tmp_name']);
            }
        } else {
            $input_errors[] = gettext("The configuration could not be restored (file upload error).");
        }

        if (!empty($_POST['decrypt'])) {
            if (!tagfile_deformat($data, $data, "config.xml")) {
                $input_errors[] = gettext("The uploaded file does not appear to contain an encrypted OPNsense configuration.");
            }
            $data = decrypt_data($data, $_POST['decrypt_password']);
        }

        if(!empty($_POST['restorearea']) && !stristr($data, "<" . $_POST['restorearea'] . ">")) {
            /* restore a specific area of the configuration */
            $input_errors[] = gettext("You have selected to restore an area but we could not locate the correct xml tag.");
        }

        if (count($input_errors) == 0) {
            if (!empty($_POST['restorearea'])) {
                if (!restore_config_section($_POST['restorearea'], $data)) {
                    $input_errors[] = gettext("You have selected to restore an area but we could not locate the correct xml tag.");
                } else {
                    if (!empty($config['rrddata'])) {
                        /* XXX we should point to the data... */
                        rrd_import();
                        unset($config['rrddata']);
                        write_config();
                        convert_config();
                    }
                    if (!empty($pconfig['rebootafterrestore'])) {
                        $do_reboot = true;
                    }
                    $savemsg = gettext("The configuration area has been restored.");
                }
            } else {
                /* restore the entire configuration */
                $filename = $_FILES['conffile']['tmp_name'];
                file_put_contents($filename, $data);
                $cnf = OPNsense\Core\Config::getInstance();
                if ($cnf->restoreBackup($filename)) {
                    if (!empty($pconfig['rebootafterrestore'])) {
                        $do_reboot = true;
                    }
                    $config = parse_config();
                    /* extract out rrd items, unset from $config when done */
                    if($config['rrddata']) {
                        /* XXX we should point to the data... */
                        rrd_import();
                        unset($config['rrddata']);
                        write_config();
                        convert_config();
                    }
                    $savemsg = gettext("The configuration has been restored.");
                } else {
                    $input_errors[] = gettext("The configuration could not be restored.");
                }
            }

            if ($do_reboot) {
                $savemsg .= ' ' . gettext("The system is rebooting now. This may take one minute.");
            }
        }
    } elseif ( $mode == "setup_gdrive" ){
        if (!isset($config['system']['remotebackup'])) {
            $config['system']['remotebackup'] = array() ;
        }
        $config['system']['remotebackup']['GDriveEnabled'] = $_POST['GDriveEnabled'];
        $config['system']['remotebackup']['GDriveEmail'] =   $_POST['GDriveEmail'] ;
        $config['system']['remotebackup']['GDriveFolderID'] = $_POST['GDriveFolderID'];
        $config['system']['remotebackup']['GDrivePassword'] = $_POST['GDrivePassword'];
        if (is_numeric($_POST['GDriveBackupCount'])) {
            $config['system']['remotebackup']['GDriveBackupCount'] = $_POST['GDriveBackupCount'];
        } else {
            $config['system']['remotebackup']['GDriveBackupCount'] = 60;
        }

        if ( $_POST['GDrivePasswordConfirm'] != $_POST['GDrivePassword'] ) {
            // log error, but continue
            $input_errors[] = gettext("The supplied 'Password' and 'Confirm' field values must match.");
        }

        if (count($input_errors) == 0) {
            if (is_uploaded_file($_FILES['GDriveP12file']['tmp_name'])) {
                $data = file_get_contents($_FILES['GDriveP12file']['tmp_name']);
                $config['system']['remotebackup']['GDriveP12key'] = base64_encode($data);
            } elseif ($config['system']['remotebackup']['GDriveEnabled'] != "on") {
                unset($config['system']['remotebackup']['GDriveP12key']);
            }

            $savemsg = gettext("Google Drive backup settings have been saved.");

            write_config();
            system_cron_configure();

            try {
                $filesInBackup = backup_to_google_drive();
            } catch (Exception $e) {
                $filesInBackup = array();
            }

            if (empty($config['system']['remotebackup']['GDriveEnabled'])) {
                /* unused */
            } elseif (count($filesInBackup) == 0) {
                $input_errors[] = gettext("Google Drive communication failure");
            } else {
                $input_messages = gettext("Backup successful, current file list:") . "<br>";
                foreach ($filesInBackup as $filename => $file) {
                     $input_messages = $input_messages . "<br>" . $filename;
                }
            }
        }

    }
}

include("head.inc");
?>

<body>
<?php include("fbegin.inc"); ?>

<script>
//<![CDATA[
$( document ).ready(function() {
    // show encryption password
    $("#encryptconf").change(function(event){
        event.preventDefault();
        if ($("#encryptconf").prop('checked')) {
            $("#encrypt_opts").removeClass("hidden");
        } else {
            $("#encrypt_opts").addClass("hidden");
        }
    });

    // show decryption password
    $("#decryptconf").change(function(event){
        event.preventDefault();
        if ($("#decryptconf").prop('checked')) {
            $("#decrypt_opts").removeClass("hidden");
        } else {
            $("#decrypt_opts").addClass("hidden");
        }
    });
});
//]]>
</script>

<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
      <?php if (isset($savemsg)) print_info_box($savemsg); ?>
      <?php if ($input_messages) print_info_box($input_messages); ?>
      <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
      <form method="post" enctype="multipart/form-data">
        <section class="col-xs-12">
          <div class="content-box tab-content table-responsive __mb">
            <table class="table table-striped ">
              <tbody>
                <tr>
                  <th colspan="2" style="vertical-align:top" class="listtopic">
                    <?=gettext("Download"); ?>
                  </th>
                </tr>
                <tr>
                  <td>
                    <input name="donotbackuprrd" type="checkbox" id="dotnotbackuprrd" checked="checked" />
                    <?=gettext("Do not backup RRD data."); ?><br/>
                    <input name="encrypt" type="checkbox" id="encryptconf" />
                    <?=gettext("Encrypt this configuration file."); ?><br/>
                    <div class="hidden table-responsive __mt" id="encrypt_opts">
                      <table class="table table-condensed">
                        <tr>
                          <td><?=gettext("Password:"); ?></td>
                          <td><input name="encrypt_password" type="password" value="" /></td>
                        </tr>
                        <tr>
                          <td><?=gettext("confirm:"); ?></td>
                          <td><input name="encrypt_passconf" type="password" value="" /> </td>
                        </tr>
                      </table>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td>
                    <input name="download" type="submit" class="btn btn-primary" value="<?=gettext("Download configuration"); ?>" />
                  </td>
                </tr>
                <tr>
                  <td>
                    <?=gettext("Click this button to download the system configuration in XML format."); ?>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="content-box tab-content table-responsive __mb">
            <table class="table table-striped ">
              <tbody>
                <tr>
                  <th colspan="2" style="vertical-align:top" class="listtopic">
                    <?=gettext("Restore"); ?>
                  </th>
                </tr>
                <tr>
                  <td>
                    <?=gettext("Restore area:"); ?>
                    <select name="restorearea" id="restorearea">
                      <option value=""><?=gettext("ALL");?></option>
<?php
                    foreach($areas as $area => $areaname):?>
                      <option value="<?=$area;?>"><?=$areaname;?></option>
<?php
                    endforeach;?>
                    </select><br/>
                    <input name="conffile" type="file" id="conffile" /><br/>
                    <input name="rebootafterrestore" type="checkbox" id="rebootafterrestore" checked="checked" />
                    <?=gettext("Reboot after a successful restore."); ?><br/>
                    <input name="decrypt" type="checkbox" id="decryptconf"/>
                    <?=gettext("Configuration file is encrypted."); ?>
                    <div class="hidden table-responsive __mt" id="decrypt_opts">
                      <table class="table table-condensed">
                        <tr>
                          <td><?=gettext("Password:"); ?></td>
                          <td><input name="decrypt_password" type="password" value="" /></td>
                        </tr>
                        <tr>
                          <td><?=gettext("confirm:"); ?></td>
                          <td><input name="decrypt_passconf" type="password" value="" /> </td>
                        </tr>
                      </table>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td>
                    <input name="restore" type="submit" class="btn btn-primary" id="restore" value="<?=gettext("Restore configuration"); ?>" />
                  </td>
                </tr>
                <tr>
                  <td>
                    <?=gettext("Open a configuration XML file and click the button below to restore the configuration."); ?><br/>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="content-box tab-content table-responsive">
            <table class="table table-striped opnsense_standard_table_form">
              <thead style="display: none;">
                <tr>
                  <th class="col-sm-1"></th>
                  <th class="col-sm-3"></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <th colspan="2" style="vertical-align:top" class="listtopic">
                    <?=gettext("Google Drive"); ?>
                  </th>
                </tr>
                <tr>
                  <td><?=gettext("Enable"); ?> </td>
                  <td>
                    <input name="GDriveEnabled" type="checkbox" <?=!empty($pconfig['GDriveEnabled']) ? "checked" : "";?> >
                  </td>
                </tr>
                <tr>
                  <td><?=gettext("Email Address"); ?> </td>
                  <td>
                    <input name="GDriveEmail" value="<?=$pconfig['GDriveEmail'];?>" type="text">
                  </td>
                </tr>
                <tr>
                  <td><?=gettext("P12 key"); ?> <?=!empty($pconfig['GDriveP12key']) ? gettext("(replace)") : gettext("(not loaded)"); ?> </td>
                  <td>
                    <input name="GDriveP12file" type="file">
                  </td>
                </tr>
                <tr>
                  <td><?=gettext("Folder ID"); ?> </td>
                  <td>
                    <input name="GDriveFolderID" value="<?=$pconfig['GDriveFolderID'];?>" type="text">
                  </td>
                </tr>
                <tr>
                  <td><?=gettext("Backup Count"); ?> </td>
                  <td>
                    <input name="GDriveBackupCount" value="<?=$pconfig['GDriveBackupCount'];?>"  type="text">
                  </td>
                </tr>
                <tr>
                  <td colspan=2><?=gettext("Password protect your data"); ?> :</td>
                </tr>
                <tr>
                  <td><?=gettext("Password :"); ?></td>
                  <td>
                    <input name="GDrivePassword" type="password" value="<?=$pconfig['GDrivePassword'];?>" />
                  </td>
                </tr>
                <tr>
                  <td><?=gettext("Confirm :"); ?></td>
                  <td>
                    <input name="GDrivePasswordConfirm" type="password" value="<?=$pconfig['GDrivePassword'];?>" />
                  </td>
                </tr>
                <tr>
                  <td>
                    <input name="setup_gdrive" class="btn btn-primary" id="Gdrive" value="<?=gettext("Setup/Test Google Drive");?>" type="submit">
                  </td>
                  <td></td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </form>
    </div>
  </div>
</section>

<?php

include("foot.inc");

if ($do_reboot) {
    system_reboot();
}
