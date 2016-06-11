<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.
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
require_once("pfsense-utils.inc");
require_once("services.inc") ;
require_once("interfaces.inc");

/* returns true if $uname is a valid dynamic DNS username */
function is_dyndns_username($uname)
{
    if (!is_string($uname)) {
        return false;
    } elseif (preg_match("/[^a-z0-9\-.@_:]/i", $uname)) {
        return false;
    } else {
        return true;
    }
}

if (!isset($config['dyndnses']['dyndns'])) {
    $config['dyndnses']['dyndns'] = array();
}
$a_dyndns = &$config['dyndnses']['dyndns'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id']) && !empty($a_dyndns[$_GET['id']])) {
        $id = $_GET['id'];
    }
    $config_copy_fieldnames = array('username', 'password', 'host', 'mx', 'type', 'zoneid', 'ttl', 'updateurl',
                                    'resultmatch', 'requestif', 'descr', 'interface');
    foreach ($config_copy_fieldnames as $fieldname) {
        if (isset($id) && isset($a_dyndns[$id][$fieldname])) {
            $pconfig[$fieldname] = $a_dyndns[$id][$fieldname];
        } else {
            $pconfig[$fieldname] = null;
        }
    }

    if (isset($id)) {
        $pconfig['enable'] = isset($a_dyndns[$id]['enable']);
    } else {
        $pconfig['enable'] = true;
    }
    $pconfig['wildcard'] = isset($id) && isset($a_dyndns[$id]['wildcard']);
    $pconfig['verboselog'] = isset($id) && isset($a_dyndns[$id]['verboselog']);
    $pconfig['curl_ipresolve_v4'] = isset($id) && isset($a_dyndns[$id]['curl_ipresolve_v4']);
    $pconfig['curl_ssl_verifypeer'] = isset($id) && isset($a_dyndns[$id]['curl_ssl_verifypeer']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['id']) && !empty($a_dyndns[$_POST['id']])) {
        $id = $_POST['id'];
    }
    $input_errors = array();
    $pconfig = $_POST;
    if(($pconfig['type'] == "freedns" || $pconfig['type'] == "namecheap") && $pconfig['username'] == "") {
        $pconfig['username'] = "none";
    }

    /* input validation */
    $reqdfields = array();
    $reqdfieldsn = array();
    $reqdfields = array('type');
    $reqdfieldsn = array(gettext('Service type'));
    if ($pconfig['type'] != 'custom' && $pconfig['type'] != 'custom-v6') {
        $reqdfields[] = 'host';
        $reqdfieldsn[] = gettext('Hostname');
        $reqdfields[] = 'username';
        $reqdfieldsn[] = gettext('Username');
        if ($pconfig['type'] != 'duckdns') {
            $reqdfields[] = 'password';
            $reqdfieldsn[] = gettext('Password');
        }
    } else {
        $reqdfields[] = 'updateurl';
        $reqdfieldsn[] = gettext('Update URL');
    }

    do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

    if (isset($pconfig['host']) && in_array('host', $reqdfields)) {
        /* Namecheap can have a @. in hostname */
        if ($pconfig['type'] == "namecheap" && substr($pconfig['host'], 0, 2) == '@.') {
            $host_to_check = substr($pconfig['host'], 2);
        } else {
            $host_to_check = $pconfig['host'];
        }

        if (!is_domain($host_to_check)) {
            $input_errors[] = gettext("The Hostname contains invalid characters.");
        }
    }

    if (!empty($pconfig['mx']) && !is_domain($pconfig['mx'])) {
        $input_errors[] = gettext("The MX contains invalid characters.");
    }
    if ((in_array("username", $reqdfields) && !empty($pconfig['username']) && !is_dyndns_username($pconfig['username'])) || ((in_array("username", $reqdfields)) && ($pconfig['username'] == ""))) {
        $input_errors[] = gettext("The username contains invalid characters.");
    }


    if (count($input_errors) == 0) {
        $dyndns = array();
        $dyndns['type'] = $pconfig['type'];
        $dyndns['username'] = $pconfig['username'];
        $dyndns['password'] = $pconfig['password'];
        $dyndns['host'] = $pconfig['host'];
        $dyndns['mx'] = $pconfig['mx'];
        $dyndns['wildcard'] = !empty($pconfig['wildcard']);
        $dyndns['verboselog'] = !empty($pconfig['verboselog']);
        $dyndns['curl_ipresolve_v4'] = !empty($pconfig['curl_ipresolve_v4']);
        $dyndns['curl_ssl_verifypeer'] = !empty($pconfig['curl_ssl_verifypeer']);
        $dyndns['enable'] = !empty($pconfig['enable']);
        $dyndns['interface'] = $pconfig['interface'];
        $dyndns['zoneid'] = $pconfig['zoneid'];
        $dyndns['ttl'] = $pconfig['ttl'];
        $dyndns['updateurl'] = $pconfig['updateurl'];
        // Trim hard-to-type but sometimes returned characters
        $dyndns['resultmatch'] = trim($pconfig['resultmatch'], "\t\n\r");
        ($dyndns['type'] == "custom" || $dyndns['type'] == "custom-v6") ? $dyndns['requestif'] = $pconfig['requestif'] : $dyndns['requestif'] = $pconfig['interface'];
        $dyndns['descr'] = $pconfig['descr'];
        $dyndns['force'] = isset($pconfig['force']);
        if ($dyndns['username'] == "none") {
            $dyndns['username'] = "";
        }

        if (isset($id)) {
            $a_dyndns[$id] = $dyndns;
        } else {
            $a_dyndns[] = $dyndns;
            $id = count($a_dyndns) - 1;
        }

        $dyndns['id'] = $id;
        for($i = 0; $i < count($a_dyndns); $i++) {
            $a_dyndns[$i]['id'] = $i;
        }

        write_config();
        services_dyndns_configure_client($dyndns);
        header("Location: services_dyndns.php");
        exit;
    }
}


