<?php

/*
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
define('I18N_JAN', _('January'));
define('I18N_FEB', _('February'));
define('I18N_MAR', _('March'));
define('I18N_APR', _('April'));
define('I18N_MAY', _('May'));
define('I18N_JUN', _('June'));
define('I18N_JUL', _('July'));
define('I18N_AUG', _('August'));
define('I18N_SEP', _('September'));
define('I18N_OCT', _('October'));
define('I18N_NOV', _('November'));
define('I18N_DEC', _('December'));
define('I18N_MON', _('Mon'));
define('I18N_TUE', _('Tue'));
define('I18N_WED', _('Wed'));
define('I18N_THU', _('Thu'));
define('I18N_FRI', _('Fri'));
define('I18N_SAT', _('Sat'));
define('I18N_SUN', _('Sun'));

class Schedule
{
    public const EDIT_PAGE = 'firewall_schedule_edit.php';

    private static $_i18n_months = [
        I18N_JAN,
        I18N_FEB,
        I18N_MAR,
        I18N_APR,
        I18N_MAY,
        I18N_JUN,
        I18N_JUL,
        I18N_AUG,
        I18N_SEP,
        I18N_OCT,
        I18N_NOV,
        I18N_DEC
    ];

    private static $_i18n_days = [
        I18N_MON,
        I18N_TUE,
        I18N_WED,
        I18N_THU,
        I18N_FRI,
        I18N_SAT,
        I18N_SUN
    ];

    private $_rules;
    private $_id;
    private $_name;
    private $_description;
    private $_time_ranges;
    private $_start_on;
    private $_end_on;
    private $_is_disabled;
    private $_errors = [];

    public function __construct(int $id, array $schedule) {
        $this->_rules = config_read_array('filter', 'rule') ?? [];

        $this->_id = $id;
        $this->_name = $this->_escape($schedule['name'] ?? null);
        $this->_description = $this->_escape($schedule['description'] ?? $schedule['descr'] ?? null);
        $this->_start_on = $this->_escape($schedule['start_on'] ?? null);
        $this->_end_on = $this->_escape($schedule['end_on'] ?? null);
        $this->_is_disabled = (bool)@$schedule['is_disabled'];

        $time_ranges = $schedule['time_ranges'] ?? $schedule['timerange'] ?? [];
        $this->_time_ranges = [];

        foreach ($time_ranges as $time_range) {
            if (!$time_range) {
                continue;
            }

            $time_range = array_merge($time_range, [
                'start_stop_time' => $this->_escape($time_range['start_stop_time'] ?? $time_range['hour'] ?? ''),
                'description' => rawurldecode($this->_escape($time_range['description'] ?? $time_range['rangedescr'] ?? '')),
                'label' => $this->_getSelectedDatesLabel($time_range)
            ]);

            $this->_time_ranges[] = (object)$time_range;
        }
    }

    private function _escape($value) {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML401);
    }

    private function _setError(string $message): void {
        $this->_errors[] = $message;
    }

    final public function getErrors(): array {
        return array_unique($this->_errors);
    }

    private function _getOrdinalDate(string $date): string {
        return date('jS', mktime(1, 1, 1, 1, $date));
    }

    private function _getCustomDatesLabel(array $time_range): string {
        $months = $time_range['months'] ?? $time_range['month'] ?? null;
        $days = $time_range['days'] ?? $time_range['day'] ?? null;

        if (!($months && $days)) {
            return '';
        }

        $selected = (object)[
            'months' => explode(',', $months),
            'days' => explode(',', $days)
        ];
        $day_range_start = null;
        $label = [];

        foreach ($selected->months as $i => $month) {
            $month = (int)$month;
            $day = (int)$selected->days[$i];
            $day_range_start = $day_range_start ?? $day;

            $next_month = (int)$selected->months[$i + 1];
            $next_day = (int)$selected->days[$i + 1];

            if ($month == $next_month && ($day + 1) == $next_day) {
                continue;
            }

            if ($day == $day_range_start) {
                $label[] = sprintf(
                    '%s %s',
                    self::$_i18n_months[$month - 1],
                    $this->_getOrdinalDate($day)
                );

                $day_range_start = null;
                continue;
            }

            $label[] = sprintf(
                '%s %s-%s',
                self::$_i18n_months[$month - 1],
                $this->_getOrdinalDate($day_range_start),
                $this->_getOrdinalDate($day)
            );
            $day_range_start = null;
        }

        return nl2br(implode("\n", $label));
    }

    private function _getRepeatingMonthlyDatesLabel(array $time_range): string {
        $days = $time_range['days'] ?? $time_range['day'] ?? null;

        if (!$days) {
            return '';
        }

        $day_range_start = null;
        $label = ['(Monthly)'];
        $days = explode(',', $days);

        foreach ($days as $i => $day) {
            $day = (int)$day;
            $day_range_start = $day_range_start ?? $day;

            $next_day = (int)$days[$i + 1];

            if (($day + 1) == $next_day) {
                continue;
            }

            if ($day == $day_range_start) {
                $label[] = $this->_getOrdinalDate($day);
                $day_range_start = null;
                continue;
            }

            $label[] = sprintf(
                '%s-%s',
                $this->_getOrdinalDate($day_range_start),
                $this->_getOrdinalDate($day)
            );
            $day_range_start = null;
        }

        return nl2br(implode("\n", $label));
    }

    private function _getRepeatingWeeklyDaysLabel(array $time_range): string {
        $days_of_week = $time_range['days_of_week'] ?? $time_range['position'] ?? null;

        if (!$days_of_week) {
            return '';
        }

        $days_of_week = explode(',', $days_of_week);
        $day_range_start = null;
        $label = ['(Weekly)'];

        foreach ($days_of_week as $i => $day_of_week) {
            $day_of_week = (int)$day_of_week;

            if (!$day_of_week) {
                continue;
            }

            $day_range_start = $day_range_start ?? $day_of_week;
            $next_day = $days_of_week[$i + 1];

            if (($day_of_week + 1) == $next_day) {
                continue;
            }

            $start_day = self::$_i18n_days[$day_range_start - 1];
            $end_day = self::$_i18n_days[$day_of_week - 1];

            if ($day_of_week == $day_range_start) {
                $label[] = $start_day;
                $day_range_start = null;
                continue;
            }

            $label[] = sprintf('%s-%s', $start_day, $end_day);
            $day_range_start = null;
        }

        return nl2br(implode("\n", $label));
    }

    private function _getSelectedDatesLabel(array $time_range): string {
        $months = $time_range['months'] ?? $time_range['month'] ?? null;

        if ($months) {
            return $this->_getCustomDatesLabel($time_range);
        }

        $days = $time_range['days'] ?? $time_range['day'] ?? null;

        if ($days) {
            return $this->_getRepeatingMonthlyDatesLabel($time_range);
        }

        return $this->_getRepeatingWeeklyDaysLabel($time_range);
    }

    private function _getData(): array {
        return [
            'name' => $this->_name,
            'description' => $this->_description,
            'time_ranges' => json_decode(json_encode($this->_time_ranges), true),
            'start_on' => $this->_start_on,
            'end_on' => $this->_end_on,
            'is_disabled' => $this->_is_disabled
        ];
    }

    final public function isDisabled(): bool {
        return !!$this->_is_disabled;
    }

    final public function isPending(): bool {
        return ($this->_start_on && time() < strtotime($this->_start_on));
    }

    final public function isExpired(): bool {
        return ($this->_end_on && time() > strtotime($this->_end_on));
    }

    private function _isRunning(): bool {
        return (!$this->_is_disabled
            && filter_get_time_based_rule_status($this->_getData())
        );
    }

    final public function getReferences(): string {
        if (!($this->_name && $this->_rules)) {
            return 'N/A';
        }

        $references = [];

        foreach ($this->_rules as $rule) {
            if (@$rule['sched'] != $this->_name) {
                continue;
            }

            $references[] = sprintf('<div>%s</div>', trim($rule['descr']));
        }

        return ($references) ? implode("\n", $references) : 'N/A';
    }

    final public function getToogleButtonTooltip(): string {
        $is_expired = $this->isExpired();

        if ($this->isPending() || $is_expired) {
            return ($is_expired) ? _('Schedule has expired') : _('Schedule is pending');
        }

        return (!$this->_is_disabled) ? _('Schedule is enabled') : _('Schedule is disabled');
    }

    final public function getToogleButtonCSS(): string {
        $is_expired = $this->isExpired();

        if ($this->isPending() || $is_expired) {
            return sprintf('fa-times %s', ($is_expired) ? 'text-danger' : 'text-muted');
        }

        return sprintf(
            'action-toggle fa-play %s',
            ($this->_is_disabled) ? 'text-danger' : 'text-success'
        );
    }

    final public function getRunStatusTooltip(): string {
        if ($this->isPending() || $this->isExpired()) {
            return 'N/A';
        }

        return ($this->_isRunning()) ? _('Schedule is currently running') : _('Schedule is currently inactive');
    }

    final public function getRunStatusCSS(): string {
        if ($this->isPending() || $this->isExpired()) {
            return '';
        }

        return ($this->_isRunning()) ? 'text-success' : 'text-muted';
    }

    final public function getID(): string {
        return $this->_id;
    }

    final public function getName(): string {
        return $this->_name ?? '';
    }

    final public function getDescription(): string {
        return $this->_description ?? '';
    }

    final public function getTimeRanges(): array {
        return $this->_time_ranges;
    }

    private function _getFormattedDate(?string $date): string {
        if (!$date) {
            return '';
        }

        return date('m/d/Y', strtotime($date));
    }

    final public function getStartOn($is_format = false): string {
        if (!$this->_start_on) {
            return '';
        }

        return (!$is_format) ? $this->_start_on : $this->_getFormattedDate($this->_start_on);
    }

    final public function getEndOn($is_format = false): string {
        if (!$this->_end_on) {
            return '';
        }

        return (!$is_format) ? $this->_end_on : $this->_getFormattedDate($this->_end_on);
    }

    final public function getEditURL(): string {
        return sprintf('%s?id=%d', self::EDIT_PAGE, $this->_id);
    }

    final public function getCloneURL(): string {
        return sprintf('%s?dup=%d', self::EDIT_PAGE, $this->_id);
    }

    final public function delete(): void {
        $schedule = &config_read_array('schedules', 'schedule');

        foreach ($this->_rules as $rule) {
            if ($rule['sched'] != $schedule[$this->_id]['name']) {
                continue;
            }

            $this->_setError(_(sprintf(
                'Cannot delete Schedule. Currently in use by %s',
                $rule['descr']
            )));

            return;
        }

        unset($schedule[$this->_id]);
        write_config();

        header(url_safe(sprintf('Location: /%s', basename(__FILE__))));
        exit;
    }

    final public function toggle(): void {
        $schedule = &config_read_array('schedules', 'schedule');

        $this->_is_disabled = (string)!$this->_is_disabled;
        $schedule[$this->_id]['is_disabled'] = $this->_is_disabled;
        $result = write_config();

        if (!$result || $result == -1) {
            http_response_code(500);

            $last_error = error_get_last();

            echo json_encode([
                 'status' => 'error',
                 'message' => html_safe($last_error['message'] ?? _('An unknown error occurred'))
            ]);

            exit;
        }

        $this->_is_disabled = (bool)$this->_is_disabled;
        $is_running = $this->_isRunning();

        $toggle_title = _(($this->_is_disabled) ? 'Schedule is disabled' : 'Schedule is enabled');
        $running_title = _(($is_running) ? 'Schedule is currently running' : 'Schedule is currently inactive');

        echo json_encode([
            'status' => 'success',
            'data' => [
                'id' => $this->_id,
                'is_disabled' => $this->_is_disabled,
                'is_running' => $is_running,
                'toggle_title' => html_safe($toggle_title),
                'running_title' => html_safe($running_title)
            ]
        ]);

        exit;
    }
}


$config_schedules = &config_read_array('schedules', 'schedule');
$delete_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Is the ID valid?
    $id = @$_POST['id'];
    $id = (!isset($config_schedules[$id])) ? null : (int)$id;

    if ($id !== null && @$_POST['action']) {
        $schedule = new Schedule($id, $config_schedules[$id]);

        switch ($_POST['action']) {
            case 'del':
                $schedule->delete();

                $delete_error = $schedule->getErrors();
                $delete_error = implode("\n", $delete_error);
                break;

            // XHR
            case 'toggle':
                $schedule->toggle();
                break;
        }
    }
}

$schedules = [];
foreach ($config_schedules as $id => $schedule) {
    $schedules[$id] = new Schedule($id, $schedule);
}

include('head.inc');
?>
<body>
<style>
.action-toggle {
  cursor: pointer;
}
</style>
<script>
$(document).ready(function() {
    $('.action-delete').click(function() {
        const id = $(this).data('id');

        BootstrapDialog.show({
            'type': BootstrapDialog.TYPE_DANGER,
            'title': '<?= html_safe(_('Rules')) ?>',
            'message': '<?= html_safe(_('Do you really want to delete this schedule?')) ?>',

            'buttons': [
                {
                    'label': '<?= html_safe(_('No')) ?>',
                    'action': function(dialog) {
                        dialog.close();
                    }
                },
                {
                    'label': '<?= html_safe(_('Yes')) ?>',
                    'action': function() {
                        $('#id').val(id);
                        $('#action').val('del');
                        $('#iform').submit();
                    }
                }
            ]
        });
    });

    $('.action-toggle').click(function(e){
        e.preventDefault();

        const toggle_button = $(this);
        const spinner = 'fa-spinner fa-pulse';

        toggle_button.addClass(spinner);

        $.ajax('<?= basename(__FILE__) ?>', {
            'type': 'post',
            'cache': false,
            'dataType': 'json',
            'data': {
                'action': 'toggle',
                'id': toggle_button.data('id')
            },

            'success': function(response) {
                const data = response.data;

                toggle_button.prop('title', data.toggle_title).tooltip('fixTitle').tooltip('hide');
                toggle_button.removeClass('text-success text-danger');
                toggle_button.addClass((data.is_disabled) ? 'text-danger' : 'text-success');

                const row = toggle_button.closest('.rule');
                const running_icon = row.find('.fa-clock-o');

                running_icon.prop('title', data.running_title).tooltip('fixTitle').tooltip('hide');
                running_icon.removeClass('text-success text-muted');
                running_icon.addClass((data.is_running) ? 'text-success' : 'text-muted');

                const addOrRemoveClass = (data.is_disabled) ? 'addClass' : 'removeClass';
                row[addOrRemoveClass]('text-muted');

                toggle_button.removeClass(spinner);
            },

            'error': function(response) {
                BootstrapDialog.show({
                    'type': BootstrapDialog.TYPE_DANGER,
                    'title': '<?= html_safe(_('Unknown Error')) ?>',
                    'message': response.responseJSON.message,

                    'buttons': [
                        {
                            'label': '<?= html_safe(_('Close')) ?>',
                            'cssClass': 'btn btn-default',
                            'action': function(dialog) {
                                dialog.close();
                            }
                        }
                    ]
                });

                toggle_button.removeClass(spinner);
            }
        });
  });
});
</script>

<?php include('fbegin.inc'); ?>

  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
<?php
if ($delete_error) {
    print_info_box($delete_error);
}
?>
        <section class="col-xs-12">
          <div class="content-box tab-content">
            <form method="post" name="iform" id="iform">
              <input type="hidden" name="id" id="id" value="" />
              <input type="hidden" name="action" id="action" value="" />

              <table class="table table-striped">
                <thead>
                  <tr>
                    <td colspan="2"></td>
                    <td style="min-width: 100px;"><?= _('Name') ?></td>
                    <td><?= _('Starts') ?></td>
                    <td><?= _('Ends') ?></td>
                    <td><?= _('Time Ranges') ?></td>
                    <td style="min-width: 150px;"><?= _('Description') ?></td>
                    <td class="text-nowrap" style="width: 125px;">
                      <a href="<?= Schedule::EDIT_PAGE ?>"
                         class="btn btn-primary btn-xs"
                         title="<?= html_safe(_('Add')) ?>"
                         data-toggle="tooltip">
                        <em class="fa fa-plus fa-fw"></em>
                      </a>
                    </td>
                  </tr>
                </thead>
                <tbody>
<?php
foreach ($schedules as $schedule):
?>
                  <tr ondblclick="document.location='<?= $schedule->getEditURL() ?>'"
                      class="rule<?= ($schedule->isDisabled()) ? ' text-muted' : '' ?>">
                    <td style="width: 15px;">
                      <span class="fa <?= $schedule->getToogleButtonCSS() ?>"
                            title="<?= html_safe($schedule->getToogleButtonTooltip()) ?>"
                            data-id="<?= $schedule->getID() ?>" data-toggle="tooltip"></span>
                    </td>
                    <td style="width: 15px;">
<?php
    if (!($schedule->isPending() || $schedule->isExpired())) {
?>
                      <span class="fa fa-clock-o <?= $schedule->getRunStatusCSS() ?>"
                            title="<?= html_safe($schedule->getRunStatusTooltip()) ?>"
                            data-toggle="tooltip"></span>
<?php
    }
?>
                    </td>
                    <td>
                      <span title="<div><strong><?= html_safe(_('References:')) ?></strong></div><?= html_safe($schedule->getReferences()) ?>"
                            data-toggle="tooltip" data-html="true">
                        <?= $schedule->getName() ?>
                      </span>
                    </td>
                    <td><?= $schedule->getStartOn(true) ?></td>
                    <td><?= $schedule->getEndOn(true) ?></td>
                    <td>
                      <table class="table table-condensed table-striped">
<?php
    foreach ($schedule->getTimeRanges() as $time_range):
?>
                        <tr>
                          <td style="width: 80px;"><?= $time_range->label ?></td>
                          <td style="width: 80px;"><?= $time_range->start_stop_time ?></td>
                          <td style="width: 150px;"><?= $time_range->description ?></td>
                        </tr>
<?php
    endforeach;
?>
                      </table>
                    </td>
                    <td><?= $schedule->getDescription() ?></td>
                    <td>
                      <a href="<?= $schedule->getEditURL() ?>"
                         class="btn btn-default btn-xs"
                         title="<?= html_safe(_('Edit')) ?>"
                         data-toggle="tooltip">
                        <span class="fa fa-pencil fa-fw"></span>
                      </a>
                      <a href="<?= $schedule->getCloneURL() ?>"
                         class="btn btn-default btn-xs"
                         title="<?= html_safe(_('Clone')) ?>"
                         data-toggle="tooltip">
                        <span class="fa fa-clone fa-fw"></span>
                      </a>
                      <a class="action-delete btn btn-default btn-xs"
                         title="<?= html_safe(_('Delete')) ?>"
                         data-id="<?= $schedule->getID() ?>"
                         data-toggle="tooltip">
                        <span class="fa fa-trash fa-fw text-danger"></span>
                      </a>
                    </td>
                  </tr>
<?php
endforeach;
?>
                </tbody>
              </table>
            </form>
          </div>
        </section>
      </div>
    </div>
  </section>

<?php include('foot.inc'); ?>
