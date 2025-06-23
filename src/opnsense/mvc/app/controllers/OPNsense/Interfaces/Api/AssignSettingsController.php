<?php

namespace OPNsense\Interfaces\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Base\UserException;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;

class AssignSettingsController extends ApiControllerBase
{
    // set version number
    private $version = '1.0';
    
    /**
     * add the interface configuration
     *
     * required POST-Request:
     * {
     *     "assign": {
     *         "device": "vlan0.250" # required, name of the physical/virtual device
     *         "interface": "opt1", # optional, if not set a new interface will be created
     *         "descr": "OPT1", # optional, description of the interface
     *         "enable": "true", # optional, enable the interface
     *         "ipaddr": "xxx.xxx.xxx.xxx", # optional, ip address
     *         "spoofmac": "xx:xx:xx:xx:xx:xx", # optional, spoof mac address
     *         "subnet": "xx" # optional, subnet
     *         "gateway_interface": "true", # optional, dynamic interface gateway
     *     }
     * }
     *
     * @return array
     * @throws UserException
     */
    public function AddItemAction()
    {
        $result = array("result" => "failed");

        // Retrieve parameters from the POST request
        $data = $this->request->getPost("assign");
        if (empty($data['device'])) {
            throw new UserException(gettext("The parameter 'device' is required."));
        }

        // load configuration
        $configHandle = Config::getInstance()->object();

        // If an interface name was provided and exists in the model, we use it,
        // otherwise, a new node name in the format "optX" is generated.
        if (isset($data['interface']) && !empty($data['interface']) &&
            !empty($configHandle->interfaces->{ $data['interface'] })) {
            $ifname = $data['interface'];
        } else {
            for ($i = 1; $i <= count($configHandle->interfaces); $i++) {
                if (empty($config['interfaces']["opt{$i}"])) {
                    break;
                }
            }
            $ifname = "opt" . $i;
        }

        // Adjust interface assignment: Set the node "if" to the new physical device
        $interface = $configHandle->interfaces->{$ifname};
        $configHandle->interfaces->{$ifname}->if = $data['device'];
        $configHandle->interfaces->{$ifname}->descr = !empty($data['descr']) ? $data['descr'] : strtoupper($ifname);

        if (!empty($data['enable']) && isset($data['enable'])) {
            $configHandle->interfaces->{$ifname}->enable = (bool)$data['enable'];
            //$configHandle->interfaces->{$ifname}->enable = $data['enable'];
        }
        if(!empty($data['ipaddr'])) {
            $configHandle->interfaces->{$ifname}->ipaddr = $data['ipaddr'];
        }
        $configHandle->interfaces->{$ifname}->spoofmac = $data['spoofmac'];
        if(!empty($data['gateway_interface'])) {
            $configHandle->interfaces->{$ifname}->gateway_interface = (bool)$data['gateway_interface'];
        }
        if(!empty($data['subnet'])) {
            $configHandle->interfaces->{$ifname}->subnet = $data['subnet'];
        }
        

        // save the configuration
        Config::getInstance()->save();

        // changes are done, apply them
        $backend = new Backend();
        $end = strtolower(trim($backend->configdRun("interface reconfigure {$ifname}")));
        
        $result['result'] = 'saved';
        $result['uuid'] = $ifname; // needed for ifname in response

        return $result;
    }

    /** todos: check for groups, bridge, gre, gif
     * Updates the configuration of an interface.
     * 
     * required POST-Request:
     * {
     *    "assign": {
     *      "device": "vlan0.250" # required, name of the physical/virtual device
     *      "descr": "OPT1", # optional, description of the interface
     *      "enable": "true", # optional, enable the interface
     *      "ipaddr": "xxx.xxx.xxx.xxx", # optional, ip address
     *      "spoofmac": "xx:xx:xx:xx:xx:xx", # optional, spoof mac address
     *      "subnet": "xx" # optional, subnet
     *      "gateway_interface": "true", # optional, dynamic interface gateway
     *     }
     * }
     * 
     * @param string $uuid
     * @return array
     * @throws UserException
     */
    public function setItemAction($uuid)
    {
        $result = array("result" => "failed");

        if (empty($uuid)) {
            throw new UserException(gettext("The parameter 'uuid' is required."));
        }

        $configHandle = Config::getInstance()->object();
        if (!isset($configHandle->interfaces->{$uuid})) {
            throw new UserException(gettext("The interface '{$uuid}' does not exist."));
        }

        $data = $this->request->getPost("assign");

        if (empty($data['device'])) {
            throw new UserException(gettext("The parameter 'device' is required."));
        }

        $configHandle->interfaces->{$uuid}->if = $data['device'];
        $configHandle->interfaces->{$uuid}->descr = !empty($data['descr']) ? $data['descr'] : strtoupper($uuid);

        if (!empty($data['enable']) && $data['enable'] == 'false') {
            unset($configHandle->interfaces->{$uuid}->enable);
        } else {
            $configHandle->interfaces->{$uuid}->enable = (bool)$data['enable'];
        }

        if(!empty($data['gateway_interface']) && $data['gateway_interface'] == 'false') {
            unset($configHandle->interfaces->{$uuid}->gateway_interface);
        } else {
            $configHandle->interfaces->{$uuid}->gateway_interface = (bool)$data['gateway_interface'];
        }

        if(!empty($data['ipaddr'])) {
            $configHandle->interfaces->{$uuid}->ipaddr = $data['ipaddr'];
        }
        $configHandle->interfaces->{$uuid}->spoofmac = $data['spoofmac'];

        if(!empty($data['subnet'])) {
            $configHandle->interfaces->{$uuid}->subnet = $data['subnet'];
        }

        Config::getInstance()->save();

        $backend = new Backend();
        $end = strtolower(trim($backend->configdRun("interface reconfigure {$uuid}")));

        $result['result'] = 'saved';

        return $result;
    }

