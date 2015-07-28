<?php

/*
	Copyright (C) 2014-2015 Deciso B.V.
	Copyright (C) Jim McBeath
	Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>.
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

$pgtitle = array(gettext("Interfaces"),gettext("Assign network ports"));
$shortcut_section = "interfaces";

require_once("guiconfig.inc");
require_once("filter.inc");
require_once("vpn.inc");
require_once("captiveportal.inc");
require_once("rrd.inc");
require_once("system.inc");
require_once("interfaces.inc");
require_once("openvpn.inc");
require_once("pfsense-utils.inc");
require_once("services.inc");
require_once("unbound.inc");

function interface_assign_description($portinfo, $portname) {
	if ($portinfo['isvlan']) {
		$descr = sprintf(gettext('VLAN %1$s on %2$s'),$portinfo['tag'],$portinfo['if']);
		if ($portinfo['descr'])
			$descr .= " (" . $portinfo['descr'] . ")";
	} elseif ($portinfo['iswlclone']) {
		$descr = $portinfo['cloneif'];
		if ($portinfo['descr'])
			$descr .= " (" . $portinfo['descr'] . ")";
	} elseif ($portinfo['isppp']) {
		$descr = $portinfo['descr'];
	} elseif ($portinfo['isbridge']) {
		$descr = strtoupper($portinfo['bridgeif']);
		if ($portinfo['descr'])
			$descr .= " (" . $portinfo['descr'] . ")";
	} elseif ($portinfo['isgre']) {
		$descr = "GRE {$portinfo['remote-addr']}";
		if ($portinfo['descr'])
			$descr .= " (" . $portinfo['descr'] . ")";
	} elseif ($portinfo['isgif']) {
		$descr = "GIF {$portinfo['remote-addr']}";
		if ($portinfo['descr'])
			$descr .= " (" . $portinfo['descr'] . ")";
	} elseif ($portinfo['islagg']) {
		$descr = strtoupper($portinfo['laggif']);
		if ($portinfo['descr'])
			$descr .= " (" . $portinfo['descr'] . ")";
	} elseif ($portinfo['isqinq']) {
		$descr =  $portinfo['descr'];
	} elseif (substr($portname, 0, 4) == 'ovpn') {
		$descr = $portname . " (" . $ovpn_descrs[substr($portname, 5)] . ")";
	} else
		$descr = $portname . " (" . $portinfo['mac'] . ")";

	return htmlspecialchars($descr);
}

/*
	In this file, "port" refers to the physical port name,
	while "interface" refers to LAN, WAN, or OPTn.
*/

/* get list without VLAN interfaces */
$portlist = get_interface_list();

/* add wireless clone interfaces */
if (isset($config['wireless']['clone'])) {
	foreach ($config['wireless']['clone'] as $clone) {
		$portlist[$clone['cloneif']] = $clone;
		$portlist[$clone['cloneif']]['iswlclone'] = true;
	}
}

/* add VLAN interfaces */
if (isset($config['vlans']['vlan'])) {
	foreach ($config['vlans']['vlan'] as $vlan) {
		$portlist[$vlan['vlanif']] = $vlan;
		$portlist[$vlan['vlanif']]['isvlan'] = true;
	}
}

/* add Bridge interfaces */
if (isset($config['bridges']['bridged'])) {
	foreach ($config['bridges']['bridged'] as $bridge) {
		$portlist[$bridge['bridgeif']] = $bridge;
		$portlist[$bridge['bridgeif']]['isbridge'] = true;
	}
}

/* add GIF interfaces */
if (isset($config['gifs']['gif'])) {
	foreach ($config['gifs']['gif'] as $gif) {
		$portlist[$gif['gifif']] = $gif;
		$portlist[$gif['gifif']]['isgif'] = true;
	}
}

/* add GRE interfaces */
if (isset($config['gres']['gre'])) {
	foreach ($config['gres']['gre'] as $gre) {
		$portlist[$gre['greif']] = $gre;
		$portlist[$gre['greif']]['isgre'] = true;
	}
}

