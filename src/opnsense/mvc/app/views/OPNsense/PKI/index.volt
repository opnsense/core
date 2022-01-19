{#
Copyright (C) 2022 Manuel Faux
Copyright (C) 2014-2015 Deciso B.V.
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

<div class="alert alert-info hidden" role="alert" id="responseMsg"></div>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#authorities">{{ lang._('Authorities') }}</a></li>
    <li><a data-toggle="tab" href="#certificates">{{ lang._('Certificates') }}</a></li>
</ul>

<div class="tab-content content-box">

    <!-- tab page "authorities" -->
    <div id="authorities" class="tab-pane fade in active">
        <table id="grid-authorities" class="table table-condensed table-hover table-striped table-responsive">
            <thead>
                <tr>
                    <th data-column-id="refid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('UUID') }}</th>
                    <th data-column-id="id" data-type="string" data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                    <th data-column-id="internal" data-type="string" data-formatter="boolean" data-width="6em">{{ lang._('Internal') }}</th>
                    <th data-column-id="issuer" data-type="string" data-formatter="issuer">{{ lang._('Issuer') }}</th>
                    <th data-column-id="certificate_count" data-type="string" data-width="7em">{{ lang._('Certificates') }}</th>
                    <th data-column-id="subject" data-type="string" data-formatter="dn">{{ lang._('Distinguished Name') }}</th>
                    <th data-column-id="valid_from" data-type="string" data-formatter="datetime">{{ lang._('Valid From') }}</th>
                    <th data-column-id="valid_until" data-type="string" data-formatter="datetime">{{ lang._('Valid Until') }}</th>
                    <th data-column-id="commands" data-width="11em" data-formatter="authorityCommands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td>
                        <button data-action="add" type="button" class="btn btn-xs btn-primary legacy_action command-add"><span class="fa fa-fw fa-plus"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>

        <table id="grid-revocations" class="table table-condensed table-hover table-striped table-responsive">
            <thead>
                <tr>
                    <th data-column-id="refid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('UUID') }}</th>
                    <th data-column-id="id" data-type="string" data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                    <th data-column-id="internal" data-type="string" data-formatter="boolean" data-width="6em">{{ lang._('Internal') }}</th>
                    <th data-column-id="certificate_count" data-type="string" data-width="7em">{{ lang._('Certificates') }}</th>
                    <th data-column-id="used" data-type="string" data-formatter="boolean" data-width="6em">{{ lang._('Used') }}</th>
                    <th data-column-id="commands" data-width="11em" data-formatter="revocationCommands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td>
                        <button data-action="add" type="button" class="btn btn-xs btn-primary legacy_action command-add"><span class="fa fa-fw fa-plus"></span></button>
                        <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-fw fa-trash-o"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- tab page "certificates" -->
    <div id="certificates" class="tab-pane">
        <table id="grid-certificates" class="table table-condensed table-hover table-striped table-responsive">
            <thead>
                <tr>
                    <th data-column-id="refid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('UUID') }}</th>
                    <th data-column-id="id" data-type="string" data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                    <th data-column-id="internal" data-type="string" data-formatter="boolean" data-visible="false" data-width="6em">{{ lang._('Private Key') }}</th>
                    <th data-column-id="issuer" data-type="string" data-formatter="issuer">{{ lang._('Issuer') }}</th>
                    <th data-column-id="subject" data-type="string" data-formatter="dn">{{ lang._('Distinguished Name') }}</th>
                    <th data-column-id="purpose" data-type="string" data-formatter="purpose" data-sortable="false">{{ lang._('Purpose') }}</th>
                    <th data-column-id="usage" data-type="string" data-formatter="list">{{ lang._('Usage') }}</th>
                    <th data-column-id="validity" data-type="string" data-formatter="validity">{{ lang._('Valid') }}</th>
                    <th data-column-id="valid_from" data-type="string" data-formatter="datetime" data-visible="false">{{ lang._('Valid From') }}</th>
                    <th data-column-id="valid_until" data-type="string" data-formatter="datetime">{{ lang._('Valid Until') }}</th>
                    <th data-column-id="commands" data-width="12em" data-formatter="certificateCommands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td>
                        <button data-action="add" type="button" class="btn btn-xs btn-primary legacy_action command-add"><span class="fa fa-fw fa-plus"></span></button>
                        <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-fw fa-trash-o"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

</div>

<style>
  .theading-text {
      font-weight: 800;
      font-style: italic;
  }
</style>

<script>
$( document ).ready(function() {
    const formatters = {
        authorityCommands: function(column, row) {
            return `<button type="button" class="btn btn-xs btn-default command-info bootgrid-tooltip" data-row-id="${row.refid}"><span class="fa fa-fw fa-info-circle"></span></button>` +
                `<button type="button" class="btn btn-xs btn-default legacy_action command-edit bootgrid-tooltip" data-row-id="${row.id}"><span class="fa fa-fw fa-pencil"></span></button> ` +
                `<button type="button" class="btn btn-xs btn-default legacy_action command-export bootgrid-tooltip" data-row-id="${row.id}" title="{{ lang._('Export Certificate') }}"><span class="fa fa-fw fa-download"></span></button>` +
                `<button type="button" class="btn btn-xs btn-default${row["internal"] ? "" : " disabled"} legacy_action command-export-keys bootgrid-tooltip" data-row-id="${row.id}" title="{{ lang._('Export Private Key') }}"><span class="fa fa-fw fa-download"></span></button> ` +
                `<button type="button" class="btn btn-xs btn-default command-delete bootgrid-tooltip" data-row-id="${row.refid}"><span class="fa fa-fw fa-trash-o"></span></button>`;
        },
        revocationCommands: function(column, row) {
            return `<button type="button" class="btn btn-xs btn-default legacy_action command-edit bootgrid-tooltip" data-row-refid="${row.refid}"><span class="fa fa-fw fa-pencil"></span></button> ` +
                `<button type="button" class="btn btn-xs btn-default legacy_action command-export bootgrid-tooltip" data-row-refid="${row.refid}" title="{{ lang._('Export Certificate') }}"><span class="fa fa-fw fa-download"></span></button>` +
                `<button type="button" class="btn btn-xs btn-default command-delete bootgrid-tooltip" data-row-id="${row.refid}"><span class="fa fa-fw fa-trash-o"></span></button>`;
        },
        certificateCommands: function(column, row) {
            let commands = `<button type="button" class="btn btn-xs btn-default${!row["csr"] ? "" : " disabled"} command-info bootgrid-tooltip" data-row-id="${row.refid}"><span class="fa fa-fw fa-info-circle"></span></button> ` +
                `<button type="button" class="btn btn-xs btn-default${!row["csr"] ? "" : " disabled"} legacy_action command-export bootgrid-tooltip" data-row-id="${row.id}" title="{{ lang._('Export Certificate') }}"><span class="fa fa-fw fa-download"></span></button>` +
                `<button type="button" class="btn btn-xs btn-default${row["internal"] ? "" : " disabled"} legacy_action command-export-keys bootgrid-tooltip" data-row-id="${row.id}" title="{{ lang._('Export Private Key') }}"><span class="fa fa-fw fa-download"></span></button>` +
                `<button type="button" class="btn btn-xs btn-default${row["internal"] ? "" : " disabled"} legacy_action command-export-pkcs12 bootgrid-tooltip" data-row-id="${row.id}" title="{{ lang._('Export PKCS#12') }}"><span class="fa fa-fw fa-download"></span></button> ` +
                `<button type="button" class="btn btn-xs btn-default${!row["used"] ? "" : " disabled"} command-delete bootgrid-tooltip" data-row-id="${row.refid}"><span class="fa fa-fw fa-trash-o"></span></button>`;
            if (row["csr"]) {
                commands += `<button type="button" class="btn btn-xs btn-default legacy_action command-update-csr bootgrid-tooltip" data-row-id="${row.id}" title="{{ lang._('Update CSR') }}"><span class="fa fa-fw fa-pencil-square-o"></span></button>`;
            }
            return commands;
        },
        boolean: function (column, row) {
            if (parseInt(row[column.id], 2) === 1) {
                return "<span class=\"fa fa-fw fa-check\" data-value=\"1\" data-row-id=\"" + row.id + "\"></span>";
            } else {
                return "<span class=\"fa fa-fw fa-times\" data-value=\"0\" data-row-id=\"" + row.id + "\"></span>";
            }
        },
        datetime: function (column, row) {
            let value = parseInt(row[column.id]);
            if (value > 0) {
                return moment(value * 1000).format("lll");
            } else if (value == 0) {
                return "";
            } else {
                return "{{ lang._('invalid date') }}";
            }
        },
        dn: function (column, row) {
            return row[column.id].replace(/^unknown$/, "<em>{{ lang._('unknown') }}</em>");
            // XXX use line-breaks for better readability?
            // return row[column.id].replace(/, /g, ",<br>").replace(/^unknown$/, "<em>{{ lang._('unknown') }}</em>");
        },
        issuer: function (column, row) {
            if (row[column.id] == "self-signed") {
                return "<em>" + "{{ lang._('self-signed') }}" + "</em>";
            } else if (row[column.id] == "external") {
                return "<em>" + "{{ lang._('external') }}" + "</em>";
            } else if (row[column.id] == "pending") {
                return "<em>" + "{{ lang._('pending CSR') }}" + "</em>";
            } else {
                return row[column.id];
            }
        },
        list: function (column, row) {
            if (Array.isArray(row[column.id])) {
            // XXX use line-breaks for better readability?
                return row[column.id].join(", ");
            } else {
                return row[column.id];
            }
        },
        purpose: function (column, row) {
            let result = [];
            if (row[column.id].ca) {
                result.push("{{ lang._('CA') }}");
            }
            if (row[column.id].client) {
                result.push("{{ lang._('Client') }}");
            }
            if (row[column.id].server) {
                result.push("{{ lang._('Server') }}");
            }
            // XXX use line-breaks for better readability?
            return result.join(", ");
        },
        validity: function (column, row) {
            const future = moment().isBefore(row["valid_from"] * 1000);
            const expired = moment().isAfter(row["valid_until"] * 1000);
            const revoked = parseInt(row["revoked"], 2) !== 0;
            const is_csr = parseInt(row["csr"], 2) !== 0;
            if (is_csr) {
                return "<span class=\"fa fa-fw fa-clock-o\" data-value=\"0\" data-row-id=\"" + row.id + "\"></span> {{ lang._('Pending CSR') }}";
            } else if (future) {
                return "<span class=\"fa fa-fw fa-clock-o\" data-value=\"0\" data-row-id=\"" + row.id + "\"></span> {{ lang._('Future Validity') }}";
            } else if (expired) {
                return "<span class=\"fa fa-fw fa-times\" data-value=\"0\" data-row-id=\"" + row.id + "\"></span> {{ lang._('Expired') }}";
            } else if (revoked) {
                return "<span class=\"fa fa-fw fa-times\" data-value=\"0\" data-row-id=\"" + row.id + "\"></span> {{ lang._('Revoked') }}";
            }
            // !is_csr && !future && !expire && !revoked
            return "<span class=\"fa fa-fw fa-check\" data-value=\"1\" data-row-id=\"" + row.id + "\"></span>";
        }
    };

    // authorities grid
    const authorities_grid = $("#grid-authorities").UIBootgrid({
        search:'/api/pki/certificate/searchAuthority/',
        del:'/api/pki/certificate/delAuthority/',
        info: '/api/pki/certificate/infoAuthority/',
        get: '/api/pki/certificate/infoAuthority/',
        options: {
            formatters: formatters,
            multiSelect: false,
            rowSelect: true,
            selection: true
        }
    }).on("selected.rs.jquery.bootgrid", function(e, rows) {
        $("#grid-revocations").bootgrid('reload');
    }).on("deselected.rs.jquery.bootgrid", function(e, rows) {
        $("#grid-revocations").bootgrid('reload');
    }).on('loaded.rs.jquery.bootgrid', function() {
        let ids = $("#grid-authorities").bootgrid("getCurrentRows");
        if (ids.length > 0) {
            $("#grid-authorities").bootgrid('select', [ids[0].id]);
        }
        $("#grid-revocations").bootgrid('reload');

        $("#grid-authorities").find(".legacy_action").unbind('click').click(function(e){
            e.stopPropagation();
            if ($(this).hasClass('command-add')) {
                window.location = '/system_camanager.php?act=new';
            } else if ($(this).hasClass('command-edit')) {
                window.location = '/system_camanager.php?act=edit&id=' + $(this).data('row-id');
            } else if ($(this).hasClass('command-export')) {
                window.location = '/system_camanager.php?act=exp&id=' + $(this).data('row-id');
            } else if ($(this).hasClass('command-export-keys')) {
                window.location = '/system_camanager.php?act=expkey&id=' + $(this).data('row-id');
            }
        });
    });

    // Revocations grid
    const revocations_grid = $("#grid-revocations").UIBootgrid({
        search:'/api/pki/certificate/searchRevocation/',
        del:'/api/pki/certificate/delRevocation/',
        options: {
            formatters: formatters,
            useRequestHandlerOnGet: true,
            requestHandler: function(request) {
                let ids = $("#grid-authorities").bootgrid("getSelectedRows");
                request['caref'] = ids.length > 0 ? ids[0] : "__not_found__";
                return request;
            }
        }
    }).on('loaded.rs.jquery.bootgrid', function() {
        let ids = $("#grid-authorities").bootgrid("getSelectedRows");
        $("#grid-revocations button[data-action=\"add\"]").prop("disabled", ids.length == 0);
        $("#grid-revocations button[data-action=\"deleteSelected\"]").prop("disabled", ids.length == 0);

        $("#grid-revocations").find(".legacy_action").unbind('click').click(function(e){
            e.stopPropagation();
            if ($(this).hasClass('command-add')) {
                let ids = $("#grid-authorities").bootgrid("getSelectedRows");
                window.location = '/system_crlmanager.php?act=new&caref=' + (ids.length > 0 ? ids[0] : "");
            } else if ($(this).hasClass('command-edit')) {
                window.location = '/system_crlmanager.php?act=edit&id=' + $(this).data('row-refid');
            } else if ($(this).hasClass('command-export')) {
                window.location = '/system_crlmanager.php?act=exp&id=' + $(this).data('row-refid');
            }
        });
    });

    // reformat bootgrid headers to show type of content (phase 1 or 2)
    $("div.actionBar").each(function(){
        let heading_text = "";
        if ($(this).closest(".bootgrid-header").attr("id").includes("authorities")) {
            heading_text = "{{ lang._('Authorities') }}";
        } else {
            heading_text = "{{ lang._('Revocations') }}";
        }
        $(this).parent().prepend($('<td class="col-sm-2 theading-text">'+heading_text+'</div>'));
        $(this).removeClass("col-sm-12");
        $(this).addClass("col-sm-10");
    });

    // certificates grid
    const certificates_grid = $("#grid-certificates").UIBootgrid({
        search:'/api/pki/certificate/searchCertificate/',
        del:'/api/pki/certificate/delCertificate/',
        info: '/api/pki/certificate/infoCertificate/',
        get: '/api/pki/certificate/infoCertificate/',
        options: {
            formatters: formatters
        }
    }).on('loaded.rs.jquery.bootgrid', function() {
        $("#grid-certificates").find(".legacy_action").unbind('click').click(function (e){
            e.stopPropagation();
            if ($(this).hasClass('command-add')) {
                window.location = '/system_certmanager.php?act=new';
            } else if ($(this).hasClass('command-edit')) {
                window.location = '/system_certmanager.php?act=edit&id=' + $(this).data('row-id');
            } else if ($(this).hasClass('command-export')) {
                window.location = '/system_certmanager.php?act=exp&id=' + $(this).data('row-id');
            } else if ($(this).hasClass('command-export-keys')) {
                window.location = '/system_certmanager.php?act=key&id=' + $(this).data('row-id');
            } else if ($(this).hasClass('command-export-pkcs12')) {
                // e.preventDefault();
                var id = $(this).data('row-id');

                let password_input = $('<input type="password" autocomplete="new-password" class="form-control password_field" placeholder="{{ lang._('Password') }}">');
                let confirm_input = $('<input type="password" autocomplete="new-password" class="form-control password_field" placeholder="{{ lang._('Confirm') }}">');
                let dialog_items = $('<div class = "form-group">');
                dialog_items.append(
                  $("<span>").text("{{ lang._('Optionally use a password to protect your export') }}"),
                  $('<table class="table table-condensed"/>').append(
                    $("<tbody/>").append(
                      $("<tr/>").append($("<td/>").append(password_input)),
                      $("<tr/>").append($("<td/>").append(confirm_input))
                    )
                  )
                );

                // highlight password/confirm when not equal
                let keyup_pass = function() {
                    if (confirm_input.val() !== password_input.val()) {
                        $(".password_field").addClass("has-warning");
                        $(".password_field").closest('div').addClass('has-warning');
                    } else {
                        $(".password_field").removeClass("has-warning");
                        $(".password_field").closest('div').removeClass('has-warning');
                    }
                };
                confirm_input.on('keyup', keyup_pass);
                password_input.on('keyup', keyup_pass);


                BootstrapDialog.show({
                    type:BootstrapDialog.TYPE_INFO,
                    title: "{{ lang._('Certificates') }}",
                    message: dialog_items,
                    buttons: [
                        {
                            label: "{{ lang._('Close') }}",
                            action: function(dialogRef) {
                                dialogRef.close();
                            }
                        }, {
                            label: '<i class="fa fa-download fa-fw"></i> {{ lang._('Download') }}',
                            action: function(dialogRef) {
                                if (confirm_input.val() === password_input.val()) {
                                    $.post('/system_certmanager.php', {'id': id, 'act': 'p12', 'password': password_input.val()}, function (data) {
                                        var link = $('<a></a>')
                                            .attr('href','data:application/octet-stream;base64,' + data.content)
                                            .attr('download', data.filename)
                                            .appendTo('body');
                                        link.ready(function() {
                                            link.get(0).click();
                                            link.empty();
                                        });
                                    });
                                    dialogRef.close();
                                }
                            }
                        }
                    ]
                });
            } else if ($(this).hasClass('command-update-csr')) {
                window.location = '/system_certmanager.php?act=csr&id=' + $(this).data('row-id');
            }
        });
    });

    // update history on tab state and implement navigation
    if(window.location.hash != "") {
        $('a[href="' + window.location.hash + '"]').click()
    }
    $('.nav-tabs a').on('shown.bs.tab', function (e) {
        history.pushState(null, null, e.target.hash);
    });
    $(window).on('hashchange', function(e) {
        $('a[href="' + window.location.hash + '"]').click()
    });
});
</script>
