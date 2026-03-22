{#
# Copyright (c) 2024 Deciso B.V.
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
        ajaxGet('/api/core/hasync_status/version', {}, function(data){
            if (data.response && data.response.firmware) {
                $("#remote_firmware_version").text(data.response.firmware.version);
                $("#remote_base_version").text(data.response.base.version);
                $("#remote_kernel_version").text(data.response.kernel.version);
                if (data.response.firmware.version != data.response.firmware._my_version) {
                    $("#version_warning").show();
                }
                $("#grid_services").UIBootgrid({
                    search:'/api/core/hasync_status/services/',
                    options: {
                        templates: {
                            select: ''
                        },
                        formatters: {
                            service_status: function (column, row) {
                                let service_id = row.id ?? '';
                                let service_name = row.name ?? '';
                                let widget = [];
                                if (row.status) {
                                    widget.push(
                                        '<span class="btn btn-xs btn-success"><i class="fa fa-play fa-fw"></i></span>'
                                    );
                                    widget.push('&nbsp;');
                                    widget.push(
                                        '<span data-service_action="restart" data-service_id="'+service_id+'"  data-service_name="'+service_name+'" class="btn btn-xs btn-default xmlrpc_srv_status_act">'+
                                            '<i class="fa fa-repeat fa-fw"></i>' +
                                        '</span>'
                                    );
                                    if (!row.nocheck) {
                                        widget.push('&nbsp;');
                                        widget.push(
                                        '<span data-service_action="stop" data-service_id="'+service_id+'"  data-service_name="'+service_name+'" class="btn btn-xs btn-default xmlrpc_srv_status_act">'+
                                            '<i class="fa fa-stop fa-fw"></i>' +
                                        '</span>'
                                    );

                                    }
                                } else {
                                    widget.push(
                                        '<span class="btn btn-xs btn-danger"><i class="fa fa-stop fa-fw"></i></span>'
                                    );
                                    widget.push('&nbsp;');
                                    widget.push(
                                        '<span data-service_action="start" data-service_id="'+service_id+'"  data-service_name="'+service_name+'" class="btn btn-xs btn-default xmlrpc_srv_status_act">'+
                                            '<i class="fa fa-play fa-fw"></i>' +
                                        '</span>'
                                    );

                                }
                                return widget.join('');
                            }
                        }
                    }
                }).on('loaded.rs.jquery.bootgrid', function(){
                    $(".xmlrpc_srv_status_act").each(function(){
                        switch($(this).data('service_action')) {
                            case 'start':
                                $(this).tooltip({title: "{{ lang._('Start') | safe}}", container: "body", trigger: "hover"});
                                break;
                            case 'restart':
                                $(this).tooltip({title: "{{ lang._('Synchronize and Restart') | safe}}", container: "body", trigger: "hover"});
                                break;
                            case 'stop':
                                $(this).tooltip({title: "{{ lang._('Stop') | safe}}", container: "body", trigger: "hover"});
                                break;
                        }
                        $(this).click(function(event){
                            event.stopPropagation();
                            if ($(this).hasClass('locked')) {
                                return;
                            }
                            $(this).find('i').removeClass('fa-play fa-stop fa-repeat').addClass('fa-spinner fa-pulse');
                            $(this).parent().find('span').addClass('locked');
                            ajaxCall('/api/core/hasync_status/' + $(this).data('service_action') + '/' + $(this).data('service_name') + '/' + $(this).data('service_id'), {}, function(data){
                                $('#grid_services').bootgrid('reload');
                            });
                        });
                    });
                });
                $("#status_ok").show();
            } else {
                $("#status_error").show();
            }
            $("#status_query").hide();

            $("#grid_services-header > .row > .actionBar").prepend($(`
                <div id="sync_container">
                    <span>{{ lang._('Synchronize and reconfigure all') }}</span>
                    <span id="act_restart_all" class="btn btn-xs btn-default" data-toggle="tooltip"
                        title="{{ lang._('Synchronize and restart all services') }}">
                        <i class="fa fa-repeat fa-fw"></i>
                    </span>
                </div>
            `));

            $("#act_restart_all").click(function () {
                let icon = $(this).find('i');
                if (icon.hasClass('spinner')) {
                    return;
                }
                icon.removeClass('fa-repeat').addClass('fa-spinner fa-pulse');
                ajaxCall('/api/core/hasync_status/restart_all', {}, function(data){
                    icon.removeClass('fa-spinner fa-pulse').addClass('fa-repeat');
                    $('#grid_services').bootgrid('reload');
                });
            });

        });
    });
</script>
<style>
    #grid_services-header .actionBar #act_restart_all {
        flex: 0 0 auto !important;
        width: auto !important;
        align-self: center !important;
    }
</style>
<section class="page-content-main">
    <div class="container-fluid">
        <div class="row">
            <div id="status_query"  class="alert alert-info" role="alert">
                {{ lang._('Loading....') }}
            </div>
            <div id="status_error" class="alert alert-warning" role="alert" style="display: none;">
                {{ lang._('The backup firewall is not accessible (check user credentials).')}}
            </div>
            <div id="status_ok" style="display: none;">
                <section class="col-xs-12">
                    <div class="content-box">
                        <div class="table-responsive">
                        <table class="table table-condensed">
                            <thead>
                                <tr>
                                    <th colspan="3"> {{ lang._("Backup firewall versions") }}</th>
                                </tr>
                                <tr>
                                    <th> {{ lang._("Firmware")}}</th>
                                    <th> {{ lang._("Base")}}</th>
                                    <th> {{ lang._("Kernel")}}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td id="remote_firmware_version"></td>
                                    <td id="remote_base_version"></td>
                                    <td id="remote_kernel_version"></td>
                                </tr>
                            </tbody>
                        </table>
                        </div>
                    </div>
                </section>
                <section class="col-xs-12">
                    <div id="version_warning" class="alert alert-warning" role="alert" style="display: none;">
                        {{ lang._('Remote version differs from this machines, please update first before using any of the actions below to avoid breakage.')}}
                    </div>

                    <div class="content-box">
                        <div class="table-responsive">
                            <table id="grid_services" class="table table-condensed table-hover table-striped table-responsive">
                                <thead>
                                    <tr>
                                        <th data-column-id="uid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                                        <th data-column-id="name" data-type="string">{{ lang._('Service') }}</th>
                                        <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                                        <th data-column-id="status" data-width="7em" data-type="string" data-formatter="service_status">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
            </div>
        </div>
    </div>
</section>
