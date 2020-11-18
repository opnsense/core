<?php

/*
 * Copyright (C) 2014 Deciso B.V.
 * Copyright (C) 2008 Shrew Soft Inc. <mgrooms@shrew.net>
 * Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

require_once("guiconfig.inc");
require_once("interfaces.inc");
require_once("plugins.inc.d/ipsec.inc");

/**
 * combine ealgos and keylen_* tags
 */
function pconfig_to_ealgos($pconfig)
{
    $p2_ealgos = ipsec_p2_ealgos();

    $ealgos = array();
    if (isset($pconfig['ealgos'])) {
        foreach ($p2_ealgos as $algo_name => $algo_data) {
            if (in_array($algo_name, $pconfig['ealgos'])) {
                $ealg = array();
                $ealg['name'] = $algo_name;
                if (isset($algo_data['keysel'])) {
                    $ealg['keylen'] = $pconfig["keylen_".$algo_name];
                }
                $ealgos[] = $ealg;
            }
        }
    }

    return $ealgos;
}

function ealgos_to_pconfig(& $ealgos, & $pconfig)
{

    $pconfig['ealgos'] = array();
    foreach ($ealgos as $algo_data) {
        $pconfig['ealgos'][] = $algo_data['name'];
        if (isset($algo_data['keylen'])) {
            $pconfig["keylen_".$algo_data['name']] = $algo_data['keylen'];
        }
    }

    return $ealgos;
}

/**
 * convert <tag>id_address, <tag>id_netbits, <tag>id_type
 * to type/address/netbits structure
 */
function pconfig_to_idinfo($prefix, $pconfig)
{
    $type = isset($pconfig[$prefix."id_type"]) ? $pconfig[$prefix."id_type"] : null;
    $address = isset($pconfig[$prefix."id_address"]) ? $pconfig[$prefix."id_address"] : null;
    $netbits = isset($pconfig[$prefix."id_netbits"]) ? $pconfig[$prefix."id_netbits"] : null;

    switch ($type) {
        case "address":
            return array('type' => $type, 'address' => $address);
        case "network":
            return array('type' => $type, 'address' => $address, 'netbits' => $netbits);
        default:
            return array('type' => $type );
    }
}

/**
 * reverse pconfig_to_idinfo from $idinfo array to $pconfig
 */
function idinfo_to_pconfig($prefix, $idinfo, & $pconfig)
{
    switch ($idinfo['type']) {
        case "address":
            $pconfig[$prefix."id_type"] = $idinfo['type'];
            $pconfig[$prefix."id_address"] = $idinfo['address'];
            break;
        case "network":
            $pconfig[$prefix."id_type"] = $idinfo['type'];
            $pconfig[$prefix."id_address"] = $idinfo['address'];
            $pconfig[$prefix."id_netbits"] = $idinfo['netbits'];
            break;
        default:
            $pconfig[$prefix."id_type"] = $idinfo['type'];
            break;
    }
}

/**
 * search phase 2 entries for record with uniqid
 */
function getIndexByUniqueId($uniqid)
{
    global $config;
    $p2index = null;
    if ($uniqid != null) {
        foreach ($config['ipsec']['phase2'] as $idx => $ph2) {
            if ($ph2['uniqid'] == $uniqid) {
                $p2index = $idx;
                break;
            }
        }
    }
    return $p2index;
}

