<?php

/*
 * Copyright (C) 2015 Deciso B.V.
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

namespace OPNsense\Auth;

/**
 * Class Radius connector
 * @package OPNsense\Auth
 */
class Radius extends Base implements IAuthConnector
{
    /**
     * @var null radius hostname / ip
     */
    private $radiusHost = null;

    /**
     * @var null port to use for authentication
     */
    private $authPort = "1812";

    /**
     * @var null port to use for accounting
     */
    private $acctPort = null;

    /**
     * @var null shared secret to use for this server
     */
    private $sharedSecret = null;

    /**
     * @var string radius protocol selection
     */
    private $protocol = 'PAP';

    /**
     * @var string called station id to use, read from config
     */
    private $calledStationId = null;

    /**
     * @var string calling station id to use, read from preauth config
     */
    private $callingStationId = null;

    /**
     * @var int timeout to use
     */
    private $timeout = 10;

    /**
     * @var int maximum number of retries
     */
    private $maxRetries = 3;

    /**
     * @var null RADIUS_NAS_IDENTIFIER to use, read from config.
     */
    private $nasIdentifier = 'local';

    /**
     * @var array internal list of authentication properties (returned by radius auth)
     */
    private $lastAuthProperties = [];

    /**
     * @var boolean when set, synchronize groups defined in memberOf attribute to local database
     */
    private $syncMemberOf = false;

    /**
     * @var boolean when set, allow local user creation
     */
    private $syncCreateLocalUsers = false;

    /**
     * @var array limit the groups which will be considered for sync, empty means all
     */
    private $syncMemberOfLimit = [];

    /**
     * @var array list of groups to add by default
     */
    private $syncDefaultGroups = [];

    private function mapTerminateCause($cause)
    {
        switch ($cause) {
            case 'Idle-Timeout':
                return RADIUS_TERM_IDLE_TIMEOUT;
            case 'Session-Timeout':
                return RADIUS_TERM_SESSION_TIMEOUT;
            case 'Admin-Reset':
                return RADIUS_TERM_ADMIN_RESET;
            case 'NAS-Request':
                return RADIUS_TERM_NAS_REQUEST;
            default:
                return RADIUS_TERM_USER_REQUEST;
        }
    }

    private function splitBytes($bytes)
    {
        $limit = 2 ** 32;
        $wraps = intdiv($bytes, $limit);
        $lower32 = $bytes % $limit;
        return [$wraps, $lower32];
    }

    /**
     * type name in configuration
     * @return string
     */
    public static function getType()
    {
        return 'radius';
    }

    /**
     * user friendly description of this authenticator
     * @return string
     */
    public function getDescription()
    {
        return gettext("Radius");
    }

    /**
     * set connector properties
     * @param array $config connection properties
     */
    public function setProperties($config)
    {
        // map properties to object
        $confMap = array('host' => 'radiusHost',
            'radius_secret' => 'sharedSecret',
            'radius_timeout' => 'timeout',
            'radius_auth_port' => 'authPort',
            'radius_acct_port' => 'acctPort',
            'radius_protocol' => 'protocol',
            'radius_stationid' => 'calledStationId',
            'refid' => 'nasIdentifier'
        );

        // map properties 1-on-1
        foreach ($confMap as $confSetting => $objectProperty) {
            if (!empty($config[$confSetting]) && property_exists($this, $objectProperty)) {
                $this->$objectProperty = $config[$confSetting];
            }
        }
        if (!empty($config['sync_create_local_users'])) {
            $this->syncCreateLocalUsers = true;
        }
        if (!empty($config['sync_memberof'])) {
            $this->syncMemberOf = true;
        }
        if (!empty($config['sync_memberof_groups'])) {
            $this->syncMemberOfLimit = explode(",", strtolower($config['sync_memberof_groups']));
        }
        if (!empty($config['sync_default_groups'])) {
            $this->syncDefaultGroups = explode(",", strtolower($config['sync_default_groups']));
        }
    }

