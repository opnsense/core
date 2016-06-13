<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
    Copyright (C) 2008 Ermal Luçi
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
require_once("services.inc");
require_once("interfaces.inc");
require_once("pfsense-utils.inc");

if (!isset($config['dnsupdates']['dnsupdate'])) {
    $config['dnsupdates']['dnsupdate'] = array();
}
$a_rfc2136 = &$config['dnsupdates']['dnsupdate'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id']) && !empty($a_rfc2136[$_GET['id']])) {
        $id = $_GET['id'];
    }

    if (isset($id)) {
        $pconfig['enable'] = isset($a_rfc2136[$id]['enable']);
    } else {
        $pconfig['enable'] = true;
    }
    $pconfig['host'] = isset($id) && !empty($a_rfc2136[$id]['host']) ? $a_rfc2136[$id]['host'] : null;
    $pconfig['ttl'] = isset($id) &&!empty($a_rfc2136[$id]['ttl']) ? $a_rfc2136[$id]['ttl'] : 60;
    $pconfig['keydata'] = isset($id) &&!empty($a_rfc2136[$id]['keydata']) ? $a_rfc2136[$id]['keydata'] : null;
    $pconfig['keyname'] = isset($id) &&!empty($a_rfc2136[$id]['keyname']) ? $a_rfc2136[$id]['keyname'] : null;
    $pconfig['keytype'] = isset($id) &&!empty($a_rfc2136[$id]['keytype']) ? $a_rfc2136[$id]['keytype'] : "zone";
    $pconfig['server'] = isset($id) &&!empty($a_rfc2136[$id]['server']) ? $a_rfc2136[$id]['server'] : null;
    $pconfig['interface'] = isset($id) &&!empty($a_rfc2136[$id]['interface']) ? $a_rfc2136[$id]['interface'] : null;
    $pconfig['descr'] = isset($id) &&!empty($a_rfc2136[$id]['descr']) ? $a_rfc2136[$id]['descr'] : null;

    $pconfig['usetcp'] = isset($a_rfc2136[$id]['usetcp']);
    $pconfig['usepublicip'] = isset($a_rfc2136[$id]['usepublicip']);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['id']) && !empty($a_rfc2136[$_POST['id']])) {
        $id = $_POST['id'];
    }
    $input_errors = array();
    $pconfig = $_POST;
    /* input validation */
    $reqdfields = array();
    $reqdfieldsn = array();
    $reqdfields = array_merge($reqdfields, explode(" ", "host ttl keyname keydata"));
    $reqdfieldsn = array_merge($reqdfieldsn, array(gettext("Hostname"), gettext("TTL"), gettext("Key name"), gettext("Key")));

    do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

    if (!empty($pconfig['host']) && !is_domain($pconfig['host'])) {
        $input_errors[] = gettext("The DNS update host name contains invalid characters.");
    }
    if (!empty($pconfig['ttl']) && !is_numericint($pconfig['ttl'])) {
        $input_errors[] = gettext("The DNS update TTL must be an integer.");
    }
    if (!empty($pconfig['keyname']) && !is_domain($pconfig['keyname'])) {
        $input_errors[] = gettext("The DNS update key name contains invalid characters.");
    }

    if (count($input_errors) == 0) {
        $rfc2136 = array();
        $rfc2136['enable'] = !empty($pconfig['enable']);
        $rfc2136['host'] = $pconfig['host'];
        $rfc2136['ttl'] = $pconfig['ttl'];
        $rfc2136['keyname'] = $pconfig['keyname'];
        $rfc2136['keytype'] = $pconfig['keytype'];
        $rfc2136['keydata'] = $pconfig['keydata'];
        $rfc2136['server'] = $pconfig['server'];
        $rfc2136['usetcp'] = !empty($pconfig['usetcp']);
        $rfc2136['usepublicip'] = !empty($pconfig['usepublicip']);
        $rfc2136['interface'] = $pconfig['interface'];
        $rfc2136['descr'] = $pconfig['descr'];

        if (isset($id)) {
            $a_rfc2136[$id] = $rfc2136;
        } else {
            $a_rfc2136[] = $rfc2136;
        }
        write_config(gettext("New/Edited RFC2136 dnsupdate entry was posted."));

        if (!empty($pconfig['force'])) {
            services_dnsupdate_process("", $rfc2136['host'], true);
        } else {
            services_dnsupdate_process();
        }
        header("Location: services_rfc2136.php");
        exit;
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
        <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
        <?php if (isset($savemsg)) print_info_box($savemsg); ?>
        <section class="col-xs-12">
          <div class="content-box">
            <form method="post" name="iform" id="iform">
              <div class="table-responsive">
                <table class="table table-striped opnsense_standard_table_form">
                  <tr>
                    <td width="22%"><strong><?=gettext("RFC 2136 client");?></strong></td>
                    <td width="78%" align="right">
                      <small><?=gettext("full help"); ?> </small>
                      <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page" type="button"></i>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Enable");?></td>
                    <td>
                      <input name="enable" type="checkbox" id="enable" value="yes" <?=!empty($pconfig['enable']) ? "checked=\"checked\"" : ""; ?> />
                    </td>
                  </tr>
                  <tr>
                   <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Interface to monitor");?></td>
                   <td>
                     <select name="interface" class="selectpicker" id="requestif">
 <?php
                      foreach (get_configured_interface_with_descr() as $if => $ifdesc):?>
                        <option value="<?=$if;?>" <?=$pconfig['interface'] == $if ? "selected=\"selected\"" : "";?>>
                          <?=htmlspecialchars($ifdesc);?>
                        </option>

