<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
    Copyright (C) 2007 Scott Dale
    Copyright (C) 2004-2005 T. Lechat <dev@lechat.org>
    Copyright (C) 2004-2005 Manuel Kasper <mk@neon1.net>
    Copyright (C) 2004-2005 Jonathan Watt <jwatt@jwatt.org>.
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
?>

<script>
  /**
   * update interface statistics
   */
  function interface_statistics_widget_update(sender, data)
  {
      var tbody = sender.find('tbody');
      var thead = sender.find('thead');
      data.map(function(interface_data) {
          var th_id = "interface_statistics_widget_intf_" + interface_data['name'];
          if (thead.find("#"+th_id).length == 0) {
              thead.find('tr:eq(0)').append('<th id="'+th_id+'">'+interface_data['name']+'</th>');
              tbody.find('tr').append('<td></td>')
          }
          // fill in stats, use column index to determine td location
          var item_index  = $("#"+th_id).index();
          $("#interface_statistics_widget_pkg_in > td:eq("+item_index+")").html(interface_data['inpkts']);
          $("#interface_statistics_widget_pkg_out > td:eq("+item_index+")").html(interface_data['outpkts']);
          $("#interface_statistics_widget_bytes_in > td:eq("+item_index+")").html(interface_data['inbytes_frmt']);
          $("#interface_statistics_widget_bytes_out > td:eq("+item_index+")").html(interface_data['outbytes_frmt']);
          $("#interface_statistics_widget_errors_in > td:eq("+item_index+")").html(interface_data['inerrs']);
          $("#interface_statistics_widget_errors_out > td:eq("+item_index+")").html(interface_data['outerrs']);
          $("#interface_statistics_widget_collisions > td:eq("+item_index+")").html(interface_data['collisions']);
      });
  }
</script>

<table class="table table-striped table-condensed" data-plugin="interfaces" data-callback="interface_statistics_widget_update">
  <thead>
    <tr>
      <th>&nbsp;</th>
    </tr>
  </thead>
  <tbody>
      <tr id="interface_statistics_widget_pkg_in"><td><strong><?=gettext('Packets In');?></strong></td></tr>
      <tr id="interface_statistics_widget_pkg_out"><td><strong><?=gettext('Packets Out');?></strong></td></tr>
      <tr id="interface_statistics_widget_bytes_in"><td><strong><?=gettext('Bytes In');?></strong></td></tr>
      <tr id="interface_statistics_widget_bytes_out"><td><strong><?=gettext('Bytes Out');?></strong></td></tr>
      <tr id="interface_statistics_widget_errors_in"><td><strong><?=gettext('Errors In');?></strong></td></tr>
      <tr id="interface_statistics_widget_errors_out"><td><strong><?=gettext('Errors Out');?></strong></td></tr>
      <tr id="interface_statistics_widget_collisions"><td><strong><?=gettext('Collisions');?></strong></td></tr>
  </tbody>
</table>
