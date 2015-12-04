<?php

/*
  Copyright (C) 2014-2015 Deciso B.V.
  Copyright (C) 2005-2007 Scott Ullrich
  Copyright (C) 2008 Shrew Soft Inc
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
require_once("filter.inc");
require_once("system.inc");
require_once("pfsense-utils.inc");


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();
    $pconfig['ipv6allow'] = isset($config['system']['ipv6allow']);
    $pconfig['ipv6nat_enable'] = isset($config['diag']['ipv6nat']['enable']);
    $pconfig['ipv6nat_ipaddr'] = isset($config['diag']['ipv6nat']['ipaddr']) ? $config['diag']['ipv6nat']['ipaddr']:"" ;
    $pconfig['prefer_ipv4'] = isset($config['system']['prefer_ipv4']);
    $pconfig['polling'] = isset($config['system']['polling']);
    $pconfig['disablechecksumoffloading'] = isset($config['system']['disablechecksumoffloading']);
    $pconfig['disablesegmentationoffloading'] = isset($config['system']['disablesegmentationoffloading']);
    $pconfig['disablelargereceiveoffloading'] =  isset($config['system']['disablelargereceiveoffloading']);
    $pconfig['disablevlanhwfilter'] = isset($config['system']['disablevlanhwfilter']);
    $pconfig['sharednet'] = isset($config['system']['sharednet']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    $input_errors = array();

    if (!empty($pconfig['ipv6nat_enable']) && !is_ipaddr($_POST['ipv6nat_ipaddr'])) {
        $input_errors[] = gettext("You must specify an IP address to NAT IPv6 packets.");
    }

    if (!empty($pconfig['ipv6nat_enable'])) {
        $config['diag']['ipv6nat'] = array();
        $config['diag']['ipv6nat']['enable'] = true;
        $config['diag']['ipv6nat']['ipaddr'] = $_POST['ipv6nat_ipaddr'];
    } elseif (isset($config['diag']['ipv6nat'])) {
        unset($config['diag']['ipv6nat']);
    }

    if (!empty($pconfig['ipv6allow'])) {
        $config['system']['ipv6allow'] = true;
    } elseif (isset($config['system']['ipv6allow'])) {
        unset($config['system']['ipv6allow']);
    }

    if (!empty($pconfig['prefer_ipv4'])) {
        $config['system']['prefer_ipv4'] = true;
    } elseif (isset($config['system']['prefer_ipv4'])) {
        unset($config['system']['prefer_ipv4']);
    }

    if (!empty($pconfig['sharednet'])) {
        $config['system']['sharednet'] = true;
    } elseif (isset($config['system']['sharednet'])) {
        unset($config['system']['sharednet']);
    }

    if (!empty($pconfig['polling'])) {
        $config['system']['polling'] = true;
    } elseif (isset($config['system']['polling'])) {
        unset($config['system']['polling']);
    }

    if (!empty($pconfig['disablechecksumoffloading'])) {
        $config['system']['disablechecksumoffloading'] = true;
    } elseif (isset($config['system']['disablechecksumoffloading'])) {
        unset($config['system']['disablechecksumoffloading']);
    }

    if (!empty($pconfig['disablesegmentationoffloading'])) {
        $config['system']['disablesegmentationoffloading'] = true;
    } elseif (isset($config['system']['disablesegmentationoffloading'])) {
        unset($config['system']['disablesegmentationoffloading']);
    }

    if (!empty($_POST['disablelargereceiveoffloading'])) {
        $config['system']['disablelargereceiveoffloading'] = true;
    } elseif (isset($config['system']['disablelargereceiveoffloading'])) {
        unset($config['system']['disablelargereceiveoffloading']);
    }

    if (!empty($_POST['disablevlanhwfilter'])) {
        $config['system']['disablevlanhwfilter'] = true;
    } elseif(isset($config['system']['disablevlanhwfilter'])) {
        unset($config['system']['disablevlanhwfilter']);
    }

    if (count($input_errors) == 0) {

        setup_polling();
        if (isset($config['system']['sharednet'])) {
            system_disable_arp_wrong_if();
        } else {
            // system_enable_arp_wrong_if
            set_sysctl(array(
                "net.link.ether.inet.log_arp_wrong_iface" => "1",
                "net.link.ether.inet.log_arp_movements" => "1"
            ));
        }
        setup_microcode();

        write_config();
        $savemsg = get_std_save_message();

        prefer_ipv4_or_ipv6();
        filter_configure();
    }
}

legacy_html_escape_form_data($pconfig);

$pgtitle = array(gettext("System"),gettext("Settings"),gettext("Networking"));
include("head.inc");

?>

<body>
  <?php include("fbegin.inc"); ?>

  <script type="text/javascript">
  //<![CDATA[
  function enable_change(enable_over) {
    if (document.iform.ipv6nat_enable.checked || enable_over) {
        document.iform.ipv6nat_ipaddr.disabled = 0;
    } else {
      document.iform.ipv6nat_ipaddr.disabled = 1;
    }
  }

  $( document ).ready(function() {
    enable_change(false);
  });
  //]]>
  </script>


<!-- row -->
<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
<?php
    if (isset($input_errors) && count($input_errors) > 0) {
        print_input_errors($input_errors);
    }
    if (isset($savemsg)) {
        print_info_box($savemsg);
    }
?>
    <section class="col-xs-12">
      <div class="content-box tab-content table-responsive">
        <form action="system_advanced_network.php" method="post" name="iform" id="iform">
          <table class="table table-striped">
            <tr>
              <td width="22%"><strong><?=gettext("IPv6 Options");?></strong></td>
              <td  width="78%" align="right">
                <small><?=gettext("full help"); ?> </small>
                <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page" type="button"></i></a>
              </td>
            </tr>
              <tr>
                <td><a id="help_for_ipv6allow" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Allow IPv6"); ?></td>
                <td>
                  <input name="ipv6allow" type="checkbox" value="yes" <?= !empty($pconfig['ipv6allow']) ? "checked=\"checked\"" :"";?> onclick="enable_change(false)" />
                  <div class="hidden" for="help_for_ipv6allow">
                    <strong><?=gettext("Allow IPv6"); ?></strong><br />
                    <?=gettext("All IPv6 traffic will be blocked by the firewall unless this box is checked."); ?><br />
                    <?=gettext("NOTE: This does not disable any IPv6 features on the firewall, it only blocks traffic."); ?><br />
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_ipv6nat_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("IPv6 over IPv4 Tunneling"); ?></td>
                <td>
                  <input name="ipv6nat_enable" type="checkbox" id="ipv6nat_enable" value="yes" <?=!empty($pconfig['ipv6nat_enable']) ? "checked=\"checked\"" : "";?> onclick="enable_change(false)" />
                  <strong><?=gettext("Enable IPv4 NAT encapsulation of IPv6 packets"); ?></strong><br />
                  <div class="hidden" for="help_for_ipv6nat_enable">
                    <?=gettext("This provides an RFC 2893 compatibility mechanism ".
                                        "that can be used to tunneling IPv6 packets over IPv4 ".
                                        "routing infrastructures. If enabled, don't forget to ".
                                        "add a firewall rule to permit IPv6 packets."); ?>
                  </div>
                  <?=gettext("IP address"); ?>&nbsp;:&nbsp;
                  <input name="ipv6nat_ipaddr" type="text" class="formfld unknown" id="ipv6nat_ipaddr" size="20" value="<?=$pconfig['ipv6nat_ipaddr'];?>" />
                </td>
              </tr>
              <tr>
                <td><a id="help_for_prefer_ipv4" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Prefer IPv4 over IPv6"); ?></td>
                <td>
                  <input name="prefer_ipv4" type="checkbox" id="prefer_ipv4" value="yes" <?= !empty($config['system']['prefer_ipv4']) ? "checked=\"checked\"" : "";?> />
                  <div class="hidden" for="help_for_prefer_ipv4">
                    <strong><?=gettext("Prefer to use IPv4 even if IPv6 is available"); ?></strong><br />
                    <?=gettext("By default, if a hostname resolves IPv6 and IPv4 addresses ".
                                        "IPv6 will be used, if you check this option, IPv4 will be " .
                                        "used instead of IPv6."); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <th colspan="2" valign="top" class="listtopic"><?=gettext("Network Interfaces"); ?></th>
              </tr>
              <tr>
                <td><a id="help_for_polling" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Device polling"); ?></td>
                <td>
                  <input name="polling" type="checkbox" id="polling_enable" value="yes" <?= !empty($pconfig['polling']) ? "checked=\"checked\"" :"";?> />
                  <strong><?=gettext("Enable device polling"); ?></strong>
                  <div class="hidden" for="help_for_polling">
                    <?php printf(gettext("Device polling is a technique that lets the system periodically poll network devices for new data instead of relying on interrupts. This prevents your webConfigurator, SSH, etc. from being inaccessible due to interrupt floods when under extreme load. Generally this is not recommended. Not all NICs support polling; see the %s homepage for a list of supported cards."), $g['product_name']); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_disablechecksumoffloading" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Hardware CRC"); ?></td>
                <td>
                  <input name="disablechecksumoffloading" type="checkbox" id="disablechecksumoffloading" value="yes" <?= !empty($pconfig['disablechecksumoffloading']) ? "checked=\"checked\"" :"";?> />
                  <strong><?=gettext("Disable hardware checksum offload"); ?></strong>
                  <div class="hidden" for="help_for_disablechecksumoffloading">
                    <?=gettext("Checking this option will disable hardware checksum offloading. Checksum offloading is broken in some hardware, particularly some Realtek cards. Rarely, drivers may have problems with checksum offloading and some specific NICs."); ?>
                    <br />
                    <span class="text-warning"><strong><?=gettext("Note:");?>&nbsp;</strong></span>
                    <?=gettext("This will take effect after you reboot the machine or re-configure each interface.");?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_disablesegmentationoffloading" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Hardware TSO"); ?></td>
                <td>
                  <input name="disablesegmentationoffloading" type="checkbox" id="disablesegmentationoffloading" value="yes" <?= !empty($pconfig['disablesegmentationoffloading']) ? "checked=\"checked\"" :"";?>/>
                  <strong><?=gettext("Disable hardware TCP segmentation offload"); ?></strong><br />
                  <div class="hidden" for="help_for_disablesegmentationoffloading">
                    <?=gettext("Checking this option will disable hardware TCP segmentation offloading (TSO, TSO4, TSO6). This offloading is broken in some hardware drivers, and may impact performance with some specific NICs."); ?>
                    <br />
                    <span class="text-warning"><strong><?=gettext("Note:");?>&nbsp;</strong></span>
                    <?=gettext("This will take effect after you reboot the machine or re-configure each interface.");?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_disablelargereceiveoffloading" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Hardware LRO"); ?></td>
                <td>
                  <input name="disablelargereceiveoffloading" type="checkbox" id="disablelargereceiveoffloading" value="yes" <?= !empty($pconfig['disablelargereceiveoffloading']) ? "checked=\"checked\"" :"";?>/>
                  <strong><?=gettext("Disable hardware large receive offload"); ?></strong><br />
                  <div class="hidden" for="help_for_disablelargereceiveoffloading">
                    <?=gettext("Checking this option will disable hardware large receive offloading (LRO). This offloading is broken in some hardware drivers, and may impact performance with some specific NICs."); ?>
                    <br />
                    <span class="text-warning"><strong><?=gettext("Note:");?>&nbsp;</strong></span>
                    <?=gettext("This will take effect after you reboot the machine or re-configure each interface.");?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_disablevlanhwfilter" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("VLAN Hardware Filtering"); ?></td>
                <td>
                  <input name="disablevlanhwfilter" type="checkbox" id="disablevlanhwfilter" value="yes" <?= !empty($pconfig['disablevlanhwfilter']) ? "checked=\"checked\"" : "";?>/>
                  <strong><?=gettext("Disable VLAN Hardware Filtering"); ?></strong><br />
                  <div class="hidden" for="help_for_disablevlanhwfilter">
                    <?=gettext("Checking this option will disable VLAN hardware filtering. This offloading is broken in some hardware drivers, and may impact performance with some specific NICs."); ?>
                    <br />
                    <span class="text-warning"><strong><?=gettext("Note:");?>&nbsp;</strong></span>
                    <?=gettext("This will take effect after you reboot the machine or re-configure each interface.");?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_sharednet" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("ARP Handling"); ?></td>
                <td>
                  <input name="sharednet" type="checkbox" id="sharednet" value="yes" <?= !empty($pconfig['sharednet']) ? "checked=\"checked\"" :"";?>/>
                  <strong><?=gettext("Suppress ARP messages"); ?></strong><br />
                  <div class="hidden" for="help_for_sharednet">
                    <?=gettext("This option will suppress ARP log messages when multiple interfaces reside on the same broadcast domain"); ?>
                  </div>
                </td>
              </tr>
            <tr>
              <td>&nbsp;</td>
              <td><input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" /></td>
            </tr>
            </table>
          </form>
        </div>
      </section>
    </div>
  </div>
</section>


<?php include("foot.inc");
