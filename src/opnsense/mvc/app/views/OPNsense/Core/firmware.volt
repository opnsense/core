{#

OPNsense® is Copyright © 2014 – 2015 by Deciso B.V.
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

<script type="text/javascript">

    /**
     * retrieve update status from backend
     */
    function updateStatus() {
        // update UI
        $('#maintabs li:eq(0) a').tab('show');
        $("#checkupdate_progress").addClass("fa fa-spinner fa-pulse");
        $('#updatestatus').attr('class', 'text-info');
        $('#updatestatus').html("{{ lang._('Updating.... (may take up to 30 seconds)') }}");

        // request status
        ajaxGet('/api/core/firmware/status',{},function(data,status){
            // update UI
            if (data['status'] == 'unknown') {
                $('#updatestatus').attr('class', 'text-warning');
            } else if (data['status'] == 'error') {
                $('#updatestatus').attr('class', 'text-danger');
            } else if (data['status'] == 'none' || data['status'] == 'ok') {
                $('#updatestatus').attr('class', 'text-info');
            }
            $('#updatestatus').html(data['status_msg']);
            $("#checkupdate_progress").removeClass("fa fa-spinner fa-pulse");

            if (data['status'] == "ok") {
                $.upgrade_action = data['status_upgrade_action'];
                if (data['status_upgrade_action'] != 'pkg') {
                    $.upgrade_needs_reboot = data['upgrade_needs_reboot'];
                } else {
                    $.upgrade_needs_reboot = 0 ;
                }

                // unhide upgrade button
                $("#upgrade").attr("style","");
                // show upgrade list
                $("#updatelist").html("<tr><th>{{ lang._('Package Name') }}</th>" +
                "<th>{{ lang._('Current Version') }}</th><th>{{ lang._('New Version') }}</th></tr>");
                $.each(['upgrade_packages','new_packages','reinstall_packages'], function(type_idx,type_name){
                    if ( data[type_name] != undefined ) {
                        $.each(data[type_name],function(index,row){
                            if (type_name == "new_packages") {
                                $('#updatelist').append('<tr><td>'+row['name']+'</td>' +
                                "<td><strong>{{ lang._('NEW') }}</strong></td><td>"+row['version']+"</td></tr>");
                            } else if (type_name == "reinstall_packages") {
                                $('#updatelist').append('<tr><td>'+row['name']+'</td>' +
                                "<td>"+row['version']+"</td><td><strong>{{ lang._('REINSTALL') }}</strong></td></tr>");
                            } else {
                                $('#updatelist').append('<tr><td>'+row['name']+'</td>' +
                                '<td>'+row['current_version']+'</td><td>'+row['new_version']+'</td></tr>');
                            }
                        });
                    }
                });
            }

        });
    }

    /**
     * perform upgrade, install poller to update status
     */
    function upgrade(){
        $('#maintabs li:eq(1) a').tab('show');
        $('#updatestatus').html("{{ lang._('Starting Upgrade.. Please do not leave this page while upgrade is in progress.') }}");
        $("#upgrade_progress").addClass("fa fa-spinner fa-pulse");

        ajaxCall('/api/core/firmware/upgrade',{upgrade:$.upgrade_action},function() {
            $("#upgrade_progress").removeClass("fa fa-spinner fa-pulse");
            setTimeout(trackStatus, 1000) ;
        });
    }

    /**
     *  check if a reboot is required, warn user or just upgrade
     */
    function upgrade_ui(){
        if ( $.upgrade_needs_reboot == "1" ) {
            // reboot required, inform the user.
            BootstrapDialog.show({
                type:BootstrapDialog.TYPE_WARNING,
                title: 'Reboot required',
                message: 'This upgrade may require a reboot, when needed the firewall will automatically reboot!',
                buttons: [{
                    label: 'Ok',
                    action: function(dialogRef){
                        dialogRef.close();
                        upgrade();
                    }
                },{
                    label: 'Abort',
                    action: function(dialogRef){
                        dialogRef.close();
                    }
                }]

            });

        } else {
            upgrade();
        }
    }

    /**
     * handle update status
     */
    function trackStatus(){
        ajaxGet('/api/core/firmware/upgradestatus',{},function(data, status) {
            if (data['log'] != undefined) {
                $('#update_status').html(data['log']);
                $('#update_status').scrollTop($('#update_status')[0].scrollHeight);
            }
            if (data['status'] == 'running' || data['status'] == 'error') {
                // schedule next poll
                setTimeout(trackStatus, 500);
            } else if (data['status'] == 'done') {
                $('#updatestatus').html("{{ lang._('Upgrade done!') }}");
            } else if (data['status'] == 'reboot') {
                // reboot required, tell the user to wait until this is finished and redirect after 5 minutes
                BootstrapDialog.show({
                    type:BootstrapDialog.TYPE_INFO,
                    title: "{{ lang._('Upgrade') }}",
                    message: "{{ lang._('The upgrade is finished and your device is being rebooted at the moment, please wait.') }}",
                    closable: false,
                    onshow:function(dialogRef){
                        dialogRef.setClosable(false);
                        dialogRef.getModalBody().html("{{ lang._('You will be redirected to the login page in 5 minutes.') }}");
                        setTimeout(function(){
                            dialogRef.close();
                            $(location).attr('href',"/");
                        }, 60000 * 5);
                    },
                    buttons: [{
                        label: "{{ lang._('Close') }}",
                        cssClass: 'btn-success',
                        autospin: true,
                        action: function(dialogRef){
                            dialogRef.enableButtons(false);
                        }
                    }]
                });

            }
        });
    }

    $( document ).ready(function() {
        // link event handlers
        $('#checkupdate').click(updateStatus);
        $('#upgrade').click(upgrade_ui);

    });


</script>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <strong>{{ lang._('Current Firmware Status :')}}</strong>
            <br/>
            <span class="text-info" id="updatestatus">{{ lang._('Current status is unknown')}} </span>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <button class='btn btn-primary' id="checkupdate"><i id="checkupdate_progress" class=""></i> {{ lang._('Click to check now')}}</button>
            <button class='btn btn-primary' id="upgrade" style="display:none"><i id="upgrade_progress" class=""></i> {{ lang._('Upgrade') }} </button>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <br/>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12" id="content">
            <ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
                <li class="active"><a data-toggle="tab" href="#updates">{{ lang._('Updates') }}</a></li>
                <li><a data-toggle="tab" href="#progress">{{ lang._('Progress') }}</a></li>
            </ul>
            <div class="tab-content content-box tab-content">
                <div id="updates" class="tab-pane fade in active">
                    <table class="table table-striped table-condensed table-responsive" id="updatelist">
                    </table>
                </div>
                <div id="progress" class="tab-pane fade in">
                    <textarea name="output"  id="update_status" class="form-control" rows="10" wrap="hard" readonly style="max-width:100%;"></textarea>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            &nbsp;
        </div>
    </div>
</div>