/* add LAGG interfaces */
if (isset($config['laggs']['lagg'])) {
	foreach ($config['laggs']['lagg'] as $lagg) {
		$portlist[$lagg['laggif']] = $lagg;
		$portlist[$lagg['laggif']]['islagg'] = true;
		/* LAGG members cannot be assigned */
		$lagifs = explode(',', $lagg['members']);
		foreach ($lagifs as $lagif)
			if (isset($portlist[$lagif]))
				unset($portlist[$lagif]);
	}
}

/* add QinQ interfaces */
if (isset($config['qinqs']['qinqentry'])) {
	foreach ($config['qinqs']['qinqentry'] as $qinq) {
		$portlist["vlan{$qinq['tag']}"]['descr'] = "VLAN {$qinq['tag']}";
		$portlist["vlan{$qinq['tag']}"]['isqinq'] = true;
		/* QinQ members */
		$qinqifs = explode(' ', $qinq['members']);
		foreach ($qinqifs as $qinqif) {
			$portlist["vlan{$qinq['tag']}_{$qinqif}"]['descr'] = "QinQ {$qinqif}";
			$portlist["vlan{$qinq['tag']}_{$qinqif}"]['isqinq'] = true;
		}
	}
}

/* add PPP interfaces */
if (isset($config['ppps']['ppp'])) {
	foreach ($config['ppps']['ppp'] as $pppid => $ppp) {
		$portname = $ppp['if'];
		$portlist[$portname] = $ppp;
		$portlist[$portname]['isppp'] = true;
		$ports_base = basename($ppp['ports']);
		if (isset($ppp['descr']))
			$portlist[$portname]['descr'] = strtoupper($ppp['if']). "({$ports_base}) - {$ppp['descr']}";
		else if (isset($ppp['username']))
			$portlist[$portname]['descr'] = strtoupper($ppp['if']). "({$ports_base}) - {$ppp['username']}";
		else
			$portlist[$portname]['descr'] = strtoupper($ppp['if']). "({$ports_base})";
	}
}

/* add tun0 interface (required for sixxs-aiccu) */
//   This is a temporary solution to allow using sixxs-aiccu without manual code change
//   until the aiccu service is correctly included in the web interface.
//   'mac' is used to add the description in the available network ports list
//   (to avoid additional temporary code in the interface_assign_description function).
$tunfound = "";
exec("/sbin/ifconfig | /usr/bin/grep -c '^tun0'", $tunfound);
if (intval($tunfound[0]) > 0) {
	$portlist['tun0'] = array();
	$portlist['tun0']['if'] = 'tun0';
	$portlist['tun0']['mac'] = 'sixxs-aiccu';
}

$ovpn_descrs = array();
if (isset($config['openvpn'])) {
	if (isset($config['openvpn']['openvpn-server'])) {
		foreach ($config['openvpn']['openvpn-server'] as $s) {
			$ovpn_descrs[$s['vpnid']] = $s['description'];
		}
	}
	if (isset($config['openvpn']['openvpn-client'])) {
		foreach ($config['openvpn']['openvpn-client'] as $c) {
			$ovpn_descrs[$c['vpnid']] = $c['description'];
		}
	}
}

