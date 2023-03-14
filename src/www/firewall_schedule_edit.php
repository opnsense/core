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

// Constants for localization that need to be used for static class properties
define('L10N_JAN', _('January'));
define('L10N_FEB', _('February'));
define('L10N_MAR', _('March'));
define('L10N_APR', _('April'));
define('L10N_MAY', _('May'));
define('L10N_JUN', _('June'));
define('L10N_JUL', _('July'));
define('L10N_AUG', _('August'));
define('L10N_SEP', _('September'));
define('L10N_OCT', _('October'));
define('L10N_NOV', _('November'));
define('L10N_DEC', _('December'));
define('L10N_MON', _('Mon'));
define('L10N_TUE', _('Tue'));
define('L10N_WED', _('Wed'));
define('L10N_THU', _('Thu'));
define('L10N_FRI', _('Fri'));
define('L10N_SAT', _('Sat'));
define('L10N_SUN', _('Sun'));

trait ErrorTrait {
    private $_errors = [];

    private function _setError(string $message): void {
        $this->_errors[] = $message;
    }

    final public function hasErrors(): bool {
        return !empty($this->_errors);
    }

    public function getErrors(): array {
        return array_unique($this->_errors);
    }

    private function _mergeErrors(array $errors): void {
        $this->_errors = array_merge($this->_errors, $errors);
    }
}

class TimeRange implements JsonSerializable
{
    use ErrorTrait;

    private static $_i18n_months = [
        L10N_JAN,
        L10N_FEB,
        L10N_MAR,
        L10N_APR,
        L10N_MAY,
        L10N_JUN,
        L10N_JUL,
        L10N_AUG,
        L10N_SEP,
        L10N_OCT,
        L10N_NOV,
        L10N_DEC
    ];

    private static $_i18n_days = [
        L10N_MON,
        L10N_TUE,
        L10N_WED,
        L10N_THU,
        L10N_FRI,
        L10N_SAT,
        L10N_SUN
    ];

    private $_description;
    private $_label;
    private $_days_of_week;
    private $_months;
    private $_days;
    private $_start_time;
    private $_stop_time;

    public function __construct(array $time_range) {
        $this->_initDescription($time_range);
        $this->_initStartAndStop($time_range);
        $this->_initSelectedDates($time_range);
    }

    private function _escape($value) {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML401);
    }

    private function _isValidTime(string $time): bool {
        return preg_match('/^[0-9]+:[0-9]+$/', $time);
    }

    private function _validateTimes(): bool {
        $is_error = false;

        if (!$this->_isValidTime($this->_start_time)) {
            $this->_setError(_(sprintf('Invalid start time: %s', $this->_start_time)));
            $is_error = true;
        }

        if (!$this->_isValidTime($this->_stop_time)) {
            $this->_setError(_(sprintf('Invalid stop time: %s', $this->_stop_time)));
            $is_error = true;
        }

        return !$is_error;
    }

    private function _validateSelectedDates(): bool {
        if ($this->_days_of_week || $this->_months || $this->_days) {
            return true;
        }

        $this->_setError(_('One or more days/dates must be selected before the time range can be added.'));
        return false;
    }

    private function _initDescription(array $time_range): void {
        $description = $time_range['description'] ?? $time_range['rangedescr'] ?? '';
        $this->_description = $this->_escape(rawurldecode($description));
    }

    private function _initStartAndStop(array $time_range): void {
        $this->_start_time = '';
        $this->_stop_time = '';

        if (isset($time_range['start_time']) && isset($time_range['stop_time'])) {
            $this->_start_time = $this->_escape($time_range['start_time']);
            $this->_stop_time = $this->_escape($time_range['stop_time']);
        }

        $start_stop_time = $time_range['start_stop_time'] ?? $time_range['hour'] ?? null;

        if ($start_stop_time) {
            [$start_time, $stop_time] = explode('-', $start_stop_time);
            $this->_start_time = $this->_escape($start_time);
            $this->_stop_time = $this->_escape($stop_time);
        }

        $this->_validateTimes();
    }

    private function _getOrdinalDate($date) {
        return date('jS', mktime(1, 1, 1, 1, $date));
    }

    private function _initRepeatingWeeklyDays(string $selected_days_of_week): void {
        $this->_days_of_week = $this->_escape($selected_days_of_week);

        // ISO 8601 days
        $days_of_week = explode(',', $selected_days_of_week);
        $day_range_start = null;
        $label = [];

        foreach ($days_of_week as $i => $day_of_week) {
            $day_of_week = (int)$day_of_week;

            if (!$day_of_week) {
                continue;
            }

            $day_range_start = $day_range_start ?? $day_of_week;
            $next_selected_day = $days_of_week[$i + 1];

            // Continue to the next day when working on a range (i.e. Mon-Thu)
            if (($day_of_week + 1) == $next_selected_day) {
                continue;
            }

            $start_day = self::$_i18n_days[$day_range_start - 1];
            $stop_day = self::$_i18n_days[$day_of_week - 1];

            // Friendly description for a repeating day
            if ($day_of_week == $day_range_start) {
                $label[] = $start_day;
                $day_range_start = null;
                continue;
            }

            // Friendly description for a range of days
            $label[] = sprintf('%s-%s', $start_day, $stop_day);
            $day_range_start = null;
        }

        $this->_label = sprintf('(Weekly) %s', $this->_escape(implode(', ', $label)));
    }

    private function _initRepeatingMonthlyDates(string $selected_dates): void {
        $date_range_start = null;
        $label = [];
        $dates = explode(',', $selected_dates);

        foreach ($dates as $selection_num => $date) {
            $date = (int)$date;
            $date_range_start = $date_range_start ?? $date;
            $next_selected_date = (int)$dates[$selection_num + 1];

            // Continue to the next day when working on a range (i.e. 6-8)
            if (($date + 1) == $next_selected_date) {
                continue;
            }

            // Friendly description for a repeating date
            if ($date == $date_range_start) {
                $label[] = $this->_getOrdinalDate($date);
                $date_range_start = null;
                continue;
            }

            // Friendly description for a range of dates
            $label[] = sprintf(
                '%s-%s',
                $this->_getOrdinalDate($date_range_start),
                $this->_getOrdinalDate($date)
            );

            $date_range_start = null;
        }

        $this->_days = $this->_escape($selected_dates);
        $this->_label = sprintf('(Monthly) %s', $this->_escape(implode(', ', $label)));
    }

    private function _initCustomDates(
        string $selected_months,
        string $selected_days
    ): void {
        $date_range_start = null;
        $label = [];
        $months = explode(',', $selected_months);
        $days = explode(',', $selected_days);

        foreach ($months as $selection_num => $month) {
            $month = (int)$month;
            $date = (int)$days[$selection_num];

            $date_range_start = $date_range_start ?? $date;
            $month_short = substr(self::$_i18n_months[$month - 1], 0, 3);
            $next_selected_month = (int)$months[$selection_num + 1];
            $next_selected_date = (int)$days[$selection_num + 1];

            // Continue to the next day when working on a range (i.e. Feb 6-8)
            if ($month == $next_selected_month && ($date + 1) == $next_selected_date) {
                continue;
            }

            // Friendly description for custom date
            if ($date == $date_range_start) {
                $label[] = sprintf('%s %s', $month_short, $this->_getOrdinalDate($date));

                $date_range_start = null;
                continue;
            }

            // Friendly description for a range of dates
            $label[] = sprintf(
                '%s %s-%s',
                $month_short,
                $this->_getOrdinalDate($date_range_start),
                $this->_getOrdinalDate($date)
            );

            $date_range_start = null;
        }

        $this->_months = $this->_escape($selected_months);
        $this->_days = $this->_escape($selected_days);
        $this->_label = $this->_escape(implode(', ', $label));
    }

    private function _isRepeatingWeekly(?array $time_range = null): bool {
        if ($time_range) {
            $days_of_week = $time_range['days_of_week'] ?? $time_range['position'] ?? null;

            return !empty($days_of_week);
        }

        return !empty($this->_days_of_week);
    }

    private function _isRepeatingMonthly(?array $time_range = null): bool {
        if ($time_range) {
            $months = $time_range['months'] ?? $time_range['month'] ?? null;
            $days = $time_range['days'] ?? $time_range['day'] ?? null;

            return !empty($days) && empty($months);
        }

        return !empty($this->_days) && empty($this->_months);
    }

    private function _isCustom(?array $time_range = null): bool {
        if ($time_range) {
            $months = $time_range['months'] ?? $time_range['month'] ?? null;
            $days = $time_range['days'] ?? $time_range['day'] ?? null;

            return !empty($months) && !empty($days);
        }

        return !empty($this->_months) && !empty($this->_days);
    }

    private function _initSelectedDates(array $time_range): void {
        if ($this->_isRepeatingWeekly($time_range)) {
            $days_of_week = $time_range['days_of_week'] ?? $time_range['position'] ?? '';

            $this->_initRepeatingWeeklyDays($days_of_week);
        }

        if ($this->_isRepeatingMonthly($time_range)) {
            $days = $time_range['days'] ?? $time_range['day'] ?? null;

            $this->_initRepeatingMonthlyDates($days);
        }

        if ($this->_isCustom($time_range)) {
            $months = $time_range['months'] ?? $time_range['month'] ?? null;
            $days = $time_range['days'] ?? $time_range['day'] ?? null;

            $this->_initCustomDates($months, $days);
        }

        $this->_validateSelectedDates();
    }

    final public static function getJSONMonths(): string {
        return json_encode(self::$_i18n_months);
    }

    final public static function getJSONDays(): string {
        return json_encode(self::$_i18n_days);
    }

    final public static function getDays(): array {
        return self::$_i18n_days;
    }

    final public function getDataForSave(): ?array {
        if (!($this->_validateTimes() && $this->_validateSelectedDates())) {
            return null;
        }

        $time_range = [
            'start_stop_time' => sprintf('%s-%s', $this->_start_time, $this->_stop_time),
            'description' => rawurlencode($this->_description)
        ];

        if ($this->_isRepeatingWeekly()) {
            $time_range['days_of_week'] = $this->_days_of_week;
            return $time_range;
        }

        if ($this->_isRepeatingMonthly()) {
            return array_merge($time_range, [
                'months' => '',
                'days' => $this->_days
            ]);
        }

        if (!$this->_isCustom()) {
            $this->_setError(_('Malformed custom date selection'));
            return null;
        }

        return array_merge($time_range, [
            'months' => $this->_months,
            'days' => $this->_days
        ]);
    }

    final public function jsonSerialize() {
        return [
            'description' => $this->_description,
            'selections' => [
                'days_of_week' => $this->_days_of_week ?? '',
                'months' => $this->_months ?? '',
                'days' => $this->_days ?? ''
            ],
            'label' => $this->_label,
            'start_time' => $this->_start_time,
            'stop_time' => $this->_stop_time
        ];
    }
}

