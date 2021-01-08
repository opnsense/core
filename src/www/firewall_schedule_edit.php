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

require_once("guiconfig.inc");
require_once("filter.inc");

/****f* legacy/is_schedule_inuse
 * NAME
 *   checks to see if a schedule is currently in use by a rule
 * INPUTS
 *
 * RESULT
 *   true or false
 * NOTES
 *
 ******/
function is_schedule_inuse($schedule)
{
        global $config;

        if ($schedule == '') {
                return false;
        }

        /* loop through firewall rules looking for schedule in use */
        if (isset($config['filter']['rule'])) {
                foreach ($config['filter']['rule'] as $rule) {
                        if ($rule['sched'] == $schedule) {
                                return true;
                        }
                }
        }

        return false;
}

function schedule_sort()
{
    global $config;

    if (!isset($config['schedules']['schedule'])) {
        return;
    }

    usort($config['schedules']['schedule'], function ($a, $b) {
        return strcmp($a['name'], $b['name']);
    });
}

$dayArray = array (gettext('Mon'),gettext('Tues'),gettext('Wed'),gettext('Thur'),gettext('Fri'),gettext('Sat'),gettext('Sun'));
$monthArray = array (gettext('January'),gettext('February'),gettext('March'),gettext('April'),gettext('May'),gettext('June'),gettext('July'),gettext('August'),gettext('September'),gettext('October'),gettext('November'),gettext('December'));

$a_schedules = &config_read_array('schedules', 'schedule');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // input record id, if valid
    if (!empty($_GET['name'])) {
        foreach ($a_schedules as $i => $sched) {
            if ($sched['name'] == $_GET['name']) {
              $id = $i;
              $configId = $id;
              break;
            }
        }
    } elseif (isset($_GET['dup']) && isset($a_schedules[$_GET['dup']]))  {
        $configId = $_GET['dup'];
    } elseif (isset($_GET['id']) && isset($a_schedules[$_GET['id']])) {
        $id = $_GET['id'];
        $configId = $id;
    }
    $pconfig['name'] = $a_schedules[$configId]['name'];
    $pconfig['descr'] = $a_schedules[$configId]['descr'];
    $pconfig['timerange'] = isset($a_schedules[$configId]['timerange']) ? $a_schedules[$configId]['timerange'] : array();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    if (isset($_POST['id']) && isset($a_schedules[$_POST['id']])) {
        $id = $_POST['id'];
    }
    $pconfig = $_POST;

    // validate
    if (strtolower($pconfig['name']) == 'lan') {
        $input_errors[] = gettext('Schedule may not be named LAN.');
    }
    if (strtolower($pconfig['name']) == 'wan') {
        $input_errors[] = gettext('Schedule may not be named WAN.');
    }
    if (empty($pconfig['name'])) {
        $input_errors[] = gettext('Schedule may not use a blank name.');
    }

    if (!preg_match('/^[a-zA-Z0-9_\-]{1,32}$/', $pconfig['name'])) {
        $input_errors[] = sprintf(gettext('The schedule name must be less than 32 characters long and may only consist of the following characters: %s'), 'a-z, A-Z, 0-9, _');
    }

    /* check for name conflicts */
    foreach ($a_schedules as $schedId => $schedule) {
        if ((!isset($id) || $schedId != $id) && $schedule['name'] == $pconfig['name']) {
            $input_errors[] = gettext("A Schedule with this name already exists.");
            break;
        }
    }

    // parse time ranges
    $pconfig['timerange'] = array();

    $timerangeFound = false;
    for ($x=0; $x<99; $x++){
      if($pconfig['schedule' . $x]) {
        if (!preg_match('/^[0-9]+:[0-9]+$/', $pconfig['starttime' . $x])) {
            $input_errors[] = sprintf(gettext("Invalid start time - '%s'"), $pconfig['starttime' . $x]);
            continue;
        }
        if (!preg_match('/^[0-9]+:[0-9]+$/', $pconfig['stoptime' . $x])) {
            $input_errors[] = sprintf(gettext("Invalid stop time - '%s'"), $pconfig['stoptime' . $x]);
            continue;
        }
        $timerangeFound = true;
        $timeparts = array();
        $firstprint = false;
        $timestr = $pconfig['schedule' . $x];
        $timehourstr = $pconfig['starttime' . $x];
        $timehourstr .= "-";
        $timehourstr .= $pconfig['stoptime' . $x];
        $timedescrstr = $pconfig['timedescr' . $x];
        $dashpos = strpos($timestr, '-');
        if ($dashpos === false) {
              $timeparts['position'] = $timestr;
        } else {
            $tempindarray = array();
            $monthstr = "";
            $daystr = "";
            $tempindarray = explode(",", $timestr);
            foreach ($tempindarray as $currentselection) {
                if ($currentselection){
                    if ($firstprint) {
                        $monthstr .= ",";
                        $daystr .= ",";
                    }
                    $tempstr = "";
                    $monthpos = strpos($currentselection, "m");
                    $daypos = strpos($currentselection, "d");
                    $monthstr .= substr($currentselection, $monthpos+1, $daypos-$monthpos-1);
                    $daystr .=  substr($currentselection, $daypos+1);
                    $firstprint = true;
                }
            }
            $timeparts['month'] = $monthstr;
            $timeparts['day'] = $daystr;
          }
          $timeparts['hour'] = $timehourstr;
          $timeparts['rangedescr'] = $timedescrstr;
          $pconfig['timerange'][$x] = $timeparts;
      }
    }

    if (count($pconfig['timerange']) == 0) {
        $input_errors[] = gettext("The schedule must have at least one time range configured.");
    }

    if (count($input_errors) == 0) {
        $schedule = array();
        $schedule['name'] = $pconfig['name'];
        $schedule['descr'] = $pconfig['descr'];
        $schedule['timerange'] = $pconfig['timerange'];

        if (isset($id)) {
            $a_schedules[$id] = $schedule;
        } else {
            $a_schedules[] = $schedule;
        }

        schedule_sort();
        write_config();
        filter_configure();

        header(url_safe('Location: /firewall_schedule.php'));
        exit;
    }
}

