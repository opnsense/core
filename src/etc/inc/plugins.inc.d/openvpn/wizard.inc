<?php

/*
 * Copyright (C) 2010 Ermal Luçi
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

require_once("plugins.inc.d/openvpn.inc");
require_once("util.inc");

function cert_get_subject_hash($crt)
{
    $str_crt = base64_decode($crt);
    $inf_crt = openssl_x509_parse($str_crt);
    return $inf_crt['subject'];
}

function has_special_chars($text)
{
    return preg_match('/[^A-Za-z0-9 _-]/', $text);
}

function step1_submitphpaction()
{
    global $stepid, $config;

    if ($_POST['authtype'] == "local") {
        $stepid = 4;
        $config['wizardtemp']['step1']['type'] = "local";
    } elseif ($_POST['authtype'] == "ldap") {
        $stepid = 0;
    } elseif ($_POST['authtype'] == "radius") {
        $stepid = 2;
        $config['wizardtemp']['step1']['type'] = "radius";
        unset($config['wizardtemp']['step1']['uselist']);
    }
}

function step2_stepbeforeformdisplay()
{
    global $pkg, $stepid;

    $fields =& $pkg['step'][1]['fields']['field'];

    $found = false;
    $authlist = auth_get_authserver_list();
    $fields[1]['options']['option'] = array();
    foreach ($authlist as $key => $auth) {
        if ($auth['type'] != 'ldap') {
            continue;
        }
        $found = true;
        $opts = array();
        $opts['name'] = $auth['name'];
        $opts['value'] = $key;
        $fields[1]['options']['option'][] = $opts;
    }
    if ($found == false) {
        $stepid = 2;
    }
}

function step2_submitphpaction()
{
    global $stepid;

    if (isset($_POST['next'])) {
        $_POST['uselist'] = "";
        $stepid += 3;
    }
}

function step3_submitphpaction()
{
    global $stepid, $input_errors, $config;

    /* Default LDAP port is 389 for TCP and 636 for SSL */
    if (empty($_POST['port'])) {
        if ($_POST['transport'] == "tcp") {
            $config['wizardtemp']['step2']['port'] = 389;
        } elseif ($_POST['transport'] == "ssl") {
            $config['wizardtemp']['step2']['port'] = 636;
        }
    } elseif (!is_port($_POST['port'])) {
        $input_errors[] = gettext('Please enter a valid port number.');
        $stepid--;
        return;
    }

    $authcfg = auth_get_authserver($_POST['name']);
    if (
        empty($_POST['name']) || empty($_POST['ip']) || empty($_POST['transport']) ||
         empty($_POST['scope']) || empty($_POST['basedn']) || empty($_POST['authscope']) || empty($_POST['nameattr'])
    ) {
        $input_errors[] = gettext('Please enter all information for authentication server.');
        $stepid--;
    } elseif ($authcfg !== false && !empty($authcfg)) {
        $input_errors[] = gettext('Please choose a different name because an authentication ' .
            'server with this name already exists.');
        $stepid--;
    } elseif (!is_fqdn($_POST['ip']) && !is_ipaddr($_POST['ip'])) {
        $input_errors[] = gettext('Please enter a valid IP address or hostname for the authentication server.');
        $stepid--;
    } else {
        $config['wizardtemp']['step2']['uselist'] = "on";
        $_POST['uselist'] = "on";
        $stepid += 2;
    }
}

function step4_stepbeforeformdisplay()
{
    global $pkg, $stepid;

    $fields =& $pkg['step'][3]['fields']['field'];

    $found = false;
    $authlist = auth_get_authserver_list();
    $fields[1]['options']['option'] = array();
    foreach ($authlist as $key => $auth) {
        if ($auth['type'] != 'radius') {
            continue;
        }
        $found = true;
        $opts = array();
        $opts['name'] = $auth['name'];
        $opts['value'] = $key;
        $fields[1]['options']['option'][] = $opts;
    }
    if ($found == false) {
        $stepid = 4;
    }
}

