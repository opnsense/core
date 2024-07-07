<?php

/*
 * Copyright (C) 2014-2016 Deciso B.V.
 * Copyright (C) 2004-2012 Scott Ullrich <sullrich@gmail.com>
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
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

require_once('guiconfig.inc');

$product = product::getInstance();

if (isset($config['trigger_initial_wizard']) || isset($_GET['wizard_done'])):
  include("head.inc");
  ?>
  <body>
  <?php
  include("fbegin.inc");
    if (isset($config['trigger_initial_wizard']) || isset($_GET['wizard_done'])): ?>
    <script>
        $( document ).ready(function() {
          $(".page-content-head:first").hide();
        });
    </script>
    <header class="page-content-head">
      <div class="container-fluid">
  <?php
        if (isset($config['trigger_initial_wizard'])): ?>
        <h1><?= gettext("Starting initial configuration!") ?></h1>
  <?php
        else: ?>
        <h1><?= gettext("Finished initial configuration!") ?></h1>
  <?php
        endif ?>
      </div>
    </header>
    <section class="page-content-main">
      <div class="container-fluid col-xs-12 col-sm-10 col-md-9">
        <div class="row">
          <section class="col-xs-12">
            <div class="content-box wizard" style="padding: 20px;">
              <div class="table-responsive">
  <?php if (get_themed_filename('/images/default-logo.svg', true)): ?>
                <img src="<?= cache_safe(get_themed_filename('/images/default-logo.svg')) ?>" border="0" alt="logo" style="max-width:380px;" />
  <?php else: ?>
                <img src="<?= cache_safe(get_themed_filename('/images/default-logo.png')) ?>" border="0" alt="logo" style="max-width:380px;" />
  <?php endif ?>
                <br />
                <div class="content-box-main" style="padding-bottom:0px;">
                  <?php
                      if (isset($config['trigger_initial_wizard'])) {
                          echo '<p>' . sprintf(gettext('Welcome to %s!'), $product->name()) . "</p>\n";
                          echo '<p>' . gettext('One moment while we start the initial setup wizard.') . "</p>\n";
                          echo '<p class="__nomb">' . gettext('To bypass the wizard, click on the logo in the upper left corner.') . "</p>\n";
                      } else {
                          echo '<p>' . sprintf(gettext('Congratulations! %s is now configured.'), $product->name()) . "</p>\n";
                          echo '<p>' . sprintf(gettext(
                              'Please consider donating to the project to help us with our overhead costs. ' .
                              'See %sour website%s to donate or purchase available %s support services.'),
                              '<a target="_new" href="' . $product->website() . '">', '</a>', $product->name()) . "</p>\n";
                          echo '<p class="__nomb">' . sprintf(gettext('Click to %scontinue to the dashboard%s.'), '<a href="/">', '</a>') . ' ';
                          echo sprintf(gettext('Or click to %scheck for updates%s.'), '<a href="/ui/core/firmware#checkupdate">', '</a>'). "</p>\n";
                      }
                  ?>
                </div>
              <div>
            </div>
          </section>
        </div>
      </div>
    </section>
  <?php
        if (isset($config['trigger_initial_wizard'])): ?>
    <meta http-equiv="refresh" content="5;url=/wizard.php?xml=system">
  <?php endif ;
include("foot.inc"); endif;
else:
header('Location: /ui/core/dashboard');
exit;
endif;