legacy_html_escape_form_data($pconfig);

include("head.inc");

?>
<body>
<script>
//<![CDATA[
var daysSelected = "";
var month_array = <?= json_encode($monthArray) ?>;
var day_array = <?= json_encode($dayArray) ?>;
var schCounter = 0;

function repeatExistingDays(){
  var tempstr, tempstrdaypos, week, daypos, dayposdone = "";

  var dayarray = daysSelected.split(",");
  for (let i=0; i<=dayarray.length; i++){
    tempstr = dayarray[i];
    tempstrdaypos = tempstr.search("p");
    week = tempstr.substring(1,tempstrdaypos);
    week = parseInt(week);
    const dashpos = tempstr.search("-");
    daypos = tempstr.substring(tempstrdaypos+1, dashpos);
    daypos = parseInt(daypos);

    const daydone = dayposdone.search(daypos);
    tempstr = 'w' + week + 'p' + daypos;
    const daycell = document.getElementById(tempstr);
    if (daydone == "-1"){
      if (daycell.dataset['state'] == "lightcoral")
        daytogglerepeating(week,daypos,true);
      else
        daytogglerepeating(week,daypos,false);
      dayposdone += daypos + ",";
    }
  }
}

function daytogglerepeating(week,daypos,bExists){
  var tempstr, daycell, dayoriginal = "";
  for (let j=1; j<=53; j++)
  {
    tempstr = 'w' + j + 'p' + daypos;
    daycell = document.getElementById(tempstr);
    const dayoriginalpos =  daysSelected.indexOf(tempstr);

    //if bExists set to true, means cell is already select it
    //unselect it and remove original day from daysSelected string

    if (daycell != null)
    {
      if (bExists){
        daycell.dataset['state'] = "white";
      }
      else
      {
        daycell.dataset['state'] = "lightcoral";
      }

      if (dayoriginalpos != "-1")
      {
        const dayoriginalend = daysSelected.indexOf(',', dayoriginalpos);
        tempstr = daysSelected.substring(dayoriginalpos, dayoriginalend+1);
        daysSelected = daysSelected.replace(tempstr, "");

      }
    }
  }
}

function daytoggle(id) {
  var runrepeat, tempstr = "";
  var bFoundValid = false;

  const iddashpos = id.search("-");
  var tempstrdaypos = id.search("p");
  var week = id.substring(1,tempstrdaypos);
  week = parseInt(week);

  let idmod;
  if (iddashpos == "-1")
  {
    idmod = id;
    runrepeat = true;
    var daypos = id.substr(tempstrdaypos+1);
  }
  else
  {
    idmod = id.substring(0,iddashpos);
    var daypos = id.substring(tempstrdaypos+1,iddashpos);
  }

  daypos = parseInt(daypos);

  while (!bFoundValid){
    var daycell = document.getElementById(idmod);

    if (daycell != null){
      if (daycell.dataset['state'] == "red"){  // red
        daycell.dataset['state'] = "white";
        let str = id + ",";
        daysSelected = daysSelected.replace(str, "");
      }
      else if (daycell.dataset['state'] == "lightcoral")
      {
        daytogglerepeating(week,daypos,true);
      }
      else //color is white cell
      {
        if (!runrepeat)
        {
          daycell.dataset['state'] = "red";  // red
        }
        else
        {
          daycell.dataset['state'] = "lightcoral";
          daytogglerepeating(week,daypos,false);
        }
        daysSelected += id + ",";
      }
      bFoundValid = true;
    }
    else
    {
      //we found an invalid cell when column was clicked, move up to the next week
      week++;
      tempstr = "w" + week + "p" + daypos;
      idmod = tempstr;
    }
  }
}

