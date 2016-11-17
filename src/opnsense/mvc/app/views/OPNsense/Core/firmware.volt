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
     * prepare for checking update status
     */
    function updateStatusPrepare() {
        $('#update_status').hide();
        $('#updatelist').show();
        $("#checkupdate_progress").addClass("fa fa-spinner fa-pulse");
        $('#updatestatus').html("{{ lang._('Checking... (may take up to 30 seconds)') }}");
    }

    /**
     * retrieve update status from backend
     */
    function updateStatus() {
        // update UI
        updateStatusPrepare();

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
                $('#update_status').hide();
                $('#updatelist').show();
                $('#updatelist').empty();
                $('#updatetab > a').tab('show');
                $("#updatelist").html("<tr><th>{{ lang._('Package Name') }}</th>" +
                "<th>{{ lang._('Current Version') }}</th><th>{{ lang._('New Version') }}</th>" +
                "<th>{{ lang._('Required Action') }}</th></tr>");
                $.each(data['all_packages'], function (index, row) {
                    $('#updatelist').append('<tr><td>'+row['name']+'</td>' +
                    '<td>'+row['old']+'</td><td>'+row['new']+'</td><td>' +
                    row['reason'] + '</td></tr>');
                });

                // update list so plugins sync as well (no logs)
                packagesInfo(false);
            } else {
                $("#upgrade").attr("style","display:none");

                // update list so plugins sync as well (all)
                packagesInfo(true);
            }
        });
    }

    /**
     * perform upgrade, install poller to update status
     */
    function upgrade() {
        $('#updatelist').hide();
        $('#update_status').show();
        $('#updatetab > a').tab('show');
        $('#updatestatus').html("{{ lang._('Upgrading...') }}");
        $("#upgrade").attr("style","");
        $("#upgrade_progress").addClass("fa fa-spinner fa-pulse");

        ajaxCall('/api/core/firmware/upgrade',{upgrade:$.upgrade_action},function() {
            $('#updatelist').empty();
            setTimeout(trackStatus, 500);
        });
    }

    /**
     * read changelog from backend
     */
    function changelog(version)
    {
        ajaxCall('/api/core/firmware/changelog/' + version, {}, function (data, status) {
            if (data['html'] != undefined) {
                BootstrapDialog.show({
                    type:BootstrapDialog.TYPE_PRIMARY,
                    title: version,
                    /* we trust this data, it was signed by us and secured by csrf */
                    message: htmlDecode(data['html']),
                    buttons: [{
                        label: "{{ lang._('Close') }}",
                        action: function(dialogRef){
                            dialogRef.close();
                        }
                    }]
                });
            }
        });
    }

    /**
     * perform package action, install poller to update status
     */
    function action(pkg_act, pkg_name)
    {
        $('#updatelist').hide();
        $('#update_status').show();
        $('#updatetab > a').tab('show');
        $('#updatestatus').html("{{ lang._('Executing...') }}");

        ajaxCall('/api/core/firmware/'+pkg_act+'/'+pkg_name,{},function() {
            $('#updatelist').empty();
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
                if ($.upgrade_action != 'pkg') {
                    $('#updatestatus').html("{{ lang._('Upgrade done!') }}");
                } else {
                    $('#updatestatus').html("{{ lang._('Package manager update done. Please check for more updates.') }}");
                }
                $("#upgrade").attr("style","display:none");
                packagesInfo(true);
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
                        setTimeout(rebootWait, 45000);
                    },
                });
            } else {
                // schedule next poll
                setTimeout(trackStatus, 500);
            }
        }).fail(function () {
            // recover from temporary errors
            setTimeout(trackStatus, 500);
        });
    }

    /**
     * show package info
     */
    function packagesInfo(changelog_display) {
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

            if (!data['local'].length) {
                $('#packageslist').append(
                    '<tr><td colspan=5>{{ lang._('No packages were found on your system. Please call for help.') }}</td></tr>'
                );
            }

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
                    '<tr><td colspan=5>{{ lang._('Check for updates to view available plugins.') }}</td></tr>'
                );
            }

            if (changelog_display) {
                $('#updatelist').empty();

                $("#updatelist").html("<tr><th>{{ lang._('Version') }}</th>" +
                "<th>{{ lang._('Date') }}</th><th></th></tr>");

                installed_version = data['product_version'].replace(/[_-].*/, '');

                $.each(data['changelog'], function(index, row) {
                    installed_text = '';
                    if (installed_version == row['version']) {
                        installed_text = ' ({{ lang._('installed') }})';
                    }
                    $('#updatelist').append(
                        '<tr><td>' + row['version'] + installed_text + '</td>' +
                        '<td>' + row['date'] + '</td>' +
                        '<td><button class="btn btn-default btn-xs act_changelog" data-version="' + row['version'] + '" ' +
                        'data-toggle="tooltip" title="View ' + row['version'] + '">' +
                        '<span class="fa fa-book"></span></button></td></tr>'
                    );
                });

                if (!data['changelog'].length) {
                    $('#updatelist').append(
                        '<tr><td colspan=3>{{ lang._('Check for updates to view changelog history.') }}</td></tr>'
                    );
                }
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
            $(".act_changelog").click(function(event) {
                event.preventDefault();
                changelog($(this).data('version'));
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

        // populate package information
        packagesInfo(true);

        ajaxGet('/api/core/firmware/running',{},function(data, status) {
            // if action is already running reattach now...
            if (data['status'] == 'busy') {
                upgrade();
            // dashboard link: run check automatically
            } else if (window.location.hash == '#checkupdate') {
                // update UI and delay update to avoid races
                updateStatusPrepare();
                setTimeout(updateStatus, 1000);
            }
        });

        // handle firmware config options
        ajaxGet('/api/core/firmware/getFirmwareOptions',{},function(firmwareoptions, status) {
            ajaxGet('/api/core/firmware/getFirmwareConfig',{},function(firmwareconfig, status) {
                var other_selected = true;
                $.each(firmwareoptions.mirrors, function(key, value) {
                    var selected = false;
                    if ((key != "" && firmwareconfig['mirror'].indexOf(key) == 0) || key == firmwareconfig['mirror']) {
                        selected = true;
                        other_selected = false;
                    }
                    $("#firmware_mirror").append($("<option/>")
                            .attr("value",key)
                            .text(value)
                            .data("has_subscription", firmwareoptions['has_subscription'].indexOf(key) == 0)
                            .prop('selected', selected)
                    );
                });
                $("#firmware_mirror").prepend($("<option/>")
                        .attr("value", firmwareconfig['mirror'])
                        .text("(other)")
                        .data("other", 1)
                        .data("has_subscription", false)
                        .prop('selected', other_selected)
                );

                if ($("#firmware_mirror option:selected").data("has_subscription") == true) {
                    $("#firmware_mirror_subscription").val(firmwareconfig['mirror'].substr($("#firmware_mirror").val().length+1));
                } else {
                    $("#firmware_mirror_subscription").val("");
                }
                $("#firmware_mirror").selectpicker('refresh');
                $("#firmware_mirror").change();

                other_selected = true;
                $.each(firmwareoptions.flavours, function(key, value) {
                    var selected = false;
                    if (key == firmwareconfig['flavour']) {
                        selected = true;
                        other_selected = false;
                    }
                    $("#firmware_flavour").append($("<option/>")
                            .attr("value",key)
                            .text(value)
                            .prop('selected', selected)
                    );
                });
                $("#firmware_flavour").prepend($("<option/>")
                        .attr("value",firmwareconfig['flavour'])
                        .text("(other)")
                        .data("other", 1)
                        .prop('selected', other_selected)
                );
                $("#firmware_flavour").selectpicker('refresh');
                $("#firmware_flavour").change();
            });
        });

        $("#firmware_mirror").change(function(){
            $("#firmware_mirror_value").val($(this).val());
            if ($(this).find(':selected').data("other") == 1) {
                $("#firmware_mirror_other").show();
            } else {
                $("#firmware_mirror_other").hide();
            }
            if ($("#firmware_mirror option:selected").data("has_subscription") == true) {
                $("#firmware_mirror_subscription").parent().parent().show();
            } else {
                $("#firmware_mirror_subscription").parent().parent().hide();
            }
        });
        $("#firmware_flavour").change(function() {
            $("#firmware_flavour_value").val($(this).val());
            if ($(this).find(':selected').data("other") == 1) {
                $("#firmware_flavour_other").show();
            } else {
                $("#firmware_flavour_other").hide();
            }
        });

        $("#change_mirror").click(function(){
            $("#change_mirror_progress").addClass("fa fa-spinner fa-pulse");
            var confopt = {};
            confopt.mirror = $("#firmware_mirror_value").val();
            confopt.flavour = $("#firmware_flavour_value").val();
            if ($("#firmware_mirror option:selected").data("has_subscription") == true) {
                confopt.subscription = $("#firmware_mirror_subscription").val();
            } else {
                confopt.subscription = null;
            }
            ajaxCall(url='/api/core/firmware/setFirmwareConfig',sendData=confopt, callback=function(data,status) {
                $("#change_mirror_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });

        // update history on tab state and implement navigation
        if(window.location.hash != "") {
            $('a[href="' + window.location.hash + '"]').click()
        }
        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            history.pushState(null, null, e.target.hash);
        });
        $(window).on('hashchange', function(e) {
            $('a[href="' + window.location.hash + '"]').click()
        });
    });
</script>

<div class="container-fluid">
    <div class="row">
        <div id="message" style="display:none" class="alert alert-warning" role="alert"><?= @file_get_contents('/usr/local/opnsense/firmware-message') ?></div>
        <div class="alert alert-info" role="alert" style="min-height: 65px;">
            <button class='btn btn-primary pull-right' id="upgrade" style="display:none"><i id="upgrade_progress" class=""></i> {{ lang._('Upgrade now') }}</button>
            <button class='btn btn-default pull-right' id="checkupdate" style="margin-right: 8px;"><i id="checkupdate_progress" class=""></i> {{ lang._('Check for updates')}}</button>
            <div style="margin-top: 8px;" id="updatestatus">{{ lang._('Click to check for updates.')}}</div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12" id="content">
            <ul class="nav nav-tabs" data-tabs="tabs">
                <li id="updatetab" class="active"><a data-toggle="tab" href="#updates">{{ lang._('Updates') }}</a></li>
                <li id="packagestab"><a data-toggle="tab" href="#packages">{{ lang._('Packages') }}</a></li>
                <li id="plugintab"><a data-toggle="tab" href="#plugins">{{ lang._('Plugins') }}</a></li>
                <li id="settingstab"><a data-toggle="tab" href="#settings">{{ lang._('Settings') }}</a></li>
            </ul>
            <div class="tab-content content-box tab-content">
                <div id="updates" class="tab-pane fade in active">
                    <textarea name="output" id="update_status" class="form-control" rows="25" wrap="hard" readonly style="max-width:100%; font-family: monospace; display: none;"></textarea>
                    <table class="table table-striped table-condensed table-responsive" id="updatelist"></table>
                </div>
                <div id="packages" class="tab-pane fade in">
                    <table class="table table-striped table-condensed table-responsive" id="packageslist"></table>
                </div>
                <div id="plugins" class="tab-pane fade in">
                    <table class="table table-striped table-condensed table-responsive" id="pluginlist"></table>
                </div>
                <div id="settings" class="tab-pane fade in">
                    <table class="table table-striped table-responsive">
                        <tbody>
                            <tr>
                                <td style="width: 150px;"><a id="help_for_mirror" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> {{ lang._('Firmware Mirror') }}</td>
                                <td>
                                    <select class="selectpicker" id="firmware_mirror">
                                    </select>
                                    <div style="display:none;" id="firmware_mirror_other">
                                        <input type="text" id="firmware_mirror_value">
                                    </div>
                                    <div class="hidden" for="help_for_mirror">
                                        <strong>
                                            {{ lang._("Select an alternate firmware mirror.") }}
                                        </strong>
                                    </div>
                                </td>
                                <td></td>
                            </tr>
                            <tr>
                                <td><a id="help_for_flavour" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> {{ lang._('Firmware Flavour') }}</td>
                                <td>
                                    <select class="selectpicker" id="firmware_flavour">
                                    </select>
                                    <div style="display:none;" id="firmware_flavour_other">
                                        <input type="text" id="firmware_flavour_value">
                                    </div>
                                    <div class="hidden" for="help_for_flavour">
                                        <strong>
                                            {{ lang._("Select the firmware cryptography flavour.") }}
                                        </strong>
                                    </div>
                                </td>
                                <td></td>
                            </tr>
                            <tr>
                                <td style="width: 150px;"><a id="help_for_mirror_subscription" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> {{ lang._('Subscription') }}</td>
                                <td>
                                    <input type="text" id="firmware_mirror_subscription">
                                    <div class="hidden" for="help_for_mirror_subscription">
                                        <strong>
                                            {{ lang._("Provide subscription key.") }}
                                        </strong>
                                    </div>
                                </td>
                                <td></td>
                            </tr>
                            <tr>
                                <td></td>
                                <td>
                                    <button class="btn btn-primary" id="change_mirror" type="button"><i id="change_mirror_progress" class=""></i> {{ lang._('Save') }}</button>
                                </td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="3">
                                    {{ lang._('In order to apply these settings a firmware update must be performed after save, which can include a reboot of the system.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
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