config_read_array('ipsec', 'client');
config_read_array('ipsec', 'phase2');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // lookup p2index
    if (!empty($_GET['dup'])) {
        $p2index = getIndexByUniqueId($_GET['dup']);
    } elseif (!empty($_GET['p2index'])) {
        $p2index = getIndexByUniqueId($_GET['p2index']);
    } else {
        $p2index = null;
    }
    // initialize form data
    $pconfig = array();

    $phase2_fields = "ikeid,mode,descr,uniqid,proto,hash-algorithm-option,pfsgroup,lifetime,pinghost,protocol,spd,";
    $phase2_fields .= "tunnel_local,tunnel_remote";
    if ($p2index !== null) {
        // 1-on-1 copy
        foreach (explode(",", $phase2_fields) as $fieldname) {
            $fieldname = trim($fieldname);
            if (isset($config['ipsec']['phase2'][$p2index][$fieldname])) {
                $pconfig[$fieldname] = $config['ipsec']['phase2'][$p2index][$fieldname];
            } elseif (!isset($pconfig[$fieldname])) {
                // initialize element
                $pconfig[$fieldname] = null;
            }
        }
        // fields with some kind of logic
        $pconfig['disabled'] = isset($config['ipsec']['phase2'][$p2index]['disabled']);

        idinfo_to_pconfig("local", $config['ipsec']['phase2'][$p2index]['localid'], $pconfig);
        idinfo_to_pconfig("remote", $config['ipsec']['phase2'][$p2index]['remoteid'], $pconfig);
        if (!empty($config['ipsec']['phase2'][$p2index]['encryption-algorithm-option'])) {
            ealgos_to_pconfig($config['ipsec']['phase2'][$p2index]['encryption-algorithm-option'], $pconfig);
        } else {
            $pconfig['ealgos'] = array();
        }

        if (isset($config['ipsec']['phase2'][$p2index]['mobile'])) {
            $pconfig['mobile'] = true;
        }

        if (!empty($_GET['dup'])) {
            $pconfig['uniqid'] = uniqid();
        }
    } else {
        if (isset($_GET['ikeid'])) {
            $pconfig['ikeid'] = $_GET['ikeid'];
        }
        /* defaults */
        $pconfig['localid_type'] = "lan";
        $pconfig['remoteid_type'] = "network";
        $pconfig['protocol'] = "esp";
        $pconfig['ealgos'] = explode(",", "3des,blowfish,cast128,aes");
        $pconfig['hash-algorithm-option'] = explode(",", "hmac_sha1,hmac_md5");
        $pconfig['pfsgroup'] = "0";
        $pconfig['lifetime'] = "3600";
        $pconfig['uniqid'] = uniqid();

        /* mobile client */
        if (isset($_GET['mobile'])) {
            $pconfig['mobile']=true;
        }
        // init empty
        foreach (explode(",", $phase2_fields) as $fieldname) {
            $fieldname = trim($fieldname);
            if (!isset($pconfig[$fieldname])) {
                $pconfig[$fieldname] = null;
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['uniqid'])) {
        $p2index = getIndexByUniqueId($_POST['uniqid']);
    } else {
        $p2index = null;
    }
    $input_errors = array();
    $pconfig = $_POST;

    /* input validation */
    if (!isset($_POST['ikeid'])) {
        $input_errors[] = gettext("A valid ikeid must be specified.");
    }
    $reqdfields = explode(" ", "localid_type uniqid");
    $reqdfieldsn = array(gettext("Local network type"), gettext("Unique Identifier"));
    if (!isset($pconfig['mobile'])) {
        $reqdfields[] = "remoteid_type";
        $reqdfieldsn[] = gettext("Remote network type");
    }

    do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

    if (($pconfig['mode'] == 'tunnel') || ($pconfig['mode'] == 'tunnel6')) {
        switch ($pconfig['localid_type']) {
            case 'network':
                if (($pconfig['localid_netbits'] != 0 && !$pconfig['localid_netbits']) || !is_numeric($pconfig['localid_netbits'])) {
                    $input_errors[] = gettext('A valid local network bit count must be specified.');
                }
            case 'address':
                if (!$pconfig['localid_address'] || !is_ipaddr($pconfig['localid_address'])) {
                    $input_errors[] = gettext('A valid local network IP address must be specified.');
                } elseif (is_ipaddrv4($pconfig['localid_address']) && ($pconfig['mode'] != 'tunnel')) {
                    $input_errors[] = gettext('A valid local network IPv4 address must be specified or you need to change Mode to IPv6');
                } elseif (is_ipaddrv6($pconfig['localid_address']) && ($pconfig['mode'] != 'tunnel6')) {
                    $input_errors[] = gettext('A valid local network IPv6 address must be specified or you need to change Mode to IPv4');
                }
                break;
            default:
                if ($pconfig['mode'] == 'tunnel' && !is_subnetv4(find_interface_network(get_real_interface($pconfig['localid_type'])))) {
                    $input_errors[] = sprintf(
                        gettext('Invalid local network: %s has no valid IPv4 network.'),
                        convert_friendly_interface_to_friendly_descr($pconfig['localid_type'])
                    );
                } elseif ($pconfig['mode'] == 'tunnel6' && !is_subnetv6(find_interface_networkv6(get_real_interface($pconfig['localid_type']), 'inet6'))) {
                    $input_errors[] = sprintf(
                        gettext('Invalid local network: %s has no valid IPv6 network.'),
                        convert_friendly_interface_to_friendly_descr($pconfig['localid_type'])
                    );
                }
                break;
        }

        switch ($pconfig['remoteid_type']) {
            case "network":
                if (($pconfig['remoteid_netbits'] != 0 && !$pconfig['remoteid_netbits']) || !is_numeric($pconfig['remoteid_netbits'])) {
                    $input_errors[] = gettext("A valid remote network bit count must be specified.");
                }
                // address rules also apply to network type (hence, no break)
            case "address":
                if (!$pconfig['remoteid_address'] || !is_ipaddr($pconfig['remoteid_address'])) {
                    $input_errors[] = gettext("A valid remote network IP address must be specified.");
                } elseif (is_ipaddrv4($pconfig['remoteid_address']) && ($pconfig['mode'] != "tunnel")) {
                    $input_errors[] = gettext("A valid remote network IPv4 address must be specified or you need to change Mode to IPv6");
                } elseif (is_ipaddrv6($pconfig['remoteid_address']) && ($pconfig['mode'] != "tunnel6")) {
                    $input_errors[] = gettext("A valid remote network IPv6 address must be specified or you need to change Mode to IPv4");
                }
                break;
        }
    } elseif ($pconfig['mode'] == 'route-based') {
        // validate if both tunnel networks are using the correct address family
        if (!is_ipaddr($pconfig['tunnel_local']) || !is_ipaddr($pconfig['tunnel_remote'])) {
            if (!is_ipaddr($pconfig['tunnel_local'])) {
                $input_errors[] = gettext('A valid local network IP address must be specified.');
            }
            if (!is_ipaddr($pconfig['tunnel_remote'])) {
                $input_errors[] = gettext("A valid remote network IP address must be specified.");
            }
        } elseif(
            !(is_ipaddrv4($pconfig['tunnel_local']) && is_ipaddrv4($pconfig['tunnel_remote'])) &&
            !(is_ipaddrv6($pconfig['tunnel_local']) && is_ipaddrv6($pconfig['tunnel_remote']))
        ) {
            $input_errors[] = gettext('A valid local network IP address must be specified.');
            $input_errors[] = gettext("A valid remote network IP address must be specified.");
        }
    }
    /* Validate enabled phase2's are not duplicates */
    if (isset($pconfig['mobile'])) {
        /* User is adding phase 2 for mobile phase1 */
        foreach ($config['ipsec']['phase2'] as $key => $name) {
            if (isset($name['mobile']) && $pconfig['ikeid'] == $name['ikeid'] && $name['uniqid'] != $pconfig['uniqid']) {
                /* check duplicate localids only for mobile clents */
                $localid_data = ipsec_idinfo_to_cidr($name['localid'], false, $name['mode']);
                $entered = array();
                $entered['type'] = $pconfig['localid_type'];
                if (isset($pconfig['localid_address'])) {
                    $entered['address'] = $pconfig['localid_address'];
                }
                if (isset($pconfig['localid_netbits'])) {
                    $entered['netbits'] = $pconfig['localid_netbits'];
                }
                $entered_localid_data = ipsec_idinfo_to_cidr($entered, false, $pconfig['mode']);
                if ($localid_data == $entered_localid_data) {
                    /* adding new p2 entry */
                    $input_errors[] = gettext("Phase2 with this Local Network is already defined for mobile clients.");
                    break;
                }
            }
        }
    } else {
        /* User is adding phase 2 for site-to-site phase1 */
        foreach ($config['ipsec']['phase2'] as $key => $name) {
            if (!isset($name['mobile']) && $pconfig['ikeid'] == $name['ikeid'] && $pconfig['uniqid'] != $name['uniqid']) {
                /* check duplicate subnets only for given phase1 */
                $localid_data = ipsec_idinfo_to_cidr($name['localid'], false, $name['mode']);
                $remoteid_data = ipsec_idinfo_to_cidr($name['remoteid'], false, $name['mode']);
                $entered_local = array();
                $entered_local['type'] = $pconfig['localid_type'];
                if (isset($pconfig['localid_address'])) {
                    $entered_local['address'] = $pconfig['localid_address'];
                }
                if (isset($pconfig['localid_netbits'])) {
                    $entered_local['netbits'] = $pconfig['localid_netbits'];
                }
                $entered_localid_data = ipsec_idinfo_to_cidr($entered_local, false, $pconfig['mode']);
                $entered_remote = array();
                $entered_remote['type'] = $pconfig['remoteid_type'];
                if (isset($pconfig['remoteid_address'])) {
                    $entered_remote['address'] = $pconfig['remoteid_address'];
                }
                if (isset($pconfig['remoteid_netbits'])) {
                    $entered_remote['netbits'] = $pconfig['remoteid_netbits'];
                }
                $entered_remoteid_data = ipsec_idinfo_to_cidr($entered_remote, false, $pconfig['mode']);
                if ($localid_data == $entered_localid_data && $remoteid_data == $entered_remoteid_data) {
                    /* adding new p2 entry */
                    $input_errors[] = gettext("Phase2 with this Local/Remote networks combination is already defined for this Phase1.");
                    break;
                }
            }
        }
    }

    /* For ESP protocol, handle encryption algorithms */
    if ($pconfig['protocol'] == "esp") {
        $ealgos = pconfig_to_ealgos($pconfig);

        if (!count($ealgos)) {
            $input_errors[] = gettext("At least one encryption algorithm must be selected.");
        } else {
            if (empty($pconfig['hash-algorithm-option'])) {
                foreach ($ealgos as $ealgo) {
                    if (!strpos($ealgo['name'], "gcm")) {
                        $input_errors[] = gettext("At least one hashing algorithm needs to be selected.");
                        break;
                    }
                }
                $pconfig['hash-algorithm-option'] = array();
            }
        }
    }
    if ((!empty($_POST['lifetime']) && !is_numeric($_POST['lifetime']))) {
        $input_errors[] = gettext("The P2 lifetime must be an integer.");
    }

    if (!empty($pconfig['spd'])) {
        foreach (explode(',', $pconfig['spd']) as $spd_entry) {
            if (($pconfig['mode'] == "tunnel" && !is_subnetv4(trim($spd_entry))) ||
              ($pconfig['mode'] == "tunnel6" && !is_subnetv6(trim($spd_entry)))) {
                $input_errors[] = sprintf(gettext('SPD "%s" is not a valid network, it should match the tunnel type (IPv4/IPv6).'), $spd_entry) ;
            }
        }
    }

    if (count($input_errors) == 0) {
        $ph2ent = array();
        $copy_fields = "ikeid,uniqid,mode,pfsgroup,lifetime,pinghost,descr,protocol,spd";

        // 1-on-1 copy
        foreach (explode(",", $copy_fields) as $fieldname) {
            $fieldname = trim($fieldname);
            if (!empty($pconfig[$fieldname])) {
                $ph2ent[$fieldname] = $pconfig[$fieldname];
            }
        }

        // fields with some logic in them
        $ph2ent['disabled'] = $pconfig['disabled'] ? true : false;
        if (($ph2ent['mode'] == "tunnel") || ($ph2ent['mode'] == "tunnel6")) {
            $ph2ent['localid'] = pconfig_to_idinfo("local", $pconfig);
            $ph2ent['remoteid'] = pconfig_to_idinfo("remote", $pconfig);
        } elseif ($ph2ent['mode'] == 'route-based') {
            $ph2ent['tunnel_local'] = $pconfig['tunnel_local'];
            $ph2ent['tunnel_remote'] = $pconfig['tunnel_remote'];
        }

        $ph2ent['encryption-algorithm-option'] = pconfig_to_ealgos($pconfig);
        ;
        if (!empty($pconfig['hash-algorithm-option'])) {
            $ph2ent['hash-algorithm-option'] = $pconfig['hash-algorithm-option'];
        } else {
            unset($ph2ent['hash-algorithm-option']);
        }

        if (isset($pconfig['mobile'])) {
            $ph2ent['mobile'] = true;
        }

        // save to config
        if ($p2index !== null) {
            $config['ipsec']['phase2'][$p2index] = $ph2ent;
        } else {
            $config['ipsec']['phase2'][] = $ph2ent;
        }


        write_config();
        mark_subsystem_dirty('ipsec');

        header(url_safe('Location: /vpn_ipsec.php'));
        exit;
    }
}

