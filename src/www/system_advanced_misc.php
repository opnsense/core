<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2005-2007 Scott Ullrich <sullrich@gmail.com>
 * Copyright (C) 2009 Scott Ullrich <sullrich@gmail.com>
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
require_once("filter.inc");
require_once("system.inc");
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
    $pconfig['use_mfs_var'] = isset($config['system']['use_mfs_var']);
    $pconfig['use_mfs_tmp'] = isset($config['system']['use_mfs_tmp']);
    $pconfig['use_swap_file'] = isset($config['system']['use_swap_file']);
    $pconfig['dhparamusage'] = !empty($config['system']['dhparamusage']) ? $config['system']['dhparamusage'] : null;
    $pconfig['rrdbackup'] = !empty($config['system']['rrdbackup']) ? $config['system']['rrdbackup'] : null;
    $pconfig['dhcpbackup'] = !empty($config['system']['dhcpbackup']) ? $config['system']['dhcpbackup'] : null;
    $pconfig['netflowbackup'] = !empty($config['system']['netflowbackup']) ? $config['system']['netflowbackup'] : null;
    $pconfig['captiveportalbackup'] = !empty($config['system']['captiveportalbackup']) ? $config['system']['captiveportalbackup'] : null;
    $pconfig['powerd_ac_mode'] = "hadp";
    if (!empty($config['system']['powerd_ac_mode'])) {
        $pconfig['powerd_ac_mode'] = $config['system']['powerd_ac_mode'];
    }
    $pconfig['powerd_battery_mode'] = "hadp";
    if (!empty($config['system']['powerd_battery_mode'])) {
        $pconfig['powerd_battery_mode'] = $config['system']['powerd_battery_mode'];
    }
    $pconfig['powerd_normal_mode'] = "hadp";
    if (!empty($config['system']['powerd_normal_mode'])) {
        $pconfig['powerd_normal_mode'] = $config['system']['powerd_normal_mode'];
    }
    // System Sounds
    $pconfig['disablebeep'] = isset($config['system']['disablebeep']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $config['system']['powerd_normal_mode'] = $pconfig['powerd_normal_mode'];

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

        if (!empty($pconfig['use_mfs_var'])) {
            $config['system']['use_mfs_var'] = true;
        } elseif (isset($config['system']['use_mfs_var'])) {
            unset($config['system']['use_mfs_var']);
        }

        if (!empty($pconfig['use_mfs_tmp'])) {
            $config['system']['use_mfs_tmp'] = true;
        } elseif (isset($config['system']['use_mfs_tmp'])) {
            unset($config['system']['use_mfs_tmp']);
        }

        if (!empty($pconfig['use_swap_file'])) {
            /* set explicit value here in case we want to make it flexible */
            $config['system']['use_swap_file'] = 2048;
        } elseif (isset($config['system']['use_swap_file'])) {
            unset($config['system']['use_swap_file']);
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

        if (!empty($pconfig['captiveportalbackup'])) {
            $config['system']['captiveportalbackup'] = $pconfig['captiveportalbackup'];
        } elseif (isset($config['system']['captiveportalbackup'])) {
            unset($config['system']['captiveportalbackup']);
        }

        if (!empty($pconfig['dhparamusage'])) {
            $config['system']['dhparamusage'] = $pconfig['dhparamusage'];
        } elseif (isset($config['system']['dhparamusage'])) {
            unset($config['system']['dhparamusage']);
        }

        // System Sounds
        if (!empty($pconfig['disablebeep'])) {
            $config['system']['disablebeep'] = true;
        } elseif (isset($config['system']['disablebeep'])) {
            unset($config['system']['disablebeep']);
        }

        write_config();

        system_resolvconf_generate();
        system_cron_configure();
        system_powerd_configure();
        system_kernel_configure();

        $savemsg = get_std_save_message();
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
        <form method="post" name="iform" id="iform">
          <div class="content-box tab-content table-responsive __mb">
            <table class="table table-striped opnsense_standard_table_form">
              <tr>
                <td style="width:22%"><strong><?= gettext('Cryptography settings') ?></strong></td>
                <td style="width:78%; text-align:right">
                  <small><?=gettext("full help"); ?> </small>
                  <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_dhparamusage" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Diffie-Hellman parameters') ?></td>
                <td>
                  <select name="dhparamusage" class="selectpicker" data-style="btn-default" id="dhparamusage">
                    <option value="" <?= $pconfig['dhparamusage'] == '' ? "selected='selected'" : '' ?>><?=gettext('System defaults') ?></option>
                    <option value="rfc7919" <?= $pconfig['dhparamusage'] == 'rfc7919' ? "selected='selected'" : '' ?>><?=gettext('RFC 7919') ?></option>
                    <option value="weekly" <?= $pconfig['dhparamusage'] == 'weekly' ? "selected='selected'" : '' ?>><?=gettext('Weekly renewal') ?></option>
                    <option value="monthly" <?= $pconfig['dhparamusage'] == 'monthly' ? "selected='selected'" : '' ?>><?=gettext('Monthly renewal') ?></option>
                    <option value="custom" <?= $pconfig['dhparamusage'] == 'custom' ? "selected='selected'" : '' ?>><?=gettext('Custom renewal') ?></option>
                  </select>
                  <div class="hidden" data-for="help_for_dhparamusage">
                    <?=gettext('Diffie-Hellman parameters are statically provided and updated at least twice per year in software updates. ' .
                               'RFC 7919 predefines static recommendations instead, which are commonly known and verfiable by clients. ' .
                               'You can choose to regenerate unqiue parameters locally instead according to a predefined or custom schedule. ' .
                               'In case of custom renewal make sure to set up the respective cron job or rotate the files in some other way.') ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_crypto_hardware" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Hardware acceleration') ?></td>
                <td>
                  <select name="crypto_hardware" id="crypto_hardware" class="selectpicker" data-style="btn-default">
                    <option value=""><?=gettext("None"); ?></option>
<?php foreach (crypto_modules() as $cryptomod_name => $cryptomod_descr): ?>
                    <option value="<?= html_safe($cryptomod_name) ?>" <?=$pconfig['crypto_hardware'] == $cryptomod_name ? "selected=\"selected\"" :"";?>>
                      <?="{$cryptomod_descr} ({$cryptomod_name})"; ?>
                    </option>
<?php endforeach ?>
                  </select>
                  <div class="hidden" data-for="help_for_crypto_hardware">
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
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_cryptodev_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Use /dev/crypto");?> </td>
                <td>
                  <input name="cryptodev_enable" type="checkbox" id="cryptodev_enable" value="yes" <?= !empty($pconfig['cryptodev_enable']) ? "checked=\"checked\"" : "";?> />
                  <?= gettext('Enable old userland device for cryptographic acceleration') ?>
                  <div class="hidden" data-for="help_for_cryptodev_enable">
                    <?=gettext("Old hardware accelerators like 'safe', 'hifn' or 'ubsec' may only provide userland acceleration to e.g. " .
                                            "OpenVPN by means of the /dev/crypto interface, which can be accessed via the OpenSSL " .
                                            "engine framework. Note that LibreSSL does not have support for this device and " .
                                            "instead solely relies on embedded acceleration methods e.g. AES-NI. The default is " .
                                            "to disable this device as it is likely not needed on modern systems."); ?>
                  </div>
                </td>
              </tr>
            </table>
          </div>
          <div class="content-box tab-content table-responsive __mb">
            <table class="table table-striped opnsense_standard_table_form">
              <tr>
                <td style="width:22%"><strong><?= gettext('Thermal Sensors') ?></strong></td>
                <td style="width:78%"></td>
              </tr>
              <tr>
                <td><a id="help_for_thermal_hardware" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Hardware");?> </td>
                <td>
                  <select name="thermal_hardware" class="selectpicker" data-style="btn-default">
                    <option value=""><?=gettext("None/ACPI"); ?></option>
<?php foreach (thermal_modules() as $themalmod_name => $themalmod_descr): ?>
                    <option value="<?= html_safe($themalmod_name) ?>" <?=$pconfig['thermal_hardware'] == $themalmod_name ? " selected=\"selected\"" :"";?>>
                      <?="{$themalmod_descr} ({$themalmod_name})"; ?>
                    </option>
<?php endforeach ?>
                  </select>
                  <div class="hidden" data-for="help_for_thermal_hardware">
                    <?=gettext("If you have a supported CPU, selecting a themal sensor will load the appropriate " .
                                              "driver to read its temperature. Setting this to 'None' will attempt to read the " .
                                              "temperature from an ACPI-compliant motherboard sensor instead, if one is present."); ?>
                    <br /><br />
                    <?=gettext("If you do not have a supported thermal sensor chip in your system, this option will have no " .
                                          "effect. To unload the selected module, set this option to 'none' and then reboot."); ?>
                  </div>
                </td>
              </tr>
            </table>
          </div>
          <div class="content-box tab-content table-responsive __mb">
            <table class="table table-striped opnsense_standard_table_form">
              <tr>
                <td style="width:22%"><strong><?= gettext('Periodic Backups') ?></strong></td>
                <td style="width:78%"></td>
              </tr>
              <tr>
                <td><a id="help_for_rrdbackup" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Periodic RRD Backup");?></td>
                <td>
                  <select name="rrdbackup" class="selectpicker" data-style="btn-default" id="rrdbackup">
                    <option value='0' <?= $pconfig['rrdbackup'] == 0 ? 'selected="selected"' : '' ?>><?= gettext('Power off') ?></option>
<?php for ($x = 1; $x <= 24; $x++): ?>
                    <option value="<?= $x ?>" <?= $pconfig['rrdbackup'] == $x ? 'selected="selected"' : ''; ?>>
                      <?= $x == 1 ? gettext('1 hour') : sprintf(gettext('%s hours'), $x) ?>
                    </option>
<?php endfor ?>
                    <option value='-1' <?= $pconfig['rrdbackup'] == -1 ? 'selected="selected"' : '' ?>><?=gettext('Disabled') ?></option>
                  </select>
                  <br />
                  <div class="hidden" data-for="help_for_rrdbackup">
                    <?=gettext("This will periodically backup the RRD data so it can be restored automatically on the next boot.");?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_dhcpbackup" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Periodic DHCP Leases Backup");?></td>
                <td>
                  <select name="dhcpbackup" class="selectpicker" data-style="btn-default" id="dhcpbackup">
                    <option value='0' <?= $pconfig['dhcpbackup'] == 0 ? "selected='selected'" : '' ?>><?= gettext('Power off') ?></option>
<?php for ($x = 1; $x <= 24; $x++): ?>
                    <option value="<?= $x ?>" <?= $pconfig['dhcpbackup'] == $x ? 'selected="selected"' : '';?>>
                      <?= $x == 1 ? gettext('1 hour') : sprintf(gettext('%s hours'), $x) ?>
                    </option>
<?php endfor ?>
                    <option value='-1' <?= $pconfig['dhcpbackup'] == -1 ? "selected='selected'" : '' ?>><?= gettext('Disabled') ?></option>
                  </select>
                  <div class="hidden" data-for="help_for_dhcpbackup">
                    <?=gettext("This will periodically backup the DHCP leases data so it can be restored automatically on the next boot.");?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_netflowbackup" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Periodic NetFlow Backup");?></td>
                <td>
                  <select name="netflowbackup" class="selectpicker" data-style="btn-default" id="netflowbackup">
                    <option value='0' <?= $pconfig['netflowbackup'] == 0 ? 'selected="selected"' : '' ?>><?= gettext('Power off') ?></option>
