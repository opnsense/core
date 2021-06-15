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

require_once("guiconfig.inc");
require_once("filter.inc");

$dayArray = array (gettext('Mon'),gettext('Tues'),gettext('Wed'),gettext('Thur'),gettext('Fri'),gettext('Sat'),gettext('Sun'));
$monthArray = array (gettext('January'),gettext('February'),gettext('March'),gettext('April'),gettext('May'),gettext('June'),gettext('July'),gettext('August'),gettext('September'),gettext('October'),gettext('November'),gettext('December'));

$a_schedules = &config_read_array('schedules', 'schedule');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    if (isset($pconfig['id']) && isset($a_schedules[$pconfig['id']])) {
        // id found and valid
        $id = $pconfig['id'];
    }

    if (isset($pconfig['act']) && $pconfig['act'] == 'del' && isset($id)) {
        /* make sure rule is not being referenced by filter rule */
        $referenced_by = false;
        if(isset($config['filter']['rule'])) {
          foreach($config['filter']['rule'] as $rule) {
              //check for this later once this is established
              if ($rule['sched'] == $a_schedules[$id]['name']){
                  $referenced_by = $rule['descr'];
                  break;
              }
          }
        }

        if( $referenced_by !== false) {
            $savemsg = sprintf(gettext("Cannot delete Schedule. Currently in use by %s"),$referenced_by);
        } else {
            unset($a_schedules[$id]);
            write_config();
            header(url_safe('Location: /firewall_schedule.php'));
            exit;
        }
    }
}

include("head.inc");

legacy_html_escape_form_data($a_schedules);

?>
<body>
  <script>
  $( document ).ready(function() {
    // link delete buttons
    $(".act_delete").click(function(){
      var id = $(this).attr("id").split('_').pop(-1);
        // delete single
        BootstrapDialog.show({
          type:BootstrapDialog.TYPE_DANGER,
          title: "<?= gettext("Rules");?>",
          message: "<?=gettext('Do you really want to delete this schedule?');?>",
          buttons: [{
                    label: "<?= gettext("No");?>",
                    action: function(dialogRef) {
                        dialogRef.close();
                    }}, {
                    label: "<?= gettext("Yes");?>",
                    action: function(dialogRef) {
                      $("#id").val(id);
                      $("#action").val("del");
                      $("#iform").submit()
                  }
                }]
      });
    });
  });
  </script>

<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (isset($savemsg)) print_info_box($savemsg); ?>
        <section class="col-xs-12">
          <div class="content-box tab-content">
            <form method="post" name="iform" id="iform">
              <input type="hidden" id="id" name="id" value="" />
              <input type="hidden" id="action" name="act" value="" />
              <table class="table table-striped">
                <thead>
                  <tr>
                    <td><?=gettext("Name");?></td>
                    <td><?=gettext("Time Range(s)");?></td>
                    <td><?=gettext("Description");?></td>
                    <td class="text-nowrap">
                      <a href="firewall_schedule_edit.php" class="btn btn-primary btn-xs" data-toggle="tooltip" title="<?= html_safe(gettext('Add')) ?>">
                        <i class="fa fa-plus fa-fw"></i>
                      </a>
                    </td>
                  </tr>
                </thead>
                <tbody>
                  <?php $i = 0; foreach ($a_schedules as $schedule): ?>
                  <tr ondblclick="document.location='firewall_schedule_edit.php?id=<?=$i;?>';">
                     <td>
                      <?=$schedule['name'];?>
<?php
                        if (filter_get_time_based_rule_status($schedule)):?>
                        <span data-toggle="tooltip" title="<?=gettext("Schedule is currently active");?>" class="fa fa-clock-o"></span>
<?php
                        endif;?>
                    </td>
                    <td>
                      <table class="table table-condensed table-striped">