function step4_submitphpaction()
{
    global $stepid;

    if (isset($_POST['next'])) {
        $_POST['uselist'] = "";
        $stepid++;
    }
}

function step5_submitphpaction()
{
    global $stepid, $input_errors, $config;

    /* Default RADIUS Auth port = 1812 */
    if (empty($_POST['port'])) {
        $config['wizardtemp']['step2']['port'] = 1812;
    } elseif (!is_port($_POST['port'])) {
        $input_errors[] = gettext('Please enter a valid port number.');
        $stepid--;
        return;
    }

    $authcfg = auth_get_authserver($_POST['name']);
    if (empty($_POST['name']) || empty($_POST['ip']) || empty($_POST['secret'])) {
        $input_errors[] = gettext('Please enter all information for authentication server.');
        $stepid--;
    } elseif ($authcfg !== false && !empty($authcfg)) {
        $input_errors[] = gettext('Please choose a different name because an authentication ' .
            'server with this name already exists.');
        $stepid--;
    } elseif (!is_fqdn($_POST['ip']) && !is_ipaddr($_POST['ip'])) {
        $input_errors[] = gettext('Please enter a valid IP address or hostname for the authentication server.');
        $stepid--;
    } else {
        $config['wizardtemp']['step2']['uselist'] = "on";
        $_POST['uselist'] = "on";
    }
}

function step6_stepbeforeformdisplay()
{
    global $stepid, $config;
    $no_internal_ca = true;

    if (empty($config['ca'])) {
        $stepid++;
    } else {
        foreach ($config['ca'] as $ca) {
            if (!empty($ca['prv'])) {
                $no_internal_ca = false;
                break;
            }
        }
        if ($no_internal_ca) {
            $stepid++;
        }
    }
}

function step6_submitphpaction()
{
    global $stepid, $config;

    if (isset($_POST['next'])) {
        $_POST['uselist'] = "";
        unset($config['wizardtemp']['step6']['uselist']);
        $stepid++;
    } else {
        $config['wizardtemp']['step6']['uselist'] = "on";
        $_POST['uselist'] = "on";
    }
}

function step7_submitphpaction()
{
    global $input_errors, $stepid, $_POST, $config;

    $canames = array();
    $cacns = array();
    if (is_array($config['ca'])) {
        foreach ($config['ca'] as $ca) {
            $canames[] = $ca['descr'];
            $cainfo = cert_get_subject_hash($ca['crt']);
            $cacns[] = $cainfo["CN"];
        }
    }

    if (
        empty($_POST['descr']) || empty($_POST['keylength']) || empty($_POST['lifetime']) ||
        empty($_POST['country']) || empty($_POST['state']) || empty($_POST['city']) ||
        empty($_POST['organization']) || empty($_POST['email'])
    ) {
        $input_errors[] = gettext('Please enter all information for the new Certificate Authority.');
        $stepid--;
    } elseif (
        has_special_chars($_POST['country']) || has_special_chars($_POST['state']) ||
        has_special_chars($_POST['city']) || has_special_chars($_POST['organization'])
    ) {
        $input_errors[] = gettext('Please do not use special characters in Certificate field names.');
        $stepid--;
    } elseif (in_array($_POST['descr'], $canames) || in_array($_POST['descr'], $cacns)) {
        $input_errors[] = gettext('Please enter a different name for the Certificate Authority. ' .
            'A Certificate Authority with that name already exists.');
        $stepid--;
    } elseif (strlen($_POST['country']) != 2) {
        $input_errors[] = gettext('Please enter only a two-letter ISO country code');
        $stepid--;
    } else {
        $config['wizardtemp']['step6']['uselist'] = "on";
        $_POST['uselist'] = "on";
    }
}

