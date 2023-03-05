<?php

/*
 * Copyright (C) 2014-2016 Deciso B.V.
 * Copyright (C) 2003-2005 Bob Zoller <bob@kludgebox.com>
 * Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>
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
require_once("system.inc");
require_once("interfaces.inc");
require_once("plugins.inc.d/dnsmasq.inc");

$a_domainOverrides = &config_read_array('dnsmasq', 'domainoverrides');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id']) && !empty($a_domainOverrides[$_GET['id']])) {
        $id = $_GET['id'];
    }
    $pconfig =  array();

    $pconfig['domain'] = isset($id) && !empty($a_domainOverrides[$id]['domain']) ? $a_domainOverrides[$id]['domain'] : null;
    $pconfig['descr'] = !empty($a_domainOverrides[$id]['descr']) ? $a_domainOverrides[$id]['descr'] : null;
    if (!isset($id) || empty($a_domainOverrides[$id]['ip'])) {
        $pconfig['ip'] = null;
        $pconfig['dnssrcip'] = null;
    } elseif (!empty($a_domainOverrides[$id]['ip'])) {
        // parse ip string, it would be better to have different fields here, but we don't want to break backwards
        // compatibility.
        $parts = explode("@", $a_domainOverrides[$id]['ip']);
        if (count($parts) > 1) {
            $pconfig['dnssrcip'] = $parts[1];
        } else {
            $pconfig['dnssrcip'] = null;
        }
        $parts = explode("#", $parts[0]);
        if (count($parts) > 1) {
            $pconfig['port'] = $parts[1];
        } else {
            $pconfig['port'] =  null;
        }
        $pconfig['ip'] = $parts[0];

    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['id']) && !empty($a_domainOverrides[$_POST['id']])) {
        $id = $_POST['id'];
    }
    $input_errors= array();
    $pconfig = $_POST;

    /* input validation */
    $reqdfields = explode(" ", "domain ip");
    $reqdfieldsn = array(gettext("Domain"),gettext("IP address"));

    do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

    if (!empty($pconfig['domain']) && substr($pconfig['domain'], 0, 6) == '_msdcs') {
        $subdomainstr = substr($pconfig['domain'], 7);
        if ($subdomainstr && !is_domain($subdomainstr)) {
            $input_errors[] = gettext("A valid domain must be specified after _msdcs.");
        }
    } elseif (!empty($pconfig['domain']) && !is_domain($_POST['domain'])) {
        $input_errors[] = gettext("A valid domain must be specified.");
    }
    if (!empty($pconfig['ip']) && !is_ipaddr($pconfig['ip']) && ($pconfig['ip'] != '#') && ($pconfig['ip'] != '!')) {
        $input_errors[] = gettext("A valid IP address must be specified, or # for an exclusion or ! to not forward at all.");
    }
    if (!empty($pconfig['port']) && !is_port($pconfig['port'])) {
        $input_errors[] = gettext("A valid port number must be specified.");
    }
    if (!empty($pconfig['dnssrcip']) && !in_array($pconfig['dnssrcip'], array_keys(get_configured_ip_addresses()))) {
        $input_errors[] = gettext("An interface IP address must be specified for the DNS query source.");
    }
    if (count($input_errors) == 0) {
        $doment = array();
        $doment['domain'] = $pconfig['domain'];
        $doment['ip'] = $pconfig['ip'];
        if (!empty($pconfig['port'])) {
            $doment['ip'] .= "#" . $pconfig['port'];
        }
        if (!empty($pconfig['dnssrcip'])) {
            $doment['ip'] .= "@" . $pconfig['dnssrcip'];
        }
        $doment['descr'] = $pconfig['descr'];

        if (isset($id)) {
            $a_domainOverrides[$id] = $doment;
        } else {
            $a_domainOverrides[] = $doment;
        }
        dnsmasq_configure_do();
        plugins_configure('dhcp');
        write_config();
        header(url_safe('Location: /services_dnsmasq.php'));
        exit;
    }
}


$service_hook = 'dnsmasq';
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
            <div class="content-box">
              <form method="post" name="iform" id="iform">
                <div class="table-responsive">
                  <table class="table table-striped opnsense_standard_table_form">
                    <tr>
                      <td style="width:22%"><strong><?=gettext("Edit Domain Override entry");?></strong></td>
                      <td style="width:78%; text-align:right">
                        <small><?=gettext("full help"); ?> </small>
                        <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                      </td>
                    </tr>
                    <tr>
                      <td style="width:22%"><a id="help_for_domain" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Domain");?></td>
                      <td style="width:78%">
                        <input name="domain" type="text" value="<?=$pconfig['domain'];?>" />
                        <div class="hidden" data-for="help_for_domain">
                          <?=gettext("Domain to override (NOTE: this does not have to be a valid TLD!)"); ?><br />
                          <?=gettext("e.g."); ?> <em><?=gettext("test"); ?></em> <?=gettext("or"); ?> <em>mycompany.localdomain</em> <?=gettext("or"); ?> <em>1.168.192.in-addr.arpa</em>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_ip" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IP address");?></td>
                      <td>
                        <input name="ip" type="text" value="<?=$pconfig['ip'];?>" />
                        <div class="hidden" data-for="help_for_ip">
                          <?=gettext("IP address of the authoritative DNS server for this domain"); ?><br />
                          <?=gettext("e.g."); ?> <em>192.168.100.100</em><br /><?=gettext("Or enter # for an exclusion to pass through this host/subdomain to standard nameservers instead of a previous override."); ?><br /><?=gettext("Or enter ! for lookups for this host/subdomain to NOT be forwarded anywhere."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_port" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Port");?></td>
                      <td>
                        <input name="port" type="text" value="<?=$pconfig['port'];?>" />
                        <div class="hidden" data-for="help_for_port">
                          <?=gettext("Specify a non standard port number here, leave blank for default"); ?><br />
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_dnssrcip" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Source IP");?></td>
                      <td>
                        <input name="dnssrcip" type="text" value="<?=$pconfig['dnssrcip'];?>" />
                        <div class="hidden" data-for="help_for_dnssrcip">
                          <?=gettext("Source IP address for queries to the DNS server for the override domain."); ?><br />
                          <?=gettext("Leave blank unless your DNS server is accessed through a VPN tunnel."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_descr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description");?></td>
                      <td>
                        <input name="descr" type="text" value="<?=$pconfig['descr'];?>" />
                        <div class="hidden" data-for="help_for_descr">
                          <?=gettext("You may enter a description here"." for your reference (not parsed).");?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td>&nbsp;</td>
                      <td>
                        <input name="Submit" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save'));?>" />
                        <input type="button" class="btn btn-default" value="<?=html_safe(gettext('Cancel'));?>" onclick="window.location.href='/services_dnsmasq.php'" />
                        <?php if (isset($id)): ?>
                        <input name="id" type="hidden" value="<?=$id;?>" />
                        <?php endif; ?>
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
