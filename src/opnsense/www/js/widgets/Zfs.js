/*
 * Copyright (C) 2026 Deciso B.V.
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

export default class Zfs extends BaseTableWidget {
    constructor() {
        super();

        this.tickTimeout = 60;
    }

    getMarkup() {
        const $table = this.createTable('zfs-table', {
            headerPosition: 'left',
        });

        /*
         * Separate the pool header from its details when the native
         * left-header layout stacks vertically.
         */
        Object.assign(this.sizeStates[0]['.column'], {
            'border-top': 'inherit',
            'margin-top': '0.5em',
            'padding-top': '0.5em',
        });

        Object.assign(this.sizeStates[this.headerBreakpoint]['.column'], {
            'border-top': '',
            'margin-top': '',
            'padding-top': '',
        });

        return $table;
    }

    poolField(name, state) {
        const value = state || 'UNKNOWN';

        let color = 'text-danger';
        if (value === 'ONLINE') {
            color = 'text-success';
        } else if (value === 'DEGRADED') {
            color = 'text-warning';
        }

        const $status = $('<i>')
            .addClass(`fa fa-circle text-muted ${color} zfs-status-icon`)
            .css({
                'font-size': '11px',
                'cursor': 'pointer',
            })
            .attr('data-toggle', 'tooltip')
            .attr('title', value);

        return $('<div>')
            .append($status)
            .append('&nbsp;')
            .append($('<span>').text(name))
            .prop('outerHTML');
    }

    scanFunction(value) {
        if (value === 'SCRUB') {
            return this.translations.scrub;
        }

        if (value === 'RESILVER') {
            return this.translations.resilver;
        }

        return value || this.translations.scan;
    }

    formatTimestamp(timestamp) {
        const value = Number(timestamp);

        if (!Number.isFinite(value) || value <= 0) {
            return '';
        }

        return new Date(value * 1000).toLocaleString([], {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            timeZone: 'UTC',
            timeZoneName: 'short',
        });
    }

    scanField(scan) {
        const $field = $('<div>')
            .append($('<b>').text(this.translations.scan));

        if (!scan) {
            return $field
                .append('<br>')
                .append($('<span>').text(this.translations.never))
                .prop('outerHTML');
        }

        const func = this.scanFunction(scan.function);
        const state = scan.state || 'UNKNOWN';

        if (state === 'SCANNING') {
            const total = Number(scan.to_examine ?? 0);
            const issued = Number(scan.issued ?? 0);

            let value = `${func}: ${this.translations.running}`;

            if (
                Number.isFinite(total) &&
                total > 0 &&
                Number.isFinite(issued)
            ) {
                const progress = Math.max(0, (issued / total) * 100);
                value = `${func}: ${progress.toFixed(1)}%`;
            }

            return $field
                .append('<br>')
                .append($('<span>').text(value))
                .prop('outerHTML');
        }

        const value = state === 'FINISHED'
            ? func
            : `${func}: ${state}`;

        $field
            .append('<br>')
            .append($('<span>').text(value));

        if (state === 'FINISHED') {
            const finished = this.formatTimestamp(scan.end_time);

            if (finished) {
                $field
                    .append('<br>')
                    .append($('<span>').text(finished));
            }
        }

        return $field.prop('outerHTML');
    }

    countDeviceErrors(node) {
        if (!node || typeof node !== 'object') {
            return 0;
        }

        const children = Object.values(node.vdevs ?? {});
        const hasErrorCounters =
            'read_errors' in node ||
            'write_errors' in node ||
            'checksum_errors' in node;

        if (hasErrorCounters && children.length === 0) {
            return (
                Number(node.read_errors ?? 0) > 0 ||
                Number(node.write_errors ?? 0) > 0 ||
                Number(node.checksum_errors ?? 0) > 0
            ) ? 1 : 0;
        }

        let count = 0;

        for (const value of Object.values(node)) {
            if (value && typeof value === 'object') {
                count += this.countDeviceErrors(value);
            }
        }

        return count;
    }

    getErrorSummary(pool) {
        const dataErrors = Number(pool.error_count ?? 0);

        return {
            devices: this.countDeviceErrors(pool),
            data: Number.isFinite(dataErrors) ? dataErrors : 0,
        };
    }

    errorField(pool) {
        const errors = this.getErrorSummary(pool);
        const parts = [];

        if (errors.devices > 0) {
            parts.push(
                `${errors.devices} ${
                    errors.devices === 1
                        ? this.translations.device
                        : this.translations.devices
                }`
            );
        }

        if (errors.data > 0) {
            parts.push(
                `${errors.data} ${
                    errors.data === 1
                        ? this.translations.dataerror
                        : this.translations.dataerrors
                }`
            );
        }

        const $value = $('<span>').text(
            parts.length > 0
                ? parts.join(' / ')
                : this.translations.none
        );

        if (errors.data > 0) {
            $value.addClass('text-danger');
        } else if (errors.devices > 0) {
            $value.addClass('text-warning');
        }

        return $('<div>')
            .append($('<b>').text(this.translations.errors))
            .append('<br>')
            .append($value)
            .prop('outerHTML');
    }

    renderPools(pools) {
        const statusSelector = '#zfs-table .zfs-status-icon';
        $(statusSelector).tooltip('hide');

        const rows = Object.entries(pools).map(([name, pool]) => [
            this.poolField(pool.name || name, pool.state),
            [
                this.errorField(pool),
                this.scanField(pool.scan_stats),
            ],
        ]);

        if (rows.length === 0) {
            rows.push([
                $('<span>')
                    .text(this.translations.nopools)
                    .prop('outerHTML'),
                '',
            ]);
        }

        this.updateTable('zfs-table', rows);
        $(statusSelector).tooltip({container: 'body'});
    }

    async onWidgetTick() {
        const status = await this.ajaxCall(
            '/api/diagnostics/system/zfs_status'
        );

        this.renderPools(status?.pools ?? {});
    }
}