class Schedule
{
    use ErrorTrait;

    private const RETURN_URL = 'firewall_schedule.php';

    private $_rules;
    private $_config;
    private $_id;
    private $_name;
    private $_description;
    private $_time_ranges;
    private $_start_on;
    private $_end_on;
    private $_is_disabled;

    public function __construct() {
        $this->_rules = config_read_array('filter', 'rule') ?? [];
        $this->_config = &config_read_array('schedules', 'schedule');

        // Add button clicked
        if (empty($_GET)) {
            $this->_init([]);
            return;
        }

        // Calendar button clicked on the Rules screen
        if (isset($_GET['name'])) {
            foreach ($this->_config as $id => $schedule) {
                if ($schedule['name'] != $_GET['name']) {
                    continue;
                }

                $this->_id = $id;
                $this->_init($schedule);
                return ;
            }

            // Invalid request
            $this->returnToSchedules();
        }

        // Clone button clicked
        if (isset($_GET['dup'])) {
            $this->_id = (int)$_GET['dup'];
            $schedule = @$this->_config[$this->_id];

            // Only the time range is needed when cloning because users should
            // enter a new name and/or description
            if ($schedule) {
                $time_ranges = $schedule['time_ranges'] ?? $schedule['timerange'] ?? [];
                $schedule = ['time_ranges' => $time_ranges];
            }

            // NOTE: Schedule is being cloned; so $_id MUST NOT be set
            $this->_id = null;

            $this->_init($schedule);
            return;
        }

        // Edit button clicked
        if (isset($_GET['id'])) {
            $this->_id = (int)$_GET['id'];
            $this->_init(@$this->_config[$this->_id]);
            return;
        }

        // Invalid request
        $this->returnToSchedules();
    }

    final public function returnToSchedules(): void {
        header(url_safe(sprintf('Location: /%s', self::RETURN_URL)));
        exit;
    }

    final public function getReturnURL(): string {
        return self::RETURN_URL;
    }

