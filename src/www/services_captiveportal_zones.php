<?php
/*
	LICENSE
*/

##|+PRIV
##|*IDENT=page-services-captiveportal-zones
##|*NAME=Services: Captiveprotal Zones page
##|*DESCR=Allow access to the 'Services: CaptivePortal Zones' page.
##|*MATCH=services_captiveportal_zones.php*
##|-PRIV

require("guiconfig.inc");
require("functions.inc");
require_once("filter.inc");
require("shaper.inc");
require("captiveportal.inc");

global $cpzone;
global $cpzoneid;

if (!is_array($config['captiveportal']))
	$config['captiveportal'] = array();
$a_cp = &$config['captiveportal'];

if ($_GET['act'] == "del" && !empty($_GET['zone'])) {
	$cpzone = $_GET['zone'];
	if ($a_cp[$cpzone]) {
		$cpzoneid = $a_cp[$cpzone]['zoneid'];
		unset($a_cp[$cpzone]['enable']);
		captiveportal_configure_zone($a_cp[$cpzone]);
		unset($a_cp[$cpzone]);
		if (isset($config['voucher'][$cpzone]))
			unset($config['voucher'][$cpzone]);
		write_config();
		header("Location: services_captiveportal_zones.php");
		exit;
	}
}

$pgtitle = array(gettext("Captiveportal"),gettext("Zones"));
$shortcut_section = "captiveportal";
include("head.inc");

$main_buttons = array(
	array('href'=>'services_captiveportal_zones_edit.php', 'label'=>gettext("add a new captiveportal instance")),	
);

?>

<body>
<?php include("fbegin.inc"); ?>

<section class="page-content-main">
		<div class="container-fluid">	
			<div class="row">
				
				<?php if ($savemsg) print_info_box($savemsg); ?>
				<?php if (is_subsystem_dirty('captiveportal')): ?><p>
				<?php print_info_box_np(gettext("The CaptivePortal entry list has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));?>
				<?php endif; ?>
				
			    <section class="col-xs-12">
					
					<div class="content-box">	    					
    				    
    				    <form action="services_captiveportal_zones.php" method="post" name="iform" id="iform">

							<div class="table-responsive">
						 		<table class="table table-striped table-sort">

									<tr>
									  <td width="15%" class="listhdrr"><?=gettext("Zone");?></td>
									  <td width="30%" class="listhdrr"><?=gettext("Interfaces");?></td>
									  <td width="10%" class="listhdrr"><?=gettext("Users");?></td>
									  <td width="35%" class="listhdrr"><?=gettext("Description");?></td>
									  <td width="10%" class="list">
									   
									  </td>
									</tr>
										  <?php foreach ($a_cp as $cpzone => $cpitem):
											if (!is_array($cpitem))
												continue;
										  ?>
									<tr>
									  <td class="listlr" ondblclick="document.location='services_captiveportal.php?zone=<?=$cpzone;?>';">
									    <?=htmlspecialchars($cpitem['zone']);?>
									  </td>
									  <td class="listlr" ondblclick="document.location='services_captiveportal.php?zone=<?=$cpzone;?>';">
									    <?php $cpifaces = explode(",", $cpitem['interface']);
										foreach ($cpifaces as $cpiface)
											echo convert_friendly_interface_to_friendly_descr($cpiface) . " ";
									    ?>
									  </td>
									  <td class="listr" ondblclick="document.location='services_captiveportal.php?zone=<?=$cpzone;?>';">
									      <?php
									      $cpdb = new Captiveportal\DB($cpzone) ;
									      echo $cpdb->countClients() ;
									      ?>
									  </td>
									  <td class="listbg" ondblclick="document.location='services_captiveportal.php?zone=<?=$cpzone;?>';">
									    <?=htmlspecialchars($cpitem['descr']);?>&nbsp;
									  </td>
									  <td valign="middle" class="list nowrap">
									    <a href="services_captiveportal.php?zone=<?=$cpzone?>" title="<?=gettext("edit captiveportal instance"); ?>"  class="btn btn-default btn-xs"><span class="glyphicon glyphicon-pencil"></span></a>
									    <a href="services_captiveportal_zones.php?act=del&amp;zone=<?=$cpzone;?>" onclick="return confirm('<?=gettext("Do you really want to delete this entry?");?>')" title="<?=gettext("delete captiveportal instance");?>"  class="btn btn-default btn-xs"><span class="glyphicon glyphicon-remove"></span></a>
									  </td>
									</tr>
										  <?php endforeach; ?>
									</table>
							</div>
    				    </form>
					</div>
			    </section>
			</div>
		</div>
	</section>
<?php include("foot.inc"); ?>