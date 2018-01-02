{#
Copyright (C) 2017 Smart-Soft

All rights reserved.

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

#}

<script type="text/javascript">
    $(document).ready(function () {
        grid = $("#grid-certs").UIBootgrid(
            {
                search: '/api/trust/certificates/search',
                del: '/api/trust/certificates/del/',
                options: {
                    formatters: {
                        "commands": function (column, row) {
                            ret = row.InUse.replace("\n", "<br>") + "<br>";
                            if (!row.csr) {
                                ret += "<button type=\"button\" class=\"btn btn-xs btn-default command-certinfo\" data-row-id=\"" + row.uuid + "\" title=\"{{ lang._('show certificate info') }}\" alt=\"{{ lang._('show certificate info') }}\"><span class=\"glyphicon glyphicon-info-sign\"></span></button>" +
                                    "<button type=\"button\" class=\"btn btn-xs btn-default command-exp\" data-type=\"crt\" data-row-id=\"" + row.uuid + "\" title=\"{{ lang._('export user cert') }}\" alt=\"{{ lang._('export user cert') }}\"><span class=\"glyphicon glyphicon-download\"></span></button>";
                            }
                            if (row.prv) {
                                ret += "<button type=\"button\" class=\"btn btn-xs btn-default command-exp\" data-type=\"key\" data-row-id=\"" + row.uuid + "\" title=\"{{ lang._('export user key') }}\" alt=\"{{ lang._('export user key') }}\"><span class=\"glyphicon glyphicon-download\"></span></button>";
                                if (!row.csr) {
                                    ret += "<button type=\"button\" class=\"btn btn-xs btn-default command-exp\" data-type=\"p12\" data-row-id=\"" + row.uuid + "\" title=\"{{ lang._('export ca+user cert+user key in .p12 format') }}\" alt=\"{{ lang._('export ca+user cert+user key in .p12 format') }}\"><span class=\"glyphicon glyphicon-download\"></span></button>";
                                }
                            }
                            if (row.InUse === "") {
                                ret += "<button type=\"button\" class=\"btn btn-xs btn-default command-delete\" data-row-id=\"" + row.uuid + "\" title=\"{{ lang._('delete cert') }}\" alt=\"{{ lang._('delete cert') }}\"><span class=\"fa fa-trash-o\"></span></button>";
                            }
                            if (row.csr) {
                                ret += "<button type=\"button\" class=\"btn btn-xs btn-default command-csr\" data-row-id=\"" + row.uuid + "\" title=\"{{ lang._('update csr') }}\" alt=\"{{ lang._('update csr') }}\"><span class=\"glyphicon glyphicon-edit\"></span></button>";
                            }
                            return ret;
                        },
                        "Name": function (column, row) {
                            return "<span class=\"glyphicon glyphicon-certificate __iconspacer\"></span>" + row[column.id] + "<br>" + row["Purpose"];
                        },
                        "Distinguished": function (column, row) {
                            return row[column.id] +
                                "<table width=\"100%\" style=\"font-size: smaller\"> \
                                    <tr> \
                                    <td>&nbsp;</td> \
                                <td width=\"20%\">{{ lang._('Valid From') }}:</td> \
                            <td width=\"70%\">" + row["startdate"] + "</td> \
                            </tr> \
                            <tr> \
                            <td>&nbsp;</td> \
                            <td>{{ lang._('Valid Until') }}:</td> \
                            <td>" + row["enddate"] + "</td> \
                            </tr> \
                            </table>";
                        },
                    }
                }
            }
        );

        grid.on("loaded.rs.jquery.bootgrid", function () {
            grid.find(".command-exp").on("click", function () {
                window.location = "/api/trust/certificates/exp/" + $(this).data("row-id") + "/" + $(this).data("type");
            }).end();
            // info item
            grid.find(".command-certinfo").on("click", function (e) {
                ajaxGet(url = "/api/trust/certificates/info/" + $(this).data("row-id"),
                    sendData = {}, callback = function (data, status) {
                        if (status == 'success') {
                            BootstrapDialog.show({
                                title: "{{ lang._('Certificate') }}",
                                type: BootstrapDialog.TYPE_INFO,
                                message: "<pre>" + data.message + "</pre>",
                                cssClass: 'monospace-dialog'
                            });
                        }
                    });
            }).end();
            grid.find(".command-csr").on("click", function (e) {
                var uuid = $(this).data("row-id");
                var urlMap = {};
                urlMap['frm_csr'] = "/api/trust/certificates/getCsr/" + uuid;
                mapDataToFormUI(urlMap).done(function () {
                    // update selectors
                    formatTokenizersUI();
                    $('.selectpicker').selectpicker('refresh');
                    // clear validation errors (if any)
                    clearFormValidation('frm_csr');

                    // show dialog for pipe edit
                    $('#csr').modal({backdrop: 'static', keyboard: false});
                    // define save action
                    $("#btn_csr_save").unbind('click').click(function () {
                        saveFormToEndpoint(url = "/api/trust/certificates/setCsr/" + uuid,
                            formid = 'csr', callback_ok = function () {
                                $("#csr").modal('hide');
                                std_bootgrid_reload(gridId);
                            }, true);
                    });
                });
            }).end();
        });

        var methods = ["Import", "Internal", "External"];
        for (var i = 0; i < methods.length; i++) {
            (function (method) {
                $("#act" + method).click(function () {
                    var urlMap = {};
                    urlMap['frm_' + method + 'Cert'] = "/api/trust/certificates/get" + method + "/";
                    mapDataToFormUI(urlMap).done(function () {
                        // update selectors
                        formatTokenizersUI();
                        $('.selectpicker').selectpicker('refresh');
                        // clear validation errors (if any)
                        clearFormValidation('frm_' + method + 'Cert');
                    });
                    $('#' + method + 'Cert').modal({backdrop: 'static', keyboard: false});
                    $("#btn_" + method + "Cert_save").unbind('click').click(function () {
                        saveFormToEndpoint(url = "/api/trust/certificates/set" + method, formid = method + 'Cert', callback_ok = function () {
                            $("#" + method + "Cert").modal('hide');
                            $("#grid-certs").bootgrid("reload");
                        }, true);
                    });
                });
            })(methods[i]);
        }
    })
</script>

<div id="certs">
    <table id="grid-certs" class="table table-condensed table-hover table-striped table-responsive">
        <thead>
        <tr>
            <th data-column-id="Name" data-type="string" data-formatter="Name"
                data-width="15%">{{ lang._('Name') }}</th>
            <th data-column-id="Issuer" data-type="string" data-width="15%">{{ lang._('Issuer') }}</th>
            <th data-column-id="Distinguished" data-type="string"
                data-formatter="Distinguished">{{ lang._('Distinguished Name') }}</th>
            <th data-column-id="commands" data-width="15%" data-formatter="commands"
                data-sortable="false">{{ lang._('Commands') }}</th>
        </tr>
        </thead>
        <tbody>
        </tbody>
        <tfoot>
        </tfoot>
    </table>
</div>
<button class="btn btn-primary" id="actImport" type="button"><b>{{ lang._('Import an existing Certificate') }}</b>
</button>
<button class="btn btn-primary" id="actInternal" type="button"><b>{{ lang._('Create an internal Certificate') }}</b>
</button>
<button class="btn btn-primary" id="actExternal" type="button">
    <b>{{ lang._('Create a Certificate Signing Request') }}</b></button>

{{ partial("layout_partials/base_dialog",['fields':importCert,'id':'ImportCert','label':lang._('Import an existing Certificate')]) }}
{{ partial("layout_partials/base_dialog",['fields':internalCert,'id':'InternalCert','label':lang._('Create an internal Certificate')]) }}
{{ partial("layout_partials/base_dialog",['fields':externalCert,'id':'ExternalCert','label':lang._('Create a Certificate Signing Request')]) }}
{{ partial("layout_partials/base_dialog",['fields':csr,'id':'csr','label':lang._('Complete Signing Request')]) }}
