{#
 # Copyright (c) 2023-2024 Deciso B.V.
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
    // Add the status column since it is not part of dialogRelay.xml
    const $statusColumn = $('<th data-column-id="status" data-width="6em" data-type="string" data-formatter="statusled">{{ lang._('Status') }}</th>');
    $('#{{formGridRelay['table_id']}} thead tr th[data-column-id="enabled"]').after($statusColumn);

    $("#{{formGridDest['table_id']}}").UIBootgrid({
        search:'/api/dhcrelay/settings/search_dest',
        get:'/api/dhcrelay/settings/get_dest/',
        set:'/api/dhcrelay/settings/set_dest/',
        add:'/api/dhcrelay/settings/add_dest/',
        del:'/api/dhcrelay/settings/del_dest/',
    });
    $("#{{formGridRelay['table_id']}}").UIBootgrid({
        search:'/api/dhcrelay/settings/search_relay',
        get:'/api/dhcrelay/settings/get_relay/',
        set:'/api/dhcrelay/settings/set_relay/',
        add:'/api/dhcrelay/settings/add_relay/',
        del:'/api/dhcrelay/settings/del_relay/',
        toggle:'/api/dhcrelay/settings/toggle_relay/'
    });
    $("div.actionBar").each(function(){
        if ($(this).closest(".bootgrid-header").attr("id").includes("Dest")) {
            $(this).parent().prepend($('<td id="heading-wrapper" class="col-sm-2 theading-text">{{ lang._('Destinations') }}</div>'));
        } else {
            $(this).parent().prepend($('<td id="heading-wrapper" class="col-sm-2 theading-text">{{ lang._('Relays') }}</div>'));
        }
    });
    $("#reconfigureAct").SimpleActionButton();
});
</script>
<div class="content-box __mb">
    {{ partial('layout_partials/base_bootgrid_table', formGridDest)}}
</div>
<div class="content-box __mb">
    {{ partial('layout_partials/base_bootgrid_table', formGridRelay)}}
</div>
{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/dhcrelay/service/reconfigure', 'data_grid_reload': formGridRelay['table_id']}) }}
{{ partial('layout_partials/base_dialog',['fields':formDialogRelay,'id':formGridRelay['edit_dialog_id'],'label':lang._('Edit DHCP relay')])}}
{{ partial('layout_partials/base_dialog',['fields':formDialogDest,'id':formGridDest['edit_dialog_id'],'label':lang._('Edit DHCP destination')])}}
