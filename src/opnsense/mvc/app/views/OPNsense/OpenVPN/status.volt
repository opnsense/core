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
    'use strict';

    $( document ).ready(function () {
        let grid_sessions = $("#grid-sessions").UIBootgrid({
            search:'/api/openvpn/service/search_sessions',
            options:{
                selection: false,
                formatters:{
                    commands: function (column, row) {
                        if (row.is_client) {
                            return '<button type="button" class="btn btn-xs btn-default ovpn-command command-kill" data-toggle="tooltip" title="{{ lang._('Kill') }}" data-common_name="'+row.common_name+'" data-row-id="' + row.id + '"><span class="fa fa-times fa-fw"></span></button>';
                        } else if (row.status === null) {
                            return '<button type="button" class="btn btn-xs btn-default ovpn-command command-start" data-toggle="tooltip" title="{{ lang._('Start') }}" data-row-id="' + row.id + '"><span class="fa fa-play fa-fw"></span></button>';
                        } else {
                            return '<button type="button" class="btn btn-xs btn-default ovpn-command command-restart" data-toggle="tooltip" title="{{ lang._('Restart') }}" data-row-id="' + row.id + '"><span class="fa fa-repeat fa-fw"></span></button>' +
                                '<button type="button" class="btn btn-xs btn-default ovpn-command command-stop" data-toggle="tooltip" title="{{ lang._('Stop') }}" data-row-id="' + row.id + '"><span class="fa fa-stop fa-fw"></span></button>';
                        }
                    }
                },
                requestHandler: function(request){
                    if ( $('#type_filter').val().length > 0) {
                        request['type'] = $('#type_filter').val();
                    }
                    return request;
                },
            }
        });

        $("#grid-routes").UIBootgrid({
            search:'/api/openvpn/service/search_routes',
            options:{
                selection: false
            }
        });

        grid_sessions.on('loaded.rs.jquery.bootgrid', function () {
            $('[data-toggle="tooltip"]').tooltip();
            $(".ovpn-command").click(function(){
                let this_cmd = $(this);
                if (this_cmd.hasClass('command-kill')) {
                    let tmp = this_cmd.data('row-id').split('_');
                    if (tmp.length == 2) {
                        let params = {server_id:  tmp[0], session_id: tmp[1]};
                        ajaxCall('/api/openvpn/service/kill_session/', params, function(data, status){
                            if (data && data.status === 'not_found') {
                                // kill by common name
                                params.session_id = this_cmd.data('common_name');
                                ajaxCall('/api/openvpn/service/kill_session/', params, function(data, status){
                                    $('#grid-sessions').bootgrid('reload');
                                });
                            } else{
                                $('#grid-sessions').bootgrid('reload');
                            }
                        });
                    }
                } else if (this_cmd.hasClass('command-start')) {
                    ajaxCall('/api/openvpn/service/start_service/' + this_cmd.data('row-id'), {}, function(data, status){
                        $('#grid-sessions').bootgrid('reload');
                    });
                } else if (this_cmd.hasClass('command-stop')) {
                    ajaxCall('/api/openvpn/service/stop_service/' + this_cmd.data('row-id'), {}, function(data, status){
                        $('#grid-sessions').bootgrid('reload');
                    });
                } else if (this_cmd.hasClass('command-restart')) {
                    ajaxCall('/api/openvpn/service/restart_service/' + this_cmd.data('row-id'), {}, function(data, status){
                        $('#grid-sessions').bootgrid('reload');
                    });
                }
            });
        });

        $("#type_filter").change(function(){
            $('#grid-sessions').bootgrid('reload');
        });

        $("#type_filter_container").detach().prependTo('#grid-sessions-header > .row > .actionBar > .actions');
    });

</script>


<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#sessions">{{ lang._('Sessions') }}</a></li>
    <li><a data-toggle="tab" href="#routes">{{ lang._('Routes') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="sessions" class="tab-pane fade in active">
        <div class="hidden">
            <!-- filter per type container -->
            <div id="type_filter_container" class="btn-group">
                <select id="type_filter"  data-title="{{ lang._('Filter type') }}" class="selectpicker"  data-live-search="true" multiple="multiple" data-width="200px">
                    <option value="server">{{ lang._('Server') }}</option>
                    <option value="client">{{ lang._('Client') }}</option>
                </select>
            </div>
        </div>
        <table id="grid-sessions" class="table table-condensed table-hover table-striped table-responsive">
            <thead>
                <tr>
                <th data-column-id="id" data-type="string" data-sortable="false" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                <th data-column-id="type" data-type="string">{{ lang._('Type') }}</th>
                <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                <th data-column-id="common_name" data-type="string">{{ lang._('Common Name') }}</th>
                <th data-column-id="real_address" data-type="string">{{ lang._('Real Address') }}</th>
                <th data-column-id="virtual_address" data-type="string">{{ lang._('Virtual IPv4 Address') }}</th>
                <th data-column-id="virtual_ipv6_address" data-type="string">{{ lang._('Virtual IPv6 Address') }}</th>
                <th data-column-id="connected_since" data-type="string">{{ lang._('Connected Since') }}</th>
                <th data-column-id="bytes_sent" data-type="numeric" data-formatter="bytes">{{ lang._('Bytes Sent') }}</th>
                <th data-column-id="bytes_received" data-type="numeric" data-formatter="bytes">{{ lang._('Bytes Received') }}</th>
                <th data-column-id="status" data-type="string">{{ lang._('Status') }}</th>
                <th data-column-id="commands" data-width="5em" data-formatter="commands" data-sortable="false"></th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
    <div id="routes" class="tab-pane fade in">
        <table id="grid-routes" class="table table-condensed table-hover table-striped table-responsive">
            <thead>
                <tr>
                <th data-column-id="id" data-type="string" data-sortable="false" data-visible="false">{{ lang._('ID') }}</th>
                <th data-column-id="type" data-type="string">{{ lang._('Type') }}</th>
                <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                <th data-column-id="common_name" data-type="string">{{ lang._('Common Name') }}</th>
                <th data-column-id="real_address" data-type="string">{{ lang._('Real Address') }}</th>
                <th data-column-id="virtual_address" data-type="string">{{ lang._('Target Network') }}</th>
                <th data-column-id="last_ref" data-type="string">{{ lang._('Last referenced') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
</div>
