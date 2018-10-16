{#

Copyright (c) 2018 Deciso B.V.
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

1.  Redistributions of source code must retain the above copyright notice,
this list of conditions and the following disclaimer.

2.  Redistributions in binary form must reproduce the above copyright notice,
this list of conditions and the following disclaimer in the documentation
and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
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

<script>

    $( document ).ready(function() {
        /**
         * load providers and templates
         */
        ajaxGet('/api/openvpn/export/providers/', {}, function(data, status){
            if (status == 'success') {
                $.each(data, function (idx, record) {
                    $("#openvpn_export\\.servers").append(
                        $("<option/>").val(record.vpnid)
                            .text(record.name)
                            .data('hostname', record.hostname)
                            .data('local_port', record.local_port)
                            .data('template', record.template)
                    );
                });
                $("#openvpn_export\\.servers").selectpicker('refresh');
                $("#openvpn_export\\.servers").change();
            }
            ajaxGet('/api/openvpn/export/templates/',  {}, function(data, status){
                if (status == 'success') {
                    var selected_provider = $("#openvpn_export\\.servers").find('option:selected');
                    $.each(data, function (idx, record) {
                        var this_opt = $("<option/>").val(idx)
                            .text(record.name)
                            .data('options', record.supportedOptions);

                        if (selected_provider.data('template') == idx) {
                            this_opt.attr('selected', 'selected');
                        }
                        $("#openvpn_export\\.template").append(this_opt);
                    });
                    $("#openvpn_export\\.template").selectpicker('refresh');
                    $("#openvpn_export\\.template").change();
                }
            });
        });

        /**
         * Template / type selection
         */
        $("#openvpn_export\\.template").change(function () {
            $(".export_option").closest('tr').hide();
            var selected_options = $(this).find('option:selected').data('options');
            for (var i=0; i < selected_options.length; ++i) {
                $("#row_openvpn_export\\."+selected_options[i]).show();
            }

        });

        /**
         * server change, drives account select and download logic
         */
        $("#openvpn_export\\.servers").change(function () {
            var selected_opt = $(this).find('option:selected');
            $("#openvpn_export\\.hostname").val(selected_opt.data('hostname'));
            $("#openvpn_export\\.local_port").val(selected_opt.data('local_port'));
            ajaxGet('/api/openvpn/export/accounts/' +  $(this).val(), {}, function(data, status){
                $("#accounts_table > tbody").empty();
                if (status == 'success') {
                    $.each(data, function (idx, record) {
                        $("#accounts_table > tbody").append(
                            $("<tr/>").append(
                                $("<td/>").text(record.description)
                            ).append(
                                $("<td/>").text(record.users.join(','))
                            ).append(
                                $("<td/>").append(
                                    $('<button class="btn btn-xs act_download"><i class="fa fa-cloud-download"></i></button>')
                                        .data('certref', idx)
                                )
                            )
                        );
                    });
                    // attach download buttons
                    $(".act_download").click(function(){
                        var caref = $(this).data('certref');
                        var vpnid = $("#openvpn_export\\.servers").find('option:selected').val();
                        saveFormToEndpoint("/api/openvpn/export/download/"+vpnid+"/"+caref+"/",'frm_ExportSettings', function(data){
                            // TODO: error handling + download to client when successful
                            console.log(data);
                        });
                    });
                }
            });

            //
        });
    });


</script>

<div class="content-box">
    {{ partial("layout_partials/base_form",['fields':exportForm,'id':'frm_ExportSettings'])}}
    <br/>
    <div class="table-responsive">
        <table class="table table-striped table-condensed table-responsive table-hover" id="accounts_table">
            <thead>
                <tr>
                    <th colspan="3">{{ lang._('Accounts / certificates')}}</th>
                </tr>
                <tr>
                    <th>{{ lang._('Certificate')}}</th>
                    <th>{{ lang._('Linked user(s)')}}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
</div>