function step8_stepbeforeformdisplay()
{
    global $stepid, $config;
    $no_server_cert = true;

    if (empty($config['cert'])) {
        $stepid++;
    } else {
        foreach ($config['cert'] as $cert) {
            if (cert_get_purpose($cert['crt'])['id-kp-serverAuth'] == 'Yes') {
                $no_server_cert = false;
                break;
            }
        }
        if ($no_server_cert) {
            $stepid++;
        }
    }
}

function step8_submitphpaction()
{
    global $stepid, $config, $_POST;

    if (isset($_POST['next'])) {
        $_POST['uselist'] = "";
        unset($config['wizardtemp']['step9']['uselist']);
        $stepid++;
    } else {
        $config['wizardtemp']['step6']['uselist'] = "on";
        $_POST['uselist'] = "on";
    }
}

function step9_stepbeforeformdisplay()
{
    global $config, $pkg, $stepid;

    $pconfig = $config['wizardtemp'];

    if (isset($pconfig['step6']['uselist'])) {
        $country = $pconfig['step6']['country'];
        $state = $pconfig['step6']['state'];
        $city = $pconfig['step6']['city'];
        $org = $pconfig['step6']['organization'];
        $email = $pconfig['step6']['email'];
    } else {
        $ca = lookup_ca($pconfig['step6']['authcertca']);
        $cavl = cert_get_subject_array($ca['crt']);
        $country = $cavl[0]['v'];
        $state = $cavl[1]['v'];
        $city = $cavl[2]['v'];
        $org = $cavl[3]['v'];
        $email = $cavl[4]['v'];
    }
    $fields =& $pkg['step'][$stepid]['fields']['field'];

    foreach ($fields as $idx => $field) {
        switch ($field['name']) {
            case 'country':
                $fields[$idx]['value'] = $country;
                break;
            case 'state':
                $fields[$idx]['value'] = $state;
                break;
            case 'city':
                $fields[$idx]['value'] = $city;
                break;
            case 'organization':
                $fields[$idx]['value'] = $org;
                break;
            case 'email':
                $fields[$idx]['value'] = $email;
                break;
        }
    }
}

function step9_submitphpaction()
{
    global $input_errors, $stepid, $_POST, $config;

    $certnames = array();
    $certcns = array();
    if (is_array($config['cert'])) {
        foreach ($config['cert'] as $cert) {
            $certnames[] = $cert['descr'];
            $certinfo = cert_get_subject_hash($cert['crt']);
            $certcns[] = $certinfo["CN"];
        }
    }

    if (
        empty($_POST['descr']) || empty($_POST['keylength']) || empty($_POST['lifetime']) ||
        empty($_POST['country']) || empty($_POST['state']) || empty($_POST['city']) ||
        empty($_POST['organization']) || empty($_POST['email'])
    ) {
        $input_errors[] = gettext('Please enter all information for the new certificate.');
        $stepid--;
    } elseif (
        has_special_chars($_POST['country']) || has_special_chars($_POST['state']) ||
        has_special_chars($_POST['city']) || has_special_chars($_POST['organization'])
    ) {
        $input_errors[] = gettext('Please do not use special characters in Certificate field names.');
        $stepid--;
    } elseif (in_array($_POST['descr'], $certnames) || in_array($_POST['descr'], $certcns)) {
        $input_errors[] = gettext('Please enter a different name for the Certificate. ' .
            'A Certificate with that name/common name already exists.');
        $stepid--;
    } elseif (strlen($_POST['country']) != 2) {
        $input_errors[] = gettext('Please enter only a two-letter ISO country code');
        $stepid--;
    } else {
        $config['wizardtemp']['step9']['uselist'] = "on";
        $_POST['uselist'] = "on";
    }
}

