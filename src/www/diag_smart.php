<?php

/*
    Copyright (C) 2014 Deciso B.V.
    Copyright (C) 2010 Jim Pingle
    Copyright (C) 2006 Eric Friesen
    All rights reserved

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
    this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
    notice, this list of conditions and the following disclaimer in the
    documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

require_once("guiconfig.inc");

$smartctl = "/usr/local/sbin/smartctl";

$valid_test_types = array("offline", "short", "long", "conveyance");
$valid_info_types = array("i", "H", "c", "A", "a");
$valid_log_types = array("error", "selftest");

include("head.inc");
?>

<body>

<?php
include("fbegin.inc");

?>

<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">

      <section class="col-xs-12">

<?

// Highlates the words "PASSED", "FAILED", and "WARNING".
function add_colors($string)
{
    // To add words keep arrayes matched by numbers
    $patterns[0] = '/PASSED/';
    $patterns[1] = '/FAILED/';
    $patterns[2] = '/Warning/';
    $replacements[0] = '<b><font color="#00ff00">' . gettext("PASSED") . '</font></b>';
    $replacements[1] = '<b><font color="#ff0000">' . gettext("FAILED") . '</font></b>';
    $replacements[2] = '<font color="#ff0000">' . gettext("Warning") . '</font>';
    ksort($patterns);
    ksort($replacements);
    return preg_replace($patterns, $replacements, $string);
}

// What page, aka. action is being wanted
// If they "get" a page but don't pass all arguments, smartctl will throw an error
$action = (isset($_POST['action']) ? $_POST['action'] : $_GET['action']);
$targetdev = basename($_POST['device']);
if (!file_exists('/dev/' . $targetdev)) {
    echo "Device does not exist, bailing.";
    return;
}
switch($action) {
  // Testing devices
  case 'test':
  {
    $test = $_POST['testType'];
    if (!in_array($test, $valid_test_types)) {
      echo "Invalid test type, bailing.";
      return;
    }
    $output = add_colors(shell_exec($smartctl . " -t " . escapeshellarg($test) . " /dev/" . escapeshellarg($targetdev)));
    echo '<pre>' . $output . '
    <form action="diag_smart.php" method="post" name="abort">
    <input type="hidden" name="device" value="' . $targetdev . '" />
    <input type="hidden" name="action" value="abort" />
    <input type="submit" name="submit" value="' . gettext("Abort") . '" />
    </form>
    </pre>';
    break;
  }

  // Info on devices
  case 'info':
  {
    $type = $_POST['type'];
    if (!in_array($type, $valid_info_types)) {
      echo "Invalid info type, bailing.";
      return;
    }
    $output = add_colors(shell_exec($smartctl . " -" . escapeshellarg($type) . " /dev/" . escapeshellarg($targetdev)));
    echo "<pre>$output</pre>";
    break;
  }

  // View logs
  case 'logs':
  {
    $type = $_POST['type'];
    if (!in_array($type, $valid_log_types)) {
      echo "Invalid log type, bailing.";
      return;
    }
    $output = add_colors(shell_exec($smartctl . " -l " . escapeshellarg($type) . " /dev/" . escapeshellarg($targetdev)));
    echo "<pre>$output</pre>";
    break;
  }

  // Abort tests
  case 'abort':
  {
    $output = shell_exec($smartctl . " -X /dev/" . escapeshellarg($targetdev));
    echo "<pre>$output</pre>";
    break;
  }

  // Default page, prints the forms to view info, test, etc...
  default:
  {

    // Get all AD* and DA* (IDE and SCSI) devices currently installed and stores them in the $devs array
    exec("ls /dev | grep '^\(ad\|da\|ada\)[0-9]\{1,2\}$'", $devs);
    ?>

            <div class="content-box tab-content table-responsive">
              <form action="<?= $_SERVER['PHP_SELF']?>" method="post" name="iform" id="iform">
                <table class="table table-striped __nomb">
                   <tr>
                     <th colspan="2" valign="top" class="listtopic"><?=gettext('Info'); ?></th>
                    </tr>
                    <tr>
                      <td><?=gettext("Info type"); ?></td>
                      <td><div class="radio">
                      <label><input type="radio" name="type" value="i" /><?=gettext("Info"); ?></label>&nbsp;
                      <label><input type="radio" name="type" value="H" checked="checked" /><?=gettext("Health"); ?></label>&nbsp;
                      <label><input type="radio" name="type" value="c" /><?=gettext("SMART Capabilities"); ?></label>&nbsp;
                      <label><input type="radio" name="type" value="A" /><?=gettext("Attributes"); ?></label>&nbsp;
                      <label><input type="radio" name="type" value="a" /><?=gettext("All"); ?></label>
                </div>
                      </td>
                    </tr>
                  <tr>
                    <td><?=gettext("Device: /dev/"); ?></td>
                    <td >
                      <select name="device" class="form-control">
                      <?php
                      foreach($devs as $dev)
                      {
                        echo "<option value=\"" . $dev . "\">" . $dev . "</option>";
                      }
                      ?>
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <td width="22%" valign="top">&nbsp;</td>
                    <td width="78%">
                      <input type="hidden" name="action" value="info" />
                      <input type="submit" name="submit" class="btn btn-primary" value="<?=gettext("View"); ?>" />
                    </td>
                  </tr>
              </table>
              </form>
            </div>
      </section>


      <section class="col-xs-12">

            <div class="content-box tab-content table-responsive">
              <form action="<?= $_SERVER['PHP_SELF']?>" method="post" name="test" id="iform">
                <table class="table table-striped __nomb">
                   <tr>
                     <th colspan="2" valign="top" class="listtopic"><?=gettext('Perform Self-tests'); ?></th>
                    </tr>
                    <tr>
                      <td><?=gettext("Test type"); ?></td>
                      <td>
                  <div class="radio">
                    <label><input type="radio" name="testType" value="offline" /><?=gettext("Offline"); ?></label>&nbsp;
                        <label><input type="radio" name="testType" value="short" checked="checked" /><?=gettext("Short"); ?></label>&nbsp;
                        <label><input type="radio" name="testType" value="long" /><?=gettext("Long"); ?></label>&nbsp;
                        <label><input type="radio" name="testType" value="conveyance" /><?=gettext("Conveyance (ATA Disks Only)"); ?></label>
                  </div>
                      </td>
                    </tr>
                  <tr>
                    <td><?=gettext("Device: /dev/"); ?></td>
                    <td >
                      <select name="device" class="form-control">
                      <?php
                      foreach($devs as $dev)
                      {
                        echo "<option value=\"" . $dev . "\">" . $dev . "</option>";
                      }
                      ?>
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <td width="22%" valign="top">&nbsp;</td>
                    <td width="78%">
                      <input type="hidden" name="action" value="test" />
                      <input type="submit" name="submit" class="btn btn-primary" value="<?=gettext("Test"); ?>" />
                    </td>
                  </tr>
                </table>
              </form>
            </div>
      </section>


      <section class="col-xs-12">
            <div class="content-box tab-content table-responsive">
              <form action="<?= $_SERVER['PHP_SELF']?>" method="post" name="logs" id="iform">
                <table class="table table-striped __nomb">
                   <tr>
                     <th colspan="2" valign="top" class="listtopic"><?=gettext('View Logs'); ?></th>
                    </tr>
                    <tr>
                      <td><?=gettext("Log type"); ?></td>
                      <td>
                        <div class="radio">
                  <label><input type="radio" name="type" value="error" checked="checked" /><?=gettext("Error"); ?></label>&nbsp;
                      <label><input type="radio" name="type" value="selftest" /><?=gettext("Self-test"); ?></label>
                        </div>
                      </td>
                    </tr>
                  <tr>
                    <td><?=gettext("Device: /dev/"); ?></td>
                    <td >
                      <select name="device" class="form-control">
                      <?php
                      foreach($devs as $dev)
                      {
                        echo "<option value=\"" . $dev . "\">" . $dev . "</option>";
                      }
                      ?>
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <td width="22%" valign="top">&nbsp;</td>
                    <td width="78%">
                      <input type="hidden" name="action" value="logs" />
                      <input type="submit" name="submit" class="btn btn-primary" value="<?=gettext("View"); ?>" />
                    </td>
                  </tr>
              </table>
              </form>
            </div>
      </section>


      <section class="col-xs-12">
            <div class="content-box tab-content table-responsive">
              <form action="<?= $_SERVER['PHP_SELF']?>" method="post" name="abort" id="iform">
                <table class="table table-striped __nomb">
                   <tr>
                     <th colspan="2" valign="top" class="listtopic"><?=gettext('Abort tests'); ?></th>
                    </tr>
                  <tr>
                    <td><?=gettext("Device: /dev/"); ?></td>
                    <td >
                      <select name="device" class="form-control">
                      <?php
                      foreach($devs as $dev)
                      {
                        echo "<option value=\"" . $dev . "\">" . $dev . "</option>";
                      }
                      ?>
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <td width="22%" valign="top">&nbsp;</td>
                    <td width="78%">
                      <input type="hidden" name="action" value="logs" />
                      <input type="submit" name="submit" value="<?=gettext("Abort"); ?>" class="btn btn-primary" onclick="return confirm('<?=gettext("Do you really want to abort the test?"); ?>')" />
                    </td>
                  </tr>
              </table>
              </form>
            </div>
      </section>

    <?php
    break;
  }
}

// print back button on pages
if(isset($_POST['submit']) && $_POST['submit'] != "Save")
{
  echo '<br /><a class="btn btn-primary" href="' . $_SERVER['PHP_SELF'] . '">' . gettext("Back") . '</a>';
}
?>
<br />
<?php if ($ulmsg) echo "<p><strong>" . $ulmsg . "</strong></p>\n"; ?>

    </section>
  </div>
</div>
</section>


<?php include("foot.inc"); ?>