$service_hook = 'strongswan';

legacy_html_escape_form_data($pconfig);

include("head.inc");

?>

<body>
<?php include("fbegin.inc"); ?>
<script>
    $( document ).ready(function() {
        $("#mode").change(function(){
            $(".opt_localid").hide();
            $(".opt_remoteid").hide();
            $(".opt_route").hide();
            if ($(this).val() == 'tunnel' || $(this).val() == 'tunnel6') {
                $(".opt_localid").show();
                if ($("#mobile").val() == undefined) {
                    $(".opt_remoteid").show();
                }
            } else if ($(this).val() == 'route-based') {
                $(".opt_route").show();
            }
            $(window).resize();
        });
        $("#mode").change();

        $("#proto").change(function(){
            if ($(this).val() == 'esp') {
                $("#opt_enc").show();
            } else {
                $("#opt_enc").hide();
            }
            $(window).resize();
        });
        $("#proto").change();

        ['localid', 'remoteid'].map(function(field){
            $("#"+field+"_type").change(function(){
                $("#"+field+"_netbits").prop("disabled", true);
                $("#"+field+"_address").prop("disabled", true);
                switch ($(this).val()) {
                    case 'address':
                        $("#"+field+"_address").prop("disabled", false);
                        break;
                    case 'network':
                        $("#"+field+"_netbits").prop("disabled", false);
                        $("#"+field+"_address").prop("disabled", false);
                        break;
                    default:
                        break;
                }
                $(window).resize();
            });
            $("#"+field+"_type").change();
        });

        // hook in, ipv4/ipv6 selector events
        hook_ipv4v6('ipv4v6net', 'network-id');
    });