    /**
     * Removes the assignment of an interface.
     *
     * Expects a POST request with the following parameter: uuid (name of the interface)
     * @return array
     */
    public function delItemAction($uuid)
    {
        // TODOs:
        // - check for groups, bridge, gre, gif

        $result = array("result" => "failed");

        if (empty($uuid)) {
            throw new UserException(gettext("The parameter 'uuid' is required."));
        }

        // Load configuration
        $configHandle = Config::getInstance()->object();

        if (!isset($configHandle->interfaces->{$uuid})) {
            throw new UserException(gettext("The interface '{$uuid}' does not exist."));
        }
        
        // First disable the interface then reset it
        unset($configHandle->interfaces->{$uuid}->enable);
        
        // Remove dhcp4
        if (isset($configHandle->dhcpd->{$uuid})) {
            unset($configHandle->dhcpd->{$uuid});
            //plugins_configure('dhcp', false, array('inet'));
        }
        if (isset($configHandle->dhcpdv6->{$uuid})) {
            unset($configHandle->dhcpdv6->{$uuid});
            //plugins_configure('dhcp', false, array('inet6'));
        }
        // Remove filter and nat rules
        if (isset($configHandle->filter->rule)) {
            foreach ($configHandle->filter->rule as $x => $rule) {
                if (isset($rule['interface']) && $rule['interface'] == $uuid) {
                    unset($configHandle->filter->rule->{$x});
                }
            }
        }
        if (isset($configHandle->nat->rule)) {
            foreach ($configHandle->nat->rule as $x => $rule) {
                if ($rule['interface'] == $uuid) {
                    unset($configHandle->nat->rule->{$x}->interface);
                }
            }
        }

        // Everything is done -> delete the interface
        unset($configHandle->interfaces->{$uuid});

        Config::getInstance()->save();

        $backend = new Backend();
        $end = strtolower(trim($backend->configdRun("interface reconfigure {$uuid}")));
        
        $result = array("result" => "deleted");

        return $result;
    }

    /**
     * Returns the list of available interfaces.
     *
     * @return array
     */
    public function getInterfaceListAction() {
        $result = array("result" => "failed");

        $configHandle = Config::getInstance()->object();
        $interfaces = [];
        if (!empty($configHandle->interfaces)) {
            foreach ($configHandle->interfaces->children() as $ifname => $node) {
                if (!empty((string)$node->type) && $node->type == 'group') {
                    continue;
                } elseif (!empty((string)$node->if) && $node->if == 'enc0') {
                    continue;
                }
                $descr = !empty((string)$node->descr) ? (string)$node->descr : strtoupper($ifname);
                $interfaces[$ifname] = $descr;
            }
        }

        $result['interfaces'] = $interfaces;
        $result['result'] = "ok";

        return $result;
    }

    /**
     * Returns the configuration of an interface.
     *
     * Expects a GET request with the following parameter: uuid (name of the interface)
     * @return array
     */
    public function getItemAction($uuid) {

        if (empty($uuid)) {
            throw new UserException(gettext("The parameter 'ifname' is required."));
        }

        $configHandle = Config::getInstance()->object();
        if (!isset($configHandle->interfaces->{$uuid})) {
            // throw new UserException(gettext("The interface '{$uuid}' does not exist."));
            return [];
        }

        $result['assign'] = $configHandle->interfaces->{$uuid};

        return $result;
    }

    /**
     * Reconfigure an interface.
     *
     * Expects a POST request with the following parameter: ifname (name of the interface)
     * @return array
     */
    public function reconfigureAction() {
        
        $result = array("result" => "ok");

        return $result;
        /*
        if ($this->request->isPost()) {
            $result['result'] = strtolower(trim((new Backend())->configdRun('interface reconfigure ' . $ifname)));
        }

        return $result;*/
    }

    public function versionAction() {
        $result = array("status" => "ok");

        $result['version'] = $this->version;

        return $result;
    }

}