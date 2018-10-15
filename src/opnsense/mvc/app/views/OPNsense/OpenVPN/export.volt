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
         * Provider selection
         */
        $("#openvpn_export\\.servers").change(function () {
            var selected_opt = $(this).find('option:selected');
            $("#openvpn_export\\.hostname").val(selected_opt.data('hostname'));
        });
        ajaxGet('/api/openvpn/export/providers/', {}, function(data, status){
            if (status == 'success') {
                $.each(data, function (idx, record) {
                    $("#openvpn_export\\.servers").append(
                        $("<option/>").val(record.vpnid)
                            .text(record.name)
                            .data('hostname', record.hostname)
                    );
                });
                $("#openvpn_export\\.servers").selectpicker('refresh');
                $("#openvpn_export\\.servers").change();
            }
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
        ajaxGet('/api/openvpn/export/templates/',  {}, function(data, status){
            if (status == 'success') {
                $.each(data, function (idx, record) {
                    $("#openvpn_export\\.template").append(
                        $("<option/>").val(idx)
                            .text(record.name)
                            .data('options', record.supportedOptions)
                    );
                });
                $("#openvpn_export\\.template").selectpicker('refresh');
                $("#openvpn_export\\.template").change();
            }
        });
    });

</script>

<div class="content-box">
    {{ partial("layout_partials/base_form",['fields':exportForm,'id':'frm_ExportSettings'])}}

</div>
