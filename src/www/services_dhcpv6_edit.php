<?php

/*
 * Copyright (C) 2014-2016 Deciso B.V.
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
 * Copyright (C) 2011 Seth Mos <seth.mos@dds.nl>
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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // handle identifiers and action
    if (!empty($_GET['if']) && !empty($config['interfaces'][$_GET['if']])) {
        $if = $_GET['if'];
    } else {
        header(url_safe('Location: /services_dhcpv6.php'));
        exit;
    }
    if (isset($if) && isset($_GET['id']) && !empty($config['dhcpdv6'][$if]['staticmap'][$_GET['id']])) {
        $id = $_GET['id'];
    }

    // read form data
    $pconfig = array();
    $config_copy_fieldnames = array('duid', 'hostname', 'ipaddrv6', 'filename' ,'rootpath' ,'descr', 'domain', 'domainsearchlist');
    foreach ($config_copy_fieldnames as $fieldname) {
        if (isset($if) && isset($id) && isset($config['dhcpdv6'][$if]['staticmap'][$id][$fieldname])) {
            $pconfig[$fieldname] = $config['dhcpdv6'][$if]['staticmap'][$id][$fieldname];
        } elseif (isset($_GET[$fieldname])) {
            $pconfig[$fieldname] = $_GET[$fieldname];
        } else {
            $pconfig[$fieldname] = null;
        }
    }

    // backward compatibility: migrate 'domain' to 'domainsearchlist'
    if (empty($pconfig['domainsearchlist'])) {
        $pconfig['domainsearchlist'] = $pconfig['domain'];
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    $pconfig = $_POST;

    // handle identifiers and actions
    if (!empty($pconfig['if']) && !empty($config['interfaces'][$pconfig['if']])) {
        $if = $pconfig['if'];
    }
    if (!empty($config['dhcpdv6'][$if]['staticmap'][$pconfig['id']])) {
        $id = $pconfig['id'];
    }

    config_read_array('dhcpdv6', $if, 'staticmap');

    /* input validation */
    if (!empty($pconfig['hostname'])) {
        preg_match("/\-\$/", $pconfig['hostname'], $matches);
        if ($matches) {
            $input_errors[] = gettext("The hostname cannot end with a hyphen according to RFC952");
        }
        if (!is_hostname($pconfig['hostname'])) {
            $input_errors[] = gettext("The hostname can only contain the characters A-Z, 0-9 and '-'.");
        } elseif (strpos($pconfig['hostname'],'.')) {
            $input_errors[] = gettext("A valid hostname is specified, but the domain name part should be omitted");
        }
    }
    if (!empty($pconfig['ipaddrv6']) && !is_ipaddrv6($pconfig['ipaddrv6'])) {
        $input_errors[] = gettext("A valid IPv6 address must be specified.");
    }

    if (!empty($pconfig['duid'])) {
        $pconfig['duid'] = str_replace("-",":",$pconfig['duid']);
        if( preg_match('/^([a-fA-F0-9]{2}[:])*([a-fA-F0-9]{2}){1}$/', $pconfig['duid']) !== 1) {
            $input_errors[] = gettext("A valid DUID Identifier must be specified.");
        }
    }

    if (!empty($pconfig['domainsearchlist'])) {
        $domain_array=preg_split("/[ ;]+/",$pconfig['domainsearchlist']);
        foreach ($domain_array as $curdomain) {
            if (!is_domain($curdomain)) {
                $input_errors[] = gettext("A valid domain search list must be specified.");
                break;
            }
        }
    }

    /* check for overlaps */
    $a_maps = &config_read_array('dhcpdv6', $if, 'staticmap');
    foreach ($a_maps as $mapent) {
        if (isset($id) && ($a_maps[$id] === $mapent)) {
            continue;
        }
        if ((($mapent['hostname'] == $pconfig['hostname']) && $mapent['hostname'])  || ($mapent['duid'] == $pconfig['duid'])) {
            $input_errors[] = gettext("This Hostname, IP or DUID Identifier already exists.");
            break;
        }
    }
    if (count($input_errors) == 0) {
        $mapent = array();
        $config_copy_fieldnames = array('duid', 'ipaddrv6', 'hostname', 'descr', 'filename', 'rootpath', 'domainsearchlist');
        foreach ($config_copy_fieldnames as $fieldname) {
            if (!empty($pconfig[$fieldname])) {
                $mapent[$fieldname] = $pconfig[$fieldname];
            }
        }

        if (isset($id)) {
            $config['dhcpdv6'][$if]['staticmap'][$id] = $mapent;
        } else {
            $config['dhcpdv6'][$if]['staticmap'][] = $mapent;
        }

        usort($config['dhcpdv6'][$if]['staticmap'], function ($a, $b) {
            return ipcmp($a['ipaddrv6'], $b['ipaddrv6']);
        });

        write_config();

        if (isset($config['dhcpdv6'][$if]['enable'])) {
            mark_subsystem_dirty('staticmaps');
            mark_subsystem_dirty('hosts');
        }

        header(url_safe('Location: /services_dhcpv6.php?if=%s', array($if)));
        exit;
    }
}

