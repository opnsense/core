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
<script src="{{ cache_safe('/ui/js/moment-with-locales.min.js') }}"></script>

<script>

    $( document ).ready(function() {
        /**
         * update zone list
         */
        function updateVoucherProviders() {
            ajaxGet("/api/captiveportal/voucher/listProviders/", {}, function(data, status) {
                if (status == "success") {
                    $('#voucher-providers').html("");
                    $.each(data, function(key, value) {
                        $('#voucher-providers').append($("<option></option>").attr("value", value).text(value));
                    });
                    if ($('#voucher-providers option').length > 0) {
                        // link on change event
                        $('#voucher-providers').on('change', function(){
                            updateVoucherGroupList();
                        });
                        // initial load voucher list
                        updateVoucherGroupList();
                    } else {
                        // A voucher server is needed before we can add vouchers, alert user
                        BootstrapDialog.alert('{{ lang._('Please setup a voucher server first (%sgoto auth servers%s)') | format('<a href="/system_authservers.php">','</a>') }}');
                    }
                }
            });
        }

        /**
         * update voucher group list
         */
        function updateVoucherGroupList() {
            var voucher_provider = $('#voucher-providers').find("option:selected").val();
            ajaxGet("/api/captiveportal/voucher/listVoucherGroups/" + voucher_provider + "/", {}, function(data, status) {
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
                converters: {
                    // convert datetime type fields from unix timestamp to readable format
                    datetime: {
                        from: function (value) {
                            return moment(parseInt(value) * 1000);
                        },
                        to: function (value) {
                            return value == 0 ? "" :  value.format("lll");
                        }
                    }
                }
            };
            $("#grid-vouchers").bootgrid('destroy');
            ajaxGet("/api/captiveportal/voucher/listVouchers/" + voucher_provider + "/" + voucher_group + "/", {},
                function (data, status) {
                    if (status == "success") {
                        $("#grid-vouchers > tbody > tr").remove();
                        $.each(data, function (key, value) {
                            var fields = ["username", "starttime", "endtime", "expirytime", "state"];
                            let tr_str = '<tr>';
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
                    $("#grid-vouchers").bootgrid(gridopt);
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
                    type:BootstrapDialog.TYPE_DANGER,
                    title: '{{ lang._('Remove voucher group') }} "' + voucher_group + '" @ ' + voucher_provider,
                    message: '{{ lang._('All vouchers within this group will be deleted') }}',
                    buttons: [{
                        icon: 'fa fa-trash-o',
                        label: '{{ lang._('Yes') }}',
                        cssClass: 'btn-primary',
                        action: function(dlg){
                            ajaxCall(
                              "/api/captiveportal/voucher/dropVoucherGroup/" + voucher_provider + "/" + voucher_group + '/',
                              {}, function(data,status){
                                  // reload grid after delete
                                  updateVoucherGroupList();
                            });
                            dlg.close();
                        }
                    }, {
                        label: '{{ lang._('Close') }}',
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
            $('#generatevouchererror').html('');
            $('#generatevouchererror').hide();
            var voucher_provider = $('#voucher-providers').find("option:selected").val();
            var voucher_validity = $("#voucher-validity").val();
            var voucher_expirytime = $("#voucher-expiry").val();
            var voucher_quantity = $("#voucher-quantity").val();
            var voucher_groupname = $("#voucher-groupname").val();
            if (!$.isNumeric(voucher_validity) || !$.isNumeric(voucher_quantity) || !$.isNumeric(voucher_expirytime)) {
                // don't try to generate vouchers when validity, expirytime or quantity are invalid
                var error = $('<p />');
                error.text("{{ lang._('The validity, expiry time and the quantity of vouchers must be integers.') }}");
                $('#generatevouchererror').append(error);
                $('#generatevouchererror').show();
                return;
            }
            ajaxCall("/api/captiveportal/voucher/generateVouchers/" + voucher_provider + "/", {
                        'count':voucher_quantity,
                        'validity':voucher_validity,
                        'expirytime':voucher_expirytime,
                        'vouchergroup':voucher_groupname
                    }, function(data, status){
                        // convert json to csv data
                        var output_data = 'username,password,vouchergroup,expirytime,validity\n';
                        $.each(data, function( key, value ) {
                            output_data = output_data.concat('"', value['username'], '",');
                            output_data = output_data.concat('"', value['password'], '",');
                            output_data = output_data.concat('"', value['vouchergroup'], '",');
                            output_data = output_data.concat('"', value['expirytime'], '",');
                            output_data = output_data.concat('"', value['validity'], '"\n');
                        });

                        // generate download link and send data to the client
                        if ($('#downloadFile').length) {
                            // remove previous link
                            $('#downloadFile').remove();
                        }

                        $('<a></a>')
                                .attr('id','downloadFile')
                                .attr('href','data:text/csv;charset=utf8,' + encodeURIComponent(output_data))
                                .attr('download',voucher_groupname.toLowerCase() + '.csv')
                                .appendTo('body');

                        $('#downloadFile').ready(function() {
                            if ( window.navigator.msSaveOrOpenBlob && window.Blob ) {
                                var blob = new Blob( [ output_data ], { type: "text/csv" } );
                                navigator.msSaveOrOpenBlob( blob, voucher_groupname.toLowerCase() + '.csv' );
                            } else {
                                $('#downloadFile').get(0).click();
                            }
                        });

                        $("#generateVouchers").modal('hide');

                        // reload grid after creating new vouchers
                        updateVoucherGroupList();

                    });

        });

        $("#dropExpired").click(function(){
            var voucher_group = $('#voucher-groups').find("option:selected").val();
            var voucher_provider = $('#voucher-providers').find("option:selected").val();
            if (voucher_group != undefined) {
                BootstrapDialog.show({
                    type:BootstrapDialog.TYPE_DANGER,
                    title: '{{ lang._('Remove expired vouchers') }} "' + voucher_group + '" @ ' + voucher_provider,
                    message: '{{ lang._('All expired vouchers within this group will be deleted') }}',
                    buttons: [{
                        icon: 'fa fa-trash-o',
                        label: '{{ lang._('Yes') }}',
                        cssClass: 'btn-primary',
                        action: function(dlg){
                            ajaxCall("/api/captiveportal/voucher/dropExpiredVouchers/" + voucher_provider + "/" + voucher_group + '/',
                                    {}, function(data,status){
                                        // reload grid after delete
                                        updateVoucherGroupList();
                                    });
                            dlg.close();
                        }
                    }, {
                        label: '{{ lang._('Close') }}',
                        action: function(dlg){
                            dlg.close();
                        }
                    }]
                });
            }
        });

        /**
         * Expire selected vouchers
         */
        $("#expireVouchers").click(function(){
            var voucher_provider = $('#voucher-providers').find("option:selected").val();
            BootstrapDialog.show({
                type:BootstrapDialog.TYPE_DANGER,
                title: voucher_provider,
                message: '{{ lang._('Expire all selected vouchers?') }}',
                buttons: [{
                    icon: 'fa fa-trash-o',
                    label: '{{ lang._('Yes') }}',
                    cssClass: 'btn-primary',
                    action: function(dlg){
                        var rows =$("#grid-vouchers").bootgrid('getSelectedRows');
                        if (rows != undefined) {
                            var deferreds = [];
                            $.each(rows, function (key, username) {
                                deferreds.push(
                                  ajaxCall("/api/captiveportal/voucher/expireVoucher/" + voucher_provider + "/",
                                      {username:username}, null
                                  ));
                            });
                            $.when.apply(null, deferreds).done(function(){
                                updateVoucherGroupList();
                            });
                        }
                        dlg.close();
                    }
                }, {
                    label: '{{ lang._('Close') }}',
                    action: function(dlg){
                        dlg.close();
                    }
                }]
            });
        });

        $("#voucher-validity").change(function(){
            if ($(this).children(":selected").attr("id") == 'voucher-validity-custom') {
                $("#voucher-validity-custom-data").show();
            } else {
                $("#voucher-validity-custom-data").hide();
            }
        });
        $("#voucher-validity-custom-data").keyup(function(){
            $("#voucher-validity-custom").val($(this).val()*60);
        });
        $("#voucher-expiry").change(function(){
            if ($(this).children(":selected").attr("id") == 'voucher-expiry-custom') {
                $("#voucher-expiry-custom-data").show();
            } else {
                $("#voucher-expiry-custom-data").hide();
            }
        });
        $("#voucher-expiry-custom-data").keyup(function(){
            $("#voucher-expiry-custom").val($(this).val()*3600);
        });

        $("#voucher-quantity").change(function(){
            if ($(this).children(":selected").attr("id") == 'voucher-quantity-custom') {
                $("#voucher-quantity-custom-data").show();
            } else {
                $("#voucher-quantity-custom-data").hide();
            }
        });
        $("#voucher-quantity-custom-data").keyup(function(){
            $("#voucher-quantity-custom").val($(this).val());
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
            <div  class="col-sm-12">
                <table id="grid-vouchers" class="table table-condensed table-hover table-striped table-responsive">
                    <thead>
                    <tr>
                        <th data-column-id="username" data-type="string" data-identifier="true">{{ lang._('Voucher') }}</th>
                        <th data-column-id="starttime" data-type="datetime">{{ lang._('Valid from') }}</th>
                        <th data-column-id="endtime" data-type="datetime">{{ lang._('Valid to') }}</th>
                        <th data-column-id="expirytime" data-type="datetime">{{ lang._('Expires at') }}</th>
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

                            <button id="expireVouchers" type="button" class="btn btn-default">
                                <span>{{ lang._('Expire selected vouchers') }}</span>
                                <span class="fa fa-trash"></span>
                            </button>
                            <button id="dropExpired" type="button" class="btn btn-default">
                                <span>{{ lang._('Drop expired vouchers') }}</span>
                                <span class="fa fa-trash"></span>
                            </button>

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
            <div id="generatevouchererror" class="alert alert-danger" role="alert" style="display: none"></div>
                <table class="table table-striped table-condensed table-responsive">
                    <thead>
                        <tr>
                            <th>{{ lang._('Setting') }}</th>
                            <th>{{ lang._('Value') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{ lang._('Validity') }}</td>
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
                                    <option id="voucher-validity-custom" value="">{{ lang._('Custom (minutes)') }}</option>
                                </select>
                                <input type="text" id="voucher-validity-custom-data" style="display:none;">
                            </td>
                        </tr>
                        <tr>
                            <td>{{ lang._('Expires in') }}</td>
                            <td>
                                <select id="voucher-expiry" class="selectpicker" data-width="200px">
                                    <option value="0">{{ lang._('never') }}</option>
                                    <option value="21600">{{ lang._('6 hours') }}</option>
                                    <option value="43200">{{ lang._('12 hours') }}</option>
                                    <option value="86400">{{ lang._('1 day') }}</option>
                                    <option value="172800">{{ lang._('2 days') }}</option>
                                    <option value="259200">{{ lang._('3 days') }}</option>
                                    <option value="345600">{{ lang._('4 days') }}</option>
                                    <option value="432000">{{ lang._('5 days') }}</option>
                                    <option value="518400">{{ lang._('6 days') }}</option>
                                    <option value="604800">{{ lang._('1 week') }}</option>
                                    <option value="1209600">{{ lang._('2 weeks') }}</option>
                                    <option value="1814400">{{ lang._('3 weeks') }}</option>
                                    <option value="2419200">{{ lang._('1 month') }}</option>
                                    <option value="4838400">{{ lang._('2 months') }}</option>
                                    <option value="7257600">{{ lang._('3 months') }}</option>
                                    <option id="voucher-expiry-custom" value="">{{ lang._('Custom (hours)') }}</option>
                                </select>
                                <input type="text" id="voucher-expiry-custom-data" style="display:none;">
                            </td>
                        </tr>
                        <tr>
                            <td>{{ lang._('Number of vouchers') }}</td>
                            <td>
                                <select id="voucher-quantity" class="selectpicker" data-width="200px">
                                    <option value="1">1</option>
                                    <option value="5">5</option>
                                    <option value="10">10</option>
                                    <option value="25">25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                    <option value="250">250</option>
                                    <option id="voucher-quantity-custom" value="">{{ lang._('Custom') }}</option>
                                </select>
                                <input type="text" id="voucher-quantity-custom-data" style="display:none;">
                            </td>
                        </tr>
                        <tr>
                            <td>{{ lang._('Groupname') }}</td>
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
