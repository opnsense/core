<?php

/*
 * Copyright (C) 2018 Fabian Franz
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2004 Scott Ullrich <sullrich@gmail.com>
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
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

require_once('guiconfig.inc');
require_once('filter.inc');

$return_to = '/firewall_schedule.php';

$days = [
    gettext('Mon'),
    gettext('Tue'),
    gettext('Wed'),
    gettext('Thu'),
    gettext('Fri'),
    gettext('Sat'),
    gettext('Sun')
];

$months = [
    gettext('January'),
    gettext('February'),
    gettext('March'),
    gettext('April'),
    gettext('May'),
    gettext('June'),
    gettext('July'),
    gettext('August'),
    gettext('September'),
    gettext('October'),
    gettext('November'),
    gettext('December')
];

function initSchedule(array $config_schedules): array {
    global $return_to;

    $id = null;
    $schedule = null;

    // Was the Add button clicked?
    if (empty($_GET)) {
        return [
            'id' => null,
            'name' => null,
            'description' => null,
            'time_ranges' => []
        ];
    }

    if (isset($_GET['name'])) {
        foreach ($config_schedules as $config_id => $config_schedule) {
            if ($config_schedule['name'] != $_GET['name']) {
                continue;
            }

            $id = (int)$config_id;
            $schedule = $config_schedule;
            break;
        }
    }
    elseif (isset($_GET['dup'])) {
        // NOTE: Schedule is being cloned; so $id MUST NOT be set
        $config_id = $_GET['dup'];
        $schedule = @$config_schedules[$config_id];
    }
    elseif (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $schedule = @$config_schedules[$id];
    }

    // Invalid request
    if (!$schedule) {
        header(url_safe("Location: {$return_to}"));
        exit;
    }

    return [
        'id' => $id,
        'name' => $schedule['name'],
        'description' => $schedule['descr'],
        'time_ranges' => @$schedule['timerange'] ?? []
    ];
}

function isNameEditable(string $name): bool {
    global $config;

    $name = trim($name);

    if (!$name || !isset($config['filter']['rule'])) {
        return true;
    }

    foreach ($config['filter']['rule'] as $rule) {
        if ($rule['sched'] != $name) {
            continue;
        }

        return false;
    }

    return true;
}

function sortByName(array &$config_schedules): void {
    if (empty($config_schedules)) {
        return;
    }

    usort($config_schedules, function ($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
}

function _getSelectedDaysNonRepeating(array $time_range): ?object {
    global $months;

    if (empty($time_range['month'])) {
        return null;
    }

    $day_range_start = null;
    $days_selected = [];
    $days_selected_text = [];
    $selected_months = explode(',', $time_range['month']);
    $selected_days = explode(',', $time_range['day']);

    foreach ($selected_months as $selection_num => $month) {
        $month = (int)$month;
        $day = (int)$selected_days[$selection_num];

        // Mon = 1, Tue = 2, Wed = 3, Thu = 4, Fri = 5, Sat = 6, Sun = 7
        // NOTE: First day of the week is Monday based on ISO 8601
        $day_of_week = date('N', mktime(0, 0, 0, $month, $day, date('Y')));
        $week_of_year = (int)date('W', mktime(0, 0, 0, $month, $day, date('Y')));

        $days_selected[] = sprintf('w%dp%d-m%dd%d', $week_of_year, $day_of_week, $month, $day);

        $day_range_start = $day_range_start ?? $day;
        $month_short = substr($months[$month - 1], 0, 3);
        $next_selected_day = (int)$selected_days[$selection_num + 1];
        $next_selected_month = (int)$selected_months[$selection_num + 1];

        // Continue to the next day when working on a range (i.e. Feb 6 - 8)
        if ($month == $next_selected_month && ($day + 1) == $next_selected_day) {
            continue;
        }

        // Prepare the friendly labels for selected days
        if ($day == $day_range_start) {
            $days_selected_text[] = sprintf('%s %s', $month_short, $day);
            $day_range_start = null;
            continue;
        }

        $days_selected_text[] = sprintf(
            '%s %s - %s',
            $month_short,
            $day_range_start,
            $day
        );
        $day_range_start = null;
    }

    return (object)[
        'days_selected_text' => implode(', ', $days_selected_text),
        'days_selected' => implode(',', $days_selected)
    ];
}

function _getSelectedDaysRepeating(array $time_range): ?object {
    global $days;

    if (!isset($time_range['position'])) {
        return null;
    }

    $day_range_start = null;
    $days_selected_text = [];
    $days_of_week = explode(',', $time_range['position']);

    // Make days display as a range instead of a comma-delimited list; for
    // example, instead of "Mon, Tues, Wed", display "Mon - Wed" instead
    foreach ($days_of_week as $i => $day_of_week) {
        $day_of_week = (int)$day_of_week;

        if (!$day_of_week) {
            continue;
        }

        $day_range_start = $day_range_start ?? $day_of_week;
        $next_selected_day = $days_of_week[$i + 1];

        // Continue to the next day when working on a range (i.e. Feb 6 - 8)
        if (($day_of_week + 1) == $next_selected_day) {
            continue;
        }

        $start_day = $days[$day_range_start - 1];
        $end_day = $days[$day_of_week - 1];

        if ($day_of_week == $day_range_start) {
            $days_selected_text[] = $start_day;
            $day_range_start = null;
            continue;
        }

        $days_selected_text[] = sprintf('%s - %s', $start_day, $end_day);
        $day_range_start = null;
    }

    return (object)[
        'days_selected_text' => implode(', ', $days_selected_text),
        'days_selected' => $time_range['position']
    ];
}

function getConfiguredTimeRanges(array $schedule): array {
    if (!isset($schedule['time_ranges'])) {
        return [];
    }

    $time_ranges = [];

    foreach ($schedule['time_ranges'] as $time_range) {
        if (!$time_range) {
            continue;
        }

        [$start_time, $stop_time] = explode('-', $time_range['hour']);
        $description = rawurldecode($time_range['rangedescr']);

        if ($time_range['month']) {
            $data = _getSelectedDaysNonRepeating($time_range);

            if (!$data) {
                continue;
            }

            $data->start_time = $start_time;
            $data->stop_time = $stop_time;
            $data->description = $description;
            $time_ranges[] = $data;

            continue;
        }

        $data = _getSelectedDaysRepeating($time_range);

        if (!$data) {
            continue;
        }

        $data->start_time = $start_time;
        $data->stop_time = $stop_time;
        $data->description = $description;
        $time_ranges[] = $data;
    }

    return $time_ranges;
}

function getMonthOptions(): string {
    $month = (int)date('n');
    $year = (int)date('Y');
    $options = [];

    for ($m = 0; $m < 12; $m++) {
        $options[] = sprintf(
            '<option value="%d">%s</option>',
            $month,
            date('F_y', mktime(0, 0, 0, $month, 1, $year))
        );

        if ($month++ < 12) {
            continue;
        }

        $month = 1;
        $year++;
    }

    return implode("\n", $options);
}

function get24HourOptions(): string {
    $options = [];

    for ($h = 0; $h < 24; $h++) {
        $options[] = sprintf('<option value="%d">%d</option>', $h, $h);
    }

    return implode("\n", $options);
}

function getCalendarTableBody($month, $year): string {
    // Mon = 1, Tue = 2, Wed = 3, Thu = 4, Fri = 5, Sat = 6, Sun = 7
    // NOTE: First day of the week is Monday based on ISO 8601
    $day_of_week = 1;
    $first_day_week_start = date('N', mktime(0, 0, 0, $month, 1, $year));

    $day_of_month = 1;
    $max_days_in_month = date('t', mktime(0, 0, 0, $month, 1, $year));
    $html_attribs = '';
    $html_text = '';

    $table_rows = [];

    while ($day_of_month <= $max_days_in_month) {
        $week_of_year = (int)date('W', mktime(0, 0, 0, $month, $day_of_month, $year));

        // Is it Monday?
        if ($day_of_week == 1) {
            $table_rows[] = '<tr>';
        }

        if ($first_day_week_start == $day_of_week
            || ($html_text && $day_of_month <= $max_days_in_month)
        ) {
            $cell_id = sprintf('w%dp%d', $week_of_year, $day_of_week);
            $toggle_cell_id = sprintf(
                '%s-m%dd%d',
                $cell_id,
                $month,
                $day_of_month
            );

            $html_attribs = sprintf(
                ' id="%s" class="calendar-day" onclick="toggleSingleOrRepeatingDays(\'%s\');"',
                $cell_id,
                $toggle_cell_id
            );

            $html_text = $day_of_month;
            $day_of_month++;
        }

        $table_rows[] = sprintf('<td%s>%s</td>', $html_attribs, $html_text);

        // Is it Sunday or the last day of the month?
        if ($day_of_week++ >= 7 || $day_of_month > $max_days_in_month) {
            $day_of_week = 1;
            $table_rows[] = '</tr>';
        }
    }

    return implode("\n", $table_rows);
}

// $id will be null when saving a new schedule
function validateName(array $schedule, array $config_schedules, ?int $id = null): array {
    $errors = [];

    if (!preg_match('/^[a-zA-Z0-9_\-]{1,32}$/', $schedule['name'])) {
        $errors[] = gettext('The schedule name must be less than 32 characters long and may only consist of the following characters: a-z, A-Z, 0-9, _');
    }
    if (strtolower($schedule['name']) == 'lan') {
        $errors[] = gettext('Schedule may not be named LAN.');
    }
    if (strtolower($schedule['name']) == 'wan') {
        $errors[] = gettext('Schedule may not be named WAN.');
    }
    if (empty($schedule['name'])) {
        $errors[] = gettext('Schedule may not use a blank name.');
    }

    // Check for name conflicts
    foreach ($config_schedules as $config_id => $config_schedule) {
        if ($config_schedule['name'] != $schedule['name'] || $config_id == @$id) {
            continue;
        }

        $errors[] = gettext('A Schedule with this name already exists.');
        break;
    }

    return $errors;
}

function _isValidTime(string $time): bool {
    return preg_match('/^[0-9]+:[0-9]+$/', $time);
}

function validateTimes(string $start_time, string $stop_time): array {
    $errors = [];

    if (!_isValidTime($start_time)) {
        $errors[] = gettext(sprintf('Invalid start time - "%s"', $start_time));
    }

    if (!_isValidTime($stop_time)) {
        $errors[] = gettext(sprintf('Invalid stop time - "%s"', $stop_time));
    }

    return $errors;
}


$config_schedules = &config_read_array('schedules', 'schedule');
$schedule = initSchedule($config_schedules);
$id = $schedule['id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = @$_POST['id'];
    $id = (!isset($config_schedules[$id])) ? null : (int)$id;

    $schedule = $_POST;

    $save_errors = validateName($schedule, $config_schedules, $id);

    // Parse time ranges
    $schedule['time_ranges'] = [];

    if ($schedule['days']) {
        foreach ($schedule['days'] as $range_num => $selected_days) {
            $start_time = $schedule['start_times'][$range_num];
            $stop_time = $schedule['stop_times'][$range_num];

            $errors = validateTimes($start_time, $stop_time);
            if ($errors) {
                $save_errors = array_merge($save_errors, $errors);
                continue;
            }

            $time_range = [
                'hour' => sprintf('%s-%s', $start_time, $stop_time),
                'rangedescr' => rawurlencode($schedule['range_descriptions'][$range_num])
            ];

            // Repeating time ranges
            if (!strstr($selected_days, '-')) {
                $schedule['time_ranges'][$range_num] = array_merge(
                    $time_range,
                    ['position' => $selected_days]
                );

                continue;
            }

            // Single time ranges
            $selected_days = explode(',', $selected_days);
            $months = [];
            $days = [];

            foreach ($selected_days as $selected_day) {
                if (!$selected_day) {
                    continue;
                }

// FIXME: Can the first part (i.e. w6p1) be removed if it's not even going to be used when saving?
                [$ignore, $month_and_day] = explode('-', $selected_day);

                [$month, $day] = explode('d', $month_and_day);
                $months[] = ltrim($month, 'm');
                $days[] = $day;
            }

            $schedule['time_ranges'][$range_num] = array_merge(
                $time_range,
                [
                    'month' => implode(',', $months),
                    'day' => implode(',', $days)
                ]
            );
        }
    }

    if (!$schedule['time_ranges']) {
        $save_errors[] = gettext('The schedule must have at least one time range configured.');
    }

    if (!$save_errors) {
        $id = (isset($id)) ? $id : count($config_schedules);
        $config_schedules[$id] = [
            'name' => $schedule['name'],
            'descr' => $schedule['description'],
            'timerange' => $schedule['time_ranges']
        ];

        sortByName($config_schedules);
        write_config();
        filter_configure();

        header(url_safe("Location: {$return_to}"));
        exit;
    }
}

legacy_html_escape_form_data($schedule);

include('head.inc');
?>
<body>
<style>
#show_all_help_page {
  cursor: pointer;
}
.calendar-header-day {
  text-decoration: underline;
  text-align: center;
  cursor: pointer;
}
.calendar-day {
  text-align: center;
  cursor: pointer;
}
.time-range-configured {
  word-wrap: break-word;
  max-width: initial !important;
  border: 0 !important;
  border-radius: 0 !important;
}
</style>
<script>
//<![CDATA[
function _addSelectedDays(cell_id) {
    $('#iform')[0]._selected_days[cell_id] = 1;
}

function _removeSelectedDays(cell_id) {
    const iform = $('#iform')[0];

    if (!(cell_id in iform._selected_days))
        return;

    delete iform._selected_days[cell_id];
}

function _getSelectedDays(is_sort = false) {
    const selected_days = Object.keys($('#iform')[0]._selected_days || {});

    if (!is_sort)
        return selected_days;

    selected_days.sort();
    return selected_days;
}

function _isSelectedDaysEmpty() {
    return !_getSelectedDays().length;
}

function _clearSelectedDays() {
    // Use an object instead of an array to leverage hash-sieving for faster
    // lookups and to prevent duplicates
    $('#iform')[0]._selected_days = {};
}

function _toggleRepeatingDays(day_of_week, is_highlight = false) {
    for (let week_of_year = 1; week_of_year <= 53; week_of_year++) {
        let cell_id = `w${week_of_year}p${day_of_week}`;
        let day_cell = $(`#${cell_id}`);

        if (!day_cell.length)
            continue;

        day_cell.attr('data-state', (is_highlight) ? 'lightcoral' : 'white');

        _removeSelectedDays(cell_id);
    }
}

function toggleSingleOrRepeatingDays(cell_id) {
    let week_and_position, month_and_day;
    [week_and_position, month_and_day] = cell_id.split('-');

    const is_repeating = !month_and_day;
    const day_of_week = parseInt(week_and_position.slice(-1));

    let day_cell = $('#' + ((is_repeating) ? cell_id : week_and_position));

    if (!day_cell.length) {
        // Move to the following week when an invalid cell is found
        let next_week_of_year = parseInt(week_and_position.split('p')[0].slice(1)) + 1;

        day_cell = $(`#w${next_week_of_year}p${day_of_week}`);

        if (!day_cell.length) {
            // Something is really wrong if a cell for the following week wasn't
            // found
            return alert('Failed to find the correct day to toggle. Please save your schedule and try again.');
        }
    }

    // Deselect an individual day
    if (day_cell.attr('data-state') === 'red') {
        day_cell.attr('data-state', 'white');
        return _removeSelectedDays(cell_id);
    }

    // Deselect a repeating day in a week
    if (day_cell.attr('data-state') === 'lightcoral')
        return _toggleRepeatingDays(day_of_week);

    // Select a repeating day in a week
    if (is_repeating) {
        _toggleRepeatingDays(day_of_week, true);
        _addSelectedDays(cell_id);
        return day_cell.attr('data-state', 'lightcoral');
    }

    // Select an individual day
    _addSelectedDays(cell_id);
    day_cell.attr('data-state', 'red');
}

function showSelectedMonth() {
    const month_select = $(this);

    // The first month will always be visible by default when the page loads;
    // otherwise, the visible_month property should be set
    const visible_month = month_select.prop('visible_month') || month_select.prop('options')[0].label;
    $(`#${visible_month}`).css('display', 'none');

    const selected_month = month_select.prop('selectedOptions')[0].label;
    month_select.prop('visible_month', selected_month);
    $(`#${selected_month}`).css('display', 'block');
}

function hasSelectedDaysInProgress() {
    if (!_isSelectedDaysEmpty()) {
        alert('You have not saved the specified time range. Please click "Add Time" button to save the time range.');
        return true;
    }

    return false;
}

function addTimeRange() {
    const _months = <?= json_encode($months) ?>;
    const _days = <?= json_encode($days) ?>;
    const start_hour = parseInt($('#start-hour').val());
    const start_minute = $('#start-minute').val();
    const stop_hour = parseInt($('#stop-hour').val());
    const stop_minute = $('#stop-minute').val();
    const start_time = `${start_hour}:${start_minute}`;
    const stop_time = `${stop_hour}:${stop_minute}`;
    const range_description = $('#range-description').val();

    let selected_months = [];
    let selected_days = [];
    let days_of_week = [];
    let days_selected = [];

    // Do time checks
    if (start_hour > stop_hour)
        return alert('Error: Start Hour cannot be greater than Stop Hour.');

    if (start_hour === stop_hour && parseInt(start_minute) > parseInt(stop_minute))
        return alert('Error: Start Minute cannot be greater than Stop Minute.');

    if (_isSelectedDaysEmpty()) {
        return alert('You must select at least one day before adding time');
    }

    _getSelectedDays(true).forEach(function(cell_id) {
        if (!cell_id)
            return;

        let week_and_position, month_and_day;
        [week_and_position, month_and_day] = cell_id.split('-');

        // Single days
        if (month_and_day) {
            let month, day;
            [month, day] = month_and_day.split('d');

            selected_months.push(month.slice(1));
            selected_days.push(day);
            days_selected.push(cell_id);
            return;
        }

        // Repeating days
        let week_of_year, day_of_week;
        [week_of_year, day_of_week] = week_and_position.split('p');

        days_of_week.push(day_of_week);
    });

    // Single days
    if (selected_months.length) {
        let day_range_start = null;
        let days_selected_text = [];

        selected_months.forEach(function(month, selection_num) {
            month = parseInt(month);

            if (!(month && !isNaN(month)))
                return;

            const day = parseInt(selected_days[selection_num]);
            const next_selected_day = parseInt(selected_days[selection_num + 1]);
            const next_selected_month = parseInt(selected_months[selection_num + 1]);
            const month_short = _months[month - 1].slice(0, 3);

            day_range_start = (!day_range_start) ? day : day_range_start;

            // Continue to the next day when working on a range (i.e. Feb 6 - 8)
            if (month === next_selected_month && (day + 1) === next_selected_day)
                return;

            if (day === day_range_start) {
                days_selected_text.push(`${month_short} ${day}`);
                day_range_start = null;
                return;
            }

            days_selected_text.push(`${month_short} ${day_range_start} - ${day}`);
            day_range_start = null;
        });

        injectTimeRange(
            days_selected_text.join(', '),
            days_selected.join(','),
            start_time,
            stop_time,
            range_description
        );
    }

    // Repeating days
    if (days_of_week.length) {
        let day_range_start = null;
        let days_selected_text = [];

        days_of_week.sort();

        days_of_week.forEach(function(day_of_week, i) {
            day_of_week = parseInt(day_of_week);

            if (!(day_of_week && !isNaN(day_of_week)))
                return;

            let next_selected_day = parseInt(days_of_week[i + 1]);

            day_range_start = (!day_range_start) ? day_of_week : day_range_start;

            if ((day_of_week + 1) === next_selected_day)
                return;

            let start_day = _days[day_range_start - 1];
            let end_day = _days[day_of_week - 1];

            if (day_of_week === day_range_start) {
                days_selected_text.push(start_day);
                day_range_start = null;
                return;
            }

            days_selected_text.push(`${start_day} - ${end_day}`);
            day_range_start = null;
        });

        injectTimeRange(
            days_selected_text.join(', '),
            days_of_week.join(','),
            start_time,
            stop_time,
            range_description
        );
    }
}

function injectTimeRange(
    days_text,
    days,
    start_time,
    stop_time,
    range_description,
    is_clear_calendar = true
) {
    const tbody = $('#calendar tbody');
    const tr = $('<tr></tr>');
    const edit_click = `return editTimeRange.bind(this)('${days}', '${start_time}', '${stop_time}', '${range_description}');`;
    const delete_click = `return removeTimeRange.bind(this)(true);`;

    tbody.append(tr);
    tr.append(`<td><span>${days_text}</span> <input type="hidden" name="days[]" value="${days}" /></td>`);
    tr.append(`<td><input type="text" name="start_times[]" value="${start_time}" class="time-range-configured" readonly="readonly" /></td>`);
    tr.append(`<td><input type="text" name="stop_times[]" value="${stop_time}" class="time-range-configured" readonly="readonly" /></td>`);
    tr.append(`<td><input type="text" name="range_descriptions[]" value="${range_description}" class="time-range-configured" readonly="readonly" /></td>`);
    tr.append(`<td><a href="#" class="btn btn-default" onclick="${edit_click}"><span class="fa fa-pencil fa-fw"></span></a></td>`);
    tr.append(`<td><a href="#" class="btn btn-default" onclick="${delete_click}"><span class="fa fa-trash fa-fw"></span></a></td>`);

    if (!is_clear_calendar)
        return;

    clearCalendar(true);
}

function clearCalendar(is_clear_description = false) {
    _clearSelectedDays();

    const month_select = $('#month-select');
    const visible_month = (month_select.prop('visible_month')
        || month_select.prop('options')[0].label
    );

    $(`#${visible_month}`)
        .parent()
        .find('tbody td[data-state]')
        .filter('[data-state != "white"]')
        .attr('data-state', 'white');

    resetStartAndStopTimes();

    if (is_clear_description)
        clearTimeRangeDescription();
}

function resetStartAndStopTimes() {
    $('#start-hour').val('0');
    $('#start-minute').val('00');
    $('#stop-hour').val('23');
    $('#stop-minute').val('59');
    $('.selectpicker.form-control').selectpicker('refresh');
}

function clearTimeRangeDescription(){
    $('#range-description').val('');
}

function editTimeRange(days, start_time, stop_time, range_description) {
    const _refreshSelectPickers = function() {
        $('.selectpicker').selectpicker('refresh');

        // Stop the onClick event from propagating
        return false;
    };

    $(this).blur();

    if (hasSelectedDaysInProgress())
        return _refreshSelectPickers();

    clearCalendar();

    let start_hour, start_min, stop_hour, stop_min;
    [start_hour, start_min] = start_time.split(':');
    [stop_hour, stop_min] = stop_time.split(':');

    $('#start-hour').val(start_hour);
    $('#start-minute').val(start_min);
    $('#stop-hour').val(stop_hour);
    $('#stop-minute').val(stop_min);
    $('#range-description').val(range_description);

    days = days.split(',');
    days.forEach(function(day) {
        if (!day)
            return;

        // Repeating days
        if (day.length === 1)
            return toggleSingleOrRepeatingDays(`w1p${day}`);

        // Single day
        toggleSingleOrRepeatingDays(day);
    });

    removeTimeRange.bind(this)();

    return _refreshSelectPickers();
}

// A "false" value is returned to stop the onClick event from propagating
function removeTimeRange(is_confirm = false) {
    $(this).blur();

    if (is_confirm && !confirm('Do you really want to delete this time range?'))
        return false;

    $(this).closest('tr').remove();
    return false;
}


$(function() {
    // NOTE: Needed to prevent hook_stacked_form_tables() from breaking the
    // calendar's CSS when selecting days
    $('#iform td').css('background-color', '');

    const time_ranges = <?= json_encode(getConfiguredTimeRanges($schedule)) ?>;

    time_ranges.forEach(function(time_range) {
        injectTimeRange(
            time_range.days_selected_text,
            time_range.days_selected,
            time_range.start_time,
            time_range.stop_time,
            time_range.description,
            false
        );
    });

    clearCalendar(true);
});
//]]>
</script>

<?php include('fbegin.inc'); ?>

  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
<?php
if (count($save_errors ?? [])) {
    print_input_errors($save_errors);
}
?>
        <section class="col-xs-12">
          <div class="content-box tab-content">
            <form method="post" name="iform" id="iform">
              <table class="table table-striped opnsense_standard_table_form">
                <tbody>
                  <tr>
                    <td style="width: 15%">
                      <strong><?= gettext('Schedule information') ?></strong>
                    </td>
                    <td style="width: 85%; text-align: right">
                      <small><?= gettext('full help') ?></small>
                      <em id="show_all_help_page" class="fa fa-toggle-off text-danger"></em>
                    </td>
                  </tr>
                  <tr>
                    <td><em class="fa fa-info-circle text-muted"></em> <?= gettext('Name') ?></td>
                    <td>
<?php if (isset($id) && !isNameEditable($schedule['name'])): ?>
                      <?= $schedule['name'] ?>
                      <p><?= gettext('This schedule is in use so the name may not be modified!') ?></p>
                      <input name="name" type="hidden" value="<?= $schedule['name'] ?>" />
<?php else: ?>
                      <input type="text" name="name" value="<?= $schedule['name'] ?>" />
<?php endif ?>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <a id="help_for_description" href="#" class="showhelp"><em class="fa fa-info-circle"></em></a>
                      <?= gettext('Description') ?>
                    </td>
                    <td>
                      <input type="text" name="description" value="<?= $schedule['description'] ?>" />
                      <br />
                      <div class="hidden" data-for="help_for_description">
                        <?= gettext('You may enter a description here for your reference (not parsed).') ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <a id="help_for_month" href="#" class="showhelp"><em class="fa fa-info-circle"></em></a>
                      <?= gettext('Month') ?>
                    </td>
                    <td>
                      <select id="month-select" class="selectpicker"
                              data-width="auto" data-live-search="true" onchange="showSelectedMonth.bind(this)();">
                        <?= getMonthOptions(); ?>
                      </select>
                      <br />
                      <br />
<?php
$month = (int)date('n');
$year = (int)date('Y');

for ($m = 0; $m < 12; $m++) {
    $month_year = date('F_y', mktime(0, 0, 0, $month, 1, $year));
?>
                      <div id="<?= $month_year ?>" style="position: relative; display: <?= (!$m) ? 'block' : 'none' ?>;">
                        <table id="calTable<?= $month . $year ?>" class="table table-condensed table-bordered">
                          <thead>
                            <tr>
                              <td colspan="7" style="text-align: center"><?= $month_year ?></td>
                            </tr>
                            <tr>
                              <td class="calendar-header-day" onclick="toggleSingleOrRepeatingDays('w1p1');">
                                <?= gettext('Mon') ?>
                              </td>
                              <td class="calendar-header-day" onclick="toggleSingleOrRepeatingDays('w1p2');">
                                <?= gettext('Tue') ?>
                              </td>
                              <td class="calendar-header-day" onclick="toggleSingleOrRepeatingDays('w1p3');">
                                <?= gettext('Wed') ?>
                              </td>
                              <td class="calendar-header-day" onclick="toggleSingleOrRepeatingDays('w1p4');">
                                <?= gettext('Thu') ?>
                              </td>
                              <td class="calendar-header-day" onclick="toggleSingleOrRepeatingDays('w1p5');">
                                <?= gettext('Fri') ?>
                              </td>
                              <td class="calendar-header-day" onclick="toggleSingleOrRepeatingDays('w1p6');">
                                <?= gettext('Sat') ?>
                              </td>
                              <td class="calendar-header-day" onclick="toggleSingleOrRepeatingDays('w1p7');">
                                <?= gettext('Sun') ?>
                              </td>
                            </tr>
                          </thead>
                          <tbody>
                            <?= getCalendarTableBody($month, $year) ?>
                          </tbody>
                        </table>
                      </div>
<?php
    if ($month++ < 12) {
        continue;
    }

    $month = 1;
    $year++;
}
?>
                      <div class="hidden" data-for="help_for_month">
                        <br />
                        <?= gettext('Click individual date to select that date only. Click the appropriate weekday Header to select all occurrences of that weekday.') ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <a id="help_for_time" href="#" class="showhelp"><em class="fa fa-info-circle"></em></a>
                      <?= gettext('Time') ?>
                    </td>
                    <td>
                      <table class="tabcont">
                        <tr>
                          <td><?= gettext('Start Time') ?></td>
                          <td><?= gettext('Stop Time') ?></td>
                        </tr>
                        <tr>
                          <td>
                            <div class="input-group">
                              <select id="start-hour" class="selectpicker form-control"
                                      data-width="auto" data-size="5" data-live-search="true">
                                <?= get24HourOptions() ?>
                              </select>
                              <select id="start-minute" class="selectpicker form-control"
                                      data-width="auto" data-size="5" data-live-search="true">
                                <option value="00">00</option>
                                <option value="15">15</option>
                                <option value="30">30</option>
                                <option value="45">45</option>
                                <option value="59">59</option>
                              </select>
                            </div>
                          </td>
                          <td>
                            <div class="input-group">
                              <select id="stop-hour" class="selectpicker form-control"
                                      data-width="auto" data-size="5" data-live-search="true">
                                <?= get24HourOptions() ?>
                              </select>
                              <select id="stop-minute" class="selectpicker form-control"
                                      data-width="auto" data-size="5" data-live-search="true">
                                <option value="00">00</option>
                                <option value="15">15</option>
                                <option value="30">30</option>
                                <option value="45">45</option>
                                <option value="59" selected="selected">59</option>
                              </select>
                            </div>
                          </td>
                        </tr>
                      </table>

                      <div class="hidden" data-for="help_for_time">
                        <br />
                        <?= gettext('Select the time range for the day(s) selected on the Month(s) above. A full day is 0:00-23:59.') ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <a id="help_for_timerange_desc" href="#" class="showhelp"><em class="fa fa-info-circle"></em></a>
                      <?= gettext('Time Range Description') ?>
                    </td>
                    <td>
                      <input type="text" id="range-description" />
                      <div class="hidden" data-for="help_for_timerange_desc">
                        <?= gettext('You may enter a description here for your reference (not parsed).') ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td>&nbsp;</td>
                    <td>
                      <input type="button" value="<?= html_safe(gettext('Add Time')) ?>"
                             class="btn btn-default" style="margin: 0 5px;"
                             onclick="addTimeRange();" />
                      <input type="button" value="<?= html_safe(gettext('Clear Selection')) ?>"
                             class="btn btn-default" style="margin: 0 5px;"
                             onclick="clearCalendar(true);" />
                    </td>
                  </tr>
                  <tr>
                    <th colspan="2"><?= gettext('Schedule repeat') ?></th>
                  </tr>
                  <tr>
                    <td><?= gettext('Configured Ranges') ?></td>
                    <td>
                      <table id="calendar">
                        <tbody>
                          <tr>
                            <td style="width: 35%;"><?= gettext('Day(s)') ?></td>
                            <td style="width: 12%;"><?= gettext('Start Time') ?></td>
                            <td style="width: 11%;"><?= gettext('Stop Time') ?></td>
                            <td style="width: 42%;"><?= gettext('Description') ?></td>
                          </tr>
                        </tbody>
                      </table>
                    </td>
                  </tr>
                  <tr>
                    <td>&nbsp;</td>
                    <td>
                      <input type="submit" name="submit" id="submit"
                             value="<?= html_safe(gettext('Save')) ?>"
                             class="btn btn-primary" style="margin: 0 5px;"
                             onclick="return !hasSelectedDaysInProgress();" />
                      <input type="button" value="<?= html_safe(gettext('Cancel')) ?>"
                             class="btn btn-default" style="margin: 0 5px;"
                             onclick="window.location.href='<?= $return_to ?>'" />
                      <?= (isset($id)) ? sprintf('<input name="id" type="hidden" value="%d" />', $id) : '' ?>
                    </td>
                  </tr>
                </tbody>
              </table>
            </form>
          </div>
        </section>
      </div>
    </div>
  </section>

<?php include('foot.inc'); ?>
