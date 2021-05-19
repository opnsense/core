<?php

/**
 *    Copyright (C) 2018 Deciso B.V.
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace OPNsense\Firewall\FieldTypes;

use OPNsense\Base\FieldTypes\BaseField;
use OPNsense\Base\Validators\CallbackValidator;
use Phalcon\Validation\Validator\Regex;
use Phalcon\Validation\Validator\ExclusionIn;
use Phalcon\Messages\Message;
use OPNsense\Firewall\Util;

/**
 * Class AliasContentField
 * @package OPNsense\Base\FieldTypes
 */
class AliasContentField extends BaseField
{
    /**
     * @var bool marks if this is a data node or a container
     */
    protected $internalIsContainer = false;

    /**
     * @var string default validation message string
     */
    protected $internalValidationMessage = "alias name required";

    /**
     * @var array list of known countries
     */
    private static $internalCountryCodes = array();

    /**
     * item separator
     * @var string
     */
    private $separatorchar = "\n";

    /**
     * retrieve data as options
     * @return array
     */
    public function getNodeData()
    {
        $result = array ();
        $selectlist = explode($this->separatorchar, (string)$this);
        foreach ($selectlist as $optKey) {
            $result[$optKey] = array("value" => $optKey, "selected" => 1);
        }
        return $result;
    }


    /**
     * split and yield items
     * @param array $data to validate
     * @return \Generator
     */
    private function getItems($data)
    {
        foreach (explode($this->separatorchar, $data) as $value) {
            yield $value;
        }
    }

    /**
     * return separator character used
     * @return string
     */
    public function getSeparatorChar()
    {
        return $this->separatorchar;
    }

    /**
     * fetch valid country codes
     * @return array valid country codes
     */
    private function getCountryCodes()
    {
        if (empty(self::$internalCountryCodes)) {
            foreach (explode("\n", file_get_contents('/usr/local/opnsense/contrib/tzdata/iso3166.tab')) as $line) {
                $line = trim($line);
                if (strlen($line) > 3 && substr($line, 0, 1) != '#') {
                    self::$internalCountryCodes[] = substr($line, 0, 2);
                }
            }
        }
        return self::$internalCountryCodes;
    }

    /**
     * Validate port alias options
     * @param array $data to validate
     * @return bool|Callback
     * @throws \OPNsense\Base\ModelException
     */
    private function validatePort($data)
    {
        $messages = array();
        foreach ($this->getItems($data) as $port) {
            if (!Util::isAlias($port) && !Util::isPort($port, true)) {
                $messages[] = sprintf(
                    gettext('Entry "%s" is not a valid port number.'),
                    $port
                );
            }
        }
        return $messages;
    }

    /**
     * Validate host options
     * @param array $data to validate
     * @return bool|Callback
     * @throws \OPNsense\Base\ModelException
     */
    private function validateHost($data)
    {
        $messages = array();
        foreach ($this->getItems($data) as $host) {
            if (strpos($host, "!") === 0 && Util::isIpAddress(substr($host, 1))) {
                // exclude address (https://www.freebsd.org/doc/handbook/firewalls-pf.html 30.3.2.4)
                continue;
            } elseif (!Util::isAlias($host) && !Util::isIpAddress($host) && !Util::isDomain($host)) {
                $messages[] = sprintf(
                    gettext('Entry "%s" is not a valid hostname or IP address.'),
                    $host
                );
            }
        }
        return $messages;
    }

    /**
     * Validate host options
     * @param array $data to validate
     * @return bool|Callback
     * @throws \OPNsense\Base\ModelException
     */
    private function validateNestedAlias($data)
    {
        $messages = array();
        foreach ($this->getItems($data) as $host) {
            if (!Util::isAlias($host)) {
                $messages[] = sprintf(
                    gettext('Entry "%s" is not a valid alias.'),
                    $host
                );
            }
        }
        return $messages;
    }