$service_hook = 'dhcpd6';

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
                    <td style="width:22%"><strong><?=gettext("Static DHCPv6 Mapping");?></strong></td>
                    <td style="width:78%; text-align:right">
                      <small><?=gettext("full help"); ?> </small>
                      <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_duid" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("DUID Identifier");?></td>
                    <td>
                      <input name="duid" type="text" value="<?=$pconfig['duid'];?>" />
                      <div class="hidden" data-for="help_for_duid">
                        <?=gettext("Enter a DUID Identifier in the following format: ");?><br />
                        "<?= gettext('DUID-LLT - ETH -- TIME --- ---- ADDR ----') ?>" <br />
                        "xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx:xx"
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_ipaddrv6" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IPv6 address");?></td>
                    <td>
                      <input name="ipaddrv6" type="text" value="<?=$pconfig['ipaddrv6'];?>" />
                      <div class="hidden" data-for="help_for_ipaddrv6">
                        <?=gettext("If an IPv6 address is entered, the address must be outside of the pool.");?>
                        <br />
                        <?=gettext("If no IPv6 address is given, one will be dynamically allocated from the pool.");?>
                        <br />
                        <?= gettext("When using a static WAN address, this should be entered using the full IPv6 address. " .
                        "When using a dynamic WAN address, only enter the suffix part (i.e. ::1:2:3:4)."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_hostname" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Hostname");?></td>
                    <td>
                      <input name="hostname" type="text" value="<?=$pconfig['hostname'];?>" />
                      <div class="hidden" data-for="help_for_hostname">
                        <?=gettext("Name of the host, without domain part.");?>
                        <?=gettext("If no IP address is given above, hostname will not be visible to DNS services with lease registration enabled.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_domainsearchlist" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Domain search list");?></td>
                    <td>
                      <input name="domainsearchlist" type="text" value="<?=$pconfig['domainsearchlist'];?>" />
                      <div class="hidden" data-for="help_for_domainsearchlist">
                        <?=gettext("If you want to use a custom domain search list for this host, you may optionally specify one or multiple domains here. " .
                        "Use the semicolon character as separator. The first domain in this list will also be used for DNS registration of this host if enabled. " .
                        "If empty, the first domain in the interface's domain search list will be used. If this is empty, too, the system domain will be used.");?>
                      </div>
                    </td>
                  </tr>
<?php if (isset($config['dhcpdv6'][$if]['netboot'])): ?>
                  <tr>
                    <td><a id="help_for_filename" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Netboot filename') ?></td>
                    <td>
                      <input name="filename" type="text" value="<?=$pconfig['filename'];?>" />
                      <div class="hidden" data-for="help_for_filename">
                        <?= gettext('Name of the file that should be loaded when this host boots off of the network, overrides setting on main page.') ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_rootpath" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Root Path') ?></td>
                    <td>
                      <input name="rootpath" type="text" value="<?=$pconfig['rootpath'];?>" />
                      <div class="hidden" data-for="help_for_rootpath">
                        <?= gettext('Enter the root-path-string, overrides setting on main page.') ?>
                      </div>
                    </td>
                  </tr>
<?php
              endif;?>
                  <tr>
                    <td><a id="help_for_descr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description");?></td>
                    <td>
                      <input name="descr" type="text" value="<?=$pconfig['descr'];?>" />
                      <div class="hidden" data-for="help_for_descr">
                        <?=gettext("You may enter a description here for your reference (not parsed).");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td></td>
                    <td>
                      <input name="Submit" type="submit" class="formbtn btn btn-primary" value="<?=html_safe(gettext('Save'));?>" />
                      <input type="button" class="formbtn btn btn-default" value="<?=html_safe(gettext('Cancel'));?>" onclick="window.location.href='/services_dhcpv6.php?if=<?= html_safe($if) ?>'" />
                      <?php if (isset($id)): ?>
                      <input name="id" type="hidden" value="<?=$id;?>" />
                      <?php endif; ?>
                      <input name="if" type="hidden" value="<?=$if;?>" />
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
