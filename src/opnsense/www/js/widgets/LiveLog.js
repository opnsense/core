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

export default class LiveLog extends BaseTableWidget {
    constructor(config) {
        super(config);
        this.configurable = true;
        this._hasData = false;
        this._severityLevels = [
            'Emergency', 'Alert', 'Critical', 'Error',
            'Warning', 'Notice', 'Informational', 'Debug'
        ];
        this._sources = {};
        this._logPrefix = '/ui/diagnostics/log/core/';
    }

    getGridOptions() {
        return {
            sizeToContent: 650
        };
    }

    getMarkup() {
        let $container = $('<div></div>');
        let $table = this.createTable('live-log-table', {headerPosition: 'none'});
        $container.append($table);
        return $container;
    }

    async _fetchSources() {
        const sources = {};
        const logFilesLabel = $('a[href$="_LogFiles"]').first().text().trim();
        for (const item of [...new MenuSystem().walk()].filter((x) => x.href.startsWith(this._logPrefix))) {
            const scope = item.href.slice(this._logPrefix.length).split('/')[0];
            const parts = item.breadcrumb().split(': ');
            if (parts.length > 2 && parts[parts.length - 2] === logFilesLabel) {
                sources[scope] = parts[0] + ': ' + parts[parts.length - 1];
            } else {
                sources[scope] = parts[0] + ': ' + parts[parts.length - 2];
            }
        }
        this._sources = sources;
        return sources;
    }

    async onMarkupRendered() {
        const [, config] = await Promise.all([
            this._fetchSources(),
            this.getWidgetConfig()
        ]);
        this._startLog(config);
    }

    _renderRow(entry) {
        const meta = [entry.timestamp, entry.severity, entry.process_name]
            .filter(v => v && v !== 'null')
            .join(' \u00b7 ');
        let $row = $('<span></span>');
        $row.append($('<span style="font-size:0.85em;color:#888"></span>').text(meta + ' \u00b7 '));
        $row.append($('<span></span>').html(entry.line));
        return $row;
    }

    _defaultSource() {
        if (this._sources['system']) return 'system';
        const keys = Object.keys(this._sources);
        return keys.length > 0 ? keys[0] : null;
    }

    _startLog(config) {
        const source = (config.source && this._sources[config.source])
            ? config.source
            : this._defaultSource();
        config.source = source;

        if (source === null) {
            $(`#link-handle-${this.id}`).hide();
            super.updateTable('live-log-table', [[this.translations.noaccess]], 'error');
            this.config.callbacks.updateGrid();
            return;
        }

        const lineCount = parseInt(config.lineCount, 10);
        const severity = config.severity;
        const searchFilter = config.searchFilter;
        const severityFilter = this._severityLevels
            .slice(0, this._severityLevels.indexOf(severity) + 1)
            .join(',');

        $(`#link-handle-${this.id}`).show().find('a').attr('href', `${this._logPrefix}${source}`);

        const sourceLabel = this._sources[source];
        const severityLabel = this.translations[`severity${severity.toLowerCase()}`] ?? severity;
        const filterHtml = searchFilter
            ? ` &nbsp;|&nbsp; <b>${this.translations.filterlabel}:</b> ${$('<span>').text(searchFilter).html()}`
            : '';
        const summary =
            `<div style="text-align:center">` +
            `<b>${this.translations.sourcelabel}:</b> ${$('<span>').text(sourceLabel).html()} &nbsp;|&nbsp; ` +
            `<b>${this.translations.thresholdlabel}:</b> ${severityLabel} &nbsp;|&nbsp; ` +
            `<b>${this.translations.lineslabel}:</b> ${lineCount}` +
            filterHtml +
            `</div>`;

        super.updateTable('live-log-table', [[summary]], 'summary');
        const $summary = $(`#${this.id}_summary`);

        const onEntry = (event) => {
            let data;
            try {
                data = JSON.parse(event.data);
            } catch (e) {
                this.closeEventSource();
                $(`#link-handle-${this.id}`).hide();
                $(`#${this.id}_summary`).remove();
                super.updateTable('live-log-table', [[this.translations.noaccess]], 'error');
                this.config.callbacks.updateGrid();
                return;
            }

            $(`#${this.id}_error`).remove();
            $summary.after(
                $('<div class="flextable-row"></div>')
                    .append($('<div class="flex-cell"></div>').append(this._renderRow(data)))
            );
            const rows = $(`#live-log-table .flextable-row:not(#${this.id}_summary)`);
            if (rows.length > lineCount) {
                rows.last().remove();
            } else {
                this.config.callbacks.updateGrid();
            }
        };

        this._onData = (event) => {
            if (event.type !== 'message') {
                this._hasData = true;
            }
            if (event.type !== 'log' && this._hasData) {
                return;
            }
            onEntry(event);
        };

        this.openEventSource(
            `/api/diagnostics/log/core/${source}/live?offset=${lineCount}&searchPhrase=${encodeURIComponent(searchFilter)}&severity=${encodeURIComponent(severityFilter)}`,
            this._onData
        );
    }

    openEventSource(url, onMessage) {
        if (!this._hasData) {
            $(`#live-log-table .flextable-row`).each((_, el) => {
                if (el.id !== `${this.id}_summary` && el.id !== `${this.id}_error`) $(el).remove();
            });
        }
        super.openEventSource(url, onMessage);
        if (this.eventSource) {
            this.eventSource.addEventListener('log', this._onData);
            this.eventSource.addEventListener('keepalive', this._onData);
            this.eventSource.addEventListener('error', () => {
                if (!this._hasData && this.eventSource === null) {
                    $(`#link-handle-${this.id}`).hide();
                    $(`#${this.id}_summary`).remove();
                    super.updateTable('live-log-table', [[this.translations.noaccess]], 'error');
                    this.config.callbacks.updateGrid();
                }
            });
        }
    }

    onVisibilityChanged(visible) {
        super.onVisibilityChanged(visible);
        if (!visible) {
            this._hasData = false;
        }
    }

    async onWidgetOptionsChanged(options) {
        this.closeEventSource();
        this.eventSourceRetryCount = 0;
        this._hasData = false;
        $(`#live-log-table .flextable-row`).remove();
        this._startLog(options);
    }

    async getWidgetOptions() {
        return {
            source: {
                id: 'source',
                title: this.translations.source,
                type: 'select',
                options: Object.keys(this._sources).length > 0
                    ? Object.entries(this._sources).map(([key, label]) => ({
                        value: key,
                        label: label
                    }))
                    : [{ value: '', label: this.translations.nosources }],
                default: this._defaultSource() ?? ''
            },
            severity: {
                id: 'severity',
                title: this.translations.severity,
                type: 'select',
                options: this._severityLevels.map(s => ({
                    value: s,
                    label: this.translations[`severity${s.toLowerCase()}`] ?? s
                })),
                default: 'Notice'
            },
            lineCount: {
                id: 'lineCount',
                title: this.translations.linecount,
                type: 'select',
                options: [
                    { value: '5', label: '5' },
                    { value: '10', label: '10' },
                    { value: '25', label: '25' },
                    { value: '50', label: '50' },
                    { value: '100', label: '100' }
                ],
                default: '25'
            },
            searchFilter: {
                id: 'searchFilter',
                title: this.translations.searchfilter,
                type: 'text',
                placeholder: this.translations.searchfilterplaceholder,
                default: ''
            }
        };
    }
}
