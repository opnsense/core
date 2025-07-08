/**
 *    Copyright (C) 2022-2025 Deciso B.V.
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

class Status {
    constructor() {
        this.observers = [];
        this.data = null;
    }

    updateStatus() {
        const fetch = new Promise((resolve, reject) => {
            ajaxGet('/api/core/system/status', {path: window.location.pathname}, function (data, status) {
                if (status !== "success") {
                    reject(status);
                }
                resolve(data);
            });
        });

        fetch.then((data) => {
            if (!data.subsystems || typeof data.subsystems !== 'object') {
                data.subsystems = { };
            }
            this.notify(data);
        }, (reject) => {
            // Either inaccessible or something went wrong on the backend.
            $('#system_status').css({
                'cursor': 'default',
                'pointer-events': 'none'
            });
        });
    }

    attach(observer) {
        this.observers.push(observer);
    }

    notify(data) {
        this.observers.forEach(observer => observer.update(data));
    }
}

class StatusIcon {
    update(status) {
        const icon = this._parseStatusIcon(status.metadata.system.status);
        $('#system_status').removeClass().addClass(icon);
    }

    _parseStatusIcon(statusCode) {
        switch (statusCode) {
            case "ERROR":
                return 'fa fa-circle text-danger';
            case "WARNING":
                return 'fa fa-circle text-warning';
            case "NOTICE":
                return 'fa fa-circle text-info';
            default:
                return 'fa fa-circle text-muted';
        }
    }

    asClass(statusCode) {
        return this._parseStatusIcon(statusCode);
    }
}

class StatusDialog {
    constructor() {
        this.clickHandlerRegistered = false;
        this.dialogOpen = false;
        this.currentStatus = null;
        this.dialog = null;
    }

    update(status) {
        this.currentStatus = status;
        if (!this.clickHandlerRegistered) {
            this.clickHandlerRegistered = true;
            const translations = status.metadata.translations;
            $('#system_status').click(() => {
                this.dialog = new BootstrapDialog({
                    title: translations.dialogTitle,
                    draggable: true,
                    buttons: [{
                        id: 'close',
                        label: translations.dialogCloseButton,
                        action: (dialogRef) => {
                            dialogRef.close();
                            this.dialogOpen = false;
                        }
                    }],
                });

                this._setDialogContent(this.currentStatus);

                this.dialog.open();
                this.dialogOpen = true;
            });
        } else {
            this._setDialogContent(this.currentStatus);

            if (!this.dialogOpen) {
                this.dialog.open();
                this.dialogOpen = true;
            }
        }
    }

    _setDialogContent(status) {
        this.dialog.setMessage((dialog) => {
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

            for (let [shortname, subject] of Object.entries(status.subsystems)) {
                if (subject.status == "OK" || subject.isBanner)
                    continue;

                $message.find('a').last().addClass('__mb');

                let formattedSubject = subject.title;
                if (subject.age != undefined) {
                    formattedSubject += '&nbsp;<small>(' + subject.age + ')</small>';
                }

                let ref = subject.location != null ? `href="${subject.location}"` : '';
                let hoverStyle = (subject.location == null && subject.persistent) ? 'cursor: default; pointer-events: none;' : '';

                let $closeBtn = `
                    <button id="dismiss-${shortname}" class="close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                `;

                let $listItem = $(`
                    <a class="btn btn-default" style="width:100%; text-align: left; ${hoverStyle}" ${ref}>
                        <h4>
                            <span class="${(new StatusIcon()).asClass(subject.status)}"></span>
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
                    $.ajax('/api/core/system/dismiss_status', {
                        type: 'post',
                        data: {'subject': shortname},
                        dialogRef: dialog,
                        itemRef: $listItem,
                        success: function() {
                            statusObj.updateStatus();
                        }
                    });
                });

                $ret = $message;
            }

            return $ret;
        });
    }
}

class StatusBanner {
    constructor() {
        this.bannerActive = false;
    }

    update(status) {
        for (let [name, subject] of Object.entries(status.subsystems)) {
            if (subject.status == "OK")
                continue;

            if (subject.isBanner) {
                if (!this.bannerActive) {
                    $('.page-content-main > .container-fluid > .row').prepend($(`
                        <div class="container-fluid">
                            <div id="notification-banner" class="alert ${this.parseStatusBanner(subject.status)}"
                                style="padding: 10px; text-align: center;">
                                ${subject.message}
                            </div>
                        </div>
                    `));
                    this.bannerActive = true;
                    break;
                } else {
                    $('#notification-banner').text(subject.message);
                    $('#notification-banner').removeClass().addClass(`alert ${this.parseStatusBanner(subject.status)}`);
                }
            }
        }
    }

    parseStatusBanner(statusCode) {
        switch (statusCode) {
            case "ERROR":
                return 'alert-danger';
            case "WARNING":
                return 'alert-warning';
            default:
                return 'alert-info';
        }
    }
}

const statusObj = new Status();

function updateSystemStatus() {
    statusObj.attach(new StatusIcon());
    statusObj.attach(new StatusDialog());
    statusObj.attach(new StatusBanner());

    statusObj.updateStatus();
}