    /**
     * retrieve configuration options
     * @return array
     */
    public function getConfigurationOptions()
    {
        $options = [];
        $options['radius_protocol'] = [];
        $options['radius_protocol']['name'] = gettext('Protocol');
        $options['radius_protocol']['type'] = 'dropdown';
        $options['radius_protocol']['default'] = 'PAP';
        $options['radius_protocol']['options'] = [
            'PAP' => 'PAP',
            'MSCHAPv2' => 'MSCHAPv2'
        ];
        $options['radius_protocol']['validate'] = function ($value) {
            if (!in_array($value, ['PAP', 'MSCHAPv2'])) {
                return [gettext('Invalid protocol specified')];
            } else {
                return [];
            }
        };
        return $options;
    }

    /**
     * return session info
     * @return array mixed named list of authentication properties
     */
    public function getLastAuthProperties()
    {
        return $this->lastAuthProperties;
    }

    /**
     * send start accounting message to radius
     * @param string $username username
     * @param string $sessionid session id to pass through
     */
    public function startAccounting($username, $sessionid)
    {
        // only send messages if target port specified
        if ($this->acctPort != null) {
            $radius = radius_auth_open();

            $error = null;
            if (
                !radius_add_server(
                    $radius,
                    $this->radiusHost,
                    $this->acctPort,
                    $this->sharedSecret,
                    $this->timeout,
                    $this->maxRetries
                )
            ) {
                $error = radius_strerror($radius);
            } elseif (!radius_create_request($radius, RADIUS_ACCOUNTING_REQUEST)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_string($radius, RADIUS_NAS_IDENTIFIER, $this->nasIdentifier)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_int($radius, RADIUS_SERVICE_TYPE, RADIUS_FRAMED)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_int($radius, RADIUS_FRAMED_PROTOCOL, RADIUS_ETHERNET)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_int($radius, RADIUS_NAS_PORT, 0)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_int($radius, RADIUS_NAS_PORT_TYPE, RADIUS_ETHERNET)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_string($radius, RADIUS_USER_NAME, $username)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_int($radius, RADIUS_ACCT_STATUS_TYPE, RADIUS_START)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_string($radius, RADIUS_ACCT_SESSION_ID, $sessionid)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_int($radius, RADIUS_ACCT_AUTHENTIC, RADIUS_AUTH_LOCAL)) {
                $error = radius_strerror($radius);
            }

            if ($error != null) {
                syslog(LOG_ERR, 'RadiusError: ' . $error);
            } else {
                $req = radius_send_request($radius);
                if (!$req) {
                    syslog(LOG_ERR, 'RadiusError: ' . radius_strerror($radius));
                    exit;
                }
                switch ($req) {
                    case RADIUS_ACCOUNTING_RESPONSE:
                        break;
                    default:
                        syslog(LOG_ERR, "Unexpected return value:$radius\n");
                }
                radius_close($radius);
            }
        }
    }