</script>

<?php
if (isset($input_errors) && count($input_errors) > 0) {
    print_input_errors($input_errors);
}
?>
<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
        <section class="col-xs-12">
        <div class="tab-content content-box col-xs-12">
          <form method="post" name="iform" id="iform">
            <div class="table-responsive">
              <table class="table table-striped opnsense_standard_table_form">
                <tr>
                  <td style="width:22%"><b><?=gettext("General information"); ?></b></td>
                  <td style="width:78%; text-align:right">
                    <small><?=gettext("full help"); ?> </small>
                    <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                  </td>
                </tr>
                <tr>
                  <td style="width:22%"><a id="help_for_disabled" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Disabled"); ?></td>
                  <td style="width:78%" class="vtable">
                    <input name="disabled" type="checkbox" id="disabled" value="yes" <?= !empty($pconfig['disabled']) ? "checked=\"checked\"" : "" ;?> />
                    <div class="hidden" data-for="help_for_disabled">
                        <?=gettext("Disable this phase2 entry"); ?><br/>
                        <?=gettext("Set this option to disable this phase2 entry without " .
                                                  "removing it from the list"); ?>.
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i>  <?=gettext("Mode"); ?></td>
                  <td>
                    <select name="mode" id="mode">
                        <?php
                        $p2_modes = array(
                        'tunnel' => 'Tunnel IPv4',
                        'tunnel6' => 'Tunnel IPv6',
                        'route-based' => 'Route-based',
                        'transport' => 'Transport');
                        foreach ($p2_modes as $name => $value) :
    ?>
                        <option value="<?=$name;?>"
                          <?=$name == $pconfig['mode'] ? "selected=\"selected\"":"" ;?>><?=$value;?>
                        </option>
