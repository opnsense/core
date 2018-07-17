<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.
    Copyright (C) 2012 Jim Pingle <jimp@pfsense.org>
    Copyright (C) 2007-2008 Scott Ullrich <sullrich@gmail.com>
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
  <nav class="navbar navbar-default">
    <div class="container-fluid">
      <div class="navbar-header">
        <a class="navbar-brand" href="/index.php">
        <img class="brand-logo" src="/ui/themes/<?= $themename ?>/build/images/default-logo.<?=file_exists("/usr/local/opnsense/www/themes/{$themename}/build/images/default-logo.svg") ? "svg" : "png";?>" height="30" alt="logo" />
        <img class="brand-icon" src="/ui/themes/<?= $themename ?>/build/images/icon-logo.<?=file_exists("/usr/local/opnsense/www/themes/{$themename}/build/images/icon-logo.svg") ? "svg" : "png";?>" height="30" alt="icon" />
        </a>
        <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navigation">
          <span class="sr-only">Toggle navigation</span>
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
        </button>
      </div>
	  <button class="toggle-sidebar glyphicon glyphicon-chevron-left"></button>
      <div class="collapse navbar-collapse">
        <ul class="nav navbar-nav navbar-right">
          <li id="menu_messages"><?= get_menu_messages() ?></li>
          <li>
            <form class="navbar-form" role="search">
              <div class="input-group">
                <div class="input-group-addon"><i class="fa fa-search"></i></div>
                <input type="text" style="width: 250px;" class="form-control" tabindex="1" data-provide="typeahead" id="menu_search_box">
              </div>
            </form>
          </li>
        </ul>
      </div>
    </div>
  </nav>
</header>

<main class="page-content col-sm-9 col-sm-push-3 col-lg-10 col-lg-push-2">
    <aside id="navigation" class="page-side col-xs-12 col-sm-3 col-lg-2 hidden-xs">
        <div class="row">
            <nav class="page-side-nav">
                <div id="mainmenu" class="panel" style="border:0px" >
                    <div class="panel list-group" style="border:0px">
<?php
                          foreach($menuSystem as $topMenuItem): ?>
<?php
                          if (count($topMenuItem->Children) >= 1): ?>
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
                                            <a href="<?=$subsubMenuItem->Url;?>" target="_blank" rel="noopener noreferrer" class="list-group-item menu-level-3-item <?=$subsubMenuItem->Selected ? "active" :"";?>"><?=gettext($subsubMenuItem->VisibleName);?></a>
<?php
                                    elseif ($aclObj->isPageAccessible($_SESSION['Username'],$subsubMenuItem->Url)):?>
                                            <a href="<?=$subsubMenuItem->Url;?>" class="list-group-item menu-level-3-item <?=$subsubMenuItem->Selected ? "active" :"";?>"><?=gettext($subsubMenuItem->VisibleName);?></a>
<?php
                                    endif;
                                  endforeach;?>
                                </div>
<?php
                              elseif ($subMenuItem->IsExternal == "Y" ):?>
                                <a href="<?=$subMenuItem->Url;?>" target="_blank" rel="noopener noreferrer" class="list-group-item <?=$subMenuItem->Selected ? "active" : "";?>" aria-expanded="<?=$subMenuItem->Selected ? "true" : "false";?>">
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
                          else: ?>
<?php
                              if ($topMenuItem->IsExternal == "Y" ):?>
                                <a href="<?=$topMenuItem->Url;?>" target="_blank" rel="noopener noreferrer" class="list-group-item <?=$topMenuItem->Selected ? "active-menu-title" : "";?>" data-parent="#mainmenu">
                                    <span class="<?=$topMenuItem->CssClass;?> __iconspacer"></span><?=gettext($topMenuItem->VisibleName);?>
                                </a>
<?php
                              elseif ($aclObj->isPageAccessible($_SESSION['Username'],$topMenuItem->Url)):?>
                                <a href="<?=$topMenuItem->Url;?>" class="list-group-item <?=$topMenuItem->Selected ? "active-menu-title" : "";?>" data-parent="#mainmenu">
                                    <span class="<?=$topMenuItem->CssClass;?> __iconspacer"></span><?=gettext($topMenuItem->VisibleName);?>
                                </a>

<?php
                              endif;?>
