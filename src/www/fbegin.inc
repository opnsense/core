<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2012 Jim Pingle <jimp@pfsense.org>
 * Copyright (C) 2007-2008 Scott Ullrich <sullrich@gmail.com>
 * Copyright (C) 2005-2006 Colin Smith <ethethlay@gmail.com>
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

$aclObj = new \OPNsense\Core\ACL();

?>

<header class="page-head">
  <nav class="navbar navbar-default">
    <div class="container-fluid">
      <div class="navbar-header">
        <a class="navbar-brand" href="/index.php">
<?php if (file_exists("/usr/local/opnsense/www/themes/{$themename}/build/images/default-logo.svg")): ?>
        <img class="brand-logo" src="<?= cache_safe("/ui/themes/{$themename}/build/images/default-logo.svg") ?>" height="30" alt="logo" />
<?php else: ?>
        <img class="brand-logo" src="<?= cache_safe("/ui/themes/{$themename}/build/images/default-logo.png") ?>" height="30" alt="logo" />
<?php endif ?>
<?php if (file_exists("/usr/local/opnsense/www/themes/{$themename}/build/images/icon-logo.svg")): ?>
        <img class="brand-icon" src="<?= cache_safe("/ui/themes/{$themename}/build/images/icon-logo.svg") ?>" height="30" alt="icon" />
<?php else: ?>
        <img class="brand-icon" src="<?= cache_safe("/ui/themes/{$themename}/build/images/icon-logo.png") ?>" height="30" alt="icon" />
<?php endif ?>
        </a>
        <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navigation">
          <span class="sr-only">Toggle navigation</span>
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
        </button>
      </div>
      <button class="toggle-sidebar" data-toggle="tooltip right" title="<?= html_safe(gettext('Toggle sidebar')) ?>" style="display:none"><i class="fa fa-chevron-left"></i></button>
      <div class="collapse navbar-collapse">
        <ul class="nav navbar-nav navbar-right">
          <!-- <li id="menu_messages"><?= get_menu_user() ?></li> -->
          <li style="display: flex; flex-direction: column; align-items: center;">
            <span class="navbar-text">
              <i id="system_status" data-toggle="tooltip left" title="<?= html_safe(gettext('Show system status')) ?>" style="cursor:pointer" class="fa fa-bell text-muted"></i>
            </span>
            <span style="color:black; margin-top: -5px;">Notification</span>
          </li>
          <!-- <li> 
            <form class="navbar-form" role="search">
              <div class="input-group">
                <div class="input-group-addon"><i class="fa fa-search"></i></div>
                <input type="text" style="width: 250px;" class="form-control" tabindex="1" data-provide="typeahead" id="menu_search_box" autocomplete="off">
              </div>
            </form>
          </li> -->
          <li>	
              <a href="/wizard.php?xml=system" title="Wizard">	
              <span class="fa fa-magic fa-fw"></span>	
              </a>	
              <span style="color:black;">Wizard</span>	
          </li>
          <li class="dropdown">	
              <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fa fa-medkit fa-fw" aria-hidden="true"></i></a>	
              <span style="color:black;">Diagnostics</span>	
              <ul class="dropdown-menu">	
                <li><a href="/ui/diagnostics/interface/arp">ARP Table</a></li>	
                <li><a href="/ui/diagnostics/dns_diagnostics">DNS Lookup</a></li>	
                <li><a href="/ui/diagnostics/interface/ndp">NDP Table</a></li>	
                <li><a href="/ui/diagnostics/interface/netstat">Netstat</a></li>	
                <li><a href="/ui/diagnostics/packet_capture">Packet Capture</a></li>	
                <li><a href="/diag_testport.php">Port Probe</a></li>	
                <li><a href="/diag_traceroute.php">Trace Route</a></li>	
                <li><a href="/ui/iperf">Iperf</a></li>
                <li><a href="/ui/diagnostics/ping">Ping</a></li>	
              </ul>	
            </li>
            <li class="dropdown">	
              <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fa fa-user" aria-hidden="true"></i></a>	
              <span style="color:black;">User</span>	
              <ul class="dropdown-menu">	
                <li><a href="/system_usermanager_passwordmg.php">Password<i class="fa fa-key fa-fw documentation-menu-icon">`</i>`</i></a></li>	
                <li><a href="/index.php?logout">Logout<i class="fa fa-sign-out documentation-menu-icon"></i></a></li>	
              </ul>
              <li class="dropdown">	
              <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button"  aria-expanded="false"><i Class="fa fa-support" order="80" ></i></a>	
              <span style="color:black;">Help</span>	
              <ul class="dropdown-menu">	
                <li><a IsExternal="Y" href="https://www.veritawall.com/" class="documentation-menu">Documentation<i order="10" Class="fa fa-book documentation-menu-icon"></i></a></li>	
                <li><a href="https://www.veritawall.com/" class="documentation-menu">User Forum<i order="20" Class="fa fa-ticket documentation-menu-icon"></i>	
                </a></li>	
                <li><a href="https://www.veritawall.com/" class="documentation-menu">Commercial Support<i order="30" Class="fa fa-phone documentation-menu-icon"></i></a></li>	
              </ul>	
            </li>
            <li class="dropdown">	
              <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"><i class="fa fa-power-off fa-fw" aria-hidden="true"></i></a>	
              <span style="color:black;">Power</span>	
              <ul class="dropdown-menu">	
                <li><a  href="/ui/core/reboot" >Reboot<i class="fa fa-repeat fa-fw documentation-menu-icon"></i></a></li>	
                <li><a  href="/ui/core/halt">Power Off<i order="20" url="/ui/core/halt" class="fa fa-power-off fa-fw documentation-menu-icon"></i></a></li>	
              </ul>	
            </li>
        </ul>
      </div>
    </div>
  </nav>