<?php
                          foreach($schedule['timerange'] as $timerange) {
                              $firstprint = false;
                              if ($timerange){
                                $dayFriendly = "";

                                //get hours
                                $temptimerange = $timerange['hour'];
                                $temptimeseparator = strrpos($temptimerange, "-");

                                $starttime = substr ($temptimerange, 0, $temptimeseparator);
                                $stoptime = substr ($temptimerange, $temptimeseparator+1);

                                if ($timerange['month']){
                                  $tempmontharray = explode(",", $timerange['month']);
                                  $tempdayarray = explode(",",$timerange['day']);
                                  $arraycounter = 0;
                                  $firstDayFound = false;
                                  $firstPrint = false;
                                  foreach ($tempmontharray as $monthtmp){
                                    $month = $tempmontharray[$arraycounter];
                                    $day = $tempdayarray[$arraycounter];

                                    if (!$firstDayFound)
                                    {
                                      $firstDay = $day;
                                      $firstmonth = $month;
                                      $firstDayFound = true;
                                    }

                                    $currentDay = $day;
                                    $nextDay = $tempdayarray[$arraycounter+1];
                                    $currentDay++;
                                    if (($currentDay != $nextDay) || ($tempmontharray[$arraycounter] != $tempmontharray[$arraycounter+1])){
                                      if ($firstPrint)
                                        $dayFriendly .= "<br />";
                                      $currentDay--;
                                      if ($currentDay != $firstDay)
                                        $dayFriendly .= $monthArray[$firstmonth-1] . " " . $firstDay . " - " . $currentDay ;
                                      else
                                        $dayFriendly .=  $monthArray[$month-1] . " " . $day;
                                      $firstDayFound = false;
                                      $firstPrint = true;
                                    }
                                    $arraycounter++;
                                  }
                                } else {
                                  $tempdayFriendly = $timerange['position'];
                                  $firstDayFound = false;
                                  $tempFriendlyDayArray = explode(",", $tempdayFriendly);
                                  $currentDay = "";
                                  $firstDay = "";
                                  $nextDay = "";
                                  $counter = 0;
                                  foreach ($tempFriendlyDayArray as $day){
                                    if ($day != ""){
                                      if (!$firstDayFound)
                                      {
                                        $firstDay = $tempFriendlyDayArray[$counter];
                                        $firstDayFound = true;
                                      }
                                      $currentDay =$tempFriendlyDayArray[$counter];
                                      //get next day
                                      $nextDay = $tempFriendlyDayArray[$counter+1];
                                      $currentDay++;
                                      if ($currentDay != $nextDay){
                                        if ($firstprint)
                                          $dayFriendly .= "<br />";
                                        $currentDay--;
                                        if ($currentDay != $firstDay)
                                          $dayFriendly .= $dayArray[$firstDay-1] . " - " . $dayArray[$currentDay-1];
                                        else
                                          $dayFriendly .= $dayArray[$firstDay-1];
                                        $firstDayFound = false;
                                        $firstprint = true;
                                      }
                                      $counter++;
                                    }
                                  }
                                }
                                $timeFriendly = $starttime . "-" . $stoptime;
                                $description = $timerange['rangedescr'];

                                ?><tr><td><?=$dayFriendly;?></td><td><?=$timeFriendly;?></td><td><?=$description;?></td></tr><?php
                              }
                            }//end for?></table>
                  </td>
                  <td>
                    <?=$schedule['descr'];?>
                  </td>
                  <td>
                    <a href="firewall_schedule_edit.php?id=<?=$i;?>" data-toggle="tooltip" title="<?= html_safe(gettext('Edit')) ?>" class="btn btn-default btn-xs">
                      <span class="fa fa-pencil fa-fw"></span>
                    </a>
                    <a id="del_<?=$i;?>" title="<?= html_safe(gettext('Delete')) ?>" data-toggle="tooltip"  class="act_delete btn btn-default btn-xs">
                      <span class="fa fa-trash fa-fw"></span>
                    </a>
                    <a href="firewall_schedule_edit.php?dup=<?=$i;?>" class="btn btn-default btn-xs" data-toggle="tooltip" title="<?= html_safe(gettext('Clone')) ?>">
                      <span class="fa fa-clone fa-fw"></span>
                    </a>
                  </td>
                </tr>
<?php
                  $i++;
                  endforeach; ?>
              </tbody>
            </table>
          </form>
        </div>
      </section>
    </div>
  </div>
</section>
<?php include("foot.inc"); ?>
