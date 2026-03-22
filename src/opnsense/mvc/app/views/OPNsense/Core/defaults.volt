{#
 # Copyright (c) 2025 Deciso B.V.
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
 # THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
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
        /* update default address and show form */
        ajaxGet('/api/core/defaults/get', {}, function(data){
            if (data.default_ip) {
                $("#ip_placeholder").text($("#ip_placeholder").text().replace('%s', data.default_ip));
                $("#defaults_form").show();
            }
        });
        /* full system defauls */
        $("#system_defaults").click(function(){
            BootstrapDialog.show({
                type:BootstrapDialog.TYPE_INFO,
                title: '{{ lang._('Configuration defaults') }}',
                closable: false,
                message: '{{ lang._('The system will install the configuration defaults now and power off when finished.') }}',
                onshow: function (dialogRef) {
                    ajaxCall('/api/core/defaults/factory_defaults');
                },
            });
        });

        function load_section()
        {
            ajaxGet('/api/core/defaults/get_installed_sections', {}, function(data){
                if (data.items) {
                    $("#sections").empty();
                    data.items.forEach(function (item, index) {
                        let label = item.description + ' [' + item.id + ']';
                        if (item.installed == "0") {
                             label += ' ' + "{{ lang._('(not installed)') }}";
                        }
                        $("#sections").append($("<option/>").val(item.id).text(label));
                    });
                    $("#sections").selectpicker('refresh');
                }
            });
        }
        load_section();

        $("#reset_sections").click(function(event){
            event.preventDefault();
            BootstrapDialog.show({
                type:BootstrapDialog.TYPE_DANGER,
                title: "{{ lang._('Reset') }}",
                message: "{{ lang._('Are you sure you want to reset selected configuration sections?\n Removing parts of the configuration does not warrant consistency and may lead to unexpected behavior.')}}",
                buttons: [
                    {
                        label: "{{ lang._('No') }}",
                        action: function(dialogRef) {
                            dialogRef.close();
                        }
                    },
                    {
                        label: "{{ lang._('Yes') }}",
                        action: function(dialogRef) {
                            ajaxCall('/api/core/defaults/reset', {'items': $("#sections").val()}, function(){
                                dialogRef.close();
                                load_section();
                            });
                        }
                    }
                ]
            });
        });


    });

</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a id="systemtab" data-toggle="tab" href="#full_defaults">{{ lang._('Full') }}</a></li>
    <li id="componentstab"><a data-toggle="tab" href="#components">{{ lang._('Components') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="full_defaults" class="tab-pane fade in active">
        <div class="container-fluid">
            <div class="row">
                <section class="col-xs-12" style="display: none;" id="defaults_form">
                    <br/>
                    <p><strong> {{ lang._('If you click "Yes", the system will:') }}</strong></p>
                    <ul>
                        <li>{{ lang._('Reset to factory defaults') }}</li>
                        <li id="ip_placeholder">{{ lang._('LAN IP address will be reset to %s') }}</li>
                        <li>{{ lang._('System will be configured as a DHCP server on the default LAN interface') }}</li>
                        <li>{{ lang._('WAN interface will be set to obtain an address automatically from a DHCP server') }}</li>
                        <li>{{ lang._('Admin user name and password will be reset') }}</li>
                        <li>{{ lang._('Shut down after changes are complete') }}</li>
                    </ul>
                    <p><strong>{{ lang._('Are you sure you want to proceed?') }}</strong></p>
                    <div class="btn-group">
                    <button id="system_defaults" class="btn btn-primary">{{ lang._('Yes')}}</button>
                    <a href="/" class="btn btn-default">{{ lang._('No')}}</a>
                    <br/><br/><br/>
                </section>
            </div>
        </div>
    </div>
    <div id="components" class="tab-pane fade in">
        <table class="table table-condensed table-striped">
            <br/>
            <thead>
                <tr>
                    <th colspan="2">
                        {{ lang._('Danger zone, only reset configuration sections if you understand the impact.') }}
                    </th>
                </tr>
                <tr>
                    <th>{{ lang._('Sections')}}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <select id="sections" data-size="10" data-width="100%" data-live-search="true" multiple="multiple"></select>
                    </td>
                    <td>
                        <button id="reset_sections" class="btn btn-primary">{{ lang._('Reset')}}</button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
