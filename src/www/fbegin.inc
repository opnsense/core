<?php

/*
	Copyright (C) 2014 Deciso B.V.
	Copyright (C) 2008 Scott Ullrich <sullrich@gmail.com>
	Copyright (C) 2005-2006 Colin Smith <ethethlay@gmail.com>
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

require_once("shortcuts.inc");

function find_service_by_name($name) {
	$services = get_services();
	foreach ($services as $service)
		if ($service["name"] == $name)
			return $service;
	return array();
}

function find_service_by_cp_zone($zone) {
	$services = get_services();
	foreach ($services as $service)
		if (($service["name"] == "captiveportal") && isset($service["zone"]) && ($service["zone"] == $zone))
			return $service;
	return array();
}


/* Determine automated help URL. Should output the page name and
   parameters separately */
$uri_split = "";
preg_match("/\/(.*)\?(.*)/", $_SERVER["REQUEST_URI"], $uri_split);

/* If there was no match, there were no parameters, just grab the filename
   Otherwise, use the matched filename from above. */
if (empty($uri_split[0])) {
	$pagename = ltrim($_SERVER["REQUEST_URI"], '/');
} else {
	$pagename = $uri_split[1];
}
/* If the page name is still empty, the user must have requested / (index.php) */
if (empty($pagename)) {
	$pagename = "index.php";
}

/* If the filename is wizard.php, reparse looking for the .xml filename */
if ($pagename == 'wizard.php') {
	$param_split = explode('&', $uri_split[2]);
	foreach ($param_split as $param) {
		if (substr($param, 0, 4) == "xml=") {
			$xmlfile = explode('=', $param);
			$pagename = $xmlfile[1];
		}
	}
}

// link menu system
$menu = new OPNsense\Base\Menu\MenuSystem();

// add interfaces to "Interfaces" menu tab... kind of a hack, may need some improvement.
$cnf = OPNsense\Core\Config::getInstance();
$ifarr = array();
foreach ($cnf->object()->interfaces->children() as $key => $node) {
    $ifarr[$key] = $node->descr ? $node->descr->__toString() : strtoupper($key);
}
natcasesort($ifarr);
$ordid = 0;
foreach ($ifarr as $key => $descr) {
    $menu->appendItem('Interfaces', $key, array(
        'url' => '/interfaces.php?if=' . $key,
        'visiblename' => '[' . $descr . ']',
        'cssclass' => 'fa fa-sitemap',
        'order' => $ordid++,
    ));
}
unset($ifarr);
$menuSystem = $menu->getItems($_SERVER['REQUEST_URI']);
$menuBreadcrumbs = $menu->getBreadcrumbs();

$aclObj = new \OPNsense\Core\ACL();

/* display a top alert bar if need be */
$need_alert_display = false;
$found_notices = are_notices_pending();
if($found_notices == true) {
	$notices = get_notices();
	if(!$notices) {
		$need_alert_display = true;
		$display_text = print_notices($notices) . "<br />";
	}
}
if($need_alert_display == true) {
    echo "<div class=\"col-xs-12\"><div class=\"alert alert-info\"><p>".$display_text."</p></div></div>";
}

?>

<header class="page-head">

	<nav class="navbar navbar-default" role="navigation">

		<div class="container-fluid">
			<div class="navbar-header">
				<a class="navbar-brand" href="/index.php">
				<img class="brand-logo" src="/ui/themes/<?=$g['theme'];?>/build/images/default-logo.png" height="30"/>
				<img class="brand-icon" src="/ui/themes/<?=$g['theme'];?>/build/images/icon-logo.png" height="30"/>
				</a>
				<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navigation">
					<span class="sr-only">Toggle navigation</span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
				</button>
			</div>

			<div class="collapse navbar-collapse">
				<ul class="nav navbar-nav navbar-right">
					<li id="menu_messages">
					<?php
						echo get_menu_messages();
					?>
					</li>
					<?php
						if (isset($shortcut_section)) {
							echo '<li>'.get_shortcut_main_link($shortcut_section, false).'</li>';
							echo '<li>'.get_shortcut_status_link($shortcut_section, false).'</li>';
							echo '<li>'.get_shortcut_log_link($shortcut_section, false).'</li>';
						}
					?>
					<li><a href="<?= "/help.php?page={$pagename}" ?>" target="_blank" title="<?= gettext('Help for items on this page') ?>"><?= gettext('Help') ?></a></li>
					<li class="active"><a href="/index.php?logout"><?= gettext('Logout') ?></a></li>
				</ul>

			</div>
		</div>
	</nav>

</header>

<main class="page-content col-sm-10 col-sm-push-2">

    <aside id="navigation" class="page-side col-xs-12 col-sm-2 hidden-xs">
        <div class="row">
            <nav class="page-side-nav" role="navigation">
                <div id="mainmenu" class="panel" style="border:0px" >
                    <div class="panel list-group" style="border:0px">
<?php
                          foreach($menuSystem as $topMenuItem): ?>
                            <a href="#<?=$topMenuItem->Id;?>" class="list-group-item <?= $topMenuItem->Selected ? 'active-menu-title' : ""; ?>" data-toggle="collapse" data-parent="#mainmenu"><span class="<?=$topMenuItem->CssClass;?> __iconspacer"></span><?=gettext($topMenuItem->VisibleName);?>  </a>
                            <div class="collapse <?=$topMenuItem->Selected ? 'active-menu in' :"";?>" id="<?=$topMenuItem->Id;?>">
