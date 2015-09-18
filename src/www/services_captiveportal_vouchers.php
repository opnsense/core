<?php

/*
	Copyright (C) 2014-2015 Deciso B.V.
	Copyright (C) 2007 Marcel Wiget <mwiget@mac.com>
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

if ($_POST['postafterlogin']) {
    $nocsrf = true;
}

require_once('guiconfig.inc');
require_once('interfaces.inc');
require_once('captiveportal.inc');
require_once("services.inc");
require_once("pfsense-utils.inc");

function voucher_unlink_db($roll)
{
	global $cpzone;

	@unlink("/var/db/voucher_{$cpzone}_used_{$roll}.db");
	@unlink("/var/db/voucher_{$cpzone}_active_{$roll}.db");
}


$referer = (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/services_captiveportal_vouchers.php');

$cpzone = $_GET['zone'];
if (isset($_POST['zone'])) {
        $cpzone = $_POST['zone'];
}

if (empty($cpzone)) {
        header("Location: services_captiveportal_zones.php");
        exit;
}

function generatekey($exponent)
{
    $ret = array();

    /* generate a random 64 bit RSA key pair using the voucher binary */
    $fd = popen(sprintf('/usr/local/bin/voucher -g 64 -e %s', $exponent), 'r');
    if ($fd !== false) {
        $output = fread($fd, 16384);
        pclose($fd);
        list($privkey, $pubkey) = explode("\0", $output);
        $ret['priv'] = $privkey;
        $ret['pub'] = $pubkey;
    }

    return $ret;
}

if (!is_array($config['captiveportal'])) {
        $config['captiveportal'] = array();
}
$a_cp =& $config['captiveportal'];

if (!is_array($config['voucher'])) {
    $config['voucher'] = array();
}

if (empty($a_cp[$cpzone])) {
    log_error("Submission on captiveportal page with unknown zone parameter: " . htmlspecialchars($cpzone));
    header("Location: services_captiveportal_zones.php");
    exit;
}


$pgtitle = array(gettext("Services"), gettext("Captive portal"), gettext("Vouchers"), $a_cp[$cpzone]['zone']);
$shortcut_section = "captiveportal-vouchers";

if (!is_array($config['voucher'][$cpzone]['roll'])) {
    $config['voucher'][$cpzone]['roll'] = array();
}
if (!isset($config['voucher'][$cpzone]['charset'])) {
    $config['voucher'][$cpzone]['charset'] = '2345678abcdefhijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
}
if (!isset($config['voucher'][$cpzone]['rollbits'])) {
    $config['voucher'][$cpzone]['rollbits'] = 16;
}
if (!isset($config['voucher'][$cpzone]['ticketbits'])) {
    $config['voucher'][$cpzone]['ticketbits'] = 10;
}
if (!isset($config['voucher'][$cpzone]['checksumbits'])) {
    $config['voucher'][$cpzone]['checksumbits'] = 5;
}
if (!isset($config['voucher'][$cpzone]['magic'])) {
    $config['voucher'][$cpzone]['magic'] = rand();   // anything slightly random will do
}if (!isset($config['voucher'][$cpzone]['exponent'])) {
    while (true) {
        while (($exponent = rand()) % 30000 < 5000) {
            continue;
        }
        $exponent = ($exponent * 2) + 1; // Make it odd number
        if ($exponent <= 65537) {
            break;
        }
    }
    $config['voucher'][$cpzone]['exponent'] = $exponent;
    unset($exponent);
}

if ($_REQUEST['generatekey']) {
    $key = generatekey($config['voucher'][$cpzone]['exponent']);

    $alertmessage = gettext(
        'You will need to recreate any existing Voucher Rolls due ' .
        'to the public and private key changes. Click cancel if you ' .
        'do not wish to recreate the vouchers.'
    );

    echo json_encode(array(
        'alertmessage' => $alertmessage,
        'privatekey' => $key['priv'],
        'publickey' => $key['pub'],
    ));

    exit;
}

if (!isset($config['voucher'][$cpzone]['publickey'])) {
    $key = generatekey($config['voucher'][$cpzone]['exponent']);
    $config['voucher'][$cpzone]['publickey'] = base64_encode($key['pub']);
    $config['voucher'][$cpzone]['privatekey'] = base64_encode($key['priv']);
}

