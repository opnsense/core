{#
 # Copyright (c) 2014-2015 Deciso B.V.
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
        /*************************************************************************************************************
         * link grid actions
         *************************************************************************************************************/

        $("#{{formGridZone['table_id']}}").UIBootgrid(
            {   search:'/api/captiveportal/settings/search_zones',
                get:'/api/captiveportal/settings/get_zone/',
                set:'/api/captiveportal/settings/set_zone/',
                add:'/api/captiveportal/settings/add_zone/',
                del:'/api/captiveportal/settings/del_zone/',
                toggle:'/api/captiveportal/settings/toggle_zone/',
                options: {
                    triggerEditFor: getUrlHash('edit')
                }
            }
        );

        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            if (e.target.id == 'templates_tab') {
                if (!$("#grid-templates").hasClass('tabulator')) {
                    var grid_templates  = $("#grid-templates").UIBootgrid({
                        search: '/api/captiveportal/service/search_templates',
                        options: {
                            formatters: {
                                "commands": function (column, row) {
                                    return '<button type="button" class="btn btn-xs btn-default command-download bootgrid-tooltip" data-toggle="tooltip" title="{{ lang._('Download') }}" data-row-id="' + row.fileid + '"><span class="fa fa-download fa-fw"></span></button> ' +
                                        '<button type="button" class="btn btn-xs btn-default command-edit bootgrid-tooltip" data-row-id="' + row.uuid + '" data-row-name="' + row.name + '"><span class="fa fa-pencil fa-fw"></span></button> ' +
                                        '<button type="button" class="btn btn-xs btn-default command-delete bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-trash-o fa-fw"></span></button>';
                                }
                            }
                        }
                    });
                    grid_templates.on("loaded.rs.jquery.bootgrid", function(){
                        grid_templates.find(".command-edit").on("click", function(e) {
                            $("#templateUUID").val($(this).data("row-id"));
                            $("#templateName").val($(this).data("row-name"));
                            $("#base64text_upload").val("");
                            $('#DialogTemplate').modal({backdrop: 'static', keyboard: false});
                        });
                        grid_templates.find(".command-delete").on("click", function(e) {
                            var uuid=$(this).data("row-id");
                            stdDialogConfirm('{{ lang._('Confirm removal') }}',
                                '{{ lang._('Do you want to remove the selected item?') }}',
                                '{{ lang._('Yes') }}', '{{ lang._('Cancel') }}', function () {
                                ajaxCall("/api/captiveportal/service/del_template/" + uuid, {},function(data,status){
                                    // reload grid after delete
                                    $("#grid-templates").bootgrid("reload");
                                });
                            });
                        });
                        grid_templates.find(".command-download").on("click", function(e) {
                            window.open('/api/captiveportal/service/get_template/'+$(this).data("row-id")+'/','downloadTemplate');
                        });

                        /**
                         * Open dialog to add new template
                         */
                        $("#addTemplateAct").off("click").on("click", function() {
                            $("#templateUUID").val("");
                            $("#templateName").val("");
                            $("#base64text_upload").val("");
                            $('#DialogTemplate').modal({backdrop: 'static', keyboard: false});
                        });

                        /**
                         * download default template
                         */
                        $("#downloadTemplateAct").off("click").on("click", function() {
                            window.open('/api/captiveportal/service/get_template/', 'downloadTemplate');
                        });
                    });
                }
            }
        });

        /*************************************************************************************************************
         * Commands
         *************************************************************************************************************/

        /**
         * Reconfigure
         */
        $("#reconfigureAct").SimpleActionButton();

        /*************************************************************************************************************
         * File upload action, template dialog
         *************************************************************************************************************/
        // catch file select event and save content to textarea as base64 string
        $("#input_filename").change(function(evt) {
            if (evt.target.files[0]) {
                var reader = new FileReader();
                reader.onload = function(readerEvt) {
                    var binaryString = readerEvt.target.result;
                    $("#base64text_upload").val(btoa(binaryString));
                };
                reader.readAsBinaryString(evt.target.files[0]);
            }
        });
        $("#act_upload").click(function() {
            var requestData = {'name' : $("#templateName").val(), 'content': $("#base64text_upload").val()};
            if ($("#templateUUID").val() != "") {
                requestData['uuid'] = $("#templateUUID").val();
            }
            // save file content to server
            ajaxCall("/api/captiveportal/service/save_template", requestData, function(data,status) {
                if (data['error'] == undefined) {
                    // saved, flush form data and hide modal
                    $("#grid-templates").bootgrid("reload");
                    $("#DialogTemplate").modal('hide');
                } else {
                    // error saving
                    BootstrapDialog.show({
                        type: BootstrapDialog.TYPE_WARNING,
                        title: "{{ lang._('Error uploading template') }}",
                        message: data['error'],
                        draggable: true
                    });
                }
            });
        });

        updateServiceControlUI('captiveportal');
    });


</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#zones">{{ lang._('Zones') }}</a></li>
    <li><a data-toggle="tab" id="templates_tab" href="#template">{{ lang._('Templates') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="zones" class="tab-pane fade in active">
        {{ partial('layout_partials/base_bootgrid_table', formGridZone)}}
    </div>
    <div id="template" class="tab-pane fade in">
        <div class="col-md-12">
            <table id="grid-templates" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogTemplate">
                <thead>
                <tr>
                    <th data-column-id="fileid" data-type="string" data-visible="false">{{ lang._('Fileid') }}</th>
                    <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                    <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                    <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                </tr>
                </thead>
                <tbody>
                </tbody>
                <tfoot>
                <tr>
                    <td></td>
                    <td>
                        <button id="addTemplateAct" type="button" class="btn btn-xs btn-primary bootgrid-tooltip" title="{{ lang._('Add template') }}"><span class="fa fa-fw fa-plus"></span></button>
                        <button id="downloadTemplateAct" type="button" class="btn btn-xs btn-default bootgrid-tooltip" title="{{ lang._('Download default template') }}"><span class="fa fa-fw fa-download"></span></button>
                    </td>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/captiveportal/service/reconfigure', 'data_service_widget': 'captiveportal'}) }}

{# include dialogs #}
{{ partial("layout_partials/base_dialog",['fields':formDialogZone,'id':formGridZone['edit_dialog_id'],'label':lang._('Edit zone')])}}

<!-- upload (new) template content dialog -->
<div class="modal fade" id="DialogTemplate" tabindex="-1" role="dialog" aria-labelledby="formDialogTemplateLabel" aria-hidden="true">
    <div class="modal-backdrop fade in"></div>
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ lang._('Close') }}"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="formDialogTemplateLabel">{{ lang._('Upload file') }}</h4>
            </div>
            <div class="modal-body">
                <form>
                    <input type="text" id="templateUUID" class="hidden">
                    <div class="form-group">
                        <label for="templateName">{{ lang._('Template name') }}</label>
                        <input type="text" class="form-control" id="templateName" placeholder="Name">
                    </div>
                    <div class="form-group">
                        <label for="input_filename">{{ lang._('File input') }}</label>
                        <input type="file" id="input_filename">
                    </div>
                    <textarea id="base64text_upload" class="hidden"></textarea>
                </form>
            </div>
            <div class="modal-footer">
                <button id="act_upload" type="button" class="btn btn-default">
                    {{ lang._('Upload') }}
                    <span class="fa fa-upload"></span>
                </button>
            </div>
        </div>
    </div>
</div>