    /**
     * stop radius accounting
     * @param string $username user name
     * @param string $sessionid session id
     * @param int $session_time total time spent on this session
     * @param $bytes_in
     * @param $bytes_out
     * @param $ip_address
     * @param $cause see mapTerminateCause for possible values
     */
    public function stopAccounting($username, $sessionid, $session_time, $bytes_in, $bytes_out, $ip_address, $cause = null)
    {
        // only send messages if target port specified
        if ($this->acctPort != null) {
            $radius = radius_auth_open();

            [$wraps_in, $bytes_in] = $this->splitBytes($bytes_in);
            [$wraps_out, $bytes_out] = $this->splitBytes($bytes_out);

            $error = null;
            if (
                !radius_add_server(
                    $radius,
                    $this->radiusHost,
                    $this->acctPort,
                    $this->sharedSecret,
                    $this->timeout,
                    $this->maxRetries
                )
            ) {
                $error = radius_strerror($radius);
            } elseif (!radius_create_request($radius, RADIUS_ACCOUNTING_REQUEST)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_string($radius, RADIUS_NAS_IDENTIFIER, $this->nasIdentifier)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_int($radius, RADIUS_SERVICE_TYPE, RADIUS_FRAMED)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_int($radius, RADIUS_FRAMED_PROTOCOL, RADIUS_ETHERNET)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_int($radius, RADIUS_NAS_PORT, 0)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_int($radius, RADIUS_NAS_PORT_TYPE, RADIUS_ETHERNET)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_string($radius, RADIUS_USER_NAME, $username)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_int($radius, RADIUS_ACCT_STATUS_TYPE, RADIUS_STOP)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_string($radius, RADIUS_ACCT_SESSION_ID, $sessionid)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_int($radius, RADIUS_ACCT_AUTHENTIC, RADIUS_AUTH_LOCAL)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_int($radius, RADIUS_ACCT_SESSION_TIME, $session_time)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_int($radius, RADIUS_ACCT_INPUT_OCTETS, $bytes_in)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_int($radius, RADIUS_ACCT_OUTPUT_OCTETS, $bytes_out)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_int($radius, 52, $wraps_in)) { /* Acct-Input-Gigawords */
                $error = radius_strerror($radius);
            } elseif (!radius_put_int($radius, 53, $wraps_out)) { /* Acct-Output-Gigawords */
                $error = radius_strerror($radius);
            } elseif (!radius_put_addr($radius, RADIUS_FRAMED_IP_ADDRESS, $ip_address)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_int($radius, RADIUS_ACCT_TERMINATE_CAUSE, $this->mapTerminateCause($cause))) {
                $error = radius_strerror($radius);
            }

            if ($error != null) {
                syslog(LOG_ERR, 'RadiusError: ' . $error);
            } else {
                $req = radius_send_request($radius);
                if (!$req) {
                    syslog(LOG_ERR, 'RadiusError: ' . radius_strerror($radius));
                    exit;
                }
                switch ($req) {
                    case RADIUS_ACCOUNTING_RESPONSE:
                        break;
                    default:
                        syslog(LOG_ERR, "Unexpected return value:$radius\n");
                }
                radius_close($radius);
            }
        }
    }

    /**
     * update radius accounting (interim update)
     * @param string $username user name
     * @param string $sessionid session id
     * @param int $session_time total time spend on this session
     * @param $bytes_in
     * @param $bytes_out
     * @param $ip_address
     */
    public function updateAccounting($username, $sessionid, $session_time, $bytes_in, $bytes_out, $ip_address)
    {
        // only send messages if target port specified
        if ($this->acctPort != null) {
            $radius = radius_auth_open();
            if (!defined('RADIUS_UPDATE')) {
                define('RADIUS_UPDATE', 3);
            }

            [$wraps_in, $bytes_in] = $this->splitBytes($bytes_in);
            [$wraps_out, $bytes_out] = $this->splitBytes($bytes_out);

            $error = null;
            if (
                !radius_add_server(
                    $radius,
                    $this->radiusHost,
                    $this->acctPort,
                    $this->sharedSecret,
                    $this->timeout,
                    $this->maxRetries
                )
            ) {
                $error = radius_strerror($radius);
            } elseif (!radius_create_request($radius, RADIUS_ACCOUNTING_REQUEST)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_string($radius, RADIUS_NAS_IDENTIFIER, $this->nasIdentifier)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_int($radius, RADIUS_SERVICE_TYPE, RADIUS_FRAMED)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_int($radius, RADIUS_FRAMED_PROTOCOL, RADIUS_ETHERNET)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_int($radius, RADIUS_NAS_PORT, 0)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_int($radius, RADIUS_NAS_PORT_TYPE, RADIUS_ETHERNET)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_string($radius, RADIUS_USER_NAME, $username)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_int($radius, RADIUS_ACCT_STATUS_TYPE, RADIUS_UPDATE)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_string($radius, RADIUS_ACCT_SESSION_ID, $sessionid)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_int($radius, RADIUS_ACCT_AUTHENTIC, RADIUS_AUTH_LOCAL)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_int($radius, RADIUS_ACCT_SESSION_TIME, $session_time)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_int($radius, RADIUS_ACCT_INPUT_OCTETS, $bytes_in)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_int($radius, RADIUS_ACCT_OUTPUT_OCTETS, $bytes_out)) {
                $error = radius_strerror($radius);
            } elseif (!radius_put_int($radius, 52, $wraps_in)) { /* Acct-Input-Gigawords */
                $error = radius_strerror($radius);
            } elseif (!radius_put_int($radius, 53, $wraps_out)) { /* Acct-Output-Gigawords */
                $error = radius_strerror($radius);
            } elseif (!radius_put_addr($radius, RADIUS_FRAMED_IP_ADDRESS, $ip_address)) {
                $error = radius_strerror($radius);
            }

            if ($error != null) {
                syslog(LOG_ERR, 'RadiusError: ' . $error);
            } else {
                $req = radius_send_request($radius);
                if (!$req) {
                    syslog(LOG_ERR, 'RadiusError: ' . radius_strerror($radius));
                    exit;
                }
                switch ($req) {
                    case RADIUS_ACCOUNTING_RESPONSE:
                        break;
                    default:
                        syslog(LOG_ERR, "Unexpected return value:$radius\n");
                }
                radius_close($radius);
            }
        }
    }

