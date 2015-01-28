<?php
/**
 *    Copyright (C) 2015 Deciso B.V.
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
namespace OPNsense\CaptivePortal;

use \Phalcon\Logger\Adapter\Syslog;
use \Phalcon\DI\FactoryDefault;
use \OPNsense\Core;

/**
 * Class CPClient main class for captive portal backend functionality
 * // TODO: CARP interfaces are probably not handled correctly
 * @package CaptivePortal
 */
class CPClient
{
    /**
     * config handle
     * @var Core_Config
     */
    private $config = null;

    /**
     * ipfw rule object
     * @var \CaptivePortal\Rules
     */
    private $rules = null;

    /**
     * link to shell object
     * @var  Core\Shell
     */
    private $shell = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Request handle to configuration
        $this->config = Core\Config::getInstance();
        // generate new ruleset
        $this->rules = new Rules();
        // keep a link to the shell object
        $this->shell = new Core\Shell();
    }

    /**
     * reset traffic counters
     *
     * @param string|null $rulenum
     */
    public function zeroCounters($rulenum = null)
    {
        if ($rulenum != null and is_numeric($rulenum)) {
            $this->shell->exec("/sbin/ipfw zero " . $rulenum);
        } elseif ($rulenum == null) {
            $this->shell->exec("/sbin/ipfw zero ");
        }

    }

    /**
     * Reconfigure zones ( generate and load ruleset )
     */
    public function reconfigure()
    {
        if ($this->isEnabled()) {
            $ruleset_filename = FactoryDefault::getDefault()->get('config')->globals->temp_path."/ipfw.rules";
            $this->rules->generate($ruleset_filename);

            // load ruleset
            $this->shell->exec("/sbin/ipfw -f ".$ruleset_filename);

            // update tables
            $this->update();

            // after reinit all accounting rules are vanished, reapply them for active sessions
            $this->loadAccounting();
        } else {
            // captiveportal is disabled, flush all rules to be sure
            $this->shell->exec("/sbin/ipfw -f flush");
        }
    }

    /**
     * check if captiveportal is enabled (traverse zones, if none active return false )
     * @return bool
     */
    public function isEnabled()
    {
        $enabled_zones = 0 ;
        foreach ($this->config->object()->captiveportal->children() as $cpzonename => $zone) {
            if (isset($zone->enable)) {
                $enabled_zones++;
            }
        }

        if ($enabled_zones > 0) {
            return true;
        } else {
            return false ;
        }
    }

    /**
     * update zone(s) with new configuration data
     * @param string|null $zone
     */
    public function update($zone = null)
    {
        $this->refreshAllowedIPs($zone);
        $this->refreshAllowedMACs($zone);
    }

    /**
     * refresh allowed ip's for defined zone ( null for all zones )
     * @param string|null $cpzone
     */
    public function refreshAllowedIPs($cpzone = null)
    {
        $handled_addresses = array();
        foreach ($this->config->object()->captiveportal->children() as $cpzonename => $zone) {
            // search requested zone (id)
            if ($cpzonename == $cpzone || $zone->zoneid == $cpzone || $cpzone == null) {
                $db = new DB($cpzonename);
                $db_iplist = $db->listFixedIPs();

                // calculate table numbers for this zone
                $ipfw_tables = $this->rules->getAuthIPTables($zone->zoneid);

                foreach ($zone->children() as $tagname => $tagcontent) {
                    $ip = $tagcontent->ip->__toString();
                    if ($tagname == 'allowedip') {
                        $handled_addresses[$ip] = array();
                        $handled_addresses[$ip]["bw_up"] = $tagcontent->bw_up->__toString() ;
                        $handled_addresses[$ip]["bw_down"] = $tagcontent->bw_down->__toString() ;

                        if (!array_key_exists($ip, $db_iplist)) {
                            // only insert new values
                            $pipeno_in = $this->newIPFWpipeno() ;
                            $pipeno_out = $this->newIPFWpipeno() ;

                            $exec_commands = array(
                                # insert new ip address
                                "/sbin/ipfw table ". $ipfw_tables["in"]  ." add " .
                                $ip . "/" . $tagcontent->sn->__toString() . " " . $pipeno_in,
                                "/sbin/ipfw table ". $ipfw_tables["out"] ." add " .
                                $ip . "/" . $tagcontent->sn->__toString() . " " . $pipeno_out,
                            );

                            // execute all ipfw actions
                            $this->shell->exec($exec_commands, false, false);
                            // update administration
                            $db->upsertFixedIP($ip, $pipeno_in, $pipeno_out);
                            // save bandwidth data
                            $handled_addresses[$ip]["pipeno_in"] = $pipeno_in ;
                            $handled_addresses[$ip]["pipeno_out"] = $pipeno_out ;
                        } else {
                            //
                            $handled_addresses[$ip]["pipeno_in"] = $db_iplist[$ip]->pipeno_in ;
                            $handled_addresses[$ip]["pipeno_out"] = $db_iplist[$ip]->pipeno_out ;
                        }
                    }

                }


                // Cleanup deleted addresses
                foreach ($db_iplist as $ip => $record) {
                    if (!array_key_exists($ip, $handled_addresses)) {
                        $exec_commands = array(
                            # insert new ip address
                            "/sbin/ipfw table ". $ipfw_tables["in"]  .
                            " del " . $ip . "/" . $tagcontent->sn->__toString() ,
                            "/sbin/ipfw table ". $ipfw_tables["out"] .
                            " del " . $ip . "/" . $tagcontent->sn->__toString() ,
                        );

                        // execute all ipfw actions
                        $this->shell->exec($exec_commands, false, false);
                        // TODO : cleanup $record->pipeno_in, $record->pipeno_out ;
                        $db->dropFixedIP($ip);
                    }
                }

                // reset bandwidth,
                foreach ($handled_addresses as $mac => $record) {
                    if (array_key_exists("pipeno_in", $record)) {
                        $this->resetBandwidth($record["pipeno_in"], $record["bw_down"]);
                        $this->resetBandwidth($record["pipeno_out"], $record["bw_up"]);
                    }
                }

                unset($db);
            }
        }

    }

    /**
     * Request new pipeno
     * @return int
     */
    private function newIPFWpipeno()
    {
        // TODO: implement global pipe number assigment
        return 999;
    }

    /**
     * reset bandwidth, if the current bandwidth is unchanged, do nothing
     * @param  int $pipeno system pipeno
     * @param  int $bw  bandwidth in Kbit/s
     * @return status
     */
    private function resetBandwidth($pipeno, $bw)
    {
        //TODO : setup bandwidth for sessions ( check changed )
        //#pipe 2000 config bw 2000Kbit/s
        return false;
    }

    /**
     * To be able to grant access to physical pc's, we need to do some administration.
     * Our captive portal database keeps a list of every used address and last know mac address
     *
     * @param string|null $cpzone zone name or number
     */
    public function refreshAllowedMACs($cpzone = null)
    {

        // read ARP table
        $arp= new ARP();
        $arp_maclist = $arp->getMACs();

        // keep a list of handled addresses, so we can cleanup the rest and keep track of needed bandwidth restrictions
        $handled_mac_addresses = array();
        foreach ($this->config->object()->captiveportal->children() as $cpzonename => $zone) {
            if ($cpzonename == $cpzone || $zone->zoneid == $cpzone || $cpzone == null) {
                // open administrative database for this zone
                $db = new DB($cpzonename);
                $db_maclist = $db->listPassthruMacs();
                $ipfw_tables = $this->rules->getAuthMACTables($zone->zoneid);

                foreach ($zone->children() as $tagname => $tagcontent) {
                    $mac = trim(strtolower($tagcontent->mac));
                    if ($tagname == 'passthrumac') {
                        // only accept valid macaddresses
                        if (preg_match('/^([a-fA-F0-9]{2}:){5}[a-fA-F0-9]{2}$/', $mac)) {
                            if ($tagcontent->action == "pass") {
                                $handled_mac_addresses[$mac] = array("action"=>"skipped" );
                                $handled_mac_addresses[$mac]["bw_up"] = $tagcontent->bw_up ;
                                $handled_mac_addresses[$mac]["bw_down"] = $tagcontent->bw_down ;

                                // only handle addresses we know of
                                if (array_key_exists($mac, $arp_maclist)) {
                                    // if the address is already in our database, check if it has changed
                                    if (array_key_exists($mac, $db_maclist)) {
                                        // save pipe numbers for bandwidth restriction
                                        $handled_mac_addresses[$mac]["pipeno_in"] = $db_maclist[$mac]->pipeno_in ;
                                        $handled_mac_addresses[$mac]["pipeno_out"] = $db_maclist[$mac]->pipeno_out ;

                                        if ($db_maclist[$mac]->ip !=  $arp_maclist[$mac]['ip']) {
                                            // handle changed ip,
                                            $handled_mac_addresses[$mac]["action"] = "changed ip";
                                            $exec_commands = array(
                                                # delete old ip address
                                                "/sbin/ipfw table ". $ipfw_tables["in"] .
                                                " delete ". $db_maclist[$mac]->ip,
                                                "/sbin/ipfw table ". $ipfw_tables["out"] .
                                                " delete ". $db_maclist[$mac]->ip,
                                                # insert new ip address
                                                "/sbin/ipfw table ". $ipfw_tables["in"]  .
                                                " add " . $arp_maclist[$mac]['ip']. " " . $db_maclist[$mac]->pipeno_in,
                                                "/sbin/ipfw table ". $ipfw_tables["out"] .
                                                " add " . $arp_maclist[$mac]['ip']. " " . $db_maclist[$mac]->pipeno_out,
                                            );

                                            // execute all ipfw actions
                                            $this->shell->exec($exec_commands, false, false);
                                            // update administration
                                            $db->upsertPassthruMAC(
                                                $tagcontent->mac,
                                                $arp_maclist[$mac]['ip'],
                                                $db_maclist[$mac]->pipeno_in,
                                                $db_maclist[$mac]->pipeno_out
                                            ); // new ip according to arp table
                                        }
                                    } else {
                                        // new host, not seen it yet
                                        $handled_mac_addresses[$mac]["action"] = "new";
                                        $pipeno_in = $this->newIPFWpipeno() ;
                                        $pipeno_out = $this->newIPFWpipeno() ;

                                        // execute all ipfw actions
                                        $exec_commands = array(
                                            # insert new ip address
                                            "/sbin/ipfw table ". $ipfw_tables["in"]  .
                                            " add " . $arp_maclist[$mac]['ip']. " " . $pipeno_in,
                                            "/sbin/ipfw table ". $ipfw_tables["out"] .
                                            " add " . $arp_maclist[$mac]['ip']. " " . $pipeno_out,
                                        );
                                        $this->shell->exec($exec_commands, false, false);

                                        $db->upsertPassthruMAC(
                                            $tagcontent->mac,
                                            $arp_maclist[$mac]['ip'],
                                            $pipeno_in,
                                            $pipeno_out
                                        );
                                        // save pipe numbers for bandwidth restriction
                                        $handled_mac_addresses[$mac]["pipeno_in"] = $pipeno_in ;
                                        $handled_mac_addresses[$mac]["pipeno_out"] =  $pipeno_out ;
                                    }
                                }
                            }
                        }
                    }
                }

                //
                // cleanup old addresses
                //
                foreach ($db_maclist as $mac => $record) {
                    if (!array_key_exists($mac, $handled_mac_addresses)) {
                        # delete old ip address, execute all actions
                        $exec_commands = array(
                            "/sbin/ipfw table ". $ipfw_tables["in"] .
                            " delete ". $db_maclist[$mac]->ip,
                            "/sbin/ipfw table ". $ipfw_tables["out"] .
                            " delete ". $db_maclist[$mac]->ip,
                        );
                        $this->shell->exec($exec_commands, false, false);
                        // TODO : cleanup $record->pipeno_in, $record->pipeno_out ;
                        $db->dropPassthruMAC($mac);
                    }
                }

                // reset bandwidth
                foreach ($handled_mac_addresses as $mac => $record) {
                    if (array_key_exists("pipeno_in", $record)) {
                        $this->resetBandwidth($record["pipeno_in"], $record["bw_down"]);
                        $this->resetBandwidth($record["pipeno_out"], $record["bw_up"]);
                    }
                }

                unset($db);

            }
        }

    }

    /**
     * load accounting rules into ruleset, used for reinitialisation of the ruleset.
     * triggers addAccounting() for all active clients in all zones
     */
    private function loadAccounting()
    {
        foreach ($this->config->object()->captiveportal->children() as $cpzonename => $zone) {
            $db = new DB($cpzonename);
            foreach ($db->listClients(array()) as $client) {
                $this->addAccounting($zone->zoneid, $client->ip) ;
            }
            unset($db);
        }
    }

    /**
     * add accounting rules for ip
     * @param int $zoneid zone
     * @param string $ip  ip address
     */
    public function addAccounting($zoneid, $ip)
    {
        // TODO: check processing speed, this might need some improvement
        // check if our ip is already in the list and collect first free rule number to place it there if necessary
        $shell_output=array();
        $this->shell->exec("/sbin/ipfw show", false, false, $shell_output);
        $prev_id = 0;
        $new_id = null;
        foreach ($shell_output as $line) {
            // only trigger on counter rules and last item in the list
            if (strpos($line, " count ") !== false || strpos($line, "65535 ") !== false) {
                if (strpos($line, " ".$ip." ") !== false) {
                    // already in table... exit
                    return;
                }

                $this_line_id = (int)(explode(" ", $line)[0]) ;
                if ($this_line_id  > 30000 and ($this_line_id -1) > $prev_id and $new_id == null) {
                    // new id found
                    if ($this_line_id == 65535) {
                        $new_id = $prev_id+1;
                    } else {
                        $new_id = $this_line_id-1;
                    }
                }

                $prev_id =  $this_line_id;
            }
        }

        if ($new_id != null) {
            $exec_commands = array(
                "/sbin/ipfw add " . $new_id . " set " . $zoneid . " count ip from " . $ip . " to any ",
                "/sbin/ipfw add " . $new_id . " set " . $zoneid . " count ip from  any to " . $ip,
            );

            // execute all ipfw actions
            $this->shell->exec($exec_commands, false, false);
        }
    }

    /**
     * unlock host for captiveportal use
     * @param string $cpzonename
     * @param string $clientip
     * @param string $clientmac
     * @param string $username
     * @param string|null $password
     * @param string|null $bw_up
     * @param string|null $bw_down
     * @param string|null $radiusctx
     * @param int|null $session_timeout
     * @param int|null $idle_timeout
     * @param int|null $session_terminate_time
     * @param int|null $interim_interval
     * @return bool|string
     */
    public function portalAllow(
        $cpzonename,
        $clientip,
        $clientmac,
        $username,
        $password = null,
        $bw_up = null,
        $bw_down = null,
        $radiusctx = null,
        $session_timeout = null,
        $idle_timeout = null,
        $session_terminate_time = null,
        $interim_interval = null
    ) {
        // defines
        $exec_commands = array() ;
        $db = new DB($cpzonename);
        $arp= new ARP();

        // find zoneid for this named zone
        $zoneid = -1;
        foreach ($this->config->object()->captiveportal->children() as $zone => $zoneobj) {
            if ($zone == $cpzonename) {
                $zoneid = $zoneobj->zoneid;
            }
        }

        if ($zoneid == -1) {
            return false; // not a valid zone, bailout
        }


        // grap needed data to generate our rules
        $ipfw_tables = $this->rules->getAuthUsersTables($zoneid);
        $cp_table = $db->listClients(array("mac"=>$clientmac, "ip"=>$clientip), "or");
        if (sizeof($cp_table) > 0 && ($cp_table[0]->ip == $clientip && $cp_table[0]->mac == $clientmac)) {
            // nothing (important) changed here... move on
            return $cp_table[0]->sessionid;
        } elseif (sizeof($cp_table) > 0) {
            // something changed...
            // prevent additional sessions to popup,
            // one MAC should have only one active session, remove the rest (if any)
            $cnt = 0;
            $remove_sessions = array();
            foreach ($cp_table as $record) {
                if ($cnt >0) {
                    $remove_sessions[] = $record->sessionid;
                } else {
                    $current_session = $record;
                }
                $cnt++;
                // prepare removal for all ip addresses belonging to this host
                $exec_commands[] = "/sbin/ipfw table ". $ipfw_tables["in"] ." delete ". $record->ip;
                $exec_commands[] = "/sbin/ipfw table ". $ipfw_tables["out"] ." delete ". $record->ip;
                // TODO: if for some strange reason there is more than one session, we are failing to drop the pipes
                $exec_commands[] = "/usr/sbin/arp -d ".trim($record->ip); // drop static arp entry (prevent MAC change)
            }
            if (sizeof($remove_sessions)) {
                $db->removeSession($remove_sessions);
            }

            // collect pipe numbers for  dummynet
            $pipeno_in = $current_session->pipeno_in;
            $pipeno_out = $current_session->pipeno_out;

            $db->updateSession($current_session->sessionid, array("ip"=>$clientip, "mac"=>$clientmac));

            // preserve session for response
            $sessionid = $current_session->sessionid;
        } else {
            // new session, allocate new dummynet pipes and generate a unique id
            $pipeno_in = $this->newIPFWpipeno();
            $pipeno_out = $this->newIPFWpipeno();

            // construct session data
            $session_data=array();
            $session_data["ip"]=$clientip;
            $session_data["mac"]=$clientmac;
            $session_data["pipeno_in"] = $pipeno_in;
            $session_data["pipeno_out"] = $pipeno_out;
            $session_data["username"]=\SQLite3::escapeString($username);
            $session_data["bpassword"] = base64_encode($password);
            $session_data["session_timeout"] = $session_timeout;
            $session_data["idle_timeout"] = $idle_timeout;
            $session_data["session_terminate_time"] = $session_terminate_time;
            $session_data["interim_interval"] = $interim_interval;
            $session_data["radiusctx"] = $radiusctx;
            $session_data["allow_time"] = time(); // allow time is actual starting time of this session
            $sessionid = uniqid() ;

            $db->insertSession($sessionid, $session_data);

        }

        // add commands for access tables, and execute all collected
        $exec_commands[] = "/sbin/ipfw table ". $ipfw_tables["in"] ." add ". $clientip . " ".$pipeno_in;
        $exec_commands[] = "/sbin/ipfw table ". $ipfw_tables["out"] ." add ". $clientip . " ".$pipeno_out;
        $this->shell->exec($exec_commands, false, false);

        // lock the user/ip to it's MAC address using arp
        $arp->setStatic($clientip, $clientmac);

        // add accounting rule
        $this->addAccounting($zoneid, $clientip);

        // set bandwidth restrictions
        $this->resetBandwidth($pipeno_in, $bw_up);
        $this->resetBandwidth($pipeno_in, $bw_down);

        // log
        $this->logportalauth($cpzonename, $username, $clientmac, $clientip, $status = "LOGIN");

        // cleanup
        unset($db);

        return $sessionid;
    }

    /**
     * send message to syslog
     * @param string $cpzonename
     * @param string $user
     * @param string $mac
     * @param string $ip
     * @param string $status
     * @param string $message
     */
    private function logportalauth($cpzonename, $user, $mac, $ip, $status, $message = "")
    {
        $message = trim($message);
        $message = "Zone : {$cpzonename} {$status}: {$user}, {$mac}, {$ip}, {$message}";

        $logger = new Syslog("logportalauth", array(
            'option' => LOG_PID,
            'facility' => LOG_LOCAL4
        ));
        $logger->info($message);
    }

    /**
     * flush zone (null flushes all zones)
     * @param string|null $zone zone name or id
     */
    public function flush($zone = null)
    {
        if ($zone == null) {
            $shell = new Core\Shell();
            $shell->exec("/sbin/ipfw -f table all flush");
        } else {
            // find zoneid for this named zone
            if (preg_match("/^[0-9]{1,2}$/", trim($zone))) {
                $zoneid = $zone;
            } else {
                $zoneid = -1;
                foreach ($this->config->object()->captiveportal->children() as $zonenm => $zoneobj) {
                    if ($zonenm == $zone) {
                        $zoneid = $zoneobj->zoneid;
                    }
                }
            }

            if ($zoneid != -1) {
                $exec_commands= array(
                    "/sbin/ipfw -f table ".$this->rules->getAuthUsersTables($zoneid)["in"]." flush",
                    "/sbin/ipfw -f table ".$this->rules->getAuthUsersTables($zoneid)["out"]." flush",
                    "/sbin/ipfw -f table ".$this->rules->getAuthIPTables($zoneid)["in"]." flush",
                    "/sbin/ipfw -f table ".$this->rules->getAuthIPTables($zoneid)["out"]." flush",
                    "/sbin/ipfw -f table ".$this->rules->getAuthMACTables($zoneid)["in"]." flush",
                    "/sbin/ipfw -f table ".$this->rules->getAuthMACTables($zoneid)["out"]." flush",
                    "/sbin/ipfw delete set ".$zoneid,
                );
                $this->shell->exec($exec_commands, false, false);
            }
        }
    }

    /**
     * cleanup portal sessions
     * @param $cpzone|null zone name
     */
    public function portalCleanupSessions($cpzone = null)
    {
        $acc_list = $this->listAccounting();
        foreach ($this->config->object()->captiveportal->children() as $cpzonename => $zoneobj) {
            if ($cpzone == null || $cpzone == $cpzonename) {
                $db = new DB($cpzonename);

                $clients = $db->listClients(array(), null, null);

                foreach ($clients as $client) {
                    $idle_time = 0;
                    if (array_key_exists($client->ip, $acc_list)) {
                        $idle_time = $acc_list[$client->ip];
                    }

                    // if session timeout is reached, disconnect
                    if (is_numeric($client->session_timeout) && $client->session_timeout > 0) {
                        if (((time() - $client->allow_time) ) > $client->session_timeout) {
                            $this->disconnect($cpzonename, $client->sessionid);
                            $this->logportalauth(
                                $cpzonename,
                                $client->username,
                                $client->mac,
                                $client->ip,
                                $status = "SESSION TIMEOUT"
                            );
                            continue;
                        }
                    }

                    // disconnect session if idle timeout is reached
                    if (is_numeric($client->idle_timeout) && $client->idle_timeout > 0  && $idle_time > 0) {
                        if ($idle_time > $client->idle_timeout) {
                            $this->disconnect($cpzonename, $client->sessionid);
                            $this->logportalauth(
                                $cpzonename,
                                $client->username,
                                $client->mac,
                                $client->ip,
                                $status = "IDLE TIMEOUT"
                            );
                            continue;
                        }
                    }

                    // disconnect on session terminate time
                    if (is_numeric($client->session_terminate_time) &&
                        $client->session_terminate_time > 0 &&
                        $client->session_terminate_time < time()) {
                        $this->disconnect($cpzonename, $client->sessionid);
                        $this->logportalauth(
                            $cpzonename,
                            $client->username,
                            $client->mac,
                            $client->ip,
                            $status = "TERMINATE TIME REACHED"
                        );
                        continue;
                    }
                }

                unset($db);
            }
        }

        unset ($acc_list);

    }

    /**
     * list (ipfw) accounting information
     * @param string|null $ipaddr  ip address
     * @return array (key = hosts ip)
     */
    public function listAccounting($ipaddr = null)
    {
        $filter_cmd = "";
        $result = array();
        $shell_output = array();
        if ($ipaddr != null) {
            $filter_cmd =" | /usr/bin/grep ' " . $ipaddr ." '" ;
        }

        if ($this->shell->exec("/sbin/ipfw -aT list ".$filter_cmd, false, false, $shell_output) == 0) {
            foreach ($shell_output as $line) {
                if (strpos($line, ' count ip from') !== false) {
                    $parts = preg_split('/\s+/', $line);
                    if (count($parts) > 8 && $parts[7] != 'any' and strlen($parts[7]) > 5) {
                        $result[$parts[7]] = array(
                            "rulenum" => $parts[0],
                            "last_accessed" => (int)$parts[3],
                            "idle_time" => time() - (int)$parts[3],
                            "out_packets" => (int)$parts[1],
                            "in_packets" => (int)$parts[2]
                        );
                    }
                }
            }
        }

        return $result;

    }

    /**
     * disconnect a session or a list of sessions depending on the parameter
     * @param string $cpzonename zone name or id
     * @param string $sessionid session id
     */
    public function disconnect($cpzonename, $sessionid)
    {
        if (is_array($sessionid)) {
            foreach ($sessionid as $sessid) {
                $this->disconnectSession($cpzonename, $sessid);
            }
        } else {
            $this->disconnectSession($cpzonename, $sessionid);
        }
    }

    /**
     * @param string $cpzonename zone name
     * @param string $sessionid session id
     * @return boolean false for invalid request
     */
    private function disconnectSession($cpzonename, $sessionid)
    {
        $zoneid = -1;
        foreach ($this->config->object()->captiveportal->children() as $zone => $zoneobj) {
            if ($zone == $cpzonename) {
                $zoneid = $zoneobj->zoneid;
            }
        }

        if ($zoneid == -1) {
            // not a valid zone
            return false;
        }

        $db = new DB($cpzonename);
        $db_clients = $db->listClients(array("sessionid"=>$sessionid));

        $ipfw_tables = $this->rules->getAuthUsersTables($zoneid);
        if (sizeof($db_clients) > 0) {
            if ($db_clients[0]->ip != null) {
                // only handle disconnect if we can find a client in our database
                $exec_commands[] = "/sbin/ipfw table " . $ipfw_tables["in"] . " delete " . $db_clients[0]->ip;
                $exec_commands[] = "/sbin/ipfw table " . $ipfw_tables["out"] . " delete " . $db_clients[0]->ip;
                $this->shell->exec($exec_commands, false, false);
                // TODO: cleanup dummynet pipes $db_clients[0]->pipeno_in/out
                // TODO: log removal
                // ( was : captiveportal_logportalauth($cpentry[4], $cpentry[3], $cpentry[2], "DISCONNECT");)
            }
            $db->removeSession($sessionid);
        }
        return true;
    }
}
