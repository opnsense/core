<?php

/*
 * Copyright (C) 2014-2016 Deciso B.V.
 * Copyright (C) 2003-2004 Bob Zoller <bob@kludgebox.com>
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *   notice, this list of conditions and the following disclaimer in the
 *   documentation and/or other materials provided with the distribution.
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
require_once("system.inc");
require_once("plugins.inc.d/dnsmasq.inc");

$a_dnsmasq = &config_read_array('dnsmasq');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();
    // booleans
    $pconfig['enable'] = isset($a_dnsmasq['enable']);
    $pconfig['regdhcp'] = isset($a_dnsmasq['regdhcp']);
    $pconfig['regdhcpdomain'] = !empty($a_dnsmasq['regdhcpdomain']) ? $a_dnsmasq['regdhcpdomain'] : null;
    $pconfig['regdhcpstatic'] = isset($a_dnsmasq['regdhcpstatic']);
    $pconfig['dhcpfirst'] = isset($a_dnsmasq['dhcpfirst']);
    $pconfig['strict_order'] = isset($a_dnsmasq['strict_order']);
    $pconfig['domain_needed'] = isset($a_dnsmasq['domain_needed']);
    $pconfig['no_private_reverse'] = isset($a_dnsmasq['no_private_reverse']);
    $pconfig['strictbind'] = isset($a_dnsmasq['strictbind']);
    $pconfig['dnssec'] = isset($a_dnsmasq['dnssec']);
    $pconfig['log_queries'] = isset($a_dnsmasq['log_queries']);
    // simple text types
    $pconfig['port'] = !empty($a_dnsmasq['port']) ? $a_dnsmasq['port'] : "";
    $pconfig['custom_options'] = !empty($a_dnsmasq['custom_options']) ? $a_dnsmasq['custom_options'] : "";
    // arrays
    $pconfig['interface'] = !empty($a_dnsmasq['interface']) ? explode(",", $a_dnsmasq['interface']) : array();

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    $input_errors = array();
    if (isset($pconfig['submit'])) {
        // validate
        if (!empty($pconfig['regdhcpdomain']) && !is_domain($pconfig['regdhcpdomain'])) {
            $input_errors[] = gettext("The domain may only contain the characters a-z, 0-9, '-' and '.'.");
        }
        if (!empty($pconfig['port']) && !is_port($pconfig['port'])) {
            $input_errors[] = gettext("You must specify a valid port number");
        }
        $unbound_port = empty($config['unbound']['port']) ? "53" : $config['unbound']['port'];
        $dnsmasq_port = empty($pconfig['port']) ? "53" : $pconfig['port'];
        if (!empty($pconfig['enable']) && isset($config['unbound']['enable']) && $dnsmasq_port == $unbound_port) {
            $input_errors[] = gettext('Unbound is still active on the same port. Disable it before enabling Dnsmasq.');
        }

        $prev_opt = !empty($a_dnsmasq['custom_options']) ? $a_dnsmasq['custom_options'] : "";
        if ($prev_opt != str_replace("\r\n", "\n", $pconfig['custom_options']) && !userIsAdmin($_SESSION['Username'])) {
            $input_errors[] = gettext('Advanced options may only be edited by system administrators due to the increased possibility of privilege escalation.');
        }
        if (!empty($pconfig['custom_options']) && userIsAdmin($_SESSION['Username'])) {
            $args = '';
            foreach (preg_split('/\s+/', str_replace("\r\n", "\n", $pconfig['custom_options'])) as $c) {
                if (!empty($c)) {
                    $args .= escapeshellarg("--{$c}") . " ";
                }
            }
            exec("/usr/local/sbin/dnsmasq --test $args", $output, $rc);
            if ($rc != 0) {
                $input_errors[] = gettext("Invalid custom options");
            }
        }

        if (count($input_errors) == 0) {
            // save form
            $a_dnsmasq['enable'] = !empty($pconfig['enable']);
            $a_dnsmasq['regdhcp'] = !empty($pconfig['regdhcp']);
            $a_dnsmasq['regdhcpstatic'] = !empty($pconfig['regdhcpstatic']);
            $a_dnsmasq['dhcpfirst'] = !empty($pconfig['dhcpfirst']);
            $a_dnsmasq['strict_order'] = !empty($pconfig['strict_order']);
            $a_dnsmasq['domain_needed'] = !empty($pconfig['domain_needed']);
            $a_dnsmasq['no_private_reverse'] = !empty($pconfig['no_private_reverse']);
            $a_dnsmasq['log_queries'] = !empty($pconfig['log_queries']);
            $a_dnsmasq['strictbind'] = !empty($pconfig['strictbind']);
            $a_dnsmasq['dnssec'] = !empty($pconfig['dnssec']);
            if (!empty($pconfig['regdhcpdomain'])) {
                $a_dnsmasq['regdhcpdomain'] = $pconfig['regdhcpdomain'];
            } elseif (isset($a_dnsmasq['regdhcpdomain'])) {
                unset($a_dnsmasq['regdhcpdomain']);
            }
            if (!empty($pconfig['interface'])) {
                $a_dnsmasq['interface'] = implode(",", $pconfig['interface']);
            } elseif (isset($a_dnsmasq['interface'])) {
                unset($a_dnsmasq['interface']);
            }
            if (!empty($pconfig['port'])) {
                $a_dnsmasq['port'] = $pconfig['port'];
            } elseif (isset($a_dnsmasq['port'])) {
                unset($a_dnsmasq['port']);
            }
            if (!empty($pconfig['custom_options'])) {
                $a_dnsmasq['custom_options'] = str_replace("\r\n", "\n", $pconfig['custom_options']);
            } elseif (isset($a_dnsmasq['custom_options'])) {
                unset($a_dnsmasq['custom_options']);
            }
            write_config();
            system_resolvconf_generate();
            dnsmasq_configure_do();
            plugins_configure('dhcp');
            header(url_safe('Location: /services_dnsmasq.php'));
            exit;
        }
    } elseif (isset($pconfig['apply'])) {
        filter_configure();
        system_resolvconf_generate();
        system_hosts_generate();
        dnsmasq_configure_do();
        plugins_configure('dhcp');
        clear_subsystem_dirty('hosts');
        header(url_safe('Location: /services_dnsmasq.php'));
        exit;
    } elseif (!empty($pconfig['act']) && $pconfig['act'] == 'del') {
        $a_hosts = &config_read_array('dnsmasq', 'hosts');
        if (isset($pconfig['id']) && !empty($a_hosts[$pconfig['id']])) {
            unset($a_hosts[$pconfig['id']]);
            write_config();
            mark_subsystem_dirty('hosts');
            exit;
        }
    } elseif (!empty($pconfig['act']) && $pconfig['act'] == 'doverride') {
        $a_domainOverrides = &config_read_array('dnsmasq', 'domainoverrides');
        if (isset($pconfig['id']) && !empty($a_domainOverrides[$pconfig['id']])) {
            unset($a_domainOverrides[$pconfig['id']]);
            write_config();
            mark_subsystem_dirty('hosts');
            exit;
        }
    }
}

legacy_html_escape_form_data($pconfig);

$service_hook = 'dnsmasq';

include("head.inc");

?>
<body>

<script>
//<![CDATA[
$( document ).ready(function() {
  $("#show_advanced_dns").click(function(event){
    event.preventDefault();
    $("#showadvbox").hide();
    $("#showadv").show();
  });
  if ($("#custom_options").val() != "") {
      $("#show_advanced_dns").click();
  }
  // delete host action
  $(".act_delete_host").click(function(event){
    event.preventDefault();
    var id = $(this).data("id");
    // delete single
    BootstrapDialog.show({
      type:BootstrapDialog.TYPE_DANGER,
      title: "<?= gettext('Dnsmasq') ?>",
      message: "<?=gettext("Do you really want to delete this host?");?>",
      buttons: [{
                label: "<?= gettext("No");?>",
                action: function(dialogRef) {
                    dialogRef.close();
                }}, {
                label: "<?= gettext("Yes");?>",
                action: function(dialogRef) {
                  $.post(window.location, {act: 'del', id:id}, function(data) {
                      location.reload();
                  });
              }
            }]
    });
  });

  $(".act_delete_override").click(function(event){
    event.preventDefault();
    var id = $(this).data("id");
    // delete single
    BootstrapDialog.show({
      type:BootstrapDialog.TYPE_DANGER,
      title: "<?= gettext('Dnsmasq') ?>",
      message: "<?=gettext("Do you really want to delete this domain override?");?>",
      buttons: [{
                label: "<?= gettext("No");?>",
                action: function(dialogRef) {
                    dialogRef.close();
                }}, {
                label: "<?= gettext("Yes");?>",
                action: function(dialogRef) {
                  $.post(window.location, {act: 'doverride', id:id}, function(data) {
                      location.reload();
                  });
              }
            }]
    });
  });
});
//]]>
</script>

<?php include("fbegin.inc"); ?>

<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
      <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
      <?php if (is_subsystem_dirty('hosts')): ?>
      <?php print_info_box_apply(gettext('The Dnsmasq configuration has been changed.') . ' ' . gettext('You must apply the changes in order for them to take effect.')) ?>
      <?php endif; ?>
      <section class="col-xs-12">
        <div class="content-box">
          <form method="post" name="iform" id="iform">
            <div class="table-responsive">
              <table class="table table-striped opnsense_standard_table_form">
                <tr>
                  <td style="width:22%"><strong><?=gettext("General options");?></strong></td>
                  <td style="width:78%;text-align:right">
                    <small><?=gettext("full help");?> </small>
                    <i class="fa fa-toggle-off text-danger" style="cursor: pointer;" id="show_all_help_page"></i>
                  </td>
                </tr>
                <tr>
                  <td style="width:22%"><i class="fa fa-info-circle text-muted"></i> <?=gettext("Enable");?></td>
                  <td style="width:78%">
                    <input name="enable" type="checkbox" id="enable" value="yes" <?=!empty($pconfig['enable']) ? "checked=\"checked\"" : "";?> />
                    <?= gettext('Enable Dnsmasq') ?>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_port" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Listen Port");?></td>
                  <td>
                    <input name="port" type="text" id="port" size="6" placeholder="53" <?=!empty($pconfig['port']) ? "value=\"{$pconfig['port']}\"" : "";?> />
                    <div class="hidden" data-for="help_for_port">
                      <?=gettext("The port used for responding to DNS queries. It should normally be left blank unless another service needs to bind to TCP/UDP port 53.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_interfaces" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Network Interfaces') ?></td>
                  <td>
                    <select id="interface" name="interface[]" multiple="multiple" class="selectpicker" title="<?= html_safe(gettext('All (recommended)')) ?>">
<?php foreach (get_configured_interface_with_descr() as  $iface => $ifacename): ?>
                      <option value="<?= html_safe($iface) ?>" <?=!empty($pconfig['interface']) && in_array($iface, $pconfig['interface']) ? 'selected="selected"' : "" ?>>
                        <?= html_safe($ifacename) ?>
                      </option>
<?php endforeach ?>
                    </select>
                    <div class="hidden" data-for="help_for_interfaces">
                      <?=gettext("Interface IPs used by Dnsmasq for responding to queries from clients. If an interface has both IPv4 and IPv6 IPs, both are used. Queries to other interface IPs not selected below are discarded. The default behavior is to respond to queries on every available IPv4 and IPv6 address.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_strictbind" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Bind Mode') ?></td>
                  <td>
                    <input name="strictbind" type="checkbox" id="strictbind" value="yes" <?= !empty($pconfig['strictbind']) ? "checked=\"checked\"" : "";?> />
                    <?= gettext('Strict Interface Binding') ?>
                    <div class="hidden" data-for="help_for_strictbind">
                      <?= gettext("If this option is set, Dnsmasq will only bind to the interfaces containing the IP addresses selected above, rather than binding to all interfaces and discarding queries to other addresses."); ?>
                      <?= gettext("This option does not work with IPv6. If set, Dnsmasq will not bind to IPv6 addresses."); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext('DNSSEC') ?></td>
                  <td>
                    <input name="dnssec" type="checkbox" value="yes" <?=!empty($pconfig['dnssec']) ? 'checked="checked"' : '' ?> />
                    <?= gettext('Enable DNSSEC Support') ?>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_regdhcp" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("DHCP Registration");?></td>
                  <td>
                    <input name="regdhcp" type="checkbox" id="regdhcp" value="yes" <?=!empty($pconfig['regdhcp']) ? "checked=\"checked\"" : "";?> />
                    <?= gettext('Register DHCP leases') ?>
                    <div class="hidden" data-for="help_for_regdhcp">
                      <?= gettext("If this option is set, then machines that specify " .
                        "their hostname when requesting a DHCP lease will be registered " .
                        "in Dnsmasq, so that their name can be resolved.") ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_regdhcpdomain" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("DHCP Domain Override");?></td>
                  <td>
                    <input name="regdhcpdomain" type="text" id="regdhcpdomain" value="<?= $pconfig['regdhcpdomain'] ?>"/>
                    <div class="hidden" data-for="help_for_regdhcpdomain">
                      <?= gettext("The domain name to use for DHCP hostname registration. " .
                        "If empty, the default system domain is used. Note that all DHCP " .
                        "leases will be assigned to the same domain. If this is undesired, " .
                        "static DHCP lease registration is able to provide coherent mappings.") ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_regdhcpstatic" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Static DHCP");?></td>
                  <td>
                    <input name="regdhcpstatic" type="checkbox" id="regdhcpstatic" value="yes" <?=!empty($pconfig['regdhcpstatic']) ? "checked=\"checked\"" : "";?> />
                    <?= gettext('Register DHCP static mappings') ?>
                    <div class="hidden" data-for="help_for_regdhcpstatic">
                      <?= sprintf(gettext("If this option is set, then DHCP static mappings will ".
                          "be registered in Dnsmasq, so that their name can be ".
                          "resolved. You should also set the domain in %s".
                          "System: General setup%s to the proper value."),'<a href="system_general.php">','</a>');?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_dhcpfirst" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Prefer DHCP");?></td>
                  <td>
                    <input name="dhcpfirst" type="checkbox" id="dhcpfirst" value="yes" <?=!empty($pconfig['dhcpfirst']) ? "checked=\"checked\"" : "";?> />
                    <?= gettext('Resolve DHCP mappings first') ?>
                    <div class="hidden" data-for="help_for_dhcpfirst">
                      <?= sprintf(gettext("If this option is set, then DHCP mappings will ".
                          "be resolved before the manual list of names below. This only ".
                          "affects the name given for a reverse lookup (PTR)."));?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_strict_order" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("DNS Query Forwarding");?></td>
                  <td>
                    <input name="strict_order" type="checkbox" id="strict_order" value="yes" <?=!empty($pconfig['strict_order']) ? "checked=\"checked\"" : "";?> />
                    <?= gettext('Query DNS servers sequentially') ?>
                    <div class="hidden" data-for="help_for_strict_order">
                      <?= gettext("If this option is set, Dnsmasq will ".
                        "query the DNS servers sequentially in the order specified (System: " .
                        "General Setup: DNS Servers), rather than all at once in parallel.") ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td></td>
                  <td>
                    <input name="domain_needed" type="checkbox" id="domain_needed" value="yes" <?=!empty($pconfig['domain_needed']) ? "checked=\"checked\"" : "";?> />
                    <?= gettext('Require domain') ?>
                    <div class="hidden" data-for="help_for_strict_order">
                      <?= gettext('If this option is set, Dnsmasq will '.
                        'not forward A or AAAA queries for plain names, without dots or ' .
                        'domain parts, to upstream name servers. If the name is not known ' .
                        'from /etc/hosts or DHCP then a "not found" answer is returned.') ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td></td>
                  <td>
                    <input name="no_private_reverse" type="checkbox" id="no_private_reverse" value="yes" <?=!empty($pconfig['no_private_reverse']) ? "checked=\"checked\"" : "";?> />
                    <?= gettext('Do not forward private reverse lookups') ?>
                    <div class="hidden" data-for="help_for_strict_order">
                      <?= gettext('If this option is set, Dnsmasq will '.
                        'not forward reverse DNS lookups (PTR) for private addresses ' .
                        '(RFC 1918) to upstream name servers. Any entries in the Domain ' .
                        'Overrides section forwarding private "n.n.n.in-addr.arpa" names ' .
                        'to a specific server are still forwarded. If the IP to name is ' .
                        'not known from /etc/hosts, DHCP or a specific domain override ' .
                        'then a "not found" answer is immediately returned.') ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext('Log Queries') ?></td>
                  <td>
                    <input name="log_queries" type="checkbox" id="log_queries" value="yes" <?= !empty($pconfig['log_queries']) ? 'checked="checked"' : '' ?> />
                    <?= gettext('Log the results of DNS queries') ?>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_advanced" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Advanced') ?></td>
                  <td>
                    <div id="showadvbox" <?= !empty($pconfig['custom_options']) ? "style='display:none'" : '' ?>>
                      <button class="btn btn-default btn-xs" id="show_advanced_dns" value="yes"><?= gettext('Show advanced option') ?></button>
                    </div>
                    <div id="showadv" <?= empty($pconfig['custom_options']) ? "style='display:none'" : "" ?>>
                      <textarea rows="6" cols="78" name="custom_options" id="custom_options"><?=$pconfig['custom_options'];?></textarea>
                      <?=gettext("This option will be removed in the future due to being insecure by nature. In the mean time only full administrators are allowed to change this setting.");?>
                    </div>
                    <div class="hidden" data-for="help_for_advanced">
                      <?=gettext("Enter any additional options you would like to add to the Dnsmasq configuration here, separated by a space or newline"); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td></td>
                  <td>
                    <input name="submit" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save')); ?>" />
                  </td>
                </tr>
                <tr>
                  <td colspan="2">
                    <?= sprintf(gettext("If Dnsmasq is enabled, the DHCP".
                    " service (if enabled) will automatically serve the LAN IP".
                    " address as a DNS server to DHCP clients so they will use".
                    " the Dnsmasq forwarder. Dnsmasq will use the DNS servers".
                    " entered in %sSystem: General setup%s".
                    " or those obtained via DHCP or PPP on WAN if the \"Allow".
                    " DNS server list to be overridden by DHCP/PPP on WAN\"".
                    " is checked. If you don't use that option (or if you use".
                    " a static IP address on WAN), you must manually specify at".
                    " least one DNS server on the %sSystem: General setup%s page."),
                    '<a href="system_general.php">','</a>','<a href="system_general.php">','</a>');?>
                  </td>
                </tr>
              </table>
            </div>
          </form>
        </div>
      </section>
      <section class="col-xs-12">
        <div class="content-box">
          <div class="table-responsive">
            <table class="table table-striped">
              <tbody>
                <tr>
                  <td colspan="5"><strong><?= gettext('Host Overrides') ?></strong></td>
                </tr>
                <tr>
                  <td><strong><?= gettext('Host') ?></strong></td>
                  <td><strong><?= gettext('Domain') ?></strong></td>
                  <td><strong><?= gettext('IP') ?></strong></td>
                  <td><strong><?= gettext('Description') ?></strong></td>
                  <td class="text-nowrap">
                    <a href="services_dnsmasq_edit.php" class="btn btn-default btn-xs"><i class="fa fa-plus fa-fw"></i></a>
                  </td>
                </tr>
<?php foreach (config_read_array('dnsmasq', 'hosts') as $i => $hostent): ?>
                <tr>
                  <td><?=htmlspecialchars(strtolower($hostent['host']));?></td>
                  <td><?=htmlspecialchars(strtolower($hostent['domain']));?></td>
                  <td><?=htmlspecialchars($hostent['ip']);?></td>
                  <td><?=htmlspecialchars($hostent['descr']);?></td>
                  <td class="text-nowrap">
                    <a href="services_dnsmasq_edit.php?id=<?=$i;?>" class="btn btn-default btn-xs"><i class="fa fa-pencil fa-fw"></i></a>
                    <a href="#" data-id="<?=$i;?>" class="act_delete_host btn btn-xs btn-default"><i class="fa fa-trash fa-fw"></i></a>
                  </td>
                </tr>
<?php if (isset($hostent['aliases']['item'])): ?>
<?php foreach ($hostent['aliases']['item'] as $alias): ?>
                <tr>
                  <td><?=htmlspecialchars(strtolower($alias['host']));?></td>
                  <td><?=htmlspecialchars(strtolower($alias['domain']));?></td>
                  <td><?=gettext("Alias for");?> <?=$hostent['host'] ? htmlspecialchars($hostent['host'] . '.' . $hostent['domain']) : htmlspecialchars($hostent['domain']);?></td>
                  <td><?=htmlspecialchars($alias['description']);?></td>
                  <td class="text-nowrap">
                    <a href="services_dnsmasq_edit.php?id=<?=$i;?>" class="btn btn-default btn-xs"><i class="fa fa-pencil fa-fw"></i></a>
                  </td>
                </tr>
<?php endforeach ?>
<?php endif ?>
<?php endforeach ?>
                <tr>
                  <td colspan="5">
                    <?=gettext("Entries in this section override individual results from the forwarders.");?>
                    <?=gettext("Use these for changing DNS results or for adding custom DNS records.");?>
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
              <tbody>
                <tr>
                  <td colspan="4"><strong><?= gettext('Domain Overrides') ?></strong></td>
                </tr>
                <tr>
                  <td><strong><?= gettext('Domain') ?><strong></td>
                  <td><strong><?= gettext('IP') ?></strong></td>
                  <td><strong><?= gettext('Description') ?></strong></td>
                  <td class="text-nowrap">
                    <a href="services_dnsmasq_domainoverride_edit.php" class="btn btn-default btn-xs">
                      <i class="fa fa-plus fa-fw"></i>
                    </a>
                  </td>
                </tr>
<?php foreach (config_read_array('dnsmasq', 'domainoverrides') as $i => $doment): ?>
                <tr>
                  <td><?=htmlspecialchars(strtolower($doment['domain']));?></td>
                  <td><?=htmlspecialchars($doment['ip']);?></td>
                  <td><?=htmlspecialchars($doment['descr']);?></td>
                  <td class="text-nowrap">
                    <a href="services_dnsmasq_domainoverride_edit.php?id=<?=$i;?>" class="btn btn-default btn-xs">
                      <i class="fa fa-pencil fa-fw"></i>
                    </a>
                    <a href="#" data-id="<?=$i;?>" class="act_delete_override btn btn-xs btn-default"><i class="fa fa-trash fa-fw"></i></a>
                  </td>
                </tr>
<?php endforeach ?>
                <tr>
                  <td colspan="4">
                    <?=gettext("Entries in this area override an entire domain, and subdomains, by specifying an authoritative DNS server to be queried for that domain.");?>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </div>
  </div>
</section>

<?php include("foot.inc"); ?>