    /**
     * set known per-session radius access request attributes
     * @param array $config
     * @return IAuthConnector
     */
    public function preauth($config)
    {
        if (!empty($config['calling_station_id'])) {
            $this->callingStationId = $config['calling_station_id'];
        }

        return parent::preauth($config);
    }

    /**
     * authenticate user against radius
     * @param string $username username to authenticate
     * @param string $password user password
     * @return bool authentication status
     */
    public function authenticate($username, $password)
    {
        $this->lastAuthProperties = [];// reset auth properties
        $radius = radius_auth_open();

        $error = null;
        if (
            !radius_add_server(
                $radius,
                $this->radiusHost,
                $this->authPort,
                $this->sharedSecret,
                $this->timeout,
                $this->maxRetries
            )
        ) {
            $error = radius_strerror($radius);
        } elseif (!radius_create_request($radius, RADIUS_ACCESS_REQUEST, true)) {
            $error = radius_strerror($radius);
        } elseif (!radius_put_string($radius, RADIUS_USER_NAME, $username)) {
            $error = radius_strerror($radius);
        } elseif (!radius_put_int($radius, RADIUS_SERVICE_TYPE, RADIUS_LOGIN)) {
            $error = radius_strerror($radius);
        } elseif (!radius_put_int($radius, RADIUS_FRAMED_PROTOCOL, RADIUS_ETHERNET)) {
            $error = radius_strerror($radius);
        } elseif (!radius_put_string($radius, RADIUS_NAS_IDENTIFIER, $this->nasIdentifier)) {
            $error = radius_strerror($radius);
        } elseif (!radius_put_int($radius, RADIUS_NAS_PORT, 0)) {
            $error = radius_strerror($radius);
        } elseif (!radius_put_int($radius, RADIUS_NAS_PORT_TYPE, RADIUS_ETHERNET)) {
            $error = radius_strerror($radius);
        } elseif (!empty($this->calledStationId) && !radius_put_string($radius, RADIUS_CALLED_STATION_ID, $this->calledStationId)) {
            $error = radius_strerror($radius);
        } elseif (!empty($this->callingStationId) && !radius_put_string($radius, RADIUS_CALLING_STATION_ID, $this->callingStationId)) {
            $error = radius_strerror($radius);
        } else {
            // Implement extra protocols in this section.
            switch ($this->protocol) {
                case 'PAP':
                    // do PAP authentication
                    if (!radius_put_string($radius, RADIUS_USER_PASSWORD, $password)) {
                        $error = radius_strerror($radius);
                    }
                    break;
                case 'MSCHAPv2':
                    require_once 'Crypt/CHAP.php';
                    $crpt = new \Crypt_CHAP_MSv2();
                    $crpt->username = $username;
                    $crpt->password = $password;

                    $resp = pack(
                        'CCa16a8a24',
                        $crpt->chapid,
                        1,
                        $crpt->peerChallenge,
                        str_repeat("\0", 8),
                        $crpt->challengeResponse()
                    );

                    if (
                        !radius_put_vendor_attr(
                            $radius,
                            RADIUS_VENDOR_MICROSOFT,
                            RADIUS_MICROSOFT_MS_CHAP_CHALLENGE,
                            $crpt->authChallenge
                        )
                    ) {
                        $error = radius_strerror($radius);
                    } elseif (
                        !radius_put_vendor_attr(
                            $radius,
                            RADIUS_VENDOR_MICROSOFT,
                            RADIUS_MICROSOFT_MS_CHAP2_RESPONSE,
                            $resp
                        )
                    ) {
                        $error = radius_strerror($radius);
                    }
                    break;
                default:
                    syslog(LOG_ERR, 'Unsupported protocol ' . $this->protocol);
                    return false;
            }
        }

        // reset preauth attributes
        $this->callingStationId = null;

        // log errors and perform actual authentication request
        if ($error != null) {
            syslog(LOG_ERR, 'RadiusError: ' . $error);
        } else {
            $request = radius_send_request($radius);
            if (!$request) {
                syslog(LOG_ERR, 'RadiusError: ' . radius_strerror($radius));
            } else {
                switch ($request) {
                    case RADIUS_ACCESS_ACCEPT:
                        while ($resa = radius_get_attr($radius)) {
                            switch ($resa['attr']) {
                                case RADIUS_SESSION_TIMEOUT:
                                    $this->lastAuthProperties['session_timeout'] = radius_cvt_int($resa['data']);
                                    break;
                                case 85: // Acct-Interim-Interval
                                    $this->lastAuthProperties['Acct-Interim-Interval'] = radius_cvt_int($resa['data']);
                                    break;
                                case RADIUS_FRAMED_IP_ADDRESS:
                                    $this->lastAuthProperties['Framed-IP-Address'] = radius_cvt_addr($resa['data']);
                                    break;
                                case RADIUS_FRAMED_IP_NETMASK:
                                    $this->lastAuthProperties['Framed-IP-Netmask'] = radius_cvt_addr($resa['data']);
                                    break;
                                case RADIUS_FRAMED_ROUTE:
                                    if (empty($this->lastAuthProperties['Framed-Route'])) {
                                        $this->lastAuthProperties['Framed-Route'] = [];
                                    }
                                    $this->lastAuthProperties['Framed-Route'][] = $resa['data'];
                                    break;
                                case RADIUS_CLASS:
                                    if (!$this->syncMemberOf) {
                                        break;
                                    } elseif (!empty($this->lastAuthProperties['class'])) {
                                        $this->lastAuthProperties['class'] .= "\n" . $resa['data'];
                                    } else {
                                        $this->lastAuthProperties['class'] = $resa['data'];
                                    }
                                    break;
                                default:
                                    break;
                            }
                        }
                        // update group policies when applicable
                        if ($this->syncMemberOf || $this->syncCreateLocalUsers) {
                            $this->setGroupMembership(
                                $username,
                                $this->lastAuthProperties['class'] ?? '',
                                $this->syncMemberOf ? $this->syncMemberOfLimit : $this->syncDefaultGroups,
                                $this->syncCreateLocalUsers,
                                $this->syncDefaultGroups
                            );
                        }
                        return true;
                        break;
                    case RADIUS_ACCESS_REJECT:
                        return false;
                        break;
                    default:
                        // unexpected result, log
                        syslog(LOG_ERR, 'Radius unexpected response:' . $request);
                }
            }
        }
        return false;
    }
}
