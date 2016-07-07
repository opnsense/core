<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.
    Copyright (C) 2005-2007 Scott Ullrich
    Copyright (C) 2008 Shrew Soft Inc
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
require_once("filter.inc");
require_once("ipsec.inc");
require_once("vslb.inc");
require_once("system.inc");
require_once("util.inc");
require_once("services.inc");
require_once("interfaces.inc");

function crypto_modules()
{
    $modules = array(
        'aesni' => gettext('AES-NI CPU-based Acceleration'),
        'glxsb' => gettext('AMD Geode LX Security Block'),
        'hifn' => gettext('Hifn 7751/7951/7811/7955/7956 Crypto Accelerator'),
        'padlock' => gettext('Crypto and RNG in VIA C3, C7 and Eden Processors'),
        'safe' => gettext('SafeNet Crypto Accelerator'),
        'ubsec' => gettext('Broadcom and BlueSteel uBsec 5x0x crypto accelerator'),
    );
    $available = array();

    foreach ($modules as $name => $desc) {
        if (file_exists("/boot/kernel/{$name}.ko")) {
            $available[$name] = $desc;
        }
    }

    return $available;
}

function thermal_modules()
{
    $modules = array(
        'amdtemp' => gettext('AMD K8, K10 and K11 CPU on-die thermal sensor'),
        'coretemp' => gettext('Intel Core* CPU on-die thermal sensor'),
    );
    $available = array();

    foreach ($modules as $name => $desc) {
        if (file_exists("/boot/kernel/{$name}.ko")) {
            $available[$name] = $desc;
        }
    }

    return $available;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();
    $pconfig['powerd_enable'] = isset($config['system']['powerd_enable']);
    $pconfig['crypto_hardware'] = !empty($config['system']['crypto_hardware']) ? $config['system']['crypto_hardware'] : null;
    $pconfig['cryptodev_enable'] = isset($config['system']['cryptodev_enable']);
    $pconfig['thermal_hardware'] = !empty($config['system']['thermal_hardware']) ? $config['system']['thermal_hardware'] : null;
    $pconfig['use_mfs_tmpvar'] = isset($config['system']['use_mfs_tmpvar']);
    $pconfig['use_mfs_tmp'] = isset($config['system']['use_mfs_tmp']);
    $pconfig['powerd_ac_mode'] = "hadp";
    $pconfig['rrdbackup'] = !empty($config['system']['rrdbackup']) ? $config['system']['rrdbackup'] : null;
    $pconfig['dhcpbackup'] = !empty($config['system']['dhcpbackup']) ? $config['system']['dhcpbackup'] : null;
    $pconfig['netflowbackup'] = !empty($config['system']['netflowbackup']) ? $config['system']['netflowbackup'] : null;
    if (!empty($config['system']['powerd_ac_mode'])) {
        $pconfig['powerd_ac_mode'] = $config['system']['powerd_ac_mode'];
    }
    $pconfig['powerd_battery_mode'] = "hadp";
    if (!empty($config['system']['powerd_battery_mode'])) {
        $pconfig['powerd_battery_mode'] = $config['system']['powerd_battery_mode'];
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    //
    $input_errors = array();
    $pconfig = $_POST;

    if (!empty($pconfig['crypto_hardware']) && !array_key_exists($pconfig['crypto_hardware'], crypto_modules())) {
        $input_errors[] = gettext("Please select a valid Cryptographic Accelerator.");
    }

    if (!empty($pconfig['thermal_hardware']) && !array_key_exists($pconfig['thermal_hardware'], thermal_modules())) {
        $input_errors[] = gettext("Please select a valid Thermal Hardware Sensor.");
    }

    if (count($input_errors) == 0) {
        if (!empty($pconfig['powerd_enable'])) {
            $config['system']['powerd_enable'] = true;
        } elseif (isset($config['system']['powerd_enable'])) {
            unset($config['system']['powerd_enable']);
        }

        $config['system']['powerd_ac_mode'] = $pconfig['powerd_ac_mode'];
        $config['system']['powerd_battery_mode'] = $pconfig['powerd_battery_mode'];

        if ($pconfig['crypto_hardware']) {
            $config['system']['crypto_hardware'] = $pconfig['crypto_hardware'];
        } elseif (isset($config['system']['crypto_hardware'])) {
            unset($config['system']['crypto_hardware']);
        }

        if (!empty($pconfig['cryptodev_enable'])) {
            $config['system']['cryptodev_enable'] = true;
        } elseif (isset($config['system']['cryptodev_enable'])) {
            unset($config['system']['cryptodev_enable']);
        }

        if (!empty($pconfig['thermal_hardware'])) {
            $config['system']['thermal_hardware'] = $pconfig['thermal_hardware'];
        } elseif (isset($config['system']['thermal_hardware'])) {
            unset($config['system']['thermal_hardware']);
        }

        if (!empty($pconfig['use_mfs_tmpvar'])) {
            $config['system']['use_mfs_tmpvar'] = true;
        } elseif (isset($config['system']['use_mfs_tmpvar'])) {
            unset($config['system']['use_mfs_tmpvar']);
        }

        if (!empty($pconfig['use_mfs_tmp'])) {
            $config['system']['use_mfs_tmp'] = true;
        } elseif (isset($config['system']['use_mfs_tmp'])) {
            unset($config['system']['use_mfs_tmp']);
        }

        if (!empty($pconfig['rrdbackup'])) {
            $config['system']['rrdbackup'] = $pconfig['rrdbackup'];
        } elseif (isset($config['system']['rrdbackup'])) {
            unset($config['system']['rrdbackup']);
        }

        if (!empty($pconfig['dhcpbackup'])) {
            $config['system']['dhcpbackup'] = $pconfig['dhcpbackup'];
        } elseif (isset($config['system']['dhcpbackup'])) {
            unset($config['system']['dhcpbackup']);
        }

        if (!empty($pconfig['netflowbackup'])) {
            $config['system']['netflowbackup'] = $pconfig['netflowbackup'];
        } elseif (isset($config['system']['netflowbackup'])) {
            unset($config['system']['netflowbackup']);
        }

        write_config();
        $savemsg = get_std_save_message();

        system_resolvconf_generate(true);
        configure_cron();
        activate_powerd();
        load_crypto_module();
        load_thermal_module();
    }
}

legacy_html_escape_form_data($pconfig);

include("head.inc");

?>

<body>

<?php include("fbegin.inc"); ?>

<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
<?php
    if (isset($input_errors) && count($input_errors) > 0) {
        print_input_errors($input_errors);
    }
    if (isset($savemsg)) {
        print_info_box($savemsg);
    }
?>
      <section class="col-xs-12">
        <div class="content-box tab-content table-responsive">
          <form method="post" name="iform" id="iform">
            <table class="table table-striped opnsense_standard_table_form">
              <tr>
                <td width="22%"><strong><?= gettext('Cryptographic Hardware Acceleration') ?></strong></td>
                <td width="78%" align="right">
                  <small><?=gettext("full help"); ?> </small>
                  <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page" type="button"></i>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_crypto_hardware" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Hardware");?></td>
                <td>
                  <select name="crypto_hardware" id="crypto_hardware" class="selectpicker" data-style="btn-default">
                    <option value=""><?=gettext("None"); ?></option>
<?php
                    foreach (crypto_modules() as $cryptomod_name => $cryptomod_descr) :?>
                      <option value="<?=$cryptomod_name; ?>" <?=$pconfig['crypto_hardware'] == $cryptomod_name ? "selected=\"selected\"" :"";?>>
                        <?="{$cryptomod_descr} ({$cryptomod_name})"; ?>
                      </option>
<?php
                    endforeach; ?>
                  </select>
                  <div class="hidden" for="help_for_crypto_hardware">
                    <?=gettext("A cryptographic accelerator module will use hardware support to speed up some " .
                                            "cryptographic functions on systems which have the chip. Do not enable this " .
                                            "option if you have a Hifn cryptographic acceleration card, as this will take " .
                                            "precedence and the Hifn card will not be used. Acceleration should be automatic " .
                                            "for IPsec when using a cipher supported by your chip, such as AES-128. OpenVPN " .
                                            "should be set for AES-128-CBC and have cryptodev enabled for hardware " .
                                            "acceleration."); ?>
                  <br /><br />
                  <?=gettext("If you do not have a crypto chip in your system, this option will have no " .
                                      "effect. To unload the selected module, set this option to 'none' and then reboot."); ?>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_cryptodev_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Use /dev/crypto");?> </td>
                <td>
                  <input name="cryptodev_enable" type="checkbox" id="cryptodev_enable" value="yes" <?= !empty($pconfig['cryptodev_enable']) ? "checked=\"checked\"" : "";?> />
                  <strong><?=gettext("Enable old userland device for cryptographic acceleration"); ?></strong>
                  <div class="hidden" for="help_for_cryptodev_enable">
                    <?=gettext("Old hardware accelerators like 'safe', 'hifn' or 'ubsec' may only provide userland acceleration to e.g. " .
                                            "OpenVPN by means of the /dev/crypto interface, which can be accessed via the OpenSSL " .
                                            "engine framework. Note that LibreSSL does not have support for this device and " .
                                            "instead solely relies on embedded acceleration methods e.g. AES-NI. The default is " .
                                            "to disable this device as it is likely not needed on modern systems."); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <th colspan="2" valign="top" class="listtopic"><?=gettext("Thermal Sensors"); ?></th>
              </tr>
              <tr>
                <td><a id="help_for_thermal_hardware" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Hardware");?> </td>
                <td>
                  <select name="thermal_hardware" class="selectpicker" data-style="btn-default">
                    <option value=""><?=gettext("None/ACPI"); ?></option>
<?php
                    foreach (thermal_modules() as $themalmod_name => $themalmod_descr) :?>
                      <option value="<?=$themalmod_name; ?>" <?=$pconfig['thermal_hardware'] == $themalmod_name ? " selected=\"selected\"" :"";?>>
                        <?="{$themalmod_descr} ({$themalmod_name})"; ?>
                      </option>
<?php
                    endforeach; ?>
                  </select>
                  <div class="hidden" for="help_for_thermal_hardware">
                    <?=gettext("If you have a supported CPU, selecting a themal sensor will load the appropriate " .
                                              "driver to read its temperature. Setting this to 'None' will attempt to read the " .
                                              "temperature from an ACPI-compliant motherboard sensor instead, if one is present."); ?>
                    <br /><br />
                    <?=gettext("If you do not have a supported thermal sensor chip in your system, this option will have no " .
                                          "effect. To unload the selected module, set this option to 'none' and then reboot."); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <th colspan="2" valign="top" class="listtopic"><?=gettext("Periodic Backups"); ?></th>
              </tr>
              <tr>
                <td><a id="help_for_rrdbackup" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Periodic RRD Backup");?></td>
                <td>
                  <select name="rrdbackup" class="selectpicker" data-style="btn-default" id="rrdbackup">
                    <option value='0' <?=!$pconfig['rrdbackup'] == 0 ? 'selected="selected"' : ''; ?>><?=gettext("Disabled"); ?></option>
<?php
                    for ($x = 1; $x <= 24; $x++): ?>
                    <option value="<?= $x ?>" <?= $pconfig['rrdbackup'] == $x ? 'selected="selected"' : ''; ?>>
                      <?= $x == 1 ? gettext('1 hour') : sprintf(gettext('%s hours'), $x) ?>
                    </option>
<?php
                      endfor; ?>
                  </select>
                  <br />
                  <div class="hidden" for="help_for_rrdbackup">
                    <?=gettext("This will periodically backup the RRD data so it can be restored automatically on the next boot.");?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_dhcpbackup" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Periodic DHCP Leases Backup");?></td>
                <td>
                  <select name="dhcpbackup" class="selectpicker" data-style="btn-default" id="dhcpbackup">
                    <option value='0' <?= $pconfig['dhcpbackup'] == 0 ? "selected='selected'" : ''; ?>><?=gettext('Disabled'); ?></option>
