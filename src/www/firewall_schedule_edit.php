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

class Schedule
{
    private const RETURN_URL = 'firewall_schedule.php';

    private $_days;
    private $_months;
    private $_rules;
    private $_config;
    private $_id;
    private $_data;
    private $_errors;

    public function __construct() {
        $this->_rules = config_read_array('filter', 'rule') ?? [];
        $this->_config = &config_read_array('schedules', 'schedule');
        $this->_errors = [];

        $this->_days = [
            gettext('Mon'),
            gettext('Tue'),
            gettext('Wed'),
            gettext('Thu'),
            gettext('Fri'),
            gettext('Sat'),
            gettext('Sun')
        ];

        $this->_months = [
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

        $this->_initSchedule();
    }

    private function _setError($message) {
        $this->_errors[] = $message;
    }

    final public function hasErrors() {
        return !empty($this->_errors);
    }

    final public function getErrors() {
        return $this->_errors;
    }

    final public function returnToSchedules(): void {
        header(url_safe(sprintf('Location: /%s', self::RETURN_URL)));
        exit;
    }

    final public function getReturnURL(): string {
        return self::RETURN_URL;
    }

    private function _escapeData(): void {
        $this->_data = (array)$this->_data;
        legacy_html_escape_form_data($this->_data);
        $this->_data = (object)$this->_data;
    }

    private function _setData(?array $data): void {
        // Invalid request
        if ($data === null) {
            $this->returnToSchedules();
        }

        $this->_data = (object)[
            'name' => $data['name'] ?? null,
            'description' => $data['descr'] ?? null,
            'time_ranges' => $data['timerange'] ?? []
        ];

        $this->_escapeData();
    }

    final public function getData(?string $prop = null) {
        if ($prop !== null) {
            return $this->_data->{$prop} ?? null;
        }

        return $this->_data;
    }

    private function _initSchedule(): void {
        // Add button clicked
        if (empty($_GET)) {
            $this->_setData([]);
            return;
        }

        // Calendar button clicked on the Rules screen
        if (isset($_GET['name'])) {
            foreach ($this->_config as $id => $schedule) {
                if ($schedule['name'] != $_GET['name']) {
                    continue;
                }

                $this->_id = $id;
                $this->_setData($schedule);
                return ;
            }

            // Invalid request
            $this->returnToSchedules();
        }

        // Clone button clicked
        if (isset($_GET['dup'])) {
            $this->_id = (int)$_GET['dup'];
            $schedule = @$this->_config[$this->_id];

            // Only the time range is needed when cloning because users should enter
            // a new name and/or description
            if ($schedule) {
                $schedule = ['timerange' => $schedule['timerange']];
            }

            // NOTE: Schedule is being cloned; so $_id MUST NOT be set
            $this->_id = null;

            $this->_setData($schedule);
            return;
        }

        // Edit button clicked
        if (isset($_GET['id'])) {
            $this->_id = (int)$_GET['id'];

            $this->_setData(@$this->_config[$this->_id]);
            return;
        }

        // Invalid request
        $this->returnToSchedules();
    }

    final public function hasID(): bool {
        return ($this->_id !== null);
    }

    final public function getID(): ?int {
        return $this->_id;
    }

    final public function getHTMLReferences(): string {
        if (!($this->_data->name && $this->_rules)) {
            return '';
        }

        $references = [];

        foreach ($this->_rules as $rule) {
            if (@$rule['sched'] != $this->_data->name) {
                continue;
            }

            $references[] = sprintf('<li>%s</li>', trim($rule['descr']));
        }

        return implode("\n", $references);
    }

    final public function getHTMLMonthOptions(): string {
        $month = (int)date('n');
        $year = (int)date('Y');
        $options = [];

        for ($m = 0; $m < 12; $m++) {
            $options[] = sprintf(
                '<option value="%s">%s</option>',
                date('Y-m', mktime(0, 0, 0, $month, 1, $year)),
                date('F (Y)', mktime(0, 0, 0, $month, 1, $year))
            );

            if ($month++ < 12) {
                continue;
            }

            $month = 1;
            $year++;
        }

        return implode("\n", $options);
    }

    final public function getHTML24HourOptions(): string {
        $options = [];

        for ($h = 0; $h < 24; $h++) {
            $options[] = sprintf('<option value="%d">%d</option>', $h, $h);
        }

        return implode("\n", $options);
    }

    final public function getHTMLMinuteOptions(): string {
        $minutes = ['00', '15', '30', '45', '59'];
        $options = [];

        foreach ($minutes as $minute) {
            $options[] = sprintf(
                '<option value="%s">%s</option>',
                $minute,
                $minute
            );
        }

        return implode("\n", $options);
    }

    private function _getHTMLCalendaryBody($month, $year): string {
        // ISO 8601 days
        $monday = 1;
        $sunday = 7;

        $day_of_week = $monday;
        $day_of_week_start = (int)date('N', mktime(0, 0, 0, $month, 1, $year));
        $day_of_month = 1;
        $last_day_of_month = (int)date('t', mktime(0, 0, 0, $month, 1, $year));

        $total_cells = ceil($last_day_of_month / 7) * 7;
        $cell_text = '';
        $rows = [];

        for ($c = 1; $c <= $total_cells; $c++) {
            $cell_attribs = '';
            $week_of_year = (int)date('W', mktime(0, 0, 0, $month, $day_of_month, $year));

            if ($day_of_week == $monday) {
                $rows[] = '<tr>';
            }

            if ($day_of_week == $day_of_week_start || $cell_text) {
                $cell_id = sprintf('w%dp%d', $week_of_year, $day_of_week);
                $select_cell_id = sprintf('%s-m%dd%d', $cell_id, $month, $day_of_month);
                $cell_text = '';

                if ($day_of_month <= $last_day_of_month) {
                    $cell_attribs = sprintf(
                        ' id="%s" class="calendar-day" onclick="toggleSingleOrRepeatingDays(\'%s\');"',
                        $cell_id,
                        $select_cell_id
                    );

                    $cell_text = $day_of_month;
                }

                $day_of_month++;
            }

            $rows[] = sprintf('<td%s>%s</td>', $cell_attribs, $cell_text);

            if ($day_of_week++ < $sunday) {
                continue;
            }

            $day_of_week = 1;
            $rows[] = '</tr>';
        }

        return implode("\n", $rows);
    }

    final public function getHTMLCalendar(): string {
        $month = (int)date('n');
        $year = (int)date('Y');

        $calendar = [];

        for ($m = 0; $m < 12; $m++) {
            $id = date('Y-m', mktime(0, 0, 0, $month, 1, $year));
            $month_year = date('F (Y)', mktime(0, 0, 0, $month, 1, $year));
            $display = (!$m) ? 'block' : 'none';
            $body = $this->_getHTMLCalendaryBody($month, $year);

            $calendar[] = <<<HTML
                      <div id="{$id}" style="position: relative; display: {$display};">
                        <table id="calTable{$month}{$year}" class="table table-condensed table-bordered">
                          <thead>
                            <tr>
                              <td colspan="7" style="text-align: center">{$month_year}</td>
                            </tr>
                            <tr>
                              <td class="calendar-header-day" onclick="toggleSingleOrRepeatingDays('w1p1');">
                                {$this->_days[0]}
                              </td>
                              <td class="calendar-header-day" onclick="toggleSingleOrRepeatingDays('w1p2');">
                                {$this->_days[1]}
                              </td>
                              <td class="calendar-header-day" onclick="toggleSingleOrRepeatingDays('w1p3');">
                                {$this->_days[2]}
                              </td>
                              <td class="calendar-header-day" onclick="toggleSingleOrRepeatingDays('w1p4');">
                                {$this->_days[3]}
                              </td>
                              <td class="calendar-header-day" onclick="toggleSingleOrRepeatingDays('w1p5');">
                                {$this->_days[4]}
                              </td>
                              <td class="calendar-header-day" onclick="toggleSingleOrRepeatingDays('w1p6');">
                                {$this->_days[5]}
                              </td>
                              <td class="calendar-header-day" onclick="toggleSingleOrRepeatingDays('w1p7');">
                                {$this->_days[6]}
                              </td>
                            </tr>
                          </thead>
                          <tbody>
                            {$body}
                          </tbody>
                        </table>
                      </div>
HTML;

            if ($month++ < 12) {
                continue;
            }

            $month = 1;
            $year++;
        }

        return implode("\n", $calendar);
    }

    private function _getSelectedDaysNonRepeating(array $time_range): ?object {
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

            // ISO 8601 days
            $day_of_week = (int)date('N', mktime(0, 0, 0, $month, $day, date('Y')));
            $week_of_year = (int)date('W', mktime(0, 0, 0, $month, $day, date('Y')));

            $days_selected[] = sprintf('w%dp%d-m%dd%d', $week_of_year, $day_of_week, $month, $day);

            $day_range_start = $day_range_start ?? $day;
            $month_short = substr($this->_months[$month - 1], 0, 3);
            $next_selected_day = (int)$selected_days[$selection_num + 1];
            $next_selected_month = (int)$selected_months[$selection_num + 1];

            // Continue to the next day when working on a range (i.e. Feb 6-8)
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

    private function _getSelectedDaysRepeating(array $time_range): ?object {
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

            $start_day = $this->_days[$day_range_start - 1];
            $stop_day = $this->_days[$day_of_week - 1];

            if ($day_of_week == $day_range_start) {
                $days_selected_text[] = $start_day;
                $day_range_start = null;
                continue;
            }

            $days_selected_text[] = sprintf('%s - %s', $start_day, $stop_day);
            $day_range_start = null;
        }

        return (object)[
            'days_selected_text' => implode(', ', $days_selected_text),
            'days_selected' => $time_range['position']
        ];
    }

    final public function getJSONTimeRanges(): string {
        if (!isset($this->_data->time_ranges)) {
            return '[]';
        }

        $time_ranges = [];

        foreach ($this->_data->time_ranges as $time_range) {
            if (!$time_range) {
                continue;
            }

            [$start_time, $stop_time] = explode('-', $time_range['hour']);
            $description = rawurldecode($time_range['rangedescr']);

            if ($time_range['month']) {
                $data = $this->_getSelectedDaysNonRepeating($time_range);

                if (!$data) {
                    continue;
                }

                $data->start_time = $start_time;
                $data->stop_time = $stop_time;
                $data->description = $description;
                $time_ranges[] = $data;

                continue;
            }

            $data = $this->_getSelectedDaysRepeating($time_range);

            if (!$data) {
                continue;
            }

            $data->start_time = $start_time;
            $data->stop_time = $stop_time;
            $data->description = $description;
            $time_ranges[] = $data;
        }

        return json_encode($time_ranges);
    }

    final public function getJSONMonths(): string {
        return json_encode($this->_months);
    }

    final public function getJSONDays(): string {
        return json_encode($this->_days);
    }

    private function _validateName(string $name): void {
        if (!preg_match('/^[a-zA-Z0-9_\-]{1,32}$/', $name)) {
            $this->_setError(gettext('The schedule name must be less than 32 characters long and may only consist of the following characters: a-z, A-Z, 0-9, _'));
        }
        if (in_array(strtolower($name), ['lan', 'wan'])) {
            $this->_setError(gettext(sprintf('Schedule may not be named %s.', $name)));
        }
        if (empty($name)) {
            $this->_setError(gettext('Schedule may not use a blank name.'));
        }

        // Check for name conflicts
        //
        // NOTE: $_id will be null when saving a new schedule
        foreach ($this->_config as $config_id => $schedule) {
            if ($schedule['name'] != $name || $config_id == $this->_id) {
                continue;
            }

            $this->_setError(gettext('A Schedule with this name already exists.'));
            break;
        }
    }

    private function _isValidTime(string $time): bool {
        return preg_match('/^[0-9]+:[0-9]+$/', $time);
    }

    private function _validateTimes(string $start_time, string $stop_time): bool {
        $is_error = false;

        if (!$this->_isValidTime($start_time)) {
            $this->_setError(gettext(sprintf('Invalid start time - "%s"', $start_time)));
            $is_error = true;
        }

        if (!$this->_isValidTime($stop_time)) {
            $this->_setError(gettext(sprintf('Invalid stop time - "%s"', $stop_time)));
            $is_error = true;
        }

        return !$is_error;
    }

    private function _sortConfigByName(): void {
        if (empty($this->_config)) {
            return;
        }

        usort($this->_config, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
    }

    final public function save($data): bool {
        $this->_data = (object)$data;

        $this->_id = @$this->_data->id;
        $this->_id = (!isset($this->_config[$this->_id])) ? null : (int)$this->_id;

        $this->_validateName($this->_data->name);

        // Parse time ranges
        $this->_data->time_ranges = [];

        if ($this->_data->days) {
            foreach ($this->_data->days as $range_num => $selected_days) {
                $start_time = $this->_data->start_times[$range_num];
                $stop_time = $this->_data->stop_times[$range_num];

// FIXME: Create a method for the the logic below
                if (!$this->_validateTimes($start_time, $stop_time)) {
                    continue;
                }

                $time_range = [
                    'hour' => sprintf('%s-%s', $start_time, $stop_time),
                    'rangedescr' => rawurlencode($this->_data->range_descriptions[$range_num])
                ];

                // Repeating time ranges
                if (!strstr($selected_days, '-')) {
                    $this->_data->time_ranges[$range_num] = array_merge(
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

                    [$ignore, $month_and_day] = explode('-', $selected_day);

                    [$month, $day] = explode('d', $month_and_day);
                    $months[] = ltrim($month, 'm');
                    $days[] = $day;
                }

                $this->_data->time_ranges[$range_num] = array_merge(
                    $time_range,
                    [
                        'month' => implode(',', $months),
                        'day' => implode(',', $days)
                    ]
                );
            }
        }

        if (!$this->_data->time_ranges) {
            $this->_setError(gettext('The schedule must have at least one time range configured.'));
        }

        if ($this->hasErrors()) {
            return false;
        }

        $this->_id = $this->_id ?? count($this->_config);
        $this->_config[$this->_id] = [
            'name' => $this->_data->name,
            'descr' => $this->_data->description,
            'timerange' => $this->_data->time_ranges
        ];

        $this->_sortConfigByName();
        write_config();
        filter_configure();
        $this->_escapeData();

        return true;
    }
}

$schedule = new Schedule();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $schedule->save($_POST)) {
    $schedule->returnToSchedules();
}

include('head.inc');
?>
<body>
<style>
label {
  display: inline;
}
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

function injectFlashError(error) {
    const main_content = $('.page-content-main > .container-fluid > .row');

    let flash_box = main_content.find('div.col-xs-12');
    flash_box = (!flash_box.length) ? $('<div class="col-xs-12"></div>') : flash_box;
    flash_box.empty();

    const alert_box = $('<div class="alert alert-danger" role="alert"></div>')
    const close_button = $('<button type="button" class="close" data-dismiss="alert"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>');

    alert_box.append(close_button);
    alert_box.append(error);
    flash_box.append(alert_box);
    main_content.prepend(flash_box);

    const _clearBlurTimer = function() {
        if (!flash_box._timer)
            return;

        clearTimeout(flash_box._timer);
        delete flash_box._timer;
    };

    flash_box.attr('tabindex', -1).focus();

    flash_box.blur(function() {
        flash_box.off('blur');
        _clearBlurTimer();

        flash_box._timer = setTimeout(function() {
            _clearBlurTimer();

            if (document.activeElement === flash_box[0])
                return;

            flash_box.empty();
        }, 1000);
    });
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
            // Something really wrong must've happened
            return injectFlashError('Failed to find the correct day to toggle. Please save your schedule and try again.');
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
    const visible_month = month_select.prop('visible_month') || month_select.prop('options')[0].value;
    $(`#${visible_month}`).css('display', 'none');

    const selected_month = month_select.prop('selectedOptions')[0].value;
    month_select.prop('visible_month', selected_month);
    $(`#${selected_month}`).css('display', 'block');
}

function warnBeforeSave() {
    if (!_isSelectedDaysEmpty()) {
        // NOTE: askToAddOrClearTimeRange() will only resolve the promise
        $.when(askToAddOrClearTimeRange($('#range-description').val())).then(function() {
            $('#submit').click();
        });

        // Stops the onSubmit event from propagating
        return false;
    }

    return true;
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

function warnBeforeClearCalender() {
    const def = $.Deferred();

    $(this).blur();

    if (_isSelectedDaysEmpty())
        return def.resolve();

    BootstrapDialog.show({
        'type': BootstrapDialog.TYPE_DANGER,
        'title': '<?= gettext('Clear Selection(s)?') ?>',
        'message': '<div style="margin: 10px;"><?= gettext('Are you sure you want to clear your selection(s)? All unsaved changes will be lost!') ?></div>',

        'buttons': [
            {
                'label': '<?= gettext('Cancel') ?>',
                'action': function(dialog) {
                    dialog.close();
                    def.reject();
                }
            },
            {
                'label': '<?= gettext('Clear Selection(s)') ?>',
                'cssClass': 'btn-danger',
                'action': function(dialog) {
                    clearCalendar(true);
                    dialog.close();
                    def.resolve();
                }
            }
        ]
    });

    return def.promise();
}

function clearCalendar(is_clear_description = false) {
    _clearSelectedDays();

    const month_select = $('#month-select');
    const visible_month = month_select.prop('visible_month') || month_select.prop('options')[0].value;

    $(`#${visible_month}`)
        .parent()
        .find('tbody td[data-state]')
        .filter('[data-state != "white"]')
        .attr('data-state', 'white');

    resetStartAndStopTimes();

    if (is_clear_description)
        clearTimeRangeDescription();
}

function removeTimeRange(is_confirm = false) {
    const _confirmBeforeRemove = function() {
        const def = $.Deferred();

        if (!is_confirm)
            return def.resolve();

        BootstrapDialog.show({
            'type': BootstrapDialog.TYPE_DANGER,
            'title': '<?= gettext('Remove Time Range?') ?>',
            'message': '<div style="margin: 10px;"><?= gettext('Are you sure you want to remove this time range?') ?></div>',

            'buttons': [
                {
                    'label': '<?= gettext('Cancel') ?>',
                    'action': function(dialog) {
                        dialog.close();
                        def.reject();
                    }
                },
                {
                    'label': '<?= gettext('Remove') ?>',
                    'cssClass': 'btn-danger',
                    'action': function(dialog) {
                        dialog.close();
                        def.resolve();
                    }
                }
            ]
        });

        return def.promise();
    };

    const remove_button = $(this);

    remove_button.blur();

    $.when(_confirmBeforeRemove()).then(function() {
        remove_button.closest('tr').remove();
    });

    // NOTE: A "false" value is returned to stop the onClick event from propagating
    return false;
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

function addTimeRange() {
    const _months = <?= $schedule->getJSONMonths() ?>;
    const _days = <?= $schedule->getJSONDays() ?>;
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

    $(this).blur();

    if (start_hour > stop_hour)
        return injectFlashError('Start Hour cannot be greater than Stop Hour.');

    if (start_hour === stop_hour && parseInt(start_minute) > parseInt(stop_minute))
        return injectFlashError('Start Minute cannot be greater than Stop Minute.');

    if (_isSelectedDaysEmpty()) {
        return injectFlashError('You must select at least one day before adding the time range.');
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

const askToAddOrClearTimeRange = function(range_description) {
    const def = $.Deferred();

    BootstrapDialog.show({
        'type': BootstrapDialog.TYPE_PRIMARY,
        'title': '<?= gettext('Modified Time Range In Progress') ?>',
        'message': '<div style="margin: 10px;">'
            + `<strong>Range Description:</strong> ${range_description}`
            + '\n\n'
            + '<?= gettext('What would you like to do with the time range that you have in progress?') ?>'
            + '</div>',

        'buttons': [
            {
                'label': '<?= gettext('Clear Selection');?>',
                'action': function(dialog) {
                    dialog.close();
                    $.when(warnBeforeClearCalender()).then(def.resolve);
                }
            },
            {
                'label': '<?= gettext('Add Time & Continue') ?>',
                'action': function(dialog) {
                    addTimeRange();
                    dialog.close();
                    def.resolve();
                }
            }
        ]
    });

    return def.promise();
};

function editTimeRange(days, start_time, stop_time, range_description) {
    const _doEdit = function() {
        removeTimeRange.bind(this)();
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

        $('.selectpicker').selectpicker('refresh');
    }.bind(this);

    $(this).blur();

    if (!_isSelectedDaysEmpty()) {
        // NOTE: askToAddOrClearTimeRange() will only resolve the promise
        $.when(askToAddOrClearTimeRange($('#range-description').val())).then(_doEdit);

        // Stops the onClick event from propagating
        return false;
    }

    _doEdit();

    // Stops the onClick event from propagating
    return false;
}


$(function() {
    // NOTE: Needed to prevent hook_stacked_form_tables() from breaking the
    // calendar's CSS when selecting days, as well as making the selections
    // disappear when the screen gets resized or the orientation changes
    //
    // @see /www/javascripts/opnsense_legacy.js
    const _onResize = function() {
        $('#iform .table-condensed td').css('background-color', '');
    };

    $(window).resize(_onResize);
    _onResize();

    const time_ranges = <?= $schedule->getJSONTimeRanges() ?>;

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
if ($schedule->hasErrors()) {
    print_input_errors($schedule->getErrors());
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
                    <td>
                      <em class="fa fa-info-circle text-muted"></em>
                      <label for="name"><?= gettext('Name') ?></label>
                    </td>
                    <td>
<?php
$references = $schedule->getHTMLReferences();

if ($references):
?>
                      <?= $schedule->getData('name') ?>
                      <div class="text-danger" style="margin-top: 10px;">
                        <?= gettext('The name may not be modified because this schedule is referenced by the following rules:') ?>
                        <ul style="margin-top: 10px;">
                        <?= $references ?>
                        </ul>
                      </div>
                      <input name="name" type="hidden" value="<?= $schedule->getData('name') ?>" />
<?php else: ?>
                      <input type="text" name="name" id="name" value="<?= $schedule->getData('name') ?>" />
<?php endif ?>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <a id="help_for_description" href="#" class="showhelp"><em class="fa fa-info-circle"></em></a>
                      <label for="description"><?= gettext('Description') ?></label>
                    </td>
                    <td>
                      <input type="text" name="description" id="description" value="<?= $schedule->getData('description') ?>" />
                      <br />
                      <div class="hidden" data-for="help_for_description">
                        <?= gettext('You may enter a description here for your reference (not parsed).') ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <a id="help_for_month" href="#" class="showhelp"><em class="fa fa-info-circle"></em></a>
                      <label for="month-select"><?= gettext('Month') ?></label>
                    </td>
                    <td>
                      <select id="month-select" class="selectpicker"
                              data-width="auto" data-live-search="true"
                              onchange="showSelectedMonth.bind(this)();">
                        <?= $schedule->getHTMLMonthOptions(); ?>
                      </select>
                      <br />
                      <br />

                      <?= $schedule->getHTMLCalendar(); ?>

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
                                <?= $schedule->getHTML24HourOptions() ?>
                              </select>
                              <select id="start-minute" class="selectpicker form-control"
                                      data-width="auto" data-size="5" data-live-search="true">
                                  <?= $schedule->getHTMLMinuteOptions() ?>
                              </select>
                            </div>
                          </td>
                          <td>
                            <div class="input-group">
                              <select id="stop-hour" class="selectpicker form-control"
                                      data-width="auto" data-size="5" data-live-search="true">
                                <?= $schedule->getHTML24HourOptions() ?>
                              </select>
                              <select id="stop-minute" class="selectpicker form-control"
                                      data-width="auto" data-size="5" data-live-search="true">
                                <?= $schedule->getHTMLMinuteOptions() ?>
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
                      <label for="range-description"><?= gettext('Range Description') ?></label>
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
                             onclick="addTimeRange.bind(this)();" />
                      <input type="button" value="<?= html_safe(gettext('Clear Selection(s)')) ?>"
                             class="btn btn-default" style="margin: 0 5px;"
                             onclick="warnBeforeClearCalender.bind(this)();" />
                    </td>
                  </tr>
                  <tr>
                    <th colspan="2"><?= gettext('Schedule Repeat') ?></th>
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
                             onclick="return warnBeforeSave();" />
                      <input type="button" value="<?= html_safe(gettext('Cancel')) ?>"
                             class="btn btn-default" style="margin: 0 5px;"
                             onclick="window.location.href='<?= $schedule->getReturnURL() ?>'" />
                      <?= ($schedule->hasID()) ? sprintf('<input name="id" type="hidden" value="%d" />', $schedule->getID()) : '' ?>
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
