<?php

$active_tab = isset($active_tab) ? $active_tab : $_SERVER['PHP_SELF'];

$tab_array = array();
$tab_array[0] = array(gettext("Gateways"), $active_tab == "/system_gateways.php", "system_gateways.php");
$tab_array[1] = array(gettext("Routes"), $active_tab == "/system_routes.php", "system_routes.php");
$tab_array[2] = array(gettext("Groups"), $active_tab == "/system_gateway_groups.php", "system_gateway_groups.php");

display_top_tabs($tab_array);
