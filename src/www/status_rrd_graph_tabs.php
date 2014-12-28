<?php
    $tab_array = array();
	if($curcat == "system") { $tabactive = True; } else { $tabactive = False; }
        $tab_array[] = array(gettext("System"), $tabactive, "status_rrd_graph.php?cat=system");
	if($curcat == "traffic") { $tabactive = True; } else { $tabactive = False; }
        $tab_array[] = array(gettext("Traffic"), $tabactive, "status_rrd_graph.php?cat=traffic");
	if($curcat == "packets") { $tabactive = True; } else { $tabactive = False; }
        $tab_array[] = array(gettext("Packets"), $tabactive, "status_rrd_graph.php?cat=packets");
	if($curcat == "quality") { $tabactive = True; } else { $tabactive = False; }
        $tab_array[] = array(gettext("Quality"), $tabactive, "status_rrd_graph.php?cat=quality");
	if($queues) {
		if($curcat == "queues") { $tabactive = True; } else { $tabactive = False; }
			$tab_array[] = array(gettext("Queues"), $tabactive, "status_rrd_graph.php?cat=queues");
		if($curcat == "queuedrops") { $tabactive = True; } else { $tabactive = False; }
			$tab_array[] = array(gettext("QueueDrops"), $tabactive, "status_rrd_graph.php?cat=queuedrops");
	}
	if($wireless) {
		if($curcat == "wireless") { $tabactive = True; } else { $tabactive = False; }
	        $tab_array[] = array(gettext("Wireless"), $tabactive, "status_rrd_graph.php?cat=wireless");
	}
	if($cellular) {
		if($curcat == "cellular") { $tabactive = True; } else { $tabactive = False; }
	        $tab_array[] = array(gettext("Cellular"), $tabactive, "status_rrd_graph.php?cat=cellular");
	}
	if($vpnusers) {
		if($curcat == "vpnusers") { $tabactive = True; } else { $tabactive = False; }
	        $tab_array[] = array("VPN", $tabactive, "status_rrd_graph.php?cat=vpnusers");
	}
	if($captiveportal) {
		if($curcat == "captiveportal") { $tabactive = True; } else { $tabactive = False; }
	        $tab_array[] = array("Captive Portal", $tabactive, "status_rrd_graph.php?cat=captiveportal");
	}
	if($ntpd) {
		if($curcat == "ntpd") { $tabactive = True; } else { $tabactive = False; }
	        $tab_array[] = array("NTP", $tabactive, "status_rrd_graph.php?cat=ntpd");
	}
	if($curcat == "custom") { $tabactive = True; } else { $tabactive = False; }
        $tab_array[] = array(gettext("Custom"), $tabactive, "status_rrd_graph.php?cat=custom");
	if($curcat == "settings") { $tabactive = True; } else { $tabactive = False; }
        $tab_array[] = array(gettext("Settings"), $tabactive, "status_rrd_graph_settings.php");
        display_top_tabs($tab_array);
?>
