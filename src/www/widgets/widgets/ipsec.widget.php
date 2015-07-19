<?php
/*
        Copyright (C) 2014 Deciso B.V.
        Copyright (C) 2007 Scott Dale
        Copyright (C) 2004-2005 T. Lechat <dev@lechat.org>, Manuel Kasper <mk@neon1.net>
        and Jonathan Watt <jwatt@jwatt.org>.
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

$nocsrf = true;

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("ipsec.inc");

//function to create widget tabs when called
function display_widget_tabs(& $tab_array) {
	echo "<div id=\"tabs\">";
	$tabscounter = 0;
	foreach ($tab_array as $ta) {
	$dashpos = strpos($ta[2],'-');
	$tabname = $ta[2] . "-tab";
	$tabclass = substr($ta[2],0,$dashpos);
	$tabclass = $tabclass . "-class";
		if ($ta[1] == true) {
			$tabActive = "table-cell";
			$tabNonActive = "none";
		}
		else {
			$tabActive = "none";
			$tabNonActive = "table-cell";
		}
		echo "<div id=\"{$ta[2]}-active\" class=\"{$tabclass}-tabactive\" style=\"display:{$tabActive}; background-color:#EEEEEE; color:black;\">";
		echo "<b>&nbsp;&nbsp;&nbsp;{$ta[0]}";
		echo "&nbsp;&nbsp;&nbsp;</b>";
		echo "</div>";

		echo "<div id=\"{$ta[2]}-deactive\" class=\"{$tabclass}-tabdeactive\" style=\"display:{$tabNonActive}; background-color:#777777; color:white; cursor: pointer;\" onclick=\"return changeTabDIV('{$ta[2]}')\">";
		echo "<b>&nbsp;&nbsp;&nbsp;{$ta[0]}";
		echo "&nbsp;&nbsp;&nbsp;</b>";
		echo "</div>";
	}

}



if (isset($config['ipsec']['phase1'])) {
    echo "<div>&nbsp;</div>\n";
    $tab_array = array();
    $tab_array[0] = array(gettext("Overview"), true, "ipsec-Overview");
    $tab_array[1] = array(gettext("Tunnels"), false, "ipsec-tunnel");
    $tab_array[2] = array(gettext("Mobile"), false, "ipsec-mobile");
    display_widget_tabs($tab_array);

    $spd = ipsec_dump_spd();
    $sad = ipsec_dump_sad();
    $mobile = array(); // TODO: temporary disabled ( https://github.com/opnsense/core/issues/139 )  ipsec_dump_mobile();
    $ipsec_status = ipsec_smp_dump_status();

    $activecounter = 0;
    $inactivecounter = 0;

    $ipsec_detail_array = array();
    if (isset($config['ipsec']['phase2'])) {
        foreach ($config['ipsec']['phase2'] as $ph2ent) {
            if ($ph2ent['remoteid']['type'] == "mobile") {
                continue;
            }
            ipsec_lookup_phase1($ph2ent, $ph1ent);
            $ipsecstatus = false;

            $tun_disabled = "false";
            $foundsrc = false;
            $founddst = false;

            if (isset($ph1ent['disabled']) || isset($ph2ent['disabled'])) {
                $tun_disabled = "true";
                continue;
            }
            if (isset($ipsec_status['query']['ikesalist']['ikesa']) && isset($ph1ent['ikeid']) &&  ipsec_phase1_status($ipsec_status['query']['ikesalist']['ikesa'], $ph1ent['ikeid'])) {
                /* tunnel is up */
                $iconfn = "true";
                $activecounter++;
            } else {
                /* tunnel is down */
                $iconfn = "false";
                $inactivecounter++;
            }

            $ipsec_detail_array[] = array(
                'src' => convert_friendly_interface_to_friendly_descr($ph1ent['interface']),
                'dest' => $ph1ent['remote-gateway'],
                'remote-subnet' => ipsec_idinfo_to_text($ph2ent['remoteid']),
                'descr' => $ph2ent['descr'],
                'status' => $iconfn,
                'disabled' => $tun_disabled
            );
        }
    }
}

