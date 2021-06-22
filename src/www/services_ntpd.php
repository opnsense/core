<?php

/*
 * Copyright (C) 2014-2016 Deciso B.V.
 * Copyright (C) 2013 Dagorlad
 * Copyright (C) 2012 Jim Pingle <jimp@pfsense.org>
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
require_once("rrd.inc");
require_once("system.inc");
require_once("interfaces.inc");
require_once("plugins.inc.d/ntpd.inc");

$a_ntpd = &config_read_array('ntpd');

$copy_fields = [
    'clientmode',
    'clockstats',
    'interface',
    'kod',
    'leapsec',
    'logpeer',
    'logsys',
    'loopstats',
    'nomodify',
    'nopeer',
    'noquery',
    'noserve',
    'notrap',
    'orphan',
    'peerstats',
    'statsgraph',
];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();

    foreach ($copy_fields as $fieldname) {
        if (isset($a_ntpd[$fieldname])) {
            $pconfig[$fieldname] = $a_ntpd[$fieldname];
        }
    }

    // base64 encoded
    $pconfig['leapsec'] = base64_decode(chunk_split($pconfig['leapsec']));

    // array types
    $pconfig['interface'] = !empty($pconfig['interface']) ? explode(",", $pconfig['interface']) : array();

    // text types
    $pconfig['custom_options'] = !empty($a_ntpd['custom_options']) ? $a_ntpd['custom_options'] : '';

    // parse timeservers
    $pconfig['timeservers_host'] = array();
    $pconfig['timeservers_noselect'] = array();
    $pconfig['timeservers_prefer'] = array();
    if (!empty($config['system']['timeservers'])) {
        $pconfig['timeservers_noselect'] = !empty($a_ntpd['noselect']) ? explode(' ', $a_ntpd['noselect']) : array();
        $pconfig['timeservers_prefer'] = !empty($a_ntpd['prefer']) ? explode(' ', $a_ntpd['prefer']) : array();
        $pconfig['timeservers_host'] = explode(' ', $config['system']['timeservers']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    $input_errors = array();
    if (!empty($pconfig['orphan']) && ($pconfig['orphan'] < 0 || $pconfig['orphan'] > 15 || !is_numeric($pconfig['orphan']))) {
        $input_errors[] = gettext("Orphan mode must be a value between 0..15");
    }
    $prev_opt = !empty($a_ntpd['custom_options']) ? $a_ntpd['custom_options'] : "";
    if ($prev_opt != str_replace("\r\n", "\n", $pconfig['custom_options']) && !userIsAdmin($_SESSION['Username'])) {
        $input_errors[] = gettext('Advanced options may only be edited by system administrators due to the increased possibility of privilege escalation.');
    }

    // swap fields, really stupid field usage which we are not going to change now....
    foreach (array('kod', 'nomodify', 'nopeer', 'notrap') as $fieldname) {
        $pconfig[$fieldname] = empty($pconfig[$fieldname]);
    }

    if (count($input_errors) == 0) {
        // copy fields
        foreach ($copy_fields as $fieldname) {
            if (!empty($pconfig[$fieldname])) {
                $a_ntpd[$fieldname] = $pconfig[$fieldname];
            } elseif (isset($a_ntpd[$fieldname])) {
                unset($a_ntpd[$fieldname]);
            }
        }

        // list types
        $config['system']['timeservers'] = trim(implode(' ', $pconfig['timeservers_host']));
        $a_ntpd['noselect'] = !empty($pconfig['timeservers_noselect']) ? trim(implode(' ', $pconfig['timeservers_noselect'])) : null;
        $a_ntpd['prefer'] = !empty($pconfig['timeservers_prefer']) ? trim(implode(' ', $pconfig['timeservers_prefer'])) : null;
        $a_ntpd['interface'] = !empty($pconfig['interface']) ? implode(',', $pconfig['interface']) : null;

        // unset empty
        foreach (array('noselect', 'prefer', 'interface') as $fieldname) {
            if (empty($a_ntpd[$fieldname])) {
                unset($a_ntpd[$fieldname]);
            }
        }

        if (empty($config['system']['timeservers'])) {
            unset($config['system']['timeservers']);
        }

        if (!empty($pconfig['leapsec'])) {
            $a_ntpd['leapsec'] = base64_encode($a_ntpd['leapsec']);
        } elseif(isset($a_ntpd['leapsec'])) {
            unset($a_ntpd['leapsec']);
        }

        if (!empty($pconfig['custom_options'])) {
            $a_ntpd['custom_options'] = str_replace("\r\n", "\n", $pconfig['custom_options']);
        } elseif (isset($a_ntpd['custom_options'])) {
            unset($a_ntpd['custom_options']);
        }

        if (is_uploaded_file($_FILES['leapfile']['tmp_name'])) {
            $a_ntpd['leapsec'] = base64_encode(file_get_contents($_FILES['leapfile']['tmp_name']));
        }

        write_config("Updated NTP Server Settings");

        rrd_configure();
        ntpd_configure_do();
	system_cron_configure();

        header(url_safe('Location: /services_ntpd.php'));
        exit;
    }
}

$service_hook = 'ntpd';
legacy_html_escape_form_data($pconfig);

include("head.inc");

?>
<body>

<script>
  $( document ).ready(function() {
    $("#showstatisticsbox").click(function(event){
        $("#showstatisticsbox").parent().hide();
        $("#showstatistics").show();
    });
    $("#showrestrictbox").click(function(event){
        $("#showrestrictbox").parent().hide();
        $("#showrestrict").show();
    });
    $("#showleapsecbox").click(function(event){
        $("#showleapsecbox").parent().hide();
        $("#showleapsec").show();
    });
    $("#show_advanced_ntpd").click(function(event){
      $("#showadvbox").hide();
      $("#showadv").show();
    });
    if ($("#custom_options").val() != "") {
        $("#show_advanced_ntpd").click();
    }

    /**
     *  Aliases
     */
    function removeRow() {
        if ( $('#timeservers_table > tbody > tr').length == 1 ) {
            $('#timeservers_table > tbody > tr:last > td > input').each(function(){
              $(this).val("");
              $(this).prop('checked', false);
            });
        } else {
            $(this).parent().parent().remove();
        }
    }
    // add new detail record
    $("#addNew").click(function(){
        // copy last row and reset values
        $('#timeservers_table > tbody').append('<tr>'+$('#timeservers_table > tbody > tr:last').html()+'</tr>');
        $('#timeservers_table > tbody > tr:last > td > input').each(function(){
            $(this).val("");
            $(this).prop('checked', false);
        });
        $(".act-removerow").click(removeRow);
    });
    $(".act-removerow").click(removeRow);

    // on submit form, set checkbox values
    $("#iform").submit(function(event){
        $('#timeservers_table > tbody > tr').each(function(){
            var timesrv = $(this).find("td > input:first").val();
            $(this).find(".ts_checkbox").each(function(){
                $(this).val(timesrv);
            });
        });
    });
  });
