<?php

/*
 * Copyright (C) 2018 Deciso B.V.
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

namespace OPNsense\OpenVPN\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Base\UserException;
use OPNsense\Core\Config;
use OPNsense\Core\Backend;
use OPNsense\Trust\Store;
use OPNsense\OpenVPN\OpenVPN;
use OPNsense\OpenVPN\Export;
use OPNsense\OpenVPN\ExportFactory;

/**
 * Class ExportController handles client export functions
 * @package OPNsense\OpenVPN
 */
class ExportController extends ApiControllerBase
{
    /**
     * @var null|Export model object to work on
     */
    private $modelHandle = null;

    /**
     * @var array list of configured interfaces (addresses)
     */
    private $physicalInterfaces = array();

    /**
     * Get (or create) model object
     * @return Export
     * @throws \OPNsense\Base\ModelException when unable to create model
     */
    private function getModel()
    {
        if ($this->modelHandle == null) {
            $this->modelHandle = new Export();
        }
        return $this->modelHandle;
    }

    /**
     * collect (and store) configured interfaces by name [lan, wan, optX]
     * @return mixed
     * @throws \Exception when unable to contact configd
     */
    private function getInterfaces()
    {
        if (empty($this->physicalInterfaces)) {
            $ifconfig = json_decode((new Backend())->configdRun('interface list ifconfig'), true);
            $config = Config::getInstance()->object();
            if ($config->interfaces->count() > 0) {
                foreach ($config->interfaces->children() as $key => $node) {
                    $this->physicalInterfaces[(string)$key] = array();
                    if (!empty($ifconfig[(string)($node->if)])) {
                        $this->physicalInterfaces[(string)$key] = $ifconfig[(string)($node->if)];
                    }
                }
            }
        }
        return $this->physicalInterfaces;
    }

    /**
     * find configured servers
     * @param bool $active only active servers
     * @return \Generator
     */
    private function openvpnServers($active = true)
    {
        $cfg = Config::getInstance()->object();
        if (isset($cfg->openvpn)) {
            foreach ($cfg->openvpn->children() as $key => $server) {
                if ($key == 'openvpn-server' && !empty($server)) {
                    if (empty($server->disable) || !$active) {
                        $name = empty($server->description) ? "server" : (string)$server->description;
                        $name .= " " . $server->protocol . ":" . $server->local_port;
                        yield [
                            'name' => $name,
                            'mode' => (string)$server->mode,
                            'vpnid' => (string)$server->vpnid
                        ];
                    }
                }
            }
        }
        foreach ((new OpenVPN())->Instances->Instance->iterateItems() as $node_uuid => $node) {
            if (!empty((string)$node->enabled) && $node->role == 'server') {
                $name = empty($node->description) ? "server" : (string)$node->description;
                $name .= " " . $node->proto . ":" . $node->port;
                yield [
                    'name' => $name,
                    'mode' => !empty((string)$node->authmode) ? 'server_tls_user' : '',
                    'vpnid' => $node_uuid
                ];
            }
        }
    }

    /**
     * find CA record
     * @param string $caref
     * @return mixed
     */
    private function getCA($caref)
    {
        if (isset(Config::getInstance()->object()->ca)) {
            foreach (Config::getInstance()->object()->ca as $cert) {
                if (isset($cert->refid) && (string)$caref == $cert->refid) {
                    return $cert;
                }
            }
        }
        return null;
    }

    /**
     * Determine configured settings for selected server
     * @param string $vpnid server handle
     * @return array
     * @throws \OPNsense\Base\ModelException when unable to create model
     */
    private function configuredSettings($vpnid)
    {
        $result = array();
        $serverModel = $this->getModel()->getServer($vpnid);
        $server = (new OpenVPN())->getInstanceById($vpnid);
        // hostname
        if (!empty((string)$serverModel->hostname)) {
            $result["hostname"] = (string)$serverModel->hostname;
        } elseif (!empty($server['interface'])) {
            $allInterfaces = $this->getInterfaces();
            if (!empty($allInterfaces[$server['interface']])) {
                if (strstr($server['protocol'], "6") !== false) {
                    if (!empty($allInterfaces[$server['interface']]['ipv6'])) {
                        $result["hostname"] = $allInterfaces[$server['interface']]['ipv6'][0]['ipaddr'];
                    }
                } elseif (!empty($allInterfaces[$server['interface']]['ipv4'])) {
                    $result["hostname"] = $allInterfaces[$server['interface']]['ipv4'][0]['ipaddr'];
                }
            }
        }
        // simple 1-1 field mappings (overwrites)
        foreach ($serverModel->iterateItems() as $field => $value) {
            if (!empty((string)$value)) {
                $result[$field] = (string)$value;
            } elseif (!empty($server[$field]) || !isset($result[$field])) {
                $result[$field] = $server[$field] ?? null;
            }
        }
        return $result;
    }

    /**
     * list providers
     * @return array list of configured openvpn providers (servers)
     * @throws \Exception when unable to contact configd
     */
    public function providersAction()
    {
        $result = array();
        foreach ($this->openvpnServers() as $server) {
            $vpnid = $server['vpnid'];
            $result[$vpnid] = array_merge($server, $this->configuredSettings($vpnid));
        }
        return $result;
    }

