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
        let grid_cert = $("#grid-cert").UIBootgrid({
            search:'/api/trust/ca/search/',
            get:'/api/trust/ca/get/',
            add:'/api/trust/ca/add/',
            set:'/api/trust/ca/set/',
            del:'/api/trust/ca/del/',
            commands: {
                raw_dump: {
                    method: function(event){
                        let uuid = $(this).data("row-id") !== undefined ? $(this).data("row-id") : '';
                        ajaxGet('/api/trust/ca/raw_dump/' + uuid, {}, function(data, status){
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
                        $type.append($("<option value='crt'/>").text('Certificate'));
                        $type.append($("<option value='prv'/>").text('Private key'));
                        $container.append(
                            $("<div class='form-group'/>").append(
                                $("<label for='download_type'>{{ lang._('File type') }}</label>"),
                                $type
                            )
                        );
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
                                        '/api/trust/ca/generate_file/'+uuid+'/'+$type.val(),
                                        params,
                                        function(data, status) {
                                            download_content(data.payload, $type.val() + '.pem', 'application/octet-stream');
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

        /**
        * Autofill certificate fields when choosing a different CA
        */
        $("#ca\\.caref").change(function(event){
            if (event.originalEvent !== undefined) {
                // not called on form open, only when the user chooses a new ca
                ajaxGet('/api/trust/ca/ca_info/' + $(this).val(), {}, function(data, status){
                    if (data.name !== undefined) {
                        [
                            'city', 'state', 'country', 'name', 'email', 'organization', 'ocsp_uri'
                        ].forEach(function(field){
                            if (data[field]) {
                                $("#ca\\." + field).val(data[field]);
                            }
                        });
                    }
                    $("#ca\\.country").selectpicker('refresh');
                });
            }
        });

        $("#ca\\.action").change(function(event){
            if (event.originalEvent === undefined) {
                // lock valid options based on server offered action
                let visible_options = [$(this).val()];
                if ($(this).val() == 'internal') {
                    visible_options.push('existing');
                    visible_options.push('ocsp');
                }
                $("#ca\\.action option").each(function(){
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
            if (['existing'].includes(this_action)) {
                if ($(".pem_section >  table > tbody > tr:eq(0) > td:eq(0)").is(':hidden')) {
                    $(".pem_section >  table > thead").click();
                }
            } else {
                if (!$(".pem_section >  table > tbody > tr:eq(0) > td:eq(0)").is(':hidden')) {
                    $(".pem_section >  table > thead").click();
                }
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
        <table id="grid-cert" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogCert">
            <thead>
                <tr>
                    <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="descr" data-width="15em" data-type="string">{{ lang._('Description') }}</th>
                    <th data-column-id="caref" data-width="15em" data-type="string">{{ lang._('Issuer') }}</th>
                    <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                    <th data-column-id="refcount" data-width="7em" data-type="string">{{ lang._('Usages') }}</th>
                    <th data-column-id="valid_from" data-width="10em" data-type="datetime">{{ lang._('Valid from') }}</th>
                    <th data-column-id="valid_to" data-width="10em" data-type="datetime">{{ lang._('Valid to') }}</th>
                    <th data-column-id="commands" data-width="11em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td>
                        <button data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-fw fa-plus"></span></button>
                        <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-fw fa-trash-o"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditCert,'id':'DialogCert','label':lang._('Edit Certificate')])}}
