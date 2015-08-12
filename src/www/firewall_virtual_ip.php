<?php

/*
	Copyright (C) 2014-2015 Deciso B.V.
	Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>.
	Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>.
	Copyright (C) 2004-2005 Scott Ullrich <geekgod@pfsense.com>.
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

if (!isset($config['virtualip']['vip'])) {
	$config['virtualip']['vip'] = array();
}

$a_vip = &$config['virtualip']['vip'];

if ($_POST) {
	$pconfig = $_POST;

	if ($_POST['apply']) {
		if (file_exists('/tmp/.firewall_virtual_ip.apply')) {
                        $toapplylist = unserialize(file_get_contents('/tmp/.firewall_virtual_ip.apply'));
			foreach ($toapplylist as $vid => $ovip) {
				if (!empty($ovip))
					interface_vip_bring_down($ovip);
				if ($a_vip[$vid]) {
					switch ($a_vip[$vid]['mode']) {
					case "ipalias":
						interface_ipalias_configure($a_vip[$vid]);
						break;
					case "proxyarp":
						interface_proxyarp_configure($a_vip[$vid]['interface']);
						break;
					case "carp":
						interface_carp_configure($a_vip[$vid]);
						break;
					default:
						break;
					}
				}
			}
			@unlink('/tmp/.firewall_virtual_ip.apply');
		}
		$retval = 0;
		$retval |= filter_configure();
		$savemsg = get_std_save_message($retval);

		clear_subsystem_dirty('vip');
	}
}

if ($_GET['act'] == "del") {
	if ($a_vip[$_GET['id']]) {
		/* make sure no inbound NAT mappings reference this entry */
		if (is_array($config['nat']['rule'])) {
			foreach ($config['nat']['rule'] as $rule) {
				if($rule['destination']['address'] <> "") {
					if ($rule['destination']['address'] == $a_vip[$_GET['id']]['subnet']) {
						$input_errors[] = gettext("This entry cannot be deleted because it is still referenced by at least one NAT mapping.");
						break;
					}
				}
			}
		}

		if (is_ipaddrv6($a_vip[$_GET['id']]['subnet'])) {
			$is_ipv6 = true;
			$subnet = gen_subnetv6($a_vip[$_GET['id']]['subnet'], $a_vip[$_GET['id']]['subnet_bits']);
			$if_subnet_bits = get_interface_subnetv6($a_vip[$_GET['id']]['interface']);
			$if_subnet = gen_subnetv6(get_interface_ipv6($a_vip[$_GET['id']]['interface']), $if_subnet_bits);
		} else {
			$is_ipv6 = false;
			$subnet = gen_subnet($a_vip[$_GET['id']]['subnet'], $a_vip[$_GET['id']]['subnet_bits']);
			$if_subnet_bits = get_interface_subnet($a_vip[$_GET['id']]['interface']);
			$if_subnet = gen_subnet(get_interface_ip($a_vip[$_GET['id']]['interface']), $if_subnet_bits);
		}

		$subnet .= "/" . $a_vip[$_GET['id']]['subnet_bits'];
		$if_subnet .= "/" . $if_subnet_bits;

		if (isset($config['gateways']['gateway_item'])) {
			foreach($config['gateways']['gateway_item'] as $gateway) {
				if ($a_vip[$_GET['id']]['interface'] != $gateway['interface'])
					continue;
				if ($is_ipv6 && $gateway['ipprotocol'] == 'inet')
					continue;
				if (!$is_ipv6 && $gateway['ipprotocol'] == 'inet6')
					continue;
				if (ip_in_subnet($gateway['gateway'], $if_subnet))
					continue;

				if (ip_in_subnet($gateway['gateway'], $subnet)) {
					$input_errors[] = gettext("This entry cannot be deleted because it is still referenced by at least one Gateway.");
					break;
				}
			}
		}

		if ($a_vip[$_GET['id']]['mode'] == "ipalias") {
			$subnet = gen_subnet($a_vip[$_GET['id']]['subnet'], $a_vip[$_GET['id']]['subnet_bits']) . "/" . $a_vip[$_GET['id']]['subnet_bits'];
			$found_if = false;
			$found_carp = false;
			$found_other_alias = false;

			if ($subnet == $if_subnet)
				$found_if = true;

			$vipiface = $a_vip[$_GET['id']]['interface'];
			foreach ($a_vip as $vip_id => $vip) {
				if ($vip_id == $_GET['id'])
					continue;

				if ($vip['interface'] == $vipiface && ip_in_subnet($vip['subnet'], $subnet))
					if ($vip['mode'] == "carp")
						$found_carp = true;
					else if ($vip['mode'] == "ipalias")
						$found_other_alias = true;
			}

			if ($found_carp === true && $found_other_alias === false && $found_if === false)
				$input_errors[] = gettext("This entry cannot be deleted because it is still referenced by a CARP IP with the description") . " {$vip['descr']}.";
		}

		if (!$input_errors) {
			if (session_status() == PHP_SESSION_NONE) {
				session_start();
                        }
			$user = getUserEntry($_SESSION['Username']);
			if (is_array($user) && userHasPrivilege($user, "user-config-readonly")) {
				header("Location: firewall_virtual_ip.php");
				exit;
			}
			session_write_close();

			// Special case since every proxyarp vip is handled by the same daemon.
			if ($a_vip[$_GET['id']]['mode'] == "proxyarp") {
				$viface = $a_vip[$_GET['id']]['interface'];
				unset($a_vip[$_GET['id']]);
				interface_proxyarp_configure($viface);
			} else {
				interface_vip_bring_down($a_vip[$_GET['id']]);
				unset($a_vip[$_GET['id']]);
			}
			if (count($config['virtualip']['vip']) == 0)
				unset($config['virtualip']['vip']);
			write_config();
			header("Location: firewall_virtual_ip.php");
			exit;
		}
	}
} else if ($_GET['changes'] == "mods" && is_numericint($_GET['id']))
	$id = $_GET['id'];

