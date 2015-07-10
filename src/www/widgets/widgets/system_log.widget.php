<?php
require_once("functions.inc");
require_once("guiconfig.inc");
require_once('notices.inc');
include_once("includes/functions.inc.php");
require_once("script/load_phalcon.php");

$system_logfile = '/var/log/system.log';
if (!$config['widgets']['systemlogfiltercount'])
{
  $syslogEntriesToFetch = $config['syslog']['nentries'];
  if (!$syslogEntriesToFetch) { $syslogEntriesToFetch = 20;  }
}
else
{
$syslogEntriesToFetch = $config['widgets']['systemlogfiltercount'];
}

if(is_numeric($_POST['logfiltercount']))
{
 $countReceived =  $_POST['logfiltercount'];
 $config['widgets']['systemlogfiltercount'] = $countReceived;
 write_config("Saved Widget System Log Filter Setting");
 Header("Location: /");
 exit(0);
}

?>

<div class="table-responsive">
	<table class="table table-striped table-sort" cellspacing="0" cellpadding="0">
		<? dump_clog($system_logfile, $syslogEntriesToFetch, true, array(), array("ppp")); ?>
	</table>
	<table class="table" style="margin-bottom:0px;text-align:center;">

		<tr class="formselect">
		<td>Number of Log lines to display: </td>

		<td>
		<form action="/widgets/widgets/system_log.widget.php" method="post" name="iform">
                <select name="logfiltercount" id="logfiltercount">
		<?php for ($i = 1; $i <= 50; $i++) {?>
		<option value="<?php echo $i;?>" <?php if ($syslogEntriesToFetch == $i) { echo "selected=\"selected\"";}?>><?php echo $i;?></option>
		<?php } ?>
		</td>
		<td>
		<input id="submit" name="submit" type="submit" class="btn btn-primary formbtn" value="Save" autocomplete="off">
		</form>
		</td>
		</tr>
	</table>
</div>
