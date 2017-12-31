{#

Copyright (c) 2015-2017 Franco Fichtner <franco@opnsense.org>
Copyright (c) 2015-2016 Deciso B.V.
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

1.  Redistributions of source code must retain the above copyright notice,
this list of conditions and the following disclaimer.

2.  Redistributions in binary form must reproduce the above copyright notice,
this list of conditions and the following disclaimer in the documentation
and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
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
    function updateStatusPrepare(rerun) {
        if ($rerun = false) {
            $('#update_status').hide();
            $('#updatelist').show();
        }
        $("#checkupdate_progress").addClass("fa fa-spinner fa-pulse");
        $('#updatestatus').html("{{ lang._('Checking, please wait...') }}");
    }

    /**
     * retrieve update status from backend
     */
    function updateStatus() {
        // update UI
        updateStatusPrepare(false);

        // request status
        ajaxGet('/api/core/firmware/status',{},function(data,status){
            $("#checkupdate_progress").removeClass("fa fa-spinner fa-pulse");
            $('#updatestatus').html(data['status_msg']);

            if (data['status'] == "ok") {
                $.upgrade_action = data['status_upgrade_action'];
                if (data['status_upgrade_action'] != 'pkg') {
                    $.upgrade_needs_reboot = data['upgrade_needs_reboot'];
                } else {
                    $.upgrade_needs_reboot = 0;
                }

                $.upgrade_show_log = '';

                // unhide upgrade button
                $("#upgrade").attr("style","");
                $("#audit_all").attr("style","display:none");

                // show upgrade list
                $('#update_status').hide();
                $('#updatelist').show();
                $('#updatelist > tbody').empty();
                $('#updatetab > a').tab('show');
                $("#updatelist > thead").html("<tr><th>{{ lang._('Package Name') }}</th>" +
                "<th>{{ lang._('Current Version') }}</th><th>{{ lang._('New Version') }}</th>" +
                "<th>{{ lang._('Required Action') }}</th></tr>");
                $.each(data['all_packages'], function (index, row) {
                    $('#updatelist > tbody').append('<tr><td>'+row['name']+'</td>' +
                    '<td>'+row['old']+'</td><td>'+row['new']+'</td><td>' +
                    row['reason'] + '</td></tr>');

                    if (row['name'] == data['product_name']) {
                        $.upgrade_show_log = row['new'].replace(/[_-].*/, '');
                    }
                });

                // display the current changelog if one was found
                if ($.upgrade_show_log != '') {
                    changelog($.upgrade_show_log);
                }

                // update list so plugins sync as well (no logs)
                packagesInfo(false);
            } else {
                $('#update_status').hide();
                $('#updatelist').show();
                $("#upgrade").attr("style","display:none");
                $("#audit_all").attr("style","");

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
        $('#updatestatus').html("{{ lang._('Updating, please wait...') }}");
        $("#audit_all").attr("style","display:none");
        maj_suffix = '';
        if ($.upgrade_action == 'maj') {
            maj_suffix = '_maj';
        }
        $("#upgrade" + maj_suffix).attr("style","");
        $("#upgrade_progress" + maj_suffix).addClass("fa fa-spinner fa-pulse");

        ajaxCall('/api/core/firmware/upgrade',{upgrade:$.upgrade_action},function() {
            $('#updatelist > tbody, thead').empty();
            setTimeout(trackStatus, 500);
        });
    }

    /**
     * perform audit, install poller to update status
     */
    function audit($type) {
        $.upgrade_action = $type;
        $('#updatelist').hide();
        $('#update_status').show();
        $('#updatetab > a').tab('show');
        $('#updatestatus').html("{{ lang._('Auditing, please wait...') }}");
        $("#audit_all").attr("style","");
        $("#audit_progress").removeClass("caret");
        $("#audit_progress").addClass("fa fa-spinner fa-pulse");

        ajaxCall('/api/core/firmware/' + $type, {}, function () {
            $('#updatelist > tbody, thead').empty();
            setTimeout(trackStatus, 500);
        });
    }

    /**
     * read package details from backend
     */
    function details(package)
    {
        ajaxCall('/api/core/firmware/details/' + package, {}, function (data, status) {
            var details = "{{ lang._('Sorry, plugin details are currently not available.') }}";
            if (data['details'] != undefined) {
                details = data['details'];
            }
            stdDialogInform("{{ lang._('Plugin details') }}", details, "{{ lang._('Close') }}");
        });
    }

    /**
     * read license from backend
     */
    function license(package)
    {
        ajaxCall('/api/core/firmware/license/' + package, {}, function (data, status) {
            var license = "{{ lang._('Sorry, the package does not have an associated license file.') }}";
            if (data['license'] != undefined) {
                license = data['license'];
            }
            stdDialogInform("{{ lang._('License details') }}", license, "{{ lang._('Close') }}");
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
     * perform package action that requires reboot confirmation
     */
    function action_may_reboot(pkg_act, pkg_name)
    {
        if (pkg_act == 'reinstall' && (pkg_name == 'kernel' || pkg_name == 'base')) {
            reboot_msg = "{{ lang._('The firewall will reboot directly after this set reinstall.') }}";

            // reboot required, inform the user.
            BootstrapDialog.show({
                type:BootstrapDialog.TYPE_WARNING,
                title: "{{ lang._('Reboot required') }}",
                message: reboot_msg,
                buttons: [{
                    label: "{{ lang._('OK') }}",
                    cssClass: 'btn-warning',
                    action: function(dialogRef){
                        dialogRef.close();
                        action(pkg_act, pkg_name);
                    }
                },{
                    label: "{{ lang._('Abort') }}",
                    action: function(dialogRef){
                        dialogRef.close();
                    }
                }]
            });
        } else {
            action(pkg_act, pkg_name);
        }
    }

    /**
     * perform package action, install poller to update status
     */
    function action(pkg_act, pkg_name)
    {
        $('#updatelist').hide();
        $('#update_status').show();
        $('#updatetab > a').tab('show');
        $('#updatestatus').html("{{ lang._('Executing, please wait...') }}");
        $.upgrade_action = 'action';

        ajaxCall('/api/core/firmware/'+pkg_act+'/'+pkg_name,{},function() {
            $('#updatelist > tbody, thead').empty();
            setTimeout(trackStatus, 500);
        });
    }

    /**
     *  check if a reboot is required, warn user or just upgrade
     */
    function upgrade_ui()
    {
        if ( $.upgrade_needs_reboot == "1" ) {
            reboot_msg = "{{ lang._('The firewall will reboot directly after this firmware update.') }}";
            if ($.upgrade_action == 'maj') {
                reboot_msg = "{{ lang._('The firewall will download all firmware sets and reboot multiple times for this upgrade. All operating system files and packages will be reinstalled as a consequence. This may take several minutes to complete.') }}";
            }
            // reboot required, inform the user.
            BootstrapDialog.show({
                type:BootstrapDialog.TYPE_WARNING,
                title: "{{ lang._('Reboot required') }}",
                message: reboot_msg,
                buttons: [{
                    label: "{{ lang._('OK') }}",
                    cssClass: 'btn-warning',
                    action: function(dialogRef){
                        dialogRef.close();
                        upgrade();
                    }
                },{
                    label: "{{ lang._('Abort') }}",
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
            url: '/',
            timeout: 2500
        }).fail(function () {
            setTimeout(rebootWait, 2500);
        }).done(function () {
            $(location).attr('href', '/');
        });
    }

    /**
     * handle check/audit/upgrade status
     */
    function trackStatus() {
        ajaxGet('/api/core/firmware/upgradestatus',{},function(data, status) {
            if (data['log'] != undefined) {
                $('#update_status').html(data['log']);
                $('#update_status').scrollTop($('#update_status')[0].scrollHeight);
            }
            if (data['status'] == 'done') {
                $("#upgrade_progress_maj").removeClass("fa fa-spinner fa-pulse");
                $("#upgrade_progress").removeClass("fa fa-spinner fa-pulse");
                $("#audit_progress").removeClass("fa fa-spinner fa-pulse");
                $("#audit_progress").addClass("caret");
                $("#upgrade_maj").attr("style","display:none");
                $("#upgrade").attr("style","display:none");
                $("#audit_all").attr("style","");
                if ($.upgrade_action == 'pkg') {
                    // update UI and delay update to avoid races
                    updateStatusPrepare(true);
                    setTimeout(updateStatus, 1000);
                } else {
                    if ($.upgrade_action == 'audit') {
                        $('#updatestatus').html("{{ lang._('Audit done.') }}");
                    } else if ($.upgrade_action == 'action') {
                        $('#updatestatus').html("{{ lang._('Action done.') }}");
                    } else {
                        $('#updatestatus').html("{{ lang._('Upgrade done.') }}");
                    }
                    packagesInfo(true);
                }
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
            $('#packageslist > tbody').empty();
            $('#pluginlist > tbody').empty();
            var installed = {};

            var local_count = 0;
            var plugin_count = 0;
            var changelog_count = 0;
            var changelog_max = 12;
            if ($.changelog_keep_full != undefined) {
                changelog_max = 9999;
            }

            $.each(data['package'], function(index, row) {
                if (row['installed'] == "1") {
                    local_count += 1;
                } else {
                    return 1;
                }
                $('#packageslist > tbody').append(
                    '<tr>' +
                    '<td>' + row['name'] + '</td>' +
                    '<td>' + row['version'] + '</td>' +
                    '<td>' + row['flatsize'] + '</td>' +
                    '<td>' + row['license'] + '</td>' +
                    '<td>' + row['comment'] + '</td>' +
                    '<td>' +
                    '<button class="btn btn-default btn-xs act_license" data-package="' + row['name'] + '" ' +
                    '  data-toggle="tooltip" title="View ' + row['name'] + ' license">' +
                    '<span class="fa fa-balance-scale"></span></button> ' +
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
            });

            if (local_count == 0) {
                $('#packageslist > tbody').append(
                    '<tr><td colspan=5>{{ lang._('No packages were found on your system. Please call for help.') }}</td></tr>'
                );
            }

            $.each(data['plugin'], function(index, row) {
                if (row['provided'] == "1") {
                    plugin_count += 1;
                }
                status_text = '';
                bold_on = '';
                bold_off = '';
                if (row['installed'] == "1") {
                    status_text = ' ({{ lang._('installed') }})';
                    bold_on = '<b>';
                    bold_off = '</b>';
                }
                if (row['provided'] == "0") {
                    // this state overwrites installed on purpose
                    status_text = ' ({{ lang._('orphaned') }})';
                }
                $('#pluginlist > tbody').append(
                    '<tr>' + '<td>' + bold_on + row['name'] + status_text + bold_off + '</td>' +
                    '<td>' + bold_on + row['version'] + bold_off + '</td>' +
                    '<td>' + bold_on + row['flatsize'] + bold_off + '</td>' +
                    '<td>' + bold_on + row['comment'] + bold_off + '</td>' +
                    '<td><button class="btn btn-default btn-xs act_details" data-package="' + row['name'] + '" ' +
                        ' data-toggle="tooltip" title="More about ' + row['name'] + '">' +
                        '<span class="fa fa-info-circle"></span></button>' +
                        (row['installed'] == "1" ?
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

            if (plugin_count == 0) {
                $('#pluginlist > tbody').append(
                    '<tr><td colspan=5>{{ lang._('Check for updates to view available plugins.') }}</td></tr>'
                );
            }

            if (changelog_display) {
                $("#updatelist > tbody").empty();
                $("#updatelist > thead").html("<tr><th>{{ lang._('Version') }}</th>" +
                "<th>{{ lang._('Date') }}</th><th></th></tr>");

                installed_version = data['product_version'].replace(/[_-].*/, '');

                $.each(data['changelog'], function(index, row) {
                    changelog_count += 1;

                    status_text = '';
                    bold_on = '';
                    bold_off = '';

                    if (installed_version == row['version']) {
                        status_text = ' ({{ lang._('installed') }})';
                        bold_on = '<b>';
                        bold_off = '</b>';
                    }

                    $('#updatelist > tbody').append(
                        '<tr' + (changelog_count > changelog_max ? ' class="changelog-hidden" style="display: none;" ' : '' ) +
                        '><td>' + bold_on + row['version'] + status_text + bold_off + '</td><td>' + bold_on + row['date'] + bold_off + '</td>' +
                        '<td><button class="btn btn-default btn-xs act_changelog" data-version="' + row['version'] + '" ' +
                        'data-toggle="tooltip" title="View ' + row['version'] + '">' +
                        '<span class="fa fa-book"></span></button></td></tr>'
                    );
                });

                if (!data['changelog'].length) {
                    $('#updatelist > tbody').append(
                        '<tr><td colspan=3>{{ lang._('Check for updates to view changelog history.') }}</td></tr>'
                    );
                }

                if (changelog_count > changelog_max) {
                    $('#updatelist > tbody').append(
                        '<tr class= "changelog-full"><td colspan=3><a id="changelog-act" href="#">{{ lang._('Click to view full changelog history.') }}</a></td></tr>'
                    );
                    $("#changelog-act").click(function(event) {
                        event.preventDefault();
                        $(".changelog-hidden").attr('style', '');
                        $(".changelog-full").attr('style', 'display: none;');
                        $.changelog_keep_full = 1;
                    });
                }
            }

            // link buttons to actions
            $(".act_reinstall").click(function(event) {
                event.preventDefault();
                action_may_reboot('reinstall', $(this).data('package'));
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
            $(".act_details").click(function(event) {
                event.preventDefault();
                details($(this).data('package'));
            });
            $(".act_install").click(function(event) {
                event.preventDefault();
                action('install', $(this).data('package'));
            });
            $(".act_changelog").click(function(event) {
                event.preventDefault();
                changelog($(this).data('version'));
            });
            $(".act_license").click(function(event) {
                event.preventDefault();
                license($(this).data('package'));
            });
            // attach tooltip to generated buttons
            $('[data-toggle="tooltip"]').tooltip();
        });
    }

    $( document ).ready(function() {
        // link event handlers
        $('#checkupdate').click(updateStatus);
        $('#upgrade').click(upgrade_ui);
        $('#audit').click(function () { audit('audit'); });
        $('#health').click(function () { audit('health'); });
        $('#upgrade_maj').click(function () {
            $.upgrade_needs_reboot = 1;
            $.upgrade_action = 'maj';
            upgrade_ui();
        });
        $('#checkupdate_maj').click(function () {
            $("#checkupdate_progress_maj").addClass("fa fa-spinner fa-pulse");
            // empty call refreshes changelogs in the background
            ajaxCall('/api/core/firmware/changelog/update', {}, function () {
                $("#checkupdate_progress_maj").removeClass("fa fa-spinner fa-pulse");
                BootstrapDialog.show({
                    type:BootstrapDialog.TYPE_WARNING,
                    title: "{{ lang._('Upgrade instructions') }}",
                    message: $('#firmware-message').html(),
                    buttons: [{
<?php if (file_exists('/usr/local/opnsense/firmware-upgrade')): ?>
                        label: "{{ lang._('Unlock upgrade') }}",
                        cssClass: 'btn-warning',
                        action: function (dialogRef) {
                            dialogRef.close();
                            $("#upgrade_maj").attr("style","");
                            changelog($('#firmware-upgrade').text());
                        }
                    },{
<?php endif ?>
                        label: "{{ lang._('Close') }}",
                        action: function (dialogRef) {
                            dialogRef.close();
                        }
                    }]
                });
                packagesInfo(true);
            });
        });

        // populate package information
        packagesInfo(true);

        ajaxGet('/api/core/firmware/running',{},function(data, status) {
            // if action is already running reattach now...
            if (data['status'] == 'busy') {
                upgrade();
            // dashboard link: run check automatically
            } else if (window.location.hash == '#checkupdate') {
                // update UI and delay update to avoid races
                updateStatusPrepare(false);
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

                $.each(firmwareoptions.families, function(key, value) {
                    var selected = false;
                    if (key == firmwareconfig['type']) {
                        selected = true;
                    }
                    $("#firmware_type").append($("<option/>")
                            .attr("value",key)
                            .text(value)
                            .prop('selected', selected)
                    );
                });
                $("#firmware_type").selectpicker('refresh');
                $("#firmware_type").change();
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
            confopt.type = $("#firmware_type").val();
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
<?php if (file_exists('/usr/local/opnsense/firmware-message')): ?>
        <div id="firmware-upgrade" style="display:none;"><?= @file_get_contents('/usr/local/opnsense/firmware-upgrade') ?></div>
        <div id="firmware-message" style="display:none;"><?= str_replace(PHP_EOL, ' ', @file_get_contents('/usr/local/opnsense/firmware-message')) ?></div>
        <div class="alert alert-warning" role="alert" style="min-height: 65px;">
            <button class='btn btn-primary pull-right' id="upgrade_maj" style="display:none;">{{ lang._('Upgrade now') }} <i id="upgrade_progress_maj"></i> </button>
            <button class='btn pull-right' id="checkupdate_maj" style="margin-right: 8px;">{{ lang._('Check for upgrade') }} <i id="checkupdate_progress_maj"></i></button>
            <div style="margin-top: 8px;">{{ lang._('This software release has reached its designated end of life.') }}</div>
        </div>
<?php endif ?>
        <div class="alert alert-info" role="alert" style="min-height: 65px;">
            <button class='btn btn-primary pull-right' id="upgrade" style="display:none">{{ lang._('Update now') }} <i id="upgrade_progress"></i></button>
            <div class="btn-group pull-right">
                <button type="button" id="audit_all" class="btn btn-primary dropdown-toggle" data-toggle="dropdown">
                    {{ lang._('Audit now') }} <i id="audit_progress" class="caret"></i>
                </button>
                <ul class="dropdown-menu" role="menu">
                    <li><a id="audit" href="#">Security</a></li>
                    <li><a id="health" href="#">Health</a></li>
                </ul>
            </div>
            <button class='btn btn-default pull-right' id="checkupdate" style="margin-right: 8px;">{{ lang._('Check for updates') }} <i id="checkupdate_progress"></i></button>
            <div style="margin-top: 8px;" id="updatestatus">{{ lang._('Click to check for updates.')}}</div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12" id="content">
            <ul class="nav nav-tabs" data-tabs="tabs">
                <li id="updatetab" class="active"><a data-toggle="tab" href="#updates">{{ lang._('Updates') }}</a></li>
                <li id="plugintab"><a data-toggle="tab" href="#plugins">{{ lang._('Plugins') }}</a></li>
                <li id="packagestab"><a data-toggle="tab" href="#packages">{{ lang._('Packages') }}</a></li>
                <li id="settingstab"><a data-toggle="tab" href="#settings">{{ lang._('Settings') }}</a></li>
            </ul>
            <div class="tab-content content-box tab-content">
                <div id="updates" class="tab-pane fade in active">
                    <textarea name="output" id="update_status" class="form-control" rows="25" wrap="hard" readonly="readonly" style="max-width:100%; font-family: monospace; display: none;"></textarea>
                    <table class="table table-striped table-condensed table-responsive" id="updatelist">
                        <thead>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div id="plugins" class="tab-pane fade in">
                    <table class="table table-striped table-condensed table-responsive" id="pluginlist">
                      <thead>
                        <tr>
                          <th>{{ lang._('Name') }}</th>
                          <th>{{ lang._('Version') }}</th>
                          <th>{{ lang._('Size') }}</th>
                          <th>{{ lang._('Comment') }}</th>
                          <th></th>
                        </tr>
                      </thead>
                      <tbody>
                      </tbody>
                    </table>
                </div>
                <div id="packages" class="tab-pane fade in">
                    <table class="table table-striped table-condensed table-responsive" id="packageslist">
                        <thead>
                          <tr>
                              <th>{{ lang._('Name') }}</th>
                              <th>{{ lang._('Version') }}</th>
                              <th>{{ lang._('Size') }}</th>
                              <th>{{ lang._('License') }}</th>
                              <th>{{ lang._('Comment') }}</th>
                              <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
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
                                    <output class="hidden" for="help_for_mirror">
                                        {{ lang._('Select an alternate firmware mirror.') }}
                                    </output>
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
                                    <output class="hidden" for="help_for_flavour">
                                        {{ lang._('Select the firmware cryptography flavour.') }}
                                    </output>
                                </td>
                                <td></td>
                            </tr>
                            <tr>
                                <td><a id="help_for_type" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> {{ lang._('Release Type') }}</td>
                                <td>
                                    <select class="selectpicker" id="firmware_type">
                                    </select>
                                    <output class="hidden" for="help_for_type">
                                        {{ lang._('Select the release type. Use with care.') }}
                                    </output>
                                </td>
                                <td></td>
                            </tr>
                            <tr>
                                <td style="width: 150px;"><a id="help_for_mirror_subscription" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> {{ lang._('Subscription') }}</td>
                                <td>
                                    <input type="text" id="firmware_mirror_subscription">
                                    <output class="hidden" for="help_for_mirror_subscription">
                                        {{ lang._('Provide subscription key.') }}
                                    </output>
                                </td>
                                <td></td>
                            </tr>
                            <tr>
                                <td></td>
                                <td>
                                    <button class="btn btn-primary" id="change_mirror" type="button">{{ lang._('Save') }} <i id="change_mirror_progress"></i></button>
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