$pgtitle = array(gettext("Firewall"),gettext("Virtual IP Addresses"));
include("head.inc");

$main_buttons = array(
	array('href'=>'firewall_virtual_ip_edit.php', 'label'=>'Add'),
);

?>
<body>
<?php include("fbegin.inc"); ?>

	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">

				<?php
					if (isset($input_errors) && count($input_errors) > 0)
						print_input_errors($input_errors);
					else
					if (isset($savemsg))
						print_info_box($savemsg);
					else
					if (is_subsystem_dirty('vip'))
						print_info_box_np(gettext("The VIP configuration has been changed.")."<br />".gettext("You must apply the changes in order for them to take effect."));
				?>

			    <section class="col-xs-12">


					 <?php
						        /* active tabs */
						        $tab_array = array();
						        $tab_array[] = array(gettext("Virtual IPs"), true, "firewall_virtual_ip.php");
						        $tab_array[] = array(gettext("CARP Settings"), false, "system_hasync.php");
						        display_top_tabs($tab_array);
						  ?>


						<div class="tab-content content-box col-xs-12">


		                        <form action="firewall_virtual_ip.php" method="post" name="iform" id="iform">
						<input type="hidden" id="id" name="id" value="<?php echo htmlspecialchars($id); ?>" />

		                        <div class="table-responsive">
			                        <table class="table table-striped table-sort">
			                        <thead>
						                <tr>
						                  <td width="30%" class="listhdrr"><?=gettext("Virtual IP address");?></td>
						                  <td width="10%" class="listhdrr"><?=gettext("Interface");?></td>
						                  <td width="10%" class="listhdrr"><?=gettext("Type");?></td>
						                  <td width="40%" class="listhdr"><?=gettext("Description");?></td>
						                  <td width="10%" class="list"></td>
										</tr>
			                        </thead>
			                        <tbody>
								<?php
									$interfaces = get_configured_interface_with_descr(false, true);
									$interfaces['lo0'] = "Localhost";
								?>
									  <?php $i = 0; foreach ($a_vip as $vipent): ?>
									  <?php if($vipent['subnet'] <> "" or $vipent['range'] <> "" or
									        $vipent['subnet_bits'] <> "" or (isset($vipent['range']['from']) && $vipent['range']['from'] <> "")): ?>
						                <tr>
						                  <td class="listlr" ondblclick="document.location='firewall_virtual_ip_edit.php?id=<?=$i;?>';">
											<?php	if (($vipent['type'] == "single") || ($vipent['type'] == "network"))
														if($vipent['subnet_bits'])
															echo "{$vipent['subnet']}/{$vipent['subnet_bits']}";
													if ($vipent['type'] == "range")
														echo "{$vipent['range']['from']}-{$vipent['range']['to']}";
											?>
											<?php if($vipent['mode'] == "carp") echo " (vhid {$vipent['vhid']})"; ?>
						                  </td>
						                  <td class="listr" ondblclick="document.location='firewall_virtual_ip_edit.php?id=<?=$i;?>';">
						                    <?=htmlspecialchars($interfaces[$vipent['interface']]);?>&nbsp;
						                  </td>
						                  <td class="listr" align="center" ondblclick="document.location='firewall_virtual_ip_edit.php?id=<?=$i;?>';">
						                    <?php if($vipent['mode'] == "proxyarp") echo "Proxy ARP"; elseif($vipent['mode'] == "carp") echo "CARP"; elseif($vipent['mode'] == "other") echo "Other"; elseif($vipent['mode'] == "ipalias") echo "IP Alias";?>
						                  </td>
						                  <td class="listbg" ondblclick="document.location='firewall_virtual_ip_edit.php?id=<?=$i;?>';">
						                    <?=htmlspecialchars($vipent['descr']);?>&nbsp;
						                  </td>
						                  <td class="list nowrap">
						                    <table border="0" cellspacing="0" cellpadding="1" summary="icons">
						                      <tr>
						                        <td valign="middle">
							                         <a href="firewall_virtual_ip_edit.php?id=<?=$i;?>" class="btn btn-default"><span class="glyphicon glyphicon-edit" title="<?=gettext("Edit");?>"></span></a>

													<a href="firewall_virtual_ip.php?act=del&amp;tab=<?=$tab;?>&amp;id=<?=$i;?>" class="btn btn-default"  onclick="return confirm('<?=gettext("Do you really want to delete this entry?");?>')"><span class="glyphicon glyphicon-remove"></span></a>
												</td>
						                      </tr>
						                    </table>
						                  </td>
						                </tr>
										<?php endif; ?>
										<?php $i++; endforeach; ?>
			                        </tbody>
									</table>
		                        </div>
		                        <div class="container-fluid">
		                        <p><span class="vexpl"><span class="text-danger"><strong><?=gettext("Note:");?><br />
                      </strong></span><?=gettext("The virtual IP addresses defined on this page may be used in");?><a href="firewall_nat.php"> <?=gettext("NAT"); ?> </a><?=gettext("mappings.");?><br />
                      <?=gettext("You can check the status of your CARP Virtual IPs and interfaces ");?><a href="carp_status.php"><?=gettext("here");?></a>.</span></p>
		                        </div>
		                        </form>
						</div>
			    </section>
			</div>
		</div>
	</section>

<?php include("foot.inc"); ?>
