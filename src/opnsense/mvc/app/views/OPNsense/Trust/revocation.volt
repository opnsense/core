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
        grid_crl = $("#grid-crls").UIBootgrid(
            {
                search: '/api/trust/revocation/searchCrl',
                get: '/api/trust/revocation/getExisting/',
                set: '/api/trust/revocation/setExisting/',
                del: '/api/trust/revocation/delCrl/',
                options: {
                    formatters: {
                        "commands": function (column, row) {
                            ret = "<button type=\"button\" class=\"btn btn-xs btn-default command-exp\" data-row-id=\"" + row.uuid + "\" title=\"{{ lang._('Export CRL') }}\" alt=\"{{ lang._('Export CRL') }}\"><span class=\"glyphicon glyphicon-export\"></span></button>";
                            if (row.InternalBool) {
                                ret += "<button type=\"button\" class=\"btn btn-xs btn-default command-edit-internal\" data-row-id=\"" + row.uuid + "\" title=\"{{ lang._('Edit CRL') }}\" alt=\"{{ lang._('Edit CRL') }}\"><span class=\"fa fa-pencil\"></span></button> ";
                            } else {
                                ret += "<button type=\"button\" class=\"btn btn-xs btn-default command-edit\" data-row-id=\"" + row.uuid + "\" title=\"{{ lang._('Edit CRL') }}\" alt=\"{{ lang._('Edit CRL') }}\"><span class=\"fa fa-pencil\"></span></button> ";
                            }
                            return ret +
                                "<button type=\"button\" class=\"btn btn-xs btn-default command-delete\" data-row-id=\"" + row.uuid + "\" title=\"{{ lang._('Delete CRL') }}\" alt=\"{{ lang._('Delete CRL') }}\"><span class=\"fa fa-trash-o\"></span></button>";
                        },
                        "Name": function (column, row) {
                            return "<span class=\"glyphicon glyphicon-certificate __iconspacer\"></span>" + row[column.id] + "<br>" + row["Purpose"];
                        },
                        "Distinguished": function (column, row) {
                            return row[column.id] + "<br>{{ lang._('Valid From') }}: " + row["startdate"] + "<br>{{ lang._('Valid Until') }}: " + row["enddate"];
                        },
                    }
                }
            }
        );

        grid_crl.on("loaded.rs.jquery.bootgrid", function () {
            grid_crl.find(".command-exp").on("click", function () {
                window.location = "/api/trust/revocation/exp/" + $(this).data("row-id");
            }).end();
            grid_crl.find(".command-edit-internal").on("click", function () {
                window.location = "/ui/trust/revocation/edit/" + $(this).data("row-id");
            }).end();
        });

        var methods = ["Internal", "Existing"];
        for (var i = 0; i < methods.length; i++) {
            (function (method) {
                $("#act" + method).click(function () {
                    var urlMap = {};
                    urlMap['frm_' + method + 'Crl'] = "/api/trust/revocation/get" + method + "/";
                    mapDataToFormUI(urlMap).done(function () {
                        // update selectors
                        formatTokenizersUI();
                        $('.selectpicker').selectpicker('refresh');
                        // clear validation errors (if any)
                        clearFormValidation('frm_' + method + 'Crl');
                    });
                    $('#' + method + 'Crl').modal({backdrop: 'static', keyboard: false});
                    $("#btn_" + method + "Crl_save").unbind('click').click(function () {
                        saveFormToEndpoint(url = "/api/trust/revocation/set" + method, formid = method + 'Crl', callback_ok = function () {
                            $("#" + method + "Crl").modal('hide');
                            $("#grid-crls").bootgrid("reload");
                        }, true);
                    });
                });
            })(methods[i]);
        }
    })
    ;
</script>

<div id="crls">
    <table id="grid-crls" class="table table-condensed table-hover table-striped table-responsive"
           data-editDialog="ExistingCrl">
        <thead>
        <tr>
            <th data-column-id="CA" data-type="string">{{ lang._('CA') }}</th>
            <th data-column-id="Name" data-type="string">{{ lang._('Name') }}</th>
            <th data-column-id="Internal" data-type="string">{{ lang._('Internal') }}</th>
            <th data-column-id="Certificates" data-type="string">{{ lang._('Certificates') }}</th>
            <th data-column-id="InUse" data-type="string">{{ lang._('In Use') }}</th>
            <th data-column-id="commands" data-width="7em" data-formatter="commands"
                data-sortable="false">{{ lang._('Commands') }}</th>
        </tr>
        </thead>
        <tbody>
        </tbody>
        <tfoot>
        </tfoot>
    </table>
</div>
<button class="btn btn-primary" id="actInternal" type="button">
    <b>{{ lang._('Create an internal Certificate Revocation List') }}</b>
</button>
<button class="btn btn-primary" id="actExisting" type="button">
    <b>{{ lang._('Import an existing Certificate Revocation List') }}</b>
</button>
{{ partial("layout_partials/base_dialog",['fields':internalCrl,'id':'InternalCrl','label':lang._('Create an internal Certificate Revocation List')]) }}
{{ partial("layout_partials/base_dialog",['fields':existingCrl,'id':'ExistingCrl','label':lang._('Import an existing Certificate Revocation List')]) }}
