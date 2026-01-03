{#
 # Copyright (c) 2024 Deciso B.V.
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

        $('[id*="save_"]').each(function(){
            $(this).closest('tr').hide();
        });

        mapDataToFormUI({'mainform': '/api/ipsec/settings/get'}).done(function(){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
            updateServiceControlUI('ipsec');
            $("#reconfigureAct").SimpleActionButton({
                onPreAction: function() {
                    const dfObj = new $.Deferred();
                    saveFormToEndpoint("/api/ipsec/settings/set", 'mainform', function(){
                        dfObj.resolve();
                    });
                    return dfObj;
                }
            });
        });

        function showDialogAlert(type, title, message) {
            BootstrapDialog.show({
                type: type,
                title: title,
                message: message,
                buttons: [{
                    label: '{{ lang._('Close') }}',
                    action: function(dialogRef) {
                        dialogRef.close();
                    }
                }]
            });
        }

        function fetchAndDownloadConfig(apiUrl, filename) {
            ajaxGet(apiUrl, null, function(response, status) {
                if (status === "success" && response.status === "success") {
                    const content = response.content;
                    const a_tag = $('<a></a>')
                        .attr('href', 'data:text/plain;charset=utf-8,' + encodeURIComponent(content))
                        .attr('download', filename)
                        .appendTo('body');
                    a_tag[0].click();
                    a_tag.remove();
                } else {
                    showDialogAlert(BootstrapDialog.TYPE_WARNING, "{{ lang._('Download Error') }}",
                    response.message || "{{ lang._('Failed to download the configuration file.') }}");
                }
            }).fail(function(xhr, status, error) {
                showDialogAlert(BootstrapDialog.TYPE_DANGER, "{{ lang._('Download Request Failed') }}", error);
            });
        }

        let warningAcknowledged = false;

        $("#downloadConfig").click(function() {
            const apiUrl = '/api/ipsec/connections/swanctl';
            const timestamp = new Date().toISOString().replace(/[-:]/g, '').replace('T', '-').split('.')[0];
            const filename = "swanctl.conf_" + timestamp + ".txt";

            if (warningAcknowledged) {
                fetchAndDownloadConfig(apiUrl, filename);
            } else {
                BootstrapDialog.show({
                    type: BootstrapDialog.TYPE_WARNING,
                    title: "{{ lang._('Warning') }}",
                    message: "{{ lang._('The file you are about to download may contain sensitive data. Please handle it with care.') }}",
                    buttons: [{
                        label: '{{ lang._('Cancel') }}',
                        action: function(dialogRef) {
                            dialogRef.close();
                        }
                    }, {
                        label: '{{ lang._('Download') }}',
                        cssClass: 'btn-primary',
                        action: function(dialogRef) {
                            dialogRef.close();
                            warningAcknowledged = true;
                            fetchAndDownloadConfig(apiUrl, filename);
                        }
                    }]
                });
            }
        });

        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            const activeTab = $(e.target).attr('href');

            if (activeTab === '#configTab') {
                $('#reconfigureAct').hide();
                $('#downloadConfig').show();
            } else {
                $('#reconfigureAct').show();
                $('#downloadConfig').hide();
            }
        });

        $("#reconfigureAct").after($("#downloadConfig"));
    });
</script>

<ul class="nav nav-tabs" role="tablist" id="maintabs">
    {{ partial("layout_partials/base_tabs_header",['formData':formSettings]) }}
    <li><a data-toggle="tab" href="#configTab" role="tab">{{ lang._('swanctl.conf') }}</a></li>
</ul>

<form id="mainform">
    <div class="content-box tab-content">
        {{ partial("layout_partials/base_tabs_content",['formData':formSettings]) }}
        <div id="configTab" class="tab-pane fade"/>
    </div>
</form>

<button class="btn btn-primary" id="downloadConfig" style="display: none;">
    {{ lang._('Download') }}
</button>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/ipsec/service/reconfigure'}) }}