function step10_stepbeforeformdisplay()
{
    global $pkg, $stepid, $netbios_nodetypes;

    foreach ($pkg['step'][$stepid]['fields']['field'] as $idx => $field) {
        if ($field['name'] == "crypto") {
            $pkg['step'][$stepid]['fields']['field'][$idx]['options']['option'] = array();
            foreach (openvpn_get_cipherlist() as $name => $desc) {
                $opt = array();
                $opt['name'] = $desc;
                $opt['value'] = $name;
                $pkg['step'][$stepid]['fields']['field'][$idx]['options']['option'][] = $opt;
            }
        } elseif ($field['name'] == "digest") {
            $pkg['step'][$stepid]['fields']['field'][$idx]['options']['option'] = array();
            foreach (openvpn_get_digestlist() as $name => $desc) {
                $opt = array();
                $opt['name'] = $desc;
                $opt['value'] = $name;
                $pkg['step'][$stepid]['fields']['field'][$idx]['options']['option'][] = $opt;
            }
        } elseif ($field['name'] == "compression") {
            $pkg['step'][$stepid]['fields']['field'][$idx]['options']['option'] = array();
            foreach (openvpn_compression_modes() as $name => $desc) {
                $opt = array();
                $opt['name'] = $desc;
                $opt['value'] = $name;
                $pkg['step'][$stepid]['fields']['field'][$idx]['options']['option'][] = $opt;
            }
        } elseif ($field['name'] == "nbttype") {
            $pkg['step'][$stepid]['fields']['field'][$idx]['options']['option'] = array();
            foreach ($netbios_nodetypes as $type => $name) {
                $opt = array();
                $opt['name'] = $name;
                $opt['value'] = $type;
                $pkg['step'][$stepid]['fields']['field'][$idx]['options']['option'][] = $opt;
            }
        }
    }
}

