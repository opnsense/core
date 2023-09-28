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

        $("#grid-gateways").UIBootgrid({
            search:'/api/routing/settings/searchGateway/',
            get:'/api/routing/settings/getGateway/',
            set:'/api/routing/settings/setGateway/',
            add:'/api/routing/settings/addGateway/',
            del:'/api/routing/settings/delGateway/',
            toggle:'/api/routing/settings/toggleGateway/',
            options: {
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
    });
</script>

<style>
.break {
    text-overflow: clip;
    white-space: normal;
    word-break: break-word;
}
</style>

<div class="tab-content content-box col-xs-12 __mb">
    <table id="grid-gateways" class="table table-condensed table-hover table-striped table-responsive" data-editAlert="GatewayChangeMessage" data-editDialog="DialogGateway">
        <tr>
        <thead>
        <tr>
            <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
            <th data-column-id="disabled" data-width="2em" data-type="string" data-formatter="rowtoggle"></th>
            <th data-column-id="name" data-width="fit" data-type="string" data-formatter="nameformatter">{{ lang._('Name') }}</th>
            <th data-column-id="interface" data-type="string" data-formatter="interfaceformatter">{{ lang._('Interface') }}</th>
            <th data-column-id="ipprotocol" data-type="string" data-formatter="protocolformatter">{{ lang._('Protocol') }}</th>
            <th data-column-id="priority" data-type="string" data-formatter="priorityformatter">{{ lang._('Priority') }}</th>
            <th data-column-id="gateway" data-type="string">{{ lang._('Gateway') }}</th>
            <th data-column-id="monitor" data-type="string">{{ lang._('Monitor IP') }}</th>
            <th data-column-id="delay" data-type="string">{{ lang._('RTT') }}</th>
            <th data-column-id="stddev" data-type="string">{{ lang._('RTTd') }}</th>
            <th data-column-id="loss" data-type="string">{{ lang._('Loss') }}</th>
            <th data-column-id="status" data-type="string" data-formatter="statusformatter">{{ lang._('Status') }}</th>
            <th data-column-id="descr" data-type="string" data-formatter="descriptionFormatter">{{ lang._('Description') }}</th>
            <th data-column-id="commands" data-formatter="commands", data-sortable="false"></th>
        </tr>
        </thead>
        <tbody>
        </tbody>
        <tfoot>
        <tr>
            <td></td>
            <td>
                <button data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-fw fa-plus"></span></button>
            </td>
        </tr>
        </tfoot>
        </tr>
    </table>
</div>
<!-- reconfigure -->
<div class="tab-content content-box col-xs-12 __mb">
    <div id="GatewayChangeMessage" class="alert alert-info" style="display: none" role="alert">
        {{ lang._('After changing settings, please remember to apply them with the button below') }}
    </div>
    <table class="table table-condensed">
        <tbody>
        <tr>
            <td>
                <button class="btn btn-primary" id="reconfigureAct"
                        data-endpoint='/api/routing/settings/reconfigure'
                        data-label="{{ lang._('Apply') }}"
                        data-error-title="{{ lang._('Error reconfiguring gateways') }}"
                        type="button"
                ></button>
            </td>
        </tr>
        </tbody>
    </table>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditGateway,'id':'DialogGateway','label':lang._('Edit Gateway')])}}
