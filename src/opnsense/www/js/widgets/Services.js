/*
 * Copyright (C) 2024 Deciso B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

export default class Services extends BaseTableWidget {
    constructor() {
        super();
        this.locked = false;
    }

    getGridOptions() {
        return {
            // trigger overflow-y:scroll after 650px height
            sizeToContent: 650,
        }
    }

    getMarkup() {
        let $table = this.createTable('services-table', {
            headerPosition: 'left',
            headerBreakpoint: 270
        });
        return $(`<div id="services-container"></div>`).append($table);
    }

    serviceControl(actions) {
        return actions.map(({ action, id, title, icon }) => `
            <button data-service_action="${action}" data-service="${id}"
                  class="btn btn-xs btn-default srv_status_act2" style="font-size: 10px;" title="${title}" data-toggle="tooltip">
                <i class="fa fa-fw fa-${icon}"></i>
            </button>
        `).join('');
    }

    async updateServices() {
        const data = await this.ajaxCall(`/api/core/service/${'search'}`);

        if (!data || !data.rows || data.rows.length === 0) {
            this.displayError(this.translations.noservices);
            return;
        }

        $('.service-status').tooltip('hide');
        $('.srv_status_act2').tooltip('hide');

        for (const service of data.rows) {
            let name = service.name;
            let $description = $(`<div style="font-size: 12px;">${service.description}</div>`);

            let actions = [];
            if (service.locked) {
                actions.push({ action: 'restart', id: service.id, title: this.translations.restart, icon: 'refresh' });
            } else if (service.running) {
                actions.push({ action: 'restart', id: service.id, title: this.translations.restart, icon: 'refresh' });
                actions.push({ action: 'stop', id: service.id, title: this.translations.stop, icon: 'stop' });
            } else {
                actions.push({ action: 'start', id: service.id, title: this.translations.start, icon: 'play' });
            }

            let $buttonContainer = $(`
                <div style="margin-left: 45%">
                <span class="label label-opnsense label-opnsense-xs
                             label-${service.running ? 'success' : 'danger'}
                             service-status"
                             data-toggle="tooltip" title="${service.running ? this.translations.running : this.translations.stopped}"
                             style="font-size: 10px;">
                    <i class="fa fa-${service.running ? 'play' : 'stop'} fa-fw"></i>
                </span>
                </div>
            `);

            $buttonContainer.append(this.serviceControl(actions));

            super.updateTable('services-table', [[$description.prop('outerHTML'), $buttonContainer.prop('outerHTML')]], service.id);
        }

        $('.service-status').tooltip({container: 'body'});
        $('.srv_status_act2').tooltip({container: 'body'});

        $('.srv_status_act2').on('click', async (event) => {
            this.locked = true;
            event.preventDefault();
            event.currentTarget.blur();
            let $elem = $(event.currentTarget);
            let $icon = $elem.children(0);
            this.startCommandTransition($elem.data('service'), $icon);
            const result = await this.ajaxCall(`/api/core/service/${$elem.data('service_action')}/${$elem.data('service')}`, {}, 'POST');
            await this.endCommandTransition($elem.data('service'), $icon, true, false);
            await this.updateServices();
            this.locked = false;
        });
    }

    async onWidgetTick() {
        if (!this.locked) {
            await this.updateServices();
        }
    }

    displayError(message) {
        const $error = $(`<div class="error-message"><a href="/ui/core/service">${message}</a></div>`);
        $('#services-table').empty().append($error);
    }

}
