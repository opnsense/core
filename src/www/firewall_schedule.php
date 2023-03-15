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

$edit_page = 'firewall_schedule_edit.php';

$i18n_days = [
    _('Mon'),
    _('Tue'),
    _('Wed'),
    _('Thu'),
    _('Fri'),
    _('Sat'),
    _('Sun')
];

$i18n_months = [
    _('Jan'),
    _('Feb'),
    _('Mar'),
    _('Apr'),
    _('May'),
    _('Jun'),
    _('Jul'),
    _('Aug'),
    _('Sep'),
    _('Oct'),
    _('Nov'),
    _('Dec')
];

function _getOrdinalDate(string $date): string {
    return date('jS', mktime(1, 1, 1, 1, $date));
}

function getFormattedDate(?string $date): string {
    if (!$date) {
        return '';
    }

    return date('m/d/Y', strtotime($date));
}

function _getCustomDatesLabel(array $time_range): string {
    global $i18n_months;

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
            $label[] = sprintf('%s %s', $i18n_months[$month - 1], _getOrdinalDate($day));

            $day_range_start = null;
            continue;
        }

        $label[] = sprintf(
            '%s %s-%s',
            $i18n_months[$month - 1],
            _getOrdinalDate($day_range_start),
            _getOrdinalDate($day)
        );
        $day_range_start = null;
    }

    return nl2br(implode("\n", $label));
}

function _getRepeatingMonthlyDatesLabel(array $time_range): string {
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
            $label[] = _getOrdinalDate($day);
            $day_range_start = null;
            continue;
        }

        $label[] = sprintf('%s-%s',
                                        _getOrdinalDate($day_range_start),
                                        _getOrdinalDate($day)
        );
        $day_range_start = null;
    }

    return nl2br(implode("\n", $label));
}

function _getRepeatingWeeklyDaysLabel(array $time_range): string {
    global $i18n_days;

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

        $start_day = $i18n_days[$day_range_start - 1];
        $end_day = $i18n_days[$day_of_week - 1];

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

function getSelectedDatesLabel(array $time_range): string {
    $months = $time_range['months'] ?? $time_range['month'] ?? null;

    if ($months) {
        return _getCustomDatesLabel($time_range);
    }

    $days = $time_range['days'] ?? $time_range['day'] ?? null;

    if ($days) {
        return _getRepeatingMonthlyDatesLabel($time_range);
    }

    return _getRepeatingWeeklyDaysLabel($time_range);
}

function getReferences(array $schedule): string {
    global $config_rules;

    if (!($schedule['name'] && $config_rules)) {
        return 'N/A';
    }

    $references = [];

    foreach ($config_rules as $rule) {
        if (@$rule['sched'] != $schedule['name']) {
            continue;
        }

        $references[] = sprintf('<div>%s</div>', trim($rule['descr']));
    }

    return ($references) ? implode("\n", $references) : 'N/A';
}

function isPending(?string $start_on): bool {
    return ($start_on && time() < strtotime($start_on));
}

function isExpired(?string $end_on): bool {
    return ($end_on && time() > strtotime($end_on));
}

function _delete(int $id) {
    global $config_schedules, $config;

    // Make sure rule is not being referenced by filter rule
    $rules = $config['filter']['rule'] ?? [];

    foreach ($rules as $rule) {
        if ($rule['sched'] != $config_schedules[$id]['name']) {
            continue;
        }

        return _(sprintf(
            'Cannot delete Schedule. Currently in use by %s',
            $rule['descr']
        ));
    }

    unset($config_schedules[$id]);
    write_config();

    header(url_safe(sprintf('Location: /%s', basename(__FILE__))));
    exit;
}

function _toggle(int $id) {
    global $config_schedules;

    $is_disabled = (string)!$config_schedules[$id]['is_disabled'];
    $config_schedules[$id]['is_disabled'] = $is_disabled;
    $result = write_config();

    if (!$result || $result == -1) {
        http_response_code(500);

        $last_error = error_get_last();

        echo json_encode([
             'status' => 'error',
             'message' => $last_error['message'] ?? 'An unknown error occurred'
        ]);

        exit;
    }

    $is_running = (!$is_disabled && filter_get_time_based_rule_status($config_schedules[$id]));

    echo json_encode([
        'status' => 'success',
        'data' => [
            'id' => $id,
            'is_disabled' => (bool)$is_disabled,
            'is_running' => $is_running,
            'toggle_title' => _(($is_disabled) ? 'Schedule is disabled' : 'Schedule is enabled'),
            'running_title' => _(($is_running) ? 'Schedule is currently running' : 'Schedule is currently inactive')
        ]
    ]);

    exit;
}

$config_schedules = &config_read_array('schedules', 'schedule');
$config_rules = config_read_array('filter', 'rule') ?? [];
$delete_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Is the ID valid?
    $id = @$_POST['id'];
    $id = (!isset($config_schedules[$id])) ? null : (int)$id;

    if ($id !== null && @$_POST['action']) {
        switch ($_POST['action']) {
            case 'del':
                $delete_error = _delete($id);
                break;

            // XHR
            case 'toggle':
                _toggle($id);
                break;
        }
    }
}