function update_month(){
  var indexNum = document.iform.monthsel.selectedIndex;
  var selected = document.iform.monthsel.options[indexNum].text;

  for (let month = 0; month < 12; month++){
    let option = document.iform.monthsel.options[month].text;
    document.popupMonthLayer = document.getElementById(option);

    if(selected == option) {
      document.popupMonthLayer.style.display="block";
    }
    else
      document.popupMonthLayer.style.display="none";
  }
}

function checkForRanges(){
  if (daysSelected != "")
  {
    alert("You have not saved the specified time range. Please click 'Add Time' button to save the time range.");
    return false;
  }
  else
  {
    return true;
  }
}

function processEntries(){
  var tempstr, starttimehour, starttimemin, stoptimehour, stoptimemin, errors = "";
  var passedValidiation = true;

  //get time specified
  starttimehour = parseInt(document.getElementById("starttimehour").value);
  starttimemin = parseInt(document.getElementById("starttimemin").value);
  stoptimehour = parseInt(document.getElementById("stoptimehour").value);
  stoptimemin = parseInt(document.getElementById("stoptimemin").value);


  //do time checks
  if (starttimehour > stoptimehour)
  {
    errors = "Error: Start Hour cannot be greater than Stop Hour.";
    passedValidiation = false;

  }
  else if (starttimehour == stoptimehour)
  {
    if (starttimemin > stoptimemin){
      errors = "Error: Start Minute cannot be greater than Stop Minute.";
      passedValidiation = false;
    }
  }

  if (passedValidiation){
    addTimeRange();
  }
  else {
    if (errors != "")
      alert(errors);
  }
}

function addTimeRange(){
  var tempdayarray = daysSelected.split(","),
    tempstr,
    tempFriendlyDay,
    starttimehour,
    starttimemin,
    stoptimehour,
    nrtempFriendlyTime = '',
    rtempFriendlyTime = '',
    nrtempID = '',
    rtempID = "",
    stoptimemin,
    timeRange,
    tempstrdaypos,
    week,
    daypos,
    day,
    month,
    dashpos,
    nrtempTime = '',
    rtempTime = '',
    monthstr = '',
    daystr = "",
    rtempFriendlyDay = "",
    findCurrentCounter,
    nonrepeatingfound,
    tempdescr;
  tempdayarray.sort();

  //check for existing entries
  for (var u=0; u<99; u++){
    findCurrentCounter = document.getElementById("schedule" + u);
    if (!findCurrentCounter)
    {
      schCounter = u;
      break;
    }
  }

  if (daysSelected != ""){
    //get days selected
    for (let i=0; i<tempdayarray.length; i++)
    {
      tempstr = tempdayarray[i];
      if (tempstr != "")
      {
        tempstrdaypos = tempstr.search("p");
        week = parseInt(tempstr.substring(1, tempstrdaypos));
        dashpos = tempstr.search("-");

        if (dashpos != "-1")
        {
          nonrepeatingfound = true;
          daypos = tempstr.substring(tempstrdaypos+1, dashpos);
          daypos = parseInt(daypos);
          let monthpos = tempstr.search("m");
          tempstrdaypos = tempstr.search("d");
          month = tempstr.substring(monthpos+1, tempstrdaypos);
          month = parseInt(month);
          day = tempstr.substring(tempstrdaypos+1);
          day = parseInt(day);
          monthstr += month + ",";
          daystr += day + ",";
          nrtempID += tempstr + ",";
        }
        else
        {
          var repeatingfound = true;
          daypos = tempstr.substr(tempstrdaypos+1);
          daypos = parseInt(daypos);
          rtempFriendlyDay += daypos + ",";
          rtempID += daypos + ",";
        }
      }
    }

    //code below spits out friendly look format for nonrepeating schedules
    var foundEnd = false;
    var firstDayFound = false;
    var firstprint = false;
    var tempFriendlyMonthArray = monthstr.split(",");
    var tempFriendlyDayArray = daystr.split(",");
    var currentDay, firstDay, nextDay, firstMonth = 0;
    for (var k=0; k<tempFriendlyMonthArray.length; k++){
      tempstr = tempFriendlyMonthArray[k];
      if (tempstr != ""){
        if (!firstDayFound)
        {
          firstDay = parseInt(tempFriendlyDayArray[k]);
          firstMonth = parseInt(tempFriendlyMonthArray[k]);
          firstDayFound = true;
        }
        currentDay = parseInt(tempFriendlyDayArray[k]);
        //get next day
        nextDay = parseInt(tempFriendlyDayArray[k+1]);
        //get next month

        currentDay++;
        if ((currentDay != nextDay) || (tempFriendlyMonthArray[k] != tempFriendlyMonthArray[k+1])){
          if (firstprint)
            nrtempFriendlyTime += ", ";
          currentDay--;
          if (currentDay != firstDay) {
            nrtempFriendlyTime += month_array[firstMonth-1] + " " + firstDay + "-" + currentDay;
          }
          else {
            nrtempFriendlyTime += month_array[firstMonth-1] + " " + currentDay;
          }
          firstDayFound = false;
          firstprint = true;
        }
      }
    }

    //code below spits out friendly look format for repeating schedules
    foundEnd = false;
    firstDayFound = false;
    firstprint = false;
    tempFriendlyDayArray = rtempFriendlyDay.split(",");
    tempFriendlyDayArray.sort();
    currentDay, firstDay, nextDay = "";
    for (k=0; k<tempFriendlyDayArray.length; k++){
      tempstr = tempFriendlyDayArray[k];
      if (tempstr != ""){
        if (!firstDayFound)
        {
          firstDay = parseInt(tempFriendlyDayArray[k]);
          firstDayFound = true;
        }
        currentDay = parseInt(tempFriendlyDayArray[k]);
        //get next day
        nextDay = parseInt(tempFriendlyDayArray[k+1]);
        currentDay++;
        if (currentDay != nextDay){
          if (firstprint) {
            rtempFriendlyTime += ", ";
          }
          currentDay--;
          if (currentDay != firstDay) {
            rtempFriendlyTime += day_array[firstDay-1] + " - " + day_array[currentDay-1];
          }
          else {
            rtempFriendlyTime += day_array[firstDay-1];
          }
          firstDayFound = false;
          firstprint = true;
        }
      }
    }

    //sort the tempID
    var tempsortArray = rtempID.split(",");
    var isFirstdone = false;
    tempsortArray.sort();
    //clear tempID
    rtempID = "";
    for (let t=0; t<tempsortArray.length; t++)
    {
      if (tempsortArray[t] != ""){
        if (!isFirstdone){
          rtempID += tempsortArray[t];
          isFirstdone = true;
        }
        else
          rtempID += "," + tempsortArray[t];
      }
    }


    //get time specified
    starttimehour =  document.getElementById("starttimehour").value;
    starttimemin = document.getElementById("starttimemin").value;
    stoptimehour = document.getElementById("stoptimehour").value;
    stoptimemin = document.getElementById("stoptimemin").value;

    timeRange = "||"
    + starttimehour + ":"
    + starttimemin + "-"
    + stoptimehour + ":"
    + stoptimemin;

    //get description for time range
    tempdescr = escape(document.getElementById("timerangedescr").value);

    if (nonrepeatingfound){
      nrtempTime += nrtempID;
      //add time ranges
      nrtempTime += timeRange;
      //add description
      nrtempTime += "||" + tempdescr;
      insertElements(nrtempFriendlyTime,
                     starttimehour,
                     starttimemin,
                     stoptimehour,
                     stoptimemin,
                     tempdescr,
                     nrtempTime,
                     nrtempID);
    }

    if (repeatingfound){
      rtempTime += rtempID;
      //add time ranges
      rtempTime += timeRange;
      //add description
      rtempTime += "||" + tempdescr;
      insertElements(rtempFriendlyTime,
                     starttimehour,
                     starttimemin,
                     stoptimehour,
                     stoptimemin,
                     tempdescr,
                     rtempTime,
                     rtempID);
    }

  }
  else
  {
    //no days were selected, alert user
    alert ("You must select at least 1 day before adding time");
  }
}