function step10_submitphpaction()
{
    global $input_errors, $stepid, $config;

    if (empty($_POST['localport'])) {
        $config['wizardtemp']['step10']['localport'] = $_POST['localport'] = openvpn_port_next($_POST['protocol']);
    }

    if ($result = openvpn_validate_port($_POST['localport'], gettext('Local port'))) {
        $input_errors[] = $result;
    }

    if ($result = openvpn_validate_cidr($_POST['tunnelnet'], gettext('IPv4 Tunnel Network'), false, 'ipv4')) {
        $input_errors[] = $result;
    } elseif (!empty($_POST['tunnelnet'])) {
        // Check IPv4 tunnelnet pool size. Wizard makes tun mode with net30 server only.
        list($ipv4tunnel_base, $ipv4tunnel_prefix) = explode('/', trim($_POST['tunnelnet']));
        if ($ipv4tunnel_prefix > 28) {
            $input_errors[] = gettext('A prefix longer than 28 cannot be used with a net30 topology.');
        }
    }

    if ($result = openvpn_validate_cidr($_POST['tunnelnetv6'], gettext('IPv6 Tunnel Network'), false, 'ipv6')) {
        $input_errors[] = $result;
    }

    if ($result = openvpn_validate_cidr($_POST['localnet'], gettext('IPv4 Local Network'), true, 'ipv4')) {
        $input_errors[] = $result;
    }

    if ($result = openvpn_validate_cidr($_POST['localnetv6'], gettext('IPv6 Local Network'), true, 'ipv6')) {
        $input_errors[] = $result;
    }

    if ($result = openvpn_validate_cidr($_POST['remotenet'], gettext('IPv4 Remote Network'), true, 'ipv4')) {
        $input_errors[] = $result;
    }

    if ($result = openvpn_validate_cidr($_POST['remotenetv6'], gettext('IPv6 Remote Network'), true, 'ipv6')) {
        $input_errors[] = $result;
    }

    $portused = openvpn_port_used($_POST['protocol'], $_POST['interface'], $_POST['localport']);
    if ($portused != 0) {
        $input_errors[] = gettext("The specified 'Local port' is in use. Please select another value.");
    }

    if (!isset($_POST['generatetlskey']) && isset($_POST['tlsauthentication'])) {
        if (
            !strstr($_POST['tlssharedkey'], "-----BEGIN OpenVPN Static key V1-----") ||
            !strstr($_POST['tlssharedkey'], "-----END OpenVPN Static key V1-----")
        ) {
            $input_errors[] = gettext("The field 'TLS Authentication Key' does not appear to be valid.");
        }
    }

    if (!empty($_POST['dnsserver1']) && !is_ipaddr(trim($_POST['dnsserver1']))) {
        $input_errors[] = gettext("The field 'DNS Server #1' must contain a valid IP address");
    }
    if (!empty($_POST['dnsserver2']) && !is_ipaddr(trim($_POST['dnsserver2']))) {
        $input_errors[] = gettext("The field 'DNS Server #2' must contain a valid IP address");
    }
    if (!empty($_POST['dnsserver3']) && !is_ipaddr(trim($_POST['dnsserver3']))) {
        $input_errors[] = gettext("The field 'DNS Server #3' must contain a valid IP address");
    }
    if (!empty($_POST['dnsserver4']) && !is_ipaddr(trim($_POST['dnsserver4']))) {
        $input_errors[] = gettext("The field 'DNS Server #4' must contain a valid IP address");
    }

    if (!empty($_POST['ntpserver1']) && !is_ipaddr(trim($_POST['ntpserver1']))) {
        $input_errors[] = gettext("The field 'NTP Server #1' must contain a valid IP address");
    }
    if (!empty($_POST['ntpserver2']) && !is_ipaddr(trim($_POST['ntpserver2']))) {
        $input_errors[] = gettext("The field 'NTP Server #2' must contain a valid IP address");
    }

    if (!empty($_POST['winsserver1']) && !is_ipaddr(trim($_POST['winsserver1']))) {
        $input_errors[] = gettext("The field 'WINS Server #1' must contain a valid IP address");
    }
    if (!empty($_POST['winsserver2']) && !is_ipaddr(trim($_POST['winsserver2']))) {
        $input_errors[] = gettext("The field 'WINS Server #2' must contain a valid IP address");
    }

    if ($_POST['concurrentcon'] && !is_numeric($_POST['concurrentcon'])) {
        $input_errors[] = gettext("The field 'Concurrent connections' must be numeric.");
    }

    if (empty($_POST['tunnelnet']) && empty($_POST['tunnelnetv6'])) {
        $input_errors[] = gettext("You must specify a 'Tunnel network'.");
    }

    if (!empty($input_errors)) {
        $stepid = $stepid - 1;
    }
}

