<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.
    Copyright (C) 2004-2012 Scott Ullrich <sullrich@gmail.com>
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

ini_set('max_execution_time', '0');

$shortcut_section = "upnp";

require_once("guiconfig.inc");
require_once("services.inc");
require_once("interfaces.inc");

function upnp_validate_ip($ip, $check_cdir) {
	/* validate cidr */
	$ip_array = array();
	if($check_cdir)	{
		$ip_array = explode('/', $ip);
		if(count($ip_array) == 2) {
			if($ip_array[1] < 1 || $ip_array[1] > 32)
				return false;
		} else
			if(count($ip_array) != 1)
				return false;
	} else
		$ip_array[] = $ip;

	/* validate ip */
	if (!is_ipaddr($ip_array[0]))
		return false;
	return true;
}




function upnp_validate_port($port) {
	foreach(explode('-', $port) as $sub)
		if($sub < 0 || $sub > 65535)
			return false;
	return true;
}

function validate_form_miniupnpd($post, &$input_errors) {
	if(!empty($post['enable']) && (empty($post['enable_upnp']) && empty($post['enable_natpmp'])))
		$input_errors[] = gettext('At least one of \'UPnP\' or \'NAT-PMP\' must be allowed');
	if($post['iface_array'])
		foreach($post['iface_array'] as $iface) {
			if($iface == 'wan')
				$input_errors[] = gettext('It is a security risk to specify WAN in the \'Interface\' field');
			elseif ($iface == $post['ext_iface'])
				$input_errors[] = gettext('You cannot select the external interface as an internal interface.');
		}
	if(!empty($post['overridewanip']) && !upnp_validate_ip($post['overridewanip'],false))
		$input_errors[] = gettext('You must specify a valid ip address in the \'Override WAN address\' field');
	if((!empty($post['download']) && empty($post['upload'])) || (!empty($post['upload']) && empty($post['download'])))
		$input_errors[] = gettext('You must fill in both \'Maximum Download Speed\' and \'Maximum Upload Speed\' fields');
	if(!empty($post['download']) && $post['download'] <= 0)
		$input_errors[] = gettext('You must specify a value greater than 0 in the \'Maximum Download Speed\' field');
	if(!empty($post['upload']) && $post['upload'] <= 0)
		$input_errors[] = gettext('You must specify a value greater than 0 in the \'Maximum Upload Speed\' field');

	/* user permissions validation */
	for($i=1; $i<=4; $i++) {
		if(!empty($post["permuser{$i}"])) {
			$perm = explode(' ',$post["permuser{$i}"]);
			/* should explode to 4 args */
			if(count($perm) != 4) {
				$input_errors[] = sprintf(gettext("You must follow the specified format in the 'User specified permissions %s' field"), $i);
			} else {
				/* must with allow or deny */
				if(!($perm[0] == 'allow' || $perm[0] == 'deny'))
					$input_errors[] = sprintf(gettext("You must begin with allow or deny in the 'User specified permissions %s' field"), $i);
				/* verify port or port range */
				if(!upnp_validate_port($perm[1]) || !upnp_validate_port($perm[3]))
					$input_errors[] = sprintf(gettext("You must specify a port or port range between 0 and 65535 in the 'User specified permissions %s' field"), $i);
				/* verify ip address */
				if(!upnp_validate_ip($perm[2],true))
					$input_errors[] = sprintf(gettext("You must specify a valid ip address in the 'User specified permissions %s' field"), $i);
			}
		}
	}
}



/* return a fieldname that is safe for xml usage */
function xml_safe_fieldname($fieldname) {
	$replace = array('/', '-', ' ', '!', '@', '#', '$', '%', '^', '&', '*', '(', ')',
			 '_', '+', '=', '{', '}', '[', ']', '|', '/', '<', '>', '?',
			 ':', ',', '.', '\'', '\\'
		);
	return strtolower(str_replace($replace, "", $fieldname));
}

