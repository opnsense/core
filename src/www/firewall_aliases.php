<?php

/*
	Copyright (C) 2014 Deciso B.V.
	Copyright (C) 2004 Scott Ullrich
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

require_once("guiconfig.inc");
require_once("filter.inc");

function find_alias_reference($section, $field, $origname, &$is_alias_referenced, &$referenced_by) {
	global $config;
	if(!$origname || $is_alias_referenced)
		return;

	$sectionref = &$config;
	foreach($section as $sectionname) {
		if(is_array($sectionref) && isset($sectionref[$sectionname]))
			$sectionref = &$sectionref[$sectionname];
		else
			return;
	}

	if(is_array($sectionref)) {
		foreach($sectionref as $itemkey => $item) {
			$fieldfound = true;
			$fieldref = &$sectionref[$itemkey];
			foreach($field as $fieldname) {
				if(is_array($fieldref) && isset($fieldref[$fieldname]))
					$fieldref = &$fieldref[$fieldname];
				else {
					$fieldfound = false;
					break;
				}
			}
			if($fieldfound && $fieldref == $origname) {
				$is_alias_referenced = true;
				if(is_array($item))
					$referenced_by = $item['descr'];
				break;
			}
		}
	}
}


if (!isset($config['aliases'])) {
        $config['aliases'] = array();
}
if (!isset($config['aliases']['alias'])) {
	$config['aliases']['alias'] = array();
}
$a_aliases = &$config['aliases']['alias'];

// determine selected tab
$selected_tab = htmlspecialchars(($_REQUEST['tab'] == "" ? "ip" : preg_replace("/\W/","",$_REQUEST['tab'])));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['apply'])) {
		/* reload all components that use aliases */
		filter_configure();
		$savemsg = get_std_save_message();
		clear_subsystem_dirty('aliases');
	} elseif (isset($_POST['act']) && $_POST['act'] == "del") {
    if (isset($_POST['id']) && isset($a_aliases[$_POST['id']])) {
      // perform validation
      /* make sure rule is not being referenced by any nat or filter rules */
		$is_alias_referenced = false;
		$referenced_by = false;
		$alias_name = $a_aliases[$_POST['id']]['name'];
		// Firewall rules
		find_alias_reference(array('filter', 'rule'), array('source', 'address'), $alias_name, $is_alias_referenced, $referenced_by);
		find_alias_reference(array('filter', 'rule'), array('destination', 'address'), $alias_name, $is_alias_referenced, $referenced_by);
		find_alias_reference(array('filter', 'rule'), array('source', 'port'), $alias_name, $is_alias_referenced, $referenced_by);
		find_alias_reference(array('filter', 'rule'), array('destination', 'port'), $alias_name, $is_alias_referenced, $referenced_by);
		// NAT Rules
		find_alias_reference(array('nat', 'rule'), array('source', 'address'), $alias_name, $is_alias_referenced, $referenced_by);
		find_alias_reference(array('nat', 'rule'), array('source', 'port'), $alias_name, $is_alias_referenced, $referenced_by);
		find_alias_reference(array('nat', 'rule'), array('destination', 'address'), $alias_name, $is_alias_referenced, $referenced_by);
		find_alias_reference(array('nat', 'rule'), array('destination', 'port'), $alias_name, $is_alias_referenced, $referenced_by);
		find_alias_reference(array('nat', 'rule'), array('target'), $alias_name, $is_alias_referenced, $referenced_by);
		find_alias_reference(array('nat', 'rule'), array('local-port'), $alias_name, $is_alias_referenced, $referenced_by);
		// NAT 1:1 Rules
		//find_alias_reference(array('nat', 'onetoone'), array('external'), $alias_name, $is_alias_referenced, $referenced_by);
		//find_alias_reference(array('nat', 'onetoone'), array('source', 'address'), $alias_name, $is_alias_referenced, $referenced_by);
		find_alias_reference(array('nat', 'onetoone'), array('destination', 'address'), $alias_name, $is_alias_referenced, $referenced_by);
		// NAT Outbound Rules
		find_alias_reference(array('nat', 'advancedoutbound', 'rule'), array('source', 'network'), $alias_name, $is_alias_referenced, $referenced_by);
		find_alias_reference(array('nat', 'advancedoutbound', 'rule'), array('sourceport'), $alias_name, $is_alias_referenced, $referenced_by);
		find_alias_reference(array('nat', 'advancedoutbound', 'rule'), array('destination', 'address'), $alias_name, $is_alias_referenced, $referenced_by);
		find_alias_reference(array('nat', 'advancedoutbound', 'rule'), array('dstport'), $alias_name, $is_alias_referenced, $referenced_by);
		find_alias_reference(array('nat', 'advancedoutbound', 'rule'), array('target'), $alias_name, $is_alias_referenced, $referenced_by);
		// Alias in an alias
		find_alias_reference(array('aliases', 'alias'), array('address'), $alias_name, $is_alias_referenced, $referenced_by);
		// Load Balancer
		find_alias_reference(array('load_balancer', 'lbpool'),         array('port'), $alias_name, $is_alias_referenced, $referenced_by);
		find_alias_reference(array('load_balancer', 'virtual_server'), array('port'), $alias_name, $is_alias_referenced, $referenced_by);
		// Static routes
		find_alias_reference(array('staticroutes', 'route'), array('network'), $alias_name, $is_alias_referenced, $referenced_by);
		if($is_alias_referenced) {
			$savemsg = sprintf(gettext("Cannot delete alias. Currently in use by %s"), $referenced_by);
		} else {
			unset($a_aliases[$_POST['id']]);
			if (write_config()) {
				filter_configure();
				mark_subsystem_dirty('aliases');
			}
				header("Location: firewall_aliases.php?tab=" . $selected_tab);
			exit;
		}
    }
  }
}


