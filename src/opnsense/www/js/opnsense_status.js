/**
 *    Copyright (C) 2022 Deciso B.V.
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 */

function updateStatusDialog(dialog, status, subjectRef = null) {
    const keys = Object.keys(status.data);
    const statusAvailable = !(keys.length === 1 && keys[0] === 'System');

    let $message = statusAvailable ? $(
        '<div class="row">' +
        '<div class="col-md-6">' +
        '<div class="list-group" id="list-tab" role="tablist" style="margin-bottom: 0">' +
        '</div>' +
        '</div>' +
        '<div class="col-md-6">' +
        '<div class="tab-content" id="nav-tabContent">' +
        '</div>' +
        '</div>'+
        '</div>'
    ) :
        $('<div>No problems were detected.</div>');

    if (!statusAvailable) {
        return $message;
    }

    for (let subject in status.data) {
        if (subject === 'System') {
            continue;
        }
        let statusObject = status.data[subject];
        let dismissNeeded = true;

        if (status.data[subject].status == "OK") {
            dismissNeeded = false;
        }
        let formattedSubject = subject.replace(/([A-Z])/g, ' $1').trim();
        let $listItem = $(
            '<a class="list-group-item list-group-item-border" data-toggle="list" href="#list-' + subject + '" role="tab" style="outline: 0">' +
            formattedSubject +
            '<span class="' + statusObject.icon + '" style="float: right"></span>' +
            '</a>'
        );
        let referral = statusObject.status !== 'OK' ? 'Click <a href="' + statusObject.logLocation + '">here</a> for more information.' : ''
        let $pane = $(
            '<div class="tab-pane fade" id="list-' + subject + '" role="tabpanel"><p>' + statusObject.message + ' ' + referral + '</p>' +
            '</div>'
        );

        $message.find('#list-tab').addClass('opn-status-group').append($listItem);
        $message.find('#nav-tabContent').append($pane);

        if (subjectRef) {
            $message.find('#list-tab a[href="#list-' + subjectRef + '"]').addClass('active').tab('show').siblings().removeClass('active');
            $pane.addClass('active in').siblings().removeClass('active in');
        } else {
            $message.find('#list-tab a:first-child').addClass('active').tab('show');
            $message.find('#nav-tabContent div:first-child').addClass('active in');
        }

        $message.find('#list-tab a[href="#list-' + subject + '"]').on('click', function(e) {
            e.preventDefault();
            $(this).tab('show');
            $(this).toggleClass('active').siblings().removeClass('active');
        });

        if (dismissNeeded) {
            let $button = $('<div><button id="dismiss-'+ subject + '" type="button" class="btn btn-link btn-sm" style="padding: 0px;">Dismiss</button></div>');
            $pane.append($button);
        }

        $message.find('#dismiss-' + subject).on('click', function(e) {
            $.ajax('/api/core/system/dismissStatus', {
                type: 'post',
                data: {'subject': subject},
                dialogRef: dialog,
                subjectRef: subject,
                success: function() {
                    updateSystemStatus().then((data) => {
                        let newStatus = parseStatus(data);
                        let $newMessage = updateStatusDialog(this.dialogRef, newStatus, this.subjectRef);
                        this.dialogRef.setType(newStatus.severity);
                        this.dialogRef.setMessage($newMessage);
                        $('#system_status').attr("class", newStatus.data['System'].icon);
                        registerStatusDelegate(this.dialogRef, newStatus);
                    });
                }
            });
        });
    }
    return $message;
}

function parseStatus(data) {
    let status = {};
    let severity = BootstrapDialog.TYPE_SUCCESS;
    $.each(data, function(subject, statusObject) {
        switch (statusObject.status) {
            case "Error":
                statusObject.icon = 'fa fa-exclamation-triangle text-danger'
                if (subject != 'System') break;
                $('#system_status').toggleClass(statusObject.icon);
                severity = BootstrapDialog.TYPE_DANGER;
                break;
            case "Warning":
                statusObject.icon = 'fa fa-exclamation-triangle text-warning';
                if (subject != 'System') break;
                $('#system_status').toggleClass(statusObject.icon);
                severity = BootstrapDialog.TYPE_WARNING;
                break;
            case "Notice":
                statusObject.icon = 'fa fa-check-circle text-info';
                if (subject != 'System') break;
                $('#system_status').toggleClass(statusObject.icon);
                severity = BootstrapDialog.TYPE_INFO;
                break;
            default:
                statusObject.icon = 'fa fa-check-circle text-success';
                if (subject != 'System') break;
                $('#system_status').toggleClass(statusObject.icon);
                break;
        }
    });
    status.severity = severity;
    status.data = data;

    return status;
}

function registerStatusDelegate(dialog, status) {
    $("#system_status").click(function() {
        dialog.setType(status.severity);
        dialog.setMessage(function(dialogRef) {
            let $message = updateStatusDialog(dialogRef, status);
            return $message;
        });
        dialog.open();
    });
}

function updateSystemStatus() {
    return $.ajax("/api/core/system/status", {
        type: 'get',
        dataType: "json"
    });
}
