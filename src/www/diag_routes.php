<?php
/*
	Copyritgh (C) 2014 Deciso B.V.
	Copyright (C) 2006 Fernando Lamos
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

include('guiconfig.inc');

if (isset($_REQUEST['isAjax'])) {
	$netstat = "/usr/bin/netstat -rW";
	if (isset($_REQUEST['IPv6'])) {
		$netstat .= " -f inet6";
		echo "IPv6\n";
	} else {
		$netstat .= " -f inet";
		echo "IPv4\n";
	}
	if (!isset($_REQUEST['resolve']))
		$netstat .= " -n";

	if (!empty($_REQUEST['filter']))
		$netstat .= " | /usr/bin/sed -e '1,3d; 5,\$ { /" . escapeshellarg(htmlspecialchars($_REQUEST['filter'])) . "/!d; };'";
	else
		$netstat .= " | /usr/bin/sed -e '1,3d'";

	if (is_numeric($_REQUEST['limit']) && $_REQUEST['limit'] > 0)
		$netstat .= " | /usr/bin/head -n {$_REQUEST['limit']}";

	echo htmlspecialchars_decode(shell_exec($netstat));

	exit;
}

$pgtitle = array(gettext("Diagnostics"),gettext("Routing tables"));
$shortcut_section = "routing";

include('head.inc');

?>
<body>

<?php include("fbegin.inc"); ?>

<script type="text/javascript">
//<![CDATA[

	function update_routes(section) {
		var url = "diag_routes.php";
		var limit = jQuery('#limit option:selected').text();
		var filter = jQuery('#filter').val();
		var params = "isAjax=true&limit=" + limit + "&filter=" + filter;
		if (jQuery('#resolve').is(':checked'))
			params += "&resolve=true";
		if (section == "IPv6")
			params += "&IPv6=true";
		var myAjax =  $.ajax(

			{
				url:url,
				type: 'post',
				data: params,
				success: update_routes_callback
			});
	}

	function update_routes_callback(data, textStatus, transport) {
		// First line contains section
		var responseTextArr = transport.responseText.split("\n");
		var section = responseTextArr.shift();
		var tbody = '';
		var field = '';
		var elements = 8;
		var tr_class = '';

		var thead = '';
		for (var i = 0; i < responseTextArr.length; i++) {
			if (responseTextArr[i] == "")
				continue;
			var tmp = '';
			if (i == 0) {
				tr_class = 'listhdrr';
				tmp += '<tr class="sortableHeaderRowIdentifier">' + "\n";
			} else {
				tr_class = 'listlr';
				tmp += '<tr>' + "\n";
			}
			var j = 0;
			var entry = responseTextArr[i].split(" ");
			for (var k = 0; k < entry.length; k++) {
				if (entry[k] == "")
					continue;
				if (i == 0 && j == (elements - 1))
					tr_class = 'listhdr';
				tmp += '<td class="' + tr_class + '">' + entry[k] + '<\/td>' + "\n";
				if (i > 0)
					tr_class = 'listr';
				j++;
			}
			// The 'Expire' field might be blank
			if (j == (elements - 1))
				tmp += '<td class="listr">&nbsp;<\/td>' + "\n";
			tmp += '<\/tr>' + "\n";
			if (i == 0)
				thead += tmp;
			else
				tbody += tmp;
		}
		jQuery('#' + section + ' > thead').html(thead);
		jQuery('#' + section + ' > tbody').html(tbody);
	}

	function update_all_routes() {
		update_routes("IPv4");
		update_routes("IPv6");
	}

	jQuery(document).ready(function(){setTimeout('update_all_routes()', 3000);});

//]]>
</script>






<section class="page-content-main">
	<div class="container-fluid">
		<div class="row">

			<section class="col-xs-12">

				<?php if (isset($input_errors)) print_input_errors($input_errors); ?>

                <div class="content-box">

                    <header class="content-box-head container-fluid">
				        <h3><?=gettext("Routing tables"); ?></h3>
				    </header>

				    <div class="content-box-main">

						<form action="<?=$_SERVER['REQUEST_URI'];?>" method="post" name="iform" id="iform">
					    <div class="table-responsive">
				        <table class="table table-striped __nomb">
					        <tbody>
						        <tr>
						          <td><?=gettext("Name resolution");?></td>
						          <td><input type="checkbox" class="formfld" id="resolve" name="resolve" value="yes" <?php if ($_POST['resolve'] == 'yes') echo "checked=\"checked\""; ?> />&nbsp;<?=gettext("Enable");?>
										<p class="text-muted"><em><small><?=gettext("Enable this to attempt to resolve names when displaying the tables.");?></small></em></p>
									  </td>
						        </tr>
						        <tr>
						          <td><?=gettext("Number of rows");?></td>
						          <td><select id="limit" name="limit" class="form-control">
										<?php
											foreach (array("10", "50", "100", "200", "500", "1000", gettext("all")) as $item) {
												echo "<option value=\"{$item}\" " . ($item == "100" ? "selected=\"selected\"" : "") . ">{$item}</option>\n";
											}
										?>
										</select>
										<p class="text-muted"><em><small><?=gettext("Select how many rows to display.");?></small></em></p>
									 </td>
						        </tr>
						        <tr>
						          <td><?=gettext("Filter expression");?></td>
						          <td>
							          <input type="text" class="form-control search" name="filter" id="filter" />
										  <p class="text-muted"><em><small><?=gettext("Use a regular expression to filter IP address or hostnames.");?></small></em></p>
						          </td>
						        </tr>
						        <tr>
						          <td>&nbsp;</td>
						          <td>
							          <input type="button" class="btn btn-primary" name="update" onclick="update_all_routes();" value="<?=gettext("Update"); ?>" />
										  <p class="text-muted"><em><small><span class="text-danger"><strong><?=gettext("Note:")?></strong></span> <?=gettext("By enabling name resolution, the query should take a bit longer. You can stop it at any time by clicking the Stop button in your browser.");?></small></em></p>
							      </td>
						        </tr>
					        </tbody>
					    </table>
					    </div>
					    </form>
				    </div>

				</div>
			</section>

			<section class="col-xs-12">

                <div class="content-box">

                    <header class="content-box-head container-fluid">
				        <h3>IPv4</h3>
				    </header>

				     <table class="table table-striped table-sort sortable __nomb" id="IPv4" summary="ipv4 routes">
						<tbody>
							<tr><td class="listhdrr"><?=gettext("Gathering data, please wait...");?></td></tr>
						</tbody>
					</table>
                </div>
			</section>

			<section class="col-xs-12">

                <div class="content-box">

                    <header class="content-box-head container-fluid">
				        <h3>IPv6</h3>
				    </header>
					<table class="table table-striped table-sort sortable __nomb" id="IPv6" summary="IPv6 routes">

						<tbody>
							<tr><td class="listhdrr"><?=gettext("Gathering data, please wait...");?></td></tr>
						</tbody>
					</table>
                </div>
			</section>
		</div>
	</div>
</section>


<?php
include('foot.inc');
?>
