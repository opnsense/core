<?php

/*
    Copyright (C) 2014-2016 Deciso B.V.
    Copyright (C) 2008 Seth Mos <seth.mos@dds.nl>

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
  function gateways_widget_update(sender, data)
  {
      var tbody = sender.find('tbody');
      data.map(function(gateway) {
          var tr_content = [];
          var tr_id = "gateways_widget_gw_" + gateway['name'];
          var status_color = '';
          if (tbody.find("#"+tr_id).length == 0) {
              // add new gateway
              tr_content.push('<tr id="'+tr_id+'">');
              tr_content.push('<td><small><strong>'+gateway['name']+'</strong><br/>'+gateway['address']+'</small></td>');
              tr_content.push('<td class="text-nowrap">'+gateway['delay']+'</td>');
<?php
            if (isset($config['system']['prefer_dpinger'])) :?>                  
                tr_content.push('<td class="text-nowrap">'+gateway['stddev']+'</td>');    
<?php
            endif;?>                
              tr_content.push('<td class="text-nowrap">'+gateway['loss']+'</td>');
              tr_content.push('<td><span>'+gateway['status_translated']+'</span></td>');
              tr_content.push('</tr>');
              tbody.append(tr_content.join(''));
          } else {
              // update existing gateway
              $("#"+tr_id+" > td:eq(1)").html(gateway['delay']);
<?php
            if (isset($config['system']['prefer_dpinger'])) :?>     
                $("#"+tr_id+" > td:eq(2)").html(gateway['stddev']);
                $("#"+tr_id+" > td:eq(3)").html(gateway['loss']);
                $("#"+tr_id+" > td:eq(4)").html('<span>'+gateway['status_translated']+'</span>');
<?php       else:?>
            $("#"+tr_id+" > td:eq(2)").html(gateway['loss']);
            $("#"+tr_id+" > td:eq(3)").html('<span>'+gateway['status_translated']+'</span>');         
<?php
            endif;?>                 
             
          }
          // set color on status text
          switch (gateway['status']) {
            case 'force_down':
            case 'down':
              status_color = 'danger';
              break;
            case 'loss':
            case 'delay':
              status_color = 'warning';
              break;
            case 'none':
              status_color = 'success';
              break;
          }
<?php
          if (isset($config['system']['prefer_dpinger'])) :?>             
          $("#"+tr_id+" > td:eq(4) > span").removeClass("label-danger label-warning label-success label");
          if (status_color != '') {
            $("#"+tr_id+" > td:eq(4) > span").addClass("label label-" + status_color);
          }
<?php       else:?>
           $("#"+tr_id+" > td:eq(3) > span").removeClass("label-danger label-warning label-success label");
          if (status_color != '') {
            $("#"+tr_id+" > td:eq(3) > span").addClass("label label-" + status_color);
          }
<?php
            endif;?>                 
                       
      });
  }
</script>

<!-- gateway table -->
<table class="table table-striped table-condensed" data-plugin="gateway" data-callback="gateways_widget_update">
    <thead>
        <tr>
            <th><?=gettext('Name')?></th>
            <th><?=gettext('RTT')?></th>
<?php
            if (isset($config['system']['prefer_dpinger'])) :?>                         
            <th><?=gettext('STDEV')?></th>
<?php
            endif;?>            
            <th><?=gettext('Loss')?></th>
            <th><?=gettext('Status')?></th>
        </tr>
    </thead>
    <tbody>
  </tbody>
</table>
