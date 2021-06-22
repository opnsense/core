<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
    Copyright (C) 2013  Dagorlad
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
require_once("system.inc");
require_once("interfaces.inc");
require_once("plugins.inc.d/ntpd.inc");

config_read_array('ntpd', 'pps');

$copy_fields = array('port', 'fudge1', 'stratum', 'flag2', 'flag3', 'flag4', 'refid');
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();
    foreach ($copy_fields as $fieldname) {
        if (isset($config['ntpd']['pps'][$fieldname])) {
            $pconfig[$fieldname] = $config['ntpd']['pps'][$fieldname];
        } else {
            $pconfig[$fieldname] = null;
        }
    }
}  elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    $input_errors = array();
    if (!empty($pconfig['stratum']) && ($pconfig['stratum'] > 17 || $pconfig['stratum'] < 0 || !is_numeric($pconfig['stratum']))) {
        $input_errors[] = gettext("Clock stratum must be a number in the range 0..16");
    }

    if (count($input_errors) == 0) {
        $pps = array();
        foreach ($copy_fields as $fieldname) {
            if (!empty($pconfig[$fieldname])) {
                $pps[$fieldname] = $pconfig[$fieldname];
            }
        }
        $config['ntpd']['pps'] = $pps;
        write_config("Updated NTP PPS Settings");
        ntpd_configure_do();
        header(url_safe('Location: /services_ntpd_pps.php'));
        exit;
    }
}

$service_hook = 'ntpd';
legacy_html_escape_form_data($pconfig);
include("head.inc");
?>

<body>
<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
        <section class="col-xs-12">
          <div class="tab-content content-box col-xs-12">
            <form method="post" name="iform" id="iform">
              <div class="table-responsive">
                <table class="table table-striped opnsense_standard_table_form">
                  <thead>
                    <tr>
                      <td style="width:22%">
                        <strong><?=gettext("NTP PPS Configuration"); ?></strong>
                      </td>
                      <td style="width:78%; text-align:right">
                        <small><?=gettext("full help"); ?> </small>
                        <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                        &nbsp;&nbsp;
                      </td>
                    </tr>
                  </thead>
                  <tbody>
<?php
                    $serialports = glob("/dev/cua?[0-9]{,.[0-9]}", GLOB_BRACE);
                    if (!empty($serialports)):?>
                    <tr>
                      <td><a id="help_for_gpsport" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Serial port') ?></td>
                      <td>
                        <select name="port" class="selectpicker">
                          <option value=""><?=gettext("none");?></option>
<?php
                        foreach ($serialports as $port):?>
                          <option value="<?=substr($port,5);?>" <?=substr($port,5) === $pconfig['port'] ? 'selected="selected"' : "";?>>
                            <?=substr($port,5);?>
                          </option>
<?php
                          endforeach; ?>
                        </select>
                        <div class="hidden" data-for="help_for_gpsport">
                          <?=gettext("All serial ports are listed, be sure to pick the port with the PPS source attached."); ?>
                        </div>
                      </td>
                    </tr>
<?php
                    endif;?>
                    <tr>
                      <td><a id="help_for_fudge1" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Fudge time') ?> (<?=gettext("seconds");?>)</td>
                      <td>
                        <input name="fudge1" type="text" value="<?=$pconfig['fudge1'];?>" />
                        <div class="hidden" data-for="help_for_fudge1">
                          <?=gettext('Fudge time is used to specify the PPS signal offset from the actual second such as the transmission delay between the transmitter and the receiver.');?> (<?=gettext('default');?>: 0.0).
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_stratum" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Stratum') ?></td>
                      <td>
                        <input name="stratum" type="text" value="<?=$pconfig['stratum'];?>" />
                        <div class="hidden" data-for="help_for_stratum">
                          <?=gettext("(0-16)");?><br />
                          <?=gettext('This may be used to change the PPS Clock stratum');?> (<?=gettext('default');?>: 0). <?=gettext('This may be useful if, for some reason, you want ntpd to prefer a different clock and just monitor this source.'); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_flags" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Flags') ?></td>
                      <td>
                        <table class="table table-condensed">
                          <tr>
                            <td>
                              <input name="flag2" type="checkbox" <?=!empty($pconfig['flag2']) ? " checked=\"checked\"" : ""; ?> />
                            </td>
                            <td>
                              <?=gettext("Enable falling edge PPS signal processing (default: rising edge)."); ?>
                            </td>
                          </tr>
                          <tr>
                            <td>
                              <input name="flag3" type="checkbox" <?=!empty($pconfig['flag3']) ? " checked=\"checked\"" : ""; ?> />
                            </td>
                            <td>
                              <?=gettext("Enable kernel PPS clock discipline (default: disabled)."); ?>
                            </td>
                          </tr>
                          <tr>
                            <td>
                              <input name="flag4" type="checkbox" <?=!empty($pconfig['flag4']) ? " checked=\"checked\"" : ""; ?> />
                            </td>
                            <td>
                              <?=gettext("Record a timestamp once for each second, useful for constructing Allan deviation plots (default: disabled)."); ?>
                            </td>
                          </tr>
                        </table>
                        <div class="hidden" data-for="help_for_flags">
                          <?=gettext("Normally there should be no need to change these options from the defaults."); ?><br />
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_refid" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Clock ID') ?></td>
                      <td>
                        <input name="refid" type="text" value="<?=$pconfig['refid'];?>" />
                        <div class="hidden" data-for="help_for_refid">
                          <?=gettext("(1 to 4 characters)");?><br />
                          <?=gettext("This may be used to change the PPS Clock ID");?> (<?=gettext("default");?>: PPS).
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td></td>
                      <td>
                        <input name="Submit" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save'));?>" />
                      </td>
                    </tr>
                  </tbody>
                  <tfoot>
                    <tr>
                      <td colspan="2">
                        <?=gettext("Devices with a Pulse Per Second output such as radios that receive a time signal from DCF77 (DE), JJY (JP), MSF (GB) or WWVB (US) may be used as a PPS reference for NTP.");?>
                        <?=gettext("A serial GPS may also be used, but the serial GPS driver would usually be the better option.");?>
                        <?=gettext("A PPS signal only provides a reference to the change of a second, so at least one other source to number the seconds is required.");?>
                        <br />
                        <br /><strong><?=gettext("Note");?>:</strong> <?= sprintf(gettext("At least 3 additional time sources should be configured under %sServices: NTP%s to reliably supply the time of each PPS pulse."),'<a href="services_ntpd.php">', '</a>') ?>
                      </td>
                    </tr>
                  </tfoot>
                </table>
                </div>
            </form>
          </div>
        </section>
      </div>
    </div>
  </section>
<?php include("foot.inc"); ?>