</script>

<?php include("fbegin.inc"); ?>
<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
      <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
      <section class="col-xs-12">
        <div class="tab-content content-box col-xs-12">
          <form method="post" name="iform" id="iform" enctype="multipart/form-data" accept-charset="utf-8">
            <div class="table-responsive">
              <table class="table table-striped opnsense_standard_table_form">
                <thead>
                  <tr>
                    <td style="width:22%">
                      <strong><?=gettext("NTP Server Configuration"); ?></strong>
                    </td>
                    <td style="width:78%; text-align:right">
                      <small><?=gettext("full help"); ?> </small>
                      <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                      &nbsp;&nbsp;
                    </td>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td><a id="help_for_timeservers" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Time servers') ?></td>
                    <td>
                      <table class="table table-striped table-condensed" id="timeservers_table">
                        <thead>
                          <tr>
                            <th></th>
                            <th><?=gettext("Network"); ?></th>
                            <th><?=gettext("Prefer"); ?></th>
                            <th><?=gettext("Do not use"); ?></th>
                          </tr>
                        </thead>
                        <tbody>
<?php
                        if (count($pconfig['timeservers_host']) == 0 ) {
                            $pconfig['timeservers_host'][] = "";
                        }
                        foreach($pconfig['timeservers_host'] as $item_idx => $timeserver):?>
                          <tr>
                            <td>
                              <div style="cursor:pointer;" class="act-removerow btn btn-default btn-xs"><i class="fa fa-minus fa-fw"></i></div>
                            </td>
                            <td>
                              <input name="timeservers_host[]" type="text" value="<?=$timeserver;?>" />
                            </td>
                            <td>
                              <input name="timeservers_prefer[]" class="ts_checkbox" type="checkbox" value="<?=$timeserver;?>" <?= !empty($pconfig['timeservers_prefer']) && in_array($timeserver, $pconfig['timeservers_prefer']) ? 'checked="checked"' : '' ?>/>
                            </td>
                            <td>
                              <input name="timeservers_noselect[]" class="ts_checkbox" type="checkbox" value="<?=$timeserver;?>" <?= !empty($pconfig['timeservers_noselect']) && in_array($timeserver,  $pconfig['timeservers_noselect']) ? 'checked="checked"' : '' ?>/>
                            </td>
                          </tr>