function step12_submitphpaction()
{
    global $config;

    $pconfig = $config['wizardtemp'];

    if (!is_array($config['wizardtemp'])) {
        $message = "No configuration found please retry again.";
        header(url_safe('Location: /wizard.php?xml=openvpn&stepid=1&message=%s', array($message)));
        exit;
    }

    if ($pconfig['step1']['type'] == "local") {
        $auth = array();
        $auth['name'] = "Local Database";
        $auth['type'] = "local";
    } elseif (isset($pconfig['step2']['uselist'])) {
        $auth = array();
        $auth['type'] = $pconfig['step1']['type'];
        $auth['refid'] = uniqid();
        $auth['name'] = $pconfig['step2']['authtype'];

        if ($auth['type'] == "ldap") {
            $auth['host'] = $pconfig['step2']['ip'];
            $auth['ldap_port'] = $pconfig['step2']['port'];
            if ($pconfig['step1']['transport'] == "tcp") {
                $auth['ldap_urltype'] = 'TCP - Standard';
            } else {
                $auth['ldap_urltype'] = 'SSL - Encrypted';
            }
            $auth['ldap_protver'] = 3;
            $auth['ldap_scope'] = $pconfig['step2']['scope'];
            $auth['ldap_basedn'] = $pconfig['step2']['basedn'];
            $auth['ldap_authcn'] = $pconfig['step2']['authscope'];
            $auth['ldap_binddn'] = $pconfig['step2']['userdn'];
            $auth['ldap_bindpw'] = $pconfig['step2']['passdn'];
            $auth['ldap_attr_user'] = $pconfig['step1']['nameattr'];
            $auth['ldap_attr_member'] = $pconfig['step1']['memberattr'];
            $auth['ldap_attr_group'] = $pconfig['step1']['groupattr'];
        } elseif ($auth['type'] == "radius") {
            $auth['host'] = $pconfig['step2']['ip'];
            $auth['radius_auth_port'] = $pconfig['step2']['port'];
            $auth['radius_secret'] = $pconfig['step2']['password'];
            $auth['radius_srvcs'] = "auth";
        }

        $a_auth = &config_read_array('system', 'authserver');
        $a_auth[] = $auth;
    } elseif (!isset($pconfig['step2']['uselist']) && empty($pconfig['step2']['authserv'])) {
        $message = "Please choose an authentication server.";
        header(url_safe('Location: /wizard.php?xml=openvpn&stepid=1&message=%s', array($message)));
        exit;
    } elseif (!($auth = auth_get_authserver($pconfig['step2']['authserv']))) {
        $message = "No valid authentication server has been specified.";
        header(url_safe('Location: /wizard.php?xml=openvpn&stepid=1&message=%s', array($message)));
        exit;
    }

    if (isset($pconfig['step6']['uselist']) && !empty($pconfig['step6']['certca'])) {
        $ca = array();
        $ca['refid'] = uniqid();
        $ca['descr'] = $pconfig['step6']['certca'];
        $dn = array(
          'countryName' => $pconfig['step6']['country'],
          'stateOrProvinceName' => $pconfig['step6']['state'],
          'localityName' => $pconfig['step6']['city'],
          'organizationName' => $pconfig['step6']['organization'],
          'emailAddress' => $pconfig['step6']['email'],
          'commonName' => $pconfig['step6']['certca']);

        ca_create($ca, $pconfig['step6']['keylength'], $pconfig['step6']['lifetime'], $dn, "sha256");

        $a_ca = &config_read_array('ca');
        $a_ca[] = $ca;
    } elseif (!isset($pconfig['step6']['uselist']) && empty($pconfig['step6']['authcertca'])) {
        $message = "Please choose a Certificate Authority.";
        header(url_safe('Location: /wizard.php?xml=openvpn&stepid=5&message=%s', array($message)));
        exit;
    } elseif (!($ca = lookup_ca($pconfig['step6']['authcertca']))) {
        $message = "Not a valid Certificate Authority specified.";
        header(url_safe('Location: /wizard.php?xml=openvpn&stepid=5&message=%s', array($message)));
        exit;
    }

    if (isset($pconfig['step9']['uselist'])) {
        $cert = array();
        $cert['refid'] = uniqid();
        $cert['descr'] = $pconfig['step9']['certname'];
        $dn = array(
          'countryName' => $pconfig['step9']['country'],
          'stateOrProvinceName' => $pconfig['step9']['state'],
          'localityName' => $pconfig['step9']['city'],
          'organizationName' => $pconfig['step9']['organization'],
          'emailAddress' => $pconfig['step9']['email'],
          'commonName' => $pconfig['step9']['certname']
        );

        cert_create(
            $cert,
            $ca['refid'],
            $pconfig['step9']['keylength'],
            $pconfig['step9']['lifetime'],
            $dn,
            'sha256',
            'server_cert',
            []
        );

        $a_cert = &config_read_array('cert');
        $a_cert[] = $cert;
    } elseif (!isset($pconfig['step9']['uselist']) && empty($pconfig['step9']['authcertname'])) {
        $message = "Please choose a Certificate.";
        header(url_safe('Location: /wizard.php?xml=openvpn&stepid=7&message=%s', array($message)));
        exit;
    } elseif (!($cert = lookup_cert($pconfig['step9']['authcertname']))) {
        $message = "Not a valid Certificate specified.";
        header(url_safe('Location: /wizard.php?xml=openvpn&stepid=7&message=%s', array($message)));
        exit;
    }
    $server = array();
    $server['vpnid'] = openvpn_vpnid_next();
    switch ($auth['type']) {
        case "ldap":
            $server['authmode'] = $auth['name'];
            $server['mode'] = "server_user";
            break;
        case "radius":
            $server['authmode'] = $auth['name'];
            $server['mode'] = "server_user";
            break;
        default:
            $server['authmode'] = "Local Database";
            $server['mode'] = "server_tls_user";
            break;
    }
    $server['caref'] = $ca['refid'];
    $server['certref'] = $cert['refid'];
    $server['protocol'] = $pconfig['step10']['protocol'];
    $server['interface'] = $pconfig['step10']['interface'];
    $server['local_port'] = $pconfig['step10']['localport'];

    if (strlen($pconfig['step10']['descr']) > 30) {
        $pconfig['step10']['descr'] = substr($pconfig['step10']['descr'], 0, 30);
    }
    $server['description'] = $pconfig['step10']['descr'];
    if (isset($pconfig['step10']['tlsauth'])) {
        if (isset($pconfig['step10']['gentlskey'])) {
            $tlskey = openvpn_create_key();
        } else {
            $tlskey = $pconfig['step10']['tlskey'];
        }
        $server['tls'] = base64_encode($tlskey);
    }
    $server['dh_length'] = $pconfig['step10']['dhkey'];
    if (isset($pconfig['step10']['tunnelnet'])) {
        $server['tunnel_network'] = $pconfig['step10']['tunnelnet'];
    }
    if (isset($pconfig['step10']['tunnelnetv6'])) {
        $server['tunnel_networkv6'] = $pconfig['step10']['tunnelnetv6'];
    }
    if (isset($pconfig['step10']['rdrgw'])) {
        $server['gwredir'] = $pconfig['step10']['rdrgw'];
    }
    if (isset($pconfig['step10']['localnet'])) {
        $server['local_network'] = $pconfig['step10']['localnet'];
    }
    if (isset($pconfig['step10']['localnetv6'])) {
        $server['local_networkv6'] = $pconfig['step10']['localnetv6'];
    }
    if (isset($pconfig['step10']['remotenet'])) {
        $server['remote_network'] = $pconfig['step10']['remotenet'];
    }
    if (isset($pconfig['step10']['remotenetv6'])) {
        $server['remote_networkv6'] = $pconfig['step10']['remotenetv6'];
    }
    if (isset($pconfig['step10']['concurrentcon'])) {
        $server['maxclients'] = $pconfig['step10']['concurrentcon'];
    }
    if (isset($pconfig['step10']['compression'])) {
        $server['compression'] = $pconfig['step10']['compression'];
    }
    if (isset($pconfig['step10']['tos'])) {
        $server['passtos'] = $pconfig['step10']['tos'];
    }
    if (isset($pconfig['step10']['interclient'])) {
        $server['client2client'] = $pconfig['step10']['interclient'];
    }
    if (isset($pconfig['step10']['duplicate_cn'])) {
        $server['duplicate_cn'] = $pconfig['step10']['duplicate_cn'];
    }
    if (isset($pconfig['step10']['dynip'])) {
        $server['dynamic_ip'] = $pconfig['step10']['dynip'];
    }
    if (isset($pconfig['step10']['addrpool'])) {
        $server['pool_enable'] = $pconfig['step10']['addrpool'];
    }
    if (isset($pconfig['step10']['defaultdomain'])) {
        $server['dns_domain'] = $pconfig['step10']['defaultdomain'];
    }
    if (isset($pconfig['step10']['dns1'])) {
        $server['dns_server1'] = $pconfig['step10']['dns1'];
    }
    if (isset($pconfig['step10']['dns2'])) {
        $server['dns_server2'] = $pconfig['step10']['dns2'];
    }
    if (isset($pconfig['step10']['dns3'])) {
        $server['dns_server3'] = $pconfig['step10']['dns3'];
    }
    if (isset($pconfig['step10']['dns4'])) {
        $server['dns_server4'] = $pconfig['step10']['dns4'];
    }
    if (isset($pconfig['step10']['ntp1'])) {
        $server['ntp_server1'] = $pconfig['step10']['ntp1'];
    }
    if (isset($pconfig['step10']['ntp2'])) {
        $server['ntp_server2'] = $pconfig['step10']['ntp2'];
    }
    if (isset($pconfig['step10']['wins1'])) {
        $server['wins_server1'] = $pconfig['step10']['wins1'];
    }
    if (isset($pconfig['step10']['wins2'])) {
        $server['wins_server2'] = $pconfig['step10']['wins2'];
    }
    if (isset($pconfig['step10']['nbtenable'])) {
        $server['netbios_ntype'] = $pconfig['step10']['nbttype'];
        if (isset($pconfig['step10']['nbtscope'])) {
            $server['netbios_scope'] = $pconfig['step10']['nbtscope'];
        }
        $server['netbios_enable'] = $pconfig['step10']['nbtenable'];
    }
    $server['crypto'] = $pconfig['step10']['crypto'];
    $server['digest'] = $pconfig['step10']['digest'];

    if (isset($pconfig['step11']['ovpnrule'])) {
        $rule = array();
        $rule['descr'] = sprintf(gettext("OpenVPN %s wizard allow client access"), $server['description']);
        /* Ensure the rule descr is not too long for pf to handle */
        if (strlen($rule['descr']) > 52) {
            $rule['descr'] = substr($rule['descr'], 0, 52);
        }
        $rule['direction'] = "in";
        $rule['source']['any'] = true;
        if ($server['interface'] != "any") {
            $rule['destination']['network'] = $server['interface'] . "ip";
            $rule['interface'] = $server['interface'];
        } else {
            $rule['destination']['network'] = "(self)";
            $rule['floating'] = "yes";
        }
        $rule['destination']['port'] = $server['local_port'];
        $proto = strtolower($server['protocol']);
        if (strpos($proto, '4') !== false) {
            $rule['protocol'] = substr($proto, 0, -1);
            $rule['ipprotocol'] = "inet";
        } elseif (strpos($proto, '6') !== false) {
            $rule['protocol'] = substr($proto, 0, -1);
            $rule['ipprotocol'] = "inet6";
        } else {
            $rule['protocol'] = $proto;
            $rule['ipprotocol'] = "inet46";
        }
        $rule['type'] = "pass";
        $rule['enabled'] = "on";
        $rule['created'] = make_config_revision_entry();
        $config['filter']['rule'][] = $rule;
    }

    if (isset($pconfig['step11']['ovpnallow'])) {
        $rule = array();
        $rule['descr'] = sprintf(gettext("OpenVPN %s wizard"), $server['description']);
        /* Ensure the rule descr is not too long for pf to handle */
        if (strlen($rule['descr']) > 52) {
            $rule['descr'] = substr($rule['descr'], 0, 52);
        }
        $rule['source']['any'] = true;
        $rule['destination']['any'] = true;
        $rule['interface'] = "openvpn";
        $rule['type'] = "pass";
        $rule['enabled'] = "on";
        $rule['created'] = make_config_revision_entry();
        $config['filter']['rule'][] = $rule;
    }

    $a_server = &config_read_array('openvpn', 'openvpn-server');
    $a_server[] = $server;

    unset($config['wizardtemp']);
    write_config();

    openvpn_configure_single($server['vpnid']);

    header(url_safe('Location: /vpn_openvpn_server.php'));
    exit;
}
