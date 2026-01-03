{#
 # Copyright (c) 2023 Deciso B.V.
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

        $("#{{formGridGateway['table_id']}}").UIBootgrid({
            search:'/api/routing/settings/search_gateway/',
            get:'/api/routing/settings/get_gateway/',
            set:'/api/routing/settings/set_gateway/',
            add:'/api/routing/settings/add_gateway/',
            del:'/api/routing/settings/del_gateway/',
            toggle:'/api/routing/settings/toggle_gateway/',
            options: {
                triggerEditFor: getUrlHash('edit'),
                initialSearchPhrase: getUrlHash('search'),
                selection: false,
                multiSelect: false,
                rowSelect: false,
                formatters: {
                    "rowtoggle": function (column, row) {
                        if (row.disabled) {
                            return '<span style="cursor: pointer;" class="fa fa-play command-toggle text-muted bootgrid-tooltip" data-toggle="tooltip" title="{{ lang._('Enable') }}" data-value="1" data-row-id="' + row.uuid + '"></span>';
                        } else {
                            return '<span style="cursor: pointer;" class="fa fa-play command-toggle text-success bootgrid-tooltip" data-toggle="tooltip" title="{{ lang._('Disable') }}" data-value="0" data-row-id="' + row.uuid + '"></span>';
                        }
                    },
                    "commands": function (column, row) {
                        let elements = '<div class="break"><button type="button" class="btn btn-xs btn-default command-edit bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-pencil"></span></button> ' +
                        '<button type="button" class="btn btn-xs btn-default command-copy bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-clone"></span></button>';

                        if (!row.virtual) {
                            elements += '<button type="button" class="btn btn-xs btn-default command-delete bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-trash-o"></span></button>';
                        }
                        return elements + '</div>';
                    },
                    "nameformatter": function (column, row) {
                        let elem = '<span class="break">' + row.name + ' ';
                        if (row.defaultgw) {
                            elem += '<strong>({{ lang._('active')}})</strong>';
                        }
                        return elem + '</span>';
                    },
                    "interfaceformatter": function (column, row) {
                        return row.interface_descr;
                    },
                    "protocolformatter": function (column, row) {
                        return row.ipprotocol == 'inet' ? 'IPv4' : 'IPv6';
                    },
                    "priorityformatter": function (column, row) {
                        if (row.defunct) {
                            row.priority = '{{ lang._('defunct') }}';
                        }
                        let elem = '<span class="break">' + row.priority;
                        if (row.upstream) {
                            elem += ' <small>({{ lang._('upstream') }})</small>';
                        }

                        return elem + '</span>';
                    },
                    "statusformatter": function (column, row) {
                        return '<div class="' + row.label_class + ' bootgrid-tooltip" data-toggle="tooltip" title="' + row.status + '"></div>';
                    },
                    "descriptionFormatter": function (column, row) {
                        return '<div class="break">' + row.descr + '</div>';
                    }
                }
            }
        });

        $("#reconfigureAct").SimpleActionButton();

        /* hide monitor fields when disabled */
        $("#gateway_item\\.monitor_disable").change(function(){
            if ($(this).is(':checked')) {
                $(".monitor_opt").closest('tr').hide();
            } else {
                $(".monitor_opt").closest('tr').show();
            }
        });

    });
</script>

<style>
.break {
    text-overflow: clip;
    white-space: normal;
    word-break: break-word;
}
</style>

<div class="tab-content content-box">
    {{ partial('layout_partials/base_bootgrid_table', formGridGateway)}}
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/routing/settings/reconfigure'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditGateway,'id':formGridGateway['edit_dialog_id'],'label':lang._('Edit Gateway')])}}