function insertElements(tempFriendlyTime, starttimehour, starttimemin, stoptimehour, stoptimemin, tempdescr, tempTime, tempID){

    //add it to the schedule list
    let d = document;
    let tbody = document.getElementById("scheduletable").getElementsByTagName("tbody").item(0);
    var tr = document.createElement("tr");
    var td = document.createElement("td");
    td.innerHTML= "<span>"+tempFriendlyTime+"</span>";
    tr.appendChild(td);

    td = document.createElement("td");
    td.innerHTML="<input type='text' readonly='readonly' name='starttime"+schCounter+"' id='starttime"+schCounter+"' style=' word-wrap:break-word; width:100%; border:0px solid;' value='"+starttimehour+":"+starttimemin+"' />";
    tr.appendChild(td);

    td = document.createElement("td");
    td.innerHTML="<input type='text' readonly='readonly' name='stoptime"+schCounter+"' id='stoptime"+schCounter+"' style=' word-wrap:break-word; width:100%; border:0px solid;' value='"+stoptimehour+":"+stoptimemin+"' />";
    tr.appendChild(td);

    td = document.createElement("td");
    td.innerHTML="<input type='text' readonly='readonly' name='timedescr"+schCounter+"' id='timedescr"+schCounter+"' style=' word-wrap:break-word; width:100%; border:0px solid;' value='"+tempdescr+"' />";
    tr.appendChild(td);

    td = document.createElement("td");
    td.innerHTML = "<a onclick='editRow(\""+tempTime+"\",this); return false;' href='#' class=\"btn btn-default btn-xs\"><span class=\"fa fa-pencil fa-fw\"></span></a>";
    tr.appendChild(td);

    td = document.createElement("td");
    td.innerHTML = "<a onclick='removeRow(this); return false;' href='#' class=\"btn btn-default btn-xs\"><span class=\"fa fa-trash fa-fw\"></span></a>";
    tr.appendChild(td);

    td = document.createElement("td");
    td.innerHTML="<input type='hidden' id='schedule"+schCounter+"' name='schedule"+schCounter+"' value='"+tempID+"' />";
    tr.appendChild(td);
    tbody.appendChild(tr);

    schCounter++;

    //reset calendar and time and descr
    clearCalendar();
    clearTime();
    clearDescr();
}