<?php
                            foreach($topMenuItem->Children as $subMenuItem): ?>
<?php
                              if ($subMenuItem->Url == '' ):?>
                               <a href="#<?=$topMenuItem->Id;?>_<?=$subMenuItem->Id;?>" class="list-group-item <?=$subMenuItem->Selected ? "active-menu-title" : "";?>" data-toggle="collapse" data-parent="#<?=$topMenuItem->Id;?>" aria-expanded="<?=$subMenuItem->Selected ? "true" : "false";?>">
                                  <div style="display: table;width: 100%;">
                                    <div style="display: table-row">
                                      <div style="display: table-cell"><?=gettext($subMenuItem->VisibleName);?></div>
                                        <div style="display: table-cell; text-align:right; vertical-align:middle;"><span class="<?=$subMenuItem->CssClass;?>"></span></div>
                                      </div>
                                  </div>
                               </a>
                               <div class="collapse <?=$subMenuItem->Selected ? "active-menu in" :"";?>" id="<?=$topMenuItem->Id;?>_<?=$subMenuItem->Id;?>">
<?php
                                  foreach ($subMenuItem->Children as $subsubMenuItem):?>
<?php
                                    if ($subsubMenuItem->IsExternal == "Y"):?>
                                            <a href="<?=$subsubMenuItem->Url;?>" target="_new" class="list-group-item menu-level-3-item <?=$subsubMenuItem->Selected ? "active" :"";?>"><?=gettext($subsubMenuItem->VisibleName);?></a>
<?php
                                    elseif ($aclObj->isPageAccessible($_SESSION['Username'],$subsubMenuItem->Url)):?>
                                            <a href="<?=$subsubMenuItem->Url;?>" class="list-group-item menu-level-3-item <?=$subsubMenuItem->Selected ? "active" :"";?>"><?=gettext($subsubMenuItem->VisibleName);?></a>
<?php
                                    endif;
                                  endforeach;?>
                                </div>
<?php
                              elseif ($subMenuItem->IsExternal == "Y" ):?>
                                <a href="<?=$subMenuItem->Url;?>" target="_new" class="list-group-item <?=$subMenuItem->Selected ? "active" : "";?>" aria-expanded="<?=$subMenuItem->Selected ? "true" : "false";?>">
                                  <div style="display: table;width: 100%;">
                                    <div style="display: table-row">
                                      <div style="display: table-cell"><?=gettext($subMenuItem->VisibleName);?></div>
                                        <div style="display: table-cell; text-align:right; vertical-align:middle;"><span class="<?=$subMenuItem->CssClass;?>"></span></div>
                                      </div>
                                  </div>
                                </a>
<?php
                              elseif ($aclObj->isPageAccessible($_SESSION['Username'],$subMenuItem->Url)):?>
                                <a href="<?=$subMenuItem->Url;?>" class="list-group-item <?=$subMenuItem->Selected ? "active" :"";?>">
                                  <div style="display: table;width: 100%;">
                                    <div style="display: table-row">
                                      <div style="display: table-cell"><?=gettext($subMenuItem->VisibleName);?></div>
                                        <div style="display: table-cell; text-align:right; vertical-align:middle;"><span class="<?=$subMenuItem->CssClass;?>"></span></div>
                                    </div>
                                  </div>
                                </a>
<?php
                              endif;?>
<?php
                            endforeach; ?>
                            </div>
<?php
                        endforeach; ?>
                    </div>
                </div>
            </nav>
        </div>
    </aside>

	<div class="row">

		<header class="page-content-head">
			<div class="container-fluid">
			    <form action="<?=$_SERVER['REQUEST_URI'];?>" method="post">
				<ul class="list-inline">
					<li class="__mb"><h1><?= gentitle(count($menuBreadcrumbs) ? $menuBreadcrumbs : $pgtitle) ?></h1></li>

					<li class="btn-group-container">

						<?php

						if (isset($shortcut_section) && !empty($shortcuts[$shortcut_section]['service'])) {
							$ssvc = array();
							switch ($shortcut_section) {
								case "openvpn":
									$ssvc = find_service_by_openvpn_vpnid($vpnid);
									break;
								case "captiveportal":
									$ssvc = find_service_by_cp_zone($cpzone);
									break;
								default:
									$ssvc = find_service_by_name($shortcuts[$shortcut_section]['service']);

							}
							if (!empty($ssvc)) {
								echo get_service_status_icon($ssvc, false);
								echo get_service_control_links($ssvc, false);
							}
						}

						?>

						<?php if (!empty($main_buttons)): foreach ($main_buttons as $button): ?>
							<a href="<?=$button['href'];?>" class="btn btn-primary"><span class="glyphicon glyphicon-plus-sign __iconspacer"></span><?=$button['label'];?></a>
						<?php endforeach; endif; ?>

						<?php if (isset($widgetfiles)): ?>


							<a href="#" id="updatepref" style="display:none" onclick="return updatePref();" class="btn btn-primary"><?=gettext("Save Settings");?></a>

							<button type="button" class="btn btn-default" data-toggle="modal" data-target="#modal_widgets"><span class="glyphicon glyphicon-plus-sign __iconspacer"></span><?= gettext('Add widget') ?></button>
						<?php endif; ?>
					</li>
				</ul>
			    </form>
			</div>
		</header>
