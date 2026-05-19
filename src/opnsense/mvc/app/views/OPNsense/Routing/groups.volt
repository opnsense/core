{#
 # Copyright (c) 2026 Deciso B.V.
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
        $("#{{formGridGatewayGroups['table_id']}}").UIBootgrid({
            search:'/api/routing/group_settings/search',
            get:'/api/routing/group_settings/get/',
            set:'/api/routing/group_settings/set/',
            add:'/api/routing/group_settings/add/',
            del:'/api/routing/group_settings/del/',
            options: {
                formatters: {
                    gateways: function(col, row, onRendered) {
                        const result = `
                            <table class="table table-striped table-condensed" style="width: 100%;">
                                <tbody>
                                    ${Object.entries(row.gateways)
                                        .filter(([tier, gws]) => gws.length)
                                        .map(([tier, gws]) => `
                                            <tr>
                                                <th style="p[adding: 8px 12px; text-align: left; vertical-align: top;">
                                                    Tier ${tier}
                                                </th>
                                                <td style="text-overflow: ellipsis; overflow: hidden; width: 180px;">
                                                    ${gws.map((gw) => `
                                                        <div data-toggle="tooltip"
                                                            title="${gw.status_translated}
                                                                ({{ lang._('Loss') }} ${gw.loss ?? '~'} | {{ lang._('Delay') }} ${gw.delay ?? '~'} | {{ lang._('stddev') }} ${gw.stddev ?? '~'})">
                                                            <span class="fa fa-plug text-${gw.label} fa-fw"></span>
                                                            ${gw.name}
                                                        </div>
                                                    `).join('<hr style="margin: 4px;"/>')}
                                                </td>
                                            </tr>
                                        `)
                                        .join("")}
                                </tbody>
                            </table>
                        `;

                        return result;
                    }
                }
            }
        });

        $("#reconfigureAct").SimpleActionButton();
    });
</script>

<div class="tab-content content-box">
    {{ partial('layout_partials/base_bootgrid_table', formGridGatewayGroups)}}
    <div id="infosection" class="bootgrid-footer container-fluid">
        {{ lang._('Remember to use these Gateway Groups in firewall rules in order to enable load balancing, failover, or policy-based routing. Without rules directing traffic into the Gateway Groups, they will not be used.') }}
    </div>
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/routing/group_settings/reconfigure'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditGatewayGroup,'id':formGridGatewayGroups['edit_dialog_id'],'label':lang._('Edit Gateway Group')])}}