function clearCalendar(){
  var tempstr, daycell = "";
  //clear days selected
  daysSelected = "";
  //loop through all 53 weeks
  for (let week=1; week<=53; week++)
  {
    //loop through all 7 days
    for (let day = 1; day <= 7; day++){
      tempstr = 'w' + week + 'p' + day;
      daycell = document.getElementById(tempstr);
      if (daycell != null){
        daycell.dataset['state'] = "white";
      }
    }
  }
}

function clearTime(){
  document.getElementById("starttimehour").value = "0";
  document.getElementById("starttimemin").value = "00";
  document.getElementById("stoptimehour").value = "23";
  document.getElementById("stoptimemin").value = "59";
}

function clearDescr(){
  document.getElementById("timerangedescr").value = "";
}

function editRow(incTime, el) {
  if (checkForRanges()){

    //reset calendar and time
    clearCalendar();
    clearTime();

    var starttimehour, descr, days, tempstr, starttimemin, hours, stoptimehour, stoptimemin = "";

    let tempArray = incTime.split ("||");

    days = tempArray[0];
    hours = tempArray[1];
    descr = escape(tempArray[2]);

    var tempdayArray = days.split(",");
    var temphourArray = hours.split("-");
    tempstr = temphourArray[0];
    var temphourArray2 = tempstr.split(":");

    document.getElementById("starttimehour").value = temphourArray2[0];
    document.getElementById("starttimemin").value = temphourArray2[1];

    tempstr = temphourArray[1];
    temphourArray2 = tempstr.split(":");

    document.getElementById("stoptimehour").value = temphourArray2[0];
    document.getElementById("stoptimemin").value = temphourArray2[1];

    document.getElementById("timerangedescr").value = descr;

    //toggle the appropriate days
    for (let i=0; i<tempdayArray.length; i++)
    {
      if (tempdayArray[i]){
        var tempweekstr = tempdayArray[i];
        let dashpos = tempweekstr.search("-");

        if (dashpos == "-1")
        {
          tempstr = "w2p" + tempdayArray[i];
        }
        else
        {
          tempstr = tempdayArray[i];
        }
        daytoggle(tempstr);
      }
    }
    removeRownoprompt(el);
  }
  $('.selectpicker').selectpicker('refresh');
}

function removeRownoprompt(el) {
    while (el && el.nodeName.toLowerCase() != "tr") {
      el = el.parentNode;
    }
    if (el) {
      el.remove();
    }
}


function removeRow(el) {
  if (confirm("Do you really want to delete this time range?")){
    while (el && el.nodeName.toLowerCase() != "tr") {
      el = el.parentNode;
    }
    if (el) {
      el.remove();
    }
  }
}

// XXX Workaround: hook_stacked_form_tables breaks CSS query otherwise
$( function() { $('#iform td').css({ 'background-color' : '' }); })

//]]>
</script>
<?php include("fbegin.inc");  echo $jscriptstr; ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
          <section class="col-xs-12">
            <div class="content-box tab-content">
              <form method="post" name="iform" id="iform">
                  <table class="table table-striped opnsense_standard_table_form">
                    <tbody>
                      <tr>
                        <td style="width:15%"><strong><?=gettext("Schedule information");?></strong></td>
                        <td style="width:85%; text-align:right">
                          <small><?=gettext("full help"); ?> </small>
                          <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                        </td>
                      </tr>
                      <tr>
                        <td><i class="fa fa-info-circle text-muted"></i> <?= gettext('Name') ?></td>
                        <td>
<?php
                            if (is_schedule_inuse($pconfig['name']) && isset($id)): ?>
                          <input name="name" type="hidden" id="name" value="<?=htmlspecialchars($pconfig['name']);?>" />
                          <?=$pconfig['name']; ?>
                          <p>
                            <?=gettext("This schedule is in use so the name may not be modified!");?>
                          </p>
<?php
                            else: ?>
                          <input name="name" type="text" id="name" value="<?=$pconfig['name'];?>" />
<?php
                            endif; ?>
                        </td>
                      </tr>
                      <tr>
                        <td><a id="help_for_description" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description");?></td>
                        <td>
                          <input name="descr" type="text" id="descr" value="<?=$pconfig['descr'];?>" /><br />
                          <div class="hidden" data-for="help_for_name">
                            <?=gettext("You may enter a description here for your reference (not parsed).");?>
                          </div>
                        </td>
                      </tr>
                      <tr>
                        <td><a id="help_for_month" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Month");?></td>
                        <td>
                          <select name="monthsel" class="selectpicker" data-width="auto" data-live-search="true" id="monthsel" onchange="update_month();">