<?php
                        endforeach;
?>
                    </select>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_descr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description"); ?></td>
                  <td>
                    <input name="descr" type="text" id="descr" size="40" value="<?=$pconfig['descr'];?>" />
                    <div class="hidden" data-for="help_for_descr">
                        <?=gettext("You may enter a description here " .
                                                    "for your reference (not parsed)"); ?>.
                    </div>
                  </td>
                </tr>
                <!-- Route based tunnel -->
                <tr class="opt_route">
                  <td colspan="2"><b><?=gettext("Tunnel network");?></b></td>
                </tr>
                <tr class="opt_route">
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Local Address");?> </td>
                  <td>
                    <input name="tunnel_local" type="text" id="tunnel_local" size="28" value="<?=$pconfig['tunnel_local'];?>" />
                  </td>
                </tr>
                <tr class="opt_route">
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Remote Address");?> </td>
                  <td>
                    <input name="tunnel_remote" type="text" id="tunnel_remote" size="28" value="<?=$pconfig['tunnel_remote'];?>" />
                  </td>
                </tr>
                <!-- Tunnel settings -->
                <tr class="opt_localid">
                  <td colspan="2"><b><?=gettext("Local Network");?></b></td>
                </tr>
                <tr class="opt_localid">
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Type"); ?> </td>
                  <td>
                    <select name="localid_type" id="localid_type">
                      <option value="address" <?=$pconfig['localid_type'] == "address" ? "selected=\"selected\"" : ""?> ><?=gettext("Address"); ?></option>
                      <option value="network" <?=$pconfig['localid_type'] == "network" ? "selected=\"selected\"" : ""?> ><?=gettext("Network"); ?></option>