if (isset($_POST['add_x']) && isset($_POST['if_add'])) {
	/* Be sure this port is not being used */
	$portused = false;
	foreach ($config['interfaces'] as $ifname => $ifdata) {
		if ($ifdata['if'] == $_PORT['if_add']) {
			$portused = true;
			break;
		}
	}

	if ($portused === false) {
		/* find next free optional interface number */
		if(!$config['interfaces']['lan']) {
			$newifname = gettext("lan");
			$descr = gettext("LAN");
		} else {
			for ($i = 1; $i <= count($config['interfaces']); $i++) {
				if (!$config['interfaces']["opt{$i}"])
					break;
			}
			$newifname = 'opt' . $i;
			$descr = "OPT" . $i;
		}

		$config['interfaces'][$newifname] = array();
		$config['interfaces'][$newifname]['descr'] = $descr;
		$config['interfaces'][$newifname]['if'] = $_POST['if_add'];
		if (match_wireless_interface($_POST['if_add'])) {
			$config['interfaces'][$newifname]['wireless'] = array();
			interface_sync_wireless_clones($config['interfaces'][$newifname], false);
		}

		uksort($config['interfaces'], "compare_interface_friendly_names");

		write_config();

		$savemsg = gettext("Interface has been added.");
	}

} else if (isset($_POST['apply'])) {
	write_config();

	$retval = filter_configure();
	$savemsg = get_std_save_message($retval);

	if (stristr($retval, "error") != true) {
		$savemsg = get_std_save_message($retval);
	} else {
		$savemsg = $retval;
	}

} else if (isset($_POST['Submit'])) {

	unset($input_errors);

	/* input validation */

	/* Build a list of the port names so we can see how the interfaces map */
	$portifmap = array();
	foreach ($portlist as $portname => $portinfo)
		$portifmap[$portname] = array();

	/* Go through the list of ports selected by the user,
	build a list of port-to-interface mappings in portifmap */
	foreach ($_POST as $ifname => $ifport) {
		if (($ifname == 'lan') || ($ifname == 'wan') || (substr($ifname, 0, 3) == 'opt'))
			$portifmap[$ifport][] = strtoupper($ifname);
	}

	/* Deliver error message for any port with more than one assignment */
	foreach ($portifmap as $portname => $ifnames) {
		if (count($ifnames) > 1) {
			$errstr = sprintf(gettext('Port %1$s '.
				' was assigned to %2$s' .
				' interfaces:'), $portname, count($ifnames));

			foreach ($portifmap[$portname] as $ifn)
				$errstr .= " " . $ifn;

			$input_errors[] = $errstr;
		} else if (count($ifnames) == 1 && preg_match('/^bridge[0-9]/', $portname) && isset($config['bridges']['bridged'])) {
			foreach ($config['bridges']['bridged'] as $bridge) {
				if ($bridge['bridgeif'] != $portname) {
					continue;
				}

				$members = explode(",", strtoupper($bridge['members']));
				foreach ($members as $member) {
					if ($member == $ifnames[0]) {
						$input_errors[] = sprintf(gettext("You cannot set port %s to interface %s because this interface is a member of %s."), $portname, $member, $portname);
						break;
					}
				}
			}
		}
	}

	if (isset($config['vlans']['vlan'])) {
		foreach ($config['vlans']['vlan'] as $vlan) {
			if (does_interface_exist($vlan['if']) == false)
				$input_errors[] = "Vlan parent interface {$vlan['if']} does not exist anymore so vlan id {$vlan['tag']} cannot be created please fix the issue before continuing.";
		}
	}

	if (!$input_errors) {
		/* No errors detected, so update the config */
		foreach ($_POST as $ifname => $ifport) {

			if (($ifname == 'lan') || ($ifname == 'wan') || (substr($ifname, 0, 3) == 'opt')) {

				if (!is_array($ifport)) {
					$reloadif = false;
					if (!empty($config['interfaces'][$ifname]['if']) && $config['interfaces'][$ifname]['if'] <> $ifport) {
						interface_bring_down($ifname);
						/* Mark this to be reconfigured in any case. */
						$reloadif = true;
					}
					$config['interfaces'][$ifname]['if'] = $ifport;
					if (isset($portlist[$ifport]['isppp']))
						$config['interfaces'][$ifname]['ipaddr'] = $portlist[$ifport]['type'];

					if (substr($ifport, 0, 3) == 'gre' || substr($ifport, 0, 3) == 'gif') {
						unset($config['interfaces'][$ifname]['ipaddr']);
						unset($config['interfaces'][$ifname]['subnet']);
						unset($config['interfaces'][$ifname]['ipaddrv6']);
						unset($config['interfaces'][$ifname]['subnetv6']);
					}

					/* check for wireless interfaces, set or clear ['wireless'] */
					if (match_wireless_interface($ifport)) {
						if (!is_array($config['interfaces'][$ifname]['wireless']))
							$config['interfaces'][$ifname]['wireless'] = array();
					} else {
						unset($config['interfaces'][$ifname]['wireless']);
					}

					/* make sure there is a descr for all interfaces */
					if (!isset($config['interfaces'][$ifname]['descr']))
						$config['interfaces'][$ifname]['descr'] = strtoupper($ifname);

					if ($reloadif == true) {
						if (match_wireless_interface($ifport)) {
							interface_sync_wireless_clones($config['interfaces'][$ifname], false);
						}
						/* Reload all for the interface. */
						interface_configure($ifname, true);
					}
				}
			}
		}

		write_config();

		enable_rrd_graphing();
	}
} else {
	/* yuck - IE won't send value attributes for image buttons, while Mozilla does - so we use .x/.y to find move button clicks instead... */
	unset($delbtn);
	foreach ($_POST as $pn => $pd) {
		if (preg_match("/del_(.+)_x/", $pn, $matches))
			$delbtn = $matches[1];
	}


	if (isset($delbtn)) {
		$id = $delbtn;

		if (link_interface_to_group($id))
			$input_errors[] = gettext("The interface is part of a group. Please remove it from the group to continue");
		else if (link_interface_to_bridge($id))
			$input_errors[] = gettext("The interface is part of a bridge. Please remove it from the bridge to continue");
		else if (link_interface_to_gre($id))
			$input_errors[] = gettext("The interface is part of a gre tunnel. Please delete the tunnel to continue");
		else if (link_interface_to_gif($id))
			$input_errors[] = gettext("The interface is part of a gif tunnel. Please delete the tunnel to continue");
		else {
			unset($config['interfaces'][$id]['enable']);
			$realid = get_real_interface($id);
			interface_bring_down($id);   /* down the interface */

			unset($config['interfaces'][$id]);	/* delete the specified OPTn or LAN*/

			if (is_array($config['dhcpd']) && is_array($config['dhcpd'][$id])) {
				unset($config['dhcpd'][$id]);
				services_dhcpd_configure();
			}

			if (count($config['filter']['rule']) > 0) {
				foreach ($config['filter']['rule'] as $x => $rule) {
					if($rule['interface'] == $id)
						unset($config['filter']['rule'][$x]);
				}
			}
			if (is_array($config['nat']['rule']) && count($config['nat']['rule']) > 0) {
				foreach ($config['nat']['rule'] as $x => $rule) {
					if($rule['interface'] == $id)
						unset($config['nat']['rule'][$x]['interface']);
				}
			}

			write_config();

			/* If we are in firewall/routing mode (not single interface)
			 * then ensure that we are not running DHCP on the wan which
			 * will make a lot of ISP's unhappy.
			 */
			if($config['interfaces']['lan'] && $config['dhcpd']['wan']) {
				unset($config['dhcpd']['wan']);
			}

			link_interface_to_vlans($realid, "update");

			$savemsg = gettext("Interface has been deleted.");
		}
	}
}

