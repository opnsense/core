{#

OPNsense® is Copyright © 2014 – 2015 by Deciso B.V.
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

1.  Redistributions of source code must retain the above copyright notice,
this list of conditions and the following disclaimer.

2.  Redistributions in binary form must reproduce the above copyright notice,
this list of conditions and the following disclaimer in the documentation
and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.

#}
<script src="/ui/js/moment-with-locales.min.js" type="text/javascript"></script>

<script type="text/javascript">

    $( document ).ready(function() {
        /**
         * update zone list
         */
        function updateVoucherProviders() {
            ajaxGet(url="/api/captiveportal/voucher/listProviders/", sendData={}, callback=function(data, status) {
                if (status == "success") {
                    $('#voucher-providers').html("");
                    $.each(data, function(key, value) {
                        $('#voucher-providers').append($("<option></option>").attr("value", value).text(value));
                    });
                    // link on change event
                    $('#voucher-providers').on('change', function(){
                        updateVoucherGroupList();
                    });
                    // initial load voucher list
                    updateVoucherGroupList();
                }
            });
        }

        /**
         * update voucher group list
         */
        function updateVoucherGroupList() {
            var voucher_provider = $('#voucher-providers').find("option:selected").val();
            ajaxGet(url="/api/captiveportal/voucher/listVoucherGroups/" + voucher_provider + "/", sendData={}, callback=function(data, status) {
                if (status == "success") {
                    $('#voucher-groups').html("");
                    $.each(data, function(key, value) {
                        $('#voucher-groups').append($("<option></option>").attr("value", value).text(value));
                    });
                    $('.selectpicker').selectpicker('refresh');
                    // link on change event
                    $('#voucher-groups').on('change', function(){
                        updateVoucherList();
                    });
                    // initial load voucher list
                    updateVoucherList();
                }
            });
        }

        /**
         * list vouchers in grid
         */
        function updateVoucherList() {
            var voucher_provider = $('#voucher-providers').find("option:selected").val();
            var voucher_group = $('#voucher-groups').find("option:selected").val();
            var gridopt = {
                ajax: false,
                selection: true,
                multiSelect: true,
                formatters: {
                    "commands": function (column, row) {
                        return "<button type=\"button\" class=\"btn btn-xs btn-default command-remove\" data-row-id=\"" + row.username + "\"><span class=\"fa fa-trash-o\"></span></button>";
                    }
                },
                converters: {
                    // convert datetime type fields from unix timestamp to readable format
                    datetime: {
                        from: function (value) {
                            return moment(parseInt(value) * 1000);
                        },
                        to: function (value) {
                            return value.format("lll");
                        }
                    }
                }
            };
            $("#grid-vouchers").bootgrid('destroy');
            ajaxGet(url = "/api/captiveportal/voucher/listVouchers/" + voucher_provider + "/" + voucher_group + "/",
                    sendData = {}, callback = function (data, status) {
                        if (status == "success") {
                            $("#grid-vouchers > tbody").html('');
                            $.each(data, function (key, value) {
                                var fields = ["username", "starttime", "endtime", "state"];
                                tr_str = '<tr>';
                                for (var i = 0; i < fields.length; i++) {
                                    if (value[fields[i]] != null) {
                                        tr_str += '<td>' + value[fields[i]] + '</td>';
                                    } else {
                                        tr_str += '<td></td>';
                                    }
                                }
                                tr_str += '</tr>';
                                $("#grid-vouchers > tbody").append(tr_str);
                            });
                        }
                        var grid_clients = $("#grid-vouchers").bootgrid(gridopt);

                    }
            );
        }

        /**
         * link voucher delete button
         */
        $("#deleteVoucherGroup").click(function(){
            var voucher_group = $('#voucher-groups').find("option:selected").val();
            var voucher_provider = $('#voucher-providers').find("option:selected").val();
            if (voucher_group != undefined) {
                BootstrapDialog.show({
                    title: '{{ lang._('Remove voucher group') }} "' + voucher_group + '" @ ' + voucher_provider,
                    message: '{{ lang._('All vouchers within this group will be deleted') }}',
                    buttons: [{
                        icon: 'fa fa-trash-o',
                        label: '{{ lang._('Yes') }}',
                        cssClass: 'btn-primary',
                        action: function(dlg){
                            ajaxCall(url="/api/captiveportal/voucher/dropVoucherGroup/" + voucher_provider + "/" + voucher_group + '/',
                                    sendData={}, callback=function(data,status){
                                        // reload grid after delete
                                        updateVoucherGroupList();
                                    });
                            dlg.close();
                        }
                    }, {
                        label: 'Close',
                        action: function(dlg){
                            dlg.close();
                        }
                    }]
                });
            }
        });


        /**
         * link create vouchers button
         */
        $("#showVoucherModal").click(function(){
            $("#voucher-groupname").val(moment().format('YYYYMMDDHHmmss'));
            $("#generateVouchers").modal();
        });

        /**
         * link actual voucher generation in dialog
         */
        $("#generateVoucherBtn").click(function(){
            var voucher_provider = $('#voucher-providers').find("option:selected").val();
            var voucher_validity = $("#voucher-validity").val();
            var voucher_quantity = $("#voucher-quantity").val();
            var voucher_groupname = $("#voucher-groupname").val();

            ajaxCall(url="/api/captiveportal/voucher/generateVouchers/" + voucher_provider + "/",
                    sendData={
                        'count':voucher_quantity,
                        'validity':voucher_validity,
                        'vouchergroup': voucher_groupname
                    }, callback=function(data,status){
                        // convert json to csv data
                        var output_data = 'username,password,vouchergroup,validity\n';
                        $.each(data, function( key, value ) {
                            output_data = output_data.concat('"', value['username'].replace(/"/g, '""'), '",');
                            output_data = output_data.concat('"', value['password'].replace(/"/g, '""'), '",');
                            output_data = output_data.concat('"', value['vouchergroup'].replace(/"/g, '""'), '",');
                            output_data = output_data.concat('"', value['validity'].replace(/"/g, '""'), '"\n');
                        });

                        // generate download link and send data to the client
                        $('<a></a>')
                                .attr('id','downloadFile')
                                .attr('href','data:text/csv;charset=utf8,' + encodeURIComponent(output_data))
                                .attr('download','vouchers.csv')
                                .appendTo('body');

                        $('#downloadFile').ready(function() {
                            $('#downloadFile').get(0).click();
                        });

                        $("#generateVouchers").modal('hide')

                        // reload grid after creating new vouchers
                        updateVoucherGroupList();
                    });

        });

        updateVoucherProviders();
        $('.selectpicker').selectpicker('refresh');
    });


</script>

<div class="content-box">
    <div class="content-box-main">
        <div class="table-responsive">
            <div class="col-sm-12">
                <div class="pull-right">
                    <select id="voucher-providers" class="selectpicker" data-width="200px"></select>
                    <select id="voucher-groups" class="selectpicker" data-width="200px"></select>
                    <button id="deleteVoucherGroup" type="button" class="btn btn-xs btn-default">
                        <span class="fa fa-trash-o fa-2x"></span>
                    </button>
                    <hr/>
                </div>
            </div>
            <div>
                <table id="grid-vouchers" class="table table-condensed table-hover table-striped table-responsive">
                    <thead>
                    <tr>
                        <th data-column-id="username" data-type="string" data-identifier="true">{{ lang._('Voucher') }}</th>
                        <th data-column-id="starttime" data-type="datetime">{{ lang._('Valid from') }}</th>
                        <th data-column-id="endtime" data-type="datetime">{{ lang._('Valid to') }}</th>
                        <th data-column-id="state" data-type="string">{{ lang._('State') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    </tbody>
                    <tfoot>
                    </tfoot>
                </table>
            </div>
            <div  class="col-sm-12">
                <div class="row">
                    <div class="col-sm-12">
                        <div class="pull-right">
                            <button id="showVoucherModal" type="button" class="btn btn-default">
                                <span>{{ lang._('Create vouchers') }}</span>
                                <span class="fa fa-ticket"></span>
                            </button>
                        </div>
                    </div>
                </div>
                <hr/>
            </div>
        </div>
    </div>
</div>

<!-- Dialog to render new vouchers -->
<div id="generateVouchers" class="modal fade" role="dialog">
    <div class="modal-dialog">

        <!-- Modal content-->
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">{{ lang._('Generate vouchers') }}</h4>
            </div>
            <div class="modal-body">
                <table class="table table-striped table-condensed table-responsive">
                    <thead>
                        <tr>
                            <td>{{ lang._('Validity') }}</td>
                            <td>{{ lang._('Number of vouchers') }}</td>
                            <td>{{ lang._('Groupname') }}</td>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <select id="voucher-validity" class="selectpicker" data-width="200px">
                                    <option value="14400">{{ lang._('4 hours') }}</option>
                                    <option value="28800">{{ lang._('8 hours') }}</option>
                                    <option value="86400">{{ lang._('1 day') }}</option>
                                    <option value="172800">{{ lang._('2 days') }}</option>
                                    <option value="259200">{{ lang._('3 days') }}</option>
                                    <option value="345600">{{ lang._('4 days') }}</option>
                                    <option value="432000">{{ lang._('5 days') }}</option>
                                    <option value="518400">{{ lang._('6 days') }}</option>
                                    <option value="604800">{{ lang._('1 week') }}</option>
                                    <option value="1209600">{{ lang._('2 weeks') }}</option>
                                </select>
                            </td>
                            <td>
                                <select id="voucher-quantity" class="selectpicker" data-width="200px">
                                    <option value="1">1</option>
                                    <option value="5">5</option>
                                    <option value="10">10</option>
                                    <option value="25">25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                    <option value="250">250</option>
                                </select>
                            </td>
                            <td>
                                <input id="voucher-groupname" type="text">
                            </td>
                        </tr>
                    </tbody>
                </table>

            </div>
            <div class="modal-footer">
                <button id="generateVoucherBtn" type="button" class="btn btn-primary">{{ lang._('Generate') }}</button>
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ lang._('Close') }}</button>
            </div>
        </div>

    </div>
</div>