function get_pkg_interfaces_select_source($include_localhost=false) {
	$interfaces = get_configured_interface_with_descr();
	$ssifs = array();
	foreach ($interfaces as $iface => $ifacename) {
		$tmp["name"]  = $ifacename;
		$tmp["value"] = $iface;
		$ssifs[] = $tmp;
	}
	if ($include_localhost) {
		$tmp["name"]  = "Localhost";
		$tmp["value"] = "lo0";
		$ssifs[] = $tmp;
	}
	return $ssifs;
}

global $listtags;
$listtags = array_flip(array('build_port_path', 'onetoone', 'queue', 'rule', 'servernat', 'alias', 'additional_files_needed', 'tab', 'menu', 'rowhelperfield', 'service', 'step', 'package', 'columnitem', 'option', 'item', 'field', 'package', 'file'));
$pkg = parse_xml_config_raw('/usr/local/pkg/miniupnpd.xml', 'packagegui', false);

$name         = $pkg['name'];
$title        = $pkg['title'];
$pgtitle      = $title;

if($config['installedpackages'] && !is_array($config['installedpackages'][xml_safe_fieldname($pkg['name'])]['config']))
	$config['installedpackages'][xml_safe_fieldname($pkg['name'])]['config'] = array();

if ($config['installedpackages'] && (count($config['installedpackages'][xml_safe_fieldname($pkg['name'])]['config']) > 0)
	&& ($config['installedpackages'][xml_safe_fieldname($pkg['name'])]['config'][0] == ""))
	array_shift($config['installedpackages'][xml_safe_fieldname($pkg['name'])]['config']);

$a_pkg = &$config['installedpackages'][xml_safe_fieldname($pkg['name'])]['config'];

global $config;

if ($_POST) {
	$firstfield = '';
	$rows = 0;

	$input_errors = array();
	$reqfields = array();
	$reqfieldsn = array();

	foreach ($pkg['fields']['field'] as $field) {
		if (($field['type'] == 'input') && isset($field['required'])) {
			if ($field['fieldname']) {
				$reqfields[] = $field['fieldname'];
			}
			if ($field['fielddescr']) {
				$reqfieldsn[] = $field['fielddescr'];
			}
		}
	}

	do_input_validation($_POST, $reqfields, $reqfieldsn, $input_errors);
	validate_form_miniupnpd($_POST, $input_errors);

	if (!$input_errors) {
		$pkgarr = array();
		foreach ($pkg['fields']['field'] as $fields) {
			$fieldvalue = null;
			$fieldname = null;

			if (isset($fields['fieldname'])) {
				$fieldname = $fields['fieldname'];
			}

			if ($fieldname == 'interface_array') {
				$fieldvalue = $_POST[$fieldname];
			} elseif (isset($_POST[$fieldname]) && is_array($_POST[$fieldname])) {
				$fieldvalue = implode(',', $_POST[$fieldname]);
			} else {
				if (isset($_POST[$fieldname])) {
					$fieldvalue = trim($_POST[$fieldname]);
				}
			}

			if ($fieldname) {
				$pkgarr[$fieldname] = $fieldvalue;
			}
		}

		$a_pkg[] = $pkgarr;

		write_config(gettext('Modified Universal Plug and Play settings.'));
		sync_package_miniupnpd();
	} else {
		$get_from_post = true;
	}
}

include("head.inc");

?>

<body>

<?php include("fbegin.inc"); ?>

<script type="text/javascript" src="/javascript/autosuggest.js"></script>
<script type="text/javascript" src="/javascript/suggestions.js"></script>

