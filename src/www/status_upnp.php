<?php
/*
	Copyright (C) 2014-2015 Deciso B.V.
	Copyright (C) 2010 Seth Mos <seth.mos@dds.nl>.
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

if ($_POST) {
	if ($_POST['clear'] == "Clear") {
		upnp_action('restart');
		$savemsg = gettext("Rules have been cleared and the daemon restarted");
	}
}

$rdr_entries = array();
exec("/sbin/pfctl -aminiupnpd -sn", $rdr_entries, $pf_ret);

$now = time();
$year = date("Y");

$pgtitle = array(gettext('Status'), gettext('Universal Plug and Play'));
$shortcut_section = "upnp";
include("head.inc");
?>
<body>
<?php include("fbegin.inc"); ?>


<section class="page-content-main">
	<div class="container-fluid">

        <div class="row">

			<?php if (isset($savemsg)) print_info_box($savemsg); ?>


            <section class="col-xs-12">
                <div class="content-box">

	                <?php if(!$config['installedpackages'] || !$config['installedpackages']['miniupnpd']['config'][0]['iface_array'] || !$config['installedpackages']['miniupnpd']['config'][0]['enable']): ?>
				<header class="content-box-head container-fluid">
				        <h3><?php echo gettext("UPnP is currently disabled."); ?></h3>
				    </header>

	                <? else: ?>

                    <div class="table-responsive">

                        <table class="table table-striped table-sort sortable">
							<tr>
						      <td width="10%" class="listhdrr"><?=gettext("Port");?></td>
						      <td width="10%" class="listhdrr"><?=gettext("Protocol");?></td>
						      <td width="20%" class="listhdrr"><?=gettext("Internal IP");?></td>
						      <td width="10%" class="listhdrr"><?=gettext("Int. Port");?></td>
						      <td width="50%" class="listhdr"><?=gettext("Description");?></td>
							</tr>
							<?php $i = 0; foreach ($rdr_entries as $rdr_entry) {
								if (preg_match("/on (.*) inet proto (.*) from any to any port = (.*) keep state label \"(.*)\" rtable [0-9] -> (.*) port (.*)/", $rdr_entry, $matches))
								$rdr_proto = $matches[2];
								$rdr_port = $matches[3];
								$rdr_label =$matches[4];
								$rdr_ip = $matches[5];
								$rdr_iport = $matches[6];
							?>
						    <tr>
						      <td class="listlr">
							<?php print $rdr_port;?>
						      </td>
						      <td class="listlr">
							<?php print $rdr_proto;?>
						      </td>
						      <td class="listlr">
							<?php print $rdr_ip;?>
						      </td>
						      <td class="listlr">
							<?php print $rdr_iport;?>
						      </td>
						      <td class="listlr">
							<?php print $rdr_label;?>
						      </td>
						    </tr>
						    <?php $i++; }?>
						  </table>
                    </div>

					<form action="status_upnp.php" method="post">
					  <input type="submit" name="clear" id="clear" class="btn btn-primary" value="<?=gettext("Clear");?>" /> <?=gettext("all currently connected sessions");?>.
					</form>
					<? endif; ?>

                </div>
            </section>
        </div>
	</div>
</section>

<?php include("foot.inc"); ?>