// Check for invalid or expired vouchers
if (!isset($config['voucher'][$cpzone]['descrmsgnoaccess'])) {
    $config['voucher'][$cpzone]['descrmsgnoaccess'] = gettext("Voucher invalid");
}
if (!isset($config['voucher'][$cpzone]['descrmsgexpired'])) {
    $config['voucher'][$cpzone]['descrmsgexpired'] = gettext("Voucher expired");
}

$a_roll = &$config['voucher'][$cpzone]['roll'];

if ($_GET['act'] == "del") {
    $id = $_GET['id'];
    if ($a_roll[$id]) {
        $roll = $a_roll[$id]['number'];
        $voucherlck = lock("voucher{$cpzone}");
        unset($a_roll[$id]);
        voucher_unlink_db($roll);
        unlock($voucherlck);
        write_config();
    }
    header("Location: services_captiveportal_vouchers.php?zone={$cpzone}");
    exit;
} /* print all vouchers of the selected roll */
elseif ($_GET['act'] == "csv") {
    $privkey = base64_decode($config['voucher'][$cpzone]['privatekey']);
    if (strstr($privkey, "BEGIN RSA PRIVATE KEY")) {
        $fd = fopen("/var/etc/voucher_{$cpzone}.private", "w");
        if (!$fd) {
            $input_errors[] = gettext("Cannot write private key file") . ".\n";
        } else {
            chmod("/var/etc/voucher_{$cpzone}.private", 0600);
            fwrite($fd, $privkey);
            fclose($fd);
            $a_voucher = &$config['voucher'][$cpzone]['roll'];
            $id = $_GET['id'];
            if (isset($id) && $a_voucher[$id]) {
                $number = $a_voucher[$id]['number'];
                $count = $a_voucher[$id]['count'];
                header("Content-Type: application/octet-stream");
                header("Content-Disposition: attachment; filename=vouchers_{$cpzone}_roll{$number}.csv");
                if (file_exists("/var/etc/voucher_{$cpzone}.cfg")) {
                    system("/usr/local/bin/voucher -c /var/etc/voucher_{$cpzone}.cfg -p /var/etc/voucher_{$cpzone}.private $number $count");
                }
                @unlink("/var/etc/voucher_{$cpzone}.private");
            } else {
                header("Location: services_captiveportal_vouchers.php?zone={$cpzone}");
            }
            exit;
        }
    } else {
        $input_errors[] = gettext("Need private RSA key to print vouchers") . "\n";
    }
}

$pconfig['enable'] = isset($config['voucher'][$cpzone]['enable']);
$pconfig['charset'] = $config['voucher'][$cpzone]['charset'];
$pconfig['rollbits'] = $config['voucher'][$cpzone]['rollbits'];
$pconfig['ticketbits'] = $config['voucher'][$cpzone]['ticketbits'];
$pconfig['checksumbits'] = $config['voucher'][$cpzone]['checksumbits'];
$pconfig['magic'] = $config['voucher'][$cpzone]['magic'];
$pconfig['exponent'] = $config['voucher'][$cpzone]['exponent'];
$pconfig['publickey'] = base64_decode($config['voucher'][$cpzone]['publickey']);
$pconfig['privatekey'] = base64_decode($config['voucher'][$cpzone]['privatekey']);
$pconfig['msgnoaccess'] = $config['voucher'][$cpzone]['descrmsgnoaccess'];
$pconfig['msgexpired'] = $config['voucher'][$cpzone]['descrmsgexpired'];

