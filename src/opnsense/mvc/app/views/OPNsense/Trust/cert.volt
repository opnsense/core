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
    'use strict';

    $( document ).ready(function () {
        let grid_cert = $("#{{formGridCert['table_id']}}").UIBootgrid({
            search:'/api/trust/cert/search/',
            get:'/api/trust/cert/get/',
            add:'/api/trust/cert/add/',
            set:'/api/trust/cert/set/',
            del:'/api/trust/cert/del/',
            options:{
                triggerEditFor: getUrlHash('edit'),
                initialSearchPhrase: getUrlHash('search'),
                requestHandler: function(request){
                    if ( $('#ca_filter').val().length > 0) {
                        request['carefs'] = $('#ca_filter').val();
                    }
                    if ( $('#user_filter').val().length > 0) {
                        request['user'] = $('#user_filter').val();
                    }
                    return request;
                },
                formatters: {
                    in_use: function (column, row) {
                        if (!row.uuid.includes('-')) {
                            return "<span class=\"fa fa-fw fa-sign-out\" data-value=\"1\" data-row-id=\"" + row.uuid + "\"></span>";
                        } else if (row.in_use === '1') {
                            return "<span class=\"fa fa-fw fa-check\" data-value=\"1\" data-row-id=\"" + row.uuid + "\"></span>";
                        } else if (row.is_user === '1') {
                            return "<span class=\"fa fa-fw fa-user-o\" data-value=\"1\" data-row-id=\"" + row.uuid + "\"></span>";
                        } else {
                            return "<span class=\"fa fa-fw fa-times\" data-value=\"0\" data-row-id=\"" + row.uuid + "\"></span>";
                        }
                    },
                    commands: function (column, row) {
                        return (
                            '<button type="button" class="btn btn-xs btn-default command-raw_dump bootgrid-tooltip" data-row-id="' + row.uuid + '" title="{{ lang._('show certificate info') }}"><span class="fa fa-fw fa-info-circle"></span></button> ' +
                            '<button type="button" class="btn btn-xs btn-default command-download bootgrid-tooltip" data-row-id="' + row.uuid + '" title="{{ lang._('Download') }}"><span class="fa fa-fw fa-cloud-download"></span></button> ' +
                            (row.uuid.includes('-') ?
                                '<button type="button" class="btn btn-xs btn-default command-edit bootgrid-tooltip" data-row-id="' + row.uuid + '" title="{{ lang._('Edit') }}"><span class="fa fa-fw fa-pencil"></span></button> ' +
                                '<button type="button" class="btn btn-xs btn-default command-copy bootgrid-tooltip" data-row-id="' + row.uuid + '" title="{{ lang._('Clone') }}"><span class="fa fa-fw fa-clone"></span></button> ' +
                                '<button type="button" class="btn btn-xs btn-default command-delete bootgrid-tooltip" data-row-id="' + row.uuid + '" title="{{ lang._('Delete') }}"><span class="fa fa-fw fa-trash-o"></span></button>'
                                : ''
                            )
                        );
                    },
                }
            },
            commands: {
                raw_dump: {
                    method: function(event){
                        let uuid = $(this).data("row-id") !== undefined ? $(this).data("row-id") : '';
                        ajaxGet('/api/trust/cert/raw_dump/' + uuid, {}, function(data, status){
                            if (data.stdout) {
                                BootstrapDialog.show({
                                    title: "{{ lang._('Certificate info') }}",
                                    type:BootstrapDialog.TYPE_INFO,
                                    message: $("<div/>").text(data.stdout).html(),
                                    cssClass: 'monospace-dialog',
                                });
                            }
                        });
                    },
                    classname: 'fa fa-fw fa-info-circle',
                    title: "{{ lang._('show certificate info') }}",
                    sequence: 10
                },
                download: {
                    method: function(event){
                        let uuid = $(this).data("row-id") !== undefined ? $(this).data("row-id") : '';
                        let $container = $("<div style='height:150px;'/>");
                        let $type = $("<select id='download_type'/>");
                        let $password = $("<input id='download_password' type='password'/>");
                        $type.append($("<option value='csr'/>").text('Certificate Signing Request'));
                        $type.append($("<option value='crt'/>").text('Certificate'));
                        $type.append($("<option value='prv'/>").text('Private key'));
                        $type.append($("<option value='pkcs12' selected=selected/>").text('PKCS #12'));
                        $container.append(
                            $("<div class='form-group'/>").append(
                                $("<label for='download_type'>{{ lang._('File type') }}</label>"),
                                $type
                            )
                        );
                        $container.append(
                            $("<div class='form-group'/>").append(
                                $("<label for='download_password'>{{ lang._('Password') }}</label>"),
                                $password)
                            );
                        $type.change(function(){
                            if ($(this).val() != 'pkcs12') {
                                $password.closest('div').hide();
                            } else {
                                $password.closest('div').show();
                            }
                        });
                        BootstrapDialog.show({
                            title: "{{ lang._('Certificate download') }}",
                            type:BootstrapDialog.TYPE_INFO,
                            message: $container,
                            buttons: [{
                                label: "{{ lang._('Download') }}",
                                action: function(dialogItself){
                                    let params = {};
                                    if ($password.val()) {
                                        params['password'] = $password.val();
                                    }
                                    ajaxCall(
                                        '/api/trust/cert/generate_file/'+uuid+'/'+$type.val(),
                                        params,
                                        function(data, status) {
                                            let payload = null;
                                            let filename = data.descr + '_' ;
                                            let mediatype = 'application/octet-stream';
                                            if (data.payload_b64) {
                                                mediatype += ';base64';
                                                payload = data.payload_b64;
                                                filename += 'cert.p12';
                                            } else if (data.payload) {
                                                payload = data.payload;
                                                filename += $type.val() + '.pem';
                                            }
                                            if (payload !== null) {
                                                download_content(payload, filename, mediatype);
                                            }
                                        }
                                    )
                                    dialogItself.close();
                                }
                            }]
                        });

                    },
                    classname: 'fa fa-fw fa-cloud-download',
                    title: "{{ lang._('Download') }}",
                    sequence: 10
                }
            }
        });
        grid_cert.on("loaded.rs.jquery.bootgrid", function (e){
            // reload categories after grid load
            if ($("#ca_filter > option").length == 0) {
                ajaxGet('/api/trust/cert/ca_list', {}, function(data, status){
                    if (data.rows !== undefined) {
                        for (let i=0; i < data.rows.length ; ++i) {
                            let row = data.rows[i];
                            $("#ca_filter").append($("<option/>").val(row.caref).html(row.descr));
                        }
                        $("#ca_filter").selectpicker('refresh');
                    }
                });
            }
            if ($("#user_filter > option").length == 0) {
                let selected_user = null;
                if (window.location.hash != "") {
                    let tmp = window.location.hash.split('=');
                    if (tmp.length == 2 && tmp[0] == '#user') {
                        selected_user = tmp[1];
                        history.pushState(null, null, '#');
                    }
                }
                ajaxGet('/api/trust/cert/user_list', {}, function(data, status){
                    if (data.rows !== undefined) {
                        for (let i=0; i < data.rows.length ; ++i) {
                            let row = data.rows[i];
                            let opt = $("<option/>").val(row.name).html(row.name);
                            if (selected_user == row.name) {
                                opt.prop('selected', 'selected');
                            }
                            $("#user_filter").append(opt);
                        }
                        $("#user_filter").selectpicker('refresh');
                        if (selected_user) {
                            /* XXX: will re-query, ignore the glitch for now. */
                            $("#{{formGridCert['table_id']}}").bootgrid('reload');
                        }
                    }
                });
            }
        });
        /**
         * register handler to download private key on save
         */
        $(document).ajaxComplete(function(event,request, settings){
            if (settings.url.startsWith('/api/trust/cert/add') && request.responseJSON && request.responseJSON.private_key) {
                download_content(request.responseJSON.private_key, 'key.pem', 'application/octet-stream');
            }
        });

        $("#filter_container").detach().insertAfter('#{{formGridCert['table_id']}}-header .search');
        $(".cert_filter").change(function(){
            $("#{{formGridCert['table_id']}}").bootgrid('reload');
        });

        /**
        * Autofill certificate fields when choosing a different CA
        */
        $("#cert\\.caref").change(function(event){
            if (event.originalEvent !== undefined) {
                // not called on form open, only when the user chooses a new ca
                ajaxGet('/api/trust/cert/ca_info/' + $(this).val(), {}, function(data, status){
                    let fields = ['city', 'state', 'country', 'name', 'email', 'organization', 'ocsp_uri'];
                    if (data.name !== undefined) {
                        fields.forEach(function(field){
                            if (data[field]) {
                                $("#cert\\." + field).val(data[field]);
                            }
                        });
                    } else {
                        fields.forEach(function(field){
                            $("#cert\\." + field).val('');
                        });
                    }

                    $("#cert\\.country").selectpicker('refresh');
                });
            }
        });

        $("#cert\\.action").change(function(event){
            if (event.originalEvent === undefined) {
                // lock valid options based on server offered action
                let visible_options = [$(this).val()];
                if ($(this).val() == 'internal') {
                    visible_options.push('internal');
                    visible_options.push('external');
                    visible_options.push('import');
                    visible_options.push('sign_csr');
                } else if ($(this).val() == 'reissue') {
                    visible_options.push('external');
                } else if ($(this).val() == 'sign_csr') {
                    visible_options.push('manual');
                }

                $("#cert\\.action option").each(function(){
                    if (visible_options.includes($(this).val())) {
                        $(this).attr('disabled', null);
                    } else {
                        $(this).attr('disabled', 'disabled');
                    }
                });
            }

            let this_action = $(this).val();
            $(".action").each(function(){
                let target = null;
                if ($(this)[0].tagName == 'DIV') {
                    target = $(this)
                } else {
                    target = $(this).closest("tr");
                }
                target.hide();
                if ($(this).hasClass('action_' + this_action)) {
                    target.show();
                }
            });
            /* expand/collapse PEM section */
            if (['import', 'import_csr', 'sign_csr'].includes($(this).val())) {
                if ($(".pem_section >  table > tbody > tr:eq(0) > td:eq(0)").is(':hidden')) {
                    $(".pem_section >  table > thead").click();
                }
            } else {
                if (!$(".pem_section >  table > tbody > tr:eq(0) > td:eq(0)").is(':hidden')) {
                    $(".pem_section >  table > thead").click();
                }
            }
        });

        /* fill common name with preselected username */
        $("#cert\\.commonname").change(function(){
            if ($(this).val() === '' && $("#user_filter").val() !== '') {
                $(this).val($("#user_filter").val());
            }
        });
    });

</script>

<style>
    .monospace-dialog {
        font-family: monospace;
        white-space: pre;
    }

    .monospace-dialog > .modal-dialog {
        width:70% !important;
    }

    .modal-body {
        max-height: calc(100vh - 210px);
        overflow-y: auto;
    }
</style>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#cert">{{ lang._('Certificates') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="cert" class="tab-pane fade in active">
        <div class="hidden">
            <!-- filter per type container -->
            <div id="filter_container" class="btn-group">
                <select id="ca_filter"  data-title="{{ lang._('Authority') }}" class="selectpicker cert_filter" data-live-search="true" data-size="5"  multiple data-width="200px">
                </select>
                <select id="user_filter"  data-title="{{ lang._('User client certificate') }}" class="selectpicker cert_filter" data-live-search="true" data-size="5"  multiple data-width="200px">
                </select>
            </div>
        </div>
        {{ partial('layout_partials/base_bootgrid_table', formGridCert + {'command_width': '145'})}}
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditCert,'id':formGridCert['edit_dialog_id'],'label':lang._('Edit Certificate')])}}