legacy_html_escape_form_data($pconfig);
include("head.inc");
?>

<body>
<?php include("fbegin.inc"); ?>
  <script type="text/javascript">
  //<![CDATA[
  function _onTypeChange(type){
    switch(type) {
      case "custom":
      case "custom-v6":
        document.getElementById("_resulttr").style.display = '';
        document.getElementById("_urltr").style.display = '';
        document.getElementById("_requestiftr").style.display = '';
        document.getElementById("_curloptions").style.display = '';
        document.getElementById("_hostnametr").style.display = '';
        document.getElementById("_mxtr").style.display = 'none';
        document.getElementById("_wildcardtr").style.display = 'none';
        document.getElementById("r53_zoneid").style.display='none';
        document.getElementById("r53_ttl").style.display='none';
        break;
      case "route53":
        document.getElementById("_resulttr").style.display = 'none';
        document.getElementById("_urltr").style.display = 'none';
        document.getElementById("_requestiftr").style.display = 'none';
        document.getElementById("_curloptions").style.display = 'none';
        document.getElementById("_hostnametr").style.display = '';
        document.getElementById("_mxtr").style.display = '';
        document.getElementById("_wildcardtr").style.display = '';
        document.getElementById("r53_zoneid").style.display='';
        document.getElementById("r53_ttl").style.display='';
        break;
      default:
        document.getElementById("_resulttr").style.display = 'none';
        document.getElementById("_urltr").style.display = 'none';
        document.getElementById("_requestiftr").style.display = 'none';
        document.getElementById("_curloptions").style.display = 'none';
        document.getElementById("_hostnametr").style.display = '';
        document.getElementById("_mxtr").style.display = '';
        document.getElementById("_wildcardtr").style.display = '';
        document.getElementById("r53_zoneid").style.display='none';
        document.getElementById("r53_ttl").style.display='none';
    }
  }
  //]]>
  </script>

  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
        <section class="col-xs-12">
          <div class="content-box">
            <form method="post" name="iform" id="iform">
              <div class="table-responsive">
                <table class="table table-striped opnsense_standard_table_form">
                  <tr>
                    <td width="22%"><strong><?= gettext("Dynamic DNS client") ?></strong></td>
                    <td width="78%" align="right">
                      <small><?= gettext("full help") ?> </small>
                      <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page" type="button"></i>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?= gettext("Enable") ?></td>
                    <td>
                      <input name="enable" type="checkbox" id="enable" value="<?= gettext("yes") ?>" <?= empty($pconfig['enable']) ? '' : 'checked="checked"' ?> />
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?= gettext("Service type") ?></td>
                    <td>
                      <select name="type" class="selectpicker" id="type" onchange="_onTypeChange(this.options[this.selectedIndex].value);">
<?php
                        foreach (services_dyndns_list() as $value => $type):?>
                                <option value="<?= $value ?>" <?= $value == $pconfig['type'] ? 'selected="selected"' : '' ?>>
                                  <?= $type ?>
                                </option>
<?php
                        endforeach;?>
                      </select>
                    </td>
                  </tr>
                  <tr>
                     <td><i class="fa fa-info-circle text-muted"></i> <?= gettext("Interface to monitor") ?></td>
                     <td>
                       <select name="interface" class="selectpicker" id="interface">