<?php if($pkg['fields']['field'] <> "") { ?>
<script type="text/javascript">
//<![CDATA[
	//Everything inside it will load as soon as the DOM is loaded and before the page contents are loaded
	jQuery(document).ready(function() {

		//Sortable function
		jQuery('#mainarea table tbody').sortable({
			items: 'tr.sortable',
			cursor: 'move',
			distance: 10,
			opacity: 0.8,
			helper: function(e,ui){
				ui.children().each(function(){
					jQuery(this).width(jQuery(this).width());
				});
			return ui;
			},
		});

		//delete current line jQuery function
		jQuery('#maintable td .delete').live('click', function() {
			//do not remove first line
			if (jQuery("#maintable tr").length > 2){
				jQuery(this).parent().parent().remove();
				return false;
			}
	    });

		//add new line jQuery function
		jQuery('#mainarea table .add').click(function() {
			//get table size and assign as new id
			var c_id=jQuery("#maintable tr").length;
			var new_row=jQuery("table#maintable tr:last").html().replace(/(name|id)="(\w+)(\d+)"/g,"$1='$2"+c_id+"'");
			//apply new id to created line rowhelperid
			jQuery("table#maintable tr:last").after("<tr>"+new_row+"<\/tr>");
			return false;
	    });
		// Call enablechange function
		enablechange();
	});

	function enablechange() {
	<?php
	foreach ($pkg['fields']['field'] as $field) {
		if (isset($field['enablefields']) or isset($field['checkenablefields'])) {
			if (isset($field['fieldname'])) {
				$fieldname = $field['fieldname'];
			} else {
				$fieldname = "";
			}
			echo "\tif (jQuery('form[name=\"iform\"] input[name=\"{$fieldname}\"]').prop('checked') == false) {\n";

			if (isset($field['enablefields'])) {
				foreach (explode(',', $field['enablefields']) as $enablefield) {
					echo "\t\tif (jQuery('form[name=\"iform\"] input[name=\"{$enablefield}\"]').length > 0) {\n";
					echo "\t\t\tjQuery('form[name=\"iform\"] input[name=\"{$enablefield}\"]').prop('disabled',true);\n";
					echo "\t\t}\n";
				}
			}

			if (isset($field['checkenablefields'])) {
				foreach (explode(',', $field['checkenablefields']) as $checkenablefield) {
					echo "\t\tif (jQuery('form[name=\"iform\"] input[name=\"{$checkenablefield}\"]').length > 0) {\n";
					echo "\t\t\tjQuery('form[name=\"iform\"] input[name=\"{$checkenablefield}\"]').prop('checked',true);\n";
					echo "\t\t}\n";
				}
			}

			echo "\t}\n\telse {\n";

			if (isset($field['enablefields'])) {
				foreach (explode(',', $field['enablefields']) as $enablefield) {
					echo "\t\tif (jQuery('form[name=\"iform\"] input[name=\"{$enablefield}\"]').length > 0) {\n";
					echo "\t\t\tjQuery('form[name=\"iform\"] input[name=\"{$enablefield}\"]').prop('disabled',false);\n";
					echo "\t\t}\n";
				}
			}

			if (isset($field['checkenablefields'])) {
				foreach(explode(',', $field['checkenablefields']) as $checkenablefield) {
					echo "\t\tif (jQuery('form[name=\"iform\"] input[name=\"{$checkenablefield}\"]').length > 0) {\n";
					echo "\t\t\tjQuery('form[name=\"iform\"] input[name=\"{$checkenablefield}\"]').prop('checked',false);\n";
					echo "\t\t}\n";
				}
			}

			echo "\t}\n";
		}
	}
	?>
}
//]]>
</script>
<?php } ?>
<script type="text/javascript" src="javascript/domTT/domLib.js"></script>
<script type="text/javascript" src="javascript/domTT/domTT.js"></script>
<script type="text/javascript" src="javascript/domTT/behaviour.js"></script>
<script type="text/javascript" src="javascript/domTT/fadomatic.js"></script>
<script type="text/javascript" src="/javascript/row_helper_dynamic.js"></script>


	<section class="page-content-main">

		<div class="container-fluid">

			<div class="row">
				<?php if (!empty($input_errors)) print_input_errors($input_errors); ?>

			    <section class="col-xs-12">

				<div class="content-box">

					<form name="iform" action="services_upnp.php" method="post">

						<div class="table-responsive">
							<table class="table table-striped table-sort">
