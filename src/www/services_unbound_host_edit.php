<?php

/*
 * Copyright (C) 2015 Manuel Faux <mfaux@conf.at>
 * Copyright (C) 2014-2016 Deciso B.V.
 * Copyright (C) 2014 Warren Baker <warren@decoy.co.za>
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

$a_hosts = &config_read_array('unbound', 'hosts');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id']) && !empty($a_hosts[$_GET['id']])) {
        $id = $_GET['id'];
    }
    $pconfig = array();
    foreach (array('rr', 'host', 'domain', 'ip', 'mxprio', 'mx', 'descr', 'aliases') as $fieldname) {
        if (isset($id) && !empty($a_hosts[$id][$fieldname])) {
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
            if (empty($opt_host) && empty($pconfig['aliases_domain'][$opt_seq]) && empty($pconfig['aliases_descr'][$opt_seq])) {
                continue;
            }
            $pconfig['aliases']['item'][] = array(
                'domain' => $pconfig['aliases_domain'][$opt_seq],
                'descr' => $pconfig['aliases_descr'][$opt_seq],
                'host' => $opt_host,
            );
        }
    }

    $reqdfields = explode(" ", "domain rr");
    $reqdfieldsn = array(gettext("Domain"),gettext("Type"));

    do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

    if (!empty($pconfig['host']) && !is_hostname($pconfig['host']) && $pconfig['host'] != '*') {
        $input_errors[] = gettext("The hostname can only contain the characters A-Z, 0-9 and '-'.");
    }

    if (!empty($pconfig['domain']) && !is_domain($pconfig['domain'])) {
        $input_errors[] = gettext("A valid domain must be specified.");
    }

    if (!empty($pconfig['domain']) && $pconfig['domain'] == $config['system']['domain'] && $pconfig['host'] == '*') {
        $input_errors[] = sprintf(
            gettext("A wildcard domain override is not supported for the local domain '%s'."),
            $config['system']['domain']
        );
    }

    switch ($pconfig['rr']) {
        case 'A': /* also: AAAA */
            $reqdfields = explode(" ", "ip");
            $reqdfieldsn = array(gettext("IP address"));
            do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

            if (!empty($pconfig['ip']) && !is_ipaddr($pconfig['ip'])) {
                $input_errors[] = gettext("A valid IP address must be specified.");
            }
            break;
        case 'MX':
            $reqdfields = explode(" ", "mxprio mx");
            $reqdfieldsn = array(gettext("MX Priority"), gettext("MX Host"));
            do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

            if (!empty($pconfig['mxprio']) && !is_numericint($pconfig['mxprio'])) {
                $input_errors[] = gettext("A valid MX priority must be specified.");
            }

            if (!empty($pconfig['mx']) && !is_domain($pconfig['mx'])) {
                $input_errors[] = gettext("A valid MX host must be specified.");
            }
            break;
        default:
            $input_errors[] = gettext("A valid resource record type must be specified.");
            break;
    }

    foreach ($pconfig['aliases']['item'] as $idx => $alias) {
        if (!empty($alias['host']) && !is_hostname($alias['host'])) {
            $input_errors[] = gettext('Hostnames in alias list can only contain the characters A-Z, 0-9 and \'-\'.');
        }
        if (!empty($alias['domain']) && !is_domain($alias['domain'])) {
            $input_errors[] = gettext('A valid domain must be specified in alias list.');
        }
        if (empty($alias['host']) && empty($alias['domain'])) {
            $input_errors[] = gettext('A valid hostname or domain must be specified in alias list.');
        }
    }

    if (count($input_errors) == 0) {
        $hostent = array();
        $hostent['host'] = $pconfig['host'];
        $hostent['domain'] = $pconfig['domain'];
        /* distinguish between A and AAAA by parsing the passed IP address */
        $hostent['rr'] = ($pconfig['rr'] == 'A' && is_ipaddrv6($pconfig['ip'])) ? 'AAAA' : $pconfig['rr'];
        $hostent['ip'] = $pconfig['ip'];
        $hostent['mxprio'] = $pconfig['mxprio'];
        $hostent['mx'] = $pconfig['mx'];
        $hostent['descr'] = $pconfig['descr'];
        $hostent['aliases'] = $pconfig['aliases'];

        if (isset($id)) {
            $a_hosts[$id] = $hostent;
        } else {
            $a_hosts[] = $hostent;
        }

        usort($a_hosts, function ($a, $b) {
            return strcasecmp($a['host'], $b['host']);
        });

        mark_subsystem_dirty('unbound');
        write_config();
        header(url_safe('Location: /services_unbound_overrides.php'));
        exit;
    }
}

$service_hook = 'unbound';
legacy_html_escape_form_data($pconfig);
include("head.inc");
?>