$pgtitle = array(gettext("Firewall"), gettext("Aliases"));
$shortcut_section = "aliases";
$main_buttons = array(
	array('href'=>'firewall_aliases_edit.php?tab='.$selected_tab, 'label'=>gettext("Add a new alias")),
	array('href'=>'firewall_aliases_import.php', 'label'=>gettext("Bulk import aliases from list")),
);


include("head.inc");
?>
<body>
<script type="text/javascript">
$( document ).ready(function() {
	// link delete buttons
	$(".act_delete").click(function(){
		var id = $(this).attr("id").split('_').pop(-1);
		BootstrapDialog.show({
				type:BootstrapDialog.TYPE_INFO,
				title: "<?= gettext("Aliasses");?>",
				message: "<?=gettext("Do you really want to delete this alias? All elements that still use it will become invalid (e.g. filter rules)!");?>",
				buttons: [{
                label: "<?= gettext("No");?>",
                action: function(dialogRef) {
                    dialogRef.close();
                }}, {
									label: "<?= gettext("Yes");?>",
									action: function(dialogRef) {
										$("#delId").val(id);
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
<?php if (is_subsystem_dirty('aliases')): ?>
<?php print_info_box_np(gettext("The alias list has been changed.") . "<br />" . gettext("You must apply the changes in order for them to take effect."));?>
<?php endif; ?>
			  <section class="col-xs-12">
<?php
							$tab_array = array();
							$tab_array[] = array(gettext("IP"),($selected_tab=="ip" ? true : ($selected_tab=="host" ? true : ($selected_tab == "network" ? true : false))), "/firewall_aliases.php?tab=ip");
							$tab_array[] = array(gettext("Ports"), ($selected_tab=="port"? true : false), "/firewall_aliases.php?tab=port");
							$tab_array[] = array(gettext("URLs"), ($selected_tab=="url"? true : false), "/firewall_aliases.php?tab=url");
							$tab_array[] = array(gettext("All"), ($selected_tab=="all"? true : false), "/firewall_aliases.php?tab=all");
							display_top_tabs($tab_array);
?>
					<div class="tab-content content-box col-xs-12">
            <form action="firewall_aliases.php" method="post" name="iform" id="iform">
              <input type="hidden" name="tab" value="<?=$selected_tab;?>" />
							<input type="hidden" name="id" value="" id="delId"/>
							<input type="hidden" name="act" value="del"/>
                <div class="table-responsive">
                  <table class="table table-striped">
										<tr>
											<td><?=gettext("Name"); ?></td>
											<td><?=gettext("Values"); ?></td>
											<td><?=gettext("Description"); ?></td>
											<td>&nbsp;</td>
										</tr>
<?php
										asort($a_aliases);
										foreach ($a_aliases as $i=> $alias){
											$show_alias = false;
											switch ($selected_tab){
											case "all":
												$show_alias= true;
												break;
											case "ip":
											case "host":
											case "network":
												if (preg_match("/(host|network)/",$alias["type"]))
													$show_alias= true;
												break;
											case "url":
												if (preg_match("/(url)/i",$alias["type"]))
													$show_alias= true;
												break;
											case "port":
												if($alias["type"] == "port")
													$show_alias= true;
												break;
											}
											if ($show_alias ){
										?>
										<tr>
											<td ondblclick="document.location='firewall_aliases_edit.php?id=<?=$i;?>';">
												<?=htmlspecialchars($alias['name']);?>
											</td>
<?php
                        $alias_values = '';
											  if (!empty($alias["url"])) {
												    $alias_values = $alias["url"];
											  } elseif(isset($alias["aliasurl"])) {
                            $alias_values = implode(", ", array_slice($alias['aliasurl'], 0, 5));
													  if(count($alias['aliasurl']) > 5) {
														    $alias_values .= "...";
													  }
											  } else {
												    $alias_values = implode(", ", array_slice(explode(" ", $alias['address']), 0, 5));
												    if(count(explode(" ", $alias['address'])) > 5) {
													      $alias_values .= "...";
												    }
											}
?>
                      <td ondblclick="document.location='firewall_aliases_edit.php?id=<?=$i;?>';">
                        <?= htmlspecialchars($alias_values); ?>
											</td>
											<td ondblclick="document.location='firewall_aliases_edit.php?id=<?=$i;?>';">
												<?=htmlspecialchars($alias['descr']);?>
											</td>
											<td>
                        <a href="firewall_aliases_edit.php?id=<?=$i;?>" title="<?=gettext("Edit alias"); ?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-pencil"></span></a>
                        <a id="del_<?=$i;?>" title="<?=gettext("delete alias"); ?>" class="act_delete btn btn-default btn-xs"><span class="glyphicon glyphicon-remove"></span></a>
											</td>
										</tr>
<?php
											} // if ($show_alias)
										} // foreach
?>
						      </table>
                  <div class="container-fluid">
								    <span class="text-danger">
                      <strong><?=gettext("Note:"); ?><br />
                      </strong></span>
                    </span>
                    <?=gettext("Aliases act as placeholders for real hosts, networks or ports. They can be used to minimize the number of changes that have to be made if a host, network or port changes. You can enter the name of an alias instead of the host, network or port in all fields that have a red background. The alias will be resolved according to the list above. If an alias cannot be resolved (e.g. because you deleted it), the corresponding element (e.g. filter/NAT/shaper rule) will be considered invalid and skipped."); ?>
							    </div>
	          </form>
					</div>
			  </section>
			</div>
	   </div>
	</section>
<?php include("foot.inc"); ?>
