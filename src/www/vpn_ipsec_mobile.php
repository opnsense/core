<?php

/*
 * Copyright (C) 2014-2025 Deciso B.V.
 * Copyright (C) 2008 Shrew Soft Inc. <mgrooms@shrew.net>
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
require_once("filter.inc");
require_once("system.inc");
require_once("plugins.inc.d/ipsec.inc");

config_read_array('ipsec', 'client');
config_read_array('ipsec', 'phase1');

// define formfields
$form_fields = "pool_address,pool_netbits,pool_address_v6,pool_netbits_v6";

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // pass savemessage
    if (isset($_GET['savemsg'])) {
        $savemsg = htmlspecialchars($_GET['savemsg']);
    }
    $pconfig = array();
    // defaults
    $pconfig['pool_netbits'] = 24;
    $pconfig['pool_netbits_v6'] = 64;

    // copy / initialize $pconfig attributes
    foreach (explode(",", $form_fields) as $fieldname) {
        $fieldname = trim($fieldname);
        if (isset($config['ipsec']['client'][$fieldname])) {
            $pconfig[$fieldname] = $config['ipsec']['client'][$fieldname];
        } elseif (!isset($pconfig[$fieldname])) {
          // initialize element
            $pconfig[$fieldname] = null;
        }
    }
    if (isset($config['ipsec']['client']['enable'])) {
        $pconfig['enable'] = true;
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    $pconfig = $_POST;
    if (isset($_POST['create'])) {
        // create new phase1 entry
        header(url_safe('Location: /vpn_ipsec_phase1.php?mobile=true'));
        exit;
    } elseif (isset($_POST['apply'])) {
        // apply changes
        ipsec_configure_do();
        $savemsg = get_std_save_message(true);
        clear_subsystem_dirty('ipsec');
        header(url_safe('Location: /vpn_ipsec_mobile.php?savemsg=%s', array($savemsg)));
        exit;
    } elseif (isset($_POST['submit'])) {
        // save form changes
        if (!empty($pconfig['pool_address']) && !is_ipaddr($pconfig['pool_address'])) {
            $input_errors[] = gettext("A valid IPv4 address for 'Virtual IPv4 Address Pool Network' must be specified.");
        }

        if (!empty($pconfig['pool_address_v6']) && !is_ipaddr($pconfig['pool_address_v6'])) {
            $input_errors[] = gettext("A valid IPv6 address for 'Virtual IPv6 Address Pool Network' must be specified.");
        }


        if (count($input_errors) == 0) {
            $client = array();
            $copy_fields = "pool_address,pool_netbits,pool_address_v6,pool_netbits_v6";
            foreach (explode(",", $copy_fields) as $fieldname) {
                $fieldname = trim($fieldname);
                if (!empty($pconfig[$fieldname])) {
                    $client[$fieldname] = $pconfig[$fieldname];
                }
            }
            if (!empty($pconfig['enable'])) {
                $client['enable'] = true;
            }

            $config['ipsec']['client'] = $client;

            write_config();
            mark_subsystem_dirty('ipsec');
            header(url_safe('Location: /vpn_ipsec_mobile.php'));
            exit;
        }
    }

    // initialize missing post attributes
    foreach (explode(",", $form_fields) as $fieldname) {
        $fieldname = trim($fieldname);
        if (!isset($pconfig[$fieldname])) {
            $pconfig[$fieldname] = null;
        }
    }
}

legacy_html_escape_form_data($pconfig);

$service_hook = 'strongswan';

include("head.inc");

?>

<body>

<script>
//<![CDATA[
$( document ).ready(function() {
  pool_change();
  pool_v6_change();

  $("#ike_mobile_enable").change(function(){
      if ($(this).is(':checked')) {
          $("#ike_extensions").find("tr:not(.ike_heading)").show();
      } else {
          $("#ike_extensions").find("tr:not(.ike_heading)").hide();
      }
  });
  $("#ike_mobile_enable").change();

});

function pool_change() {

  if (document.iform.pool_enable.checked) {
    document.iform.pool_address.disabled = 0;
    document.iform.pool_netbits.disabled = 0;
  } else {
    document.iform.pool_address.disabled = 1;
    document.iform.pool_netbits.disabled = 1;
  }
}

function pool_v6_change() {

    if (document.iform.pool_enable_v6.checked) {
        document.iform.pool_address_v6.disabled = 0;
        document.iform.pool_netbits_v6.disabled = 0;
    } else {
        document.iform.pool_address_v6.disabled = 1;
        document.iform.pool_netbits_v6.disabled = 1;
    }
}

//]]>
</script>

<?php include("fbegin.inc"); ?>

  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
<?php
if (isset($savemsg)) {
    print_info_box($savemsg);
}
if (isset($config['ipsec']['enable']) && is_subsystem_dirty('ipsec')) {
    print_info_box_apply(gettext("The IPsec tunnel configuration has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));
}
$ph1found = false;
$legacy_radius_configured = false;
foreach ($config['ipsec']['phase1'] as $ph1ent) {
    if (!isset($ph1ent['disabled']) && isset($ph1ent['mobile'])) {
        $ph1found = true;
        if (($ph1ent['authentication_method'] ?? '') == 'eap-radius') {
            $legacy_radius_configured = true;
        }
    }
}

function print_legacy_box($msg, $name, $value)
{
  $savebutton = "<form method=\"post\">";
  $savebutton .= "<input name=\"{$name}\" type=\"submit\" class=\"btn btn-default\" id=\"{$name}\" value=\"{$value}\" />";
  if (!empty($_POST['if'])) {
    $savebutton .= "<input type=\"hidden\" name=\"if\" value=\"" . htmlspecialchars($_POST['if']) . "\" />";
  }
  $savebutton .= '</form>';

  echo <<<EOFnp
<div class="col-xs-12">
  <div class="alert alert-info alert-dismissible" role="alert">
    {$savebutton}
    <p>{$msg}</p>
  </div>
</div>

EOFnp;
}

if (!empty($pconfig['enable']) && !$ph1found && !(new OPNsense\IPsec\Swanctl())->isEnabled()) {
    print_legacy_box(gettext("Support for IPsec Mobile clients is enabled but a Phase1 definition was not found") . ".<br />" . gettext("When using (legacy) tunnels, please click Create to define one."), "create", gettext("Create Phase1"));
}
if (isset($input_errors) && count($input_errors) > 0) {
    print_input_errors($input_errors);
}
?>
        <form method="post" name="iform" id="iform">
          <section class="col-xs-12">
             <div class="tab-content content-box col-xs-12">
                <table class="table table-striped opnsense_standard_table_form" id="ike_extensions">
                  <tr class="ike_heading">
                      <td style="width:22%"><b><?=gettext("IKE Extensions"); ?> </b></td>
                      <td style="width:78%; text-align:right">
                        <small><?=gettext("full help"); ?> </small>
                        <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                      </td>
                    </tr>
                  <tr class="ike_heading">
                    <td> <a id="help_for_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Enable")?></td>
                    <td>
                      <input name="enable" id="ike_mobile_enable" type="checkbox" value="yes" <?= !empty($pconfig['enable']) ? "checked=\"checked\"" : "";?> />
                      <?=gettext("Enable IPsec Mobile Client Support"); ?>
                      <div class="hidden" data-for="help_for_enable">
                        <?= gettext(
                          'Enable mobile settings, '.
                          'some of the settings below depend on configuration choices in configured tunnels, ' .
                          'when not dependent on configured networks, they will also be used for configured connections when this option is checked.') ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                      <td colspan="2"><b><?=gettext("Client Configuration (mode-cfg)"); ?> </b></td>
                    </tr>
                  <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Virtual IPv4 Address Pool"); ?></td>
                      <td>
                          <input name="pool_enable" type="checkbox" id="pool_enable" value="yes" <?= !empty($pconfig['pool_address'])&&!empty($pconfig['pool_netbits']) ? "checked=\"checked\"" : "";?> onclick="pool_change()" />
                          <?=gettext("Provide a virtual IPv4 address to clients"); ?>
                          <div class="input-group">
                              <input name="pool_address" type="text" class="form-control" id="pool_address" size="20" value="<?=$pconfig['pool_address'];?>" style="width:200px;" />
                              <select name="pool_netbits" class="selectpicker form-control" id="pool_netbits" data-width="70px" data-size="10">
<?php
                              for ($i = 32; $i >= 0; $i--) :?>
                                  <option value="<?=$i;?>" <?= ($i == $pconfig['pool_netbits']) ? "selected=\"selected\"" : "";?>>
                                      <?=$i;?>
                                  </option>
<?php
                              endfor; ?>
                              </select>
                          </div>
                      </td>
                  </tr>
                  <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Virtual IPv6 Address Pool"); ?></td>
                      <td>
                          <input name="pool_enable_v6" type="checkbox" id="pool_enable_v6" value="yes" <?= !empty($pconfig['pool_address_v6'])&&!empty($pconfig['pool_netbits_v6']) ? "checked=\"checked\"" : "";?> onclick="pool_v6_change()" />
                          <?=gettext("Provide a virtual IPv6 address to clients"); ?>
                          <div class="input-group">
                              <input name="pool_address_v6" type="text" class="form-control" id="pool_address_v6" size="20" value="<?=$pconfig['pool_address_v6'];?>" style="width:200px;" />
                              <select name="pool_netbits_v6" class="selectpicker form-control" id="pool_netbits_v6" data-width="70px" data-size="10">
<?php
                              for ($i = 128; $i >= 0; $i--) :?>
                                  <option value="<?=$i;?>" <?= ($i == $pconfig['pool_netbits_v6']) ? "selected=\"selected\"" : "";?>>
                                      <?=$i;?>
                                  </option>
<?php
                              endfor; ?>
                              </select>
                          </div>
                      </td>
                  </tr>
                </table>
            </div>
        </section>
        <section class="col-xs-12">
            <div class="tab-content content-box col-xs-12">
              <table class="table table-striped opnsense_standard_table_form" id="ike_extensions">
                  <tr>
                    <td style="width:22%">&nbsp;</td>
                    <td style="width:78%;">
                      <input name="submit" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save')); ?>" />
                    </td>
                  </tr>
                </table>
               </div>
            </div>
          </section>
        </form>
      </div>
  </div>
</section>

<?php include("foot.inc"); ?>