<?php
	$cols = 0;
	$savevalue = gettext("Save");
	$pkg_buttons = "";

	foreach ($pkg['fields']['field'] as $pkga) {
		if (isset($pkga['fieldname'])) {
			$fieldname = $pkga['fieldname'];
		} else {
			$fieldname = "";
		}
		if (isset($pkga['description'])) {
			$description = $pkga['description'];
		} else {
			$description = "";
		}
		$colspan = '';

		if ($pkga['type'] == "sorting")
			continue;

		if ($pkga['type'] == "listtopic") {
			$input .= "<tr id='tr_{$fieldname}'><td colspan=\"2\" class=\"listtopic\"><strong>{$pkga['name']}</strong></td></tr>\n";
			echo $input;
			continue;
		}

		if(isset($pkga['combinefields']) && $pkga['combinefields']=="begin"){
			$input="<tr valign='top' id='tr_{$pkga['fieldname']}'>";
			echo $input;

		$size = "";
		if (isset($pkga['dontdisplayname'])){
			$input="";
			if(!isset($pkga['combinefields']))
				$input .= "<tr valign='top' id='tr_{$pkga['fieldname']}'>";
			if(isset($pkga['usecolspan2']))
				$colspan="colspan='2'";
			else
				$input .= "<td width='22%' class='vncell{$req}'>&nbsp;</td>";
				$advanced .= $input;
				$adv_filed_count++;
				}
			else
				echo $input;
			}
		else if (!isset($pkga['placeonbottom'])){
			unset($req);
			if (isset($pkga['required'])) {
				$req = 'req';
			} else {
				$req = '';
			}
			$input= "<tr><td valign='top' width=\"22%\" class=\"vncell{$req}\">";
			$input .= fixup_string($pkga['fielddescr']);
			$input .= "</td>";
			echo $input;
		}
		if(isset($pkga['combinefields']) && $pkga['combinefields']=="begin"){
			$input="<td class=\"vncell\"><table summary=\"advanced\">";
			echo $input;
			}

		$class=(isset($pkga['combinefields']) ? '' : 'class="vtable"');
		if (!isset($pkga['placeonbottom'])){
			$input="<td valign='top' {$colspan} {$class}>";
			echo $input;
		}

		// if user is editing a record, load in the data.
		if (isset($get_from_post)) {
			$value = $_POST[$fieldname];
			if (is_array($value)) $value = implode(',', $value);
		} else {
			$value = $pkga['default_value'];
		}
		switch($pkga['type']){
			case "input":
				$size = (!empty($pkga['size']) ? " size='{$pkga['size']}' " : "");
				$input = "<input {$size} id='{$pkga['fieldname']}' name='{$pkga['fieldname']}' class='formfld unknown' type='text' value=\"" . htmlspecialchars($value) ."\" />\n";
				$input .= "<br />" . fixup_string($description) . "\n";
				echo $input;
				break;

			case "select_source":
				$fieldname = $pkga['fieldname'];
				if (isset($pkga['multiple'])) {
					$multiple = 'multiple="multiple"';
					$items = explode(',', $value);
					$fieldname .= "[]";
				} else {
					$multiple = '';
					$items = array($value);
				}
				$size = (isset($pkga['size']) ? "size=\"{$pkga['size']}\"" : '');
				$onchange = (isset($pkga['onchange']) ? "onchange=\"{$pkga['onchange']}\"" : '');
				$input = "<select id='{$pkga['fieldname']}' {$multiple} {$size} {$onchange} name=\"{$fieldname}\">\n";

				echo $input;
				$source_url = $pkga['source'];
				eval("\$pkg_source_txt = &$source_url;");
				$input="";
				#check if show disable option is present on xml
				if(isset($pkga['show_disable_value'])){
					array_push($pkg_source_txt, array(($pkga['source_name']? $pkga['source_name'] : $pkga['name'])=> $pkga['show_disable_value'],
													  ($pkga['source_value']? $pkga['source_value'] : $pkga['value'])=> $pkga['show_disable_value']));
					}
				foreach ($pkg_source_txt as $opt) {
					$source_name =($pkga['source_name']? $opt[$pkga['source_name']] : $opt[$pkga['name']]);
					$source_value =($pkga['source_value'] ? $opt[$pkga['source_value']] : $opt[$pkga['value']]);
					$selected = (in_array($source_value, $items)? 'selected="selected"' : '' );
					$input  .= "\t<option value=\"{$source_value}\" $selected>{$source_name}</option>\n";
					}
				$input .= "</select>\n<br />\n" . fixup_string($description) . "\n";
				echo $input;
				break;

			case "checkbox":
				$checkboxchecked =($value == "on" ? " checked=\"checked\"" : "");
				$onchange = (isset($pkga['onchange']) ? "onchange=\"{$pkga['onchange']}\"" : '');
				if (isset($pkga['enablefields']) || isset($pkga['checkenablefields']))
					$onclick = ' onclick="javascript:enablechange();"';
				$input = "<input id='{$pkga['fieldname']}' type='checkbox' name='{$pkga['fieldname']}' {$checkboxchecked} {$onclick} {$onchange} />\n";
				$input .= "<br />" . fixup_string($description) . "\n";

				echo $input;
				break;
		    }
		#check typehint value
		if(isset($pkga['typehint']))
			echo " " . $pkga['typehint'];
		#check combinefields options
	if (isset($pkga['combinefields'])){
		$input="</td>";
			if ($pkga['combinefields']=="end")
			$input.="</table></td></tr>";
		}
	else{
			$input= "</td></tr>";
			if(isset($pkga['usecolspan2']))
				$input.= "</tr><br />";
		}
		echo "{$input}\n";
		}

	?>
  <tr>
    <td width="22%" valign="top">&nbsp;</td>
    <td width="78%">
    <div id="buttons">
		<?php echo "<input name='Submit' type='submit' class='btn btn-primary formbtn' value=\"" . htmlspecialchars($savevalue) . "\" />\n{$pkg_buttons}\n"; ?>
	</div>
    </td>
  </tr>

