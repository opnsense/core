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
        $('#updatelist').empty();
        $('#updatetab > a').tab('show');
        $("#checkupdate_progress").addClass("fa fa-spinner fa-pulse");
        $('#updatestatus').html("{{ lang._('Fetching... (may take up to 30 seconds)') }}");

        // request status
        ajaxGet('/api/core/firmware/status',{},function(data,status){
            $("#checkupdate_progress").removeClass("fa fa-spinner fa-pulse");
            $('#updatestatus').html(data['status_msg']);

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
                $.each(['new_packages', 'upgrade_packages', 'reinstall_packages'], function(type_idx,type_name){
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

            // update list so plugins sync as well
            packagesInfo();
        });
    }

    /**
     * perform upgrade, install poller to update status
     */
    function upgrade(){
        $('#progresstab > a').tab('show');
        $('#updatestatus').html("{{ lang._('Upgrading... (do not leave this page while upgrade is in progress)') }}");
        $("#upgrade_progress").addClass("fa fa-spinner fa-pulse");

        ajaxCall('/api/core/firmware/upgrade',{upgrade:$.upgrade_action},function() {
            $('#updatelist').empty();
            setTimeout(trackStatus, 500);
        }).fail(function () {
            setTimeout(trackStatus, 500);
        });
    }

    /**
     * perform package action, install poller to update status
     */
    function action(pkg_act, pkg_name)
    {
        $('#progresstab > a').tab('show');
        $('#updatestatus').html("{{ lang._('Executing... (do not leave this page while execute is in progress)') }}");

        ajaxCall('/api/core/firmware/'+pkg_act+'/'+pkg_name,{},function() {
            $('#updatelist').empty();
            setTimeout(trackStatus, 500);
        }).fail(function () {
            setTimeout(trackStatus, 500);
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
                message: 'The firewall will be rebooted directly after this firmware update.',
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

    function rebootWait() {
        $.ajax({
            url: document.url,
            timeout: 2500
        }).fail(function () {
            setTimeout(rebootWait, 2500);
        }).done(function () {
            $(location).attr('href',"/");
        });
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
            if (data['status'] == 'done') {
                $("#upgrade_progress").removeClass("fa fa-spinner fa-pulse");
                $('#updatestatus').html("{{ lang._('Upgrade done!') }}");
                $("#upgrade").attr("style","display:none");
                packagesInfo();
            } else if (data['status'] == 'reboot') {
                BootstrapDialog.show({
                    type:BootstrapDialog.TYPE_INFO,
                    title: "{{ lang._('Your device is rebooting') }}",
                    closable: false,
                    onshow:function(dialogRef){
                        dialogRef.setClosable(false);
                        dialogRef.getModalBody().html(
                            "{{ lang._('The upgrade has finished and your device is being rebooted at the moment, please wait...') }}" +
                            ' <i class="fa fa-cog fa-spin"></i>'
                        );
                        setTimeout(rebootWait, 30000);
                    },
                });
            } else {
                // schedule next poll
                setTimeout(trackStatus, 500);
            }
        });
    }

    /**
     * show package info
     */
    function packagesInfo() {
        ajaxGet('/api/core/firmware/info', {}, function (data, status) {
            $('#packageslist').empty();
            $('#pluginlist').empty();
            var installed = {};

            $("#packageslist").html("<tr><th>{{ lang._('Name') }}</th>" +
            "<th>{{ lang._('Version') }}</th><th>{{ lang._('Size') }}</th>" +
            "<th>{{ lang._('Comment') }}</th><th></th></tr>");
            $("#pluginlist").html("<tr><th>{{ lang._('Name') }}</th>" +
            "<th>{{ lang._('Version') }}</th><th>{{ lang._('Size') }}</th>" +
            "<th>{{ lang._('Comment') }}</th><th></th></tr>");

            $.each(data['local'], function(index, row) {
                $('#packageslist').append(
                    '<tr>' +
                    '<td>' + row['name'] + '</td>' +
                    '<td>' + row['version'] + '</td>' +
                    '<td>' + row['flatsize'] + '</td>' +
                    '<td>' + row['comment'] + '</td>' +
                    '<td>' +
                    '<button class="btn btn-default btn-xs act_reinstall" data-package="' + row['name'] + '" ' +
                    '  data-toggle="tooltip" title="Reinstall ' + row['name'] + '">' +
                    '<span class="fa fa-recycle"></span></button> ' + (row['locked'] === '1' ?
                        '<button data-toggle="tooltip" title="Unlock ' + row['name'] + '" class="btn btn-default btn-xs act_unlock" data-package="' + row['name'] + '">' +
                        '<span class="fa fa-lock">' +
                        '</span></button>' :
                        '<button class="btn btn-default btn-xs act_lock" data-package="' + row['name'] + '" ' +
                        '  data-toggle="tooltip" title="Lock ' + row['name'] + '" >' +
                        '<span class="fa fa-unlock"></span></button>'
                    ) + '</td>' +
                    '</tr>'
                );
                if (!row['name'].match(/^os-/g)) {
                    return 1;
                }
                installed[row['name']] = row;
            });

            $.each(data['remote'], function(index, row) {
                if (!row['name'].match(/^os-/g)) {
                    return 1;
                }
                $('#pluginlist').append(
                    '<tr>' + '<td>' + row['name'] + '</td>' +
                    '<td>' + row['version'] + '</td>' +
                    '<td>' + row['flatsize'] + '</td>' +
                    '<td>' + row['comment'] + '</td>' +
                    '<td>' + (row['name'] in installed ?
                        '<button class="btn btn-default btn-xs act_remove" data-package="' + row['name'] + '" '+
                        '  data-toggle="tooltip" title="Remove ' + row['name'] + '">' +
                        '<span class="fa fa-trash">' +
                        '</span></button>' :
                        '<button class="btn btn-default btn-xs act_install" data-package="' + row['name'] + '" ' +
                        ' data-toggle="tooltip" title="Install ' + row['name'] + '">' +
                        '<span class="fa fa-plus">' +
                        '</span></button>'
                    ) + '</td>' + '</tr>'
                );
            });

            if (!data['remote'].length) {
                $('#pluginlist').append(
                    '<tr><td colspan=5>{{ lang._('Fetch updates to view available plugins.') }}</td></tr>'
                );
            }

            // link buttons to actions
            $(".act_reinstall").click(function(event) {
                event.preventDefault();
                action('reinstall', $(this).data('package'));
            });
            $(".act_unlock").click(function(event) {
                event.preventDefault();
                action('unlock', $(this).data('package'));
            });
            $(".act_lock").click(function(event) {
                event.preventDefault();
                action('lock', $(this).data('package'));
            });
            $(".act_remove").click(function(event) {
                event.preventDefault();
                action('remove', $(this).data('package'));
            });
            $(".act_install").click(function(event) {
                event.preventDefault();
                action('install', $(this).data('package'));
            });
            // attach tooltip to generated buttons
            $('[data-toggle="tooltip"]').tooltip();
        });
    }

    $( document ).ready(function() {
        // link event handlers
        $('#checkupdate').click(updateStatus);
        $('#upgrade').click(upgrade_ui);
        // show upgrade message if there
        if ($('#message').html() != '') {
            $('#message').attr('style', '');
        }
        // repopulate package information
        packagesInfo();
        // dashboard link: run check automatically
        if (window.location.hash == '#checkupdate') {
            updateStatus();
        }
    });

</script>

<div class="container-fluid">
    <div class="row">
        <div id="message" style="display:none" class="alert alert-warning" role="alert"><?= @file_get_contents('/usr/local/opnsense/firmware-message') ?></div>
        <div class="alert alert-info" role="alert" style="min-height: 65px;">
            <button class='btn btn-primary pull-right' id="upgrade" style="display:none"><i id="upgrade_progress" class=""></i> {{ lang._('Upgrade now') }}</button>
            <button class='btn btn-default pull-right' id="checkupdate" style="margin-right: 8px;"><i id="checkupdate_progress" class=""></i> {{ lang._('Fetch updates')}}</button>
            <div style="margin-top: 8px;" id="updatestatus">{{ lang._('Click to check for updates.')}}</div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12" id="content">
            <ul class="nav nav-tabs" data-tabs="tabs">
                <li id="packagestab" class="active"><a data-toggle="tab" href="#packages">{{ lang._('Packages') }}</a></li>
                <li id="plugintab"><a data-toggle="tab" href="#plugins">{{ lang._('Plugins') }}</a></li>
                <li id="updatetab"><a data-toggle="tab" href="#updates">{{ lang._('Updates') }}</a></li>
                <li id="progresstab"><a data-toggle="tab" href="#progress">{{ lang._('Progress') }}</a></li>
            </ul>
            <div class="tab-content content-box tab-content">
                <div id="packages" class="tab-pane fade in active">
                    <table class="table table-striped table-condensed table-responsive" id="packageslist">
                    </table>
                </div>
                <div id="plugins" class="tab-pane fade in">
                    <table class="table table-striped table-condensed table-responsive" id="pluginlist">
                    </table>
                </div>
                <div id="updates" class="tab-pane fade in">
                    <table class="table table-striped table-condensed table-responsive" id="updatelist">
                    </table>
                </div>
                <div id="progress" class="tab-pane fade in">
                    <textarea name="output" id="update_status" class="form-control" rows="20" wrap="hard" readonly style="max-width:100%; font-family: monospace;"></textarea>
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
