<?php

/*
 * Copyright (C) 2015-2018 Franco Fichtner <franco@opnsense.org>
 * Copyright (C) 2014 Deciso B.V.
 * Copyright (C) 2004-2009 Scott Ullrich <sullrich@gmail.com>
 * Copyright (C) 2008 Shrew Soft Inc. <mgrooms@shrew.net>
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

require_once("guiconfig.inc");
require_once("interfaces.inc");
require_once("filter.inc");
require_once("rrd.inc");
require_once("system.inc");

use OPNsense\Backup\Local;

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

$backupFactory = new OPNsense\Backup\BackupFactory();
$do_reboot = false;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();

    foreach ($backupFactory->listProviders() as $providerId => $provider) {
        foreach ($provider['handle']->getConfigurationFields() as $field) {
            $fieldId = $providerId . "_" .$field['name'];
            $pconfig[$fieldId] = $field['value'];
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    $pconfig = $_POST;
    $mode = null;

    foreach (array_keys($backupFactory->listProviders()) as $providerName) {
        if (!empty($pconfig["setup_{$providerName}"])) {
            $mode = "setup_{$providerName}";
        }
    }

    if (empty($mode)) {
        if (!empty($pconfig['restore'])) {
            $mode = "restore";
        } elseif (!empty($pconfig['download'])) {
            $mode = "download";
        }
    }

    if ($mode == "download") {
        if (!empty($_POST['encrypt']) && (empty($_POST['encrypt_password']) || empty($_POST['encrypt_passconf']))) {
            $input_errors[] = gettext("You must supply and confirm the password for encryption.");
        } elseif (!empty($_POST['encrypt']) && $_POST['encrypt_password'] != $_POST['encrypt_passconf']) {
            $input_errors[] = gettext('The passwords do not match.');
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
                $crypter = new Local();
                /* XXX this *could* fail, not handled */
                $data = $crypter->encrypt($data, $_POST['encrypt_password']);
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
        if (!empty($_POST['decrypt']) && empty($_POST['decrypt_password'])) {
            $input_errors[] = gettext('You must supply the password for decryption.');
        }
        $user = getUserEntry($_SESSION['Username']);
        if (userHasPrivilege($user, 'user-config-readonly')) {
            $input_errors[] = gettext('You do not have the permission to perform this action.');
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
            $crypter = new Local();
            $data = $crypter->decrypt($data, $_POST['decrypt_password']);
            if (empty($data)) {
                $input_errors[] = gettext('The uploaded file could not be decrypted.');
            }
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
    } elseif (!empty($mode)){
        // setup backup provider, collect provider settings and save/validate
        $providerId = substr($mode, 6);
        $provider = $backupFactory->getProvider($providerId);
        $providerSet = array();
        foreach ($provider['handle']->getConfigurationFields() as $field) {
            $fieldId = $providerId . "_" .$field['name'];
            if ($field['type'] == 'file') {
                // extract file to sent to setConfiguration()
                if (is_uploaded_file($_FILES[$fieldId]['tmp_name'])) {
                    $providerSet[$field['name']] = file_get_contents($_FILES[$fieldId]['tmp_name']);
                } else {
                    $providerSet[$field['name']] = null;
                }
            } else {
                $providerSet[$field['name']] = $pconfig[$fieldId];
            }
        }
        $input_errors = $provider['handle']->setConfiguration($providerSet);
        if (count($input_errors) == 0) {
            if ($provider['handle']->isEnabled()) {
                try {
                    $filesInBackup = $provider['handle']->backup();
                } catch (Exception $e) {
                    $filesInBackup = array();
                    $input_errors[] = $e->getMessage();
                }

                if (count($filesInBackup) == 0) {
                    $input_errors[] = gettext('Saved settings, but remote backup failed.');
                } else {
                    $input_messages = gettext("Backup successful, current file list:") . "<br>";
                    foreach ($filesInBackup as $filename) {
                         $input_messages .= "<br>" . $filename;
                    }
                }
            }
            system_cron_configure();
        }
    }
}

