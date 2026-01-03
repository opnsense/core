{#
 #
 # Copyright (c) 2014-2022 Deciso B.V.
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1. Redistributions of source code must retain the above copyright notice,
 #    this list of conditions and the following disclaimer.
 #
 # 2. Redistributions in binary form must reproduce the above copyright notice,
 #    this list of conditions and the following disclaimer in the documentation
 #    and/or other materials provided with the distribution.
 #
 # THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
 # INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 # AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 # AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 # OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 # SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 # INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 # CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 # ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 # POSSIBILITY OF SUCH DAMAGE.
 #}

<script>
    $( document ).ready(function() {
        $("#grid-arp").UIBootgrid({
          search:'/api/diagnostics/interface/search_arp/',
          options:{
            virtualDOM: true,
            requestHandler: function(request){
                request['resolve'] = $("#resolve").prop("checked") ? 'yes': 'no';
                return request;
            }
          }
        });
        $("#resolve").change(function(){
            $('#grid-arp').bootgrid('reload');
        });

        $("#refresh").click(function() {
            $("#grid-arp").bootgrid("reload");
        });

        $("#flushModal").click(function(event){
          BootstrapDialog.show({
            type:BootstrapDialog.TYPE_DANGER,
            title: "{{ lang._('Flush ARP Table') }}",
            message: "{{ lang._('Flush the ARP cache manually, in case your ARP cache contains invalid data.') }}",
            buttons: [{
                      label: "<?= gettext('Close');?>",
                      action: function(dialogRef) {
                        dialogRef.close();
                      }}, {
                      label: "<?= gettext('Flush ARP Table');?>",
                      action: function(dialogRef) {
                        ajaxCall("/api/diagnostics/interface/flush_arp", {}, function (data, status) {
                            $('#grid-arp').bootgrid('reload');
                        });
                    }
                  }]
          }); // end BootstrapDialog.show
        }); // end .click(function(event)
    });
</script>

<div class="content-box">
    <div class="content-box-main">
        <div class="table-responsive">
            <div  class="col-sm-12">
                <table id="grid-arp" class="table table-condensed table-hover table-striped table-responsive">
                    <thead>
                    <tr>
                        <th data-column-id="ip" data-type="string"  data-identifier="true">{{ lang._('IP') }}</th>
                        <th data-column-id="mac" data-type="string" data-identifier="true">{{ lang._('MAC') }}</th>
                        <th data-column-id="manufacturer" data-type="string" data-css-class="hidden-xs hidden-sm" data-header-css-class="hidden-xs hidden-sm">{{ lang._('Manufacturer') }}</th>
                        <th data-column-id="intf" data-type="string" data-css-class="hidden-xs hidden-sm" data-header-css-class="hidden-xs hidden-sm">{{ lang._('Interface') }}</th>
                        <th data-column-id="intf_description" data-type="string" data-css-class="hidden-xs hidden-sm" data-header-css-class="hidden-xs hidden-sm">{{ lang._('Interface name') }}</th>
                        <th data-column-id="hostname" data-type="string" data-css-class="hidden-xs hidden-sm" data-header-css-class="hidden-xs hidden-sm">{{ lang._('Hostname') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    </tbody>
                    <tfoot>
                    <tr>
                        <td colspan="6">{{ lang._('NOTE: Local IPv6 peers use NDP instead of ARP.') }}</td>
                    </tr>
                    </tfoot>
                </table>
            </div>
            <div  class="col-sm-12">
                <div class="row">
                    <table class="table">
                        <tr>
                            <td>
                                <input type="checkbox" id="resolve" name="resolve" value="yes">
                            </td>
                            <td>
                                <strong><?=gettext("Name resolution");?></strong>
                                <p class="text-muted">
                                    <small>
                                        {{ lang._('Enable this to attempt to resolve names when displaying the tables. By enabling name resolution, the query may take longer.') }}
                                    </small>
                                </p>
                            </td>
                            <td>
                                <div class="pull-right">
                                    <button type="button" class="btn btn-default" id="flushModal">
                                        <span>{{ lang._('Flush') }}</span>
                                        <span class="fa fa-trash"></span>
                                    </button>
                                    <button id="refresh" type="button" class="btn btn-default">
                                        <span>{{ lang._('Refresh') }}</span>
                                        <span class="fa fa-refresh"></span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
