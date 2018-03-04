<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
    Copyright (C) 2007 Sam Wenham
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

config_read_array('virtualip', 'vip');

?>
<table class="table table-striped table-condensed">
<?php
    foreach ($config['virtualip']['vip'] as $carp):
        if ($carp['mode'] != "carp") {
            continue;
        }
        $status = get_carp_interface_status("{$carp['interface']}_vip{$carp['vhid']}");?>
    <tr>
      <td>
          <span class="glyphicon glyphicon-transfer text-success"></span>&nbsp;
          <strong>
            <a href="/system_hasync.php">
              <span><?=htmlspecialchars(convert_friendly_interface_to_friendly_descr($carp['interface']) . "@{$carp['vhid']}");?></span>
            </a>
          </strong>
      </td>
      <td>
<?php
      if (get_single_sysctl('net.inet.carp.allow') <= 0 ) {
          $status = gettext("DISABLED");
          echo "<span class=\"glyphicon glyphicon-remove text-danger\" title=\"$status\" alt=\"$status\" ></span>";
      } elseif ($status == gettext("MASTER")) {
          echo "<span class=\"fa fa-play text-success\" title=\"$status\" alt=\"$status\" ></span>";
      } elseif ($status == gettext("BACKUP")) {
          echo "<span class=\"fa fa-play text-muted\" title=\"$status\" alt=\"$status\" ></span>";
      } elseif ($status == gettext("INIT")) {
          echo "<span class=\"glyphicon glyphicon-info-sign\" title=\"$status\" alt=\"$status\" ></span>";
      }
      if (!empty($carp['subnet'])):?>
        &nbsp;
        <?=htmlspecialchars($status);?> &nbsp;
        <?=htmlspecialchars($carp['subnet']);?>
<?php
      endif;?>
      </td>
    </tr>
<?php
    endforeach;
    if (count($config['virtualip']['vip']) == 0):?>
    <tr>
      <td>
        <?= sprintf(gettext('No CARP Interfaces Defined. Click %shere%s to configure CARP.'), '<a href="carp_status.php">', '</a>'); ?>
      </td>
    </tr>
<?php
    endif;?>
</table>
