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
    let $ret = $('<div><div><a data-dismiss="modal" class="btn btn-default" style="width:100%;text-align:left;" href="#"><h4><span class="fa fa-circle text-muted"></span>&nbsp;System</h4><p>No pending messages.</p></a></div></div>');
    let $message = $(
        '<div>' +
        '<div id="opn-status-list"></div>' +
        '</div>'
    );

    for (let subject in status.data) {
        if (subject === 'System') {
            continue;
        }
        let statusObject = status.data[subject];
        if (status.data[subject].status == "OK") {
            continue;
        }

        $message.find('a').last().addClass('__mb');

        let formattedSubject = subject.replace(/([A-Z])/g, ' $1').trim();
        if (status.data[subject].age != undefined) {
            formattedSubject += '&nbsp;<small>(' + status.data[subject].age + ')</small>';
        }
        let listItem = '<a class="btn btn-default" style="width:100%;text-align:left;" href="' + statusObject.logLocation + '">' +
            '<h4><span class="' + statusObject.icon + '"></span>&nbsp;' + formattedSubject +
            '<button id="dismiss-'+ subject + '" class="close"><span aria-hidden="true">&times;</span></button></h4></div>' +
            '<p style="white-space: pre-wrap;">' + statusObject.message + '</p></a>';

        let referral = statusObject.logLocation;

        $message.find('#opn-status-list').append(listItem);

        $message.find('#dismiss-' + subject).on('click', function (e) {
            e.preventDefault();
            $.ajax('/api/core/system/dismissStatus', {
                type: 'post',
                data: {'subject': subject},
                dialogRef: dialog,
                subjectRef: subject,
                success: function() {
                    updateSystemStatus().then((data) => {
                        let newStatus = parseStatus(data);
                        let $newMessage = updateStatusDialog(this.dialogRef, newStatus, this.subjectRef);
                        this.dialogRef.setMessage($newMessage);
                        $('#system_status').attr("class", newStatus.data['System'].icon);
                        registerStatusDelegate(this.dialogRef, newStatus);
                    });
                }
            });
        });

        $ret = $message;
    }
    return $ret;
}

function parseStatus(data) {
    let status = {};
    let severity = BootstrapDialog.TYPE_PRIMARY;
    $.each(data, function(subject, statusObject) {
        switch (statusObject.status) {
            case "Error":
                statusObject.icon = 'fa fa-circle text-danger'
                if (subject != 'System') break;
                severity = BootstrapDialog.TYPE_DANGER;
                break;
            case "Warning":
                statusObject.icon = 'fa fa-circle text-warning';
                if (subject != 'System') break;
                severity = BootstrapDialog.TYPE_WARNING;
                break;
            case "Notice":
                statusObject.icon = 'fa fa-circle text-info';
                if (subject != 'System') break;
                severity = BootstrapDialog.TYPE_INFO;
                break;
            default:
                statusObject.icon = 'fa fa-circle text-muted';
                if (subject != 'System') break;
                break;
        }
        $('#system_status').removeClass().addClass(statusObject.icon);
    });
    status.severity = severity;
    status.data = data;

    return status;
}

function registerStatusDelegate(dialog, status) {
    $("#system_status").click(function() {
        dialog.setMessage(function(dialogRef) {
            let $message = updateStatusDialog(dialogRef, status);
            return $message;
        });
        dialog.open();
    });
}

function updateSystemStatus() {
    return $.ajax('/api/core/system/status', { type: 'get', dataType: 'json' });
}
