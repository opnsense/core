{#

    OPNsense® is Copyright © 2023 by Deciso B.V.
    All rights reserved.

    Redistribution and use in source and binary forms, with or without modification,
    are permitted provided that the following conditions are met:

    1.  Redistributions of source code must retain the above copyright notice,
    this list of conditions and the following disclaimer.

    2.  Redistributions in binary form must reproduce the above copyright notice,
    this list of conditions and the following disclaimer in the documentation
    and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.

#}

<script>
    $( document ).ready(function() {
        let data_get_map = {'frm_generalsettings':"/api/kea/dhcpv4/get"};
        mapDataToFormUI(data_get_map).done(function(){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
            updateServiceControlUI('kea');
        });


        $("#grid-subnets").UIBootgrid(
            {   search:'/api/kea/dhcpv4/search_subnet',
                get:'/api/kea/dhcpv4/get_subnet/',
                set:'/api/kea/dhcpv4/set_subnet/',
                add:'/api/kea/dhcpv4/add_subnet/',
                del:'/api/kea/dhcpv4/del_subnet/'
            }
        );

        $("#grid-reservations").UIBootgrid(
            {   search:'/api/kea/dhcpv4/search_reservation',
                get:'/api/kea/dhcpv4/get_reservation/',
                set:'/api/kea/dhcpv4/set_reservation/',
                add:'/api/kea/dhcpv4/add_reservation/',
                del:'/api/kea/dhcpv4/del_reservation/'
            }
        );

        $("#reconfigureAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();
                saveFormToEndpoint("/api/kea/dhcpv4/set", 'frm_generalsettings', function(){
                    dfObj.resolve();
                });
                return dfObj;
            },
            onAction: function(data, status) {
                updateServiceControlUI('kea');
            }
        });
    });
</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#settings" id="settings_tab">{{ lang._('Settings') }}</a></li>
    <li><a data-toggle="tab" href="#subnets" id="tab_pools"> {{ lang._('Subnets') }} </a></li>
    <li><a data-toggle="tab" href="#reservations" id="tab_pools"> {{ lang._('Reservations') }} </a></li>
</ul>
<div class="tab-content content-box">
    <div id="settings"  class="tab-pane fade in active">
        {{ partial("layout_partials/base_form",['fields':formGeneralSettings,'id':'frm_generalsettings'])}}
    </div>
    <!--  -->
    <div id="subnets" class="tab-pane fade in">
        <table id="grid-subnets" class="table table-condensed table-hover table-striped" data-editDialog="DialogSubnet">
            <thead>
                <tr>
                  <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                  <th data-column-id="subnet" data-type="string">{{ lang._('Subnet') }}</th>
                  <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td>
                        <button data-action="add" type="button" class="btn btn-xs btn-primary pull-right"><span class="fa fa-fw fa-plus"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    <!--  -->
    <div id="reservations" class="tab-pane fade in">
        <table id="grid-reservations" class="table table-condensed table-hover table-striped" data-editDialog="DialogReservation">
            <thead>
                <tr>
                  <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                  <th data-column-id="subnet" data-type="string">{{ lang._('Subnet') }}</th>
                  <th data-column-id="hw_address" data-type="string">{{ lang._('MAC') }}</th>
                  <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                  <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td>
                        <button data-action="add" type="button" class="btn btn-xs btn-primary pull-right"><span class="fa fa-fw fa-plus"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<section class="page-content-main">
    <div class="content-box">
        <div class="col-md-12">
            <br/>
            <button class="btn btn-primary" id="reconfigureAct"
                    data-endpoint='/api/kea/service/reconfigure'
                    data-label="{{ lang._('Apply') }}"
                    data-error-title="{{ lang._('Error reconfiguring DHCPv4') }}"
                    type="button"
            ></button>
            <br/><br/>
        </div>
    </div>
</section>

{{ partial("layout_partials/base_dialog",['fields':formDialogSubnet,'id':'DialogSubnet','label':lang._('Edit Subnet')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogReservation,'id':'DialogReservation','label':lang._('Edit Reservation')])}}
