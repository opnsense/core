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

$menu_system = preg_replace('/[^a-z0-9_]/', '', $theme_config['menu_system'] ?? 'sidebar');
$content_class = $theme_config['content_class'] ?? 'col-sm-9 col-sm-push-3 col-lg-10 col-lg-push-2';

?>

<header class="page-head">
  <nav class="navbar navbar-default">
    <div class="container-fluid">
      <div class="navbar-header">
        <a class="navbar-brand" href="/index.php">
<?php if (get_themed_filename('/images/default-logo.svg', true)): ?>
        <img class="brand-logo" src="<?= cache_safe(get_themed_filename('/images/default-logo.svg')) ?>" height="30" alt="logo" />
<?php else: ?>
        <img class="brand-logo" src="<?= cache_safe(get_themed_filename('/images/default-logo.png')) ?>" height="30" alt="logo" />
<?php endif ?>
<?php if (get_themed_filename('/images/icon-logo.svg', true)): ?>
        <img class="brand-icon" src="<?= cache_safe(get_themed_filename('/images/icon-logo.svg')) ?>" height="30" alt="icon" />
<?php else: ?>
        <img class="brand-icon" src="<?= cache_safe(get_themed_filename('/images/icon-logo.png')) ?>" height="30" alt="icon" />
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
          <li id="menu_messages"><?= get_menu_user() ?></li>
          <li>
            <span class="navbar-text" style="margin-left: 0">
              <i id="system_status" data-toggle="tooltip left" title="<?= html_safe(gettext('Show system status')) ?>" style="cursor:pointer" class="fa fa-circle text-muted"></i>
            </span>
          </li>
          <li>
            <form class="navbar-form" role="search">
              <div class="input-group">
                <div class="input-group-addon"><i class="fa fa-search"></i></div>
                <input type="text" style="width: 250px;" class="form-control" tabindex="1" data-provide="typeahead" id="menu_search_box" autocomplete="off">
              </div>
            </form>
          </li>
        </ul>
      </div>
    </div>
  </nav>
</header>

<?php
/* load menu system include based on theme configuration */
$menu_inc = __DIR__ . "/fbegin_menu_{$menu_system}.inc";
if (!file_exists($menu_inc)) {
    $menu_inc = __DIR__ . '/fbegin_menu_sidebar.inc';
}

if ($menu_system !== 'sidebar') {
    require $menu_inc;
}
?>
<main class="page-content <?= htmlspecialchars($content_class) ?>">
<?php
if ($menu_system === 'sidebar') {
    require $menu_inc;
}
?>
  <div class="row">
    <header class="page-content-head">
      <div class="container-fluid">
        <ul class="list-inline">
          <li><h1><?= html_safe(gentitle($menuBreadcrumbs)) ?></h1></li>
          <li class="btn-group-container">
            <form method="post">
              <?php
              if (isset($service_hook)) {
                $ssvc = json_decode(configdp_run('service list', [$service_hook]), true);
                if (!empty($ssvc[0])) {
                  echo service_control_icon($ssvc[0]);
                  echo service_control_links($ssvc[0]);
                }
              } ?>

              <?php if (isset($widgetCollection)): ?>
                <a href="#" id="updatepref" style="display:none" onclick="return updatePref();" class="btn btn-primary"><?=gettext("Save Settings");?></a>
                <button id="add_widget_btn" type="button" class="btn btn-default" data-toggle="modal" data-target="#modal_widgets"><i class="fa fa-plus-circle fa-fw"></i> <?= gettext('Add widget') ?></button>
                <select class="selectpicker" data-width="120px" id="column_count">
                    <option value="1" <?=$pconfig['column_count'] == "1" ? 'selected="selected"' : '';?>><?= gettext('1 column');?></option>
                    <option value="2" <?=$pconfig['column_count'] == "2" ? 'selected="selected"' : '';?>><?= sprintf(gettext('%s columns'), '2') ?></option>
                    <option value="3" <?=$pconfig['column_count'] == "3" ? 'selected="selected"' : '';?>><?= sprintf(gettext('%s columns'), '3') ?></option>
                    <option value="4" <?=$pconfig['column_count'] == "4" ? 'selected="selected"' : '';?>><?= sprintf(gettext('%s columns'), '4') ?></option>
                    <option value="6" <?=$pconfig['column_count'] == "6" ? 'selected="selected"' : '';?>><?= sprintf(gettext('%s columns'), '6') ?></option>
                </select>
              <?php endif; ?>
             </form>
          </li>
        </ul>
      </div>
    </header>