    private function _escape($value) {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML401);
    }

    private function _init(?array $data): void {
        // Invalid request
        if ($data === null) {
            $this->returnToSchedules();
        }

        $this->_name = $this->_escape($data['name'] ?? null);
        $this->_description = $this->_escape($data['description'] ?? $data['descr'] ?? null);
        $this->_start_on = $this->_escape($data['start_on'] ?? null);
        $this->_end_on = $this->_escape($data['end_on'] ?? null);
        $this->_is_disabled = (bool)$data['is_disabled'];

        $time_ranges = $data['time_ranges'] ?? $data['timerange'] ?? [];
        $this->_time_ranges = [];

        foreach ($time_ranges as $time_range) {
            $time_range = new TimeRange($time_range);

            if ($time_range->hasErrors()) {
                $this->_mergeErrors($time_range->getErrors());
                continue;
            }

            $this->_time_ranges[] = $time_range;
        }
    }

    final public function getName(): ?string {
        return $this->_name;
    }

    final public function getDescription(): ?string {
        return $this->_description;
    }

    final public function getStartOn(bool $is_javascript_format = false): ?string {
        if (!$is_javascript_format)
            return $this->_start_on;

// TODO: Add support for locale
        return date('m/d/Y', strtotime($this->_start_on));
    }

    final public function hasStartOn(): bool {
        return !!$this->_start_on;
    }

    final public function getEndOn(bool $is_javascript_format = false): ?string {
        if (!$is_javascript_format)
            return $this->_end_on;

// TODO: Add support for locale
        return date('m/d/Y', strtotime($this->_end_on));
    }

    final public function hasEndOn(): bool {
        return !!$this->_end_on;
    }

    final public function isDisabled(): bool {
        return !!$this->_is_disabled;
    }

    final public function hasID(): bool {
        return ($this->_id !== null);
    }

    final public function getID(): ?int {
        return $this->_id;
    }

    final public function getHTMLReferences(): string {
        if (!($this->_name && $this->_rules)) {
            return '';
        }

        $references = [];

        foreach ($this->_rules as $rule) {
            if (@$rule['sched'] != $this->_name) {
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

    final public function getHTMLDaysOfWeekButtons(): string {
        $days = TimeRange::getDays();
        $buttons = [];

        foreach ($days as $index => $day) {
            $day_of_week = $index + 1;

            $input = sprintf('<input type="checkbox" autocomplete="off" /> %s', $day);

            $buttons[] = sprintf(
                '<label id="day-of-week-%d" class="btn btn-default" onclick="toggleRepeatingWeeklyDay(%d)">%s</label>',
                $day_of_week,
                $day_of_week,
                $input
            );
        }

        return sprintf(
            '<div id="days-of-week" class="btn-group" data-toggle="buttons">%s</div>',
            implode("\n", $buttons)
        );
    }

    final public function getHTMLDaysOfMonthButtons(): string {
        // ISO 8601 days
        $monday = 1;
        $sunday = 7;
        $last_day = 31;

        $day_of_week = $monday;
        $groups = [];

        for ($day = 1; $day <= $last_day; $day++) {
            if ($day_of_week == $monday) {
                $groups[] = '<div class="btn-group" data-toggle="buttons">';
            }

            $input = sprintf('<input type="checkbox" autocomplete="off" /> %d', $day);

            $groups[] = sprintf(
                '<label id="day-of-month-%d" class="btn btn-default" onclick="toggleRepeatingMonthlyDay(%d)">%s</label>',
                $day,
                $day,
                $input
            );

            $day_of_week = ($day % $sunday) + 1;

            if ($day_of_week == $monday || $day == $last_day) {
                $groups[] = '</div>';
            }
        }

        return sprintf('<div id="days-of-month">%s</div>', implode("\n", $groups));
    }

    private function _getHTMLCalendarHeaders(): string {
        $days = TimeRange::getDays();
        $headers = [];

        foreach ($days as $index => $day_of_week) {
            $headers[] = sprintf(
                '<td class="calendar-header-day" onclick="toggleRepeatingWeeklyDay(%d)">%s</td>',
                $index + 1,
                $day_of_week
            );
        }

        return implode("\n", $headers);
    }

    private function _getHTMLCalendarBody($month, $year): string {
        // ISO 8601 days
        $monday = 1;
        $sunday = 7;

        $day_of_week = $monday;
        $day_of_week_start = (int)date('N', mktime(0, 0, 0, $month, 1, $year));
        $day_of_month = 1;
        $last_day_of_month = (int)date('t', mktime(0, 0, 0, $month, 1, $year));

        $total_cells = ceil($last_day_of_month / $sunday) * $sunday;
        $cell_text = '';
        $rows = [];

        for ($cell_num = 1; $cell_num <= $total_cells; $cell_num++) {
            $cell_attribs = '';

            if ($day_of_week == $monday) {
                $rows[] = '<tr>';
            }

            if ($day_of_week == $day_of_week_start || $cell_text) {
                $cell_text = '';

                if ($day_of_month <= $last_day_of_month) {
                    $cell_attribs = sprintf(
                        ' id="%s" class="calendar-day dow%d" onclick="toggleCustomDate.bind(this)()" data-month="%d" data-day="%d" data-state="white"',
                        sprintf('m%dd%d', $month, $day_of_month),
                        $day_of_week,
                        $month,
                        $day_of_month
                    );

                    $cell_text = $day_of_month;
                }

                $day_of_month++;
            }

            $rows[] = sprintf('<td%s>%s</td>', $cell_attribs, $cell_text);

            $day_of_week = ($cell_num % $sunday) + 1;

            if ($day_of_week == $monday) {
                $rows[] = '</tr>';
            }
        }

        return implode("\n", $rows);
    }

    final public function getHTMLCalendar(): string {
        $month = (int)date('n');
        $year = (int)date('Y');

        $calendar = [];

        for ($m = 0; $m < 12; $m++) {
            $id = date('Y-m', mktime(0, 0, 0, $month, 1, $year));
            $display = (!$m) ? 'block' : 'none';

            $headers = $this->_getHTMLCalendarHeaders();
            $body = $this->_getHTMLCalendarBody($month, $year);

            $calendar[] = <<<HTML
                      <div id="{$id}" style="position: relative; display: {$display};">
                        <table class="table table-condensed table-bordered">
                          <thead>
                            <tr>
                              {$headers}
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

    final public function getJSONTimeRanges(): string {
        if (!isset($this->_time_ranges)) {
            return '[]';
        }

        return json_encode($this->_time_ranges);
    }

    private function _validateName(): void {
        if (!preg_match('/^[a-zA-Z0-9_\-]{1,32}$/', $this->_name)) {
            $this->_setError(_('Schedule name cannot exceed 32 characters and must only contain the following: a-z, A-Z, 0-9, _'));
        }
        if (in_array(strtolower($this->_name), ['lan', 'wan'])) {
            $this->_setError(_(sprintf('Schedule cannot be named %s.', $this->_name)));
        }
        if (empty($this->_name)) {
            $this->_setError(_('Schedule name is required.'));
        }

        // Check for name conflicts
        //
        // NOTE: $_id will be null when saving a new schedule
        foreach ($this->_config as $config_id => $schedule) {
            if ($schedule['name'] != $this->_name || $config_id == $this->_id) {
                continue;
            }

            $this->_setError(_('A schedule with this name already exists.'));
            break;
        }
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
        $unsaved_time_ranges = $this->_time_ranges;

        $data = (object)$data;
        $this->_id = (!isset($this->_config[$this->_id])) ? null : (int)$this->_id;

        $this->_validateName();

        // Parse time ranges
        $data->time_ranges = [];

        // NOTE: selected_days_of_week, selected_months, and selected_days will
        // have the same number of entries
        if ($data->selected_days_of_week) {
            foreach ($data->selected_days_of_week as $range_num => $days) {
                $time_range = new TimeRange([
                    'start_time' => $data->start_times[$range_num],
                    'stop_time' => $data->stop_times[$range_num],
                    'description' => $data->range_descriptions[$range_num],
                    'days_of_week' => $days,
                    'months' => $data->selected_months[$range_num],
                    'days' => $data->selected_days[$range_num]
                ]);

                if ($time_range->hasErrors()) {
                    $this->_mergeErrors($time_range->getErrors());
                    continue;
                }

                $_time_range = $time_range->getDataForSave();

                if ($time_range->hasErrors()) {
                    $this->_mergeErrors($time_range->getErrors());
                    continue;
                }

                $data->time_ranges[] = $_time_range;
            }
        }

        unset($data->selected_days_of_week);
        unset($data->selected_months);
        unset($data->selected_days);

        if (!$data->time_ranges) {
            $this->_setError(_('The schedule must have at least one time range configured.'));
        }

        if ($this->hasErrors()) {
            $data->time_ranges = $unsaved_time_ranges;
            return false;
        }

        $this->_id = $this->_id ?? count($this->_config);
        $this->_config[$this->_id] = [
            'name' => $data->name,
            'description' => $data->description,
            'time_ranges' => $data->time_ranges,
            'start_on' => ($data->start_on) ? date('Y-m-d', strtotime($data->start_on)) : null,
            'end_on' => ($data->end_on) ? date('Y-m-d', strtotime($data->end_on)) : null,
            'is_disabled' => (string)(@$data->is_disabled == 'yes')
        ];

        $this->_sortConfigByName();
        write_config();
        filter_configure();

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
button.btn {
  margin-right: 5px;
}
.btn-group {
  width: 100%;
  margin: 3px 0;
}
.btn-group > label.btn {
  width: 14.3% !important;
}
.input-group > .dropdown.form-control > select.dropdown-hour + button.dropdown-toggle {
  border-top-left-radius: 3px;
  border-bottom-left-radius: 3px;
}
.input-group > .dropdown.form-control > select.dropdown-minute + button.dropdown-toggle {
  border-top-right-radius: 3px;
  border-bottom-right-radius: 3px;
}
.calendar-nav {
  text-align: center;
  border-bottom-width: 0 !important;
  padding: 3px;
}
.calendar-nav-button {
  text-align: center;
  line-height: 34px;
  width: 60%;
  visibility: hidden;
  cursor: pointer;
}
.calendar-header-day {
  text-decoration: underline;
  text-align: center;
  cursor: pointer;
  border-bottom-width: 0 !important;
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
.start-stop-row {
  clear: both;
  height: 37px;
  line-height: 37px;
}
.start-stop-col-left {
  float: left;
  width: 20px;
}
.start-stop-col-right {
  float: left;
  width: 180px;
}
.start-stop-col-right input {
  float: right;
  margin-top: -7px;
  width: 94px;
}
</style>
<script>
//<![CDATA[
function _initTimeRangeSelections() {
    $('#iform')[0]._time_ranges = {
        'weekly': {},
        'monthly': {},
        'custom': {}
    };
}

function _addTimeRangeSelection(type, value) {
    $('#iform')[0]._time_ranges[type][value] = 1;
}

function _removeTimeRangeSelection(type, value) {
    delete $('#iform')[0]._time_ranges[type][value];
}

function _addTimeRangeSelectionCustom(month, day) {
    month = String(month).padStart(2, '0');
    day = String(day).padStart(2, '0');

    _addTimeRangeSelection('custom', `${month}|${day}`);
}

function _removeTimeRangeSelectionCustom(month, day) {
    month = String(month).padStart(2, '0');
    day = String(day).padStart(2, '0');

    _removeTimeRangeSelection('custom', `${month}|${day}`);
}

function _getTimeRangeSelections(is_sort = false) {
    const iform = $('#iform')[0];

    const _getNonEmpty = function(value) {
        return !!String(value).trim();
    };

    let selections = {
        'weekly': Object.keys(iform._time_ranges.weekly || {}).filter(_getNonEmpty),
        'monthly': Object.keys(iform._time_ranges.monthly || {}).filter(_getNonEmpty),
        'custom': Object.keys(iform._time_ranges.custom || {}).filter(_getNonEmpty)
    };

    if (!is_sort)
        return selections;

    selections.weekly.sort();
    selections.monthly.sort();
    selections.custom.sort();

    return selections;
}

function _isTimeRangeSelectionEmpty(selections = null) {
    selections = selections || _getTimeRangeSelections();

    return !(selections.weekly.length + selections.monthly.length + selections.custom.length);
}

function _getFlattenedTimeRangeSelect(selections) {
    let data = {
        'days_of_week': '',
        'months': '',
        'days': ''
    };

    if (_isTimeRangeSelectionEmpty(selections))
        return data;

    if (selections.weekly.length) {
        data.days_of_week = selections.weekly.join(',');
        return data;
    }

    if (selections.monthly.length) {
        data.days = selections.monthly.join(',');
        return data;
    }

    // Assume the rest are custom selections
    data.months = [];
    data.days = [];

    selections.custom.forEach(function(month_and_day) {
        let month, day;

        [month, day] = month_and_day.split('|');

        data.months.push(month);
        data.days.push(day);
    });

    data.months = data.months.join(',');
    data.days = data.days.join(',');

    return data;
}

function _clearTimeRangeSelection() {
    // Use an object instead of an array to leverage hash-sieving for faster
    // lookups and to prevent duplicates
    const iform = $('#iform')[0];

    iform._time_ranges.weekly = {};
    iform._time_ranges.monthly = {};
    iform._time_ranges.custom = {};
}

function injectFlashError(error) {
    const main_content = $('.page-content-main > .container-fluid > .row');

    let flash_box = main_content.find('div.col-xs-12');
    flash_box = (!flash_box.length) ? $('<div class="col-xs-12"></div>') : flash_box;
    flash_box.empty();

    const alert_box = $('<div class="alert alert-danger" role="alert"></div>');
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

function _selectMonth(calendar_month_id) {
    if (!(calendar_month_id && $(`#${calendar_month_id}`).length))
        return;

    const month_select = $('#month-select');

    month_select.val(calendar_month_id);
    month_select.selectpicker('refresh');

    showSelectedMonth.bind(month_select[0])();
}

function toggleCustomDate(is_select_month = false) {
    const day_cell = $(this);

    if (!day_cell.length)
        return injectFlashError('Failed to find the correct day to toggle. Please save your schedule and try again.');

    const month = day_cell.data('month');
    const day = day_cell.data('day');

    // ISO 8601 day
    const day_of_week = day_cell.attr('class').slice(-1);
    const day_cells = $(`.dow${day_of_week}`);

    if (day_cell.attr('data-state') !== 'white') {
        day_cell.attr('data-state', 'white');
        _removeTimeRangeSelectionCustom(month, day);

        // Ensure that the repeating day gets removed...
        _removeTimeRangeSelection('weekly', day_of_week);

        // ... then each custom date gets selected when needed
        return day_cells.filter('[data-state = "lightcoral"]').each(function(i, cell) {
            cell = $(cell);
            cell.attr('data-state', 'red');

            _addTimeRangeSelectionCustom(cell.data('month'), cell.data('day'));
        });
    }

    day_cell.attr('data-state', 'red');
    _addTimeRangeSelectionCustom(month, day);

    // When manually selecting custom dates, ensure that each individual day is
    // replaced with the day of the week instead so that the data will get saved
    // simply as a repeating day
    if (day_cells.length === day_cells.filter('[data-state != "white"]').length) {
        day_cells.attr('data-state', 'lightcoral');

        day_cells.filter('[data-state = "lightcoral"]').each(function(i, cell) {
            cell = $(cell);

            _removeTimeRangeSelectionCustom(cell.data('month'), cell.data('day'));
        });

        _addTimeRangeSelection('weekly', day_of_week);
    }

    if (!is_select_month)
        return;

    // Ensure that the month for the first highlighted cell comes into view
    _selectMonth(day_cell.closest('table').parent().prop('id'));
}

// ISO 8601 day
function toggleRepeatingWeeklyDay(day_of_week) {
    const day_cells = $(`.dow${day_of_week}`);

    if (!day_cells.length)
        return injectFlashError('Failed to find the correct days to toggle. Please save your schedule and try again.');

    const selected_days = day_cells.filter('[data-state != "white"]');

    if (day_cells.length === selected_days.length) {
        day_cells.attr('data-state', 'white');
        return _removeTimeRangeSelection('weekly', day_of_week);
    }

    // Ensure that all custom dates get removed
    selected_days.each(function(i, cell) {
        cell = $(cell);

        _removeTimeRangeSelectionCustom(cell.data('month'), cell.data('day'));
    });

    day_cells.attr('data-state', 'lightcoral');
    _addTimeRangeSelection('weekly', day_of_week);
}

function toggleRepeatingMonthlyDay(day) {
    for (let month = 1; month <= 12; month++) {
        let day_button = $(`#m${month}d${day}`);

        if (!day_button.length)
            continue;

        if (day_button.attr('data-state') !== 'white') {
            day_button.attr('data-state', 'white');
            _removeTimeRangeSelection('monthly', day);
            continue;
        }

        day_button.attr('data-state', 'red');
        _addTimeRangeSelection('monthly', day);
    }
}

function _activateButton(button) {
    $(button).addClass('active');
}

function _deactivateButton(button) {
    $(button).removeClass('active').blur();
}

function _clearCalendar(is_clear_description = false) {
    _clearTimeRangeSelection();

    const month_select = $('#month-select');
    const visible_month = month_select.prop('visible_month') || month_select.prop('options')[0].value;

    $(`#${visible_month}`)
        .parent()
        .find('tbody td[data-state]')
        .filter('[data-state != "white"]')
        .attr('data-state', 'white');

    $('#start-hour').val('0');
    $('#start-minute').val('00');
    $('#stop-hour').val('23');
    $('#stop-minute').val('59');
    $('.selectpicker.form-control').selectpicker('refresh');

    if (is_clear_description)
        $('#range-description').val('');
}

function _clearEntryModeInputs(is_clear_description = false) {
    _clearCalendar(is_clear_description);

    $('#days-of-week label.btn').each(function(index, button) {
        _deactivateButton(button);
    });

    $('#days-of-month label.btn').each(function(index, button) {
        _deactivateButton(button);
    });
}

function warnBeforeClearCalender() {
    const def = $.Deferred();

    $(this).blur();

    if (_isTimeRangeSelectionEmpty())
        return def.resolve();

    BootstrapDialog.show({
        'type': BootstrapDialog.TYPE_DANGER,
        'title': '<?= _('Clear Selections?') ?>',
        'message': '<div style="margin: 10px;"><?= _('Are you sure you want to clear your selection(s)? All unsaved changes will be lost!') ?></div>',

        'buttons': [
            {
                'label': '<?= _('Cancel') ?>',
                'cssClass': 'btn btn-default',
                'action': function(dialog) {
                    dialog.close();
                    def.reject();
                }
            },
            {
                'label': '<?= _('Clear') ?>',
                'cssClass': 'btn btn-danger',
                'action': function(dialog) {
                    _clearEntryModeInputs();
                    dialog.close();
                    def.resolve();
                }
            }
        ]
    });

    return def.promise();
}

function _hideTimeRangeInputRows() {
    _deactivateButton('#mode-repeat-weekly-button');
    _deactivateButton('#mode-repeat-monthly-button');
    _deactivateButton('#mode-custom-button');

    $('#range-repeat-weekly-row').hide();
    $('#range-repeat-monthly-row').hide();
    $('#range-custom-row').hide();
}

function resetTimeRangeInputRows() {
    _hideTimeRangeInputRows();

    $('#range-time-row').hide();
    $('#range-description-row').hide();
    $('#range-buttons-row').hide();

    _clearEntryModeInputs(true);
}

function removeTimeRange(is_confirm = false) {
    const _confirmBeforeRemove = function() {
        const def = $.Deferred();

        if (!is_confirm)
            return def.resolve();

        BootstrapDialog.show({
            'type': BootstrapDialog.TYPE_DANGER,
            'title': '<?= _('Remove Time Range?') ?>',
            'message': '<div style="margin: 10px;"><?= _('Are you sure you want to remove this time range?') ?></div>',

            'buttons': [
                {
                    'label': '<?= _('Cancel') ?>',
                    'cssClass': 'btn btn-default',
                    'action': function(dialog) {
                        dialog.close();
                        def.reject();
                    }
                },
                {
                    'label': '<?= _('Remove') ?>',
                    'cssClass': 'btn btn-danger',
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

    // Stops the onClick event from propagating
    return false;
}

function _injectTimeRange(
    label,
    selections,
    start_time,
    stop_time,
    description,
    is_clear_calendar = true
) {
    const body = $('#calendar tbody');
    const edit_click = `return editTimeRange.bind(this)('${start_time}', '${stop_time}', '${description}')`;
    const delete_click = `return removeTimeRange.bind(this)(true)`;

    const edit_cell = $('<td></td>');
    edit_cell.append(`<a href="#" class="btn btn-default" onclick="${edit_click}"><span class="fa fa-pencil fa-fw"></span></a>`);
    edit_cell.append(`<input type="hidden" name="selected_days_of_week[]" value="${selections.days_of_week}" />`);
    edit_cell.append(`<input type="hidden" name="selected_months[]" value="${selections.months}" />`);
    edit_cell.append(`<input type="hidden" name="selected_days[]" value="${selections.days}" />`);

    const row = $('<tr></tr>');
    row.append(`<td><span>${label}</span></td>`);
    row.append(`<td><input type="text" name="start_times[]" value="${start_time}" class="time-range-configured" readonly="readonly" /></td>`);
    row.append(`<td><input type="text" name="stop_times[]" value="${stop_time}" class="time-range-configured" readonly="readonly" /></td>`);
    row.append(`<td><input type="text" name="range_descriptions[]" value="${description}" class="time-range-configured" readonly="readonly" /></td>`);
    row.append(edit_cell);
    row.append(`<td><a href="#" class="btn btn-default" onclick="${delete_click}"><span class="fa fa-trash fa-fw"></span></a></td>`);

    body.append(row);

    if (!is_clear_calendar)
        return;

    resetTimeRangeInputRows();
}

function _getOrdinalDate(date) {
    if (date > 3 && date < 21)
        return `${date}th`;

    switch (date % 10) {
        case 1:
            return `${date}st`;
        case 2:
            return `${date}nd`;
        case 3:
            return `${date}rd`;
    }

    return `${date}th`;
}

function _injectRepeatingWeeklyDays(
    selections,
    start_time,
    stop_time,
    description
) {
    const _days = <?= TimeRange::getJSONDays() ?>;
    let day_range_start = null;
    let label = [];

    // Prepare the friendly label to make it easier to read on the list of
    // "Configured Time Ranges"
    selections.weekly.forEach(function(day_of_week, i) {
        day_of_week = parseInt(day_of_week);

        if (!(day_of_week && !isNaN(day_of_week)))
            return;

        let next_selected_day = parseInt(selections.weekly[i + 1]);

        day_range_start = (!day_range_start) ? day_of_week : day_range_start;

        // Continue to the next day when working on a range (i.e. Mon-Wed)
        if ((day_of_week + 1) === next_selected_day)
            return;

        let start_day = _days[day_range_start - 1];
        let end_day = _days[day_of_week - 1];

        if (day_of_week === day_range_start) {
            label.push(start_day);
            day_range_start = null;
            return;
        }

        label.push(`${start_day}-${end_day}`);
        day_range_start = null;
    });

    label = label.join(', ');
    label = `(<?= _('Weekly') ?>) ${label}`;

    selections = _getFlattenedTimeRangeSelect(selections);

    _injectTimeRange(label, selections, start_time, stop_time, description);
}

function _injectRepeatingMonthlyDates(
    selections,
    start_time,
    stop_time,
    description
) {
    let date_range_start = null;
    let label = [];

    // Prepare the friendly label to make it easier to read on the list of
    // "Configured Time Ranges"
    selections.monthly.forEach(function(day, i) {
        let date = parseInt(day);

        if (!(date && !isNaN(date)))
            return;

        date_range_start = (!date_range_start) ? date : date_range_start;

        const next_date = parseInt(selections.monthly[i + 1]);

        // Continue to the next day when working on a range (i.e. 6-8)
        if ((date + 1) === next_date)
            return;

        if (date === date_range_start) {
            label.push(_getOrdinalDate(date));
            date_range_start = null;
            return;
        }

        label.push(`${_getOrdinalDate(date_range_start)}-${_getOrdinalDate(date)}`);
        date_range_start = null;
    });

    label = label.join(', ');
    label = `(<?= _('Monthly') ?>) ${label}`;

    selections = _getFlattenedTimeRangeSelect(selections);

    _injectTimeRange(label, selections, start_time, stop_time, description);
}

function _injectCustomDates(
    selections,
    start_time,
    stop_time,
    description
) {
    const _months = <?= TimeRange::getJSONMonths() ?>;
    let date_range_start = null;
    let label = [];

    // Prepare the friendly label to make it easier to read on the list of
    // "Configured Time Ranges"
    selections.custom.forEach(function(month_and_day, i) {
        let month, day, next_selected_month, next_selected_day;

        [month, day] = month_and_day.split('|');

        month = parseInt(month);
        day = parseInt(day);

        if (!(month && !isNaN(month)))
            return;

        date_range_start = (!date_range_start) ? day : date_range_start;

        [next_selected_month, next_selected_day] = (selections.custom[i + 1] || '').split('|');

        next_selected_month = parseInt(next_selected_month);
        next_selected_day = parseInt(next_selected_day);

        // Continue to the next day when working on a range (i.e. Feb 6-8)
        if (month === next_selected_month && (day + 1) === next_selected_day)
            return;

        const month_short = _months[month - 1].slice(0, 3);

        if (day === date_range_start) {
            label.push(`${month_short} ${_getOrdinalDate(day)}`);
            date_range_start = null;
            return;
        }

        label.push(`${month_short} ${_getOrdinalDate(date_range_start)}-${_getOrdinalDate(day)}`);
        date_range_start = null;
    });

    selections = _getFlattenedTimeRangeSelect(selections);

    _injectTimeRange(label.join(', '), selections, start_time, stop_time, description);
}

function addTimeRange() {
    const start_hour = parseInt($('#start-hour').val());
    const start_minute = $('#start-minute').val();
    const stop_hour = parseInt($('#stop-hour').val());
    const stop_minute = $('#stop-minute').val();
    const start_time = `${start_hour}:${start_minute}`;
    const stop_time = `${stop_hour}:${stop_minute}`;
    const description = $('#range-description').val();

    if (_isTimeRangeSelectionEmpty())
        return injectFlashError('One or more days/dates must be selected before the time range can be added.');

    if (start_hour > stop_hour
        || (start_hour === stop_hour && parseInt(start_minute) > parseInt(stop_minute))
    ) {
        return injectFlashError('Start Time must not be ahead of the Stop Time.');
    }

    if (_isTimeRangeSelectionEmpty())
        return;

    let selections = _getTimeRangeSelections(true);

    if (selections.weekly.length)
        return _injectRepeatingWeeklyDays(selections, start_time, stop_time, description);

    if (selections.monthly.length)
        return _injectRepeatingMonthlyDates(selections, start_time, stop_time, description);

    if (selections.custom.length)
        return _injectCustomDates(selections, start_time, stop_time, description);

    injectFlashError('Unable to add time range. Please check your selections and try again.');
}

function _askToAddOrClearTimeRange(range_description) {
    const def = $.Deferred();

    BootstrapDialog.show({
        'type': BootstrapDialog.TYPE_PRIMARY,
        'title': '<?= _('Modified Time Range In Progress') ?>',
        'message': '<div style="margin: 10px;">'
            + `<strong>Range Description:</strong> ${range_description || '<em>N/A</em>'}`
            + '\n\n'
            + '<?= _('What would you like to do with the time range that you have in progress?') ?>'
            + '</div>',

        'buttons': [
            {
                'label': '<?= _('Cancel') ?>',
                'cssClass': 'btn btn-default',
                'action': function(dialog) {
                    dialog.close();
                }
            },
            {
                'label': '<?= _('Clear') ?>',
                'cssClass': 'btn btn-danger',
                'action': function(dialog) {
                    dialog.close();
                    $.when(warnBeforeClearCalender()).then(def.resolve);
                }
            },
            {
                'label': '<?= _('Add & Continue') ?>',
                'cssClass': 'btn btn-primary',
                'action': function(dialog) {
                    addTimeRange();
                    dialog.close();
                    def.resolve();
                }
            }
        ]
    });

    return def.promise();
}

function _showTimeRangeInputRows() {
    _clearEntryModeInputs();

    $('#range-time-row').show();
    $('#range-description-row').show();
    $('#range-buttons-row').show();
}

function showRepeatWeeklyRow() {
    const _doShow = function() {
        _hideTimeRangeInputRows();

        _activateButton('#mode-repeat-weekly-button');
        $('#range-repeat-weekly-row').show();

        _showTimeRangeInputRows();
    };

    if (!_isTimeRangeSelectionEmpty()) {
        // NOTE: askToAddOrClearTimeRange() will only resolve the promise
        $.when(_askToAddOrClearTimeRange($('#range-description').val())).then(_doShow);

        // Stops the onClick event from propagating
        return false;
    }

    _doShow();

    // Stops the onClick event from propagating
    return false;
}

function showRepeatMonthlyRow() {
    const _doShow = function() {
        _hideTimeRangeInputRows();

        _activateButton('#mode-repeat-monthly-button');
        $('#range-repeat-monthly-row').show();

        _showTimeRangeInputRows();
    };

    if (!_isTimeRangeSelectionEmpty()) {
        // NOTE: askToAddOrClearTimeRange() will only resolve the promise
        $.when(_askToAddOrClearTimeRange($('#range-description').val())).then(_doShow);

        // Stops the onClick event from propagating
        return false;
    }

    _doShow();

    // Stops the onClick event from propagating
    return false;
}

function showCustomRow() {
    const _doShow = function() {
        _hideTimeRangeInputRows();

        _activateButton('#mode-custom-button');
        $('#range-custom-row').show();

        _showTimeRangeInputRows();
    };

    if (!_isTimeRangeSelectionEmpty()) {
        // NOTE: askToAddOrClearTimeRange() will only resolve the promise
        $.when(_askToAddOrClearTimeRange($('#range-description').val())).then(_doShow);

        // Stops the onClick event from propagating
        return false;
    }

    _doShow();

    // Stops the onClick event from propagating
    return false;
}

function editTimeRange(start_time, stop_time, range_description) {
    let selected_days_of_week, selected_months, selected_days;

    // NOTE: Selections are stored in the hidden inputs
    [selected_days_of_week, selected_months, selected_days] = $(this).siblings();

    selected_days_of_week = $(selected_days_of_week).val().trim();
    selected_days_of_week = (selected_days_of_week) ? selected_days_of_week.split(',') : [];

    selected_months = $(selected_months).val().trim();
    selected_months = (selected_months) ? selected_months.split(',') : [];

    selected_days = $(selected_days).val().trim();
    selected_days = (selected_days) ? selected_days.split(',') : [];

    const _refreshSelectPickers = function() {
        $('.selectpicker').selectpicker('refresh');
    };

    const _toggleMode = function() {
        if (selected_months.length)
            return showCustomRow();

        if (selected_days.length) {
            showRepeatMonthlyRow();

            return selected_days.forEach(function(date) {
                _activateButton(`#day-of-month-${date}`);
            });
        }

        showRepeatWeeklyRow();

        selected_days_of_week.forEach(function(day) {
            _activateButton(`#day-of-week-${day}`);
        });
    };

    const _doEdit = function() {
        removeTimeRange.bind(this)();
        _clearEntryModeInputs();
        _toggleMode();

        let start_hour, start_min, stop_hour, stop_min;
        [start_hour, start_min] = start_time.split(':');
        [stop_hour, stop_min] = stop_time.split(':');

        $('#start-hour').val(start_hour);
        $('#start-minute').val(start_min);
        $('#stop-hour').val(stop_hour);
        $('#stop-minute').val(stop_min);
        $('#range-description').val(range_description);

        if (selected_days.length) {
            if (selected_months.length) {
                let is_select_month = true;

                selected_months.forEach(function(month, index) {
                    let day_cell = $(`#m${month}d${selected_days[index]}`);

                    if (!day_cell.length)
                        return injectFlashError('Failed to find the correct day to toggle. Please save your schedule and try again.');

                    toggleCustomDate.bind(day_cell)(is_select_month);
                    is_select_month = false;
                });

                return _refreshSelectPickers();
            }

            selected_days.forEach(toggleRepeatingMonthlyDay);
        }

        if (selected_days_of_week.length)
            selected_days_of_week.forEach(toggleRepeatingWeeklyDay);

        _refreshSelectPickers();
    }.bind(this);

    $(this).blur();

    if (!_isTimeRangeSelectionEmpty()) {
        // NOTE: askToAddOrClearTimeRange() will only resolve the promise
        $.when(_askToAddOrClearTimeRange($('#range-description').val())).then(_doEdit);

        // Stops the onClick event from propagating
        return false;
    }

    _doEdit();

    // Stops the onClick event from propagating
    return false;
}

function _initCalendar() {
    const month_select = $('#month-select');
    const month_select_options = month_select.prop('options');
    const total_options = month_select_options.length;
    const month_left = $('#month-left');
    const month_right = $('#month-right');

    const _toggleLeftRightVisibility = function(selected_index) {
        month_left.css(
            'visibility',
            (selected_index <= 0) ? 'hidden' : 'visible'
        );
        month_right.css(
            'visibility',
            ((selected_index + 1) >= total_options) ? 'hidden' : 'visible'
        );
    };

    month_select.change(function() {
        _toggleLeftRightVisibility($(this).prop('selectedIndex'));
    });

    month_left.click(function() {
        let index = month_select.prop('selectedIndex');

        if (index > 0) {
            index--;
            _selectMonth((month_select_options[index] || {}).value);
        }

        _toggleLeftRightVisibility(index);
    });

    month_right.click(function() {
        let index = month_select.prop('selectedIndex');

        if (index < total_options) {
            index++;

            _selectMonth((month_select_options[index] || {}).value);
        }

        _toggleLeftRightVisibility(index);
    });

    _clearCalendar(true);
    _toggleLeftRightVisibility(0);
}

function _initStartEndDates() {
    const _getFollowingDate = function(date) {
        const following_day = new Date(date);

        following_day.setDate(following_day.getDate() + 1);

        return following_day;
    };

    const toggle_start_today = $('#toggle-start-today');
    const toggle_start_on = $('#toggle-start-on');
    const toggle_end_never = $('#toggle-end-never');
    const toggle_end_on = $('#toggle-end-on');
    const start_on = $('#start-on');
    const end_on = $('#end-on');
    const today = new Date();

    const end_date = new Date();
    end_date.setFullYear(end_date.getFullYear() + 5);
    end_date.setDate(end_date.getDate() - 1);

    let options = {
        'autoclose': true,
        'showOnFocus': false,
        'startDate': today,
        'endDate': end_date,
        'weekStart': 1
    };

    start_on.datepicker(options);
    start_on.datepicker('setDate', today);
    start_on.on('changeDate', function(e) {
        const following_date = _getFollowingDate(e.date);

        end_on.datepicker('setStartDate', following_date);

        if (following_date <= end_on.datepicker('getDate'))
            return;

        end_on.datepicker('clearDates');
    });
    start_on.on('mousedown', function() {
        $(this).blur();

        if (toggle_start_today.prop('checked'))
            return false;

        $(this).datepicker('show');
    });

    options.startDate = _getFollowingDate(today);
    options.endDate = _getFollowingDate(end_date);
    end_on.datepicker(options);
    end_on.on('mousedown', function() {
        $(this).blur();

        if (toggle_end_never.prop('checked'))
            return false;

        $(this).datepicker('show');
    });

    toggle_start_today.on('click', function() {
        toggle_start_on.prop('checked', false);
        start_on.datepicker('setDate', today);
        start_on.prop('readonly', true).addClass('disabled');
    });

    toggle_start_on.on('click', function() {
        toggle_start_today.prop('checked', false);
        start_on.prop('readonly', false).removeClass('disabled');
    });

    toggle_end_never.on('click', function() {
        toggle_end_on.prop('checked', false);
        end_on.datepicker('clearDates');
        end_on.prop('readonly', true).addClass('disabled');
    });

    toggle_end_on.on('click', function() {
        toggle_end_never.prop('checked', false);
        end_on.prop('readonly', false).removeClass('disabled');
    });
}

function warnBeforeSave() {
    if (!_isTimeRangeSelectionEmpty()) {
        // NOTE: askToAddOrClearTimeRange() will only resolve the promise
        $.when(_askToAddOrClearTimeRange($('#range-description').val())).then(function() {
            $('#submit').click();
        });

        // Stops the onSubmit event from propagating
        return false;
    }

    return true;
}


$(function() {
    // NOTE: Needed to prevent hook_stacked_form_tables() from breaking the
    // calendar's CSS when selecting dates, as well as making the selections
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
        _injectTimeRange(
            time_range.label,
            time_range.selections,
            time_range.start_time,
            time_range.stop_time,
            time_range.description,
            false
        );
    });

    _initTimeRangeSelections();
    resetTimeRangeInputRows();
    _initCalendar();
    _initStartEndDates();
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
          <form method="post" name="iform" id="iform">
            <div class="content-box tab-content __mb">
              <table class="table table-striped opnsense_standard_table_form">
                <tbody>
                  <tr>
                    <td style="width: 15%">
                      <strong><?= _('Schedule Information') ?></strong>
                    </td>
                    <td style="width: 85%; text-align: right">
                      <small><?= _('full help') ?></small>
                      <em id="show_all_help_page" class="fa fa-toggle-off text-danger"></em>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <a id="help_for_disabled" href="#" class="showhelp"><em class="fa fa-info-circle"></em></a>
                      <label for="is_disabled"><?= _('Disabled') ?></label>
                    </td>
                    <td>
                      <input type="checkbox" name="is_disabled" id="is_disabled" value="yes"
                             <?= ($schedule->isDisabled()) ? 'checked="checked"' : '' ?> />
                      <?= _('Disable this schedule') ?>
                      <div class="hidden" data-for="help_for_disabled">
                        <br />
                        <?= _('Set this option to disable this schedule without removing it from the list.') ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td>
<?php $references = $schedule->getHTMLReferences(); ?>
                      <?= ($references) ? '<em class="fa fa-info-circle text-muted"></em>' : '' ?>
                      <label for="name"><?= _('Name') ?></label>
                    </td>
                    <td>
<?php if ($references): ?>
                      <?= $schedule->getName() ?>
                      <div class="text-warning" style="margin-top: 10px;">
                        <?= _('The name cannot be modified because this schedule is referenced by the following rules:') ?>
                        <ul style="margin-top: 10px;">
                        <?= $references ?>
                        </ul>
                      </div>
                      <input type="hidden" name="name" value="<?= $schedule->getName() ?>" />
<?php else: ?>
                      <input type="text" name="name" id="name" value="<?= $schedule->getName() ?>" />
<?php endif ?>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <a id="help_for_description" href="#" class="showhelp"><em class="fa fa-info-circle"></em></a>
                      <label for="description"><?= _('Description') ?></label>
                    </td>
                    <td>
                      <input type="text" name="description" id="description" value="<?= $schedule->getDescription() ?>" />
                      <div class="hidden" data-for="help_for_description">
                        <br />
                        <?= _('You may enter a description here for your reference (not parsed).') ?>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="content-box tab-content __mb">
              <table class="table table-striped opnsense_standard_table_form">
                <tbody>
                  <tr>
                    <th colspan="2"><?= _('Configured Time Ranges') ?></th>
                  </tr>
                  <tr>
                    <td></td>
                    <td>
                      <table id="calendar">
                        <tbody>
                          <tr>
                            <td style="width: 35%;"><?= _('Day or Dates') ?></td>
                            <td style="width: 12%;"><?= _('Start Time') ?></td>
                            <td style="width: 11%;"><?= _('Stop Time') ?></td>
                            <td style="width: 42%;"><?= _('Description') ?></td>
                          </tr>
                        </tbody>
                      </table>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="content-box tab-content __mb">
              <table class="table table-striped opnsense_standard_table_form">
                <tbody>
                  <tr>
                    <th colspan="2"><?= _('Edit Time Range') ?></th>
                  </tr>
                  <tr>
                    <td style="width: 150px;">
                      <a id="help_for_entry_mode" href="#" class="showhelp"><em class="fa fa-info-circle"></em></a>
                      <label><?= _('Entry Mode') ?></label>
                    </td>
                    <td>
                      <button id="mode-repeat-weekly-button" type="button"
                              class="btn btn-default"
                              onclick="return showRepeatWeeklyRow()">
                        <?= html_safe(_('Repeat Weekly')) ?>
                      </button>
                      <button id="mode-repeat-monthly-button" type="button"
                              class="btn btn-default"
                              onclick="return showRepeatMonthlyRow()">
                        <?= html_safe(_('Repeat Monthly')) ?>
                      </button>
                      <button id="mode-custom-button" type="button"
                              class="btn btn-default"
                              onclick="return showCustomRow()">
                        <?= html_safe(_('Custom')) ?>
                      </button>

                      <div class="hidden" data-for="help_for_entry_mode">
                        <br />
                        <?= _('Use "Repeat Weekly" or "Repeat Monthly" to configure a time range for repeating days/dates or "Custom" to choose dates on a calendar.') ?>
                      </div>
                    </td>
                  </tr>

                  <tr id="range-repeat-weekly-row">
                    <td>
                      <a id="help_for_repeat_weekly" href="#" class="showhelp"><em class="fa fa-info-circle"></em></a>
                      <?= _('Repeat Weekly') ?>
                    </td>
                    <td>
                      <?= $schedule->getHTMLDaysOfWeekButtons() ?>

                      <div class="hidden" data-for="help_for_repeat_weekly">
                        <br />
                        <?= _('Select the days of the week when the time range will be active.') ?>
                      </div>

                      <?php
                      // Hack to make sure the striping continues for the table
                      // rows below because the tables above appear to disrupt
                      // the striping for the rows that follow
                      ?>
                      <table style="visibility: hidden;">
                        <tr><td></td></tr>
                      </table>
                    </td>
                  </tr>

                  <tr id="range-repeat-monthly-row">
                    <td>
                      <a id="help_for_repeat_monthly" href="#" class="showhelp"><em class="fa fa-info-circle"></em></a>
                      <?= _('Repeat Monthly') ?>
                    </td>
                    <td>
                      <?= $schedule->getHTMLDaysOfMonthButtons() ?>

                      <div class="hidden" data-for="help_for_repeat_monthly">
                        <br />
                        <?= _('Select the days of the month when the time range will be active.') ?>
                      </div>

                      <?php
                      // Hack to make sure the striping continues for the table
                      // rows below because the tables above appear to disrupt
                      // the striping for the rows that follow
                      ?>
                      <table style="visibility: hidden;">
                        <tr><td></td></tr>
                      </table>
                    </td>
                  </tr>

                  <tr id="range-custom-row">
                    <td>
                      <a id="help_for_specific_dates" href="#" class="showhelp"><em class="fa fa-info-circle"></em></a>
                      <?= _('Custom') ?>
                    </td>
                    <td>
                      <table class="table table-condensed table-bordered" style="border-top-width: 2px;">
                        <thead>
                          <tr>
                            <td class="calendar-nav" style="border-right-width: 0;">
                              <div id="month-left" class="calendar-nav-button">
                                <span class="fa fa-chevron-left"></span>
                              </div>
                            </td>
                            <td colspan="5" class="calendar-nav" style="border: 0;">
                              <select id="month-select" class="selectpicker"
                                      data-width="auto" data-live-search="true"
                                      onchange="showSelectedMonth.bind(this)();">
                                  <?= $schedule->getHTMLMonthOptions(); ?>
                              </select>
                            </td>
                            <td class="calendar-nav" style="border-left-width: 0;">
                              <div id="month-right" class="calendar-nav-button" style="float: right;">
                                <span class="fa fa-chevron-right"></span>
                              </div>
                            </td>
                          </tr>
                        </thead>
                      </table>
                      <?= $schedule->getHTMLCalendar(); ?>

                      <?php
                      // Hack to make sure the striping continues for the table
                      // rows below because the tables above appear to disrupt
                      // the striping for the rows that follow
                      ?>
                      <table style="visibility: hidden;">
                        <tr><td></td></tr>
                      </table>

                      <div class="hidden" data-for="help_for_specific_dates">
                        <br />
                        <?= _('Click an individual date to select that date only. Click the appropriate day header to select all occurrences of that day.') ?>
                      </div>
                    </td>
                  </tr>

                  <tr id="range-time-row">
                    <td>
                      <a id="help_for_time" href="#" class="showhelp"><em class="fa fa-info-circle"></em></a>
                      <?= _('Time') ?>
                    </td>
                    <td>
                      <table>
                        <tr>
                          <td><?= _('Start Time') ?></td>
                          <td><?= _('Stop Time') ?></td>
                        </tr>
                        <tr>
                          <td>
                            <div class="input-group">
                              <select id="start-hour" class="selectpicker form-control dropdown-hour"
                                      data-width="auto" data-size="5" data-live-search="true">
                                <?= $schedule->getHTML24HourOptions() ?>
                              </select>
                              <select id="start-minute" class="selectpicker form-control dropdown-minute"
                                      data-width="auto" data-size="5" data-live-search="true">
                                  <?= $schedule->getHTMLMinuteOptions() ?>
                              </select>
                            </div>
                          </td>
                          <td>
                            <div class="input-group">
                              <select id="stop-hour" class="selectpicker form-control dropdown-hour"
                                      data-width="auto" data-size="5" data-live-search="true">
                                <?= $schedule->getHTML24HourOptions() ?>
                              </select>
                              <select id="stop-minute" class="selectpicker form-control dropdown-minute"
                                      data-width="auto" data-size="5" data-live-search="true">
                                <?= $schedule->getHTMLMinuteOptions() ?>
                              </select>
                            </div>
                          </td>
                        </tr>
                      </table>

                      <div class="hidden" data-for="help_for_time">
                        <br />
                        <?= _('Select the start and stop times. A full day is 0:00-23:59.') ?>
                      </div>
                    </td>
                  </tr>

                  <tr id="range-description-row">
                    <td>
                      <a id="help_for_timerange_desc" href="#" class="showhelp"><em class="fa fa-info-circle"></em></a>
                      <label for="range-description"><?= _('Description') ?></label>
                    </td>
                    <td>
                      <input type="text" id="range-description" />
                      <div class="hidden" data-for="help_for_timerange_desc">
                        <br />
                        <?= _('You may enter a description here for your reference (not parsed).') ?>
                      </div>
                    </td>
                  </tr>

                  <tr id="range-buttons-row">
                    <td></td>
                    <td>
                      <button type="button" class="btn btn-default"
                              onclick="resetTimeRangeInputRows()">
                        <?= html_safe(_('Cancel')) ?>
                      </button>
                      <button type="button" class="btn btn-danger"
                              onclick="warnBeforeClearCalender.bind(this)()">
                        <?= html_safe(_('Clear')) ?>
                      </button>
                      <button type="button" class="btn btn-primary"
                              onclick="addTimeRange.bind(this)()">
                          <?= html_safe(_('Add')) ?>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="content-box tab-content __mb">
              <table class="table table-striped opnsense_standard_table_form">
                <tbody>
                  <tr>
                    <th colspan="2"><?= _('Start &amp; End Date') ?></th>
                  </tr>
                  <tr>
                    <td style="width: 15%">
                      <a id="help_for_start_date" href="#" class="showhelp"><em class="fa fa-info-circle"></em></a>
                      <label><?= _('Starts') ?></label>
                    </td>
                    <td style="width: 85%">
                      <div class="start-stop-row">
                        <div class="start-stop-col-left">
                          <input type="radio" id="toggle-start-today"<?= (!$schedule->hasStartOn()) ? ' checked="checked"' : '' ?>>
                        </div>
                        <div class="start-stop-col-right" style="line-height: 32px;">
                          <label for="toggle-start-today">Today</label>
                        </div>
                      </div>
                      <div style="start-stop-row">
                        <div class="start-stop-col-left">
                          <input type="radio" id="toggle-start-on"<?= ($schedule->hasStartOn()) ? ' checked="checked"' : '' ?>>
                        </div>
                        <div class="start-stop-col-right">
                          <label for="toggle-start-on">On</label>
                          <input type="text" id="start-on" name="start_on"
                                 value="<?= $schedule->getStartOn(true) ?>"
                                 class="<?= (!$schedule->hasStartOn()) ? 'disabled' : '' ?>"
                                 data-provide="datepicker" />
                        </div>
                      </div>
                      <div class="hidden" style="clear: both;" data-for="help_for_start_date">
                        <br />
                        <?= _('Make the entire schedule effective immediately or on the specified date.') ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td style="width: 15%">
                      <a id="help_for_end_date" href="#" class="showhelp"><em
                                class="fa fa-info-circle"></em></a>
                      <label><?= _('Ends') ?></label>
                    </td>
                    <td style="width: 85%">
                      <div class="start-stop-row">
                        <div class="start-stop-col-left">
                          <input type="radio" id="toggle-end-never"<?= (!$schedule->hasEndOn()) ? ' checked="checked"' : '' ?>>
                        </div>
                        <div class="start-stop-col-right" style="line-height: 32px;">
                          <label for="toggle-end-never">Never</label>
                        </div>
                      </div>
                      <div style="start-stop-row">
                        <div class="start-stop-col-left">
                          <input type="radio" id="toggle-end-on"<?= ($schedule->hasEndOn()) ? ' checked="checked"' : '' ?>>
                        </div>
                        <div class="start-stop-col-right">
                          <label for="toggle-end-on">On</label>
                          <input type="text" id="end-on" name="end_on"
                                 value="<?= $schedule->getEndOn(true) ?>"
                                 class="<?= (!$schedule->hasEndOn()) ? 'disabled' : '' ?>"
                                 data-provide="datepicker" />
                        </div>
                      </div>
                      <div class="hidden" style="clear: both;" data-for="help_for_end_date">
                        <br />
                        <?= _('Make the entire schedule persistent or expire immediately on the specified date.') ?>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="content-box tab-content __mb">
              <table class="table table-striped opnsense_standard_table_form">
                <tbody>
                  <tr>
                    <td>
                      <?= ($schedule->hasID()) ? sprintf('<input type="hidden" name="id" value="%d" />', $schedule->getID()) : '' ?>

                      <div style="float: right;">
                        <button type="button" class="btn btn-default"
                                onclick="window.location.href='<?= $schedule->getReturnURL() ?>'">
                          <?= html_safe(_('Cancel')) ?>
                        </button>
                        <button type="submit" name="submit" id="submit"
                                class="btn btn-primary"
                                onclick="return warnBeforeSave()">
                          <?= html_safe(_('Save')) ?>
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

          </form>
        </section>
      </div>
    </div>
  </section>

<?php include('foot.inc'); ?>