<?php
                      $iflist = get_configured_interface_with_descr();
                      foreach ($iflist as $ifname => $ifdescr) :?>
                        <option value="<?=htmlspecialchars($ifname);?>" <?= $pconfig['localid_type'] == $ifname ? "selected=\"selected\"" : "" ;?> >
                          <?=sprintf(gettext("%s subnet"), htmlspecialchars($ifdescr)); ?>
                        </option>
<?php
                      endforeach;?>
                    </select>
                  </td>
                </tr>
                <tr class="opt_localid">
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Address:");?>&nbsp;&nbsp;</td>
                  <td>
                    <input name="localid_address" type="text" id="localid_address" size="28" value="<?=$pconfig['localid_address'];?>" />
                    /
                    <select name="localid_netbits" data-network-id="localid_address" class="ipv4v6net" id="localid_netbits">
<?php               for ($i = 128; $i >= 0; $i--) :
?>
                      <option value="<?=$i;?>" <?= isset($pconfig['localid_netbits']) && $i == $pconfig['localid_netbits'] ? "selected=\"selected\"" : "";?>>
                        <?=$i;?>
                      </option>
<?php
                    endfor; ?>
                    </select>
                  </td>
                </tr>
<?php          if (!isset($pconfig['mobile'])) :
?>
                <tr class="opt_remoteid">
                  <td colspan="2"><b><?=gettext("Remote Network");?></b></td>
                </tr>
                <tr class="opt_remoteid">
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Type"); ?>:&nbsp;&nbsp;</td>
                  <td>
                    <select name="remoteid_type" id="remoteid_type">
                      <option value="address" <?= $pconfig['remoteid_type'] == "address" ? "selected=\"selected\"" : "";?>>
                        <?=gettext("Address"); ?>
                      </option>
                      <option value="network" <?= $pconfig['remoteid_type'] == "network" ? "selected=\"selected\"" : "";?>>
                        <?=gettext("Network"); ?>
                      </option>
                    </select>
                  </td>
                </tr>
                <tr class="opt_remoteid">
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Address"); ?>:&nbsp;&nbsp;</td>
                  <td>
                    <input name="remoteid_address" type="text" class="formfld unknown" id="remoteid_address" size="28" value="<?=$pconfig['remoteid_address'];?>" />
                    /
                    <select name="remoteid_netbits" data-network-id="remoteid_address" class="ipv4v6net" id="remoteid_netbits">