<?php
                              $monthcounter = date("n");
                              $monthlimit = $monthcounter + 12;
                              $yearcounter = date("Y");
                              for ($k=0; $k<12; $k++){?>
                              <option value="<?= $monthcounter;?>"><?=date("F_y", mktime(0, 0, 0, date($monthcounter), 1, date($yearcounter)));?></option>
                              <?php
                                if ($monthcounter == 12) {
                                  $monthcounter = 1;
                                  $yearcounter++;
                                } else {
                                  $monthcounter++;
                                }
                              } ?>
                          </select>
                          <br /><br />
<?php
                            $firstmonth = TRUE;
                            $monthcounter = date("n");
                            $yearcounter = date("Y");
                            for ($k=0; $k<12; $k++){
                              $firstdayofmonth = date("w", mktime(0, 0, 0, date($monthcounter), 1, date($yearcounter)));
                              if ($firstdayofmonth == 0) {
                                  $firstdayofmonth = 7;
                              }
                              $daycounter = 1;
                              //number of day in month
                              $numberofdays = date("t", mktime(0, 0, 0, date($monthcounter), 1, date($yearcounter)));
                              $firstdayprinted = FALSE;
                              $lasttr = FALSE;
                              $positioncounter = 1;//7 for Sun, 1 for Mon, 2 for Tues, etc
?>
                            <div id="<?=date("F_y",mktime(0, 0, 0, date($monthcounter), 1, date($yearcounter)));?>" style=" position:relative; display:<?= $firstmonth ? "block" : "none";?>">
                              <table id="calTable<?=$monthcounter . $yearcounter;?>" class="table table-condensed table-bordered">
                                <thead>
                                  <tr><td colspan="7" style="text-align:center"><?= date("F_Y", mktime(0, 0, 0, date($monthcounter), 1, date($yearcounter)));?></td></tr>
                                  <tr>
                                    <td style="text-align:center; cursor: pointer;" onclick="daytoggle('w1p1');"><u><?=gettext("Mon");?></u></td>
                                    <td style="text-align:center; cursor: pointer;" onclick="daytoggle('w1p2');"><u><?=gettext("Tue");?></u></td>
                                    <td style="text-align:center; cursor: pointer;" onclick="daytoggle('w1p3');"><u><?=gettext("Wed");?></u></td>
                                    <td style="text-align:center; cursor: pointer;" onclick="daytoggle('w1p4');"><u><?=gettext("Thu");?></u></td>
                                    <td style="text-align:center; cursor: pointer;" onclick="daytoggle('w1p5');"><u><?=gettext("Fri");?></u></td>
                                    <td style="text-align:center; cursor: pointer;" onclick="daytoggle('w1p6');"><u><?=gettext("Sat");?></u></td>
                                    <td style="text-align:center; cursor: pointer;" onclick="daytoggle('w1p7');"><u><?=gettext("Sun");?></u></td>
                                  </tr>
                                </thead>
                                <tbody>
<?php
                                    $firstmonth = FALSE;
                                    while ($daycounter<=$numberofdays){
                                      $weekcounter =  date("W", mktime(0, 0, 0, date($monthcounter), date($daycounter), date($yearcounter)));
                                      $weekcounter = ltrim($weekcounter, "0");
                                      if ($positioncounter == 1) {
                                        echo "<tr>";
                                      }
                                      if ($firstdayofmonth == $positioncounter){?>
                                        <td style="text-align:center; cursor: pointer;" id="w<?=$weekcounter;?>p<?=$positioncounter;?>" onclick="daytoggle('w<?=$weekcounter;?>p<?=$positioncounter;?>-m<?=$monthcounter;?>d<?=$daycounter;?>');">
                                        <?php
                                          echo $daycounter;
                                          $daycounter++;
                                          $firstdayprinted = TRUE;
                                          echo "</td>";
                                      } elseif ($firstdayprinted == TRUE && $daycounter <= $numberofdays){?>
                                      <td style="text-align:center; cursor: pointer;" id="w<?=$weekcounter;?>p<?=$positioncounter;?>" onclick="daytoggle('w<?=$weekcounter;?>p<?=$positioncounter;?>-m<?=$monthcounter;?>d<?=$daycounter;?>');">
                                        <?php
                                          echo $daycounter;
                                          $daycounter++;
                                          echo "</td>";
                                      } else {
                                        echo "<td style=\"text-align:center\"></td>";
                                      }

                                      if ($positioncounter == 7 || $daycounter > $numberofdays) {
                                        $positioncounter = 1;
                                        echo "</tr>";
                                      } else {
                                        $positioncounter++;
                                      }
                                    }//end while loop?>
                                </tbody>
                              </table>
                            </div>
<?php
                              if ($monthcounter == 12) {
                                $monthcounter = 1;
                                $yearcounter++;
                              } else {
                                $monthcounter++;
                              }
                            } //end for loop