/* Create a list of unused ports */
$unused_portlist = array();
foreach ($portlist as $portname => $portinfo) {
	$portused = false;
	foreach ($config['interfaces'] as $ifname => $ifdata) {
		if ($ifdata['if'] == $portname) {
			$portused = true;
			break;
		}
	}
	if ($portused === false)
		$unused_portlist[$portname] = $portinfo;
}

include("head.inc");

?>

<body>
<?php include("fbegin.inc"); ?>

	<section class="page-content-main">
	<div class="container-fluid">
		<div class="row">

			<?php
			if (isset($savemsg)) {
				print_info_box($savemsg);
			}

			if (isset($input_errors) && count($input_errors) > 0) {
				print_input_errors($input_errors);
			}
			?>

		    <section class="col-xs-12">


					<?php
						$tab_array = array();
						$tab_array[0] = array(gettext("Interface assignments"), true, "interfaces_assign.php");
						$tab_array[1] = array(gettext("Interface Groups"), false, "interfaces_groups.php");
						$tab_array[2] = array(gettext("Wireless"), false, "interfaces_wireless.php");
						$tab_array[3] = array(gettext("VLANs"), false, "interfaces_vlan.php");
						$tab_array[4] = array(gettext("QinQs"), false, "interfaces_qinq.php");
						$tab_array[5] = array(gettext("PPPs"), false, "interfaces_ppps.php");
						$tab_array[7] = array(gettext("GRE"), false, "interfaces_gre.php");
						$tab_array[8] = array(gettext("GIF"), false, "interfaces_gif.php");
						$tab_array[9] = array(gettext("Bridges"), false, "interfaces_bridge.php");
						$tab_array[10] = array(gettext("LAGG"), false, "interfaces_lagg.php");
						display_top_tabs($tab_array);
					?>


					<div class="tab-content content-box col-xs-12">

	                        <form action="interfaces_assign.php" method="post" name="iform" id="iform">

	                        <div class="table-responsive">
		                        <table class="table table-striped table-sort">

                                        <thead>
                                            <tr>
								<th colspan="2" class="listtopic"><?=gettext("Interface"); ?></th>
								<th colspan="2" class="listtopic"><?=gettext("Network port"); ?></th>
                                            </tr>
                                        </thead>

									<tbody>
									<?php
												foreach ($config['interfaces'] as $ifname => $iface):
													if ($iface['descr'])
														$ifdescr = $iface['descr'];
													else
														$ifdescr = strtoupper($ifname);
									?>
													<tr>
														<td class="listlr" valign="middle"><strong><u><span onclick="location.href='/interfaces.php?if=<?=$ifname;?>'" style="cursor: pointer;"><?=$ifdescr;?></span></u></strong></td>
														<td valign="middle" class="listr">
															<select onchange="javascript:jQuery('#savediv').show();" name="<?=$ifname;?>" id="<?=$ifname;?>">
									<?php
															foreach ($portlist as $portname => $portinfo):
									?>
																<option  value="<?=$portname;?>"  <?php if ($portname == $iface['if']) echo " selected=\"selected\"";?>>
																	<?=interface_assign_description($portinfo, $portname);?>
																</option>
									<?php
															endforeach;
									?>
															</select>
														</td>
														<td valign="middle" class="list">
									<?php
														if ($ifname != 'wan'):
									?>
															<button name="del_<?=$ifname;?>_x"
																title="<?=gettext("delete interface");?>"
																class="btn btn-default"
																type="submit"
																value="<?=$ifname;?>"
																onclick="return confirm('<?=gettext("Do you really want to delete this interface?"); ?>')">
																<span class=" glyphicon glyphicon-remove"></span>
															</button>
									<?php
														endif;
									?>
														</td>
													</tr>
									<?php
												endforeach;
												if (count($config['interfaces']) < count($portlist)):
									?>
													<tr>
														<td class="list">
															<strong><?=gettext("Available network ports:");?></strong>
														</td>
														<td class="list">
															<select name="if_add" id="if_add">
									<?php
															foreach ($unused_portlist as $portname => $portinfo):
									?>
																<option  value="<?=$portname;?>"  <?php if ($portname == $iface['if']) echo " selected=\"selected\"";?>>
																	<?=interface_assign_description($portinfo, $portname);?>
																</option>
									<?php
															endforeach;
									?>
															</select>
														</td>
														<td class="list">
															<button name="add_x" type="submit" value="<?=$portname;?>" class="btn btn-primary" title="<?=gettext("add selected interface");?>"><span class="glyphicon glyphicon-plus"></span></button>
														</td>
													</tr>
									<?php
												endif;
									?>
									</tbody>
								</table>
	                        </div>

	                        <div class="container-fluid">
		                        <div id='savediv' style='display:none'>
									<input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save"); ?>" /><br /><br />
								</div>
								<ul>
									<li><span class="vexpl"><?=gettext("Interfaces that are configured as members of a lagg(4) interface will not be shown."); ?></span></li>
								</ul>
	                        </div>

	                        </form>

					</div>
		    </section>
		</div>
		</div>
	</section>

<?php include("foot.inc"); ?>
