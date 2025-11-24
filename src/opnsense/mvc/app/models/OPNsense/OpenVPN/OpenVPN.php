<?php

/*
 * Copyright (C) 2023 Deciso B.V.
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

namespace OPNsense\OpenVPN;

use OPNsense\Base\Messages\Message;
use OPNsense\Base\BaseModel;
use OPNsense\Trust\Store;
use OPNsense\Core\Config;
use OPNsense\Core\File;
use OPNsense\Firewall\Util;

/**
 * Class OpenVPN
 * @package OPNsense\OpenVPN
 */
class OpenVPN extends BaseModel
{
    /**
     * {@inheritdoc}
     */
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);
        // validate changed instances
        foreach ($this->Instances->Instance->iterateItems() as $instance) {
            if (!$validateFullModel && !$instance->isFieldChanged()) {
                continue;
            }
            $key = $instance->__reference;
            if ($instance->role == 'client') {
                if (empty((string)$instance->remote)) {
                    $messages->appendMessage(new Message(gettext("Remote required"), $key . ".remote"));
                }
                if (empty((string)$instance->username) xor empty((string)$instance->password)) {
                    $messages->appendMessage(
                        new Message(
                            gettext("When using password authentication, both username and password are required"),
                            $key . ".username"
                        )
                    );
                }
                if (empty((string)$instance->cert) && empty((string)$instance->ca)) {
                    $messages->appendMessage(new Message(
                        gettext('When no certificate is provided a CA needs to be provided.'),
                        $key . ".cert"
                    ));
                }
            } elseif ($instance->role == 'server') {
                if (in_array($instance->dev_type, ['tun', 'ovpn'])) {
                    if (empty((string)$instance->server) && empty((string)$instance->server_ipv6)) {
                        $messages->appendMessage(
                            new Message(gettext('At least one IPv4 or IPv6 tunnel network is required.'), $key . '.server')
                        );
                        $messages->appendMessage(
                            new Message(gettext('At least one IPv4 or IPv6 tunnel network is required.'), $key . '.server_ipv6')
                        );
                    }
                    if (!$instance->server->isEmpty() && strpos((string)$instance->server, '/') !== false) {
                        if (
                            explode('/', (string)$instance->server)[1] > 29 && !(
                            (string)$instance->dev_type == 'tun' && (string)$instance->topology == 'p2p'
                            )
                        ) {
                            /* tun + p2p is the exceptions here */
                            $msg = gettext(
                                'Server directive must define a subnet of /29 or lower unless topology equals p2p.'
                            );
                            $messages->appendMessage(new Message($msg, $key . '.server'));
                        }
                    }
                } elseif ($instance->dev_type == 'tap') {
                    if (!(empty((string)$instance->bridge_gateway) xor ((string)$instance->bridge_pool))) {
                        $messages->appendMessage(new Message(
                            gettext('When specifying a bridge gateway, a pool should also be provided.'),
                            $key . ".bridge_gateway"
                        ));
                    } elseif (!$instance->bridge_pool->isEmpty()) {
                        $parts = array_map('trim', explode('-', (string)$instance->bridge_pool));
                        if (count($parts) != 2 || !Util::isIpv4Address($parts[0]) || !Util::isIpv4Address($parts[1])) {
                            $messages->appendMessage(new Message(
                                gettext('Invalid range provided.'),
                                $key . ".bridge_pool"
                            ));
                        } else {
                            $ip = (string)$instance->bridge_gateway;
                            if (!Util::isIPInCIDR($parts[0], $ip) || !Util::isIPInCIDR($parts[1], $ip)) {
                                $messages->appendMessage(new Message(
                                    gettext('Range does not match specified subnet.'),
                                    $key . ".bridge_pool"
                                ));
                            }
                        }
                    }
                }
                if ((string)$instance->verify_client_cert != 'none') {
                    if (empty((string)$instance->cert)) {
                        $messages->appendMessage(new Message(
                            gettext('To validate a certificate one has to be provided.'),
                            $key . ".verify_client_cert"
                        ));
                    }
                } elseif (empty((string)$instance->authmode)) {
                    $messages->appendMessage(new Message(
                        gettext(
                            'Please select an authentication option, at least one type of authentication is required.'
                        ),
                        $key . ".verify_client_cert"
                    ));
                }
                if (!$instance->{'auth-gen-token'}->isEmpty() && (string)$instance->{'reneg-sec'} == '0') {
                    $messages->appendMessage(new Message(
                        gettext('A token lifetime requires a non zero Renegotiate time.'),
                        $key . ".auth-gen-token"
                    ));
                } elseif ((string)$instance->{'auth-gen-token'} == '0' && (string)$instance->{'reneg-sec'} == '0') {
                    $messages->appendMessage(new Message(
                        gettext('A disabled renegotiation time requires a token lifetime.'),
                        $key . ".auth-gen-token"
                    ));
                }

                if (!$instance->{'auth-gen-token-renewal'}->isEmpty() && (string)$instance->{'auth-gen-token'} === '') {
                    $messages->appendMessage(new Message(
                        gettext('A token renewal requires a token lifetime.'),
                        $key . ".auth-gen-token-renewal"
                    ));
                }

                if (!$instance->{'auth-gen-token-secret'}->isEmpty() && (string)$instance->{'auth-gen-token'} === '') {
                    $messages->appendMessage(new Message(
                        gettext('A token secret requires a token lifetime.'),
                        $key . ".auth-gen-token-secret"
                    ));
                }
                if (!$instance->{'port-share'}->isEmpty() && strpos($instance->proto, 'tcp') === false) {
                    $messages->appendMessage(new Message(
                        gettext('Port sharing is only supported when using tcp.'),
                        $key . ".port-share"
                    ));
                }
            }
            if (!$instance->cert->isEmpty()) {
                $tmp = Store::getCertificate((string)$instance->cert);
                if (empty((string)$instance->ca) && (empty($tmp) || !isset($tmp['ca']))) {
                    $messages->appendMessage(new Message(
                        gettext('Unable to locate a CA for this certificate.'),
                        $key . ".cert"
                    ));
                }
            }

            if (
                $instance->keepalive_timeout->asFloat() < $instance->keepalive_interval->asFloat() * 2 ||
                $instance->keepalive_timeout->isEmpty() != $instance->keepalive_interval->isEmpty()
            ) {
                $messages->appendMessage(new Message(
                    gettext('Timeout must be at least twice the interval value.'),
                    $key . ".keepalive_timeout"
                ));
            }

            if ($instance->dev_type == 'ovpn' && strpos($instance->proto, 'udp') === false) {
                $messages->appendMessage(new Message(
                    gettext('DCO type instances only support UDP mode.'),
                    $key . ".proto"
                ));
            }
            if ($instance->dev_type == 'ovpn' && !$instance->fragment->isEmpty()) {
                $messages->appendMessage(new Message(
                    gettext('DCO type instances do not support fragment size.'),
                    $key . ".fragment"
                ));
            }
            if ($instance->dev_type == 'ovpn' && in_array('fast-io', $instance->various_flags->getValues())) {
                $messages->appendMessage(new Message(
                    gettext('DCO type instances do not support fast-io.'),
                    $key . ".various_flags"
                ));
            }
            if (
                !str_starts_with($instance->proto->getValue(), 'udp') &&
                in_array('fast-io', $instance->various_flags->getValues())
            ) {
                $messages->appendMessage(new Message(gettext('fast-io requires UDP.'), $key . ".various_flags"));
            }
        }
        return $messages;
    }

    /**
     * Retrieve overwrite content in legacy format
     * @param string $server_id vpnid
     * @param string $common_name certificate common name (or username when specified)
     * @param array $overlay overwrite CSO properties
     * @return array legacy overwrite data, empty when failed
     */
    public function getOverwrite($server_id, $common_name, $overlay = [])
    {
        $result = [];
        foreach ($this->Overwrites->Overwrite->iterateItems() as $cso) {
            if (empty((string)$cso->enabled)) {
                continue;
            }
            $servers = !$cso->servers->isEmpty() ? explode(',', (string)$cso->servers) : [];
            if (!empty($servers) && !in_array($server_id, $servers)) {
                continue;
            }
            if ((string)$cso->common_name != $common_name) {
                continue;
            }

            // translate content to legacy format so this may easily inject into the existing codebase
            $result['redirect_gateway'] = str_replace(',', ' ', (string)$cso->redirect_gateway);

            $opts = [
                'common_name',
                'description',
                'dns_domain',
                'dns_domain_search',
                'tunnel_network',
                'tunnel_networkv6',
                'route_gateway',
            ];
            foreach ($opts as $fieldname) {
                $result[$fieldname] = (string)$cso->$fieldname;
            }

            foreach (['local', 'remote'] as $type) {
                $f1 = $type . '_network';
                $f2 = $type . '_networkv6';
                foreach (explode(',', (string)$cso->{$type . '_networks'}) as $item) {
                    if (strpos($item, ":") === false) {
                        $target_fieldname = $f1;
                    } else {
                        $target_fieldname = $f2;
                    }
                    if (!isset($result[$target_fieldname])) {
                        $result[$target_fieldname] = $item;
                    } else {
                        $result[$target_fieldname] .=  "," . $item;
                    }
                }
            }
            if (!$cso->push_reset->isEmpty()) {
                $result['push_reset'] = '1';
            }
            if (!$cso->block->isEmpty()) {
                $result['block'] = '1';
            }
            foreach (['dns_server', 'ntp_server', 'wins_server'] as $fieldname) {
                if (!$cso->{$fieldname . 's'}->isEmpty()) {
                    foreach (explode(',', (string)$cso->{$fieldname . 's'}) as $idx => $item) {
                        $result[$fieldname . (string)($idx + 1)] = $item;
                    }
                }
            }
        }

        if (empty($result)) {
            $result['common_name'] = $common_name;
        }

        // overlay is fed by authentication backends and takes precedence
        $result = array_merge($result, $overlay);

        // check if provisioning by authentication backend is mandatory
        foreach ($this->Instances->Instance->iterateItems() as $node_uuid => $node) {
            if (
                !$node->enabled->isEmpty() &&
                $server_id == $node_uuid &&
                (string)$node->role == 'server' &&
                !$node->provision_exclusive->isEmpty()
            ) {
                if (!$node->server->isEmpty() && empty($result['tunnel_network'])) {
                    return [];
                } elseif (!$node->server_ipv6->isEmpty() && empty($result['tunnel_networkv6'])) {
                    return [];
                }
            }
        }

        return $result;
    }

    /**
     * The VPNid sequence is used for device creation, in which case we can't use uuid's due to their size
     * @return list of vpn id's used by legacy or mvc instances
     */
    public function usedVPNIds()
    {
        $result = [];
        $cfg = Config::getInstance()->object();
        foreach (['openvpn-server', 'openvpn-client'] as $ref) {
            if (isset($cfg->openvpn) && isset($cfg->openvpn->$ref)) {
                foreach ($cfg->openvpn->$ref as $item) {
                    if (isset($item->vpnid)) {
                        $result[] = (string)$item->vpnid;
                    }
                }
            }
        }
        foreach ($this->Instances->Instance->iterateItems() as $node_uuid => $node) {
            if ((string)$node->vpnid != '') {
                $result[$node_uuid] = (string)$node->vpnid;
            }
        }
        return $result;
    }

    /**
     * @return bool true when there is any enabled tunnel (legacy and/or mvc)
     */
    public function isEnabled()
    {
        $cfg = Config::getInstance()->object();
        foreach (['openvpn-server', 'openvpn-client'] as $ref) {
            if (isset($cfg->openvpn) && isset($cfg->openvpn->$ref)) {
                foreach ($cfg->openvpn->$ref as $item) {
                    if (empty((string)$item->disable)) {
                        return true;
                    }
                }
            }
        }
        foreach ($this->Instances->Instance->iterateItems() as $node_uuid => $node) {
            if (!$node->enabled->isEmpty()) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array of server devices (legacy and mvc)
     */
    public function serverDevices()
    {
        $result = [];
        foreach ($this->Instances->Instance->iterateItems() as $node_uuid => $node) {
            if (!$node->enabled->isEmpty() && (string)$node->role == 'server') {
                $result[(string)$node->__devname] = [
                    'descr' => (string)$node->description ?? '',
                    'sockFilename' => (string)$node->sockFilename
                ];
            }
        }
        $cfg = Config::getInstance()->object();
        if (isset($cfg->openvpn) && isset($cfg->openvpn->{'openvpn-server'})) {
            foreach ($cfg->openvpn->{'openvpn-server'} as $item) {
                if (empty((string)$item->disable)) {
                    $result[sprintf("ovpns%s", $item->vpnid)] = [
                        'descr' => (string)$item->description ?? '',
                        'sockFilename' => "/var/etc/openvpn/server{$item->vpnid}.sock"
                    ];
                }
            }
        }
        return $result;
    }

    /**
     * Find unique instance properties, either from legacy or mvc model
     * Offers glue between both worlds.
     * @param string $server_id vpnid (either numerical or uuid)
     * @param string $role the node role
     * @return array selection of relevant fields for downstream processes
     */
    public function getInstanceById($server_id, $role = null)
    {
        // travers model first, two key types are valid, the id used in the device (numeric) or the uuid
        foreach ($this->Instances->Instance->iterateItems() as $node_uuid => $node) {
            if (
                !$node->enabled->isEmpty() &&
                ((string)$node->vpnid == $server_id || $server_id == $node_uuid) &&
                ($role == null || $role == (string)$node->role)
            ) {
                // find static key
                $this_tls = null;
                $this_mode = null;
                if (!$node->tls_key->isEmpty()) {
                    $tlsnode = $this->getNodeByReference("StaticKeys.StaticKey.{$node->tls_key}");
                    if (!empty($node->tls_key)) {
                        $this_mode = (string)$tlsnode->mode;
                        $this_tls = base64_encode((string)$tlsnode->key);
                    }
                }
                // find caref
                $this_caref = null;
                if (!$node->ca->isEmpty()) {
                    $this_caref = (string)$node->ca;
                } elseif (isset(Config::getInstance()->object()->cert)) {
                    foreach (Config::getInstance()->object()->cert as $cert) {
                        if (isset($cert->refid) && (string)$node->cert == $cert->refid) {
                            $this_caref = (string)$cert->caref;
                        }
                    }
                }
                // legacy uses group names, convert key (gid) to current name
                $local_group = null;
                if (!$node->local_group->isEmpty()) {
                    $local_group = $node->local_group->getNodeData()[(string)$node->local_group]['value'];
                }
                return [
                    'role' => (string)$node->role,
                    'vpnid' => (string)$node->vpnid,
                    'authmode' => (string)$node->authmode,
                    'local_group' => $local_group,
                    'cso_login_matching' => (string)$node->username_as_common_name,
                    'strictusercn' => (string)$node->strictusercn,
                    'dev_mode' => (string)$node->dev_type,
                    'topology_subnet' => $node->topology == 'subnet' ? '1' : '0',
                    'local_port' =>  (string)$node->port,
                    'protocol' => (string)$node->proto,
                    'mode' => !$node->authmode->isEmpty() ? 'server_tls_user' : '',
                    'reneg-sec' => (string)$node->{'reneg-sec'},
                    'tls' => $this_tls,
                    'tlsmode' => $this_mode,
                    'certref' => (string)$node->cert,
                    'caref' => $this_caref,
                    'cert_depth' => (string)$node->cert_depth,
                    'digest' => (string)$node->auth,
                    'description' => (string)$node->description,
                    'use_ocsp' => !$node->use_ocsp->isEmpty(),
                    // legacy only (backwards compatibility)
                    'crypto' => (string)$node->{'data-ciphers-fallback'},
                ];
            }
        }
        // when not found, try to locate the server in our legacy pool
        $cfg = Config::getInstance()->object();
        foreach (['openvpn-server', 'openvpn-client'] as $section) {
            if (!isset($cfg->openvpn) || !isset($cfg->openvpn->$section)) {
                continue;
            }
            foreach ($cfg->openvpn->$section as $item) {
                $this_role =  explode('-', $section)[1];
                // XXX: previous legacy code did not check if the instance is enabled, we might want to revise that
                if (
                    isset($item->vpnid) &&
                    $item->vpnid == $server_id &&
                    ($role == null || $role == $this_role)
                ) {
                    return [
                        'role' => $this_role,
                        'vpnid' => (string)$item->vpnid,
                        'authmode' => (string)$item->authmode,
                        'local_group' => (string)$item->local_group,
                        'cso_login_matching' => (string)$item->cso_login_matching,
                        'strictusercn' => (string)$item->strictusercn,
                        'dev_mode' => (string)$item->dev_mode,
                        'topology_subnet' => (string)$item->topology_subnet,
                        'local_port' =>  (string)$item->local_port,
                        'protocol' => (string)$item->protocol,
                        'mode' => (string)$item->mode,
                        'reneg-sec' => (string)$item->{'reneg-sec'},
                        'tls' => (string)$item->tls,
                        'tlsmode' => (string)$item->tlsmode,
                        'certref' => (string)$item->certref,
                        'caref'  => (string)$item->caref,
                        'cert_depth' => (string)$item->cert_depth,
                        'description' => (string)$item->description,
                        // legacy only (backwards compatibility)
                        'compression' => (string)$item->compression,
                        'crypto' => (string)$item->crypto,
                        'digest' => (string)$item->digest,
                        'interface' => (string)$item->interface,
                        'use_ocsp' => false,
                    ];
                }
            }
        }
        return null;
    }

    /**
     * Convert options into a openvpn config file on disk
     * @param string $filename target filename
     * @return null
     */
    private function writeConfig($filename, $options)
    {
        $output = '';
        foreach ($options as $key => $value) {
            if ($value === null) {
                $output .= $key . "\n";
            } elseif (str_starts_with($key, '<')) {
                $output .= $key . "\n";
                $output .= trim($value) . "\n";
                $output .= "</" . substr($key, 1) . "\n";
            } elseif (is_array($value)) {
                if ($key == 'auth-user-pass') {
                    // user/passwords need to be feed using a file
                    $output .= $key . " " . $value['filename'] . "\n";
                    File::file_put_contents($value['filename'], $value['content'], 0600);
                } elseif ($key == 'ca-file') {
                    File::file_put_contents($value['filename'], $value['content'], 0600);
                } else {
                    foreach ($value as $item) {
                        $output .= $key . " " . $item . "\n";
                    }
                }
            } else {
                $output .= $key . " " . $value . "\n";
            }
        }
        File::file_put_contents($filename, $output, 0600);
    }

    /**
     * generate OpenVPN instance config files.
     * Ideally we would like to use our standard template system, but due to the complexity of the output
     * and the need for multiple files and a cleanup, this would add more unwanted complexity.
     */
    public function generateInstanceConfig($uuid = null)
    {
        foreach ($this->Instances->Instance->iterateItems() as $node_uuid => $node) {
            if (!$node->enabled->isEmpty() && ($uuid == null || $node_uuid == $uuid)) {
                $options = [];
                // mode specific settings
                if ($node->role == 'client') {
                    $options['client'] = null;
                    $options['dev'] = "ovpnc{$node->vpnid}";
                    $options['remote'] = [];
                    foreach (explode(',', (string)$node->remote) as $this_remote) {
                        $parts = [];
                        if (substr_count($this_remote, ':') > 1) {
                            foreach (explode(']', $this_remote) as $part) {
                                $parts[] = ltrim($part, '[:');
                            }
                        } else {
                            $parts = explode(':', $this_remote);
                        }
                        $options['remote'][] = implode(' ', $parts);
                    }
                    if (empty((string)$node->port) && empty((string)$node->local)) {
                        $options['nobind'] = null;
                    }
                    if (!$node->username->isEmpty() && !$node->password->isEmpty()) {
                        $options['auth-user-pass'] = [
                            "filename" => "/var/etc/openvpn/instance-{$node_uuid}.up",
                            "content" => "{$node->username}\n{$node->password}\n"
                        ];
                    }
                    if (!$node->remote_cert_tls->isEmpty()) {
                        $options['remote-cert-tls'] = 'server';
                    }
                    if (strrpos($node->{'http-proxy'}, ':') > 0) {
                        $tmp = substr_replace($node->{'http-proxy'}, ' ', strrpos($node->{'http-proxy'}, ':'), 1);
                        $options['http-proxy'] = $tmp;
                    }

                    // XXX: In some cases it might be practical to drop privileges, for server mode this will be
                    //      more difficult due to the associated script actions (and their requirements).
                    //$options['user'] = 'openvpn';
                    //$options['group'] = 'openvpn';
                } else {
                    // server only settings
                    $event_script = '/usr/local/opnsense/scripts/openvpn/ovpn_event.py';
                    $options['dev'] = "ovpns{$node->vpnid}";
                    $options['ping-timer-rem'] = null;
                    $options['topology'] = (string)$node->topology;
                    $options['dh'] = '/usr/local/etc/inc/plugins.inc.d/openvpn/dh.rfc7919';
                    if (!$node->crl->isEmpty() && !$node->cert->isEmpty()) {
                        // updated via plugins_configure('crl');
                        $options['crl-verify'] = "/var/etc/openvpn/server-{$node_uuid}.crl-verify";
                    }
                    $options['verify-client-cert'] = (string)$node->verify_client_cert;
                    if (!$node->remote_cert_tls->isEmpty()) {
                        $options['remote-cert-tls'] = 'client';
                    }
                    if (in_array($node->dev_type, ['tun', 'ovpn']) && !$node->server->isEmpty()) {
                        $parts = explode('/', (string)$node->server);
                        $mask = Util::CIDRToMask($parts[1]);
                        if ((string)$node->topology == 'p2p' && $parts[1] > 29) {
                            /**
                             * Workaround and backwards compatibility, the server directive doesn't support
                             * networks smaller than /30, pushing ifconfig manually works in some cases.
                             * According to RFC3021 when the mask is /31 we may omit network and broadcast addresses.
                             **/
                            $masklong = ip2long($mask);
                            $ip1 = long2ip32((ip2long32($parts[0]) & $masklong) + ($masklong == 0xfffffffe ? 0 : 1));
                            $ip2 = long2ip32((ip2long32($parts[0]) & $masklong) + ($masklong == 0xfffffffe ? 1 : 2));
                            $ip3 = long2ip32((ip2long32($parts[0]) & $masklong) + ($masklong == 0xfffffffe ? 2 : 3));
                            $options['mode'] = 'server';
                            $options['tls-server'] = null;
                            $options['ifconfig'] = "{$ip1} {$ip2}";
                            $options['ifconfig-pool'] = "{$ip2} {$ip3}";
                        } else {
                            $options['server'] = $parts[0] . " " . $mask;
                            if ($node->nopool->isEqual('1')) {
                                $options['server'] .=  ' nopool';
                            }
                        }
                    } elseif ((string)$node->dev_type == 'tap') {
                        if (!$node->bridge_gateway->isEmpty()) {
                            $parts = explode('/', (string)$node->bridge_gateway);
                            $options['server-bridge'] = sprintf(
                                "%s %s %s",
                                $parts[0],
                                Util::CIDRToMask($parts[1]),
                                str_replace('-', ' ', (string)$node->bridge_pool)
                            );
                        } else {
                            $options['server-bridge'] = '';
                        }
                    }
                    if (!$node->server_ipv6->isEmpty()) {
                        $options['server-ipv6'] = (string)$node->server_ipv6;
                    }
                    if (!$node->username_as_common_name->isEmpty()) {
                        $options['username-as-common-name'] = null;
                    }
                    $options['client-config-dir'] = "/var/etc/openvpn-csc/{$node->vpnid}";
                    // hook event handlers
                    if (!$node->authmode->isEmpty()) {
                        $options['auth-user-pass-verify'] = "\"{$event_script} --defer '{$node_uuid}'\" via-env";
                        $options['learn-address'] =  "\"{$event_script} '{$node->vpnid}'\"";
                    } else {
                        // client specific profiles are being deployed using the connect event when no auth is used
                        $options['client-connect'] = "\"{$event_script} '{$node_uuid}'\"";
                    }
                    $options['client-disconnect'] = "\"{$event_script} '{$node_uuid}'\"";
                    $options['tls-verify'] = "\"{$event_script} '{$node_uuid}'\"";

                    if (!$node->maxclients->isEmpty()) {
                        $options['max-clients'] = (string)$node->maxclients;
                    }
                    if (empty((string)$node->local) && str_starts_with((string)$node->proto, 'udp')) {
                        // assume multihome when no bind address is specified for udp
                        $options['multihome'] = null;
                    }
                    $options['push'] = [];
                    $options['route'] = [];
                    $options['route-ipv6'] = [];

                    // push options
                    if (isset($options['ifconfig'])) {
                        /* "manual" server directive, we should tell the client which topology we are using */
                        $options['push'][] = "\"topology {$node->topology}\"";
                    }
                    if (!$node->redirect_gateway->isEmpty()) {
                        $redirect_gateway = str_replace(',', ' ', (string)$node->redirect_gateway);
                        $options['push'][] = "\"redirect-gateway {$redirect_gateway}\"";
                    }

                    if (!$node->route_metric->isEmpty()) {
                        $options['push'][] = "\"route-metric {$node->route_metric}\"";
                    }
                    if (!$node->register_dns->isEmpty()) {
                        $options['push'][] = "\"register-dns\"";
                    }
                    if (!$node->dns_domain->isEmpty()) {
                        foreach (explode(',', (string)$node->dns_domain) as $opt) {
                            $options['push'][] = "\"dhcp-option DOMAIN {$opt}\"";
                        }
                    }
                    if (!$node->dns_domain_search->isEmpty()) {
                        foreach (explode(',', (string)$node->dns_domain_search) as $opt) {
                            $options['push'][] = "\"dhcp-option DOMAIN-SEARCH {$opt}\"";
                        }
                    }
                    if (!$node->dns_servers->isEmpty()) {
                        foreach (explode(',', (string)$node->dns_servers) as $opt) {
                            $options['push'][] = "\"dhcp-option DNS {$opt}\"";
                        }
                    }
                    if (!$node->ntp_servers->isEmpty()) {
                        foreach (explode(',', (string)$node->ntp_servers) as $opt) {
                            $options['push'][] = "\"dhcp-option NTP {$opt}\"";
                        }
                    }
                    if (!$node->push_inactive->isEmpty()) {
                        $options['push'][] = "\"inactive {$node->push_inactive}\"";
                    }

                    if ((string)$node->{'auth-gen-token'} !== '') {
                        $options['auth-gen-token'] = $node->{'auth-gen-token'};

                        if ((string)$node->{'auth-gen-token-renewal'} !== '') {
                            $options['auth-gen-token'] .= ' ' . $node->{'auth-gen-token-renewal'};
                        }
                    }

                    if (!$node->{'auth-gen-token-secret'}->isEmpty()) {
                        $options['<auth-gen-token-secret>'] = $node->{'auth-gen-token-secret'};
                    }

                    if (!$node->compress_migrate->isEmpty()) {
                        $options['compress'] = 'migrate';
                    }

                    if (!$node->{'ifconfig-pool-persist'}->isEmpty()) {
                        $options['ifconfig-pool-persist'] = "/var/etc/openvpn/instance-{$node_uuid}.pool";
                    }
                }
                $options['persist-tun'] = null;
                $options['persist-key'] = null;
                if (!$node->keepalive_interval->isEmpty() && !$node->keepalive_timeout->isEmpty()) {
                    $options['keepalive'] = "{$node->keepalive_interval} {$node->keepalive_timeout}";
                }

                $options['dev-type'] = $node->dev_type == 'ovpn' ? 'tun' : (string)$node->dev_type;
                $options['dev-node'] = "/dev/{$node->dev_type}{$node->vpnid}";
                $options['script-security'] = '3';
                $options['writepid'] = $node->pidFilename;
                $options['daemon'] = "openvpn_{$node->role}{$node->vpnid}";
                $options['management'] = "{$node->sockFilename} unix";
                $options['proto'] = (string)$node->proto;
                if (substr((string)$node->proto, 0, 3) == "tcp") {
                    // suffix role for tcp connections, required in tap mode
                    $options['proto'] .= ('-'  . (string)$node->role);
                }
                $options['verb'] = (string)$node->verb;
                if ($node->dev_type != 'ovpn') {
                    $options['disable-dco'] = null; /* DCO (ovpn) not selected */
                }
                $options['up'] = '/usr/local/etc/inc/plugins.inc.d/openvpn/ovpn-linkup';
                $options['down'] = '/usr/local/etc/inc/plugins.inc.d/openvpn/ovpn-linkdown';

                foreach (['reneg-sec', 'port', 'local', 'data-ciphers', 'data-ciphers-fallback', 'auth'] as $opt) {
                    if ((string)$node->$opt != '') {
                        $options[$opt] = str_replace(',', ':', (string)$node->$opt);
                    }
                }
                if (!$node->{'port-share'}->isEmpty()) {
                    $parts = explode(':', $node->{'port-share'});
                    $port = array_pop($parts);
                    $options['port-share'] = sprintf('%s %s', implode(':', $parts), $port);
                }

                if (!$node->various_flags->isEmpty()) {
                    foreach (explode(',', (string)$node->various_flags) as $opt) {
                        $options[$opt] = null;
                    }
                }

                if (!$node->various_push_flags->isEmpty()) {
                    foreach (explode(',', (string)$node->various_push_flags) as $opt) {
                        $options['push'][] = "\"{$opt}\"";
                    }
                }

                if (!$node->tun_mtu->isEmpty()) {
                    $options['tun-mtu'] = (string)$node->tun_mtu;
                }

                if ($node->fragment != null && (string)$node->fragment != '') {
                    $options['fragment'] = (string)$node->fragment;
                }

                if (!$node->mssfix->isEmpty()) {
                    $options['mssfix'] = null;
                }

                // routes (ipv4, ipv6 local or push)
                foreach (['route', 'push_route', 'push_excluded_routes'] as $type) {
                    foreach (explode(',', (string)$node->$type) as $item) {
                        if (empty($item)) {
                            continue;
                        } elseif (strpos($item, ":") === false) {
                            $parts = explode('/', (string)$item);
                            $item = $parts[0] . " " . Util::CIDRToMask($parts[1] ?? '32');
                            $target_fieldname = "route";
                        } else {
                            $target_fieldname = "route-ipv6";
                        }
                        if ($type == 'push_route') {
                            $options['push'][] = "\"{$target_fieldname} $item\"";
                        } elseif ($type == 'push_excluded_routes') {
                            $options['push'][] = "\"{$target_fieldname} $item net_gateway\"";
                        } else {
                            $options[$target_fieldname][] = $item;
                        }
                    }
                }

                if (!$node->tls_key->isEmpty()) {
                    $tlsnode = $this->getNodeByReference("StaticKeys.StaticKey.{$node->tls_key}");
                    if ($tlsnode) {
                        $options["<tls-{$tlsnode->mode}>"] = (string)$tlsnode->key;
                        if ($tlsnode->mode == 'auth') {
                            $options['key-direction'] = $node->role == 'server' ? '0' : '1';
                        }
                    }
                }
                if (!$node->ca->isEmpty()) {
                    $options['<ca>'] = Store::getCaChain((string)$node->ca);
                }
                if (!$node->cert->isEmpty()) {
                    $tmp = Store::getCertificate((string)$node->cert);
                    if ($tmp && isset($tmp['prv'])) {
                        $options['<key>'] = $tmp['prv'];
                        $options['<cert>'] = $tmp['crt'];
                        if (empty($options['<ca>']) && isset($tmp['ca'])) {
                            $options['<ca>'] = $tmp['ca']['crt'];
                        }
                    }
                }
                if (!$node->use_ocsp->isEmpty() && !empty($options['<ca>'])) {
                    $options['ca-file'] = [
                        "filename" => "/var/etc/openvpn/instance-{$node_uuid}.ca",
                        "content" => $options['<ca>']
                    ];
                }

                // dump to file
                $this->writeConfig($node->cnfFilename, $options);
            }
        }
    }
}