?>
                          <div class="hidden" data-for="help_for_month">
                            <br />
                            <?=gettext("Click individual date to select that date only. Click the appropriate weekday Header to select all occurrences of that weekday.");?>
                          </div>
                        </td>
                      </tr>
                      <tr>
                        <td><a id="help_for_time" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Time");?></td>
                        <td>
                          <table class="tabcont">
                            <tr>
                              <td><?=gettext("Start Time");?></td>
                              <td><?=gettext("Stop Time");?></td>
                            </tr>
                            <tr>
                              <td>
                                <div class="input-group">
                                  <select name="starttimehour" class="selectpicker form-control" data-width="auto" data-size="5" data-live-search="true" id="starttimehour">
<?php
                                    for ($i=0; $i<24; $i++):?>
                                    <option value="<?=$i;?>"><?=$i;?> </option>
<?php
                                      endfor; ?>
                                  </select>
                                  <select name="starttimemin" class="selectpicker form-control" data-width="auto" data-size="5" data-live-search="true" id="starttimemin">
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
                                  <select name="stoptimehour" class="selectpicker form-control" data-width="auto" data-size="5" data-live-search="true" id="stoptimehour">
<?php
                                    for ($i=0; $i<24; $i++):?>
                                    <option value="<?=$i;?>"><?=$i;?> </option>