    /**
     * list configured accounts
     * @param string $vpnid server handle
     * @return array list of configured accounts
     */
    public function accountsAction($vpnid = null)
    {
        $result = [
            null => [
                "description" => gettext("(none) Exclude certificate from export"),
                "users" => []
            ]
        ];
        $server = (new OpenVPN())->getInstanceById($vpnid);
        if ($server !== null) {
            // collect certificates for this server's ca
            if (isset(Config::getInstance()->object()->cert)) {
                foreach (Config::getInstance()->object()->cert as $cert) {
                    if (isset($cert->refid) && isset($cert->caref) && $server['caref'] == $cert->caref) {
                        $result[(string)$cert->refid] = array(
                            "description" => (string)$cert->descr,
                            "users" => array()
                        );
                    }
                }
            }
            // collect linked users
            foreach (Config::getInstance()->object()->system->user as $user) {
                if (isset($user->cert)) {
                    foreach ($user->cert as $cert) {
                        if (!empty($result[(string)$cert])) {
                            $result[(string)$cert]['users'][] = (string)$user->name;
                        }
                    }
                }
            }
        }
        return $result;
    }

    /**
     * list configured export options (client types)
     * @return array list of templates
     */
    public function templatesAction()
    {
        $result = array();
        $factory = new ExportFactory();
        foreach ($factory->listProviders() as $key => $provider) {
            $result[$key] = array(
                "name" => $provider['handle']->getName(),
                "supportedOptions" => $provider['handle']->supportedOptions()
            );
        }

        return $result;
    }

    /**
     * validate user/model input for configurable options
     * @param $vpnid server handle
     * @return array status and validation output
     * @throws \OPNsense\Base\ModelException
     */
    public function validatePresetsAction($vpnid)
    {
        $result = array("result" => "");
        if ($this->request->isPost()) {
            $result['result'] = 'ok';
            $result['changed'] = false;
            $serverModel = $this->getModel()->getServer($vpnid);
            foreach ($this->request->getPost('openvpn_export') as $key => $value) {
                if ($serverModel->$key !== null) {
                    $serverModel->$key = (string)$value;
                    $result['changed'] = $result['changed'] ? $result['changed'] : $serverModel->$key->isFieldChanged();
                }
            }
            foreach ($this->getModel()->performValidation() as $field => $msg) {
                if (!array_key_exists("validations", $result)) {
                    $result["validations"] = array();
                    $result["result"] = "failed";
                }
                $fieldnm = str_replace($serverModel->__reference, 'openvpn_export', $msg->getField());
                $result["validations"][$fieldnm] = $msg->getMessage();
            }
        }
        return $result;
    }


    /**
     * store presets when valid and changed
     * @param $vpnid server handle
     * @return array status and validation output
     * @throws \OPNsense\Base\ModelException
     */
    public function storePresetsAction($vpnid)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $result = $this->validatePresetsAction($vpnid);
            if ($result['result'] == 'ok' && $result['changed']) {
                $this->getModel()->serializeToConfig();
                Config::getInstance()->save();
            }
        }
        return $result;
    }

    /**
     * download configuration
     * @param string $vpnid server handle
     * @param string $certref certificate to export if applicable
     * @return array
     * @throws \OPNsense\Base\ModelException
     * @throws UserException when invalid user input
     */
    public function downloadAction($vpnid, $certref = null)
    {
        $response = array("result" => "failed");
        if ($this->request->isPost()) {
            $server = (new OpenVPN())->getInstanceById($vpnid);
            if ($server !== null) {
                // fetch server config data
                $config = $server;
                // fetch associated certificate data, add to config
                $config['server_ca_chain'] = '';
                $config['server_subject_name'] = null;
                $config['server_cert_is_srv'] = null;
                if (!empty($server['certref'])) {
                    $cert = (new Store())->getCertificate($server['certref']);
                    if ($cert) {
                        $config['server_cert_is_srv'] = $cert['is_server'];
                        $config['server_subject_name'] = $cert['name'] ?? '';
                        $config['server_subject'] = $cert['subject'] ?? '';
                        if (!empty($cert['ca'])) {
                            $config['server_ca_chain'] = $cert['ca']['crt'];
                        }
                    }
                }
                if ($certref !== null) {
                    $cert = (new Store())->getCertificate($certref);
                    if ($cert) {
                        if (!empty($cert['subject']) && !empty($cert['subject']['CN'])) {
                            $config['client_cn'] = $cert['subject']['CN'];
                            $config['client_crt'] = $cert['crt'];
                            $config['client_prv'] = $cert['prv'];
                        }
                    }
                    if (empty($config['client_cn'])) {
                        throw new UserException("Client certificate not found", gettext("OpenVPN export"));
                    }
                }

                // overlay (saved) user settings
                if ($this->request->hasPost('openvpn_export')) {
                    $response = $this->storePresetsAction($vpnid);
                    // p12 password shouldn't be saved to the config, so we need to copy the content here as
                    // not defined in either model or configuration data.
                    if (!empty($this->request->getPost('openvpn_export')['p12_password'])) {
                        $config['p12_password'] = $this->request->getPost('openvpn_export')['p12_password'];
                    }
                }
                foreach ($this->getModel()->getServer($vpnid)->iterateItems() as $key => $value) {
                    if ($value !== "") {
                        $config[$key] = (string)$value;
                    }
                }
                if ($response['result'] == 'ok') {
                    // request config generation
                    $factory = new ExportFactory();
                    $provider = $factory->getProvider($config['template']);
                    if ($provider !== null) {
                        $provider->setConfig($config);
                        $response['filename'] = $provider->getFilename();
                        $response['filetype'] = $provider->getFileType();
                        $response['content'] = base64_encode($provider->getContent());
                    }
                }
            }
        }
        return $response;
    }
}