</header>

<main class="page-content col-sm-9 col-sm-push-3 col-lg-10 col-lg-push-2">
    <aside id="navigation" class="page-side col-xs-12 col-sm-3 col-lg-2 hidden-xs">
        <div class="row" style="background-color: #e7e7e7f5; height: auto;" > 
            <nav class="page-side-nav">
                <div id="mainmenu" class="panel" style="border:0px"  >
                    <div class="panel list-group" style="border:0px">
<?php
                          foreach($menuSystem as $topMenuItem): ?>
<?php
                          if (count($topMenuItem->Children) >= 1): ?>
                            <a href="#<?=$topMenuItem->Id;?>" class="list-group-item <?= $topMenuItem->Selected ? 'active-menu-title' : ''; ?>" data-toggle="collapse" data-parent="#mainmenu"><span class="<?=$topMenuItem->CssClass;?> __iconspacer"></span><?=html_safe(gettext($topMenuItem->VisibleName));?>  </a>
                            <div class="collapse <?=$topMenuItem->Selected ? 'active-menu in' :'';?>" id="<?=$topMenuItem->Id;?>">
<?php
                            foreach($topMenuItem->Children as $subMenuItem): ?>
<?php
                              if ($subMenuItem->Url == '' ):?>
                               <a href="#<?=$topMenuItem->Id;?>_<?=$subMenuItem->Id;?>" class="list-group-item <?=$subMenuItem->Selected ? "active-menu-title" : '';?>" data-toggle="collapse" data-parent="#<?=$topMenuItem->Id;?>" aria-expanded="<?=$subMenuItem->Selected ? "true" : "false";?>">
                                  <div style="display: table;width: 100%;">
                                    <div style="display: table-row">
                                      <div style="display: table-cell"><?=html_safe(gettext($subMenuItem->VisibleName));?></div>
                                        <div style="display: table-cell; text-align:right; vertical-align:middle;"><span class="<?=$subMenuItem->CssClass;?>"></span></div>
                                      </div>
                                  </div>
                               </a>
                               <div class="collapse <?=$subMenuItem->Selected ? "active-menu in" :'';?>" id="<?=$topMenuItem->Id;?>_<?=$subMenuItem->Id;?>">
<?php
                                  foreach ($subMenuItem->Children as $subsubMenuItem):?>
<?php
                                    if ($subsubMenuItem->IsExternal == "Y"):?>
                                            <a href="<?=$subsubMenuItem->Url;?>" target="_blank" rel="noopener noreferrer" class="list-group-item menu-level-3-item <?=$subsubMenuItem->Selected ? 'active' :'';?>"><?=html_safe(gettext($subsubMenuItem->VisibleName));?></a>