<?php
                        endforeach;?>
                        </tbody>
                        <tfoot>
                          <tr>
                            <td colspan="4">
                              <div id="addNew" style="cursor:pointer;" class="btn btn-default btn-xs"><i class="fa fa-plus fa-fw"></i></div>
                            </td>
                          </tr>
                        </tfoot>
                      </table>
                      <div class="hidden" data-for="help_for_timeservers">
                        <?=gettext('For best results three to five servers should be configured here.'); ?>
                        <br />
                        <?= gettext('The "prefer" option indicates that NTP should favor the use of this server more than all others.') ?>
                        <br />
                        <?= gettext('The "do not use" option indicates that NTP should not use this server for time, but stats for this server will be collected and displayed.') ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext('Client mode') ?></td>
                    <td>
                      <input name="clientmode" type="checkbox" id="clientmode" <?=!empty($pconfig['clientmode']) ? ' checked="checked"' : '' ?> />
                        <?= gettext('Do not persist the NTP server to synchronize time') ?>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_interfaces" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Interfaces') ?></td>
                    <td>
<?php
                    $interfaces = get_configured_interface_with_descr();
                    $carplist = get_configured_carp_interface_list();
                    foreach ($carplist as $cif => $carpip) {
                        $interfaces[$cif] = $carpip." (".get_vip_descr($carpip).")";
                    }
                    $aliaslist = get_configured_ip_aliases_list();
                    foreach ($aliaslist as $aliasip => $aliasif) {
                        $interfaces[$aliasip] = $aliasip." (".get_vip_descr($aliasip).")";
                    }?>
                      <select id="interface" name="interface[]" multiple="multiple" class="selectpicker" title="<?= html_safe(gettext('All (recommended)')) ?>">
<?php
                      foreach ($interfaces as $iface => $ifacename):
                          if (!is_ipaddr(get_interface_ip($iface)) && !is_ipaddr($iface)) {
                              continue;
                          }?>
                          <option value="<?=$iface;?>" <?= !empty($pconfig['interface']) && in_array($iface, $pconfig['interface']) ? 'selected="selected"' : '' ?>>
                              <?=htmlspecialchars($ifacename);?>
                          </option>
