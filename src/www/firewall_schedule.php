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

$days = [
    _('Mon'),
    _('Tue'),
    _('Wed'),
    _('Thu'),
    _('Fri'),
    _('Sat'),
    _('Sun')
];

$months = [
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

function _getSelectedDaysNonRepeating(array $time_range): string {
    global $months;

    if (!@$time_range['month']) {
        return '';
    }

    $selected = (object)[
        'months' => explode(',', $time_range['month']),
        'days' => explode(',', $time_range['day'])
    ];
    $day_range_start = null;

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
            $days_selected_text[] = sprintf('%s %s', $months[$month - 1], $day);
            $day_range_start = null;
            continue;
        }

        $days_selected_text[] = sprintf('%s %s-%s',
            $months[$month - 1],
            $day_range_start,
            $day
        );
        $day_range_start = null;
    }

    return nl2br(implode("\n", $days_selected_text));
}

function _getSelectedDaysRepeating(array $time_range): string {
    global $days;

    if (!@$time_range['position']) {
        return '';
    }

    $days_of_week = explode(',', $time_range['position']);
    $day_range_start = null;

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

        $start_day = $days[$day_range_start - 1];
        $end_day = $days[$day_of_week - 1];

        if ($day_of_week == $day_range_start) {
            $days_selected_text[] = $start_day;
            $day_range_start = null;
            continue;
        }

        $days_selected_text[] = sprintf('%s-%s', $start_day, $end_day);
        $day_range_start = null;
    }

    return nl2br(implode("\n", $days_selected_text));
}

function getSelectedDays(array $time_range): string {
    if (@$time_range['month']) {
        return _getSelectedDaysNonRepeating($time_range);
    }

    return _getSelectedDaysRepeating($time_range);
}


$config_schedules = &config_read_array('schedules', 'schedule');
$delete_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Is the ID valid?
    $id = @$_POST['id'];
    $id = (!isset($config_schedules[$id])) ? null : (int)$id;

    if ($id && @$_POST['action'] == 'del') {
        // Make sure rule is not being referenced by filter rule
        $rules = $config['filter']['rule'] ?? [];

        foreach ($rules as $rule) {
            if ($rule['sched'] != $config_schedules[$id]['name']) {
                continue;
            }

            $delete_error = _(sprintf(
                'Cannot delete Schedule. Currently in use by %s',
                $rule['descr']
            ));
            break;
        }

        if (!$delete_error) {
            unset($config_schedules[$id]);
            write_config();

            header(url_safe(sprintf('Location: /%s', basename(__FILE__))));
            exit;
        }
    }
}

include('head.inc');

legacy_html_escape_form_data($config_schedules);
?>
<body>
<script>
$(document).ready(function() {
    $('.act_delete').click(function() {
        const id = $(this).attr('id').split('_').pop(-1);

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
                    <td><?= _('Name') ?></td>
                    <td><?= _("Time Range(s)") ?></td>
                    <td><?= _("Description") ?></td>
                    <td class="text-nowrap">
                      <a href="<?= $edit_page ?>" title="<?= html_safe(_('Add')) ?>"
                         class="btn btn-primary btn-xs" data-toggle="tooltip">
                        <em class="fa fa-plus fa-fw"></em>
                      </a>
                    </td>
                  </tr>
                </thead>
                <tbody>
<?php
foreach ($config_schedules as $i => $config_schedule):
?>
                  <tr ondblclick="document.location='<?= $edit_page ?>?id=<?= $i ?>';">
                    <td>
                      <?= $config_schedule['name'] ?>
<?php
    if (filter_get_time_based_rule_status($config_schedule)):
?>
                      <span title="<?= _('Schedule is currently active') ?>"
                            class="fa fa-clock-o"
                            data-toggle="tooltip"></span>
<?php
    endif;
?>
                    </td>
                    <td>
                      <table class="table table-condensed table-striped">
<?php
    foreach ($config_schedule['timerange'] as $time_range):
        if (!$time_range) {
            continue;
        }
?>
                        <tr>
                          <td><?= getSelectedDays($time_range) ?></td>
                          <td><?= $time_range['hour'] ?></td>
                          <td><?= rawurldecode($time_range['rangedescr']) ?></td>
                        </tr>
<?php
    endforeach;
?>
                      </table>
                    </td>
                    <td><?= $config_schedule['descr'] ?></td>
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
                      <a id="del_<?= $i ?>" title="<?= html_safe(_('Delete')) ?>"
                         class="act_delete btn btn-default btn-xs" data-toggle="tooltip">
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