    /**
     * Validate network options
     * @param array $data to validate
     * @return bool|Callback
     * @throws \OPNsense\Base\ModelException
     */
    private function validateNetwork($data)
    {
        $messages = array();
        foreach ($this->getItems($data) as $network) {
            $ipaddr_count = 0;
            $domain_alias_count = 0;
            foreach (explode('-', $network) as $tmpaddr) {
                if (Util::isIpAddress($tmpaddr)) {
                    $ipaddr_count++;
                } elseif (trim($tmpaddr) != "") {
                    $domain_alias_count++;
                }
            }
            if (
                strpos($network, "!") === 0 &&
                  (
                    Util::isIpAddress(substr($network, 1)) ||
                    Util::isSubnet(substr($network, 1)) ||
                    Util::isWildcard(substr($network, 1))
                  )
            ) {
                // exclude address or network (https://www.freebsd.org/doc/handbook/firewalls-pf.html 30.3.2.4)
                continue;
            } elseif (
                !Util::isAlias($network) &&
                !Util::isIpAddress($network) &&
                !Util::isSubnet($network) &&
                !Util::isWildcard($network) &&
                    !($ipaddr_count == 2 && $domain_alias_count == 0)
            ) {
                $messages[] = sprintf(
                    gettext('Entry "%s" is not a network.'),
                    $network
                );
            }
        }
        return $messages;
    }

    /**
     * Validate partial ipv6 network definition
     * @param array $data to validate
     * @return bool|Callback
     * @throws \OPNsense\Base\ModelException
     */
    private function validatePartialIPv6Network($data)
    {
        $messages = array();
        foreach ($this->getItems($data) as $pnetwork) {
            if (!Util::isSubnet("0000" . $pnetwork)) {
                $messages[] = sprintf(
                    gettext('Entry "%s" is not a valid partial ipv6 net definition (e.g. ::1000/64).'),
                    $pnetwork
                );
            }
        }
        return $messages;
    }

    /**
     * Validate host options
     * @param array $data to validate
     * @return bool|Callback
     */
    private function validateCountry($data)
    {
        $country_codes = $this->getCountryCodes();
        $messages = array();
        foreach ($this->getItems($data) as $country) {
            if (!in_array($country, $country_codes)) {
                $messages[] = sprintf(gettext('Entry "%s" is not a valid country code.'), $country);
            }
        }
        return $messages;
    }

    /**
     * Validate (partial) mac address options
     * @param array $data to validate
     * @return bool|Callback
     * @throws \OPNsense\Base\ModelException
     */
    private function validatePartialMacAddr($data)
    {
        $messages = array();
        foreach ($this->getItems($data) as $macaddr) {
            if (!preg_match('/^[0-9A-Fa-f]{2}(?:[:][0-9A-Fa-f]{2}){1,5}$/i', $macaddr)) {
                $messages[] = sprintf(
                    gettext('Entry "%s" is not a valid (partial) MAC address.'),
                    $macaddr
                );
            }
        }
        return $messages;
    }

    /**
     * retrieve field validators for this field type
     * @return array
     */
    public function getValidators()
    {
        $validators = parent::getValidators();
        if ($this->internalValue != null) {
            switch ((string)$this->getParentNode()->type) {
                case "port":
                    $validators[] = new CallbackValidator(["callback" => function ($data) {
                        return $this->validatePort($data);
                    }
                    ]);
                    break;
                case "host":
                    $validators[] = new CallbackValidator(["callback" => function ($data) {
                        return $this->validateHost($data);
                    }
                    ]);
                    break;
                case "geoip":
                    $validators[] = new CallbackValidator(["callback" => function ($data) {
                        return $this->validateCountry($data);
                    }
                    ]);
                    break;
                case "network":
                    $validators[] = new CallbackValidator(["callback" => function ($data) {
                        return $this->validateNetwork($data);
                    }
                    ]);
                    break;
                case "networkgroup":
                    $validators[] = new CallbackValidator(["callback" => function ($data) {
                        return $this->validateNestedAlias($data);
                    }
                    ]);
                    break;
                case "mac":
                    $validators[] = new CallbackValidator(["callback" => function ($data) {
                        return $this->validatePartialMacAddr($data);
                    }
                    ]);
                    break;
                case "dynipv6host":
                    $validators[] = new CallbackValidator(["callback" => function ($data) {
                        return $this->validatePartialIPv6Network($data);
                    }
                    ]);
                    break;
                default:
                    break;
            }
        }
        return $validators;
    }
}
