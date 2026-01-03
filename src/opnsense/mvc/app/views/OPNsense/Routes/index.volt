{#
 #
 # Copyright (c) 2017 Fabian Franz
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

        $("#{{formGridRoute['table_id']}}").UIBootgrid(
          { 'search':'/api/routes/routes/searchroute/',
            'get':'/api/routes/routes/getroute/',
            'set':'/api/routes/routes/setroute/',
            'add':'/api/routes/routes/addroute/',
            'del':'/api/routes/routes/delroute/',
            'toggle':'/api/routes/routes/toggleroute/',
            'options':{selection:false, multiSelect:false}
          }
        );
        $("#reconfigureAct").SimpleActionButton();
    });

</script>

<div class="content-box">
    {{ partial('layout_partials/base_bootgrid_table', formGridRoute)}}
    <div style="margin: 10px 10px 10px 10px;">
        {{ lang._('Do not enter static routes for networks assigned on any interface of this firewall. Static routes are only used for networks reachable via a different router, and not reachable via your default gateway.') }}
    </div>
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/routes/routes/reconfigure'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditRoute,'id':formGridRoute['edit_dialog_id'],'label':lang._('Edit route')])}}
