/**
 *    Copyright (C) 2022-2024 Deciso B.V.
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

function updateStatusDialog(dialog, status) {
    let $ret = $(`
        <div>
            <a data-dismiss="modal" class="btn btn-default" style="width:100%; text-align: left;" href="#">
                <h4>
                    <span class="fa fa-circle text-muted">
                    </span>
                    &nbsp;
                    System
                </h4>
                <p>No pending messages.</p>
            </a>
        </div>
    `);

    let $message = $(
        '<div>' +
        '<div id="opn-status-list"></div>' +
        '</div>'
    );

    for (let [shortname, subject] of Object.entries(status)) {
        $message.find('a').last().addClass('__mb');

        let formattedSubject = subject.title;
        if (subject.age != undefined) {
            formattedSubject += '&nbsp;<small>(' + subject.age + ')</small>';
        }

        let ref = subject.logLocation != null ? `href="${subject.logLocation}"` : '';
        let $closeBtn = `
            <button id="dismiss-${shortname}" class="close">
                <span aria-hidden="true">&times;</span>
            </button>
        `;
        let $listItem = $(`
            <a class="btn btn-default" style="width:100%; text-align: left;" ${ref}>
                <h4>
                    <span class="${subject.icon}"></span>
                    &nbsp;
                    ${formattedSubject}
                    ${subject.persistent ? '' : $closeBtn}
                </h4>
                <p style="white-space: pre-wrap;">${subject.message}</p>
            </a>
        `)

        $message.find('#opn-status-list').append($listItem);

        $message.find('#dismiss-' + shortname).on('click', function (e) {
            e.preventDefault();
            $.ajax('/api/core/system/dismissStatus', {
                type: 'post',
                data: {'subject': shortname},
                dialogRef: dialog,
                success: function() {
                    updateSystemStatus(this.dialogRef);
                }
            });
        });

        $ret = $message;
    }
    return $ret;
}

function parseStatusIcon(subject) {
    switch (subject.status) {
        case "ERROR":
            subject.icon = 'fa fa-circle text-danger';
            subject.banner = 'alert-danger';
            subject.severity = BootstrapDialog.TYPE_DANGER;
            break;
        case "WARNING":
            subject.icon = 'fa fa-circle text-warning';
            subject.banner ='alert-warning';
            subject.severity = BootstrapDialog.TYPE_WARNING;
            break;
        case "NOTICE":
            subject.icon = 'fa fa-circle text-info';
            subject.banner = 'alert-info';
            subject.severity = BootstrapDialog.TYPE_INFO;
            break;
        default:
            subject.icon = 'fa fa-circle text-muted';
            subject.banner = 'alert-info';
            subject.severity = BootstrapDialog.TYPE_PRIMARY;
            break;
    }
}

function fetchSystemStatus() {
    return new Promise((resolve, reject) => {
        ajaxGet('/api/core/system/status', {}, function (data) {
            resolve(data);
        });
    });
}

function parseStatus(data) {
    let system = data.metadata.system;

    // handle initial page load status icon
    parseStatusIcon(system);
    $('#system_status').removeClass().addClass(system.icon);

    let notifications = {};
    let bannerMessages = {};
    for (let [shortname, subject] of Object.entries(data.subsystems)) {
        parseStatusIcon(subject);

        if (subject.status == "OK")
            continue;

        if (subject.persistent) {
            bannerMessages[shortname] = subject;
        } else {
            notifications[shortname] = subject;
        }
    }

    return {
        'banners': bannerMessages,
        'notifications': notifications
    };
}

function updateSystemStatus(dialog = null) {
    fetchSystemStatus().then((data) => {
        let status = parseStatus(data); // will also update status icon

        if (dialog != null) {
            dialog.setMessage(function(dialogRef) {
                return updateStatusDialog(dialogRef, status.notifications);
            })
        }

        if (!$.isEmptyObject(status.banners)) {
            let banner = Object.values(status.banners)[0];
            $('.page-content-main > .container-fluid > .row').prepend($(`
                <div class="container-fluid">
                    <div id="notification-banner" class="alert alert-info ${banner.banner}"
                        style="padding: 10px; text-align: center;">
                        ${banner.message}
                    </div>
                </div>
            `));

        }
    });

    $("#system_status").click(function() {
        fetchSystemStatus().then((data) => {
            let translations = data.metadata.translations;
            let status = parseStatus(data);

            dialog = new BootstrapDialog({
                title: translations.dialogTitle,
                buttons: [{
                    id: 'close',
                    label: translations.dialogCloseButton,
                    action: function(dialogRef) {
                        dialogRef.close();
                    }
                }],
            });

            dialog.setMessage(function(dialogRef) {
                // intentionally do banners first, as these should always show on top
                // in both cases normal backend sorting applies
                return updateStatusDialog(dialogRef, {...status.banners, ...status.notifications});
            })

            dialog.open();
        });
    });

}