<?php
                      endforeach;?>
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_host" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Hostname");?></td>
                    <td>
                      <input name="host" type="text" id="host" value="<?=$pconfig['host'];?>" />
                      <div class="hidden" for="help_for_host">
                        <?= gettext('Fully qualified hostname of the host to be updated.') ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("TTL"); ?> (<?=gettext("seconds");?>)</td>
                    <td>
                      <input name="ttl" type="text" id="ttl" value="<?=$pconfig['ttl'];?>" />
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_keyname" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Key name");?></td>
                    <td>
                      <input name="keyname" type="text" id="keyname" value="<?=$pconfig['keyname'];?>" />
                      <div class="hidden" for="help_for_keyname">
                        <?=gettext("This must match the setting on the DNS server.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Key type");?> </td>
                    <td>
                      <input name="keytype" type="radio" value="zone" <?= $pconfig['keytype'] == "zone" ? "checked=\"checked\"" :""; ?> /> <?=gettext("Zone");?> &nbsp;
                      <input name="keytype" type="radio" value="host" <?= $pconfig['keytype'] == "host" ? "checked=\"checked\"" :""; ?> /> <?=gettext("Host");?> &nbsp;
                      <input name="keytype" type="radio" value="user" <?= $pconfig['keytype'] == "user" ? "checked=\"checked\"" :""; ?> /> <?=gettext(" User");?>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_keydata" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Key");?></td>
                    <td>
                      <input name="keydata" type="text" id="keydata" size="70" value="<?=htmlspecialchars($pconfig['keydata']);?>" />
                      <div class="hidden" for="help_for_keydata">
                        <?=gettext("Paste an HMAC-MD5 key here.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Server");?></td>
                    <td>
                      <input name="server" type="text" class="formfld" id="server" size="30" value="<?=$pconfig['server'];?>" />
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Protocol");?></td>
                    <td>
                      <input name="usetcp" type="checkbox" id="usetcp" value="<?=gettext("yes");?>" <?=!empty($pconfig['usetcp']) ? "checked=\"checked\"" : ""; ?> />
                      <strong><?=gettext("Use TCP instead of UDP");?></strong>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Use Public IP");?></td>
                    <td>
                      <input name="usepublicip" type="checkbox" id="usepublicip" value="<?=gettext("yes");?>" <?=!empty($pconfig['usepublicip']) ? "checked=\"checked\"" : ""; ?> />
                      <strong><?=gettext("If the interface IP is private, attempt to fetch and use the public IP instead.");?></strong>
                    </td>
                  </tr>
                  <tr>
                    <td><?=gettext("Description");?></td>
                    <td>
                      <input name="descr" type="text" id="descr" value="<?=$pconfig['descr'];?>" />
                    </td>
                  </tr>
                  <tr>
                    <td>&nbsp;</td>
                    <td>
                      <input name="save" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" onclick="enable_change(true)" />
                      <a href="services_rfc2136.php"><input name="Cancel" type="button" class="btn btn-default" value="<?=gettext("Cancel");?>" /></a>
                      <input name="force" type="submit" class="btn btn-default" value="<?=gettext("Save &amp; Force Update");?>" onclick="enable_change(true)" />
                      <?php if (isset($id)): ?>
                      <input name="id" type="hidden" value="<?=$id;?>" />
                      <?php endif; ?>
                    </td>
                  </tr>
                  <tr>
                    <td colspan="2">
                      <?= sprintf(gettext("You must configure a DNS server in %sSystem: " .
                      "General setup%s or allow the DNS server list to be overridden " .
                      "by DHCP/PPP on WAN for dynamic DNS updates to work."),'<a href="system_general.php">', '</a>');?>
                    </td>
                  </tr>
                </table>
              </div>
            </form>
          </div>
        </section>
      </div>
    </div>
  </section>
<?php include("foot.inc"); ?>