<?php
                                      endfor; ?>
                                  </select>
                                  <select name="stoptimemin" class="selectpicker form-control" data-width="auto" data-size="5" data-live-search="true" id="stoptimemin">
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
                          <?=gettext("Select the time range for the day(s) selected on the Month(s) above. A full day is 0:00-23:59.")?>
                          </div>
                        </td>
                      </tr>
                      <tr>
                        <td><a id="help_for_timerange_desc" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Time Range Description")?></td>
                        <td>
                          <input name="timerangedescr" type="text" id="timerangedescr"/>
                          <div class="hidden" data-for="help_for_timerange_desc">
                            <?=gettext("You may enter a description here for your reference (not parsed).")?>
                          </div>
                        </td>
                      </tr>
                      <tr>
                        <td>&nbsp;</td>
                        <td>
                          <input type="button" value="<?= html_safe(gettext('Add Time')) ?>" class="btn btn-default" onclick="javascript:processEntries();" />&nbsp;&nbsp;&nbsp;
                          <input type="button" value="<?= html_safe(gettext('Clear Selection')) ?>" class="btn btn-default" onclick="javascript:clearCalendar(); clearTime(); clearDescr();" />
                        </td>
                      </tr>
                      <tr>
                        <th colspan="2"><?= gettext('Schedule repeat') ?></th>
                      </tr>
                      <tr>
                        <td><?=gettext("Configured Ranges");?></td>
                        <td>
                          <table id="scheduletable">
                            <tbody>
                              <tr>
                                <td style="width:35%"><?=gettext("Day(s)");?></td>
                                <td style="width:12%"><?=gettext("Start Time");?></td>
                                <td style="width:11%"><?=gettext("Stop Time");?></td>
                                <td style="width:42%"><?=gettext("Description");?></td>
                              </tr>
                              <?php
                              if (isset($pconfig['timerange'])){
                                $counter = 0;
                                foreach($pconfig['timerange'] as $timerange) {
                                  $tempFriendlyTime = "";
                                  $tempID = "";
                                  if ($timerange){
                                    $dayFriendly = "";
                                    $tempFriendlyTime = "";
                                    $timedescr = $timerange['rangedescr'];
                                    //get hours
                                    $temptimerange = $timerange['hour'];
                                    $temptimeseparator = strrpos($temptimerange, "-");

                                    $starttime = substr ($temptimerange, 0, $temptimeseparator);
                                    $stoptime = substr ($temptimerange, $temptimeseparator+1);
                                    $currentDay = "";
                                    $firstDay = "";
                                    $nextDay = "";
                                    $foundEnd = false;
                                    $firstDayFound = false;
                                    $firstPrint = false;
                                    $firstprint2 = false;

                                    if (!empty($timerange['month'])){
                                      $tempmontharray = explode(",", $timerange['month']);
                                      $tempdayarray = explode(",",$timerange['day']);
                                      $arraycounter = 0;
                                      foreach ($tempmontharray as $monthtmp){
                                        $month = $tempmontharray[$arraycounter];
                                        $day = $tempdayarray[$arraycounter];
                                        $daypos = date("w", mktime(0, 0, 0, date($month), date($day), date("Y")));
                                        //if sunday, set position to 7 to get correct week number. This is due to php limitations on ISO-8601. When we move to php5.1 we can change this.
                                        if ($daypos == 0){
                                          $daypos = 7;
                                        }
                                        $weeknumber = date("W", mktime(0, 0, 0, date($month), date($day), date("Y")));
                                        $weeknumber = ltrim($weeknumber, "0");
                                        if ($firstPrint) {
                                          $tempID .= ",";
                                        }
                                        $tempID .= "w" . $weeknumber . "p" . $daypos . "-m" .  $month . "d" . $day;
                                        $firstPrint = true;
                                        if (!$firstDayFound) {
                                          $firstDay = $day;
                                          $firstmonth = $month;
                                          $firstDayFound = true;
                                        }

                                        $currentDay = $day;
                                        $nextDay = $tempdayarray[$arraycounter+1];
                                        $currentDay++;
                                        if (($currentDay != $nextDay) || ($tempmontharray[$arraycounter] != $tempmontharray[$arraycounter+1])){
                                          if ($firstprint2) {
                                              $tempFriendlyTime .= ", ";
                                          }
                                          $currentDay--;
                                          if ($currentDay != $firstDay) {
                                              $tempFriendlyTime .= $monthArray[$firstmonth-1] . " " . $firstDay . " - " . $currentDay ;
                                          } else {
                                              $tempFriendlyTime .=  $monthArray[$month-1] . " " . $day;
                                          }
                                          $firstDayFound = false;
                                          $firstprint2 = true;
                                        }
                                        $arraycounter++;
                                      }
                                    }  else {
                                      $dayFriendly = $timerange['position'];
                                      $tempID = $dayFriendly;
                                    }
                                    $tempTime = $tempID . "||" . $starttime . "-" . $stoptime . "||" . $timedescr;

                                    //following code makes the days friendly appearing, IE instead of Mon, Tues, Wed it will show Mon - Wed
                                    $foundEnd = false;
                                    $firstDayFound = false;
                                    $firstprint = false;
                                    $tempFriendlyDayArray = explode(",", $dayFriendly);
                                    $currentDay = "";
                                    $firstDay = "";
                                    $nextDay = "";
                                    $i = 0;
                                    if (empty($timerange['month'])) {
                                      foreach ($tempFriendlyDayArray as $day){
                                        if ($day != ""){
                                          if (!$firstDayFound) {
                                            $firstDay = $tempFriendlyDayArray[$i];
                                            $firstDayFound = true;
                                          }
                                          $currentDay =$tempFriendlyDayArray[$i];
                                          //get next day
                                          $nextDay = $tempFriendlyDayArray[$i+1];
                                          $currentDay++;
                                          if ($currentDay != $nextDay){
                                            if ($firstprint){
                                                $tempFriendlyTime .= ", ";
                                            }
                                            $currentDay--;
                                            if ($currentDay != $firstDay) {
                                                $tempFriendlyTime .= $dayArray[$firstDay-1] . " - " . $dayArray[$currentDay-1];
                                            } else {
                                                $tempFriendlyTime .= $dayArray[$firstDay-1];
                                            }
                                            $firstDayFound = false;
                                            $firstprint = true;
                                          }
                                          $i++;
                                        }
                                      }
                                    }
?>
                              <tr>
                                <td>
                                  <span><?=$tempFriendlyTime; ?></span>
                                </td>
                                <td>
                                  <input type='text' readonly='readonly' name='starttime<?=$counter; ?>' id='starttime<?=$counter; ?>' style=' word-wrap:break-word; width:100%; border:0px solid;' value='<?=$starttime; ?>' />
                                </td>
                                <td>
                                  <input type='text' readonly='readonly' name='stoptime<?=$counter; ?>' id='stoptime<?=$counter; ?>' style=' word-wrap:break-word; width:100%; border:0px solid;' value='<?=$stoptime; ?>' />
                                </td>
                                <td>
                                  <input type='text' readonly='readonly' name='timedescr<?=$counter; ?>' id='timedescr<?=$counter; ?>' style=' word-wrap:break-word; width:100%; border:0px solid;' value='<?=$timedescr; ?>' />
                                </td>
                                <td>
                                  <a onclick='editRow("<?=$tempTime; ?>",this); return false;' href='#' class="btn btn-default"><span class="fa fa-pencil fa-fw"></span></a>
                                </td>
                                <td>
                                  <a onclick='removeRow(this); return false;' href='#' class="btn btn-default"><span class="fa fa-trash fa-fw"></span></a>
                                </td>
                                <td>
                                  <input type='hidden' id='schedule<?=$counter; ?>' name='schedule<?=$counter; ?>' value='<?=$tempID; ?>' />
                                </td>
                              </tr>
                              <?php
                              $counter++;
                            }//end if
                          } // end foreach
                        }//end if
                        ?>
                          </tbody>
                        </table>
                      </td>
                    </tr>
                    <tr>
                      <td>&nbsp;</td>
                      <td>
                        <input id="submit" name="submit" type="submit" onclick="return checkForRanges();" class="btn btn-primary" value="<?=html_safe(gettext('Save')); ?>" />
                        <input type="button" class="btn btn-default" value="<?=html_safe(gettext('Cancel'));?>" onclick="window.location.href='/firewall_schedule.php'" />
                        <?php if (isset($id)): ?>
                          <input name="id" type="hidden" value="<?=$id;?>" />
                        <?php endif; ?>
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
<?php include("foot.inc"); ?>
