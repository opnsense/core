<?php

/*
 * Copyright (C) 2019 Pascal Mathis <mail@pascalmathis.com>
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2008 Shrew Soft Inc. <mgrooms@shrew.net>
 * Copyright (C) 2003-2005 Manuel Kasper <mk@neon1.net>
 * Copyright (C) 2014 Ermal Luçi
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
require_once("system.inc");
require_once("filter.inc");
require_once("interfaces.inc");
require_once("plugins.inc.d/ipsec.inc");

/*
 * ikeid management functions
 */

function ipsec_ikeid_used($ikeid) {
    global $config;

    if (!empty($config['ipsec']['phase1'])) {
        foreach ($config['ipsec']['phase1'] as $ph1ent) {
            if( $ikeid == $ph1ent['ikeid'] ) {
                return true;
            }
        }
    }
    return false;
}

function ipsec_ikeid_next() {
    $ikeid = 1;
    while(ipsec_ikeid_used($ikeid)) {
        $ikeid++;
    }

    return $ikeid;
}

function ipsec_keypairs()
{
    $mdl = new \OPNsense\IPsec\IPsec();
    $node = $mdl->getNodeByReference('keyPairs.keyPair');

    return $node ? $node->getNodes() : [];
}

config_read_array('ipsec', 'phase1');
config_read_array('ipsec', 'phase2');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // fetch data
    if (isset($_GET['dup']) && is_numericint($_GET['dup'])) {
        $p1index = $_GET['dup'];
    } elseif (isset($_GET['p1index']) && is_numericint($_GET['p1index'])) {
        $p1index = $_GET['p1index'];
    }
    $pconfig = array();

    // generice defaults
    $pconfig['interface'] = "wan";
    $pconfig['iketype'] = "ikev2";
    $phase1_fields = "mode,protocol,myid_type,myid_data,peerid_type,peerid_data
    ,encryption-algorithm,lifetime,authentication_method,descr,nat_traversal,rightallowany,inactivity_timeout
    ,interface,iketype,dpd_delay,dpd_maxfail,dpd_action,remote-gateway,pre-shared-key,certref,margintime,rekeyfuzz
    ,caref,local-kpref,peer-kpref,reauth_enable,rekey_enable,auto,tunnel_isolation,authservers,mobike,keyingtries
    ,closeaction";
    if (isset($p1index) && isset($config['ipsec']['phase1'][$p1index])) {
        // 1-on-1 copy
        foreach (explode(",", $phase1_fields) as $fieldname) {
            $fieldname = trim($fieldname);
            if(isset($config['ipsec']['phase1'][$p1index][$fieldname])) {
                $pconfig[$fieldname] = $config['ipsec']['phase1'][$p1index][$fieldname];
            } elseif (!isset($pconfig[$fieldname])) {
                // initialize element
                $pconfig[$fieldname] = null;
            }
        }

        // attributes with some kind of logic behind them...
        if (!isset($_GET['dup']) || !is_numericint($_GET['dup'])) {
            // don't copy the ikeid on dup
            $pconfig['ikeid'] = $config['ipsec']['phase1'][$p1index]['ikeid'];
        }
        $pconfig['disabled'] = isset($config['ipsec']['phase1'][$p1index]['disabled']);
        $pconfig['sha256_96'] = !empty($config['ipsec']['phase1'][$p1index]['sha256_96']);
        $pconfig['installpolicy'] = empty($config['ipsec']['phase1'][$p1index]['noinstallpolicy']); // XXX: reversed

        foreach (array('authservers', 'dhgroup', 'hash-algorithm') as $fieldname) {
            if (!empty($config['ipsec']['phase1'][$p1index][$fieldname])) {
                $pconfig[$fieldname] = explode(',', $config['ipsec']['phase1'][$p1index][$fieldname]);
            } else {
                $pconfig[$fieldname] = array();
            }
        }

        $pconfig['remotebits'] = null;
        $pconfig['remotenet'] = null ;
        if (isset($a_phase1[$p1index]['remote-subnet']) && strpos($config['ipsec']['phase1'][$p1index]['remote-subnet'],'/') !== false) {
            list($pconfig['remotenet'],$pconfig['remotebits']) = explode("/", $config['ipsec']['phase1'][$p1index]['remote-subnet']);
        } elseif (isset($config['ipsec']['phase1'][$p1index]['remote-subnet'])) {
            $pconfig['remotenet'] = $config['ipsec']['phase1'][$p1index]['remote-subnet'];
        }

        if (isset($config['ipsec']['phase1'][$p1index]['mobile'])) {
            $pconfig['mobile'] = true;
        }
    } else {
        /* defaults new */
        if (isset($config['interfaces']['lan'])) {
            $pconfig['localnet'] = "lan";
        }
        $pconfig['mode'] = "main";
        $pconfig['protocol'] = "inet";
        $pconfig['myid_type'] = "myaddress";
        $pconfig['peerid_type'] = "peeraddress";
        $pconfig['authentication_method'] = "pre_shared_key";
        $pconfig['encryption-algorithm'] = array("name" => "aes", "keylen" => "128");
        $pconfig['hash-algorithm'] = array('sha256');
        $pconfig['dhgroup'] = array('14');
        $pconfig['lifetime'] = "28800";
        $pconfig['nat_traversal'] = "on";
        $pconfig['installpolicy'] = true;
        $pconfig['authservers'] = array();

        /* mobile client */
        if (isset($_GET['mobile'])) {
            $pconfig['mobile'] = true;
        }
        // init empty
        foreach (explode(",", $phase1_fields) as $fieldname) {
            $fieldname = trim($fieldname);
            if (!isset($pconfig[$fieldname])) {
                $pconfig[$fieldname] = null;
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a_phase1 = &config_read_array('ipsec', 'phase1');
    if (isset($_POST['p1index']) && is_numericint($_POST['p1index'])) {
        $p1index = $_POST['p1index'];
    }
    $input_errors = array();
    $pconfig = $_POST;
    $old_ph1ent = $a_phase1[$p1index];

    // unset dpd on post
    if (!isset($pconfig['dpd_enable'])) {
        unset($pconfig['dpd_delay']);
        unset($pconfig['dpd_maxfail']);
        unset($pconfig['dpd_action']);
    }

    /* My identity */
    if ($pconfig['myid_type'] == "myaddress") {
        $pconfig['myid_data'] = "";
    }
    /* Peer identity */
    if ($pconfig['myid_type'] == "peeraddress") {
        $pconfig['peerid_data'] = "";
    }

    /* input validation */
    $method = $pconfig['authentication_method'];

    // Only require PSK here for normal PSK tunnels (not mobile) or xauth.
    // For RSA methods, require the CA/Cert.
    switch ($method) {
        case "eap-tls":
        case "psk_eap-tls":
        case "eap-mschapv2":
        case "rsa_eap-mschapv2":
        case "eap-radius":
          if (!in_array($pconfig['iketype'], array('ikev2', 'ike'))) {
              $input_errors[] = sprintf(gettext("%s can only be used with IKEv2 type VPNs."), strtoupper($method));
          }
          if ($method == 'eap-radius' && empty($pconfig['authservers'])) {
              $input_errors[] = gettext("Please select radius servers to use.");
          }
          break;
        case "pre_shared_key":
            // If this is a mobile PSK tunnel the user PSKs go on
            //    the PSK tab, not here, so skip the check.
            if ($pconfig['mobile']) {
                break;
            }
        case "xauth_psk_server":
            $reqdfields = explode(" ", "pre-shared-key");
            $reqdfieldsn = array(gettext("Pre-Shared Key"));
            break;
        case "hybrid_rsa_server":
            $reqdfields = explode(' ', 'certref');
            $reqdfieldsn = array(gettext("Certificate"));
            break;
        case "xauth_rsa_server":
        case "rsasig":
            $reqdfields = explode(" ", "caref certref");
            $reqdfieldsn = array(gettext("Certificate Authority"),gettext("Certificate"));
            break;
        case "pubkey":
            $reqdfields = explode(" ", "local-kpref peer-kpref");
            $reqdfieldsn = array(gettext("Local Key Pair"),gettext("Peer Key Pair"));
            break;
    }

    if (empty($pconfig['mobile'])) {
        $reqdfields[] = "remote-gateway";
        $reqdfieldsn[] = gettext("Remote gateway");
    }

    do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

    if (!empty($pconfig['inactivity_timeout']) && !is_numericint($pconfig['inactivity_timeout'])) {
        $input_errors[] = gettext("The inactivity timeout must be an integer.");
    }
    if (!empty($pconfig['keyingtries']) && !is_numericint($pconfig['keyingtries']) && $pconfig['keyingtries'] != "-1") {
        $input_errors[] = gettext("The keyingtries must be an integer.");
    }

    if ((!empty($pconfig['lifetime']) && !is_numeric($pconfig['lifetime']))) {
        $input_errors[] = gettext("The P1 lifetime must be an integer.");
    }
    if (!empty($pconfig['margintime'])) {
        if (!is_numericint($pconfig['margintime'])) {
            $input_errors[] = gettext("The margintime must be an integer.");
        } else {
            $rekeyfuzz = empty($pconfig['rekeyfuzz']) || !is_numeric($pconfig['rekeyfuzz']) ? 100 : $pconfig['rekeyfuzz'];
            if (((int)$pconfig['margintime'] * 2) * ($rekeyfuzz / 100.0) > (int)$pconfig['lifetime']) {
                $input_errors[] = gettext("The value margin... + margin... * rekeyfuzz must not exceed the original lifetime limit.");
            }
        }
    }
    if (!empty($pconfig['rekeyfuzz']) && !is_numericint($pconfig['rekeyfuzz'])) {
        $input_errors[] = gettext("Rekeyfuzz must be an integer.");
    }

    if (!empty($pconfig['remote-gateway'])) {
        if (!is_ipaddr($pconfig['remote-gateway']) && !is_domain($pconfig['remote-gateway'])) {
            $input_errors[] = gettext("A valid remote gateway address or host name must be specified.");
        } elseif (is_ipaddrv4($pconfig['remote-gateway']) && ($pconfig['protocol'] != "inet")) {
            $input_errors[] = gettext("A valid remote gateway IPv4 address must be specified or you need to change protocol to IPv6");
        } elseif (is_ipaddrv6($pconfig['remote-gateway']) && ($pconfig['protocol'] != "inet6")) {
            $input_errors[] = gettext("A valid remote gateway IPv6 address must be specified or you need to change protocol to IPv4");
        }
    }

    if (!empty($pconfig['remote-gateway']) && is_ipaddr($pconfig['remote-gateway']) && !isset($pconfig['disabled']) &&
        (empty($pconfig['iketype']) || $pconfig['iketype'] == "ikev1")) {
        $t = 0;
        foreach ($a_phase1 as $ph1tmp) {
            if ($p1index != $t) {
                if (isset($ph1tmp['remote-gateway']) && $ph1tmp['remote-gateway'] == $pconfig['remote-gateway'] && !isset($ph1tmp['disabled'])) {
                    $input_errors[] = sprintf(gettext('The remote gateway "%s" is already used by phase1 "%s".'), $pconfig['remote-gateway'], $ph1tmp['descr']);
                }
            }
            $t++;
        }
    }

    if ($pconfig['interface'] == 'any' && $pconfig['myid_type'] == "myaddress") {
        $input_errors[] = gettext("Please select an identifier (My Identifier) other then 'any' when selecting 'Any' interface");
    } elseif ($pconfig['myid_type'] == "address" && $pconfig['myid_data'] == "") {
        $input_errors[] = gettext("Please enter an address for 'My Identifier'");
    } elseif ($pconfig['myid_type'] == "keyid tag" && $pconfig['myid_data'] == "") {
        $input_errors[] = gettext("Please enter a keyid tag for 'My Identifier'");
    } elseif ($pconfig['myid_type'] == "fqdn" && $pconfig['myid_data'] == "") {
        $input_errors[] = gettext("Please enter a fully qualified domain name for 'My Identifier'");
    } elseif ($pconfig['myid_type'] == "user_fqdn" && $pconfig['myid_data'] == "") {
        $input_errors[] = gettext("Please enter a user and fully qualified domain name for 'My Identifier'");
    } elseif ($pconfig['myid_type'] == "dyn_dns" && $pconfig['myid_data'] == "") {
        $input_errors[] = gettext("Please enter a dynamic domain name for 'My Identifier'");
    } elseif ((($pconfig['myid_type'] == "address") && !is_ipaddr($pconfig['myid_data']))) {
        $input_errors[] = gettext("A valid IP address for 'My identifier' must be specified.");
    } elseif ((($pconfig['myid_type'] == "fqdn") && !is_domain($pconfig['myid_data']))) {
        $input_errors[] = gettext("A valid domain name for 'My identifier' must be specified.");
    } elseif ($pconfig['myid_type'] == "fqdn" && !is_domain($pconfig['myid_data'])) {
        $input_errors[] = gettext("A valid FQDN for 'My identifier' must be specified.");
    } elseif ($pconfig['myid_type'] == "user_fqdn") {
        $user_fqdn = explode("@", $pconfig['myid_data']);
        if (is_domain($user_fqdn[1]) == false) {
            $input_errors[] = gettext("A valid User FQDN in the form of user@my.domain.com for 'My identifier' must be specified.");
        }
    } elseif ($pconfig['myid_type'] == "dyn_dns") {
        if (is_domain($pconfig['myid_data']) == false) {
            $input_errors[] = gettext("A valid Dynamic DNS address for 'My identifier' must be specified.");
        }
    }

    // Only enforce peer ID if we are not dealing with a pure-psk mobile config.
    if (!(($pconfig['authentication_method'] == "pre_shared_key") && !empty($pconfig['mobile']))) {
        if ($pconfig['peerid_type'] == "address" and $pconfig['peerid_data'] == "") {
            $input_errors[] = gettext("Please enter an address for 'Peer Identifier'");
        }
        if ($pconfig['peerid_type'] == "keyid tag" and $pconfig['peerid_data'] == "") {
            $input_errors[] = gettext("Please enter a keyid tag for 'Peer Identifier'");
        }
        if ($pconfig['peerid_type'] == "fqdn" and $pconfig['peerid_data'] == "") {
            $input_errors[] = gettext("Please enter a fully qualified domain name for 'Peer Identifier'");
        }
        if ($pconfig['peerid_type'] == "user_fqdn" and $pconfig['peerid_data'] == "") {
            $input_errors[] = gettext("Please enter a user and fully qualified domain name for 'Peer Identifier'");
        }
        if ((($pconfig['peerid_type'] == "address") && !is_ipaddr($pconfig['peerid_data']))) {
            $input_errors[] = gettext("A valid IP address for 'Peer identifier' must be specified.");
        }
        if ((($pconfig['peerid_type'] == "fqdn") && !is_domain($pconfig['peerid_data']))) {
            $input_errors[] = gettext("A valid domain name for 'Peer identifier' must be specified.");
        }
        if ($pconfig['peerid_type'] == "fqdn") {
            if (is_domain($pconfig['peerid_data']) == false) {
                $input_errors[] = gettext("A valid FQDN for 'Peer identifier' must be specified.");
            }
        }
        if ($pconfig['peerid_type'] == "user_fqdn") {
            $user_fqdn = explode("@", $pconfig['peerid_data']);
            if (is_domain($user_fqdn[1]) == false) {
                $input_errors[] = gettext("A valid User FQDN in the form of user@my.domain.com for 'Peer identifier' must be specified.");
            }
        }
    }

    if (!empty($pconfig['closeaction']) && !in_array($pconfig['closeaction'], ['clear', 'hold', 'restart'])) {
        $input_errors[] = gettext('Invalid argument for close action.');
    }

    if (!empty($pconfig['dpd_enable'])) {
        if (!is_numeric($pconfig['dpd_delay'])) {
            $input_errors[] = gettext("A numeric value must be specified for DPD delay.");
        }
        if (!is_numeric($pconfig['dpd_maxfail'])) {
            $input_errors[] = gettext("A numeric value must be specified for DPD retries.");
        }
        if (!empty($pconfig['dpd_action']) && !in_array($pconfig['dpd_action'], array("restart", "clear"))) {
            $input_errors[] = gettext('Invalid argument for DPD action.');
        }
    }

    if (!empty($pconfig['iketype']) && !in_array($pconfig['iketype'], array("ike", "ikev1", "ikev2"))) {
        $input_errors[] = gettext('Invalid argument for key exchange protocol version.');
    }

    /* build our encryption algorithms array */
    if (!isset($pconfig['encryption-algorithm']) || !is_array($pconfig['encryption-algorithm'])) {
        $pconfig['encryption-algorithm'] = array();
    }
    $pconfig['encryption-algorithm']['name'] = $pconfig['ealgo'];
    if (!empty($pconfig['ealgo_keylen'])) {
        $pconfig['encryption-algorithm']['keylen'] = $pconfig['ealgo_keylen'];
    }

    if (empty($pconfig['hash-algorithm'])) {
        $input_errors[] = gettext("At least one hashing algorithm needs to be selected.");
        $pconfig['hash-algorithm'] = array();
    }

    if (empty($pconfig['dhgroup'])) {
        $pconfig['dhgroup'] = array();
    }

    foreach (ipsec_p1_ealgos() as $algo => $algodata) {
        if (!empty($pconfig['iketype']) && !empty($pconfig['encryption-algorithm']['name']) && !empty($algodata['iketype'])
          && $pconfig['iketype'] != $algodata['iketype'] && $pconfig['encryption-algorithm']['name'] == $algo) {
            $input_errors[] = sprintf(gettext("%s can only be used with IKEv2 type VPNs."), $algodata['name']);
        }
    }

    if (!empty($pconfig['ikeid']) && !empty($pconfig['installpolicy'])) {
        foreach ($config['ipsec']['phase2'] as $phase2ent) {
            if ($phase2ent['ikeid'] == $pconfig['ikeid'] && $phase2ent['mode'] == 'route-based') {
                $input_errors[] = gettext(
                    "Install policy on phase1 is not a valid option when using Route-based phase 2 entries."
                );
                break;
            }
        }
    }

    if (count($input_errors) == 0) {
        $copy_fields = "ikeid,iketype,interface,mode,protocol,myid_type,myid_data
        ,peerid_type,peerid_data,encryption-algorithm,margintime,rekeyfuzz,inactivity_timeout,keyingtries
        ,lifetime,pre-shared-key,certref,caref,authentication_method,descr,local-kpref,peer-kpref
        ,nat_traversal,auto,mobike,closeaction";

        foreach (explode(",",$copy_fields) as $fieldname) {
            $fieldname = trim($fieldname);
            if(!empty($pconfig[$fieldname])) {
                $ph1ent[$fieldname] = $pconfig[$fieldname];
            }
        }

        foreach (array('authservers', 'dhgroup', 'hash-algorithm') as $fieldname) {
            if (!empty($pconfig[$fieldname])) {
                $ph1ent[$fieldname] = implode(',', $pconfig[$fieldname]);
            }
        }

        $ph1ent['disabled'] = !empty($pconfig['disabled']);
        $ph1ent['sha256_96'] = !empty($pconfig['sha256_96']);
        $ph1ent['noinstallpolicy'] = empty($pconfig['installpolicy']); // XXX: reversed
        $ph1ent['private-key'] =isset($pconfig['privatekey']) ? base64_encode($pconfig['privatekey']) : null;
        if (!empty($pconfig['mobile'])) {
            $ph1ent['mobile'] = true;
        } else {
            $ph1ent['remote-gateway'] = $pconfig['remote-gateway'];
        }
        if (isset($pconfig['reauth_enable'])) {
            $ph1ent['reauth_enable'] = true;
        }
        if (isset($pconfig['rekey_enable'])) {
            $ph1ent['rekey_enable'] = true;
        }

        if (isset($pconfig['tunnel_isolation'])) {
            $ph1ent['tunnel_isolation'] = true;
        }

        if (isset($pconfig['rightallowany'])) {
            $ph1ent['rightallowany'] = true;
        }

        if (isset($pconfig['dpd_enable'])) {
            $ph1ent['dpd_delay'] = $pconfig['dpd_delay'];
            $ph1ent['dpd_maxfail'] = $pconfig['dpd_maxfail'];
            $ph1ent['dpd_action'] = $pconfig['dpd_action'];
        }

        /* generate unique phase1 ikeid */
        if ($ph1ent['ikeid'] == 0) {
            $ph1ent['ikeid'] = ipsec_ikeid_next();
        }

        if (isset($p1index) && isset($a_phase1[$p1index])) {
            $a_phase1[$p1index] = $ph1ent;
        } else {
            if (!empty($pconfig['clone_phase2']) && !empty($a_phase1[$_GET['dup']])
              && !empty($config['ipsec']['phase2'])) {
                // clone phase 2 entries in disabled state if requested.
                $prev_ike_id = $a_phase1[$_GET['dup']]['ikeid'];
                foreach ($config['ipsec']['phase2'] as $phase2ent) {
                    if ($phase2ent['ikeid'] == $prev_ike_id) {
                        $new_phase2 = $phase2ent;
                        $new_phase2['disabled'] = true;
                        $new_phase2['uniqid'] = uniqid();
                        $new_phase2['ikeid'] = $ph1ent['ikeid'];
                        $config['ipsec']['phase2'][] = $new_phase2;
                    }
                }
            }
            $a_phase1[] = $ph1ent;
        }

        /* if the remote gateway changed and the interface is not WAN then remove route */
        if ($pconfig['interface'] != 'wan') {
            if ($old_ph1ent['remote-gateway'] != $pconfig['remote-gateway']) {
                /* XXX does this even apply? only use of system.inc at the top! */
                system_host_route($old_ph1ent['remote-gateway'], $old_ph1ent['remote-gateway'], true, false);
            }
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
        $("#iketype").change(function(){
            if (['ike', 'ikev2'].includes($(this).val())) {
                $("#mode").prop( "disabled", true );
                $("#mode_tr").hide();
            } else {
                $("#mode").prop( "disabled", false );
                $("#mode_tr").show();
            }
            $( window ).resize(); // call window resize, which will re-apply zebra
        });
        $("#iketype").change();
        $("#myid_type").change(function(){
            if ($("#myid_type").val() == 'myaddress') {
                $("#myid_data").removeClass('show');
                $("#myid_data").addClass('hidden');
            } else {
                $("#myid_data").removeClass('hidden');
                $("#myid_data").addClass('show');
            }
        });
        $("#myid_type").change();

        $("#peerid_type").change(function(){
            if ($("#peerid_type").val() == 'peeraddress') {
               $("#peerid_data").removeClass('show');
               $("#peerid_data").addClass('hidden');
            } else {
               $("#peerid_data").removeClass('hidden');
               $("#peerid_data").addClass('show');
            }
        });
        $("#peerid_type").change();

        $("#authentication_method").change(function(){
            $(".auth_opt").hide();
            $(".auth_opt :input").prop( "disabled", true );
            switch ($("#authentication_method").val()) {
                case 'eap-tls':
                case 'psk_eap-tls':
                case 'eap-mschapv2':
                case 'rsa_eap-mschapv2':
                    $(".auth_eap_tls").show();
                    $(".auth_eap_tls :input").prop( "disabled", false );
                    break;
                case 'eap-radius':
                    $(".auth_eap_tls").show();
                    $(".auth_eap_tls :input").prop( "disabled", false );
                    $(".auth_eap_radius").show();
                    $(".auth_eap_radius :input").prop( "disabled", false );
                    break;
                case 'pre_shared_key':
                    if ($("#mobile").val() == undefined) {
                        $(".auth_psk").show();
                        $(".auth_psk :input").prop( "disabled", false );
                    }
                    break;
                case 'hybrid_rsa_server':
                    $('.auth_eap_tls').show();
                    $('.auth_eap_tls :input').prop('disabled', false);
                    break;
                case 'xauth_rsa_server':
                case 'rsasig':
                case 'rsa_eap-mschapv2':
                    $(".auth_eap_tls_caref").show();
                    $(".auth_eap_tls_caref :input").prop( "disabled", false );
                    $(".auth_eap_tls").show();
                    $(".auth_eap_tls :input").prop( "disabled", false );
                    break;
                case "pubkey":
                    $(".auth_pubkey").show();
                    $(".auth_pubkey :input").prop("disabled", false);
                    break;
                default: /* psk modes*/
                    $(".auth_psk").show();
                    $(".auth_psk :input").prop( "disabled", false );
                    break;
            }
            $(".selectpicker").selectpicker('refresh');
        });
        $("#authentication_method").change();

        $("#ealgo").change(function(){
            if ($("#ealgo option:selected").data('lo') != "") {
                $("#ealgo_keylen").show();
                $("#ealgo_keylen").prop('disabled', false);
                $("#ealgo_keylen option").remove();
                for (var i = $("#ealgo option:selected").data('lo'); i <= $("#ealgo option:selected").data('hi'); i += $("#ealgo option:selected").data('step')) {
                    $("#ealgo_keylen").append($("<option/>").attr('value',i).text(i));
                }
                $("#ealgo_keylen").val($("#ealgo").data("default-keylen"));
            } else {
                $("#ealgo_keylen").hide();
                $("#ealgo_keylen").prop('disabled', true);
            }
        });
        $("#ealgo").change();

        $("#dpd_enable").change(function(){
            if ($(this).prop('checked')) {
                $("#opt_dpd").show();
                if ($("#dpd_delay").val() == "") {
                    $("#dpd_delay").val("10");
                }
                if ($("#dpd_maxfail").val() == "") {
                    $("#dpd_maxfail").val("5");
                }
            } else {
                $("#opt_dpd").hide();
            }
        });
        $("#dpd_enable").change();
    });
</script>

<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
<?php
    if (isset($input_errors) && count($input_errors) > 0) {
        print_input_errors($input_errors);
    }
?>

      <section class="col-xs-12">
        <div class="tab-content content-box col-xs-12">
          <form method="post" name="iform" id="iform">
            <div class="table-responsive">
              <table class="table table-striped opnsense_standard_table_form">
                <tbody>
                  <tr>
                    <td style="width:22%"><b><?=gettext("General information"); ?></b></td>
                    <td style="width:78%; text-align:right">
                      <small><?=gettext("full help"); ?> </small>
                      <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                    </td>
                  </tr>
<?php
                  if (!empty($_GET['dup'])):?>
                  <tr>
                    <td><a id="help_for_clone_phase2" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Clone phase2"); ?></td>
                    <td>
                      <input name="clone_phase2" type="checkbox" id="clone_phase2" value="yes" <?=!empty($pconfig['clone_phase2'])?"checked=\"checked\"":"";?> />
                      <div class="hidden" data-for="help_for_clone_phase2">
                        <?=gettext("Clone related phase 2 entries as well, remember to change the networks. All phase 2 entries will be added in disabled state"); ?>
                      </div>
                    </td>
                  </tr>
<?php
                  endif;?>
                  <tr>
                    <td style="width:22%"><a id="help_for_disabled" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Disabled"); ?></td>
                    <td>
                      <input name="disabled" type="checkbox" id="disabled" value="yes" <?=!empty($pconfig['disabled'])?"checked=\"checked\"":"";?> />
                      <?= gettext('Disable this phase1 entry') ?>
                      <div class="hidden" data-for="help_for_disabled">
                        <?= gettext('Set this option to disable this phase1 without removing it from the list.') ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_auto" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Connection method"); ?></td>
                    <td>

                      <select name="auto">
                        <option value="" <?=empty($pconfig['auto']) ?  "selected=\"selected\"" : ""; ?>><?=gettext("default");?></option>
                        <option value="add" <?=$pconfig['auto'] == "add" ?  "selected=\"selected\"" : ""; ?>><?=gettext("Respond only");?></option>
                        <option value="route" <?=$pconfig['auto'] == "route" ?  "selected=\"selected\"" : ""; ?>><?=gettext("Start on traffic");?></option>
                        <option value="start" <?=$pconfig['auto'] == "start" ?  "selected=\"selected\"" : ""; ?>><?=gettext("Start immediate");?></option>
                      </select>
                      <div class="hidden" data-for="help_for_auto">
                        <?=gettext("Choose the connect behaviour here, when using CARP you might want to consider the 'Respond only' option here (wait for the other side to connect)."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_iketype" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Key Exchange version"); ?></td>
                    <td>

                      <select name="iketype" id="iketype">
<?php
                      $keyexchange = array("ike" => "auto", "ikev1" => "V1", "ikev2" => "V2");
                      foreach ($keyexchange as $kidx => $name) :
                        ?>
                        <option value="<?=$kidx;?>" <?= $kidx == $pconfig['iketype'] ? "selected=\"selected\"" : "";?> >
                            <?=$name;?>
                        </option>
<?php                endforeach;
?>
                      </select>
                      <div class="hidden" data-for="help_for_iketype">
                        <?=gettext("Select the KeyExchange Protocol version to be used. Usually known as IKEv1 or IKEv2."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_protocol" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Internet Protocol"); ?></td>
                    <td>
                      <select name="protocol">
                      <?php
                      $protocols = array("inet" => "IPv4", "inet6" => "IPv6");
                      foreach ($protocols as $protocol => $name) :
                      ?>
                        <option value="<?=$protocol;?>"  <?=$protocol == $pconfig['protocol'] ? "selected=\"selected\"" : "";?> >
                            <?=$name?>
                        </option>
<?php                endforeach;
?>
                      </select>
                      <div class="hidden" data-for="help_for_protocol">
                        <?=gettext("Select the Internet Protocol family from this dropdown."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_interface" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Interface"); ?></td>
                    <td>
                      <select name="interface">
<?php
                      $interfaces = get_configured_interface_with_descr();
                      $carplist = get_configured_carp_interface_list();
                      foreach ($carplist as $cif => $carpip) {
                          $interfaces[$cif] = $carpip." (".get_vip_descr($carpip).")";
                      }

                      $aliaslist = get_configured_ip_aliases_list();
                      foreach ($aliaslist as $aliasip => $aliasif) {
                          $interfaces[$aliasip] = $aliasip." (".get_vip_descr($aliasip).")";
                      }
                      foreach ($interfaces as $iface => $ifacename) :
?>
                        <option value="<?=$iface;?>" <?= $iface == $pconfig['interface'] ? "selected=\"selected\"" : "" ?> >
                            <?=htmlspecialchars($ifacename);?>
                        </option>
<?php                  endforeach;
?>
                        <option value="any" <?= $pconfig['interface'] == "any" ? "selected=\"selected\"" : "" ?>>
                            <?=gettext("Any");?>
                        </option>
                      </select>
                      <div class="hidden" data-for="help_for_interface">
                        <?=gettext("Select the interface for the local endpoint of this phase1 entry."); ?>
                      </div>
                    </td>
                  </tr>
<?php if (empty($pconfig['mobile'])): ?>
                  <tr>
                    <td><a id="help_for_remotegw" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Remote gateway"); ?></td>
                    <td>
                      <input name="remote-gateway" type="text" id="remotegw" size="28" value="<?=$pconfig['remote-gateway'];?>" />
                      <div class="hidden" data-for="help_for_remotegw">
                        <?= gettext('Enter the public IP address or host name of the remote gateway.') ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_rightallowany" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Dynamic gateway') ?></td>
                    <td>
                      <input name="rightallowany" type="checkbox" id="rightallowany" value="yes" <?= !empty($pconfig['rightallowany']) ? 'checked="checked"' : '' ?>/>
                      <?= gettext('Allow any remote gateway to connect') ?>
                      <div class="hidden" data-for="help_for_rightallowany">
                        <?= gettext('Recommended for dynamic IP addresses that can be resolved by DynDNS at IPsec startup or update time.') ?>
                      </div>
                    </td>
                  </tr>
<?php endif ?>
                  <tr>
                    <td><a id="help_for_descr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description"); ?></td>
                    <td>
                      <input name="descr" type="text" id="descr" size="40" value="<?=$pconfig['descr'];?>" />
                      <div class="hidden" data-for="help_for_descr">
                        <?=gettext("You may enter a description here for your reference (not parsed)."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td colspan="2">&nbsp;</td>
                  </tr>
                  <tr>
                    <td colspan="2"><b><?=gettext("Phase 1 proposal (Authentication)"); ?></b></td>
                  </tr>
                  <tr>
                    <td><a id="help_for_authmethod" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Authentication method"); ?></td>
                    <td>
                      <select name="authentication_method" id="authentication_method">
<?php
                      foreach (ipsec_p1_authentication_methods() as $method_type => $method_params) :
                          if (empty($pconfig['mobile']) && $method_params['mobile']) {
                              continue;
                          }
                        ?>
                          <option value="<?=$method_type;?>" <?= $method_type == $pconfig['authentication_method'] ? "selected=\"selected\"" : "";?> >
        <?=$method_params['name'];?>
                          </option>
<?php                endforeach;
?>
                      </select>
                      <div class="hidden" data-for="help_for_authmethod">
                        <?=gettext("Must match the setting chosen on the remote side."); ?><br />
                        <?=sprintf(gettext("If you select EAP-RADIUS, you must define your RADIUS servers on the %sServers%s page."), '<a href="/system_authservers.php">', '</a>'); ?>
                      </div>
                    </td>
                  </tr>
                  <tr id="mode_tr">
                    <td><a id="help_for_mode" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Negotiation mode"); ?></td>
                    <td>
                      <select id="mode" name="mode">
                      <?php
                      $modes = array("main" => "Main", "aggressive" => "Aggressive");
                      foreach ($modes as $mode => $mdescr) :
?>
      <option value="<?=$mode;?>" <?= $mode == $pconfig['mode'] ? "selected=\"selected\"" : "" ;?> >
                            <?=$mdescr;?>
                        </option>
<?php                endforeach;
?>
                      </select>
                      <div class="hidden" data-for="help_for_mode">
                        <?=gettext("Aggressive is more flexible, but less secure."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("My identifier"); ?></td>
                    <td>
                      <select name="myid_type" id="myid_type">
<?php
                      $my_identifier_list = array(
                        'myaddress' => array( 'desc' => gettext('My IP address'), 'mobile' => true ),
                        'address' => array( 'desc' => gettext('IP address'), 'mobile' => true ),
                        'fqdn' => array( 'desc' => gettext('Distinguished name'), 'mobile' => true ),
                        'user_fqdn' => array( 'desc' => gettext('User distinguished name'), 'mobile' => true ),
                        'asn1dn' => array( 'desc' => gettext('ASN.1 distinguished Name'), 'mobile' => true ),
                        'keyid tag' => array( 'desc' => gettext('KeyID tag'), 'mobile' => true ),
                        'dyn_dns' => array( 'desc' => gettext('Dynamic DNS'), 'mobile' => true ),
                        'auto' => array( 'desc' => gettext('Automatic'), 'mobile' => true ));
                      foreach ($my_identifier_list as $id_type => $id_params) :
?>
                        <option value="<?=$id_type;?>" <?php if ($id_type == $pconfig['myid_type']) {
                                                    echo "selected=\"selected\"";
} ?>>
                          <?=$id_params['desc'];?>
                        </option>
                      <?php
endforeach; ?>
                      </select>
                      <div id="myid_data">
                        <input name="myid_data" type="text" size="30" value="<?=$pconfig['myid_data'];?>" />
                      </div>
                    </td>
                  </tr>
<?php
                  if (empty($pconfig['mobile'])):?>
                  <tr class="auth_opt auth_eap_tls auth_psk auth_pubkey">
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Peer identifier"); ?></td>
                    <td>
                      <select name="peerid_type" id="peerid_type">
<?php
                      $peer_identifier_list = array(
                        'peeraddress' => array( 'desc' => gettext('Peer IP address'), 'mobile' => false ),
                        'address' => array( 'desc' => gettext('IP address'), 'mobile' => false ),
                        'fqdn' => array( 'desc' => gettext('Distinguished name'), 'mobile' => true ),
                        'user_fqdn' => array( 'desc' => gettext('User distinguished name'), 'mobile' => true ),
                        'asn1dn' => array( 'desc' => gettext('ASN.1 distinguished Name'), 'mobile' => true ),
                        'keyid tag' => array( 'desc' =>gettext('KeyID tag'), 'mobile' => true ),
                        'auto' => array( 'desc' => gettext('Automatic'), 'mobile' => true ));
                      foreach ($peer_identifier_list as $id_type => $id_params) :
                        if (!empty($pconfig['mobile']) && !$id_params['mobile']) {
                          continue;
                        }
?>
                        <option value="<?=$id_type;?>" <?= $id_type == $pconfig['peerid_type'] ? "selected=\"selected\"" : "";?> >
        <?=$id_params['desc'];?>
                        </option>
<?php                endforeach;
?>
                      </select>
                      <input name="peerid_data" type="text" id="peerid_data" size="30" value="<?=$pconfig['peerid_data'];?>" />
<?php if (!empty($pconfig['mobile'])) {
?>
                      <small><?=gettext("NOTE: This is known as the \"group\" setting on some VPN client implementations."); ?></small>
                    <?php
} ?>
                    </td>
                  </tr>
<?php
                  endif;?>
                  <tr class="auth_opt auth_psk">
                    <td><a id="help_for_psk" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Pre-Shared Key"); ?></td>
                    <td>
                      <input name="pre-shared-key" type="text" id="pskey" size="40"
                             value="<?= $pconfig['authentication_method'] == "pre_shared_key" || $pconfig['authentication_method'] == "xauth_psk_server" ? $pconfig['pre-shared-key'] : "";?>" />
                      <div class="hidden" data-for="help_for_psk">
                        <?=gettext("Input your Pre-Shared Key string."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr class="auth_opt auth_eap_tls">
                    <td><a id="help_for_certref" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("My Certificate"); ?></td>
                    <td>
                      <select name="certref">
<?php
                      if (isset($config['cert'])) :
                        foreach ($config['cert'] as $cert) :
?>
                        <option value="<?=$cert['refid'];?>" <?= isset($pconfig['certref']) && $pconfig['certref'] == $cert['refid'] ? "selected=\"selected\"" : ""?>>
                          <?=$cert['descr'];?>
                        </option>
<?php                endforeach;
                      endif;
?>
                      </select>
                      <div class="hidden" data-for="help_for_certref">
                        <?=gettext("Select a certificate previously configured in the Certificate Manager."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr class="auth_opt auth_eap_tls_caref">
                    <td><a id="help_for_caref" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("My Certificate Authority"); ?></td>
                    <td>
                      <select name="caref">
                      <?php
                    $config__ca = isset($config['ca']) ? $config['ca'] : array();
                        foreach ($config__ca as $ca) :
                            $selected = "";
                            if ($pconfig['caref'] == $ca['refid']) {
                                $selected = "selected=\"selected\"";
                            }
                        ?>
                          <option value="<?=$ca['refid'];?>" <?= isset($pconfig['caref']) && $pconfig['caref'] == $ca['refid'] ? "selected=\"selected\"":"";?>>
                            <?=htmlspecialchars($ca['descr']);?>
                          </option>
<?php                endforeach;
?>
                      </select>
                      <div class="hidden" data-for="help_for_caref">
                        <?=gettext("Select a certificate authority previously configured in the Certificate Manager."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr class="auth_opt auth_pubkey">
                      <td><a id="help_for_pubkey_local" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Local Key Pair"); ?></td>
                      <td>
                          <select name="local-kpref">
                              <?php
                              foreach (ipsec_keypairs() as $keypair_uuid => $keypair) :
                                  if ($keypair['publicKey'] and $keypair['privateKey']) :
                                      ?>
                                      <option value="<?= $keypair_uuid; ?>" <?= isset($pconfig['local-kpref']) && $pconfig['local-kpref'] == $keypair_uuid ? "selected=\"selected\"" : "" ?>>
                                          <?= $keypair['name']; ?>
                                      </option>
                                  <?php
                                  endif;
                              endforeach;
                              ?>
                          </select>
                          <div class="hidden" data-for="help_for_pubkey_local">
                              <?= gettext("Select a local key pair previously configured at IPsec \\ Key Pairs."); ?>
                              <br />
                              <?= gettext("This selection will only display key pairs which have both a public and private key."); ?>
                          </div>
                      </td>
                  </tr>
                  <tr class="auth_opt auth_pubkey">
                      <td><a id="help_for_pubkey_peer" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Peer Key Pair"); ?></td>
                      <td>
                          <select name="peer-kpref">
                              <?php
                              foreach (ipsec_keypairs() as $keypair_uuid => $keypair) :
                                  if ($keypair['publicKey']) :
                                      ?>
                                      <option value="<?= $keypair_uuid; ?>" <?= isset($pconfig['peer-kpref']) && $pconfig['peer-kpref'] == $keypair_uuid ? "selected=\"selected\"" : "" ?>>
                                          <?= $keypair['name']; ?>
                                      </option>
                                  <?php
                                  endif;
                              endforeach;
                              ?>
                          </select>
                          <div class="hidden" data-for="help_for_pubkey_peer">
                              <?=gettext("Select a peer key pair previously configured at IPsec \\ Key Pairs."); ?>
                              <br />
                              <?= gettext("This selection will only display key pairs which have a public key."); ?>
                          </div>
                      </td>
                  </tr>
                  <tr class="auth_opt auth_eap_radius">
                    <td><a id="help_for_authservers" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Radius servers"); ?></td>
                    <td>
                      <select name="authservers[]"  multiple="multiple" size="3" class="selectpicker" data-live-search="true">
<?php
                      foreach (auth_get_authserver_list() as $auth_server):
                        if ($auth_server['type'] == "radius"):?>
                        <option value="<?=$auth_server['name'];?>" <?=in_array($auth_server['name'],$pconfig['authservers']) ? 'selected="selected"' : "";?>>
                          <?=htmlspecialchars($auth_server['name']);?>
                        </option>
<?php
                        endif;
                      endforeach;?>
                      </select>
                      <div class="hidden" data-for="help_for_authservers">
                        <?=gettext("Select authentication servers to use."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td colspan="2"><b><?=gettext("Phase 1 proposal (Algorithms)"); ?></b></td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Encryption algorithm"); ?></td>
                    <td>
                      <select name="ealgo" id="ealgo" data-default-keylen="<?=$pconfig['encryption-algorithm']['keylen'];?>">
<?php
                      foreach (ipsec_p1_ealgos() as $algo => $algodata) :
                      ?>
                        <option value="<?=$algo;?>" <?= $algo == $pconfig['encryption-algorithm']['name'] ? "selected=\"selected\"" : "" ;?>
                                data-hi="<?=$algodata['keysel']['hi'];?>"
                                data-lo="<?=$algodata['keysel']['lo'];?>"
                                data-step="<?=$algodata['keysel']['step'];?>"
                            >
                            <?=$algodata['name'];?>
                        </option>
<?php
                      endforeach;
?>
                      </select>

                      <select name="ealgo_keylen" id="ealgo_keylen" width="30">
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_halgo" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Hash algorithm"); ?></td>
                    <td>
                      <select name="hash-algorithm[]" class="selectpicker" multiple="multiple">
                      <?php
                      $p1_halgos = array(
                        'md5' => 'MD5',
                        'sha1' => 'SHA1',
                        'sha256' => 'SHA256',
                        'sha384' => 'SHA384',
                        'sha512' => 'SHA512',
                        'aesxcbc' => 'AES-XCBC'
                      );
                      foreach ($p1_halgos as $algo => $algoname) :
?>
                        <option value="<?= html_safe($algo) ?>" <?= in_array($algo, $pconfig['hash-algorithm']) ? 'selected="selected"' : '' ?>>
                          <?= html_safe($algoname) ?>
                        </option>
<?php                endforeach;
?>
                      </select>
                      <div class="hidden" data-for="help_for_halgo">
                        <?=gettext("Must match the setting chosen on the remote side."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_dhgroup" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("DH key group"); ?></td>
                    <td>
                      <select name="dhgroup[]" class="selectpicker" multiple="multiple">
<?php
                      $p1_dhgroups = array(
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
                      foreach ($p1_dhgroups as $keygroup => $keygroupname):
?>
                        <option value="<?= html_safe($keygroup) ?>" <?= in_array($keygroup, $pconfig['dhgroup']) ? 'selected="selected"' : '' ?>>
                          <?= html_safe($keygroupname) ?>
                        </option>
<?php                endforeach;
?>
                      </select>
                      <div class="hidden" data-for="help_for_dhgroup">
                        <?=gettext("Must match the setting chosen on the remote side."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_lifetime" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?=gettext("Lifetime"); ?></td>
                    <td>
                      <input name="lifetime" type="text" id="lifetime" size="20" value="<?=$pconfig['lifetime'];?>" />
                      <div class="hidden" data-for="help_for_lifetime">
                        <?=gettext("seconds"); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td colspan="2"><b><?=gettext("Advanced Options"); ?></b></td>
                  </tr>
                  <tr>
                    <td><a id="help_for_installpolicy" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?=gettext("Install policy");?></td>
                    <td>
                      <input name="installpolicy" type="checkbox" id="rekey_enable" value="yes" <?= !empty($pconfig['installpolicy']) ? "checked=\"checked\"" : ""; ?> />
                      <div class="hidden" data-for="help_for_installpolicy">
                        <?=gettext("Decides whether IPsec policies are installed in the kernel by the charon daemon for a given connection. ".
                                   "When using route-based mode (VTI) this needs to be disabled."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_rekey_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?=gettext("Disable Rekey");?></td>
                    <td>
                      <input name="rekey_enable" type="checkbox" id="rekey_enable" value="yes" <?= !empty($pconfig['rekey_enable']) ? "checked=\"checked\"" : ""; ?> />
                      <div class="hidden" data-for="help_for_rekey_enable">
                        <?=gettext("Whether a connection should be renegotiated when it is about to expire."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_reauth_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?=gettext("Disable Reauth");?></td>
                    <td>
                      <input name="reauth_enable" type="checkbox" id="reauth_enable" value="yes" <?= !empty($pconfig['reauth_enable']) ? "checked=\"checked\"" : "";?> />
                      <div class="hidden" data-for="help_for_reauth_enable">
                        <?=gettext("Whether rekeying of an IKE_SA should also reauthenticate the peer. In IKEv1, reauthentication is always done."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_tunnel_isolation" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Tunnel Isolation') ?></td>
                    <td>
                      <input name="tunnel_isolation" type="checkbox" id="tunnel_isolation" value="yes" <?= !empty($pconfig['tunnel_isolation']) ? 'checked="checked"' : '' ?>/>
                      <div class="hidden" data-for="help_for_tunnel_isolation">
                        <?= gettext('This option will create a tunnel for each phase 2 entry for IKEv2 interoperability with e.g. FortiGate devices.') ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_sha256_96" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('SHA256 96 Bit Truncation') ?></td>
                    <td>
                      <input name="sha256_96" type="checkbox" id="sha256_96" value="yes" <?= !empty($pconfig['sha256_96']) ? 'checked="checked"' : '' ?>/>
                      <div class="hidden" data-for="help_for_sha256_96">
                        <?= gettext(
                          "For compatibility with implementations that incorrectly use 96-bit (instead of 128-bit) truncation this ".
                          "option may be enabled to configure the shorter truncation length. This is not negotiated, so this only works ".
                          "with peers that use the incorrect truncation length (or have this option enabled), e.g. Forcepoint Sidewinder."
                        ) ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_nat_traversal" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?=gettext("NAT Traversal"); ?></td>
                    <td>
                      <select name="nat_traversal" class="selectpicker">
                        <option value="off" <?= isset($pconfig['nat_traversal']) && $pconfig['nat_traversal'] == "off" ? "selected=\"selected\"" :"" ;?> >
                          <?=gettext("Disable"); ?>
                        </option>
                        <option value="on" <?= isset($pconfig['nat_traversal']) && $pconfig['nat_traversal'] == "on" ? "selected=\"selected\"" :"" ;?> >
                          <?=gettext("Enable"); ?>
                        </option>
                        <option value="force" <?= isset($pconfig['nat_traversal']) && $pconfig['nat_traversal'] == "force" ? "selected=\"selected\"" :"" ;?> >
                          <?=gettext("Force"); ?>
                        </option>
                      </select>
                      <div class="hidden" data-for="help_for_nat_traversal">
                          <?=gettext("Set this option to enable the use of NAT-T (i.e. the encapsulation of ESP in UDP packets) if needed, " .
                                                  "which can help with clients that are behind restrictive firewalls."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_mobike" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?=gettext("Disable MOBIKE"); ?></td>
                    <td>
                      <input name="mobike" type="checkbox" id="mobike"  <?=!empty($pconfig['mobike']) ? "checked=\"checked\"":"";?> />
                      <div class="hidden" data-for="help_for_mobike">
                          <?=gettext("Disables the IKEv2 MOBIKE protocol defined by RFC 4555");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_closeaction" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?=gettext("Close Action"); ?></td>
                    <td>
                      <select name="closeaction" class="selectpicker">
                        <option value="" <?= empty($pconfig['closeaction']) ? "selected=\"selected\"" :"" ;?> >
                          <?=gettext("None"); ?>
                        </option>
                        <option value="clear" <?= $pconfig['closeaction'] == "clear" ? "selected=\"selected\"" :"" ;?> >
                          <?=gettext("Clear"); ?>
                        </option>
                        <option value="hold" <?= $pconfig['closeaction'] == "hold" ? "selected=\"selected\"" :"" ;?> >
                          <?=gettext("Hold"); ?>
                        </option>
                        <option value="restart" <?= $pconfig['closeaction'] == "restart" ? "selected=\"selected\"" :"" ;?> >
                          <?=gettext("Restart"); ?>
                        </option>
                      </select>
                      <div class="hidden" data-for="help_for_closeaction">
                          <?=gettext(
                            "Defines the action to take if the remote peer unexpectedly closes a CHILD_SA. ".
                            "A closeaction should not be used if the peer uses reauthentication or uniqueids checking, ".
                            "as these events might trigger the defined action when not desired. "
                          )?>
                          <br/></br>
                          <?=gettext(
                            "With clear the connection is closed with no further actions taken. ".
                            "hold installs a trap policy, which will catch matching traffic and tries to re-negotiate ".
                            "the connection on demand. restart will immediately trigger an attempt ".
                            "to re-negotiate the connection. The default is none and disables the close action."
                          )?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_dpd_enable" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?=gettext("Dead Peer Detection"); ?></td>
                    <td>
                      <input name="dpd_enable" type="checkbox" id="dpd_enable" value="yes" <?=!empty($pconfig['dpd_delay']) && !empty($pconfig['dpd_maxfail'])?"checked=\"checked\"":"";?> />
                      <div class="hidden" data-for="help_for_dpd_enable">
                        <?=gettext("Enable DPD"); ?>
                      </div>
                      <div id="opt_dpd">
                        <br />
                        <input name="dpd_delay" type="text" id="dpd_delay" size="5" value="<?=$pconfig['dpd_delay'];?>" />
                        <?=gettext("seconds"); ?>
                        <div class="hidden" data-for="help_for_dpd_enable">
                          <?=gettext("Delay between requesting peer acknowledgement."); ?>
                        </div>
                        <br />
                        <input name="dpd_maxfail" type="text" id="dpd_maxfail" size="5" value="<?=$pconfig['dpd_maxfail'];?>" />
                        <?=gettext("retries"); ?>
                        <div class="hidden" data-for="help_for_dpd_enable">
                          <?=gettext("Number of consecutive failures allowed before disconnect."); ?>
                        </div>
                        <br />
                        <select name="dpd_action" class="selectpicker">
                          <option value="" <?=empty($pconfig['dpd_action']) ?  "selected=\"selected\"" : ""; ?>><?=gettext("default");?></option>
                          <option value="restart" <?=$pconfig['dpd_action'] == "restart" ?  "selected=\"selected\"" : ""; ?>><?=gettext("Restart the tunnel");?></option>
                          <option value="clear" <?=$pconfig['dpd_action'] == "clear" ?  "selected=\"selected\"" : ""; ?>><?=gettext("Stop the tunnel");?></option>
                        </select>
                        <?=gettext("DPD action"); ?>
                        <div class="hidden" data-for="help_for_dpd_enable">
                          <?=gettext("Choose the behavior here what to do if a peer is detected to be unresponsive to DPD requests."); ?>
                        </div>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_inactivity_timeout" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?=gettext("Inactivity timeout"); ?></td>
                    <td>
                      <input name="inactivity_timeout" type="text" id="inactivity_timeout" value="<?=$pconfig['inactivity_timeout'];?>" />
                      <div class="hidden" data-for="help_for_inactivity_timeout">
                        <?=gettext("Time before closing inactive tunnels if they don't handle any traffic. (seconds)"); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_keyingtries" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?=gettext("Keyingtries"); ?></td>
                    <td>
                      <input name="keyingtries" type="text" id="keyingtries" value="<?=$pconfig['keyingtries'];?>" />
                      <div class="hidden" data-for="help_for_keyingtries">
                        <?=gettext(
                          "How many attempts should be made to negotiate a connection, or a replacement for one, before giving up (default 3). ".
                          "Leave empty for default, -1 for forever or any positive integer for the number of tries"
                        ); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_margintime" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?=gettext("Margintime"); ?></td>
                    <td>
                      <input name="margintime" type="text" id="margintime" value="<?=$pconfig['margintime'];?>" />
                      <div class="hidden" data-for="help_for_margintime">
                        <?=gettext("Time before SA expiry the rekeying should start. (seconds)"); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_rekeyfuzz" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>  <?=gettext("Rekeyfuzz"); ?></td>
                    <td>
                      <input name="rekeyfuzz" type="text" id="rekeyfuzz" value="<?=$pconfig['rekeyfuzz'];?>" />
                      <div class="hidden" data-for="help_for_rekeyfuzz">
                        <?=gettext("Percentage by which margintime is randomly increased (may exceed 100%). Randomization may be disabled by setting rekeyfuzz=0%."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td>&nbsp;</td>
                    <td>
                      <?php if (isset($p1index) && isset($config['ipsec']['phase1'][$p1index]) && !isset($_GET['dup'])) :
?>
                      <input name="p1index" type="hidden" value="<?=$p1index;?>" />
                      <?php
endif; ?>
                      <?php if (!empty($pconfig['mobile'])) :
?>
                      <input id="mobile" name="mobile" type="hidden" value="true" />
                      <?php
endif; ?>
                      <input name="ikeid" type="hidden" value="<?=$pconfig['ikeid'];?>" />
                      <input name="Submit" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save')); ?>" />
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </form>
        </div>
      </section>
    </div>
  </div>
</section>

<?php include("foot.inc");