<?php for ($x = 1; $x <= 24; $x++): ?>
                    <option value="<?= $x ?>" <?= $pconfig['netflowbackup'] == $x ? 'selected="selected"' : '';?>>
                      <?= $x == 1 ? gettext('1 hour') : sprintf(gettext('%s hours'), $x) ?>
                    </option>
<?php endfor ?>
                    <option value='-1' <?= $pconfig['netflowbackup'] == -1 ? 'selected="selected"' : '' ?>><?= gettext('Disabled') ?></option>
                  </select>
                  <div class="hidden" data-for="help_for_netflowbackup">
                    <?=gettext("This will periodically backup the NetFlow data aggregation so it can be restored automatically on the next boot.");?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_captiveportalbackup" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Periodic Captive Portal Backup");?></td>
                <td>
                  <select name="captiveportalbackup" class="selectpicker" data-style="btn-default" id="captiveportalbackup">
                    <option value='0' <?= $pconfig['captiveportalbackup'] == 0 ? 'selected="selected"' : '' ?>><?= gettext('Power off') ?></option>
<?php for ($x = 1; $x <= 24; $x++): ?>
                    <option value="<?= $x ?>" <?= $pconfig['captiveportalbackup'] == $x ? 'selected="selected"' : '';?>>
                      <?= $x == 1 ? gettext('1 hour') : sprintf(gettext('%s hours'), $x) ?>
                    </option>
