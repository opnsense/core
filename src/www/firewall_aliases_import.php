<?php

/*
    Copyright (C) 2014 Deciso B.V.
    Copyright (C) 2005 Scott Ullrich <sullrich@gmail.com>
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

config_read_array('aliases', 'alias');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // initialize form vars
    $pconfig = array("name" => null, "descr" => null, "aliasimport" => null, "type" => "network");
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // save form data
    $input_errors =  array();
    $pconfig = $_POST;
    // input validation
    $reqdfields = explode(" ", "name aliasimport");
    $reqdfieldsn = array(gettext("Name"),gettext("Aliases"));

    do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

    $valid = is_validaliasname($pconfig['name']);
    if ($valid === false) {
        $input_errors[] = sprintf(gettext('The name must be less than 32 characters long and may only consist of the following characters: %s'), 'a-z, A-Z, 0-9, _');
    } elseif ($valid === null) {
        $input_errors[] = sprintf(gettext('The name cannot be the internally reserved keyword "%s".'), $pconfig['name']);
    }

    /* check for name duplicates */
    if (is_alias($pconfig['name'])) {
        $input_errors[] = gettext("An alias with this name already exists.");
    }

    // Keywords not allowed in names
    $reserved_keywords = array();

    // Add all Load balance names to reserved_keywords
    if (isset($config['load_balancer']['lbpool'])) {
        foreach ($config['load_balancer']['lbpool'] as $lbpool) {
            $reserved_keywords[] = $lbpool['name'];
        }
    }

    $reserved_ifs = get_configured_interface_list(false, true);
    $reserved_keywords = array_merge($reserved_keywords, $reserved_ifs, $reserved_table_names);

    /* Check for reserved keyword names */
    foreach($reserved_keywords as $rk) {
        if ($rk == $pconfig['name']) {
            $input_errors[] = sprintf(gettext("Cannot use a reserved keyword as alias name %s"), $rk);
        }
    }

    /* check for name interface description conflicts */
    foreach($config['interfaces'] as $interface) {
        if($interface['descr'] == $pconfig['name']) {
            $input_errors[] = gettext("An interface description with this name already exists.");
            break;
        }
    }

    $imported_ips = array();
    $imported_descs = array();
    foreach (explode("\n", $pconfig['aliasimport']) as $impline) {
        $implinea = explode(" ",trim($impline),2);
        $impip = trim($implinea[0]);
        if (!empty($implinea[1])) {
            // trim and truncate description to max 200 characters
            $impdesc = substr(trim($implinea[1]),0, 200);
        } else {
            // no description given, use alias description
            $impdesc = trim(str_replace('|',' ' , $pconfig['descr']));
        }
        if (empty($impip)) {
            // skip empty lines
            continue;
        } elseif ($pconfig['type'] == "network") {
            // import networks
            if (strpos($impip,'-') !== false) {
                // ip range provided
                $ipaddr1 = explode('-', $impip)[0];
                $ipaddr2 = explode('-', $impip)[1];
                if (!is_ipaddr($ipaddr1)) {
                    $input_errors[] = sprintf(gettext("%s is not an IP address. Please correct the error to continue"), $ipaddr1);
                } elseif (!is_ipaddr($ipaddr2)) {
                    $input_errors[] = sprintf(gettext("%s is not an IP address. Please correct the error to continue"), $ipaddr2);
                } else {
                    foreach (ip_range_to_subnet_array($ipaddr1, $ipaddr2) as $network) {
                        $imported_ips[] = $network;
                        $imported_descs[] = $impdesc;
                    }
                }
            } else {
                // single ip or network
                if (!is_ipaddr($impip) && !is_subnet($impip)) {
                    $input_errors[] = sprintf(gettext("%s is not an IP address. Please correct the error to continue"), $impip);
                } else {
                    $imported_ips[] = $impip;
                    $imported_descs[] = $impdesc;
                }
            }
        } else {
            // import hosts
            if (!is_hostname($impip)) {
                $input_errors[] = sprintf(gettext("%s is not an IP address or hostname. Please correct the error to continue"), $impip);
            } else {
                $imported_ips[] = $impip;
                $imported_descs[] = $impdesc;
            }
        }
    }
    if (count($input_errors) == 0) {
        // create output structure and serialize to config
        $alias = array();
        $alias['address'] = implode(" ", $imported_ips);
        $alias['detail'] = implode("||", $imported_descs);
        $alias['name'] = $pconfig['name'];
        $alias['type'] = $pconfig['type'];
        $alias['descr'] = $pconfig['descr'];
        $config['aliases']['alias'][] = $alias;

        // Sort list
        $config['aliases']['alias'] = msort($config['aliases']['alias'], "name");

        write_config();
        mark_subsystem_dirty('aliases');
        header(url_safe('Location: /firewall_aliases.php'));
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
        <section class="col-xs-12">
          <div class="content-box tab-content">
            <form method="post" name="iform">
              <table class="table table-striped opnsense_standard_table_form">
                <tr>
                  <td style="width:22%"><strong><?=gettext("Alias Import");?></strong></td>
                  <td style="width:78%; text-align:right">
                    <small><?=gettext("full help"); ?> </small>
                    <i class="fa fa-toggle-off text-danger" style="cursor: pointer;" id="show_all_help_page"></i>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_type" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Type"); ?></td>
                  <td>
                    <select name="type" class="form-control">
                      <option value="host" <?=$pconfig['type'] == "host" ? "selected=\"selected\"" : ""; ?>><?=gettext("Host(s)"); ?></option>
                      <option value="network" <?=$pconfig['type'] == "network" ? "selected=\"selected\"" : ""; ?>><?=gettext("Network(s)"); ?></option>
                    </select>
                    <div class="hidden" data-for="help_for_type">
                      <span class="text-info">
                        <?=gettext("Networks")?><br/>
                      </span>
                      <small>
                        <?=gettext("Networks are specified in CIDR format. Select the CIDR mask that pertains to each entry. /32 specifies a single IPv4 host, /128 specifies a single IPv6 host, /24 specifies 255.255.255.0, /64 specifies a normal IPv6 network, etc. Hostnames (FQDNs) may also be specified, using a /32 mask for IPv4 or /128 for IPv6.");?>
                        <br/>
                      </small>
                      <span class="text-info">
                        <?=gettext("Hosts")?><br/>
                      </span>
                      <small>
                        <?=gettext("Enter as many hosts as you would like. Hosts must be specified by their IP address or fully qualified domain name (FQDN). FQDN hostnames are periodically re-resolved and updated. If multiple IPs are returned by a DNS query, all are used.");?>
                        <br/>
                      </small>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td style="width:22%"><a id="help_for_name" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Name') ?></td>
                  <td style="width:78%">
                    <input name="name" type="text" class="form-control unknown" size="40" maxlength="31" value="<?=$pconfig['name'];?>" />
                    <div class="hidden" data-for="help_for_name">
                      <?=gettext("The name of the alias may only consist of the characters \"a-z, A-Z and 0-9\"."); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_description" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description"); ?></td>
                  <td>
                    <input name="descr" type="text" value="<?=$pconfig['descr'];?>" />
                    <div class="hidden" data-for="help_for_description">
                      <?=gettext("You may enter a description here for your reference (not parsed)"); ?>.
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_alias" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Aliases') ?></td>
                  <td>
                    <textarea name="aliasimport" rows="15" cols="40"><?=$pconfig['aliasimport'];?></textarea>
                    <div class="hidden" data-for="help_for_alias">
                      <?=gettext("Paste in the aliases to import separated by a carriage return. Common examples are lists of IPs, networks, blacklists, etc."); ?>
                      <br />
                      <?=gettext("The list may contain IP addresses, with or without CIDR prefix, IP ranges, blank lines (ignored) and an optional description after each IP. e.g.:"); ?>
                      <code>
                        <br/>172.16.1.2
                        <br/>172.16.0.0/24
                        <br/>10.11.12.100-10.11.12.200
                        <br/>192.168.1.254 Home router
                        <br/>10.20.0.0/16 Office network
                        <br/>10.40.1.10-10.40.1.19 Managed switches
                      </code>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td>&nbsp;</td>
                  <td>
                    <input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save"); ?>" />
                    <input type="button" class="btn btn-default" value="<?=gettext("Cancel");?>"
                           onclick="window.location.href='<?=(isset($_SERVER['HTTP_REFERER']) ? html_safe($_SERVER['HTTP_REFERER']) : '/firewall_aliases.php');?>'" />
                  </td>
                </tr>
              </table>
            </form>
          </div>
        </section>
      </div>
    </div>
  </section>
<?php include("foot.inc"); ?>
