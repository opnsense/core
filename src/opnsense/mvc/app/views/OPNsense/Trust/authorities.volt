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
        grid = $("#grid-cas").UIBootgrid(
            {
                search: '/api/trust/authorities/search',
                del: '/api/trust/authorities/del/',
                get: '/api/trust/authorities/getExisting/',
                set: '/api/trust/authorities/setExisting/',
                options: {
                    formatters: {
                        "commands": function (column, row) {
                            return "<button type=\"button\" class=\"btn btn-xs btn-default command-edit\" data-row-id=\"" + row.uuid + "\" title=\"{{ lang._('edit CA') }}\" alt=\"{{ lang._('edit CA') }}\"><span class=\"fa fa-pencil\"></span></button> " +
                                "<button type=\"button\" class=\"btn btn-xs btn-default command-exp\" data-type=\"crt\" data-row-id=\"" + row.uuid + "\" title=\"{{ lang._('export CA cert') }}\" alt=\"{{ lang._('export CA cert') }}\"><span class=\"glyphicon glyphicon-download\"></span></button>" +
                                "<button type=\"button\" class=\"btn btn-xs btn-default command-exp\" data-type=\"key\" data-row-id=\"" + row.uuid + "\" title=\"{{ lang._('export CA private key') }}\" alt=\"{{ lang._('export CA private key') }}\"><span class=\"glyphicon glyphicon-download\"></span></button>" +
                                "<button type=\"button\" class=\"btn btn-xs btn-default command-delete\" data-row-id=\"" + row.uuid + "\" title=\"{{ lang._('delete ca') }}\" alt=\"{{ lang._('delete ca') }}\"><span class=\"fa fa-trash-o\"></span></button>";
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
                window.location = "/api/trust/authorities/exp/" + $(this).data("row-id") + "/" + $(this).data("type");
            }).end();
        });

        var methods = ["Existing", "Internal", "Intermediate"];
        for (var i = 0; i < methods.length; i++) {
            (function (method) {
                $("#act" + method).click(function () {
                    var urlMap = {};
                    urlMap['frm_' + method + 'CA'] = "/api/trust/authorities/get" + method + "/";
                    mapDataToFormUI(urlMap).done(function () {
                        // update selectors
                        formatTokenizersUI();
                        $('.selectpicker').selectpicker('refresh');
                        // clear validation errors (if any)
                        clearFormValidation('frm_' + method + 'CA');
                    });
                    $('#' + method + 'CA').modal({backdrop: 'static', keyboard: false});
                    $("#btn_" + method + "CA_save").unbind('click').click(function () {
                        saveFormToEndpoint(url = "/api/trust/authorities/set" + method, formid = method + 'CA', callback_ok = function () {
                            $("#" + method + "CA").modal('hide');
                            $("#grid-cas").bootgrid("reload");
                        }, true);
                    });
                });
            })(methods[i]);
        }
    })
    ;
</script>

<div class="alert alert-info hidden" role="alert" id="responseMsg">
</div>

<table id="grid-cas" class="table table-condensed table-hover table-striped table-responsive"
       data-editDialog="ExistingCA">
    <thead>
    <tr>
        <th data-column-id="Name" data-type="string" data-width="10%">{{ lang._('Name') }}</th>
        <th data-column-id="Internal" data-width="10%">{{ lang._('Internal') }}</th>
        <th data-column-id="Issuer" data-type="string" data-width="10%">{{ lang._('Issuer') }}</th>
        <th data-column-id="Certificates" data-type="string" data-width="10%">{{ lang._('Certificates') }}</th>
        <th data-column-id="Distinguished" data-type="string"
            data-formatter="Distinguished">{{ lang._('Distinguished Name') }}</th>
        <th data-column-id="commands" data-width="7em" data-formatter="commands"
            data-sortable="false">{{ lang._('Commands') }}</th>
    </tr>
    </thead>
    <tbody>
    </tbody>
    <tfoot>
    </tfoot>
</table>
<button class="btn btn-primary" id="actExisting" type="button">
    <b>{{ lang._('Import an existing Certificate Authority') }}</b>
</button>
<button class="btn btn-primary" id="actInternal" type="button">
    <b>{{ lang._('Create an internal Certificate Authority') }}</b>
</button>
<button class="btn btn-primary" id="actIntermediate" type="button">
    <b>{{ lang._('Create an intermediate Certificate Authority') }}</b>
</button>

{{ partial("layout_partials/base_dialog",['fields':existingCA,'id':'ExistingCA','label':lang._('Import an existing Certificate Authority')]) }}
{{ partial("layout_partials/base_dialog",['fields':internalCA,'id':'InternalCA','label':lang._('Create an internal Certificate Authority')]) }}
{{ partial("layout_partials/base_dialog",['fields':intermediateCA,'id':'IntermediateCA','label':lang._('Create an intermediate Certificate Authority')]) }}
