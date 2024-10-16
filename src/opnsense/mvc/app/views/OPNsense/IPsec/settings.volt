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

<div class="content-box tab-content">
    <div class="col-md-12">
        <br/>
        <button class="btn btn-primary" id="reconfigureAct"
                data-endpoint='/api/ipsec/service/reconfigure'
                data-label="{{ lang._('Apply') }}"
                data-error-title="{{ lang._('Error reconfiguring IPsec') }}"
                type="button"
        ></button>
        <button class="btn btn-primary" id="downloadConfig" style="display: none;">
            {{ lang._('Download') }}
        </button>
        <br/><br/>
    </div>
</div>