<?php endfor ?>
                    <option value='-1' <?= $pconfig['captiveportalbackup'] == -1 ? 'selected="selected"' : '' ?>><?= gettext('Disabled') ?></option>
                  </select>
                  <div class="hidden" data-for="help_for_captiveportalbackup">
                    <?=gettext("This will periodically backup the captive portal session data so it can be restored automatically on the next boot.");?>
                  </div>
                </td>
              </tr>
            </table>
          </div>
          <div class="content-box tab-content table-responsive __mb">
            <table class="table table-striped opnsense_standard_table_form">
              <tr>
                <td style="width:22%"><strong><?= gettext('Power Savings') ?></strong></td>
                <td style="width:78%"></td>
              </tr>
              <tr>
                <td><a id="help_for_powerd_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Use PowerD"); ?></td>
                <td>
                  <input name="powerd_enable" type="checkbox" id="powerd_enable" value="yes" <?=!empty($pconfig['powerd_enable']) ? "checked=\"checked\"" : "";?> />
                  <div class="hidden" data-for="help_for_powerd_enable">
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
                <td><i class="fa fa-info-circle text-muted"></i> <?=gettext('On AC Power Mode') ?></td>
                <td>
                  <select name="powerd_ac_mode" class="selectpicker" data-style="btn-default">
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
                <td><i class="fa fa-info-circle text-muted"></i> <?=gettext('On Battery Power Mode') ?></td>
                <td>
                  <select name="powerd_battery_mode" class="selectpicker" data-style="btn-default">
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
                <td><a id="help_for_powerd_normal_mode" href="#" class="showhelp"><i class="fa fa-info-circle text-circle"></i></a> <?=gettext('On Normal Power Mode'); ?></td>
                <td>
                  <select name="powerd_normal_mode" class="selectpicker" data-style="btn-default">
                    <option value="hadp"<?=$pconfig['powerd_normal_mode']=="hadp" ? "selected=\"selected\"" : "";?>>
                      <?=gettext("Hiadaptive");?>
                    </option>
                    <option value="adp" <?=$pconfig['powerd_normal_mode']=="adp" ? "selected=\"selected\"" : "";?>>
                      <?=gettext("Adaptive");?>
                    </option>
                    <option value="min" <?=$pconfig['powerd_normal_mode']=="min" ? "selected=\"selected\"" :"";?>>
                      <?=gettext("Minimum");?>
                    </option>
                    <option value="max" <?=$pconfig['powerd_normal_mode']=="max" ? "selected=\"selected\"" : "";?>>
                      <?=gettext("Maximum");?>
                    </option>
                  </select>
                  <div class="hidden" data-for="help_for_powerd_normal_mode">
                    <?=gettext("If the powerd utility can not determine the power state it uses \"normal\" for control."); ?>
                  </div>
                </td>
              </tr>
            </table>
          </div>
          <div class="content-box tab-content table-responsive __mb">
            <table class="table table-striped opnsense_standard_table_form">
              <tr>
                <td colspan="2"><strong><?= gettext('Disk / Memory Settings (reboot to apply changes)') ?></strong></td>
              </tr>
              <tr>
                <td style="width:22%"><i class="fa fa-info-circle text-muted"></i> <?=gettext('Swap file'); ?></td>
                <td style="width=78%">
                  <input name="use_swap_file" type="checkbox" id="use_swap_file" value="yes" <?=!empty($pconfig['use_swap_file']) ? 'checked="checked"' : '';?>/>
                  <?= gettext('Add a 2 GB swap file to the system') ?>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_use_mfs_var" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('/var RAM disk'); ?></td>
                <td>
                  <input name="use_mfs_var" type="checkbox" id="use_mfs_var" value="yes" <?=!empty($pconfig['use_mfs_var']) ? 'checked="checked"' : '';?>/>
                  <?=gettext("Use memory file system for /var"); ?>
                  <div class="hidden" data-for="help_for_use_mfs_var">
                    <?=gettext("Set this if you wish to use /var as a RAM disk (memory file system disks) " .
                      "rather than using the hard disk. Setting this will cause the data /var to be lost on reboot, including log data."); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_use_mfs_tmp" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('/tmp RAM disk'); ?></td>
                <td>
                  <input name="use_mfs_tmp" type="checkbox" id="use_mfs_tmp" value="yes" <?=!empty($pconfig['use_mfs_tmp']) ? 'checked="checked"' : '';?>/>
                  <?=gettext('Use memory file system for /tmp'); ?>
                  <div class="hidden" data-for="help_for_use_mfs_tmp">
                    <?= gettext('Set this if you wish to use /tmp as a RAM disk (memory file system disk) rather than using the hard disk.') ?>
                  </div>
                </td>
              </tr>
            </table>
          </div>
          <div class="content-box tab-content table-responsive __mb">
            <table class="table table-striped opnsense_standard_table_form">
                <tr>
                    <td style="width:22%"><strong><?= gettext('System Sounds') ?></strong></td>
                    <td style="width:78%"></td>
                </tr>
                <tr>
                    <td><a id="help_for_disablebeep" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Startup/Shutdown Sound"); ?></td>
                    <td>
                        <input name="disablebeep" type="checkbox" id="disablebeep" value="yes" <?=!empty($pconfig['disablebeep']) ? 'checked="checked"' : '';?>/>
                        <?=gettext("Disable the startup/shutdown beep"); ?>
                        <div class="hidden" data-for="help_for_disablebeep">
                            <?=gettext("When this is checked, startup and shutdown sounds will no longer play."); ?>
                        </div>
                    </td>
                </tr>
            </table>
          </div>
          <div class="content-box tab-content table-responsive">
            <table class="table table-striped opnsense_standard_table_form">
              <tr>
                <td style="width:22%"></td>
                <td style="width:78%">
                  <input name="Submit" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save'));?>" />
                </td>
              </tr>
            </table>
          </div>
        </form>
      </section>
    </div>
  </div>
</section>

<?php include("foot.inc");