</table>
</div>
					</form>
				</div>
			    </section>
			</div>
		</div>
	</section>

<?php include("foot.inc"); ?>

<?php
/*
 * ROW Helpers function
 */
function display_row($trc, $value, $fieldname, $type, $rowhelper, $size) {
	global $text, $config;
	echo "<td>\n";
	switch($type){
		case "input":
			echo "<input size='{$size}' name='{$fieldname}{$trc}' id='{$fieldname}{$trc}' class='formfld unknown' type='text' value=\"" . htmlspecialchars($value) . "\" />\n";
			break;
		case "checkbox":
			echo "<input size='{$size}' type='checkbox' id='{$fieldname}{$trc}' name='{$fieldname}{$trc}' value='ON' ".($value?"CHECKED":"")." />\n";
			break;
		case "password":
			echo "<input size='{$size}' type='password' id='{$fieldname}{$trc}' name='{$fieldname}{$trc}' class='formfld pwd' value=\"" . htmlspecialchars($value) . "\" />\n";
			break;
		case "textarea":
			echo "<textarea rows='2' cols='12' id='{$fieldname}{$trc}' class='formfld unknown' name='{$fieldname}{$trc}'>{$value}</textarea>\n";
		case "select":
			echo "<select style='height:22px;'  id='{$fieldname}{$trc}' name='{$fieldname}{$trc}' {$title}>\n";
			foreach($rowhelper['options']['option'] as $rowopt) {
				$text .= "<option value='{$rowopt['value']}'>{$rowopt['name']}</option>";
				echo "<option value='{$rowopt['value']}'".($rowopt['value'] == $value?" selected=\"selected\"":"").">{$rowopt['name']}</option>\n";
				}
			echo "</select>\n";
			break;
		case "interfaces_selection":
			$size = ($size ? "size=\"{$size}\"" : '');
			$multiple = '';
			if (isset($rowhelper['multiple'])) {
				$fieldname .= '[]';
				$multiple = "multiple=\"multiple\"";
			}
			echo "<select style='height:22px;' id='{$fieldname}{$trc}' name='{$fieldname}{$trc}' {$size} {$multiple}>\n";
			$ifaces = get_configured_interface_with_descr();
			$additional_ifaces = $rowhelper['add_to_interfaces_selection'];
			if (!empty($additional_ifaces))
				$ifaces = array_merge($ifaces, explode(',', $additional_ifaces));
			if(is_array($value))
				$values = $value;
			else
				$values  =  explode(',',  $value);
			$ifaces["lo0"] = "loopback";
			echo "<option><name></name><value></value></option>/n";
			foreach($ifaces as $ifname => $iface) {
				$text .="<option value=\"{$ifname}\">$iface</option>";
				echo "<option value=\"{$ifname}\" ".(in_array($ifname, $values) ? 'selected="selected"' : '').">{$iface}</option>\n";
				}
			echo "</select>\n";
			break;
		case "select_source":
			echo "<select style='height:22px;' id='{$fieldname}{$trc}' name='{$fieldname}{$trc}'>\n";
			if(isset($rowhelper['show_disable_value']))
				echo "<option value='{$rowhelper['show_disable_value']}'>{$rowhelper['show_disable_value']}</option>\n";
			$source_url = $rowhelper['source'];
			eval("\$pkg_source_txt = &$source_url;");
			foreach($pkg_source_txt as $opt) {
				$source_name = ($rowhelper['source_name'] ? $opt[$rowhelper['source_name']] : $opt[$rowhelper['name']]);
				$source_value = ($rowhelper['source_value'] ? $opt[$rowhelper['source_value']] : $opt[$rowhelper['value']]);
				$text .= "<option value='{$source_value}'>{$source_name}</option>";
				echo "<option value='{$source_value}'".($source_value == $value?" selected=\"selected\"":"").">{$source_name}</option>\n";
				}
			echo "</select>\n";
			break;
		}
	echo "</td>\n";
}

