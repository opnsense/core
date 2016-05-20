<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
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

require_once("guiconfig.inc");
require_once("interfaces.inc");
require_once("services.inc");
require_once("system.inc");


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();
    if (empty($config['snmpd']) || !is_array($config['snmpd'])) {
        // set defaults (no config)
        $pconfig['rocommunity'] = "public";
        $pconfig['pollport'] = "161";
        $pconfig['mibii'] = true;
        $pconfig['netgraph'] = true;
        $pconfig['pf'] = true;
        $pconfig['hostres'] = true;
        $pconfig['bridge'] = true;
        $pconfig['ucd'] = true;
        $pconfig['regex'] = true;
    } else {
        // modules
        foreach (array('mibii', 'netgraph', 'pf', 'hostres', 'bridge', 'ucd', 'regex') as $module) {
            $pconfig[$module] = !empty($config['snmpd']['modules'][$module]);
        }
        // booleans
        $pconfig['enable'] = isset($config['snmpd']['enable']);
        $pconfig['trapenable'] = isset($config['snmpd']['trapenable']);
        // text fields
        foreach (array('rocommunity', 'pollport', 'syslocation', 'syscontact',
                       'trapserver', 'trapserverport', 'trapstring', 'bindip') as $fieldname) {
            $pconfig[$fieldname] = !empty($config['snmpd'][$fieldname]) ? $config['snmpd'][$fieldname] : null;
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    $pconfig = $_POST;
    // input validation
    if (strstr($pconfig['syslocation'],"#")) {
        $input_errors[] = gettext("Invalid character '#' in system location");
    } elseif (preg_match('/[^\x20-\x7f]/', $pconfig['syslocation'])) {
        $input_errors[] = gettext("Invalid character (non ascii) in system location");
    }

    if (strstr($pconfig['syscontact'],"#")) {
        $input_errors[] = gettext("Invalid character '#' in system contact");
    } elseif (preg_match('/[^\x20-\x7f]/', $pconfig['syscontact'])) {
        $input_errors[] = gettext("Invalid character (non ascii) in system contact");
    }

    if (strstr($pconfig['rocommunity'],"#")) {
        $input_errors[] = gettext("Invalid character '#' in read community string");
    }

    if (!empty($pconfig['enable'])) {
        $reqdfields = array("rocommunity", "pollport");
        $reqdfieldsn = array(gettext("Community"), gettext("Polling Port"));
        do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);
    }

    if (strstr($pconfig['trapstring'],"#")) {
        $input_errors[] = gettext("Invalid character '#' in SNMP trap string");
    }

    if (!empty($pconfig['trapenable'])) {
        $reqdfields = array("trapserver", "trapserverport", "trapstring");
        $reqdfieldsn = array(gettext("Trap server"), gettext("Trap server port"), gettext("Trap string"));
        do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);
    }

    if (count($input_errors) == 0) {
        // save form data
        // modules
        $snmp = array();
        $snmp['modules'] = array();
        foreach (array('mibii', 'netgraph', 'pf', 'hostres', 'bridge', 'ucd', 'regex') as $module) {
            $snmp['modules'][$module] = !empty($pconfig[$module]);
        }
        // booleans
        $snmp['enable'] = !empty($pconfig['enable']);
        $snmp['trapenable'] = !empty($pconfig['trapenable']);
        // text fields
        foreach (array('rocommunity', 'pollport', 'syslocation', 'syscontact',
                       'trapserver', 'trapserverport', 'trapstring', 'bindip') as $fieldname) {
            $snmp[$fieldname] = $pconfig[$fieldname];
        }
        $config['snmpd'] = $snmp;
        // save and apply
        write_config();
        services_snmpd_configure();
        get_std_save_message();
        header("Location: services_snmp.php");
        exit;
    }
}


$service_hook = 'bsnmpd';
legacy_html_escape_form_data($pconfig);
include("head.inc");
?>

<body>
<script type="text/javascript">
    $( document ).ready(function() {
        $("#hostres").change(function(){
            if ($('#hostres').prop('checked')) {
                $('#mibii').prop('checked',true);
            }
        })
        $("#mibii").change(function(){
            if ($('#hostres').prop('checked')) {
                $('#mibii').prop('checked',true);
            }
        })
    });
</script>