<?php
                        $iflist = get_configured_interface_with_descr();
                        $iflist = array_merge($iflist, return_gateway_groups_array());
                        foreach ($iflist as $if => $ifdesc):?>
                          <option value="<?= $if ?>" <?=$pconfig['interface'] == $if ? 'selected="selected"' : '';?>>
                            <?= is_array($ifdesc) ? $if : htmlspecialchars($ifdesc) ?>
                          </option>

<?php
                        endforeach;?>
                        </select>
                      </td>
                  </tr>
                  <tr id="_requestiftr">
                    <td><a id="help_for_requestif" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Interface to send update from") ?></td>
                    <td>
                      <select name="requestif" class="selectpicker" id="requestif">
<?php
                       $iflist = get_configured_interface_with_descr();
                       $iflist = array_merge($iflist, return_gateway_groups_array());
                       foreach ($iflist as $if => $ifdesc):?>
                         <option value="<?= $if ?>" <?= $pconfig['requestif'] == $if ? 'selected="selected"' : '' ?>>
                           <?= is_array($ifdesc) ? $if : htmlspecialchars($ifdesc) ?>
                         </option>

<?php
                       endforeach;?>
                       </select>
                       <div class="hidden" for="help_for_requestif">
                         <?= gettext("Note: This is almost always the same as the Interface to Monitor.");?>
                       </div>
                    </td>
                  </tr>
                  <tr id="_hostnametr">
                    <td><a id="help_for_host" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Hostname") ?></td>
                    <td>
                      <input name="host" type="text" id="host" value="<?= $pconfig['host'] ?>" />
                      <div class="hidden" for="help_for_host">
                        <?= gettext("Enter the complete host/domain name. example: myhost.dyndns.org") ?><br />
                        <?= gettext("For he.net tunnelbroker, enter your tunnel ID") ?>
                      </div>
                    </td>
                  </tr>
                  <tr id="_mxtr">
                    <td><a id="help_for_mx" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("MX") ?></td>
                    <td>
                      <input name="mx" type="text" id="mx" value="<?= $pconfig['mx'] ?>" />
                      <div class="hidden" for="help_for_mx">
                        <?= gettext("Note: With a dynamic DNS service you can only use a hostname, not an IP address.") ?>
                        <br />
                        <?= gettext("Set this option only if you need a special MX record. Not all services support this.") ?>
                      </div>
                    </td>
                  </tr>
                  <tr id="_wildcardtr">
                    <td><i class="fa fa-info-circle text-muted"></i> <?= gettext("Wildcards") ?></td>
                    <td>
                      <input name="wildcard" type="checkbox" id="wildcard" value="yes" <?= empty($pconfig['wildcard']) ? '' : 'checked="checked"' ?> />
                      <strong><?= gettext("Enable Wildcard") ?></strong>
                    </td>
                  </tr>
                  <tr id="_verboselogtr">
                    <td><i class="fa fa-info-circle text-muted"></i> <?= gettext("Verbose logging") ?></td>
                    <td>
                      <input name="verboselog" type="checkbox" id="verboselog" value="yes" <?= empty($pconfig['verboselog']) ? '' : 'checked="checked"' ?> />
                      <strong><?= gettext("Enable verbose logging") ?></strong>
                    </td>
                  </tr>
                  <tr id="_curloptions">
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("CURL options"); ?></td>
                    <td>
                      <input name="curl_ipresolve_v4" type="checkbox" id="curl_ipresolve_v4" value="yes" <?= empty($pconfig['curl_ipresolve_v4']) ? '' : 'checked="checked"' ?> />
                      <?= gettext("Force IPv4 resolving") ?><br />
                      <input name="curl_ssl_verifypeer" type="checkbox" id="curl_ssl_verifypeer" value="yes" <?= empty($pconfig['curl_ssl_verifypeer']) ? '' : 'checked="checked"'  ?> />
                      <?= gettext("Verify SSL peer") ?>
                    </td>
                  </tr>
                  <tr id="_usernametr">
                    <td><a id="help_for_username" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Username") ?></td>
                    <td>
                      <input name="username" type="text" id="username" value="<?= $pconfig['username'] ?>" />
                      <div class="hidden" for="help_for_username">
                        <?= gettext("Username is required for all types except Namecheap, FreeDNS and Custom Entries.");?>
                        <br /><?= gettext('Route 53: Enter your Access Key ID.') ?>
                        <br /><?= gettext('Duck DNS: Enter your Token.') ?>
                        <br /><?= gettext('For Custom Entries, Username and Password represent HTTP Authentication username and passwords.') ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_password" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Password") ?></td>
                    <td>
                      <input name="password" type="password" id="password" value="<?= $pconfig['password'] ?>" />
                      <div class="hidden" for="help_for_password">
                        <?=gettext('FreeDNS (freedns.afraid.org): Enter your "Authentication Token" provided by FreeDNS.') ?>
                        <br /><?= gettext('Route 53: Enter your Secret Access Key.') ?>
                        <br /><?= gettext('Duck DNS: Leave blank.') ?>
                      </div>
                    </td>
                  </tr>
                  <tr id="r53_zoneid" style="display:none">
                    <td><a id="help_for_zoneid" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Zone ID") ?></td>
                    <td>
                      <input name="zoneid" type="text" id="zoneid" value="<?= $pconfig['zoneid'] ?>" />
                      <div class="hidden" for="help_for_zoneid">
                        <?= gettext("Enter Zone ID that you received when you created your domain in Route 53.") ?>
                      </div>
                    </td>
                  </tr>
                  <tr id="_urltr">
                    <td><a id="help_for_updateurl" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Update URL") ?></td>
                    <td>
                      <input name="updateurl" type="text" id="updateurl" value="<?= $pconfig['updateurl'] ?>" />
                      <div class="hidden" for="help_for_updateurl">
                        <?= gettext("This is the only field required by for Custom Dynamic DNS, and is only used by Custom Entries.") ?>
                        <br />
                        <?= gettext("If you need the new IP to be included in the request, put %IP% in its place.") ?>
                      </div>
                    </td>
                  </tr>
                  <tr id="_resulttr">
                    <td><a id="help_for_resultmatch" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Result Match") ?></td>
                    <td>
                      <textarea name="resultmatch" class="formpre" id="resultmatch" cols="65" rows="7"><?= $pconfig['resultmatch'] ?></textarea>
                      <div class="hidden" for="help_for_resultmatch">
                        <?= gettext("This field is only used by Custom Dynamic DNS Entries.") ?>
                        <br />
                        <?= gettext("This field should be identical to what your dynamic DNS Provider will return if the update succeeds, leave it blank to disable checking of returned results.");?>
                        <br />
                        <?= gettext("If you need the new IP to be included in the request, put %IP% in its place.") ?>
                        <br />
                        <?= gettext("If you need to include multiple possible values, separate them with a |.  If your provider includes a |, escape it with \\|") ?>
                        <br />
                        <?= gettext("Tabs (\\t), newlines (\\n) and carriage returns (\\r) at the beginning or end of the returned results are removed before comparison.") ?>
                      </div>
                    </td>
                  </tr>
                  <tr id="r53_ttl" style="display:none">
                    <td><a id="help_for_ttl" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("TTL");?></td>
                    <td>
                      <input name="ttl" type="text" id="ttl" value="<?= $pconfig['ttl'] ?>" />
                      <div class="hidden" for="help_for_ttl">
                        <?= gettext("Choose TTL for your dns record.") ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?= gettext("Description") ?></td>
                    <td>
                      <input name="descr" type="text" id="descr" value="<?= $pconfig['descr'] ?>" />
                    </td>
                  </tr>
                  <tr>
                    <td>&nbsp;</td>
                    <td>
                      <input name="Submit" type="submit" class="btn btn-primary" value="<?= gettext("Save") ?>" onclick="enable_change(true)" />
<?php
                      if (isset($id)): ?>
                        <input name="id" type="hidden" value="<?= $id ?>" />
                        <input name="force" type="submit" class="btn btn-primary" value="<?= gettext("Save & Force Update") ?>" onclick="enable_change(true)" />
<?php
                      endif; ?>
                        <a href="services_dyndns.php" class="btn btn-default"><?= gettext("Cancel") ?></a>
                    </td>
                  </tr>
                  <tr>
                    <td>&nbsp;</td>
                    <td><span class="vexpl"><span class="red"><strong><?= gettext("Note:") ?><br />
                      </strong></span><?= sprintf(gettext("You must configure a DNS server in %sSystem:
                      General setup%s or allow the DNS server list to be overridden
                      by DHCP/PPP on WAN for dynamic DNS updates to work."),'<a href="system_general.php">','</a>');?></span></td>
                  </tr>
                </table>
              </div>
            </form>
          </div>
        </section>
      </div>
    </div>
  </section>
<script type="text/javascript">
//<![CDATA[
  _onTypeChange("<?= $pconfig['type'] ?>");
//]]>
</script>
<?php include("foot.inc"); ?>
