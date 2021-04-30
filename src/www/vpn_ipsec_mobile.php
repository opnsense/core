<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
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
require_once("plugins.inc.d/ipsec.inc");

config_read_array('ipsec', 'client');
config_read_array('ipsec', 'phase1');

// define formfields
$form_fields = "user_source,local_group,pool_address,pool_netbits,pool_address_v6,pool_netbits_v6,net_list
,save_passwd,dns_domain,dns_split,dns_server1,dns_server2,dns_server3
,dns_server4,wins_server1,wins_server2,pfs_group,login_banner";

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
    if (isset($config['ipsec']['client']['net_list'])) {
        $pconfig['net_list'] = true;
    }

    if (isset($config['ipsec']['client']['save_passwd'])) {
        $pconfig['save_passwd'] = true;
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

        // input preparations
        if (!empty($pconfig['user_source'])) {
            $pconfig['user_source'] = implode(",", $pconfig['user_source']);
        }

        /* input validation */
        $reqdfields = explode(" ", "user_source");
        $reqdfieldsn =  array(gettext("User Authentication Source"));
        do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

        if (!empty($pconfig['pool_address']) && !is_ipaddr($pconfig['pool_address'])) {
            $input_errors[] = gettext("A valid IPv4 address for 'Virtual IPv4 Address Pool Network' must be specified.");
        }

        if (!empty($pconfig['pool_address_v6']) && !is_ipaddr($pconfig['pool_address_v6'])) {
            $input_errors[] = gettext("A valid IPv6 address for 'Virtual IPv6 Address Pool Network' must be specified.");
        }

        if (!empty($pconfig['dns_domain']) && !is_domain($pconfig['dns_domain'])) {
            $input_errors[] = gettext("A valid value for 'DNS Default Domain' must be specified.");
        }

        if (!empty($pconfig['dns_split'])) {
            $domain_array=preg_split("/[ ,]+/", $pconfig['dns_split']);
            foreach ($domain_array as $curdomain) {
                if (!is_domain($curdomain)) {
                    $input_errors[] = gettext("A valid split DNS domain list must be specified.");
                    break;
                }
            }
        }

        if (!empty($pconfig['dns_server1']) && !is_ipaddr($pconfig['dns_server1'])) {
            $input_errors[] = gettext("A valid IP address for 'DNS Server #1' must be specified.");
        }
        if (!empty($pconfig['dns_server2']) && !is_ipaddr($pconfig['dns_server2'])) {
            $input_errors[] = gettext("A valid IP address for 'DNS Server #2' must be specified.");
        }
        if (!empty($pconfig['dns_server3']) && !is_ipaddr($pconfig['dns_server3'])) {
            $input_errors[] = gettext("A valid IP address for 'DNS Server #3' must be specified.");
        }
        if (!empty($pconfig['dns_server4']) && !is_ipaddr($pconfig['dns_server4'])) {
            $input_errors[] = gettext("A valid IP address for 'DNS Server #4' must be specified.");
        }

        if (!empty($pconfig['wins_server1']) && !is_ipaddr($pconfig['wins_server1'])) {
            $input_errors[] = gettext("A valid IP address for 'WINS Server #1' must be specified.");
        }
        if (!empty($pconfig['wins_server2']) && !is_ipaddr($pconfig['wins_server2'])) {
            $input_errors[] = gettext("A valid IP address for 'WINS Server #2' must be specified.");
        }

        if (count($input_errors) == 0) {
            $client = array();
            $copy_fields = "user_source,local_group,pool_address,pool_netbits,pool_address_v6,pool_netbits_v6,dns_domain,dns_server1
            ,dns_server2,dns_server3,dns_server4,wins_server1,wins_server2
            ,dns_split,pfs_group,login_banner";
            foreach (explode(",", $copy_fields) as $fieldname) {
                            $fieldname = trim($fieldname);
                if (!empty($pconfig[$fieldname])) {
                    $client[$fieldname] = $pconfig[$fieldname];
                }
            }
            if (!empty($pconfig['enable'])) {
                $client['enable'] = true;
            }

            if (!empty($pconfig['net_list'])) {
                $client['net_list'] = true;
            }

            if (!empty($pconfig['save_passwd'])) {
                $client['save_passwd'] = true;
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
  dns_domain_change();
  dns_split_change();
  dns_server_change();
  wins_server_change();
  login_banner_change();
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

function dns_domain_change() {

  if (document.iform.dns_domain_enable.checked) {
    document.iform.dns_domain.disabled = 0;
    $("#dns_domain").addClass('show');
    $("#dns_domain").removeClass('hidden');
  } else {
    document.iform.dns_domain.disabled = 1;
    $("#dns_domain").addClass('hidden');
    $("#dns_domain").removeClass('show');
  }
}

function dns_split_change() {

  if (document.iform.dns_split_enable.checked){
    document.iform.dns_split.disabled = 0;
    $("#dns_split").addClass('show');
    $("#dns_split").removeClass('hidden');
  } else {
    document.iform.dns_split.disabled = 1;
    $("#dns_split").addClass('hidden');
    $("#dns_split").removeClass('show');
  }

}

function dns_server_change() {

  if (document.iform.dns_server_enable.checked) {
    document.iform.dns_server1.disabled = 0;
    document.iform.dns_server2.disabled = 0;
    document.iform.dns_server3.disabled = 0;
    document.iform.dns_server4.disabled = 0;
    $("#dns_server_enable_inputs").addClass('show');
    $("#dns_server_enable_inputs").removeClass('hidden');
  } else {
    document.iform.dns_server1.disabled = 1;
    document.iform.dns_server2.disabled = 1;
    document.iform.dns_server3.disabled = 1;
    document.iform.dns_server4.disabled = 1;
    $("#dns_server_enable_inputs").addClass('hidden');
    $("#dns_server_enable_inputs").removeClass('show');
  }
}

function wins_server_change() {

  if (document.iform.wins_server_enable.checked) {
    document.iform.wins_server1.disabled = 0;
    document.iform.wins_server2.disabled = 0;
    $("#wins_server_enable_inputs").addClass('show');
    $("#wins_server_enable_inputs").removeClass('hidden');
  } else {
    document.iform.wins_server1.disabled = 1;
    document.iform.wins_server2.disabled = 1;
    $("#wins_server_enable_inputs").addClass('hidden');
    $("#wins_server_enable_inputs").removeClass('show');
  }
}

function login_banner_change() {

  if (document.iform.login_banner_enable.checked) {
    document.iform.login_banner.disabled = 0;
    $("#login_banner").addClass('show');
    $("#login_banner").removeClass('hidden');
  } else {
    document.iform.login_banner.disabled = 1;
    $("#login_banner").addClass('hidden');
    $("#login_banner").removeClass('show');
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
foreach ($config['ipsec']['phase1'] as $ph1ent) {
    if (isset($ph1ent['mobile'])) {
        $ph1found = true;
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

if (!empty($pconfig['enable']) && !$ph1found) {
    print_legacy_box(gettext("Support for IPsec Mobile clients is enabled but a Phase1 definition was not found") . ".<br />" . gettext("Please click Create to define one."), "create", gettext("Create Phase1"));
}
if (isset($input_errors) && count($input_errors) > 0) {
    print_input_errors($input_errors);
}
?>
          <section class="col-xs-12">
           <div class="tab-content content-box col-xs-12">
               <form method="post" name="iform" id="iform">
               <div class="table-responsive">
                <table class="table table-striped opnsense_standard_table_form">
                    <tr>
                      <td style="width:22%"><b><?=gettext("IKE Extensions"); ?> </b></td>
                      <td style="width:78%; text-align:right">
                        <small><?=gettext("full help"); ?> </small>
                        <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                      </td>
                    </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Enable")?></td>
                    <td>
                      <input name="enable" type="checkbox" id="enable" value="yes" <?= !empty($pconfig['enable']) ? "checked=\"checked\"" : "";?> />
                      <?=gettext("Enable IPsec Mobile Client Support"); ?>
                    </td>
                  </tr>
                    <tr>
                    <td colspan="2"><b><?=gettext("Extended Authentication (Xauth)"); ?></b></td>
                  </tr>
                    <tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Backend for authentication");?> </td>
                    <td>
                      <select name="user_source[]" class="selectpicker" id="user_source" multiple="multiple" size="3">
<?php
                        $authmodes = explode(",", $pconfig['user_source']);
                        $auth_servers = auth_get_authserver_list();
foreach ($auth_servers as $auth_key => $auth_server) : ?>
  <option value="<?=htmlspecialchars($auth_key)?>" <?=in_array($auth_key, $authmodes) ? 'selected="selected"' : ''?>><?=htmlspecialchars($auth_server['name'])?></option>
<?php                                           endforeach; ?>
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_local_group" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Enforce local group') ?></td>
                    <td>
                      <select name="local_group" class="selectpicker" id="local_group">
                        <option value="" <?= empty($pconfig['local_group']) ? 'selected="selected"' : '' ?>>(<?= gettext('none') ?>)</option>
<?php
                      foreach (config_read_array('system', 'group') as $group):
                          $selected = $pconfig['local_group'] == $group['name'] ? 'selected="selected"' : ''; ?>
                        <option value="<?= $group['name'] ?>" <?= $selected ?>><?= $group['name'] ?></option>
<?php
                      endforeach ?>
                      </select>
                      <div class="hidden" data-for="help_for_local_group">
                        <?= gettext('Restrict access to users in the selected local group. Please be aware ' .
                          'that other authentication backends will refuse to authenticate when using this option.') ?>
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
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext('Network List') ?></td>
                    <td>
                      <input name="net_list" type="checkbox" id="net_list_enable" value="yes" <?= !empty($pconfig['net_list']) ? "checked=\"checked\"" : "";?> />
                      <?= gettext('Provide a list of accessible networks to clients') ?>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_save_passwd" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Save Xauth Password"); ?></td>
                    <td>
                      <input name="save_passwd" type="checkbox" id="save_passwd_enable" value="yes" <?= !empty($pconfig['save_passwd']) ? "checked=\"checked\"" : "";?> />
                      <?= gettext('Allow clients to save Xauth passwords (Cisco VPN client only)') ?>
                      <div class="hidden" data-for="help_for_save_passwd">
                        <?=gettext("With iPhone clients, this does not work when deployed via the iPhone configuration utility, only by manual entry."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("DNS Default Domain"); ?></td>
                    <td>
                      <input name="dns_domain_enable" type="checkbox" id="dns_domain_enable" value="yes"  <?= !empty($pconfig['dns_domain']) ? "checked=\"checked\"" : "";?> onclick="dns_domain_change()" />
                      <?=gettext("Provide a default domain name to clients"); ?>
                      <input name="dns_domain" type="text" id="dns_domain" size="30" value="<?=$pconfig['dns_domain'];?>" />
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_dns_split_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Split DNS"); ?></td>
                    <td>
                      <input name="dns_split_enable" type="checkbox" id="dns_split_enable" value="yes" <?= !empty($pconfig['dns_split']) ? "checked=\"checked\"" : "";?> onclick="dns_split_change()" />
                      <?= gettext('Provide a list of split DNS domain names to clients') ?>
                      <input name="dns_split" type="text" class="form-control" id="dns_split" size="30" value="<?=$pconfig['dns_split'];?>" />
                      <div class="hidden" data-for="help_for_dns_split_enable">
                        <?= gettext('Enter a comma-separated list. If left blank, and a default domain is set, it will be used for this value.') ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?= gettext('DNS Servers') ?></td>
                    <td>
                      <input name="dns_server_enable" type="checkbox" id="dns_server_enable" value="yes"  <?= !empty($pconfig['dns_server1']) || !empty($pconfig['dns_server2']) || !empty($pconfig['dns_server3']) || !empty($pconfig['dns_server4']) ? "checked=\"checked\"" : "";?> onclick="dns_server_change()" />
                      <?=gettext("Provide a DNS server list to clients"); ?>
                      <div id="dns_server_enable_inputs">
                        <?=gettext("Server"); ?> #1:
                        <input name="dns_server1" type="text" class="form-control" id="dns_server1" size="20" value="<?=$pconfig['dns_server1'];?>" />
                        <?=gettext("Server"); ?> #2:
                        <input name="dns_server2" type="text" class="form-control" id="dns_server2" size="20" value="<?=$pconfig['dns_server2'];?>" />
                        <?=gettext("Server"); ?> #3:
                        <input name="dns_server3" type="text" class="form-control" id="dns_server3" size="20" value="<?=$pconfig['dns_server3'];?>" />
                        <?=gettext("Server"); ?> #4:
                        <input name="dns_server4" type="text" class="form-control" id="dns_server4" size="20" value="<?=$pconfig['dns_server4'];?>" />
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("WINS Servers"); ?></td>
                    <td>
                      <input name="wins_server_enable" type="checkbox" id="wins_server_enable" value="yes" <?= !empty($pconfig['wins_server1']) || !empty($pconfig['wins_server2']) ? "checked=\"checked\"" : "";?> onclick="wins_server_change()" />
                      <?= gettext('Provide a WINS server list to clients') ?>
                      <div id="wins_server_enable_inputs">
                        <?=gettext("Server"); ?> #1:
                        <input name="wins_server1" type="text" class="form-control" id="wins_server1" size="20" value="<?=$pconfig['wins_server1'];?>" />
                        <?=gettext("Server"); ?> #2:
                        <input name="wins_server2" type="text" class="form-control" id="wins_server2" size="20" value="<?=$pconfig['wins_server2'];?>" />
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_pfs_group" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Phase 2 PFS Group') ?></td>
                    <td>
                        <select name="pfs_group" class="selectpicker" id="pfs_group">
<?php
                        $p2_dhgroups = array(
                            0 => gettext('off'),
                            1 => '1 (768 bits)',
                            2 => '2 (1024 bits)',
                            5 => '5 (1536 bits)',
                            14 => '14 (2048 bits)',
                            15 => '15 (3072 bits)',
                            16 => '16 (4096 bits)',
                            17 => '17 (6144 bits)',
                            18 => '18 (8192 bits)',
                            19 => '19 (NIST EC 256 bits)',
                            20 => '20 (NIST EC 384 bits)',
                            21 => '21 (NIST EC 521 bits)',
                            22 => '22 (1024(sub 160) bits)',
                            23 => '23 (2048(sub 224) bits)',
                            24 => '24 (2048(sub 256) bits)',
                            28 => '28 (Brainpool EC 256 bits)',
                            29 => '29 (Brainpool EC 384 bits)',
                            30 => '30 (Brainpool EC 512 bits)',
                            31 => '31 (Elliptic Curve 25519)',
                        );
                        foreach ($p2_dhgroups as $keygroup => $keygroupname): ?>
                          <option value="<?=$keygroup;
?>" <?= $pconfig['pfs_group'] == $keygroup ? "selected=\"selected\"" : "" ; ?>>
                            <?=$keygroupname;?>
                          </option>
<?php
endforeach;
?>
                        </select>
                        <div class="hidden" data-for="help_for_pfs_group">
                            <?=gettext("Provide the selected phase 2 PFS group to all mobile clients."); ?>
                        </div>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?= gettext('Login Banner') ?></td>
                    <td>
                      <input name="login_banner_enable" type="checkbox" id="login_banner_enable" value="yes" <?= !empty($pconfig['login_banner']) ? "checked=\"checked\"" : "";?> onclick="login_banner_change()" />
                      <?=gettext("Provide a login banner to clients"); ?>
                      <textarea name="login_banner" cols="65" rows="7" id="login_banner" class="formpre"><?=$pconfig['login_banner'];?></textarea>
                    </td>
                  </tr>
                  <tr>
                    <td>&nbsp;</td>
                    <td>
                      <input name="submit" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save')); ?>" />
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