if (isset($config['ipsec']['phase2'])) {
?>

<div id="ipsec-Overview" style="display:block;background-color:#EEEEEE;">
	<div>
	<table class="table table-striped" width="100%" border="0" cellpadding="6" cellspacing="0" summary="heading">
	<tr>
		<td class="nowrap"><?php echo gettext('Active Tunnels');?></td>
		<td class="nowrap"><?php echo gettext('Inactive Tunnels');?></td>
		<td class="nowrap"><?php echo gettext('Mobile Users');?></td>
	</tr>
	<tr>
		<td><?php echo $activecounter; ?></td>
		<td><?php echo $inactivecounter; ?></td>
		<td><?php if (is_array($mobile['pool'])) {
            echo htmlspecialchars($mobile['pool'][0]['usage']);

} else {
    echo 0;
} ?></td>
	</tr>
	</table>
	</div>
</div>

<div id="ipsec-tunnel" style="display:none;background-color:#EEEEEE;">
	<div style="padding: 10px">
		<div style="display:table-row;">
			<div class="widgetsubheader" style="display:table-cell;width:40px">Source</div>
			<div class="widgetsubheader" style="display:table-cell;width:100px">Destination</div>
			<div class="widgetsubheader" style="display:table-cell;width:90px">Description</div>
			<div class="widgetsubheader" style="display:table-cell;width:30px">Status</div>
		</div>
		<div style="max-height:105px;overflow:auto;">
	<?php
    foreach ($ipsec_detail_array as $ipsec) :
        if ($ipsec['disabled'] == "true") {
            $spans = "<span class=\"gray\">";
            $spane = "</span>";
        } else {
            $spans = $spane = "";
        }

        ?>

		<div style="display:table-row;">
			<div style="display:table-cell;width:39px">
				<?php echo $spans;?>
					<?php echo htmlspecialchars($ipsec['src']);?>
				<?php echo $spane;?>
			</div>
			<div style="display:table-cell;width:100px"><?php echo $spans;?>
				<?php echo $ipsec['remote-subnet'];?>
				<br />
				(<?php echo htmlspecialchars($ipsec['dest']);?>)<?php echo $spane;?>
			</div>
			<div style="display:table-cell;width:90px"><?php echo $spans;?><?php echo htmlspecialchars($ipsec['descr']);?><?php echo $spane;?></div>
			<div style="display:table-cell;width:37px" align="center"><?php echo $spans;?>
			<?php

            if ($ipsec['status'] == "true") {
                /* tunnel is up */
                $iconfn = "text-success";
            } else {
                /* tunnel is down */
                $iconfn = "text-danger";
            }

            echo "<span class='glyphicon glyphicon-transfer ".$iconfn."' alt='Tunnel status'></span>";

            ?><?php echo $spane;?></div>
		</div>
	<?php
    endforeach; ?>
	</div>
 </div>
</div>
<div id="ipsec-mobile" style="display:none;background-color:#EEEEEE;">
	<div style="padding: 10px">
		<div style="display:table-row;">
    <div class="widgetsubheader" style="display:table-cell;width:140px"><?= gettext('User') ?></div>
    <div class="widgetsubheader" style="display:table-cell;width:130px"><?= gettext('IP') ?></div>
    <div class="widgetsubheader" style="display:table-cell;width:30px"><?= gettext('Status') ?></div>
		</div>
		<div style="max-height:105px;overflow:auto;">
<?php
if (is_array($mobile['pool'])) :
    foreach ($mobile['pool'] as $pool) :
        if (is_array($pool['lease'])) :
            foreach ($pool['lease'] as $muser) :
?>
		<div style="display:table-row;">
			<div class="listlr" style="display:table-cell;width:139px">
				<?php echo htmlspecialchars($muser['id']);?><br />
			</div>
			<div class="listr"  style="display:table-cell;width:130px">
				<?php echo htmlspecialchars($muser['host']);?><br />
			</div>
			<div class="listr"  style="display:table-cell;width:30px">
				<?php echo htmlspecialchars($muser['status']);?><br/>
			</div>
		</div>
<?php
            endforeach;
        endif;
    endforeach;
endif;
?>
    </div>
</div>
</div>
<?php //end ipsec tunnel
} //end if tunnels are configured, else show code below
else {
?>
<div style="display:block">
	 <table class="table table-striped" width="100%" border="0" cellpadding="0" cellspacing="0" summary="note">
	  <tr>
	    <td colspan="4">
	        <span class="vexpl">
	          <span class="red">
	            <strong>
                <?= gettext('Note: There are no configured IPsec Tunnels') ?><br />
	            </strong>
	          </span>
            <?= gettext('You can configure your IPsec') ?>
            <a href="vpn_ipsec.php"><?= gettext('here') ?></a>.
	        </span>
		</td>
	  </tr>
	</table>
</div>
<?php
}