if ($_POST) {
    unset($input_errors);

    if ($_POST['postafterlogin']) {
        voucher_expire($_POST['voucher_expire']);
        exit;
    }

    $pconfig = $_POST;

    /* input validation */
    if ($_POST['enable'] == "yes") {
        $reqdfields = explode(" ", "charset rollbits ticketbits checksumbits publickey magic");
        $reqdfieldsn = array(gettext("charset"),gettext("rollbits"),gettext("ticketbits"),gettext("checksumbits"),gettext("publickey"),gettext("magic"));

        do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
    }

    // Check for form errors
    if ($_POST['charset'] && (strlen($_POST['charset'] < 2))) {
        $input_errors[] = gettext("Need at least 2 characters to create vouchers.");
    }
    if ($_POST['charset'] && (strpos($_POST['charset'], "\"")>0)) {
        $input_errors[] = gettext("Double quotes aren't allowed.");
    }
    if ($_POST['charset'] && (strpos($_POST['charset'], ",")>0)) {
        $input_errors[] = "',' " . gettext("aren't allowed.");
    }
    if ($_POST['rollbits'] && (!is_numeric($_POST['rollbits']) || ($_POST['rollbits'] < 1) || ($_POST['rollbits'] > 31))) {
        $input_errors[] = gettext("# of Bits to store Roll Id needs to be between 1..31.");
    }
    if ($_POST['ticketbits'] && (!is_numeric($_POST['ticketbits']) || ($_POST['ticketbits'] < 1) || ($_POST['ticketbits'] > 16))) {
        $input_errors[] = gettext("# of Bits to store Ticket Id needs to be between 1..16.");
    }
    if ($_POST['checksumbits'] && (!is_numeric($_POST['checksumbits']) || ($_POST['checksumbits'] < 1) || ($_POST['checksumbits'] > 31))) {
        $input_errors[] = gettext("# of Bits to store checksum needs to be between 1..31.");
    }
    if ($_POST['publickey'] && (!strstr($_POST['publickey'], "BEGIN PUBLIC KEY"))) {
        $input_errors[] = gettext("This doesn't look like an RSA Public key.");
    }
    if ($_POST['privatekey'] && (!strstr($_POST['privatekey'], "BEGIN RSA PRIVATE KEY"))) {
        $input_errors[] = gettext("This doesn't look like an RSA Private key.");
    }
    if ($_POST['vouchersyncdbip'] && (is_ipaddr_configured($_POST['vouchersyncdbip']))) {
        $input_errors[] = gettext("You cannot sync the voucher database to this host (itself).");
    }

    if (!$input_errors) {
        if (empty($config['voucher'][$cpzone])) {
                        $newvoucher = array();
        } else {
                $newvoucher = $config['voucher'][$cpzone];
        }
        if ($_POST['enable'] == "yes") {
            $newvoucher['enable'] = true;
        } else {
            unset($newvoucher['enable']);
        }

        $newvoucher['charset'] = $_POST['charset'];
        $newvoucher['rollbits'] = $_POST['rollbits'];
        $newvoucher['ticketbits'] = $_POST['ticketbits'];
        $newvoucher['checksumbits'] = $_POST['checksumbits'];
        $newvoucher['magic'] = $_POST['magic'];
        $newvoucher['exponent'] = $_POST['exponent'];
        $newvoucher['publickey'] = base64_encode($_POST['publickey']);
        $newvoucher['privatekey'] = base64_encode($_POST['privatekey']);
        $newvoucher['descrmsgnoaccess'] = $_POST['msgnoaccess'];
        $newvoucher['descrmsgexpired'] = $_POST['msgexpired'];
        $config['voucher'][$cpzone] = $newvoucher;
        write_config();
        voucher_configure_zone();

        if (!$input_errors) {
            header("Location: services_captiveportal_vouchers.php?zone={$cpzone}");
            exit;
        }
    }
}
$closehead = false;
include("head.inc");

if ($pconfig['enable']) {
    $main_buttons = array(
        array('label'=>gettext("add voucher"), 'href'=>'services_captiveportal_vouchers_edit.php?zone='.$cpzone),
    );
}

?>

<body>