<?php
                                    elseif ($aclObj->isPageAccessible($_SESSION['Username'],$subsubMenuItem->Url)):?>
                                            <a href="<?=$subsubMenuItem->Url;?>" class="list-group-item menu-level-3-item <?=$subsubMenuItem->Selected ? 'active' :'';?>"><?=html_safe(gettext($subsubMenuItem->VisibleName));?></a>
<?php
                                    endif;
                                  endforeach;?>
                                </div>
<?php
                              elseif ($subMenuItem->IsExternal == "Y" ):?>
                                <a href="<?=$subMenuItem->Url;?>" target="_blank" rel="noopener noreferrer" class="list-group-item <?=$subMenuItem->Selected ? 'active' : '';?>" aria-expanded="<?=$subMenuItem->Selected ? 'true' : 'false';?>">
                                  <div style="display: table;width: 100%;">
                                    <div style="display: table-row">
                                      <div style="display: table-cell"><?=html_safe(gettext($subMenuItem->VisibleName));?></div>
                                        <div style="display: table-cell; text-align:right; vertical-align:middle;"><span class="<?=$subMenuItem->CssClass;?>"></span></div>
                                      </div>
                                  </div>
                                </a>
<?php
                              elseif ($aclObj->isPageAccessible($_SESSION['Username'],$subMenuItem->Url)):?>
                                <a href="<?=$subMenuItem->Url;?>" class="list-group-item <?=$subMenuItem->Selected ? 'active' :'';?>">
                                  <div style="display: table;width: 100%;">
                                    <div style="display: table-row">
                                      <div style="display: table-cell"><?=html_safe(gettext($subMenuItem->VisibleName));?></div>
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
                                <a href="<?=$topMenuItem->Url;?>" target="_blank" rel="noopener noreferrer" class="list-group-item <?=$topMenuItem->Selected ? 'active-menu-title' : '';?>" data-parent="#mainmenu">
                                    <span class="<?=$topMenuItem->CssClass;?> __iconspacer"></span><?=html_safe(gettext($topMenuItem->VisibleName));?>
                                </a>
<?php
                              elseif ($aclObj->isPageAccessible($_SESSION['Username'],$topMenuItem->Url)):?>
                                <a href="<?=$topMenuItem->Url;?>" class="list-group-item <?=$topMenuItem->Selected ? 'active-menu-title' : '';?>" data-parent="#mainmenu">
                                    <span class="<?=$topMenuItem->CssClass;?> __iconspacer"></span><?=html_safe(gettext($topMenuItem->VisibleName));?>
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
          <li><h1><?= html_safe(gentitle($menuBreadcrumbs)) ?></h1></li>
          <li class="btn-group-container" style="display:none" >
            <form method="post">
              <?php
              if (isset($service_hook)) {
                $ssvc = service_by_name($service_hook);
                if (!empty($ssvc)) {
                  echo service_control_icon($ssvc);
                  echo service_control_links($ssvc);
                }
              } ?>

              <?php if (isset($widgetCollection)): ?>
                <a href="#" id="updatepref" style="display:none" onclick="return updatePref();" class="btn btn-primary"><?=gettext("Save Settings");?></a>
                <button id="add_widget_btn" type="button" class="btn btn-default" style="display:none" data-toggle="modal" data-target="#modal_widgets"><i class="fa fa-plus-circle fa-fw"></i> <?= gettext('Add widget') ?></button>
                <select class="selectpicker" data-width="120px" id="column_count" style="display:none">
                    <option style="display:none" value="1" <?=$pconfig['column_count'] == "1" ? 'selected="selected"' : '';?>><?= gettext('1 column');?></option>
                    <option style="display:none"  value="2" <?=$pconfig['column_count'] == "2" ? 'selected="selected"' : '';?>><?= sprintf(gettext('%s columns'), '2') ?></option>
                    <option style="display:none" value="3" <?=$pconfig['column_count'] == "3" ? 'selected="selected"' : '';?>><?= sprintf(gettext('%s columns'), '3') ?></option>
                    <option style="display:none" value="4" <?=$pconfig['column_count'] == "4" ? 'selected="selected"' : '';?>><?= sprintf(gettext('%s columns'), '4') ?></option>
                    <option style="display:none" value="6" <?=$pconfig['column_count'] == "6" ? 'selected="selected"' : '';?>><?= sprintf(gettext('%s columns'), '6') ?></option>
                </select>
              <?php endif; ?>
             </form>
          </li>
                  </ul>
      </div>
    </header>
