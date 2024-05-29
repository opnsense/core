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
        let grid_crl = $("#grid-crl").UIBootgrid({
            search:'/api/trust/crl/search/',
            get:'/api/trust/crl/get/',
            set:'/api/trust/crl/set/',
            del:'/api/trust/crl/del/',
            datakey: 'refid',
            commands: {
                raw_dump: {
                    method: function(event){
                        let refid = $(this).data("row-id") !== undefined ? $(this).data("row-id") : '';
                        ajaxGet('/api/trust/crl/raw_dump/' + refid, {}, function(data, status){
                            if (data.stdout) {
                                BootstrapDialog.show({
                                    title: "{{ lang._('CRL info') }}",
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
                copy: {
                    classname: undefined
                },
                download: {
                    method: function(event){
                        let refid = $(this).data("row-id") !== undefined ? $(this).data("row-id") : '';
                        ajaxGet('/api/trust/crl/get/' + refid, {}, function(data, status){
                            if (data.crl && data.crl.text) {
                                download_content(data.crl.text, data.crl.descr + '.crl', 'application/octet-stream');
                            }
                        });
                    },
                    classname: 'fa fa-fw fa-download',
                    title: "{{ lang._('Download CRL when available') }}",
                    sequence: 12
                },
                download_ocsp: {
                    method: function(event){
                        let refid = $(this).data("row-id") !== undefined ? $(this).data("row-id") : '';
                        ajaxGet('/api/trust/crl/get_ocsp_info_data/' + refid, {}, function(data, status){
                            if (data.payload) {
                                download_content(data.payload, 'index.txt', 'application/octet-stream');
                            }
                        });
                    },
                    classname: 'fa fa-fw fa-file-code-o',
                    title: "{{ lang._('Download OCSP demo index') }}",
                    sequence: 15
                }
            }
        });

        $("#DialogCrl").click(function(){
            $(this).html($("#crl\\.descr").val() !== '' ? $("#crl\\.descr").val() : '-');
            $(this).show();
        });

        $("#DialogCrl").change(function(){
            if ($("#edit_crl").is(':visible')) {
                $("#tab_crls").click();
                $("#DialogCrl").hide();
            }
        });

        $("#crl\\.crlmethod").change(function(event){
            let this_action = $(this).val();
            $(".crlmethod").each(function(){
                let target = null;
                if ($(this)[0].tagName == 'DIV') {
                    target = $(this)
                } else {
                    target = $(this).closest("tr");
                }
                target.hide();
                if ($(this).hasClass('crlmethod_' + this_action)) {
                    target.show();
                }
            });
        });

        /* when revoking a certificate, make sure we only add the same cert in one group */
        $(".revoked").change(function(event){
            event.preventDefault();
            let values = $(this).val();
            let that = $(this).attr('id');
            $("select.revoked").each(function(){
                if ($(this).attr('id') !== that && Array.isArray(values)) {
                    let target = $(this);
                    values.forEach(function(refid){
                        target.find('option[value="'+refid+'"]').prop("selected", false);
                    });
                    target.selectpicker('refresh');
                }
            });
        })
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
    <li class="active"><a data-toggle="tab" id="tab_crls"  href="#cert">{{ lang._('Index') }}</a></li>
    <li><a data-toggle="tab" href="#edit_crl" id="DialogCrl" style="display: none;"> </a></li>
</ul>
<div class="tab-content content-box">
    <div id="cert" class="tab-pane fade in active">
        <table id="grid-crl" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogCrl">
            <thead>
                <tr>
                    <th data-column-id="refid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="descr" data-type="string">{{ lang._('CA Name') }}</th>
                    <th data-column-id="crl_descr" data-type="string">{{ lang._('CRL Name') }}</th>
                    <th data-column-id="commands" data-width="11em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
    <div id="edit_crl" class="tab-pane fade in">
        <form id="frm_DialogCrl">
            {{ partial("layout_partials/base_form",['fields':formDialogCrl,'id':'DialogCrl'])}}
        </form>
        <table class="table table-condensed">
            <tbody>
                <tr>
                    <td>
                        <button class="btn btn-primary" id="btn_DialogCrl_save" type="button">
                            <b>{{ lang._('Apply') }}</b>
                            <i id="DialogCrl_progress" class=""></i>
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