<script type="text/javascript">
//<![CDATA[
function generatenewkey() {
	jQuery('#publickey').val('One moment please...');
	jQuery('#privatekey').val('One moment please...');
	jQuery.ajax("services_captiveportal_vouchers.php?zone=<?php echo($cpzone); ?>&generatekey=true", {
		type: 'get',
		dataType: 'json',
		success: function(json) {
			jQuery('#privatekey').val(json.privatekey);
			jQuery('#publickey').val(json.publickey);
			jQuery('#privatekey').addClass('alert-warning');
			jQuery('#publickey').addClass('alert-warning');
			alert(json.alertmessage);
	}});
}
function before_save() {
	document.iform.charset.disabled = false;
	document.iform.rollbits.disabled = false;
	document.iform.ticketbits.disabled = false;
	document.iform.checksumbits.disabled = false;
	document.iform.magic.disabled = false;
	document.iform.publickey.disabled = false;
	document.iform.privatekey.disabled = false;
	document.iform.msgnoaccess.disabled = false;
	document.iform.msgexpired.disabled = false;
	for(var x=0; x < <?php echo count($a_roll); ?>; x++)
		jQuery('#addeditdelete' + x).show();
	jQuery('#addnewroll').show();
}
function enable_change(enable_change) {
	var endis;
	endis = !(document.iform.enable.checked || enable_change);
	document.iform.charset.disabled = endis;
	document.iform.rollbits.disabled = endis;
	document.iform.ticketbits.disabled = endis;
	document.iform.checksumbits.disabled = endis;
	document.iform.magic.disabled = endis;
	document.iform.publickey.disabled = endis;
	document.iform.privatekey.disabled = endis;
	document.iform.msgnoaccess.disabled = endis;
	document.iform.msgexpired.disabled = endis;
	for(var x=0; x < <?php echo count($a_roll); ?>; x++)
		jQuery('#addeditdelete' + x).show();
	jQuery('#addnewroll').show();
}
//]]>
</script>

	<?php include("fbegin.inc"); ?>

	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">

				<?php if (isset($input_errors) && count($input_errors) > 0) {
                    print_input_errors($input_errors);
} ?>
				<?php if (isset($savemsg)) {
                    print_info_box($savemsg);
} ?>

			    <section class="col-xs-12">

				<?php
                        $tab_array = array();
                        $tab_array[] = array(gettext("Captive portal(s)"), false, "services_captiveportal.php?zone={$cpzone}");
                        $tab_array[] = array(gettext("MAC"), false, "services_captiveportal_mac.php?zone={$cpzone}");
                        $tab_array[] = array(gettext("Allowed IP addresses"), false, "services_captiveportal_ip.php?zone={$cpzone}");
                        // Hide Allowed Hostnames as this feature is currently not supported
                        // $tab_array[] = array(gettext("Allowed Hostnames"), false, "services_captiveportal_hostname.php?zone={$cpzone}");
                        $tab_array[] = array(gettext("Vouchers"), true, "services_captiveportal_vouchers.php?zone={$cpzone}");
                        $tab_array[] = array(gettext("File Manager"), false, "services_captiveportal_filemanager.php?zone={$cpzone}");
                        display_top_tabs($tab_array, true);
                    ?>

					<div class="tab-content content-box col-xs-12">

					<div class="container-fluid">

		                    <form action="services_captiveportal_vouchers.php" method="post" name="iform" id="iform">
		                        <input type="hidden" name="zone" id="zone" value="<?=htmlspecialchars($cpzone);?>" />

		                        <div class="table-responsive">
			                        <table class="table table-striped table-sort">
										<tr>
											<td width="22%" valign="top" class="vtable">&nbsp;</td>
											<td width="78%" class="vtable">
												<input name="enable" type="checkbox" value="yes" <?php if ($pconfig['enable']) {
                                                    echo "checked=\"checked\"";
} ?> onclick="enable_change(false)" />
												<strong><?=gettext("Enable Vouchers"); ?></strong>
											</td>
										</tr>
										<tr>
											<td valign="top" class="vncell">
												<?=gettext("Voucher Rolls"); ?>
											</td>
											<td class="vtable">
												<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="content pane">
													<tr>
														<td width="10%" class="listhdrr"><?=gettext("Roll"); ?> #</td>
														<td width="20%" class="listhdrr"><?=gettext("Minutes/Ticket"); ?></td>
														<td width="20%" class="listhdrr"># <?=gettext("of Tickets"); ?></td>
														<td width="35%" class="listhdr"><?=gettext("Comment"); ?></td>
														<td width="15%" class="list"></td>
													</tr>
													<?php $i = 0; foreach ($a_roll as $rollent) :
?>
														<tr>
															<td class="listlr">
															<?=htmlspecialchars($rollent['number']); ?>&nbsp;
														</td>
														<td class="listr">
															<?=htmlspecialchars($rollent['minutes']);?>&nbsp;
														</td>
														<td class="listr">
															<?=htmlspecialchars($rollent['count']);?>&nbsp;
														</td>
														<td class="listr">
															<?=htmlspecialchars($rollent['descr']); ?>&nbsp;
														</td>
														<td valign="middle" class="list nowrap">
															<div id='addeditdelete<?=$i?>'>
																<?php if ($pconfig['enable']) :
?>
																	<a class="btn btn-default btn-xs" href="services_captiveportal_vouchers_edit.php?zone=<?=$cpzone;