<?php
                    for ($x = 1; $x <= 24; $x++): ?>
                    <option value="<?= $x ?>" <?= $pconfig['dhcpbackup'] == $x ? 'selected="selected"' : '';?>>
                      <?= $x == 1 ? gettext('1 hour') : sprintf(gettext('%s hours'), $x) ?>
                    </option>
<?php
                    endfor; ?>
                  </select>
                  <div class="hidden" for="help_for_dhcpbackup">
                    <?=gettext("This will periodically backup the DHCP leases data so it can be restored automatically on the next boot.");?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_netflowbackup" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Periodic NetFlow Backup");?></td>
                <td>
                  <select name="netflowbackup" class="selectpicker" data-style="btn-default" id="netflowbackup">
                    <option value='0' <?= $pconfig['netflowbackup'] == 0 ? 'selected="selected"' : ''; ?>><?=gettext('Disabled'); ?></option>
<?php
                    for ($x = 1; $x <= 24; $x++): ?>
                    <option value="<?= $x ?>" <?= $pconfig['netflowbackup'] == $x ? 'selected="selected"' : '';?>>
                      <?= $x == 1 ? gettext('1 hour') : sprintf(gettext('%s hours'), $x) ?>
                    </option>
<?php
                    endfor; ?>
                  </select>
                  <div class="hidden" for="help_for_netflowbackup">
                    <?=gettext("This will periodically backup the NetFlow data aggregation so it can be restored automatically on the next boot.");?>
                  </div>
                </td>
              </tr>
              <tr>
                <th colspan="2" valign="top" class="listtopic"><?=gettext("Power Savings"); ?></th>
              </tr>
              <tr>
                <td><a id="help_for_powerd_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Use PowerD"); ?></td>
                <td>
                  <input name="powerd_enable" type="checkbox" id="powerd_enable" value="yes" <?=!empty($pconfig['powerd_enable']) ? "checked=\"checked\"" : "";?> />
                  <div class="hidden" for="help_for_powerd_enable">
                    <?=gettext("The powerd utility monitors the system state and sets various power control " .
                                        "options accordingly. It offers four modes (maximum, minimum, adaptive " .
                                        "and hiadaptive) that can be individually selected while on AC power or batteries. " .
                                        "The modes maximum, minimum, adaptive and hiadaptive may be abbreviated max, " .
                                        "min, adp, hadp. Maximum mode chooses the highest performance values. Minimum " .
                                        "mode selects the lowest performance values to get the most power savings. " .
                                        "Adaptive mode attempts to strike a balance by degrading performance when " .
                                        "the system appears idle and increasing it when the system is busy. It " .
                                        "offers a good balance between a small performance loss for greatly " .
                                        "increased power savings. Hiadaptive mode is alike adaptive mode, but " .
                                        "tuned for systems where performance and interactivity are more important " .
                                        "than power consumption. It raises frequency faster, drops slower and " .
                                        "keeps twice lower CPU load."); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><i class="fa fa-info-circle text-muted"></i>  <?=gettext('On AC Power Mode') ?></td>
                <td>
                  <select name="powerd_ac_mode" class="selectpicker" data-style="btn-default" data-width="auto">
                    <option value="hadp" <?=$pconfig['powerd_ac_mode']=="hadp" ? "selected=\"selected\"" : "";?>>
                      <?=gettext("Hiadaptive");?>
                    </option>
                    <option value="adp" <?=$pconfig['powerd_ac_mode']=="adp" ? "selected=\"selected\"" : "";?>>
                      <?=gettext("Adaptive");?>
                    </option>
                    <option value="min" <?=$pconfig['powerd_ac_mode']=="min" ? "selected=\"selected\"" : "";?>>
                      <?=gettext("Minimum");?>
                    </option>
                    <option value="max" <?=$pconfig['powerd_ac_mode']=="max" ? " selected=\"selected\"" : "";?>>
                      <?=gettext("Maximum");?>
                    </option>
                  </select>
                </td>
              <tr>
                <td><i class="fa fa-info-circle text-muted"></i>  <?=gettext('On Battery Power Mode') ?></td>
                <td>
                  <select name="powerd_battery_mode" class="selectpicker" data-style="btn-default" data-width="auto">
                    <option value="hadp"<?=$pconfig['powerd_battery_mode']=="hadp" ? "selected=\"selected\"" : "";?>>
                      <?=gettext("Hiadaptive");?>
                    </option>
                    <option value="adp" <?=$pconfig['powerd_battery_mode']=="adp" ? "selected=\"selected\"" : "";?>>
                      <?=gettext("Adaptive");?>
                    </option>
                    <option value="min" <?=$pconfig['powerd_battery_mode']=="min" ? "selected=\"selected\"" :"";?>>
                      <?=gettext("Minimum");?>
                    </option>
                    <option value="max" <?=$pconfig['powerd_battery_mode']=="max" ? "selected=\"selected\"" : "";?>>
                      <?=gettext("Maximum");?>
                    </option>
                  </select>
                </td>
              </tr>
              <tr>
                <th colspan="2" valign="top" class="listtopic"><?=gettext("RAM Disk Settings (Reboot to Apply Changes)"); ?></th>
              </tr>
              <tr>
                <td><a id="help_for_use_mfs_tmpvar" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('/tmp and /var RAM disks'); ?></td>
                <td>
                  <input name="use_mfs_tmpvar" type="checkbox" id="use_mfs_tmpvar" value="yes" <?=!empty($pconfig['use_mfs_tmpvar']) ? 'checked="checked"' : '';?>/>
                  <strong><?=gettext("Use memory file system for /tmp and /var"); ?></strong>
                  <div class="hidden" for="help_for_use_mfs_tmpvar">
                    <?=gettext("Set this if you wish to use /tmp and /var as RAM disks (memory file system disks) " .
                      "rather than use the hard disk. Setting this will cause the data /var to be lost on reboot, including log data."); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_use_mfs_tmp" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('/tmp RAM disk'); ?></td>
                <td>
                  <input name="use_mfs_tmp" type="checkbox" id="use_mfs_tmp" value="yes" <?=!empty($pconfig['use_mfs_tmp']) ? 'checked="checked"' : '';?>/>
                  <strong><?=gettext('Use memory file system for /tmp'); ?></strong>
                  <div class="hidden" for="help_for_use_mfs_tmp">
                    <?= gettext('Set this if you wish to use /tmp as a RAM disk (memory file system disk) rather than use the hard disk.') ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td>&nbsp;</td>
                <td>
                  <input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" />
                </td>
              </tr>
            </table>
          </form>
        </div>
      </section>
    </div>
  </div>
</section>

<?php include("foot.inc");
