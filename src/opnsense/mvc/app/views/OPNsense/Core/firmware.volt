{#
 # Copyright (c) 2015-2021 Franco Fichtner <franco@opnsense.org>
 # Copyright (c) 2015-2018 Deciso B.V.
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1.  Redistributions of source code must retain the above copyright notice,
 # this list of conditions and the following disclaimer.
 #
 # 2.  Redistributions in binary form must reproduce the above copyright notice,
 # this list of conditions and the following disclaimer in the documentation
 # and/or other materials provided with the distribution.
 #
 # THIS SOFTWARE IS PROVIDED "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
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

    function generic_search(that, entries) {
        let search = $(that).val();
        $('.' + entries).each(function () {
            let name = $(this).text();
            if (search.length != 0 && name.indexOf(search) == -1) {
                $(this).hide();
            } else {
                $(this).show();
            }
        });
    }

    function cancel_update() {
        $('#updatelist').hide();
        $('#update_status_container').show();
    }

    /* XXX best effort at this point, rework later */
    function reloadMenu() {
        $.ajax({
            type: 'GET',
            url: '/ui/core/firmware',
            dataType: 'html',
            contentType: 'text/html',
            success: function (data) {
                $('#navigation').html($('#navigation', data));
            }
        });
    }

    /**
     * retrieve update status from backend
     */
    function updateStatus() {
        $('#upgrade_maj').hide();
        $('#upgrade').hide();

        ajaxGet('/api/core/firmware/status', {}, function (data, status){
            if (data['status'] == "update") {
                let show_log = '';

                $.upgrade_needs_reboot = data['upgrade_needs_reboot'];

                // show upgrade list
                $('#upgrade').show();
                $('#updatestatus').html(data['status_msg']);
                $('#updatelist > tbody').empty();
                $('#updatetab > a').tab('show');
                $.each(data['all_packages'], function (index, row) {
                    $('#updatelist > tbody').append('<tr><td>'+row['name']+'</td>' +
                    '<td>'+row['old']+'</td><td>'+row['new']+'</td><td>' +
                    row['reason']+'</td><td>'+row['repository'] + '</td></tr>');

                    if (row['name'] == data['product_target'] && row['new'] != 'N/A') {
                        show_log = row['new'].replace(/[_-].*/, '');
                    }
                });
                $('#update_status_container').hide();
                $('#updatelist').show();

                // display the current changelog if one was found
                if (show_log != '') {
                    changelog(show_log);
                }

                packagesInfo(false);
            } else if (data['status'] == "upgrade") {
                if (data['upgrade_major_message'] != '') {
                    /* we trust this data, it was signed by us and secured by csrf */
                    stdDialogInform(
                        '{{ lang._('Upgrade instructions') }}',
                        htmlDecode(data['upgrade_major_message']),
                        "{{ lang._('OK') }}",
                        function () { show_upgrade(data) },
                        'warning'
                    );
                } else {
                    show_upgrade(data);
                }

                packagesInfo(false);
            } else if (data['status'] == "error") {
                stdDialogInform('{{ lang._('Firmware status') }}', data['status_msg'], "{{ lang._('Close') }}", undefined, 'danger');
                packagesInfo(true);
            } else if (data['status'] == "none") {
                stdDialogInform('{{ lang._('Firmware status') }}', data['status_msg'], "{{ lang._('Close') }}", undefined, 'success');
                packagesInfo(true);
            } else {
                // in case new responses are added
                console.log('Unknown check response');
                console.log(data);
            }
        });
    }

    /**
     * perform backend action and install poller to update status
     */
    function backend(type) {
        $.upgrade_check = type == 'check'

        $('#update_status').html('');
        $('#updatelist').hide();
        $('#update_status_container').show();
        $('#updatetab > a').tab('show');
        $('#updatetab_progress').addClass("fa fa-spinner fa-pulse");

        ajaxCall('/api/core/firmware/' + type, {}, function () {
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
                /* we trust this data, it was signed by us and secured by csrf */
                stdDialogInform(version, htmlDecode(data['html']), "{{ lang._('Close') }}", undefined, 'primary');
            }
        });
    }

    /**
     * perform package action that requires reboot confirmation
     */
    function action_may_reboot(pkg_act, pkg_name)
    {
        if (pkg_act == 'reinstall' && (pkg_name == 'kernel' || pkg_name == 'base')) {
            const reboot_msg = "{{ lang._('The firewall will reboot directly after this set reinstall.') }}";

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
                        backend(pkg_act + "/" + pkg_name);
                    }
                },{
                    label: "{{ lang._('Cancel') }}",
                    action: function(dialogRef){
                        dialogRef.close();
                    }
                }]
            });
        } else {
            backend(pkg_act + "/" + pkg_name);
        }
    }

    function show_upgrade(data) {
        $('#upgrade_maj').show();
        $('#updatestatus').html(data['status_msg']);
        $('#updatelist > tbody').empty();
        $('#updatetab > a').tab('show');
        $.each(data['all_sets'], function (index, row) {
            $('#updatelist > tbody').append('<tr><td>'+row['name']+'</td>' +
            '<td>'+row['old']+'</td><td>'+row['new']+'</td><td>' +
            row['reason']+'</td><td>'+row['repository'] + '</td></tr>');
        });
        $('#update_status_container').hide();
        $('#updatelist').show();
        changelog(data['upgrade_major_version']);
    }

    /**
     *  check if a reboot is required, warn user or just upgrade
     */
    function upgrade_ui(major = false)
    {
        let reboot_msg = "";
        if ( $.upgrade_needs_reboot == "1" || major === true) {
            reboot_msg = "{{ lang._('The firewall will reboot directly after this firmware update.') }}";
            if (major === true) {
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
                        backend(major === true ? 'upgrade' : 'update');
                    }
                },{
                    label: "{{ lang._('Cancel') }}",
                    action: function(dialogRef){
                        dialogRef.close();
                    }
                }]
            });
        } else {
            backend('update');
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

    function trackStatus() {
        ajaxGet('/api/core/firmware/upgradestatus', {}, function(data, status) {
            if (status != 'success') {
                // recover from temporary errors
                setTimeout(trackStatus, 1000);
                return;
            }
            if (data['log'] != undefined && data['log'] != '') {
                var autoscroll = $('#update_status')[0].scrollTop +
                    $('#update_status')[0].clientHeight ===
                    $('#update_status')[0].scrollHeight;
                $('#update_status').html(data['log']);
                if (autoscroll) {
                    $('#update_status').scrollTop($('#update_status')[0].scrollHeight);
                }
            }
            if (data['status'] == 'done') {
                $('#updatetab_progress').removeClass("fa fa-spinner fa-pulse");
                if ($.upgrade_check === true) {
                    updateStatus();
                } else {
                    packagesInfo(true);
                    reloadMenu();
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
        });
    }

    /**
     * show package info
     */
    function packagesInfo(reset) {
        $("#statustab_progress").addClass("fa fa-spinner fa-pulse");
        ajaxGet('/api/core/firmware/info', {}, function (data, status) {
            $('#packageslist > tbody').empty();
            $('#pluginlist > tbody').empty();
            var installed = {};

            $.each(data['product'], function(key, value) {
                if (key == 'product_check') {
                    if (value != null) {
                        $('#product_time_check').text(value['last_check']);
                    } else {
                        $('#product_time_check').text("{{ lang._('N/A') }}");
                    }
                } else {
                    $('#' + key).text(value);
                }
            });

            $("#statustab_progress").removeClass("fa fa-spinner fa-pulse");

            if (reset === true) {
                ajaxGet('/api/core/firmware/upgradestatus', {}, function(data, status) {
                    if (data['log'] != undefined && data['log'] != '') {
                        $('#update_status').html(data['log']);
                    } else {
                        $('#update_status').html('{{ lang._('No previous action log found.') }}');
                    }
                    $('#update_status').scrollTop($('#update_status')[0].scrollHeight);
                });

                $('#updatelist').hide();
                $('#update_status_container').show();
            }

            var local_count = 0;
            var plugin_count = 0;
            var misconfigured_plugins = 0;
            var missing_plugins = 0;
            var changelog_count = 0;
            var changelog_max = 15;
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
                    '<tr class="package_entry">' +
                    '<td>' + row['name'] + '</td>' +
                    '<td>' + row['version'] + '</td>' +
                    '<td>' + row['flatsize'] + '</td>' +
                    '<td>' + row['repository'] + '</td>' +
                    '<td>' + row['license'] + '</td>' +
                    '<td>' + row['comment'] + '</td>' +
                    '<td style="white-space:nowrap;vertical-align:middle;"><div class="input-group">' +
                    '<button class="btn btn-default btn-xs act_license" data-package="' + row['name'] + '" ' +
                    '  data-toggle="tooltip" title="{{ lang._('License') }}">' +
                    '<i class="fa fa-balance-scale fa-fw"></i></button> ' +
                    '<button class="btn btn-default btn-xs act_reinstall" data-package="' + row['name'] + '" ' +
                    '  data-toggle="tooltip" title="{{ lang._('Reinstall') }}">' +
                    '<i class="fa fa-recycle fa-fw"></i></button> ' + (row['locked'] === '1' ?
                        '<button data-toggle="tooltip" title="{{ lang._('Unlock') }}" class="btn btn-default btn-xs act_unlock" data-package="' + row['name'] + '">' +
                        '<i class="fa fa-lock fa-fw">' +
                        '</i></button>' :
                        '<button class="btn btn-default btn-xs act_lock" data-package="' + row['name'] + '" ' +
                        '  data-toggle="tooltip" title="{{ lang._('Lock') }}" >' +
                        '<i class="fa fa-unlock fa-fw"></i></button>'
                    ) + '</div></td>' +
                    '</tr>'
                );
            });

            if (local_count == 0) {
                $('#packageslist > tbody').append(
                    '<tr><td colspan=6>{{ lang._('No packages were found on your system. Please call for help.') }}</td></tr>'
                );
            }

            $.each(data['plugin'], function(index, row) {
                if (row['provided'] == "1") {
                    plugin_count += 1;
                }
                let status_text = '';
                let bold_on = '';
                let bold_off = '';
                if (row['installed'] == "1" && row['configured'] == "0") {
                    status_text = ' ({{ lang._('misconfigured') }})';
                    bold_on = '<b>';
                    bold_off = '</b>';
                    misconfigured_plugins = 1;
                } else if (row['installed'] == "0" && row['configured'] == "1") {
                    status_text = ' ({{ lang._('missing') }})';
                    bold_on = '<span class="text-danger plugin_missing"><b>';
                    bold_off = '</b></span>';
                    missing_plugins = 1;
                } else if (row['installed'] == "1") {
                    status_text = ' ({{ lang._('installed') }})';
                    bold_on = '<b>';
                    bold_off = '</b>';
                }
                if (row['provided'] == "0" && row['installed'] == "1") {
                    // this state overwrites installed on purpose
                    status_text = ' ({{ lang._('orphaned') }})';
                }
                $('#pluginlist > tbody').append(
                    '<tr class="plugin_entry">' + '<td>' + bold_on + row['name'] + status_text + bold_off + '</td>' +
                    '<td>' + bold_on + row['version'] + bold_off + '</td>' +
                    '<td>' + bold_on + row['flatsize'] + bold_off + '</td>' +
                    '<td>' + bold_on + row['repository'] + bold_off + '</td>' +
                    '<td>' + bold_on + row['comment'] + bold_off + '</td>' +
                    '<td style="white-space:nowrap;vertical-align:middle;"><div class="input-group">' +
                    '<button class="btn btn-default btn-xs act_details" data-package="' + row['name'] + '" ' +
                        ' data-toggle="tooltip" title="{{ lang._('Info') }}">' +
                        '<i class="fa fa-info-circle fa-fw"></i></button>' +
                        (row['installed'] == "1" ?
                        '<button class="btn btn-default btn-xs act_remove" data-package="' + row['name'] + '" '+
                        '  data-toggle="tooltip" title="{{ lang._('Remove') }}">' +
                        '<i class="fa fa-trash fa-fw">' +
                        '</i></button>' :
                        '<button class="btn btn-default btn-xs act_install" data-package="' + row['name'] + '" ' +
                        'data-repository="'+row['repository']+'" data-toggle="tooltip" title="{{ lang._('Install') }}">' +
                        '<i class="fa fa-plus fa-fw">' +
                        '</i></button>'
                    ) + '</div></td>' + '</tr>'
                );
            });

            if (plugin_count == 0) {
                $('#pluginlist > tbody').append(
                    '<tr><td colspan=5>{{ lang._('Check for updates to view available plugins.') }}</td></tr>'
                );
            }

            $('#audit_actions').show();
            $("#plugin_search").keyup();
            $("#package_search").keyup();

            if (misconfigured_plugins || missing_plugins) {
                if (!missing_plugins) {
                    $("#plugin_get").parent().hide();
                } else {
                    $("#plugin_get").parent().show();
                }
                $('#plugin_actions').show();
            } else {
                $('#plugin_actions').hide();
            }

            $("#changeloglist > tbody").empty();
            $("#changeloglist > thead").html("<tr><th>{{ lang._('Version') }}</th>" +
            "<th>{{ lang._('Date') }}</th><th></th></tr>");

            const installed_version = data['product_version'].replace(/[_-].*/, '');

            $.each(data['changelog'], function(index, row) {
                changelog_count += 1;

                let status_text = '';
                let bold_on = '';
                let bold_off = '';

                if (installed_version == row['version']) {
                    status_text = ' ({{ lang._('installed') }})';
                    bold_on = '<b>';
                    bold_off = '</b>';
                }

                $('#changeloglist > tbody').append(
                    '<tr' + (changelog_count > changelog_max ? ' class="changelog-hidden" style="display: none;" ' : '' ) +
                    '><td>' + bold_on + row['version'] + status_text + bold_off + '</td><td>' + bold_on + row['date'] + bold_off + '</td>' +
                    '<td><button class="btn btn-default btn-xs act_changelog" data-version="' + row['version'] + '" ' +
                    'data-toggle="tooltip" title="{{ lang._('View') }}">' +
                    '<i class="fa fa-book fa-fw"></i></button></td></tr>'
                );
            });

            if (!data['changelog'].length) {
                $('#changeloglist > tbody').append(
                    '<tr><td colspan=3>{{ lang._('Check for updates to view changelog history.') }}</td></tr>'
                );
            }

            if (changelog_count > changelog_max) {
                $('#changeloglist > tbody').append(
                    '<tr class= "changelog-full"><td colspan=3><a id="changelog-act" href="#">{{ lang._('Click to view full changelog history.') }}</a></td></tr>'
                );
                $("#changelog-act").click(function(event) {
                    event.preventDefault();
                    $(".changelog-hidden").attr('style', '');
                    $(".changelog-full").attr('style', 'display: none;');
                    $.changelog_keep_full = 1;
                });
            }

            // link buttons to actions
            $(".act_reinstall").click(function(event) {
                event.preventDefault();
                action_may_reboot('reinstall', $(this).data('package'));
            });
            $(".act_unlock").click(function(event) {
                event.preventDefault();
                backend('unlock/' + $(this).data('package'));
            });
            $(".act_lock").click(function(event) {
                event.preventDefault();
                backend('lock/' + $(this).data('package'));
            });
            $(".act_remove").click(function(event) {
                event.preventDefault();
                backend('remove/' + $(this).data('package'));
            });
            $(".act_details").click(function(event) {
                event.preventDefault();
                details($(this).data('package'));
            });
            $(".act_install").click(function(event) {
                event.preventDefault();
                let package_name = $(this).data('package');
                /* XXX temporary placeholder to inform the user that he/she is installing from a different (external) source */
                if ($(this).data('repository') !== 'OPNsense') {
                    BootstrapDialog.show({
                        type:BootstrapDialog.TYPE_INFO,
                        title: "{{ lang._('Third party software') }}",
                        message: "{{ lang._('This software package is provided by an external vendor, for more information contact the author')}}",
                        buttons: [{
                            label: "{{ lang._('Install') }}",
                            action: function(dialogRef){
                                dialogRef.close();
                                backend('install/' + package_name);
                            }
                        }, {
                            label: "{{ lang._('Cancel') }}",
                            action: function(dialogRef){
                                dialogRef.close();
                            }
                        }]
                    });
                } else {
                    backend('install/' + package_name);
                }
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
        $('#checkupdate').click(function () { backend('check'); });
        $('#upgrade').click(upgrade_ui);
        $('#upgrade_maj').click(function () { upgrade_ui(true); });
        $('#upgrade_cancel').click(cancel_update);
        $("#plugin_see").click(function () { $('#plugintab > a').tab('show'); });
        $("#plugin_get").click(function () { backend('syncPlugins'); });
        $("#plugin_set").click(function () { backend('resyncPlugins'); });
        $('#audit_security').click(function () { backend('audit'); });
        $('#audit_connection').click(function () { backend('connection'); });
        $('#audit_health').click(function () { backend('health'); });

        // populate package information
        packagesInfo(true);

        $("#plugin_search").keyup(function () { generic_search(this, 'plugin_entry'); });
        $("#package_search").keyup(function () { generic_search(this, 'package_entry'); });

        ajaxGet('/api/core/firmware/running', {}, function(data, status) {
            if (data['status'] == 'busy') {
                // if action is already running reattach now...
                backend('audit');
            } else if (window.location.hash == '#checkupdate') {
                // dashboard link: run check automatically after delay
                setTimeout(function () { backend('check'); }, 2000);
            }
        });

        // handle firmware config options
        ajaxGet('/api/core/firmware/getFirmwareOptions', {}, function(firmwareoptions, status) {
            ajaxGet('/api/core/firmware/getFirmwareConfig', {}, function(firmwareconfig, status) {
                var other_selected = true;
                $.each(firmwareoptions.mirrors, function(key, value) {
                    var selected = false;
                    if ((key != "" && firmwareconfig['mirror'].indexOf(key) != -1) || key == firmwareconfig['mirror']) {
                        selected = true;
                        other_selected = false;
                    }
                    $("#firmware_mirror").append($("<option/>")
                            .attr("value",key)
                            .text(value)
                            .prop('selected', selected)
                    );
                });
                if (firmwareoptions['mirrors_allow_custom']) {
                    $("#firmware_mirror").prepend($("<option/>")
                        .attr("value", firmwareconfig['mirror'])
                        .text("(other)")
                        .data("other", 1)
                        .prop('selected', other_selected)
                    );
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
                if (firmwareoptions['flavours_allow_custom']) {
                    $("#firmware_flavour").prepend($("<option/>")
                        .attr("value", firmwareconfig['flavour'])
                        .text("(other)")
                        .data("other", 1)
                        .prop('selected', other_selected)
                    );
                }
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
            $("#settingstab_progress").addClass("fa fa-spinner fa-pulse");
            var confopt = {};
            confopt.mirror = $("#firmware_mirror_value").val();
            confopt.flavour = $("#firmware_flavour_value").val();
            confopt.type = $("#firmware_type").val();
            confopt.subscription = $("#firmware_mirror_subscription").val();
            ajaxCall('/api/core/firmware/setFirmwareConfig', confopt, function(data, status) {
                $("#settingstab_progress").removeClass("fa fa-spinner fa-pulse");
                if (data['status'] == 'ok') {
                    packagesInfo(true);
                } else {
                    let validation_msgs = '<ul>';
                    for (i = 0; i < data['status_msgs'].length; i++) {
                        validation_msgs += '<li>' + $("<textarea/>").html(data['status_msgs'][i]).text() + '</li>';
                    }
                    validation_msgs += '</ul>';

                    stdDialogInform('{{ lang._('Firmware status') }}', htmlDecode(validation_msgs), "{{ lang._('Close') }}", undefined, 'danger');
                }
            });
        });

        $("#update_status_copy").click(function () {
            $("#update_status").select();
            document.execCommand("copy");
            document.getSelection().removeAllRanges();
            setTimeout(function () { $("#update_status").blur(); }, 100);
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
        <div class="col-md-12" id="content">
            <ul class="nav nav-tabs" data-tabs="tabs">
                <li id="statustab" class="active"><a data-toggle="tab" href="#status">{{ lang._('Status') }} <i id="statustab_progress"></i></a></li>
                <li id="settingstab"><a data-toggle="tab" href="#settings">{{ lang._('Settings') }} <i id="settingstab_progress"></i></a></li>
                <li id="changelogtab"><a data-toggle="tab" href="#changelog">{{ lang._('Changelog') }}</a></li>
                <li id="updatetab"><a data-toggle="tab" href="#updates">{{ lang._('Updates') }} <i id="updatetab_progress"></i></a></li>
                <li id="plugintab"><a data-toggle="tab" href="#plugins">{{ lang._('Plugins') }}</a></li>
                <li id="packagestab"><a data-toggle="tab" href="#packages">{{ lang._('Packages') }}</a></li>
            </ul>
            <div class="tab-content content-box">
                <div id="updates" class="tab-pane">
                    <table class="table table-striped table-condensed table-responsive" id="updatelist" style="display: none;">
                        <thead>
                            <tr>
                              <th style="width:20%">{{ lang._('Package name') }}</th>
                              <th style="width:20%">{{ lang._('Current version') }}</th>
                              <th style="width:20%">{{ lang._('New version') }}</th>
                              <th style="width:20%">{{ lang._('Required action') }}</th>
                              <th style="width:20%">{{ lang._('Repository') }}</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                            <tr>
                                <td></td>
                                <td style="white-space:nowrap;vertical-align:middle;">
                                    <button class="btn btn-info" id="upgrade"><i class="fa fa-check"></i> {{ lang._('Update') }}</button>
                                    <button class='btn btn-warning' id="upgrade_maj"><i class="fa fa-check"></i> {{ lang._('Upgrade') }}</button>
                                    <button class="btn btn-default" id="upgrade_cancel"><i class="fa fa-times"></i> {{ lang._('Cancel') }}</button>
                                </td>
                                <td colspan="2" style="vertical-align:middle">
                                    <strong><div id="updatestatus"></div></strong>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                    <div id="update_status_container">
                       <textarea name="output" id="update_status" class="form-control" rows="20" wrap="hard" readonly="readonly" style="max-width:100%; font-family: monospace;"></textarea>
                      <table class="table table-striped table-condensed table-responsive">
                        <tbody>
                          <tr>
                            <td>
                              {{ lang._('Output shown here for diagnostic purposes. There is no general need for manual system intervention.') }}
                              <a id="update_status_copy">{{ lang._('Click here to copy to clipboard.') }}</a>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                </div>
                <div id="status" class="tab-pane active">
                    <table class="table table-striped table-condensed table-responsive">
                        <tbody>
                            <tr>
                                <td style="width: 20px;"></td>
                                <td style="width: 150px;">{{ lang._('Type') }}</td>
                                <td id="product_id"></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td style="width: 20px;"></td>
                                <td style="width: 150px;">{{ lang._('Version') }}</td>
                                <td id="product_version"></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td style="width: 20px;"></td>
                                <td style="width: 150px;">{{ lang._('Architecture') }}</td>
                                <td id="product_arch"></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td style="width: 20px;"></td>
                                <td style="width: 150px;">{{ lang._('Flavour') }}</td>
                                <td id="product_crypto"></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td style="width: 20px;"></td>
                                <td style="width: 150px;">{{ lang._('Commit') }}</td>
                                <td id="product_hash"></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td style="width: 20px;"></td>
                                <td style="width: 150px;">{{ lang._('Mirror') }}</td>
                                <td id="product_mirror"></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td style="width: 20px;"></td>
                                <td style="width: 150px;">{{ lang._('Repositories') }}</td>
                                <td id="product_repos"></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td style="width: 20px;"></td>
                                <td style="width: 150px;">{{ lang._('Updated on') }}</td>
                                <td id="product_time"></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td style="width: 20px;"></td>
                                <td style="width: 150px;">{{ lang._('Checked on') }}</td>
                                <td id="product_time_check"></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td style="width: 20px;"></td>
                                <td style="width: 150px;"></td>
                                <td>
                                    <button class="btn btn-primary" id="checkupdate"><i class="fa fa-refresh"></i> {{ lang._('Check for updates') }}</button>
                                    <div class="btn-group" id="audit_actions" style="display:none;">
                                        <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">
                                            <i class="fa fa-lock"></i> {{ lang._('Run an audit') }} <i class="caret"></i>
                                        </button>
                                        <ul class="dropdown-menu" role="menu">
                                            <li><a id="audit_connection" href="#">{{ lang._('Connectivity') }}</a></li>
                                            <li><a id="audit_health" href="#">{{ lang._('Health') }}</a></li>
                                            <li><a id="audit_security" href="#">{{ lang._('Security') }}</a></li>
                                        </ul>
                                    </div>
                                    <div class="btn-group" id="plugin_actions" style="display:none;">
                                        <button type="button" class="btn btn-defaul dropdown-toggle" data-toggle="dropdown">
                                            <i class="fa fa-exclamation-triangle"></i> {{ lang._('Resolve plugin conflicts') }} <i class="caret"></i>
                                        </button>
                                        <ul class="dropdown-menu" role="menu">
                                            <li><a id="plugin_see" href="#">{{ lang._('View and edit local conflicts') }}</a></li>
                                            <li><a id="plugin_get" href="#">{{ lang._('Run the automatic resolver') }}</a></li>
                                            <li><a id="plugin_set" href="#">{{ lang._('Reset all local conflicts') }}</a></li>
                                        </ul>
                                    </div>
                                </td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div id="plugins" class="tab-pane">
                    <table class="table table-striped table-condensed table-responsive" id="pluginlist">
                        <thead>
                            <tr>
                                <th style="vertical-align:middle"><input type="text" class="input-sm" autocomplete="off" id="plugin_search" placeholder="{{ lang._('Name') }}"></th>
                                <th style="vertical-align:middle">{{ lang._('Version') }}</th>
                                <th style="vertical-align:middle">{{ lang._('Size') }}</th>
                                <th style="vertical-align:middle">{{ lang._('Repository') }}</th>
                                <th style="vertical-align:middle">{{ lang._('Comment') }}</th>
                                <th style="vertical-align:middle"></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div id="packages" class="tab-pane">
                    <table class="table table-striped table-condensed table-responsive" id="packageslist">
                        <thead>
                            <tr>
                                <th style="vertical-align:middle"><input type="text" class="input-sm" autocomplete="off" id="package_search" placeholder="{{ lang._('Name') }}"></th>
                                <th style="vertical-align:middle">{{ lang._('Version') }}</th>
                                <th style="vertical-align:middle">{{ lang._('Size') }}</th>
                                <th style="vertical-align:middle">{{ lang._('Repository') }}</th>
                                <th style="vertical-align:middle">{{ lang._('License') }}</th>
                                <th style="vertical-align:middle">{{ lang._('Comment') }}</th>
                                <th style="vertical-align:middle"></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div id="changelog" class="tab-pane">
                    <table class="table table-striped table-condensed table-responsive" id="changeloglist">
                        <thead></thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div id="settings" class="tab-pane">
                    <table class="table table-striped table-condensed table-responsive">
                        <tbody>
                            <tr>
                                <td style="width: 20px;"></td>
                                <td style="width: 150px;"><a id="help_for_mirror" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> {{ lang._('Mirror') }}</td>
                                <td>
                                    <select class="selectpicker" id="firmware_mirror"  data-size="5" data-live-search="true">
                                    </select>
                                    <div style="display:none;" id="firmware_mirror_other">
                                        <input type="text" id="firmware_mirror_value">
                                    </div>
                                    <div class="hidden" data-for="help_for_mirror">
                                        {{ lang._('Select an alternate firmware mirror.') }}
                                    </div>
                                </td>
                                <td></td>
                            </tr>
                            <tr>
                                <td style="width: 20px;"></td>
                                <td><a id="help_for_flavour" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> {{ lang._('Flavour') }}</td>
                                <td>
                                    <select class="selectpicker" id="firmware_flavour">
                                    </select>
                                    <div style="display:none;" id="firmware_flavour_other">
                                        <input type="text" id="firmware_flavour_value">
                                    </div>
                                    <div class="hidden" data-for="help_for_flavour">
                                        {{ lang._('Select the firmware cryptography flavour.') }}
                                    </div>
                                </td>
                                <td></td>
                            </tr>
                            <tr>
                                <td style="width: 20px;"></td>
                                <td><a id="help_for_type" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> {{ lang._('Type') }}</td>
                                <td>
                                    <select class="selectpicker" id="firmware_type">
                                    </select>
                                    <div class="hidden" data-for="help_for_type">
                                        {{ lang._('Select the release type.') }}
                                    </div>
                                </td>
                                <td></td>
                            </tr>
                            <tr>
                                <td style="width: 20px;"></td>
                                <td style="width: 150px;"><a id="help_for_mirror_subscription" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> {{ lang._('Subscription') }}</td>
                                <td>
                                    <input type="text" id="firmware_mirror_subscription">
                                    <div class="hidden" data-for="help_for_mirror_subscription">
                                        {{ lang._('Provide subscription key.') }}
                                    </div>
                                </td>
                                <td></td>
                            </tr>
                            <tr>
                                <td style="width: 20px;"></td>
                                <td style="width: 150px;"><i class="fa fa-info-circle text-muted"></i> {{ lang._('Usage') }}</td>
                                <td>
                                    {{ lang._('In order to apply these settings a firmware update must be performed after save, which can include a reboot of the system.') }}
                                </td>
                                <td></td>
                            </tr>
                            <tr>
                                <td style="width: 20px;"></td>
                                <td></td>
                                <td>
                                    <button class="btn btn-primary" id="change_mirror" type="button"><i class="fa fa-floppy-o"></i> {{ lang._('Save') }}</button>
                                </td>
                                <td></td>
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