<?php              for ($i = 128; $i >= 0; $i--) :
?>
                      <option value="<?=$i;?>" <?= isset($pconfig['remoteid_netbits']) && $i == $pconfig['remoteid_netbits'] ? "selected=\"selected\"" : "";?> >
                        <?=$i;?>
                      </option>
<?php              endfor;
?>
                    </select>
                  </td>
                </tr>

<?php
endif; ?>
                <tr>
                  <td colspan="2">
                    <b><?=gettext("Phase 2 proposal (SA/Key Exchange)"); ?></b>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_proto" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Protocol"); ?></td>
                  <td style="width:78%" class="vtable">
                    <select name="protocol" id="proto">
<?php
                    foreach (array('esp' => 'ESP','ah' => 'AH') as $proto => $protoname) :?>
                      <option value="<?=$proto;?>" <?= $proto == $pconfig['protocol'] ? "selected=\"selected\"" : "";?>>
                        <?=$protoname;?>
                      </option>
<?php
                    endforeach; ?>
                    </select>
                    <br />
                    <div class="hidden" data-for="help_for_proto">
                        <?=gettext("ESP is encryption, AH is authentication only"); ?>
                    </div>
                  </td>
                </tr>
                <tr id="opt_enc">
                  <td><a id="help_for_encalg" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Encryption algorithms"); ?></td>
                  <td>
<?php
                  foreach (ipsec_p2_ealgos() as $algo => $algodata) :?>
                    <input type="checkbox" name="ealgos[]" value="<?=$algo;?>" <?=isset($pconfig['ealgos']) && in_array($algo, $pconfig['ealgos']) ? "checked=\"checked\"" : ""; ?> />
                      <?=$algodata['name'];?>
<?php
                      if (isset($algodata['keysel'])) :?>
                      <select name="keylen_<?=$algo;?>">
                        <option value="auto"><?=gettext("auto"); ?></option>
<?php
                        for ($keylen = $algodata['keysel']['hi']; $keylen >= $algodata['keysel']['lo']; $keylen -= $algodata['keysel']['step']) :?>
                        <option value="<?=$keylen;?>" <?=$keylen == $pconfig["keylen_".$algo] ? "selected=\"selected\"" : "";?>>
                          <?=$keylen;?> <?=gettext("bits"); ?>
                        </option>
<?php
                        endfor; ?>
                      </select>
<?php
                      else :?>
                      <br/>
<?php
                      endif; ?>

<?php
                      endforeach; ?>

                      <div class="hidden" data-for="help_for_encalg">
                          <?=gettext("Hint: use 3DES for best compatibility or if you have a hardware " .
                                                  "crypto accelerator card. Blowfish is usually the fastest in " .
                                                  "software encryption"); ?>.
                      </div>
                  </td>
                </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Hash algorithms"); ?></td>
                  <td style="width:78%" class="vtable">
                    <select name="hash-algorithm-option[]" class="selectpicker" multiple="multiple">
<?php foreach (ipsec_p2_halgos() as $algo => $algoname): ?>
                      <option value="<?= html_safe($algo) ?>" <?= (is_array($pconfig['hash-algorithm-option']) && in_array($algo, $pconfig['hash-algorithm-option'])) ? 'selected="selected"' : '' ?>>
                        <?= html_safe($algoname) ?>
                      </option>
<?php endforeach ?>
                    </select>
                  </td>
                </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("PFS key group"); ?></td>
                  <td>
<?php
                  if (!isset($pconfig['mobile']) || !isset($config['ipsec']['client']['pfs_group'])) :?>
                    <select name="pfsgroup">
