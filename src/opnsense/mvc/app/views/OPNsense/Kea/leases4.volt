{#
    # Copyright (c) 2023 Deciso B.V.
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
        let selected_interfaces = [];
        $("#interface-selection").on("changed.bs.select", function (e) {
            selected_interfaces = $(this).val();
            $("#grid-leases").bootgrid('reload');
        })
        updateServiceControlUI('kea');
        $("#reconfigureAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();
                saveFormToEndpoint("/api/kea/dhcpv4/set", 'frm_generalsettings', function () { dfObj.resolve(); }, true, function () { dfObj.reject(); });
                return dfObj;
            },
            onAction: function(data, status) {
                updateServiceControlUI('kea');
            }
        });

        $("#grid-leases").UIBootgrid({
            search:'/api/kea/leases4/search/',
            options: {
                selection: false,
                multiSelect: false,
                useRequestHandlerOnGet: true,
                requestHandler: function(request) {
                    request['selected_interfaces'] = selected_interfaces;
                    return request;
                },
                responseHandler: function (response) {
                    if (response.interfaces !== undefined) {
                        let intfsel = $("#interface-selection > option").map(function () {
                            return $(this).val();
                        }).get();
                        for ([intf, descr] of Object.entries(response['interfaces'])) {
                            if (!intfsel.includes(intf)) {
                                $("#interface-selection").append($('<option>', {
                                    value: intf,
                                    text: descr
                                }));
                            }
                        }
                        $("#interface-selection").selectpicker('refresh');
                    }
                    return response;
                },
                formatters: {
                    "overflowformatter": function (column, row) {
                        return '<span class="overflow">' + row[column.id] + '</span><br/>'
                    },
                    "timestamp": function (column, row) {
                        return moment.unix(row[column.id]).local().format('YYYY-MM-DD HH:mm:ss');
                    },
                    "commands": function (column, row) {
                        let btns = '';
                        if (!row.is_reserved) {
                            btns += '<button type="button" class="btn btn-xs btn-default command-add-reservation" data-row-id="' + 
                                   row.address + '" data-mac="' + row.hwaddr + '" data-hostname="' + (row.hostname || '') + 
                                   '" title="Add to Reservations"><span class="fa fa-plus"></span></button>';
                        } else {
                            btns += '<span class="label label-info">Reserved</span>';
                        }
                        return btns;
                    }
                }
            }
        }).on("loaded.rs.jquery.bootgrid", function() {
            $(this).find(".command-add-reservation").on("click", function(e) {
                e.preventDefault();
                let ip = $(this).data("row-id");
                let mac = $(this).data("mac");
                let hostname = $(this).data("hostname");

                ajaxCall("/api/kea/leases4/addReservation", {
                    ip: ip,
                    mac: mac,
                    hostname: hostname
                }, function(data, status) {
                    if (data.status === "ok") {
                        //$("#grid-leases").bootgrid("reload");
                        $("#keaChangeMessage").show();
                        $("#reconfigureAct").show();
                        BootstrapDialog.show({
                            type: BootstrapDialog.TYPE_SUCCESS,
                            title: "{{ lang._('Success') }}",
                            message: "{{ lang._('Address has been added to reservations') }}",
                            buttons: [{
                                label: "{{ lang._('Close') }}",
                                action: function(dialogRef) {
                                    dialogRef.close();
                                }
                            }]
                        });
                    } else {
                        BootstrapDialog.show({
                            type: BootstrapDialog.TYPE_WARNING,
                            title: "{{ lang._('Error') }}",
                            message: data.message,
                            buttons: [{
                                label: "{{ lang._('Close') }}",
                                action: function(dialogRef) {
                                    dialogRef.close();
                                }
                            }]
                        });
                    }
                });
            });
        });

        $("#interface-selection-wrapper").detach().prependTo('#grid-leases-header > .row > .actionBar > .actions');
        
        // Configure the reconfigure button
        $("#reconfigureAct").click(function(){
            let _this = $(this);
            _this.prop('disabled', true);
            ajaxCall(_this.data('endpoint'), {}, function(data, status){
                // wait for status to settle.
                setTimeout(function(){
                    ajaxCall('/api/kea/service/status', {}, function(data, status) {
                        _this.prop('disabled', false);
                        if (data.status === 'running') {
                            $("#keaChangeMessage").hide();
                        }
                    });
                }, 1000);
            });
        });
        

    });
</script>

<style>
.overflow {
    text-overflow: clip;
    white-space: normal;
    word-break: break-word;
}
</style>




    <div class="content-box __mb">
        <ul class="nav nav-tabs" data-tabs="tabs" id="maintabs"></ul>
        <div class="tab-content content-box col-xs-12 __mb">
            <div class="btn-group" id="interface-selection-wrapper">
                <select class="selectpicker" multiple="multiple" data-live-search="true" id="interface-selection" data-width="auto" title="All Interfaces">
                </select>
            </div>
            <table id="grid-leases" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogReservation" data-editAlert="keaChangeMessage">
                <thead>
                <tr>
                    <th data-column-id="if_descr" data-type="string">{{ lang._('Interface') }}</th>
                    <th data-column-id="address" data-identifier="true" data-type="string" data-formatter="overflowformatter">{{ lang._('IP Address') }}</th>
                    <th data-column-id="hwaddr" data-type="string" data-width="9em">{{ lang._('MAC Address') }}</th>
                    <th data-column-id="valid_lifetime" data-type="integer">{{ lang._('Lifetime') }}</th>
                    <th data-column-id="expire" data-type="string" data-formatter="timestamp">{{ lang._('Expire') }}</th>
                    <th data-column-id="hostname" data-type="string" data-formatter="overflowformatter">{{ lang._('Hostname') }}</th>
                    <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
                </thead>
                <tbody>
                </tbody>
                <tfoot>
                </tfoot>
            </table>
        </div>
    </div>



    <div class="content-box">
        <div class="col-md-12 __mt __mb">
            <div id="keaChangeMessage" class="alert alert-info" style="display: none" role="alert">
                {{ lang._('After changing settings, please remember to apply them') }}
            </div>
            <button class="btn btn-primary" id="reconfigureAct"
                    data-endpoint='/api/kea/service/reconfigure'
                    data-label="{{ lang._('Apply') }}"
                    data-grid-reload="grid-leases"
                    data-error-title="{{ lang._('Error reconfiguring DHCPv4') }}"
                    type="button">
            </button>
        </div>
    </div>



{{ partial("layout_partials/base_dialog",['fields':formDialogReservation,'id':'DialogReservation','label':lang._('Edit Reservation')])}}