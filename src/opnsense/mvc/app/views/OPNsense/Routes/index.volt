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

        $("#grid-routes").UIBootgrid(
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
    <table id="grid-routes" class="table table-responsive" data-editDialog="DialogRoute" data-editAlert="routeChangeMessage">
        <thead>
            <tr>
                <th data-column-id="disabled" data-type="string" data-formatter="rowtoggle">{{ lang._('Disabled') }}</th>
                <th data-column-id="network" data-type="string" data-visible="true">{{ lang._('Network') }}</th>
                <th data-column-id="gateway" data-type="string" data-visible="true">{{ lang._('Gateway') }}</th>
                <th data-column-id="descr" data-type="string" data-visible="true">{{ lang._('Description') }}</th>
                <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                <th data-column-id="commands" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5"></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-fw fa-plus"></span></button>
                </td>
            </tr>
        </tfoot>
    </table>
    <div class="col-md-12">
        <div id="routeChangeMessage" class="alert alert-info" style="display: none" role="alert">
            {{ lang._('After changing settings, please remember to apply them with the button below') }}
        </div>
        {{ lang._('Do not enter static routes for networks assigned on any interface of this firewall. Static routes are only used for networks reachable via a different router, and not reachable via your default gateway.')}}
        <hr/>
        <button class="btn btn-primary" id="reconfigureAct"
                data-endpoint='/api/routes/routes/reconfigure'
                data-label="{{ lang._('Apply') }}"
                data-error-title="{{ lang._('Error reconfiguring routes') }}"
                type="button">
        </button>
        <br/><br/>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditRoute,'id':'DialogRoute','label':lang._('Edit route')])}}
