{#
 # Copyright (c) 2026 Deciso B.V.
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1. Redistributions of source code must retain the above copyright notice,
 #    this list of conditions and the following disclaimer.
 #
 # 2. Redistributions in binary form must reproduce the above copyright notice,
 #    this list of conditions and the following disclaimer in the documentation
 #    and/or other materials provided with the distribution.
 #
 # THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
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
    $( document ).ready(function() {
        const schedules = $('#{{formGridSchedule["table_id"]}}').UIBootgrid({
            search: '/api/firewall/schedule/search_item/',
            get: '/api/firewall/schedule/get_item/',
            add: '/api/firewall/schedule/add_item/',
            set: '/api/firewall/schedule/set_item/',
            del: '/api/firewall/schedule/del_item/',
            options: {
                triggerEditFor: getUrlHash('edit'),
                initialSearchPhrase: getUrlHash('search'),
                selection: true,
                multiSelect: false,
                rowSelect: true,
                stickySelect: true,
                formatters: {
                    text: function (column, row) {
                        return $('<span>').text(row[column.id] || '')[0];
                    }
                }
            },
            commands: {
                copy: {
                    filter: function () {
                        return false;
                    }
                }
            }
        }).on('selected.rs.jquery.bootgrid loaded.rs.jquery.bootgrid', function () {
            const rows = schedules.bootgrid('getCurrentRows');
            if (!schedules.bootgrid('getSelectedRows').length && rows.length) {
                schedules.bootgrid('select', [rows[0].uuid]);
            }
            $('#{{formGridRange["table_id"]}}').bootgrid('reload');
        });
        $(document).on('settings-changed', function () {
            schedules.bootgrid('reload');
        });

        $('#{{formGridRange["table_id"]}}').UIBootgrid({
            search: '/api/firewall/schedule/search_range/',
            get: '/api/firewall/schedule/get_range/',
            add: '/api/firewall/schedule/add_range/',
            set: '/api/firewall/schedule/set_range/',
            del: '/api/firewall/schedule/del_range/',
            options: {
                responsive: true,
                formatters: {
                    text: function (column, row) {
                        return $('<span>').text(row[column.id] || '*')[0];
                    },
                    days: function (column, row) {
                        if (row.position) {
                            return $('<span>').text(row.position.split(',').map(day =>
                                new Intl.DateTimeFormat(undefined, {weekday: 'long'})
                                    .format(new Date(2000, 0, 2 + Number(day)))
                            ).join(', '))[0];
                        }
                        return $('<span>').text((row.dates || '').split(',').filter(Boolean).map(value => {
                            const [month, day] = value.split('/').map(Number);
                            return new Intl.DateTimeFormat(undefined, {month: 'long', day: 'numeric'})
                                .format(new Date(2000, month - 1, day));
                        }).join(', ') || '*')[0];
                    },
                    interval: function (column, row) {
                        return $('<span>').text(row.stop_time ? row.start_time + ' – ' + row.stop_time : '*')[0];
                    }
                },
                requestHandler: function (request) {
                    const selected = schedules.bootgrid('getSelectedRows');
                    request.schedule = selected.length ? selected[0] : '';
                    return request;
                }
            },
            commands: {
                copy: {
                    filter: function () {
                        return false;
                    }
                }
            }
        });

        /* Add headings beside the grid controls. */
        $('#{{formGridSchedule["table_id"]}}-header div.actionBar, #{{formGridRange["table_id"]}}-header div.actionBar').each(function () {
            $(this).parent().prepend($('<div class="col-sm-2 theading-text">').text(
                $(this).closest('.bootgrid-header').attr('id') === '{{formGridSchedule["table_id"]}}-header'
                    ? "{{ lang._('Schedules') }}"
                    : "{{ lang._('Time ranges') }}"
            ));
            $(this).removeClass('col-sm-12').addClass('col-sm-10');
        });

        const rangeDialog = $('#{{formGridRange["edit_dialog_id"]}}');
        const field = id => rangeDialog.find('[id="' + id + '"]');
        const row = id => field(id).closest('tr');

        // Give this yearless calendar its own locale rather than changing the shared English locale.
        $.fn.datepicker.dates.schedule = $.extend({}, $.fn.datepicker.dates.en, {titleFormat: 'MM'});
        field('timerange.dates').datepicker({
            language: 'schedule',
            format: {
                toDisplay: function (date) {
                    return String(date.getUTCMonth() + 1).padStart(2, '0') + '/' +
                        String(date.getUTCDate()).padStart(2, '0');
                },
                toValue: function (value) {
                    const [month, day] = value.split('/').map(Number);
                    return new Date(Date.UTC(2000, month - 1, day));
                }
            },
            multidate: true,
            weekStart: 1,
            clearBtn: true,
            maxViewMode: 0,
            // Pin parsing and bounds to a leap year; the picker expects UTC dates from toValue().
            defaultViewDate: {year: 2000, month: 0, day: 1},
            startDate: new Date(2000, 0, 1),
            endDate: new Date(2000, 11, 31),
            container: rangeDialog
        }).on('changeDate clearDate change', updateDateFields);

        function updateDateFields() {
            row('timerange.dates').toggle(!field('timerange.position').val().length);
            row('timerange.position').toggle(!field('timerange.dates').val());
        }

        field('timerange.position').on('change', updateDateFields);
        rangeDialog.on('opnsense_bootgrid_mapped', function () {
            field('timerange.dates').datepicker('update');
            updateDateFields();
        });

        $('#reconfigureAct').SimpleActionButton();
    });
</script>

{# Keep deletion single-item in both grids, matching the API. #}
<div class="content-box __mb">
    {{ partial('layout_partials/base_bootgrid_table', formGridSchedule + {'hide_delete': true}) }}
</div>
<div class="content-box">
    {{ partial('layout_partials/base_bootgrid_table', formGridRange + {'hide_delete': true}) }}
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/firewall/schedule/reconfigure'}) }}
{{ partial('layout_partials/base_dialog', ['fields': formDialogSchedule, 'id': formGridSchedule['edit_dialog_id'], 'label': lang._('Edit schedule')]) }}
{{ partial('layout_partials/base_dialog', ['fields': formDialogRange, 'id': formGridRange['edit_dialog_id'], 'label': lang._('Edit time range')]) }}