<?php
                    $p2_dhgroups = array(
                        0 => gettext('off'),
                        1 => '1 (768 bits)',
                        2 => '2 (1024 bits)',
                        5 => '5 (1536 bits)',
                        14 => '14 (2048 bits)',
                        15 => '15 (3072 bits)',
                        16 => '16 (4096 bits)',
                        17 => '17 (6144 bits)',
                        18 => '18 (8192 bits)',
                        19 => '19 (NIST EC 256 bits)',
                        20 => '20 (NIST EC 384 bits)',
                        21 => '21 (NIST EC 521 bits)',
                        22 => '22 (1024(sub 160) bits)',
                        23 => '23 (2048(sub 224) bits)',
                        24 => '24 (2048(sub 256) bits)',
                        28 => '28 (Brainpool EC 256 bits)',
                        29 => '29 (Brainpool EC 384 bits)',
                        30 => '30 (Brainpool EC 512 bits)',
                        31 => '31 (Elliptic Curve 25519)',
                    );
                    foreach ($p2_dhgroups as $keygroup => $keygroupname): ?>
                      <option value="<?=$keygroup;?>" <?= $keygroup == $pconfig['pfsgroup'] ? "selected=\"selected\"" : "";?>>
                        <?=$keygroupname;?>
                      </option>
<?php
                    endforeach; ?>
                    </select>
<?php
                  else :?>
                    <select disabled="disabled">
                      <option selected="selected"><?=$p2_pfskeygroups[$config['ipsec']['client']['pfs_group']];?></option>
                    </select>
                    <input name="pfsgroup" type="hidden" value="<?=$pconfig['pfsgroup'];?>" />
                    <br />
                    <em><?=gettext("Set globally in mobile client options"); ?></em>
<?php
                  endif; ?>
                  </td>
                </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Lifetime"); ?></td>
                  <td>
                    <input name="lifetime" type="text" class="formfld unknown" id="lifetime" size="20" value="<?=$pconfig['lifetime'];?>" />
                    <?=gettext("seconds"); ?>
                  </td>
                </tr>
                <tr>
                  <td colspan="2">
                    <b><?=gettext("Advanced Options"); ?></b>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_pinghost" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Automatically ping host"); ?></td>
                  <td>
                    <input name="pinghost" type="text" class="formfld unknown" id="pinghost" size="28" value="<?=$pconfig['pinghost'];?>" />
                    <div class="hidden" data-for="help_for_pinghost">
                        <?=gettext("IP address"); ?>
                    </div>
                  </td>
                </tr>
<?php
                if (!isset($pconfig['mobile'])):?>
                <tr class="opt_localid">
                  <td><a id="help_for_spd" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Manual SPD entries"); ?></td>
                  <td>
                    <input name="spd" type="text" id="spd" value="<?= $pconfig['spd'];?>" />
                    <div class="hidden" data-for="help_for_spd">
                        <strong><?=gettext("Register additional Security Policy Database entries"); ?></strong><br/>
                        <?=gettext("Strongswan automatically creates SPD policies for the networks defined in this phase2. ".
                                   "If you need to allow other networks to use this ipsec tunnel, you can add them here as a comma-separated list.".
                                   "When configured, you can use network address translation to push packets through this tunnel from these networks."); ?><br/>
                        <small><?=gettext("e.g. 192.168.1.0/24, 192.168.2.0/24"); ?></small>
                    </div>
                  </td>
                </tr>
<?php
                endif; ?>
                <tr>
                  <td>&nbsp;</td>
                  <td style="width:78%">
<?php
                 if (isset($pconfig['mobile'])) :?>
                    <input name="mobile" type="hidden" value="true" />
                    <input name="remoteid_type" type="hidden" value="mobile" />
<?php
                 endif; ?>
                    <input name="Submit" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save')); ?>" />
                    <input name="ikeid" type="hidden" value="<?=$pconfig['ikeid'];?>" />
                    <input name="uniqid" type="hidden" value="<?=$pconfig['uniqid'];?>" />
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