<?php
                        endif;?>
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
        <ul class="list-inline">
          <li><h1><?= gentitle($menuBreadcrumbs) ?></h1></li>
          <li class="btn-group-container">
            <form action="<?=$_SERVER['REQUEST_URI'];?>" method="post">
              <?php
              if (isset($service_hook)) {
                $ssvc = find_service_by_name($service_hook);
                if (!empty($ssvc)) {
                  echo get_service_status_icon($ssvc);
                  echo get_service_control_links($ssvc);
                }
              } ?>
              <?php if (!empty($main_buttons)): foreach ($main_buttons as $button): ?>
                <a href="<?=$button['href'];?>" class="btn btn-primary"><i class="fa fa-plus-circle fa-fw"></i> <?= $button['label'] ?></a>
              <?php endforeach; endif; ?>

              <?php if (isset($widgetCollection)): ?>
                <a href="#" id="updatepref" style="display:none" onclick="return updatePref();" class="btn btn-primary"><?=gettext("Save Settings");?></a>
                <button id="add_widget_btn" type="button" class="btn btn-default" data-toggle="modal" data-target="#modal_widgets"><i class="fa fa-plus-circle fa-fw"></i> <?= gettext('Add widget') ?></button>
                <select class="selectpicker" data-width="120px" id="column_count">
                    <option value="1" <?=$pconfig['column_count'] == "1" ? 'selected="selected"' : "";?>><?= gettext('1 column');?></option>
                    <option value="2" <?=$pconfig['column_count'] == "2" ? 'selected="selected"' : "";?>><?= sprintf(gettext('%s columns'), '2') ?></option>
                    <option value="3" <?=$pconfig['column_count'] == "3" ? 'selected="selected"' : "";?>><?= sprintf(gettext('%s columns'), '3') ?></option>
                    <option value="4" <?=$pconfig['column_count'] == "4" ? 'selected="selected"' : "";?>><?= sprintf(gettext('%s columns'), '4') ?></option>
                    <option value="6" <?=$pconfig['column_count'] == "6" ? 'selected="selected"' : "";?>><?= sprintf(gettext('%s columns'), '6') ?></option>
                </select>
              <?php endif; ?>
             </form>
          </li>
        </ul>
      </div>
	<!-- navigation toggle -->
	<script>
		$(document).ready(function () {
			$(".toggle-sidebar").click(function () {
				$("#navigation").toggleClass("col-sidebar-left");
				$("main").toggleClass("col-sm-9 col-sm-push-3 col-lg-10 col-lg-push-2 col-lg-12");
				$(".toggle-sidebar").toggleClass("glyphicon-chevron-right glyphicon-chevron-left");
					if ($("#navigation").hasClass("col-sidebar-left")) {
						$(".brand-logo").css("display", "none");
						$(".brand-icon").css("display", "inline-block");}
					else												{
						$(".brand-icon").css("display", "none");
						$(".brand-logo").css("display", "inline-block");
						$("#navigation.page-side.col-xs-12.col-sm-3.col-lg-2.hidden-xs").css("width", "");
																		}
													});
										});
	</script>
	<!-- sidebar mouseevents -->
	<script>
		$(document).ready(function () {
			$("#navigation").hover(function () {
				if ($("#navigation").hasClass("col-sidebar-left")) {
					$("#navigation > div > nav > #mainmenu > div > div").on({
						mouseout: function() {$("#navigation.col-sidebar-left").css("width", "70px"); },
						mouseover: function() {$("#navigation.col-sidebar-left").css("width", "380px"); }});
					$("#navigation > div > nav > #mainmenu > div > a").on({
						mouseout: function() {$("#navigation.col-sidebar-left").css("width", "70px"); },
						mouseover: function() {$("#navigation.col-sidebar-left").css("width", "380px"); }});
																	}
												});
									});
	</script>
	<!-- on resize - toggle sidebar / main navigation -->
	<script>
		$(window).on('resize', function(){
			var win = $(this);
			if (((win.height() <= 669) && $("#navigation").hasClass("col-sidebar-left"))) {
					$("main").toggleClass("col-sm-9 col-sm-push-3 col-lg-10 col-lg-push-2 col-lg-12");
					$("#navigation").toggleClass("col-sidebar-left");
					$(".toggle-sidebar").toggleClass("glyphicon-chevron-right glyphicon-chevron-left");
					$(".brand-icon").css("display", "none");
					$(".brand-logo").css("display", "inline-block");
					$("#navigation.page-side.col-xs-12.col-sm-3.col-lg-2.hidden-xs").css("width", ""); }
			else if ((win.width() <= 768) && $("#navigation").hasClass("col-sidebar-left")){
					$("main").toggleClass("col-sm-9 col-sm-push-3 col-lg-10 col-lg-push-2 col-lg-12");
					$("#navigation").toggleClass("col-sidebar-left");
					$(".toggle-sidebar").toggleClass("glyphicon-chevron-right glyphicon-chevron-left");
					$(".brand-logo").css("display", "none");
					$(".brand-icon").css("display", "inline-block");
					$("#navigation.page-side.col-xs-12.col-sm-3.col-lg-2.hidden-xs").css("width", ""); }
			else if (((win.width() <= 768) && $("#navigation").not("col-sidebar-left")) || ((win.width() >= 768) && $("#navigation").hasClass("col-sidebar-left"))) {
					$(".brand-logo").css("display", "none");
					$(".brand-icon").css("display", "inline-block"); }
			else if ((win.width() >= 768) && $("#navigation").not("col-sidebar-left")){
					$("#navigation").addClass("in");
					$(".brand-icon").css("display", "none");
					$(".brand-logo").css("display", "inline-block"); }
		});
	</script>
	<!-- navigation toggle-->
    </header>