include('head.inc');

legacy_html_escape_form_data($config_schedules);
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
            'title': '<?= _('Rules') ?>',
            'message': '<?= _('Do you really want to delete this schedule?') ?>',

            'buttons': [
                {
                    'label': '<?= _('No');?>',
                    'action': function(dialog) {
                        dialog.close();
                    }
                },
                {
                    'label': '<?= _('Yes') ?>',
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
                    'title': '<?= _('Unknown Error') ?>',
                    'message': response.responseJSON.message,

                    'buttons': [
                        {
                            'label': '<?= _('Close') ?>',
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
                    <td class="text-nowrap" style="width: 120px;">
                      <a href="<?= $edit_page ?>" title="<?= html_safe(_('Add')) ?>"
                         class="btn btn-primary btn-xs" data-toggle="tooltip">
                        <em class="fa fa-plus fa-fw"></em>
                      </a>
                    </td>
                  </tr>
                </thead>
                <tbody>
<?php
foreach ($config_schedules as $i => $schedule):
    $references = getReferences($schedule);
    $is_disabled = @$schedule['is_disabled'];
    $is_running = (!$is_disabled && filter_get_time_based_rule_status($schedule));
    $is_pending = isPending(@$schedule['start_on']);
    $is_expired = isExpired(@$schedule['end_on']);
    $title = (!$is_disabled) ? _('Schedule is enabled') : _('Schedule is disabled');
    $css = (!($is_pending || $is_expired)) ? 'action-toggle fa-play' : 'fa-times';
    $css .= ($is_disabled) ? ' text-danger' : ' text-success';

    if ($is_pending || $is_expired) {
        $title = ($is_expired) ? _('Schedule has expired') : _('Schedule is pending');
        $css .= (($is_expired) ? ' text-danger' : ' text-muted');
    }
?>
                  <tr ondblclick="document.location='<?= $edit_page ?>?id=<?= $i ?>'"
                      class="rule<?= ($is_disabled) ? ' text-muted' : '' ?>">
                    <td style="width: 15px;">
                      <span title="<?= $title ?>" class="fa <?= $css ?>"
                            data-id="<?= $i ?>" data-toggle="tooltip"></span>
                    </td>
                    <td style="width: 15px;">
<?php
    if (!($is_pending || $is_expired)) {
        $title = ($is_running) ? _('Schedule is currently running') : _('Schedule is currently inactive');
        $css = ($is_running) ? 'text-success' : 'text-muted';
?>
                      <span title="<?= $title ?>" class="fa fa-clock-o <?= $css ?>"
                            data-toggle="tooltip"></span>
<?php
    }
?>
                    </td>
                    <td>
                      <span title="<div><strong><?= _('References:') ?></strong></div><?= $references ?>"
                            data-toggle="tooltip" data-html="true">
                        <?= $schedule['name'] ?>
                      </span>
                    </td>
                    <td><?= getFormattedDate($schedule['start_on']) ?></td>
                    <td><?= getFormattedDate($schedule['end_on']) ?></td>
                    <td>
                      <table class="table table-condensed table-striped">
<?php
    $time_ranges = $schedule['time_ranges'] ?? $schedule['timerange'] ?? [];

    foreach ($time_ranges as $time_range):
        if (!$time_range) {
            continue;
        }

        $start_stop_time = $time_range['start_stop_time'] ?? $time_range['hour'] ?? '';
        $description = $time_range['description'] ?? $time_range['rangedescr'] ?? '';
?>
                        <tr>
                          <td style="width: 80px;"><?= getSelectedDatesLabel($time_range) ?></td>
                          <td style="width: 80px;"><?= $start_stop_time ?></td>
                          <td style="width: 150px;"><?= rawurldecode($description) ?></td>
                        </tr>
<?php
    endforeach;
?>
                      </table>
                    </td>
                    <td><?= $schedule['description'] ?? $schedule['descr'] ?? '' ?></td>
                    <td>
                      <a href="<?= $edit_page ?>?id=<?= $i ?>"
                         title="<?= html_safe(_('Edit')) ?>"
                         class="btn btn-default btn-xs" data-toggle="tooltip">
                        <span class="fa fa-pencil fa-fw"></span>
                      </a>
                      <a href="<?= $edit_page ?>?dup=<?= $i ?>"
                         title="<?= html_safe(_('Clone')) ?>"
                         class="btn btn-default btn-xs" data-toggle="tooltip">
                        <span class="fa fa-clone fa-fw"></span>
                      </a>
                      <a title="<?= html_safe(_('Delete')) ?>"
                         class="action-delete btn btn-default btn-xs"
                         data-id="<?= $i ?>" data-toggle="tooltip">
                        <span class="fa fa-trash fa-fw"></span>
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
