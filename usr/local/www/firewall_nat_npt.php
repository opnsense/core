<?php
/* $Id$ */
/*
	firewall_nat_npt.php
	part of pfSense (https://www.pfsense.org)

	Copyright (C) 2011 Seth Mos <seth.mos@dds.nl>.
	All rights reserved.

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
/*
	pfSense_MODULE:	nat
*/

##|+PRIV
##|*IDENT=page-firewall-nat-npt
##|*NAME=Firewall: NAT: NPT page
##|*DESCR=Allow access to the 'Firewall: NAT: NPT' page.
##|*MATCH=firewall_nat_npt.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("filter.inc");
require_once("shaper.inc");

if (!is_array($config['nat']['npt'])) {
	$config['nat']['npt'] = array();
}
$a_npt = &$config['nat']['npt'];

if ($_POST) {

	$pconfig = $_POST;

	if ($_POST['apply']) {
		$retval = 0;
		$retval |= filter_configure();
		$savemsg = get_std_save_message($retval);

		if ($retval == 0) {
			clear_subsystem_dirty('natconf');
			clear_subsystem_dirty('filter');
		}
	}
}

if ($_GET['act'] == "del") {
	if ($a_npt[$_GET['id']]) {
		unset($a_npt[$_GET['id']]);
		if (write_config())
			mark_subsystem_dirty('natconf');
		header("Location: firewall_nat_npt.php");
		exit;
	}
}

$pgtitle = array(gettext("Firewall"),gettext("NAT"),gettext("NPt"));
include("head.inc");

$main_buttons = array(
	array('label'=>'Add rule', 'href'=>'firewall_nat_npt_edit.php'),
);
?>


<body>
<?php include("fbegin.inc"); ?>
	
	<script type="text/javascript" src="/javascript/row_toggle.js"></script>

	<section class="page-content-main">
		<div class="container-fluid">	
			<div class="row">				
				
				<?php if ($savemsg) print_info_box($savemsg); ?>
				<?php if (is_subsystem_dirty('natconf')): ?>
				<?php print_info_box_np(gettext("The NAT configuration has been changed") . ".<br />" . gettext("You must apply the changes in order for them to take effect."));?><br />
				<?php endif; ?>
				
			    <section class="col-xs-12">    				
    					
					<?php
						$tab_array = array();
						$tab_array[] = array(gettext("Port Forward"), false, "firewall_nat.php");
						$tab_array[] = array(gettext("1:1"), false, "firewall_nat_1to1.php");
						$tab_array[] = array(gettext("Outbound"), false, "firewall_nat_out.php");
						$tab_array[] = array(gettext("NPt"), true, "firewall_nat_npt.php");
						display_top_tabs($tab_array);
					?>
				
					<div class="tab-content content-box col-xs-12">	
    					
    				    <div class="container-fluid">	    					

	                        <form action="firewall_nat_npt.php" method="post" name="iform" id="iform">
	                        	
	                        	<div class="table-responsive">
		                       	 	<table class="table table-striped table-sort">
			                    
							   	 		<tr>
										  <td width="10%" class="listhdrr"><?=gettext("Interface"); ?></td>
								                  <td width="20%" class="listhdrr"><?=gettext("External Prefix"); ?></td>
								                  <td width="15%" class="listhdrr"><?=gettext("Internal prefix"); ?></td>
								                  <td width="30%" class="listhdr"><?=gettext("Description"); ?></td>
								                  <td width="10%" class="list">
								                    
										  </td>
												</tr>
									  <?php 
											$textse = "</span>";
								
											$i = 0; foreach ($a_npt as $natent):
								
											if (isset($natent['disabled']))
												$textss = "<span class=\"gray\">";
											else
												$textss = "<span>"; ?>
								                <tr>
										  <td class="listlr" ondblclick="document.location='firewall_nat_npt_edit.php?id=<?=$i;?>';">
								                  <?php
											echo $textss;
													if (!$natent['interface'])
														echo htmlspecialchars(convert_friendly_interface_to_friendly_descr("wan"));
													else
														echo htmlspecialchars(convert_friendly_interface_to_friendly_descr($natent['interface']));
											echo $textse;
												  ?>
								                  </td>
								                    <?php 
											$source_net = pprint_address($natent['source']);
											$source_cidr = strstr($source_net, '/');
											$destination_net = pprint_address($natent['destination']);
											$destination_cidr = strstr($destination_net, '/');
								                    ?>
								                  <td class="listr" ondblclick="document.location='firewall_nat_npt_edit.php?id=<?=$i;?>';">
								                    <?php 		echo $textss . $destination_net . $textse; ?>
								                  </td>
								                  <td class="listr" ondblclick="document.location='firewall_nat_npt_edit.php?id=<?=$i;?>';">
								                    <?php 		echo $textss . $source_net . $textse; ?>
								                  </td>
								                  <td class="listbg" ondblclick="document.location='firewall_nat_npt_edit.php?id=<?=$i;?>';">
											<?=$textss;?>
								                    <?=htmlspecialchars($natent['descr']);?>&nbsp;
											<?=$textse;?>
								                  </td>
								                  <td class="list nowrap">
								                    <a href="firewall_nat_npt_edit.php?id=<?=$i;?>" title="<?=gettext("edit rule"); ?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-pencil"></span></a><a href="firewall_nat_npt.php?act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this mapping?");?>')" title="<?=gettext("delete rule"); ?>" alt="delete"  class="btn btn-default btn-xs"><span class="glyphicon glyphicon-remove"></span></a>
								                  </td>
								                </tr>
										<?php $i++; endforeach; ?>
								                
								              </table>
	                        	</div>
	                        </form>
    				    </div>
					</div>
			    </section>
			</div>
		</div>
	</section>

<?php include("foot.inc"); ?>