include("head.inc");
legacy_html_escape_form_data($pconfig);
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
            <table class="table table-striped">
                <tr>
                  <td><strong><?= gettext('Download') ?></strong></td>
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
                          <td><?= gettext('Password') ?></td>
                          <td><input name="encrypt_password" type="password"/></td>
                        </tr>
                        <tr>
                          <td><?= gettext('Confirmation') ?></td>
                          <td><input name="encrypt_passconf" type="password"/> </td>
                        </tr>
                      </table>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td>
                    <input name="download" type="submit" class="btn btn-primary" value="<?= html_safe(gettext('Download configuration')) ?>" />
                  </td>
                </tr>
                <tr>
                  <td>
                    <?=gettext("Click this button to download the system configuration in XML format."); ?>
                  </td>
                </tr>
            </table>
          </div>
          <div class="content-box tab-content table-responsive __mb">
            <table class="table table-striped">
                <tr>
                  <td><strong><?= gettext('Restore') ?></strong></td>
                </tr>
                <tr>
                  <td>
                    <?=gettext("Restore area:"); ?>
                    <div>
                      <select name="restorearea" id="restorearea" class="selectpicker">
                        <option value=""><?=gettext("ALL");?></option>
<?php
                      foreach($areas as $area => $areaname):?>
                        <option value="<?=$area;?>"><?=$areaname;?></option>
<?php
                      endforeach;?>
                      </select>
                    </div>
                    <input name="conffile" type="file" id="conffile" /><br/>
                    <input name="rebootafterrestore" type="checkbox" id="rebootafterrestore" checked="checked" />
                    <?=gettext("Reboot after a successful restore."); ?><br/>
                    <input name="decrypt" type="checkbox" id="decryptconf"/>
                    <?=gettext("Configuration file is encrypted."); ?>
                    <div class="hidden table-responsive __mt" id="decrypt_opts">
                      <table class="table table-condensed">
                        <tr>
                          <td><?= gettext('Password') ?></td>
                          <td><input name="decrypt_password" type="password"/></td>
                        </tr>
                      </table>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td>
                    <input name="restore" type="submit" class="btn btn-primary" id="restore" value="<?= html_safe(gettext('Restore configuration')) ?>" />
                  </td>
                </tr>
                <tr>
                  <td>
                    <?=gettext("Open a configuration XML file and click the button below to restore the configuration."); ?><br/>
                  </td>
                </tr>
            </table>
          </div>

<?php
          foreach ($backupFactory->listProviders() as $providerId => $provider):?>
          <div class="content-box tab-content table-responsive __mb">
            <table class="table table-striped opnsense_standard_table_form">
                    <tr>
                        <td colspan="2"><strong><?= $provider['handle']->getName() ?></strong></td>
                    </tr>
<?php
                foreach ($provider['handle']->getConfigurationFields() as $field):
                    $fieldId = $providerId . "_" .$field['name'];?>
                    <tr>
                        <td style="width:22%">
<?php if (!empty($field['help'])): ?>
                            <a id="help_for_<?=$fieldId;?>" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>
<?php else: ?>
                            <i class="fa fa-info-circle text-muted"></i>
<?php endif ?>
                           <?=$field['label'];?>
                        </td>
                        <td style="width:78%">
<?php
                        if ($field['type'] == 'checkbox'):?>
                        <input name="<?=$fieldId;?>" type="checkbox" <?=!empty($pconfig[$fieldId]) ? "checked" : "";?> >
<?php
                        elseif ($field['type'] == 'text'):?>
                        <input name="<?=$fieldId;?>" value="<?=$pconfig[$fieldId];?>" type="text">

<?php
                        elseif ($field['type'] == 'file'):?>
                        <input name="<?=$fieldId;?>" type="file">
<?php
                        elseif ($field['type'] == 'password'):?>

                        <input name="<?=$fieldId;?>" type="password" value="<?=$pconfig[$fieldId];?>" />
<?php
                        elseif ($field['type'] == 'textarea'):?>
                        <textarea name="<?=$fieldId;?>" rows="10"><?=$pconfig[$fieldId];?></textarea>
<?php
                        endif;?>
                        <div class="hidden" data-for="help_for_<?=$fieldId;?>">
                            <?=!empty($field['help']) ? $field['help'] : "";?>
                        </div>
                        </td>
                    </tr>
<?php
                endforeach;?>

                    <tr>
                        <td></td>
                        <td>
                            <button type="submit" name="setup_<?=$providerId;?>" value="yes" class="btn btn-primary">
                              <?= sprintf(gettext("Setup/Test %s"), $provider['handle']->getName()) ?>
                            </button>
                        </td>
                    </tr>
            </table>
          </div>
<?php
          endforeach;?>
        </section>
      </form>
    </div>
  </div>
</section>

<?php

include("foot.inc");

if ($do_reboot) {
    configd_run('system reboot', true);
}
