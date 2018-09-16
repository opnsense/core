<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
    Copyright (C) 2003-2004 Justin Ellison <justin@techadvise.com>
    Copyright (C) 2010  Ermal Luçi
    Copyright (C) 2010  Seth Mos <seth.mos@dds.nl>
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


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig['enable'] = isset($config['dhcrelay6']['enable']);
    if (empty($config['dhcrelay6']['interface'])) {
        $pconfig['interface'] = array();
    } else {
        $pconfig['interface'] = explode(",", $config['dhcrelay6']['interface']);
    }
    if (empty($config['dhcrelay6']['server'])) {
        $pconfig['server'] = "";
    } else {
        $pconfig['server'] = $config['dhcrelay6']['server'];
    }
    $pconfig['agentoption'] = isset($config['dhcrelay6']['agentoption']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    $pconfig = $_POST;

    /* input validation */
    $reqdfields = explode(" ", "server interface");
    $reqdfieldsn = array(gettext("Destination Server"), gettext("Interface"));

    do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

    if (!empty($pconfig['server'])) {
        $checksrv = explode(",", $pconfig['server']);
        foreach ($checksrv as $srv) {
            if (!is_ipaddrv6($srv)) {
                $input_errors[] = gettext("A valid Destination Server IPv6 address must be specified.");
            }
        }
    }

    if (count($input_errors) == 0) {
        $config['dhcrelay6']['enable'] = !empty($pconfig['enable']);
        $config['dhcrelay6']['interface'] = implode(",", $pconfig['interface']);
        $config['dhcrelay6']['agentoption'] = !empty($pconfig['agentoption']);
        $config['dhcrelay6']['server'] = $pconfig['server'];
        write_config();
        // reconfigure
        services_dhcrelay6_configure();
        header(url_safe('Location: /services_dhcpv6_relay.php'));
        exit;
    }
}

/*   set the enabled flag which will tell us if DHCP server is enabled
 *   on any interface.   We will use this to disable dhcp-relay since
 *   the two are not compatible with each other.
 */
$dhcpd_enabled = false;
if (is_array($config['dhcpdv6'])) {
    foreach($config['dhcpdv6'] as $dhcp) {
        if (isset($dhcp['enable'])) {
            $dhcpd_enabled = true;
        }
    }
}

$service_hook = 'dhcrelay6';

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
          <?php if ($dhcpd_enabled):
            print_info_box(gettext('The DHCPv6 server is currently enabled. Cannot enable the DHCPv6 relay while the DHCPv6 server is enabled on any interface.'));
          else: ?>
          <div class="content-box">
            <form method="post" name="iform" id="iform">
              <div>
                <div class="table-responsive">
                  <table class="table table-striped opnsense_standard_table_form">
                    <tr>
                      <td style="width:22%"><strong><?=gettext("DHCPv6 Relay configuration"); ?></strong></td>
                      <td style="width:78%; text-align:right">
                        <small><?=gettext("full help"); ?> </small>
                        <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?= gettext('Enable') ?></td>
                      <td>
                        <input name="enable" type="checkbox" value="yes" <?=!empty($pconfig['enable']) ? "checked=\"checked\"" : ""; ?>/>
                        <strong><?=gettext("Enable DHCPv6 relay on interface");?></strong>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_interface" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Interface(s)') ?></td>
                      <td>
                        <select name="interface[]" multiple="multiple" class="selectpicker">
<?php
                        $iflist = get_configured_interface_with_descr();
                        foreach ($iflist as $ifent => $ifdesc):
                            if (!is_ipaddrv6(get_interface_ipv6($ifent))) {
                                continue;
                            }?>

                          <option value="<?=$ifent;?>" <?=!empty($pconfig['interface']) && in_array($ifent, $pconfig['interface']) ? " selected=\"selected\"" : "";?> >
                            <?=$ifdesc;?>
                          </option>
<?php
                        endforeach;?>
                        </select>
                        <div class="hidden" data-for="help_for_interface">
                          <?=gettext("Interfaces without an IPv6 address will not be shown."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_agentoption" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Append circuit ID");?></td>
                      <td>
                        <input name="agentoption" type="checkbox" value="yes" <?=!empty($pconfig['agentoption']) ? "checked=\"checked\"" : ""; ?> />
                        <div class="hidden" data-for="help_for_agentoption">
                          <?= gettext('If this is checked, the DHCPv6 relay will append the circuit ID (interface number) and the agent ID to the DHCPv6 request.') ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_server" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Destination server");?></td>
                      <td>
                        <input name="server" type="text" value="<?=!empty($pconfig['server']) ? htmlspecialchars($pconfig['server']):"";?>" />
                        <div class="hidden" data-for="help_for_server">
                          <?=gettext("This is the IPv6 address of the server to which DHCPv6 requests are relayed. You can enter multiple server IPv6 addresses, separated by commas. ");?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td></td>
                      <td>
                        <input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" />
                      </td>
                    </tr>
                  </table>
                </div>
              </div>
            </form>
          </div>
<?php
          endif; ?>
        </section>
      </div>
    </div>
  </section>
<?php include("foot.inc"); ?>
