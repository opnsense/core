<?php

/*
	Copyright (C) 2014-2015 Deciso B.V.
	Copyright (C) 2008 Shrew Soft Inc
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

require_once("interfaces.inc");
require_once("guiconfig.inc");
require_once("filter.inc");
require_once("vpn.inc");
require_once("services.inc");
require_once("pfsense-utils.inc");

if (!isset($config['ipsec']) || !is_array($config['ipsec'])) {
    $config['ipsec'] = array();
}

if (!isset($config['ipsec']['phase1'])) {
    $config['ipsec']['phase1'] = array();
}

if (!isset($config['ipsec']['client'])) {
    $config['ipsec']['client'] = array();
}

// define formfields
$form_fields = "user_source,group_source,pool_address,pool_netbits,net_list
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
        header("Location: vpn_ipsec_phase1.php?mobile=true");
        exit;
    } elseif (isset($_POST['apply'])) {
        // apply changes
        $retval = 0;
        $retval = vpn_ipsec_configure();
        $savemsg = get_std_save_message();
        if ($retval >= 0) {
            if (is_subsystem_dirty('ipsec')) {
                clear_subsystem_dirty('ipsec');
            }
        }
        header("Location: vpn_ipsec_mobile.php?savemsg=".$savemsg);
        exit;
    } elseif (isset($_POST['submit'])) {
        // save form changes

        // input preparations
        if (!empty($pconfig['user_source'])) {
            $pconfig['user_source'] = implode(",", $pconfig['user_source']);
        }

        /* input validation */
        $reqdfields = explode(" ", "user_source group_source");
        $reqdfieldsn =  array(gettext("User Authentication Source"),gettext("Group Authentication Source"));
        do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

        if (!empty($pconfig['pool_address']) && !is_ipaddr($pconfig['pool_address'])) {
            $input_errors[] = gettext("A valid IP address for 'Virtual Address Pool Network' must be specified.");
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
            $copy_fields = "user_source,group_source,pool_address,pool_netbits,dns_domain,dns_server1
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

            header("Location: vpn_ipsec_mobile.php");
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

$pgtitle = array(gettext('VPN'),gettext('IPsec'), gettext('Mobile Clients'));

include("head.inc");
?>

<body>

<script type="text/javascript">
//<![CDATA[
$( document ).ready(function() {
  pool_change();
  dns_domain_change();
  dns_split_change();
  dns_server_change();
  wins_server_change();
  pfs_group_change();
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

function pfs_group_change() {

	if (document.iform.pfs_group_enable.checked) {
    document.iform.pfs_group.disabled = 0;
    $("#pfs_group").addClass('show');
    $("#pfs_group").removeClass('hidden');
  } else {
    document.iform.pfs_group.disabled = 1;
    $("#pfs_group").addClass('hidden');
    $("#pfs_group").removeClass('show');
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
    print_info_box_np(gettext("The IPsec tunnel configuration has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));
}
                $ph1found = false;
foreach ($config['ipsec']['phase1'] as $ph1ent) {
    if (isset($ph1ent['mobile'])) {
        $ph1found = true;
    }
}
if (!empty($pconfig['enable']) && !$ph1found) {
    print_info_box_np(gettext("Support for IPsec Mobile clients is enabled but a Phase1 definition was not found") . ".<br />" . gettext("Please click Create to define one."), gettext("create"), gettext("Create Phase1"));
}
if (isset($input_errors) && count($input_errors) > 0) {
    print_input_errors($input_errors);
}
?>
			    <section class="col-xs-12">
					 <div class="tab-content content-box col-xs-12">
							 <form action="vpn_ipsec_mobile.php" method="post" name="iform" id="iform">
							 <div class="table-responsive">
								<table class="table table-striped table-sort">
                    <tr>
                      <td width="22%"><b><?=gettext("IKE Extensions"); ?> </b></td>
                      <td width="78%" align="right">
                        <small><?=gettext("full help"); ?> </small>
                        <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page" type="button"></i></a>
                      </td>
                    </tr>
									<tr>
                      <td><a id="help_for_enabled" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Enable")?></td>
										<td>
                        <input name="enable" type="checkbox" id="enable" value="yes" <?= !empty($pconfig['enable']) ? "checked=\"checked\"" : "";?> />
                        <div class="hidden" for="help_for_enabled">
                            <?=gettext("Enable IPsec Mobile Client Support"); ?>
                        </div>
										</td>
									</tr>
                    <tr>
										<td colspan="2"><b><?=gettext("Extended Authentication (Xauth)"); ?></b></td>
									</tr>
                    <tr>
									<tr>
										<td><i class="fa fa-info-circle text-muted"></i> <?=gettext("User Authentication"); ?></td>
										<td>
											<?=gettext("Source"); ?>:
											<select name="user_source[]" class="form-control" id="user_source" multiple="multiple" size="3">
<?php
                        $authmodes = explode(",", $pconfig['user_source']);
                        $auth_servers = auth_get_authserver_list();
foreach ($auth_servers as $auth_server) :
?>
  <option value="<?=htmlspecialchars($auth_server['name'])?>" <?=in_array($auth_server['name'], $authmodes) ? "selected=\"selected\"" : ""?> ><?=$auth_server['name']?></option>
<?php                                           endforeach;
?>
											</select>
										</td>
									</tr>
									<tr>
										<td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Group Authentication"); ?></td>
										<td>
											<select name="group_source" class="form-control" id="group_source">
												<option value="none"><?=gettext("none"); ?></option>
												<option value="system" <?= $pconfig['group_source'] == "system" ?  "selected=\"selected\"" : "";
?>><?=gettext("system"); ?></option>
											</select>
										</td>
									</tr>
                    <tr>
                      <td colspan="2"><b><?=gettext("Client Configuration (mode-cfg)"); ?> </b></td>
                    </tr>
									<tr>
										<td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Virtual Address Pool"); ?></td>
										<td>
                        <input name="pool_enable" type="checkbox" id="pool_enable" value="yes" <?= !empty($pconfig['pool_address'])&&!empty($pconfig['pool_netbits']) ? "checked=\"checked\"" : "";?> onclick="pool_change()" />
                        <?=gettext("Provide a virtual IP address to clients"); ?><br />
											<?=gettext("Network"); ?>:&nbsp;
											<input name="pool_address" type="text" class="form-control unknown" id="pool_address" size="20" value="<?=$pconfig['pool_address'];?>" />
											/
											<select name="pool_netbits" class="form-control" id="pool_netbits">
															<?php for ($i = 32; $i >= 0; $i--) :
    ?>
															<option value="<?=$i;
?>" <?= ($i == $pconfig['pool_netbits']) ? "selected=\"selected\"" : "";?>>
																<?=$i;?>
															</option>
															<?php
endfor; ?>
											</select>
										</td>
									</tr>
									<tr>
										<td><a id="help_for_net_list" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Network List"); ?></td>
										<td>
                        <input name="net_list" type="checkbox" id="net_list_enable" value="yes" <?= !empty($pconfig['net_list']) ? "checked=\"checked\"" : "";?> />
                        <div class="hidden" for="help_for_net_list">
                            <?=gettext("Provide a list of accessible networks to clients"); ?><br />
                        </div>
										</td>
									</tr>
									<tr>
										<td><a id="help_for_save_passwd" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Save Xauth Password"); ?></td>
										<td>
                        <input name="save_passwd" type="checkbox" id="save_passwd_enable" value="yes" <?= !empty($pconfig['save_passwd']) ? "checked=\"checked\"" : "";?> />
                        <div class="hidden" for="help_for_save_passwd">
                            <?=gettext("Allow clients to save Xauth passwords (Cisco VPN client only)."); ?><br />
                            <?=gettext("NOTE: With iPhone clients, this does not work when deployed via the iPhone configuration utility, only by manual entry."); ?><br />
                        </div>
										</td>
									</tr>
									<tr>
										<td><a id="help_for_dns_domain_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("DNS Default Domain"); ?></td>
										<td>
                        <input name="dns_domain_enable" type="checkbox" id="dns_domain_enable" value="yes"  <?= !empty($pconfig['dns_domain']) ? "checked=\"checked\"" : "";?> onclick="dns_domain_change()" />
                        <input name="dns_domain" type="text" id="dns_domain" size="30" value="<?=$pconfig['dns_domain'];?>" />
                        <div class="hidden" for="help_for_dns_domain_enable">
                            <?=gettext("Provide a default domain name to clients"); ?>
                        </div>
										</td>
									</tr>
									<tr>
										<td><a id="help_for_dns_split_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Split DNS"); ?></td>
										<td>
                        <input name="dns_split_enable" type="checkbox" id="dns_split_enable" value="yes" <?= !empty($pconfig['dns_split']) ? "checked=\"checked\"" : "";?> onclick="dns_split_change()" />
                        <input name="dns_split" type="text" class="form-control unknown" id="dns_split" size="30" value="<?=$pconfig['dns_split'];?>" />
                        <div class="hidden" for="help_for_dns_split_enable">
                            <?=gettext("Provide a list of split DNS domain names to clients. Enter a comma separated list."); ?><br />
                            <?=gettext("NOTE: If left blank, and a default domain is set, it will be used for this value."); ?>
                        </div>
										</td>
									</tr>
									<tr>
										<td><a id="help_for_dns_server_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("DNS Servers"); ?></td>
										<td>
                        <input name="dns_server_enable" type="checkbox" id="dns_server_enable" value="yes"  <?= !empty($pconfig['dns_server1']) || !empty($pconfig['dns_server2']) || !empty($pconfig['dns_server3']) || !empty($pconfig['dns_server4']) ? "checked=\"checked\"" : "";?> onclick="dns_server_change()" />
                        <div id="dns_server_enable_inputs">
                            <?=gettext("Server"); ?> #1:
                          <input name="dns_server1" type="text" class="form-control unknown" id="dns_server1" size="20" value="<?=$pconfig['dns_server1'];?>" />
                            <?=gettext("Server"); ?> #2:
                          <input name="dns_server2" type="text" class="form-control unknown" id="dns_server2" size="20" value="<?=$pconfig['dns_server2'];?>" />
                            <?=gettext("Server"); ?> #3:
                          <input name="dns_server3" type="text" class="form-control unknown" id="dns_server3" size="20" value="<?=$pconfig['dns_server3'];?>" />
                            <?=gettext("Server"); ?> #4:
                          <input name="dns_server4" type="text" class="form-control unknown" id="dns_server4" size="20" value="<?=$pconfig['dns_server4'];?>" />
                        </div>
                        <div class="hidden" for="help_for_dns_server_enable">
                            <?=gettext("Provide a DNS server list to clients"); ?>
                        </div>
										</td>
									</tr>
									<tr>
										<td><a id="help_for_wins_server_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("WINS Servers"); ?></td>
										<td>
                        <input name="wins_server_enable" type="checkbox" id="wins_server_enable" value="yes" <?= !empty($pconfig['wins_server1']) || !empty($pconfig['wins_server2']) ? "checked=\"checked\"" : "";?> onclick="wins_server_change()" />
                        <div id="wins_server_enable_inputs">
                            <?=gettext("Server"); ?> #1:
                          <input name="wins_server1" type="text" class="form-control unknown" id="wins_server1" size="20" value="<?=$pconfig['wins_server1'];?>" />
                            <?=gettext("Server"); ?> #2:
                          <input name="wins_server2" type="text" class="form-control unknown" id="wins_server2" size="20" value="<?=$pconfig['wins_server2'];?>" />
                        </div>
                        <div class="hidden" for="help_for_wins_server_enable">
                            <?=gettext("Provide a WINS server list to clients"); ?>
                        </div>
										</td>
									</tr>
									<tr>
										<td><a id="help_for_pfs_group_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Phase2 PFS Group"); ?></td>
										<td>
                        <input name="pfs_group_enable" type="checkbox" id="pfs_group_enable" value="yes" <?= !empty($pconfig['pfs_group']) ? "checked=\"checked\"" : "";?>  onclick="pfs_group_change()" />

                        <select name="pfs_group" class="form-control" id="pfs_group">
<?php                     foreach ($p2_pfskeygroups as $keygroup => $keygroupname) :
?>
                          <option value="<?=$keygroup;
?>" <?= $pconfig['pfs_group'] == $keygroup ? "selected=\"selected\"" : "" ; ?>>
                            <?=$keygroupname;?>
                          </option>
<?php
endforeach;
?>
                        </select>
                        <div class="hidden" for="help_for_pfs_group_enable">
                            <?=gettext("Provide the Phase2 PFS group to clients ( overrides all mobile phase2 settings )"); ?>
                        </div>
										</td>
									</tr>
									<tr>
										<td><a id="help_for_login_banner_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Login Banner"); ?></td>
										<td>
                        <input name="login_banner_enable" type="checkbox" id="login_banner_enable" value="yes" <?= !empty($pconfig['login_banner']) ? "checked=\"checked\"" : "";?> onclick="login_banner_change()" />
                        <textarea name="login_banner" cols="65" rows="7" id="login_banner" class="formpre"><?=$pconfig['login_banner'];?></textarea>
                        <div class="hidden" for="help_for_login_banner_enable">
                            <?=gettext("Provide a login banner to clients"); ?><br />
                        </div>
										</td>
									</tr>
									<tr>
										<td>&nbsp;</td>
										<td>
											<input name="submit" type="submit" class="btn btn-primary" value="<?=gettext("Save"); ?>" />
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