<?php
                      endforeach;?>
                      </select>
                      <div class="hidden" data-for="help_for_interfaces">
                        <?=gettext("Interfaces without an IP address will not be shown."); ?>
                        <br /><?=gettext("Selecting no interfaces will listen on all interfaces with a wildcard."); ?>
                        <br /><?=gettext("Selecting all interfaces will explicitly listen on only the interfaces/IPs specified."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_orphan" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Orphan mode') ?></td>
                    <td>
                      <input name="orphan" type="text" value="<?=$pconfig['orphan']?>" />
                      <div class="hidden" data-for="help_for_orphan">
                        <?=gettext("(0-15)");?><br />
                        <?=gettext("Orphan mode allows the system clock to be used when no other clocks are available. The number here specifies the stratum reported during orphan mode and should normally be set to a number high enough to insure that any other servers available to clients are preferred over this server. (default: 12)."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext('NTP graphs') ?></td>
                    <td>
                      <input name="statsgraph" type="checkbox" id="statsgraph" <?=!empty($pconfig['statsgraph']) ? " checked=\"checked\"" : ""; ?> />
                      <?= gettext('Enable RRD graphs of NTP statistics (default: disabled).') ?>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_syslog" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Syslog logging') ?></td>
                    <td>
                      <input name="logpeer" type="checkbox" <?=!empty($pconfig['logpeer']) ? " checked=\"checked\"" : ""; ?> />
                      <?=gettext("Enable logging of peer messages (default: disabled)."); ?>
                      <br />
                      <input name="logsys" type="checkbox" <?=!empty($pconfig['logsys']) ? " checked=\"checked\"" : ""; ?> />
                      <?=gettext("Enable logging of system messages (default: disabled)."); ?>
                      <div class="hidden" data-for="help_for_syslog">
                        <?=gettext("These options enable additional messages from NTP to be written to the System Log");?> (<a href="/ui/diagnostics/log/core/ntpd"><?=gettext("Status > System Logs > NTP"); ?></a>).
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext('Statistics logging') ?></td>
                    <td>
                      <div>
                        <input class="btn btn-default btn-xs" id="showstatisticsbox" type="button" value="<?= html_safe(gettext('Advanced')) ?>" /> - <?=gettext("Show statistics logging options");?>
                      </div>
                      <div id="showstatistics" style="display:none">
                      <?= gettext("These options will create persistent daily log files in /var/log/ntp.") ?>
                      <br /><br />
                      <input name="clockstats" type="checkbox" id="clockstats"<?=!empty($pconfig['clockstats']) ? " checked=\"checked\"" : ""; ?> />
                      <?=gettext("Enable logging of reference clock statistics (default: disabled)."); ?>
                      <br />
                      <input name="loopstats" type="checkbox" id="loopstats"<?=!empty($pconfig['loopstats']) ? " checked=\"checked\"" : ""; ?> />
                      <?=gettext("Enable logging of clock discipline statistics (default: disabled)."); ?>
                      <br />
                      <input name="peerstats" type="checkbox" id="peerstats"<?=!empty($pconfig['peerstats']) ? " checked=\"checked\"" : ""; ?> />
                      <?=gettext("Enable logging of NTP peer statistics (default: disabled)."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext('Access restrictions') ?></td>
                    <td>
                      <div>
                      <input type="button" id="showrestrictbox" class="btn btn-default btn-xs" value="<?= html_safe(gettext('Advanced')) ?>" /> - <?=gettext("Show access restriction options");?>
                      </div>
                      <div id="showrestrict" style="display:none">
                      <?=gettext("These options control access to NTP from the WAN."); ?>
                      <br /><br />
                      <input name="kod" type="checkbox" id="kod"<?=empty($pconfig['kod']) ? " checked=\"checked\"" : ""; ?> />
                      <?=gettext("Enable Kiss-o'-death packets (default: enabled)."); ?>
                      <br />
                      <input name="nomodify" type="checkbox" id="nomodify"<?=empty($pconfig['nomodify']) ? " checked=\"checked\"" : ""; ?> />
                      <?=gettext("Deny state modifications (i.e. run time configuration) by ntpq and ntpdc (default: enabled)."); ?>
                      <br />
                      <input name="noquery" type="checkbox" id="noquery"<?=!empty($pconfig['noquery']) ? " checked=\"checked\"" : ""; ?> />
                      <?=gettext("Disable ntpq and ntpdc queries (default: disabled)."); ?>
                      <br />
                      <input name="noserve" type="checkbox" id="noserve"<?=!empty($pconfig['noserve']) ? " checked=\"checked\"" : ""; ?> />
                      <?=gettext("Disable all except ntpq and ntpdc queries (default: disabled)."); ?>
                      <br />
                      <input name="nopeer" type="checkbox" id="nopeer"<?=empty($pconfig['nopeer']) ? " checked=\"checked\"" : ""; ?> />
                      <?=gettext("Deny packets that attempt a peer association (default: enabled)."); ?>
                      <br />
                      <input name="notrap" type="checkbox" id="notrap"<?=empty($pconfig['notrap']) ? " checked=\"checked\"" : ""; ?> />
                      <?=gettext("Deny mode 6 control message trap service (default: enabled)."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext('Leap seconds') ?></td>
                    <td>
                      <div>
                        <input type="button" id="showleapsecbox" class="btn btn-default btn-xs" value="<?= html_safe(gettext('Advanced')) ?>" /> - <?=gettext("Show Leap second configuration");?>
                      </div>
                      <div id="showleapsec" style="display:none">
                        <?=gettext("A leap second file allows NTP to advertize an upcoming leap second addition or subtraction.");?>
                        <?=gettext("Normally this is only useful if this server is a stratum 1 time server.");?>
                        <br /><br />
                        <?=gettext("Enter Leap second configuration as text:");?><br />
                        <textarea name="leapsec" cols="65" rows="7"><?=$pconfig['leapsec'];?></textarea><br />
                        <strong><?=gettext("Or");?></strong>, <?=gettext("select a file to upload:");?>
                        <input type="file" name="leapfile"/>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Advanced");?></td>
                    <td>
                      <div id="showadvbox" <?=!empty($pconfig['custom_options']) ? "style='display:none'" : ""; ?>>
                        <input type="button" class="btn btn-default btn-xs" id="show_advanced_ntpd" value="<?= html_safe(gettext('Advanced')) ?>" /> - <?=gettext("Show advanced option");?>
                      </div>
                      <div id="showadv" <?=empty($pconfig['custom_options']) ? "style='display:none'" : ""; ?>>
                        <strong><?=gettext("Advanced");?><br /></strong>
                        <textarea rows="6" cols="78" name="custom_options" id="custom_options"><?=$pconfig['custom_options'];?></textarea><br />
                        <?=gettext("This option will be removed in the future due to being insecure by nature. In the mean time only full administrators are allowed to change this setting.");?><br/>
                        <?= gettext('Enter any additional options you would like to add to the network time configuration here, separated by a space or newline.') ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td style="width:22%">&nbsp;</td>
                    <td style="width:78%">
                    <input name="Submit" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save'));?>" />
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </form>
        </div>
      </section>
    </div>
  </div>
</section>
<?php include("foot.inc"); ?>