function fixup_string($string) {
	global $config;
	// fixup #1: $myurl -> http[s]://ip_address:port/
	$https = "";
	if (!empty($config['system']['webguiport'])) {
		$port = $config['system']['webguiport'];
	} else {
		$port = null;
	}
	if($port <> "443" and $port <> "80")
		$urlport = ":" . $port;
	else
		$urlport = "";

	if($config['system']['webgui']['protocol'] == "https") $https = "s";
	$myurl = "http" . $https . "://" . getenv("HTTP_HOST") . $urlport;
	$newstring = str_replace("\$myurl", $myurl, $string);
	$string = $newstring;
	// fixup #2: $wanip
	$curwanip = get_interface_ip();
	$newstring = str_replace("\$wanip", $curwanip, $string);
	$string = $newstring;
	// fixup #3: $lanip
	$lancfg = $config['interfaces']['lan'];
	$lanip = $lancfg['ipaddr'];
	$newstring = str_replace("\$lanip", $lanip, $string);
	$string = $newstring;
	// fixup #4: fix'r'up here.
	return $newstring;
}

/* Return html div fields */
function display_advanced_field($fieldname) {
	$div = "<div id='showadv_{$fieldname}'>\n";
	$div .= "<input type='button' onclick='show_{$fieldname}()' value='" . gettext("Advanced") . "' /> - " . gettext("Show advanced option") ."</div>\n";
	$div .= "<div id='show_{$fieldname}' style='display:none'>\n";
	return $div;
}
