{#
 # Copyright (c) 2020 Deciso B.V.
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
 # THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
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

{% set theme_name = ui_theme|default('opnsense') %}
<link rel="stylesheet" type="text/css" href="{{ cache_safe(theme_file_or_default('/css/pick-a-color-1.2.3.min.css', theme_name)) }}">
<script src="{{ cache_safe('/ui/js/pick-a-color-1.2.3.min.js') }}"></script>
<script src="{{ cache_safe('/ui/js/tinycolor-1.4.1.min.js') }}"></script>

<script>

    $( document ).ready(function() {
        /*************************************************************************************************************
         * link grid actions
         *************************************************************************************************************/
        $("#grid-categories").UIBootgrid(
                {   search:'/api/firewall/category/search_item',
                    get:'/api/firewall/category/get_item/',
                    set:'/api/firewall/category/set_item/',
                    add:'/api/firewall/category/add_item/',
                    del:'/api/firewall/category/del_item/',
                    options:{
                        formatters:{
                            color: function (column, row) {
                                if (row.color != "") {
                                    return "<i style='color:#"+row.color+";' class='fa fa-circle'></i>";
                                }
                            },
                            commands: function (column, row) {
                                return "<button type=\"button\" class=\"btn btn-xs btn-default command-edit bootgrid-tooltip\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-pencil fa-fw\"></span></button> " +
                                    "<button type=\"button\" class=\"btn btn-xs btn-default command-copy bootgrid-tooltip\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-clone fa-fw\"></span></button>" +
                                    "<button type=\"button\" class=\"btn btn-xs btn-default command-delete bootgrid-tooltip\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-trash-o fa-fw\"></span></button>";
                            },
                            boolean: function (column, row) {
                                if (parseInt(row[column.id], 2) === 1) {
                                    return "<span class=\"fa fa-check\" data-value=\"1\" data-row-id=\"" + row.uuid + "\"></span>";
                                } else {
                                    return "<span class=\"fa fa-times\" data-value=\"0\" data-row-id=\"" + row.uuid + "\"></span>";
                                }
                            },
                        }
                    }

                }
        );
        $(".pick-a-color").pickAColor({
            showSpectrum: true,
            showSavedColors: true,
            saveColorsPerElement: true,
            fadeMenuToggle: true,
            showAdvanced : false,
            showBasicColors: true,
            showHexInput: true,
            allowBlank: true,
            inlineDropdown: true
        });
        $("#category\\.color").change(function(){
            // update color picker
            $(this).blur().blur();
        });

    });

</script>

<style>
    .modal-body {
        min-height: 410px;
    }
</style>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#grid-categories">{{ lang._('Categories') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="categories" class="tab-pane fade in active">
        <table id="grid-categories" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogEdit">
            <thead>
            <tr>
                <th data-column-id="color" data-width="2em" data-type="string" data-formatter="color"></th>
                <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                <th data-column-id="auto" data-width="6em" data-type="string" data-formatter="boolean">{{ lang._('Auto') }}</th>
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
                    <button data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-plus fa-fw"></span></button>
                    <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o fa-fw"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
</div>

{# include dialog #}
{{ partial("layout_partials/base_dialog",['fields':formDialogEdit,'id':'DialogEdit','label':lang._('Edit category')])}}