<script>
  $( document ).ready(function() {
    $("#rr").change(function() {
      $(".a_aaa_rec").hide();
      $(".mx_rec").hide();
      switch ($(this).val()) {
        case 'A':
          $('#ip').prop('disabled', false);
          $('#mxprio').prop('disabled', true);
          $('#mx').prop('disabled', true);
          $(".a_aaa_rec").show();
          break;
        case 'MX':
          $('#ip').prop('disabled', true);
          $('#mxprio').prop('disabled', false);
          $('#mx').prop('disabled', false);
          $(".mx_rec").show();
          break;
      }
      $( window ).resize(); // call window resize, which will re-apply zebra
    });
    // trigger initial change
    $("#rr").change();

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
                    <td style="width:22%"><strong><?= gettext('Edit entry') ?></strong></td>
                    <td style="width:78%; text-align:right">
                      <small><?=gettext("full help"); ?> </small>
                      <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_host" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Host");?></td>
                    <td>
                      <input name="host" type="text" value="<?=$pconfig['host'];?>" />
                      <div class="hidden" data-for="help_for_host">
                        <?= gettext('Name of the host, without domain part. Use "*" to create a wildcard entry.') ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_domain" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Domain");?></td>
                    <td>
                      <input name="domain" type="text" value="<?=$pconfig['domain'];?>" />
                      <div class="hidden" data-for="help_for_domain">
                        <?=gettext("Domain of the host"); ?><br />
                        <?=gettext("e.g."); ?> <em><?=gettext("example.com"); ?></em>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_rr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Type");?></td>
                    <td>
                      <select name="rr" id="rr" class="selectpicker">
<?php
                       $rrs = array("A" => gettext("A or AAAA (IPv4 or IPv6 address)"), "MX" => gettext("MX (Mail server)"));
                       foreach ($rrs as $rr => $name) :?>
                        <option value="<?=$rr;?>" <?=($rr == $pconfig['rr'] || ($rr == 'A' && $pconfig['rr'] == 'AAAA')) ? 'selected="selected"' : '';?> >
                          <?=$name;?>
                        </option>
<?php
                        endforeach; ?>
                      </select>
                      <div class="hidden" data-for="help_for_rr">
                        <?=gettext("Type of resource record"); ?>
                        <br />
                        <?=gettext("e.g."); ?> <em>A</em> <?=gettext("or"); ?> <em>AAAA</em> <?=gettext("for IPv4 or IPv6 addresses"); ?>
                      </div>
                    </td>
                  </tr>
                  <tr class="a_aaa_rec">
                    <td><a id="help_for_ip" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IP");?></td>
                    <td>
                      <input name="ip" type="text" id="ip" value="<?=$pconfig['ip'];?>" />
                      <div class="hidden" data-for="help_for_ip">
                        <?=gettext("IP address of the host"); ?><br />
                        <?=gettext("e.g."); ?> <em>192.168.100.100</em> <?=gettext("or"); ?> <em>fd00:abcd::1</em>
                      </div>
                    </td>
                  </tr>
                  <tr class="mx_rec">
                    <td><a id="help_for_mxprio" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("MX Priority");?></td>
                    <td>
                      <input name="mxprio" type="text" id="mxprio" value="<?=$pconfig['mxprio'];?>" />
                      <div class="hidden" data-for="help_for_mxprio">
                        <?=gettext("Priority of MX record"); ?><br />
                        <?=gettext("e.g."); ?> <em>10</em>
                      </div>
                    </td>
                  </tr>
                  <tr class="mx_rec">
                    <td><a id="help_for_mx" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("MX Host");?></td>
                    <td>
                      <input name="mx" type="text" id="mx" size="6" value="<?=$pconfig['mx'];?>" />
                      <div class="hidden" data-for="help_for_mx">
                        <?=gettext("Host name of MX host"); ?><br />
                        <?=gettext("e.g."); ?> <em>mail.example.com</em>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_descr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description");?></td>
                    <td>
                      <input name="descr" type="text" id="descr" value="<?=$pconfig['descr'];?>" />
                      <div class="hidden" data-for="help_for_descr">
                        <?=gettext("You may enter a description here for your reference (not parsed).");?>
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
                        $aliases = !empty($pconfig['aliases']['item']) ? $pconfig['aliases']['item'] : array();
                        $aliases[] = array('number' => null, 'value' => null, 'type' => null);

                        foreach($aliases as $item): ?>
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
                              <input name="aliases_descr[]" type="text" value="<?=$item['descr'];?>" />
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
                      <input name="Submit" type="submit" class="btn btn-primary" value="<?= html_safe(gettext('Save')) ?>" />
                      <input type="button" class="btn btn-default" value="<?= html_safe(gettext('Cancel')) ?>" onclick="window.location.href='/services_unbound_overrides.php'" />
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
