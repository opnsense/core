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
require_once("interfaces.inc");

$a_hosts = &config_read_array('dnsmasq', 'hosts');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id']) && !empty($a_hosts[$_GET['id']])) {
        $id = $_GET['id'];
    }
    $config_copy_fieldnames = array('host', 'domain', 'ip', 'descr', 'aliases');
    foreach ($config_copy_fieldnames as $fieldname) {
        if (isset($id) && isset($a_hosts[$id][$fieldname])) {
            $pconfig[$fieldname] = $a_hosts[$id][$fieldname];
        } else {
            $pconfig[$fieldname] = null;
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['id']) && !empty($a_hosts[$_POST['id']])) {
        $id = $_POST['id'];
    }
    $input_errors = array();
    $pconfig = $_POST;

    $pconfig['aliases'] =  array();
    if (isset($pconfig['aliases_host'])) {
        $pconfig['aliases']['item'] = array();
        foreach ($pconfig['aliases_host'] as $opt_seq => $opt_host) {
            if (!empty($opt_host)) {
                $pconfig['aliases']['item'][] = array(
                    'description' => $pconfig['aliases_description'][$opt_seq],
                    'domain' => $pconfig['aliases_domain'][$opt_seq],
                    'host' => $opt_host,
                );
            }
        }
    }

    /* input validation */
    $reqdfields = explode(" ", "domain ip");
    $reqdfieldsn = array(gettext("Domain"),gettext("IP address"));

    do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

    if (!empty($pconfig['host']) && !is_hostname($pconfig['host'])) {
        $input_errors[] = gettext("The hostname can only contain the characters A-Z, 0-9 and '-'.");
    }
    if (!empty($pconfig['domain']) && !is_domain($pconfig['domain'])) {
        $input_errors[] = gettext("A valid domain must be specified.");
    }

    if (!empty($pconfig['ip']) && !is_ipaddr($pconfig['ip'])) {
        $input_errors[] = gettext("A valid IP address must be specified.");
    }

    /* validate aliases */
    foreach ($pconfig['aliases']['item'] as $idx => $alias) {
        $aliasreqdfields = array('domain');
        $aliasreqdfieldsn = array(gettext("Alias Domain"));

        do_input_validation($alias, $aliasreqdfields, $aliasreqdfieldsn, $input_errors);
        if (!empty($alias['host']) && !is_hostname($alias['host'])) {
            $input_errors[] = gettext("Hostnames in alias list can only contain the characters A-Z, 0-9 and '-'.");
        }
        if (!empty($alias['domain']) && !is_domain($alias['domain'])) {
            $input_errors[] = gettext("A valid domain must be specified in alias list.");
        }
    }

    /* check for overlaps */
    foreach ($a_hosts as $hostent) {
        if (isset($id) && $a_hosts[$id] === $hostent) {
            continue;
        }
        if (($hostent['host'] == $pconfig['host']) && ($hostent['domain'] == $pconfig['domain'])
          && ((is_ipaddrv4($hostent['ip']) && is_ipaddrv4($pconfig['ip'])) || (is_ipaddrv6($hostent['ip']) && is_ipaddrv6($pconfig['ip'])))) {
            $input_errors[] = gettext("This host/domain already exists.");
            break;
        }
    }

    if (count($input_errors) == 0) {
        $hostent = array();
        $hostent['host'] = $pconfig['host'];
        $hostent['domain'] = $pconfig['domain'];
        $hostent['ip'] = $pconfig['ip'];
        $hostent['descr'] = $pconfig['descr'];
        $hostent['aliases'] = $pconfig['aliases'];

        if (isset($id)) {
            $a_hosts[$id] = $hostent;
        } else {
            $a_hosts[] = $hostent;
        }
        usort($config['dnsmasq']['hosts'], function ($a, $b) {
            return strcasecmp($a['host'], $b['host']);
        });

        write_config();
        mark_subsystem_dirty('hosts');
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
<script>
  $( document ).ready(function() {
    /**
     *  Aliases
     */
    function removeRow() {
        if ( $('#aliases_table > tbody > tr').length == 1 ) {
            $('#aliases_table > tbody > tr:last > td > input').each(function(){
              $(this).val("");
            });
        } else {
            $(this).parent().parent().remove();
        }
    }
    // add new detail record
    $("#addNew").click(function(){
        // copy last row and reset values
        $('#aliases_table > tbody').append('<tr>'+$('#aliases_table > tbody > tr:last').html()+'</tr>');
        $('#aliases_table > tbody > tr:last > td > input').each(function(){
          $(this).val("");
        });
        $(".act-removerow").click(removeRow);
    });
    $(".act-removerow").click(removeRow);
  });
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
                  <td style="width:22%"><strong><?= gettext('Edit entry') ?></strong></td>
                  <td style="width:78%; text-align:right">
                    <small><?=gettext("full help"); ?> </small>
                    <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_host" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Host");?></td>
                  <td>
                    <input name="host" type="text" id="host" value="<?=$pconfig['host'];?>" />
                    <div class="hidden" data-for="help_for_host">
                      <?=gettext("Name of the host, without"." domain part"); ?><br />
                      <?=gettext("e.g."); ?> <em><?=gettext("myhost"); ?></em>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_domain" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Domain");?></td>
                  <td>
                    <input name="domain" type="text" id="domain" value="<?=$pconfig['domain'];?>" />
                    <div class="hidden" data-for="help_for_domain">
                      <?=gettext("Domain of the host"); ?><br />
                      <?=gettext("e.g."); ?> <em><?=gettext("example.com"); ?></em>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_ip" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IP address");?></td>
                  <td>
                    <input name="ip" type="text" id="ip" value="<?=$pconfig['ip'];?>" />
                    <div class="hidden" data-for="help_for_ip">
                      <?=gettext("IP address of the host"); ?><br />
                      <?=gettext("e.g."); ?> <em>192.168.100.100</em> <?=gettext("or"); ?> <em>fd00:abcd::1</em><
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_descr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description");?></td>
                  <td>
                    <input name="descr" type="text" id="descr" value="<?=$pconfig['descr'];?>" />
                    <div class="hidden" data-for="help_for_descr">
                      <?=gettext("You may enter a description here"." for your reference (not parsed).");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_alias" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Aliases"); ?></td>
                  <td>
                    <table class="table table-striped table-condensed" id="aliases_table">
                      <thead>
                        <tr>
                          <th></th>
                          <th id="detailsHeading1"><?=gettext("Host"); ?></th>
                          <th id="detailsHeading3"><?=gettext("Domain"); ?></th>
                          <th id="updatefreqHeader" ><?=gettext("Description");?></th>
                        </tr>
                      </thead>
                      <tbody>
<?php
                      if (empty($pconfig['aliases']['item'])) {
                          $aliases = array();
                          $aliases[] = array('number' => null, 'value' => null, 'type' => null);
                      } else {
                          $aliases = $pconfig['aliases']['item'];
                      }
                      foreach($aliases as $item):?>
                        <tr>
                          <td>
                            <div style="cursor:pointer;" class="act-removerow btn btn-default btn-xs"><i class="fa fa-minus fa-fw"></i></div>
                          </td>
                          <td>
                            <input name="aliases_host[]" type="text" value="<?=$item['host'];?>" />
                          </td>
                          <td>
                            <input name="aliases_domain[]" type="text" value="<?=$item['domain'];?>" />
                          </td>
                          <td>
                            <input name="aliases_description[]" type="text" value="<?=$item['description'];?>" />
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
                    <div class="hidden" data-for="help_for_alias">
                      <?=gettext("Enter additional names for this host."); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td>&nbsp;</td>
                  <td>
                    <input name="Submit" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save'));?>" />
                    <input type="button" class="btn btn-default" value="<?=html_safe(gettext("Cancel"));?>" onclick="window.location.href='/services_dnsmasq.php'" />
                    <?php if (isset($id)): ?>
                    <input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
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
