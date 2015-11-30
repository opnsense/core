<?php

/*
  Copyright (C) 2014 Deciso B.V.
  Copyright (C) 2008 Shrew Soft Inc
  Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>.
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
require_once("interfaces.inc");
require_once("vpn.inc");
require_once("services.inc");

/* local utility functions */

/**
 * combine ealgos and keylen_* tags
 */
function pconfig_to_ealgos($pconfig)
{
    global $p2_ealgos;

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
    $type = $pconfig[$prefix."id_type"];
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

if (!isset($config['ipsec']) || !is_array($config['ipsec'])) {
    $config['ipsec'] = array();
}

if (!isset($config['ipsec']['client'])) {
    $config['ipsec']['client'] = array();
}

if (!isset($config['ipsec']['phase2'])) {
    $config['ipsec']['phase2'] = array();
}

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

    $phase2_fields = "ikeid,mode,descr,uniqid,proto,hash-algorithm-option,pfsgroup,pfsgroup,lifetime,pinghost,protocol";
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

        if (!empty($config['ipsec']['phase2'][$p2index]['natlocalid'])) {
            idinfo_to_pconfig("natlocal", $config['ipsec']['phase2'][$p2index]['natlocalid'], $pconfig);
        }
        idinfo_to_pconfig("local", $config['ipsec']['phase2'][$p2index]['localid'], $pconfig);
        idinfo_to_pconfig("remote", $config['ipsec']['phase2'][$p2index]['remoteid'], $pconfig);
        ealgos_to_pconfig($config['ipsec']['phase2'][$p2index]['encryption-algorithm-option'], $pconfig);
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

    if (($pconfig['mode'] == "tunnel") || ($pconfig['mode'] == "tunnel6")) {
        switch ($pconfig['localid_type']) {
            case "network":
                if (($pconfig['localid_netbits'] != 0 && !$pconfig['localid_netbits']) || !is_numeric($pconfig['localid_netbits'])) {
                    $input_errors[] = gettext("A valid local network bit count must be specified.");
                }
            case "address":
                if (!$pconfig['localid_address'] || !is_ipaddr($pconfig['localid_address'])) {
                    $input_errors[] = gettext("A valid local network IP address must be specified.");
                } elseif (is_ipaddrv4($pconfig['localid_address']) && ($pconfig['mode'] != "tunnel"))
                $input_errors[] = gettext("A valid local network IPv4 address must be specified or you need to change Mode to IPv6");
                elseif (is_ipaddrv6($pconfig['localid_address']) && ($pconfig['mode'] != "tunnel6"))
                $input_errors[] = gettext("A valid local network IPv6 address must be specified or you need to change Mode to IPv4");
                break;
        }
        /* Check if the localid_type is an interface, to confirm if it has a valid subnet. */
        if (isset($config['interfaces'][$pconfig['localid_type']])) {
            // Don't let an empty subnet into racoon.conf, it can cause parse errors. Ticket #2201.
            $address = get_interface_ip($pconfig['localid_type']);
            $netbits = get_interface_subnet($pconfig['localid_type']);

            if (empty($address) || empty($netbits)) {
                $input_errors[] = gettext("Invalid Local Network.") . " " . convert_friendly_interface_to_friendly_descr($pconfig['localid_type']) . " " . gettext("has no subnet.");
            }
        }

        if (!empty($pconfig['natlocalid_address'])) {
            switch ($pconfig['natlocalid_type']) {
                case "network":
                    if (($pconfig['natlocalid_netbits'] != 0 && !$pconfig['natlocalid_netbits']) || !is_numeric($pconfig['natlocalid_netbits'])) {
                        $input_errors[] = gettext("A valid NAT local network bit count must be specified.");
                    }
                    if ($pconfig['localid_type'] == "address") {
                        $input_errors[] = gettext("You cannot configure a network type address for NAT while only an address type is selected for local source.");
                    }
                    // address rules also apply to network type (hence, no break)
                case "address":
                    if (!empty($pconfig['natlocalid_address']) && !is_ipaddr($pconfig['natlocalid_address'])) {
                        $input_errors[] = gettext("A valid NAT local network IP address must be specified.");
                    } elseif (is_ipaddrv4($pconfig['natlocalid_address']) && ($pconfig['mode'] != "tunnel"))
                    $input_errors[] = gettext("A valid NAT local network IPv4 address must be specified or you need to change Mode to IPv6");
                    elseif (is_ipaddrv6($pconfig['natlocalid_address']) && ($pconfig['mode'] != "tunnel6"))
                    $input_errors[] = gettext("A valid NAT local network IPv6 address must be specified or you need to change Mode to IPv4");
                    break;
            }
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
    }
    /* Validate enabled phase2's are not duplicates */
    if (isset($pconfig['mobile'])) {
        /* User is adding phase 2 for mobile phase1 */
        foreach ($config['ipsec']['phase2'] as $key => $name) {
            if (isset($name['mobile']) && $name['uniqid'] != $pconfig['uniqid']) {
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
            }
        }
    }
    if ((!empty($_POST['lifetime']) && !is_numeric($_POST['lifetime']))) {
        $input_errors[] = gettext("The P2 lifetime must be an integer.");
    }

    if (count($input_errors) == 0) {
        $ph2ent = array();
        $copy_fields = "ikeid,uniqid,mode,pfsgroup,lifetime,pinghost,descr,protocol";

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
            if (!empty($pconfig['natlocalid_address'])) {
                $ph2ent['natlocalid'] = pconfig_to_idinfo("natlocal", $pconfig);
            }
            $ph2ent['localid'] = pconfig_to_idinfo("local", $pconfig);
            $ph2ent['remoteid'] = pconfig_to_idinfo("remote", $pconfig);
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

        header("Location: vpn_ipsec.php");
        exit;
    }
}


if (!empty($pconfig['mobile'])) {
    $pgtitle = array(gettext("VPN"),gettext("IPsec"),gettext("Edit Phase 2"), gettext("Mobile Client"));
} else {
    $pgtitle = array(gettext("VPN"),gettext("IPsec"),gettext("Edit Phase 2"));
}
$shortcut_section = "ipsec";

legacy_html_escape_form_data($pconfig);

include("head.inc");
?>

<body>
<?php include("fbegin.inc"); ?>
<script type="text/javascript">
//<![CDATA[
$( document ).ready(function() {
  // old js code..
  change_mode('<?=$pconfig['mode']?>');
  change_protocol('<?=$pconfig['protocol']?>');
  typesel_change_local(<?=$pconfig['localid_netbits']?>);
<?php if (isset($pconfig['natlocalid_netbits'])) :
?>
  typesel_change_natlocal(<?=$pconfig['natlocalid_netbits']?>);
<?php endif;
?>
    <?php if (!isset($pconfig['mobile'])) :
    ?>
  typesel_change_remote(<?=$pconfig['remoteid_netbits']?>);
    <?php
endif; ?>

  $( document ).ready(function() {
      // hook in, ipv4/ipv6 selector events
      hook_ipv4v6('ipv4v6net', 'network-id');
  });
});

function change_mode() {
  index = document.iform.mode.selectedIndex;
  value = document.iform.mode.options[index].value;
  if ((value == 'tunnel') || (value == 'tunnel6')) {
    document.getElementById('opt_localid').style.display = '';
<?php if (!isset($pconfig['mobile'])) :
?>
    document.getElementById('opt_remoteid').style.display = '';
<?php
endif; ?>
  } else {
    document.getElementById('opt_localid').style.display = 'none';
<?php if (!isset($pconfig['mobile'])) :
?>
    document.getElementById('opt_remoteid').style.display = 'none';
<?php
endif; ?>
  }
}

function typesel_change_natlocal(bits) {
  var value = document.iform.mode.options[index].value;
  if (typeof(bits) === "undefined") {
    if (value === "tunnel") {
      bits = 24;
    }
    else if (value === "tunnel6") {
      bits = 64;
    }
  }
  var address_is_blank = !/\S/.test(document.iform.natlocalid_address.value);
  switch (document.iform.natlocalid_type.selectedIndex) {
    case 0:  /* single */
      document.iform.natlocalid_address.disabled = 0;
      if (address_is_blank) {
        document.iform.natlocalid_netbits.value = 0;
      }
      document.iform.natlocalid_netbits.disabled = 1;
      break;
    case 1:  /* network */
      document.iform.natlocalid_address.disabled = 0;
      if (address_is_blank) {
        document.iform.natlocalid_netbits.value = bits;
      }
      document.iform.natlocalid_netbits.disabled = 0;
      break;
    case 3:  /* none */
      document.iform.natlocalid_address.disabled = 1;
      document.iform.natlocalid_netbits.disabled = 1;
      break;
    default:
      document.iform.natlocalid_address.value = "";
      document.iform.natlocalid_address.disabled = 1;
      if (address_is_blank) {
        document.iform.natlocalid_netbits.value = 0;
      }
      document.iform.natlocalid_netbits.disabled = 1;
      break;
  }
}

function typesel_change_local(bits) {
  var value = document.iform.mode.options[index].value;
  if (typeof(bits) === "undefined") {
    if (value === "tunnel") {
      bits = 24;
    }
    else if (value === "tunnel6") {
      bits = 64;
    }
  }
  var address_is_blank = !/\S/.test(document.iform.localid_address.value);
  switch (document.iform.localid_type.selectedIndex) {
    case 0:  /* single */
      document.iform.localid_address.disabled = 0;
      if (address_is_blank) {
        document.iform.localid_netbits.value = 0;
      }
      document.iform.localid_netbits.disabled = 1;
      break;
    case 1:  /* network */
      document.iform.localid_address.disabled = 0;
      if (address_is_blank) {
        document.iform.localid_netbits.value = bits;
      }
      document.iform.localid_netbits.disabled = 0;
      break;
    case 3:  /* none */
      document.iform.localid_address.disabled = 1;
      document.iform.localid_netbits.disabled = 1;
      break;
    default:
      document.iform.localid_address.value = "";
      document.iform.localid_address.disabled = 1;
      if (address_is_blank) {
        document.iform.localid_netbits.value = 0;
      }
      document.iform.localid_netbits.disabled = 1;
      break;
  }
}

<?php if (!isset($pconfig['mobile'])) :
?>

function typesel_change_remote(bits) {
  var value = document.iform.mode.options[index].value;
  if (typeof(bits) === "undefined") {
    if (value === "tunnel") {
      bits = 24;
    }
    else if (value === "tunnel6") {
      bits = 64;
    }
  }
  var address_is_blank = !/\S/.test(document.iform.remoteid_address.value);
  switch (document.iform.remoteid_type.selectedIndex) {
    case 0:  /* single */
      document.iform.remoteid_address.disabled = 0;
      if (address_is_blank) {
        document.iform.remoteid_netbits.value = 0;
      }
      document.iform.remoteid_netbits.disabled = 1;
      break;
    case 1:  /* network */
      document.iform.remoteid_address.disabled = 0;
      if (address_is_blank) {
        document.iform.remoteid_netbits.value = bits;
      }
      document.iform.remoteid_netbits.disabled = 0;
      break;
    default:
      document.iform.remoteid_address.value = "";
      document.iform.remoteid_address.disabled = 1;
      if (address_is_blank) {
        document.iform.remoteid_netbits.value = 0;
      }
      document.iform.remoteid_netbits.disabled = 1;
      break;
  }
}

<?php
endif; ?>

function change_protocol() {
  index = document.iform.proto.selectedIndex;
  value = document.iform.proto.options[index].value;
  if (value == 'esp')
    document.getElementById('opt_enc').style.display = '';
  else
    document.getElementById('opt_enc').style.display = 'none';
}

//]]>
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
<?php
                    $tab_array = array();
                    $tab_array[0] = array(gettext("Tunnels"), true, "vpn_ipsec.php");
                    $tab_array[1] = array(gettext("Mobile clients"), false, "vpn_ipsec_mobile.php");
                    $tab_array[2] = array(gettext("Pre-Shared Keys"), false, "vpn_ipsec_keys.php");
                    $tab_array[3] = array(gettext("Advanced Settings"), false, "vpn_ipsec_settings.php");
                    display_top_tabs($tab_array);
?>
        <div class="tab-content content-box col-xs-12">
          <form action="vpn_ipsec_phase2.php" method="post" name="iform" id="iform">
            <div class="table-responsive">
              <table class="table table-striped">
                <tr>
                  <td width="22%"><b><?=gettext("General information"); ?></b></td>
                  <td width="78%" align="right">
                    <small><?=gettext("full help"); ?> </small>
                    <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page" type="button"></i></a>
                  </td>
                </tr>
                <tr>
                  <td width="22%"><a id="help_for_disabled" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Disabled"); ?></td>
                  <td width="78%" class="vtable">
                    <input name="disabled" type="checkbox" id="disabled" value="yes" <?= !empty($pconfig['disabled']) ? "checked=\"checked\"" : "" ;?> />
                    <div class="hidden" for="help_for_disabled">
                        <?=gettext("Disable this phase2 entry"); ?><br/>
                        <?=gettext("Set this option to disable this phase2 entry without " .
                                                  "removing it from the list"); ?>.
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i>  <?=gettext("Mode"); ?></td>
                  <td>
                    <select name="mode" class="formselect" onchange="change_mode()">
                        <?php
                        $p2_modes = array(
                        'tunnel' => 'Tunnel IPv4',
                        'tunnel6' => 'Tunnel IPv6',
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
                    <div class="hidden" for="help_for_descr">
                        <?=gettext("You may enter a description here " .
                                                    "for your reference (not parsed)"); ?>.
                    </div>
                  </td>
                </tr>
                <tr>
                  <td colspan="2"><b><?=gettext("Local Network");?></b></td>
                </tr>
                <tr id="opt_localid">
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Type"); ?> </td>
                  <td>
                    <select name="localid_type" class="formselect" onchange="typesel_change_local()">
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
                <tr>
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
                <tr>
                  <td colspan="2"><b><?=gettext("NAT/BINAT");?></b></td>
                </tr>
                <tr>
                  <td><a id="help_for_natlocalid_type" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Type"); ?></td>
                  <td>
                    <select name="natlocalid_type" class="formselect" onchange="typesel_change_natlocal()">
                      <option value="address" <?=!empty($pconfig['natlocalid_type']) && $pconfig['natlocalid_type'] == "address" ? "selected=\"selected\"" : "";?> >
                        <?=gettext("Address"); ?>
                      </option>
                      <option value="network"  <?=!empty($pconfig['natlocalid_type']) && $pconfig['natlocalid_type'] == "network" ? "selected=\"selected\"" : "";?>>
                        <?=gettext("Network"); ?>
                      </option>
                      <option value="none" <?=empty($pconfig['natlocalid_type']) || $pconfig['natlocalid_type'] == "none" ? "selected=\"selected\"" : "";?>>
                        <?=gettext("None"); ?>
                      </option>
                    </select>
                    <div class="hidden" for="help_for_natlocalid_type">
                        <?php echo gettext("In case you need NAT/BINAT on this network specify the address to be translated"); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Address:");?>&nbsp;&nbsp;</td>
                  <td>
                    <input name="natlocalid_address" type="text" class="formfld unknown ipv4v6" id="natlocalid_address" size="28" value="<?=isset($pconfig['natlocalid_address']) ? $pconfig['natlocalid_address'] : "";?>" />
                    /
                    <select name="natlocalid_netbits"  data-network-id="natlocalid_address" class="formselect ipv4v6net" id="natlocalid_netbits">
<?php
                    for ($i = 128; $i >= 0; $i--) :?>
                      <option value="<?=$i;?>" <?= isset($pconfig['natlocalid_netbits']) && $i == $pconfig['natlocalid_netbits'] ? "selected=\"selected\"" : "";?>>
                        <?=$i;?>
                      </option>
<?php
                    endfor; ?>
                    </select>
                  </td>
                </tr>

<?php          if (!isset($pconfig['mobile'])) :
?>
                <tr id="opt_remoteid">
                  <td colspan="2"><b><?=gettext("Remote Network");?></b></td>
                </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Type"); ?>:&nbsp;&nbsp;</td>
                  <td>
                    <select name="remoteid_type" class="formselect" onchange="typesel_change_remote()">
                      <option value="address" <?= $pconfig['remoteid_type'] == "address" ? "selected=\"selected\"" : "";?>>
                        <?=gettext("Address"); ?>
                      </option>
                      <option value="network" <?= $pconfig['remoteid_type'] == "network" ? "selected=\"selected\"" : "";?>>
                        <?=gettext("Network"); ?>
                      </option>
                    </select>
                  </td>
                </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Address"); ?>:&nbsp;&nbsp;</td>
                  <td>
                    <input name="remoteid_address" type="text" class="formfld unknown ipv4v6" id="remoteid_address" size="28" value="<?=$pconfig['remoteid_address'];?>" />
                    /
                    <select name="remoteid_netbits" class="formselect ipv4v6" id="remoteid_netbits">
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
                  <td width="78%" class="vtable">
                    <select name="protocol" id="proto" class="formselect" onchange="change_protocol()">
<?php
                    foreach (array('esp' => 'ESP','ah' => 'AH') as $proto => $protoname) :?>
                      <option value="<?=$proto;?>" <?= $proto == $pconfig['protocol'] ? "selected=\"selected\"" : "";?>>
                        <?=$protoname;?>
                      </option>
<?php
                    endforeach; ?>
                    </select>
                    <br />
                    <div class="hidden" for="help_for_proto">
                        <?=gettext("ESP is encryption, AH is authentication only"); ?>
                    </div>
                  </td>
                </tr>
                <tr id="opt_enc">
                  <td><a id="help_for_encalg" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Encryption algorithms"); ?></td>
                  <td>
<?php
                  foreach ($p2_ealgos as $algo => $algodata) :?>
                    <input type="checkbox" name="ealgos[]" value="<?=$algo;?>" <?=isset($pconfig['ealgos']) && in_array($algo, $pconfig['ealgos']) ? "checked=\"checked\"" : ""; ?> />
                      <?=$algodata['name'];?>
<?php
                      if (isset($algodata['keysel'])) :?>
                      <select name="keylen_<?=$algo;?>" class="formselect">
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

                      <div class="hidden" for="help_for_encalg">
                          <?=gettext("Hint: use 3DES for best compatibility or if you have a hardware " .
                                                  "crypto accelerator card. Blowfish is usually the fastest in " .
                                                  "software encryption"); ?>.
                      </div>
                  </td>
                </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Hash algorithms"); ?></td>
                  <td width="78%" class="vtable">
<?php
                  foreach ($p2_halgos as $algo => $algoname) :?>
                    <input type="checkbox" name="hash-algorithm-option[]" value="<?=$algo;?>" <?= isset($pconfig['hash-algorithm-option']) && in_array($algo, $pconfig['hash-algorithm-option']) ?  'checked="checked"' : '';?>/>
                    <?=$algoname;?>
                    </br>
<?php
                  endforeach; ?>
                  </td>
                </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("PFS key group"); ?></td>
                  <td>
<?php
                  if (!isset($pconfig['mobile']) || !isset($config['ipsec']['client']['pfs_group'])) :?>
                    <select name="pfsgroup">
<?php
                    foreach ($p2_pfskeygroups as $keygroup => $keygroupname) :?>
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
                    <div class="hidden" for="help_for_pinghost">
                        <?=gettext("IP address"); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td>&nbsp;</td>
                  <td width="78%">
<?php            if (isset($pconfig['mobile'])) :
    ?>
                    <input name="mobile" type="hidden" value="true" />
                    <input name="remoteid_type" type="hidden" value="mobile" />
<?php
endif; ?>
                    <input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save"); ?>" />
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
