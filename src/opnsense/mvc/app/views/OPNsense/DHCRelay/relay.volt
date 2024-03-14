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
    $("#grid-dest").UIBootgrid({
        search:'/api/dhcrelay/settings/searchDest',
        get:'/api/dhcrelay/settings/getDest/',
        set:'/api/dhcrelay/settings/setDest/',
        add:'/api/dhcrelay/settings/addDest/',
        del:'/api/dhcrelay/settings/delDest/',
    });
    $("#grid-relay").UIBootgrid({
        search:'/api/dhcrelay/settings/searchRelay',
        get:'/api/dhcrelay/settings/getRelay/',
        set:'/api/dhcrelay/settings/setRelay/',
        add:'/api/dhcrelay/settings/addRelay/',
        del:'/api/dhcrelay/settings/delRelay/',
        toggle:'/api/dhcrelay/settings/toggleRelay/'
    });
    $("#reconfigureAct").SimpleActionButton();
});
</script>

<div class="content-box __mb">
    <table class="table table-striped page-header" style="margin-top: 0">
        <tbody><tr><th>Destinations</th><tr></tbody>
    </table>
    <table id="grid-dest" class="table table-condensed table-hover table-striped" data-editDialog="DialogDest">
        <thead>
        <tr>
            <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
            <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
            <th data-column-id="server" data-type="string">{{ lang._('Server') }}</th>
            <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Edit') }} | {{ lang._('Delete') }}</th>
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

<div class="content-box __mb">
    <table class="table table-striped page-header" style="margin-top: 0">
        <tbody><tr><th>Relays</th><tr></tbody>
    </table>
    <table id="grid-relay" class="table table-condensed table-hover table-striped" data-editDialog="DialogRelay">
        <thead>
        <tr>
            <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
            <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
            <th data-column-id="status" data-width="6em" data-type="string" data-formatter="statusled">{{ lang._('Status') }}</th>
            <th data-column-id="interface" data-type="string">{{ lang._('Interface') }}</th>
            <th data-column-id="destination" data-type="string">{{ lang._('Destination') }}</th>
            <th data-column-id="agent_info" data-type="string" data-visible="false">{{ lang._('Agent Info') }}</th>
            <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Edit') }} | {{ lang._('Delete') }}</th>
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

<div class="content-box">
    <div class="col-md-12 __mt __mb">
        <button class="btn btn-primary" id="reconfigureAct"
                data-endpoint='/api/dhcrelay/service/reconfigure'
                data-label="{{ lang._('Apply') }}"
                data-grid-reload="grid-relay"
                data-error-title="{{ lang._('Error reconfiguring dhcrelay') }}"
                type="button">
        </button>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogRelay,'id':'DialogRelay','label':lang._('Edit DHCP relay')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogDest,'id':'DialogDest','label':lang._('Edit DHCP destination')])}}