<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
        <form method="post" name="iform" id="iform">
          <section class="col-xs-12">
            <div class="content-box">
              <div class="table-responsive">
                <table class="table table-striped opnsense_standard_table_form">
                  <thead>
                    <tr>
                      <td width="22%">
                        <strong><?=gettext("SNMP Daemon");?></strong>
                      </td>
                      <td width="78%" align="right">
                        <small><?=gettext("full help"); ?> </small>
                        <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page" type="button"></i>
                        &nbsp;&nbsp;
                      </td>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Enable");?></td>
                      <td>
                       <input name="enable" id="enable" type="checkbox" value="yes" <?=!empty($pconfig['enable']) ? "checked=\"checked\"" : ""; ?> />
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_pollport" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Polling Port") ?></td>
                      <td>
                        <input name="pollport" type="text" value="<?=$pconfig['pollport'];?>" />
                        <div class="hidden" for="help_for_pollport">
                          <?=gettext("Enter the port to accept polling events on (default 161)");?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("System location");?></td>
                      <td>
                        <input name="syslocation" type="text" value="<?=$pconfig['syslocation'];?>" />
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("System contact");?></td>
                      <td>
                        <input name="syscontact" type="text" value="<?=$pconfig['syscontact'];?>" />
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_rocommunity" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Read Community String");?></td>
                      <td>
                        <input name="rocommunity" type="text" value="<?=$pconfig['rocommunity'];?>" />
                        <div class="hidden" for="help_for_rocommunity">
                          <?=gettext("The community string is like a password, restricting access to querying SNMP to hosts knowing the community string. Use a strong value here to protect from unauthorized information disclosure.");?>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </section>
          <section class="col-xs-12">
            <div class="content-box">
              <div class="table-responsive">
                <table class="table table-striped opnsense_standard_table_form">
                  <thead>
                    <tr>
                      <th colspan="2"><?=gettext("SNMP Traps");?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td width="22%"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Enable");?></td>
                      <td width="78%">
                        <input name="trapenable" type="checkbox" value="yes" <?=!empty($pconfig['trapenable']) ? "checked=\"checked\"" : ""; ?> />
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_trapserver" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Trap server");?></td>
                      <td>
                        <input name="trapserver" type="text" value="<?=$pconfig['trapserver'];?>" />
                        <div class="hidden" for="help_for_trapserver">
                          <?=gettext("Enter trap server name");?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_trapserverport" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Trap server port") ?></td>
                      <td>
                        <input name="trapserverport" type="text" id="trapserverport" size="40" value="<?=htmlspecialchars($pconfig['trapserverport']) ? htmlspecialchars($pconfig['trapserverport']) : htmlspecialchars(162);?>" />
                        <div class="hidden" for="help_for_trapserverport">
                          <?=gettext("Enter the port to send the traps to (default 162)");?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_trapstring" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Enter the SNMP trap string");?></td>
                      <td>
                        <input name="trapstring" type="text" value="<?=$pconfig['trapstring'];?>" />
                        <div class="hidden" for="help_for_trapstring">
                          <?=gettext("Trap string");?>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </section>
          <section class="col-xs-12">
            <div class="content-box">
              <div class="table-responsive">
                <table class="table table-striped opnsense_standard_table_form">
                  <thead>
                    <tr>
                      <th colspan="2"><?=gettext("Modules");?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td width="22%"><?=gettext("SNMP Modules");?></td>
                      <td width="78%">
                        <table class="table table-condensed">
                          <tr>
                            <td>
                              <input name="mibii" type="checkbox" id="mibii" value="yes" <?=!empty($pconfig['mibii']) ? "checked=\"checked\"" : ""; ?> />
                            </td>
                            <td><?=gettext("MibII"); ?></td>
                          </tr>
                          <tr>
                            <td>
                              <input name="netgraph" type="checkbox" id="netgraph" value="yes" <?=!empty($pconfig['netgraph']) ? "checked=\"checked\"" : ""; ?> />
                            </td>
                            <td><?=gettext("Netgraph"); ?></td>
                          </tr>
                          <tr>
                            <td>
                              <input name="pf" type="checkbox" id="pf" value="yes" <?=!empty($pconfig['pf']) ? "checked=\"checked\"" : ""; ?> />
                            </td>
                            <td><?=gettext("PF"); ?></td>
                          </tr>
                          <tr>
                            <td>
                              <input name="hostres" type="checkbox" id="hostres" value="yes" <?=!empty($pconfig['hostres']) ? "checked=\"checked\"" : ""; ?> />
                            </td>
                            <td><?=gettext("Host Resources (Requires MibII)");?></td>
                          </tr>
                          <tr>
                            <td>
                              <input name="ucd" type="checkbox" id="ucd" value="yes" <?=!empty($pconfig['ucd']) ? "checked=\"checked\"" : ""; ?> />
                            </td>
                            <td><?=gettext("UCD"); ?></td>
                          </tr>
                          <tr>
                            <td>
                              <input name="regex" type="checkbox" id="regex" value="yes" <?=!empty($pconfig['regex']) ? "checked=\"checked\"" : ""; ?> />
                            </td>
                            <td><?=gettext("Regex"); ?></td>
                          </tr>
                        </table>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </section>
          <section class="col-xs-12">
            <div class="content-box">
              <div class="table-responsive">
                <table class="table table-striped">
                  <thead>
                    <tr>
                      <th colspan="2"><?=gettext("Interface Binding");?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td><?=gettext("Bind Interface"); ?></td>
                      <td>
                        <select name="bindip" class="selectpicker">
                          <option value=""><?= gettext('All') ?></option>
<?php
                          foreach (get_possible_listen_ips() as $lip):?>

                          <option value="<?=$lip['value'];?>" <?=$lip['value'] == $pconfig['bindip'] ? "selected=\"selected\"" : "";?>>
                            <?=htmlspecialchars($lip['name']);?>
                          </option>
<?php
                          endforeach; ?>
                        </select>
                      </td>
                    </tr>
                    <tr>
                     <td width="22%" valign="top">&nbsp;</td>
                     <td width="78%">
                       <input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" />
                     </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </section>
        </form>
      </div>
    </div>
  </section>
<?php include("foot.inc"); ?>
