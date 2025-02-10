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
        // Add the origin column since it is not part of dialogNeighbor.xml
        const $originColumn = $('<th data-column-id="origin"  data-type="string">{{ lang._('Origin') }}</th>');
        $('#{{formGridNeighbor['table_id']}} thead tr th[data-column-id="uuid"]').after($originColumn);

        $("#{{formGridNeighbor['table_id']}}").UIBootgrid(
            {   search:'/api/interfaces/neighbor_settings/search_item',
                get:'/api/interfaces/neighbor_settings/get_item/',
                set:'/api/interfaces/neighbor_settings/set_item/',
                add:'/api/interfaces/neighbor_settings/add_item/',
                del:'/api/interfaces/neighbor_settings/del_item/',
                options:{
                    formatters: {
                        commands: function (column, row) {
                            if (row.origin == 'manual') {
                                // exclude buttons for internal (dynamic) neighbors
                                return '<button type="button" class="btn btn-xs btn-default command-edit bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-pencil"></span></button> ' +
                                    '<button type="button" class="btn btn-xs btn-default command-copy bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-clone"></span></button>' +
                                    '<button type="button" class="btn btn-xs btn-default command-delete bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-trash-o"></span></button>';
                            }
                        }
                    }
                }
            }
        );
        $("#reconfigureAct").SimpleActionButton();
    });
</script>
<div class="tab-content content-box">
    {{ partial('layout_partials/base_bootgrid_table', formGridNeighbor)}}
</div>
{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/interfaces/loopback_settings/reconfigure'}) }}
{{ partial('layout_partials/base_dialog',['fields':formDialogEdit,'id':formGridNeighbor['edit_dialog_id'],'label':lang._('Edit Neighbor')])}}