?>&amp;id=<?=$i;
?>"><span class="glyphicon glyphicon-pencil" title="<?=gettext("edit voucher");
?>"  alt="<?=gettext("edit voucher"); ?>" ></span></a>
																	<a class="btn btn-default btn-xs" href="services_captiveportal_vouchers.php?zone=<?=$cpzone;
?>&amp;act=del&amp;id=<?=$i;
?>" onclick="return confirm('<?=gettext("Do you really want to delete this voucher? This makes all vouchers from this roll invalid");
?>')"><span class="glyphicon glyphicon-remove" title="<?=gettext("delete vouchers");
?>" alt="<?=gettext("delete vouchers"); ?>"></span></a>
																	<a class="btn btn-default btn-xs" href="services_captiveportal_vouchers.php?zone=<?=$cpzone;
?>&amp;act=csv&amp;id=<?=$i;
?>"><span class="glyphicon glyphicon-download-alt" title="<?=gettext("generate vouchers for this roll to CSV file");
?>" alt="<?=gettext("generate vouchers for this roll to CSV file"); ?>"></span></a>
                                  <a class="btn btn-default btn-xs" href="/ui/captiveportal/seriesprint/seriesprint/<?=$cpzone;?>/<?=$i;?>">
                                    <span class="glyphicon glyphicon-print" title="<?=gettext("open page for printing voucher series");
                                      ?>" alt="<?=gettext("Series Print"); ?>">
                                    </span>
                                  </a>

													<?php
endif;?>
															</div>
														</td>
													</tr>
													<?php $i++;

endforeach; ?>

												</table>
												<?php if ($pconfig['enable']) :
?>
													<?=gettext("Create, generate and activate Rolls with Vouchers that allow access through the " .
                                                    "captive portal for the configured time. Once a voucher is activated, " .
                                                        "its clock is started and runs uninterrupted until it expires. During that " .
                                                        "time, the voucher can be re-used from the same or a different computer. If the voucher " .
                                                        "is used again from another computer, the previous session is stopped."); ?>
												<?php
else :
?>
													<?=gettext("Enable Voucher support first using the checkbox above and hit Save at the bottom."); ?>
												<?php
endif;?>
												</td>
											</tr>
											<tr>
												<td valign="top" class="vncellreq">
													<?=gettext("Voucher public key"); ?>
												</td>
												<td class="vtable">
													<textarea name="publickey" cols="65" rows="4" id="publickey" class="formpre"><?=htmlspecialchars($pconfig['publickey']);?></textarea>
													<br />
														<?=gettext("Paste an RSA public key (64 Bit or smaller) in PEM format here. This key is used to decrypt vouchers.");
?> <a href='#' onclick='generatenewkey();'><?=gettext('Generate');
?></a> <?=gettext('new key');?>.</td>
												</tr>
												<tr>
													<td valign="top" class="vncell"><?=gettext("Voucher private key"); ?></td>
													<td class="vtable">
														<textarea name="privatekey" cols="65" rows="5" id="privatekey" class="formpre"><?=htmlspecialchars($pconfig['privatekey']);?></textarea>
														<br />
														<?=gettext("Paste an RSA private key (64 Bit or smaller) in PEM format here. This key is only used to generate encrypted vouchers and doesn't need to be available if the vouchers have been generated offline.");
?> <a href='#' onclick='generatenewkey();'> <?=gettext('Generate');
?></a> <?=gettext('new key');?>.</td>
												</tr>
												<tr>
													<td width="22%" valign="top" class="vncellreq"><?=gettext("Character set"); ?></td>
													<td width="78%" class="vtable">
														<input name="charset" type="text" class="formfld" id="charset" size="80" value="<?=htmlspecialchars($pconfig['charset']);?>" />
														<br />
														<?=gettext("Tickets are generated with the specified character set. It should contain printable characters (numbers, lower case and upper case letters) that are hard to confuse with others. Avoid e.g. 0/O and l/1."); ?>
													</td>
												</tr>
												<tr>
													<td width="22%" valign="top" class="vncellreq"># <?=gettext("of Roll Bits"); ?></td>
													<td width="78%" class="vtable">
														<input name="rollbits" type="text" class="formfld" id="rollbits" size="2" value="<?=htmlspecialchars($pconfig['rollbits']);?>" />
														<br />
														<?=gettext("Reserves a range in each voucher to store the Roll # it belongs to. Allowed range: 1..31. Sum of Roll+Ticket+Checksum bits must be one Bit less than the RSA key size."); ?>
													</td>
												</tr>
												<tr>
													<td width="22%" valign="top" class="vncellreq"># <?=gettext("of Ticket Bits"); ?></td>
													<td width="78%" class="vtable">
														<input name="ticketbits" type="text" class="formfld" id="ticketbits" size="2" value="<?=htmlspecialchars($pconfig['ticketbits']);?>" />
														<br />
														<?=gettext("Reserves a range in each voucher to store the Ticket# it belongs to. Allowed range: 1..16. Using 16 bits allows a roll to have up to 65535 vouchers. A bit array, stored in RAM and in the config, is used to mark if a voucher has been used. A bit array for 65535 vouchers requires 8 KB of storage."); ?>
													</td>
												</tr>
												<tr>
													<td width="22%" valign="top" class="vncellreq"># <?=gettext("of Checksum Bits"); ?></td>
													<td width="78%" class="vtable">
														<input name="checksumbits" type="text" class="formfld" id="checksumbits" size="2" value="<?=htmlspecialchars($pconfig['checksumbits']);?>" />
														<br />
														<?=gettext("Reserves a range in each voucher to store a simple checksum over Roll # and Ticket#. Allowed range is 0..31."); ?>
													</td>
												</tr>
												<tr>
													<td width="22%" valign="top" class="vncellreq"><?=gettext("Magic Number"); ?></td>
													<td width="78%" class="vtable">
														<input name="magic" type="text" class="formfld" id="magic" size="20" value="<?=htmlspecialchars($pconfig['magic']);?>" />
														<br />
														<?=gettext("Magic number stored in every voucher. Verified during voucher check. Size depends on how many bits are left by Roll+Ticket+Checksum bits. If all bits are used, no magic number will be used and checked."); ?>
													</td>
												</tr>
												<tr>
													<td width="22%" valign="top" class="vncellreq"><?=gettext("Invalid Voucher Message"); ?></td>
													<td width="78%" class="vtable">
														<input name="msgnoaccess" type="text" class="formfld" id="msgnoaccess" size="80" value="<?=htmlspecialchars($pconfig['msgnoaccess']);?>" />
														<br /><?=gettext("Error message displayed for invalid vouchers on captive portal error page"); ?> ($PORTAL_MESSAGE$).
													</td>
												</tr>
												<tr>
													<td width="22%" valign="top" class="vncellreq"><?=gettext("Expired Voucher Message"); ?></td>
													<td width="78%" class="vtable">
														<input name="msgexpired" type="text" class="formfld" id="msgexpired" size="80" value="<?=htmlspecialchars($pconfig['msgexpired']);?>" />
														<br /><?=gettext("Error message displayed for expired vouchers on captive portal error page"); ?> ($PORTAL_MESSAGE$).
													</td>
												</tr>
												<tr>
													<td width="22%" valign="top">&nbsp;</td>
													<td width="78%">
														&nbsp;
													</td>
												</tr>
												<tr>
													<td width="22%" valign="top">&nbsp;</td>
													<td width="78%">
														<input type="hidden" name="zone" id="zone" value="<?=htmlspecialchars($cpzone);?>" />
														<input type="hidden" name="exponent" id="exponent" value="<?=$pconfig['exponent'];?>" />
														<input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save"); ?>" onclick="enable_change(true); before_save();" />
														<input type="button" class="btn btn-default" value="<?=gettext("Cancel");
?>" onclick="window.location.href='<?=$referer;?>'" />
													</td>
												</tr>
												<tr>
													<td colspan="2" class="list"><p class="vexpl">
														<span class="red"><strong> <?=gettext("Note:"); ?><br />   </strong></span>
													<?=gettext("Changing any Voucher parameter (apart from managing the list of Rolls) on this page will render existing vouchers useless if they were generated with different settings."); ?>
													<br />
													<?=gettext("Specifying the Voucher Database Synchronization options will not record any other value from the other options. They will be retrieved/synced from the master."); ?>
												</p>
											</td>
										</tr>
									</table>
		                        </div>
		                    </form>
					</div>
					</div>
			    </section>
			</div>
		</div>
	</section>

<script type="text/javascript">
//<![CDATA[
enable_change(false);
//]]>
</script>
<?php include("foot.inc");
