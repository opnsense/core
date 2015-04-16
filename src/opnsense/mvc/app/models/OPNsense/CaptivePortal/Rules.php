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

use OPNsense\Core;

/**
 * Class Rules provides ipfw handling
 * @package CaptivePortal
 */
class Rules
{
    /**
     * config handle
     * @var Core\Config
     */
    private $config = null;


    /**
     * generated ruleset
     * @var array
     */
    private $rules = [] ;

    /**
     *
     */
    public function __construct()
    {
        // Request handle to configuration
        $this->config = Core\Config::getInstance();
    }


    /**
     * get ipfw tables for authenticated users ( in/out )
     * @param int $zoneid zoneid (number)
     * @return array
     */
    public function getAuthUsersTables($zoneid)
    {
        return array("in"=>(6*($zoneid-1) )+1,"out"=>(6*($zoneid-1) )+2);
    }

    /**
     * get ipfw tables for authenticated hosts ( in/out )
     * @param int $zoneid zoneid (number)
     * @return array
     */
    public function getAuthIPTables($zoneid)
    {
        return array("in"=>(6*($zoneid-1) )+3,"out"=>(6*($zoneid-1) )+4);
    }

    /**
     * get ipfw tables used for authenticated physical addresses
     * @param int $zoneid zoneid (number)
     * @return array
     */
    public function getAuthMACTables($zoneid)
    {
        return array("in"=>(6*($zoneid-1) )+5,"out"=>(6*($zoneid-1) )+6);
    }


    /**
     * default rules
     * rule number range 1..1000
     */
    private function generateDefaultRules()
    {
        // define general purpose rules, rule number 1 .... 1000
        $this->rules[] = "#======================================================================================";
        $this->rules[] = "# flush ruleset ";
        $this->rules[] = "#======================================================================================";
        $this->rules[] = "flush" ;

        $this->rules[] = "#======================================================================================";
        $this->rules[] = "# general purpose rules 1...1000 ";
        $this->rules[] = "#======================================================================================";
        $this->rules[] = "add 100 allow pfsync from any to any";
        $this->rules[] = "add 110 allow carp from any to any";
        $this->rules[] = "# layer 2: pass ARP";
        $this->rules[] = "add 120 pass layer2 mac-type arp,rarp";
        $this->rules[] = "# OPNsense requires for WPA";
        $this->rules[] = "add 130 pass layer2 mac-type 0x888e,0x88c7";
        $this->rules[] = "# PPP Over Ethernet Session Stage/Discovery Stage";
        $this->rules[] = "add 140 pass layer2 mac-type 0x8863,0x8864";
        $this->rules[] = "# layer 2: block anything else non-IP(v4/v6)";
        $this->rules[] = "add 150 deny layer2 not mac-type ip,ipv6";

    }

    /**
     * Always allow traffic to our own host ( all static addresses from configuration )
     * rule number range 1001..1999
     */
    private function generateThisHostRules()
    {
        // search all static / non wan addresses and add rules to $this->rules
        $rulenum = 1001 ;
        $this->rules[] = "#======================================================================================";
        $this->rules[] = "# Allow traffic to this hosts static ip's ( 1001..1999 ) ";
        $this->rules[] = "#======================================================================================";
        foreach ($this->config->object()->interfaces->children() as $interface => $content) {
            if ($interface != "wan" && $content->ipaddr != "dhcp" && trim($content->ipaddr) != "") {
                // only keep state of dns traffic to prevent dns resolver failures
                $this->rules[] = "add ".$rulenum++." allow udp from any to ".
                    $content->ipaddr." dst-port 53 keep-state";
                $this->rules[] = "add ".$rulenum++." allow ip from any to { 255.255.255.255 or ".
                    $content->ipaddr." } in";
                $this->rules[] = "add ".$rulenum++." allow ip from { 255.255.255.255 or ".
                    $content->ipaddr." } to any out";
                $this->rules[] = "add ".$rulenum++." allow icmp from { 255.255.255.255 or ".
                    $content->ipaddr." } to any out icmptypes 0";
                $this->rules[] = "add ".$rulenum++." allow icmp from any to { 255.255.255.255 or ".
                    $content->ipaddr." } in icmptypes 8";
            }
        }
    }


    /**
     * generate zone rules, 4 ipfw tables per zone ( in/out, by host or address )
     * The tables are calculcated by zoneid using the getAuthxxxTables methods :
     *  1. authenticated users in
     *  2. authenticated users out
     *  3. allowed ip's in
     *  4. allowed ip's out
     *  5. allowed mac addresses in  ( table contains corresponding ip's )
     *  6. allowed mac addresses out  ( table contains corresponding ip's )
     *
     * A pipe to dummynet is automatically created for every stream
     *
     * Every zone receives it's own ruleset range of max 998 rules, defined by a starting position of 10.000
     * ( for example: zone 2 starts @  12000  )
     *
     * rule number ranges 3001..3999,  10000...50000
     */
    private function generateZones()
    {
        foreach ($this->config->object()->captiveportal->children() as $cpzonename => $zone) {
            if (isset($zone->enable)) {
                // search interface
                $interface = $zone->interface->xpath("//" . $zone->interface);

                // allocate tables for captive portal
                $this->rules[] = "#===================================================================================";
                $this->rules[] = "# zone " . $cpzonename . " (" . $zone->zoneid . ") configuration";
                $this->rules[] = "#===================================================================================";

                if (count($interface) > 0) {
                    $interface = $interface[0];
                    // authenticated users ( table 1 + 2 )
                    $this->rules[] = "add " . (3000 + ($zone->zoneid * 10) + 1) . " skipto " .
                        ((($zone->zoneid * 1000) + 10000) + 1) . " ip from table(" .
                        $this->getAuthUsersTables($zone->zoneid)["in"] . ") to any via " . $interface->if;
                    $this->rules[] = "add " . (3000 + ($zone->zoneid * 10) + 2) . " skipto " .
                        ((($zone->zoneid * 1000) + 10000) + 1) . " ip from any to table(" .
                        $this->getAuthUsersTables($zone->zoneid)["in"] . ") via " . $interface->if;

                    // authenticated hosts ( table 3 + 4 )
                    $this->rules[] = "add " . (3000 + ($zone->zoneid * 10) + 3) . " skipto " .
                        ((($zone->zoneid * 1000) + 10000) + 1) . " ip from table(" .
                        $this->getAuthIPTables($zone->zoneid)["in"] . ") to any via " . $interface->if;
                    $this->rules[] = "add " . (3000 + ($zone->zoneid * 10) + 4) . " skipto " .
                        ((($zone->zoneid * 1000) + 10000) + 1) . " ip from any to table(" .
                        $this->getAuthIPTables($zone->zoneid)["in"] . ") via " . $interface->if;

                    // authenticated mac addresses ( table 5 + 6 )
                    $this->rules[] = "add " . (3000 + ($zone->zoneid * 10) + 5) . " skipto " .
                        ((($zone->zoneid * 1000) + 10000) + 1) . " ip from table(" .
                        $this->getAuthMACTables($zone->zoneid)["in"] . ") to any via " . $interface->if;
                    $this->rules[] = "add " . (3000 + ($zone->zoneid * 10) + 6) . " skipto " .
                        ((($zone->zoneid * 1000) + 10000) + 1) . " ip from any to table(" .
                        $this->getAuthMACTables($zone->zoneid)["in"] . ") via " . $interface->if;

                    //                TODO: solve dummynet kernel issue on outgoing traffic
                    //                // dummynet 1,2
                    //                $this->rules[] = "add ".((($zone->zoneid*1000)+10000)+1) .
                    // " pipe tablearg ip from table(".($table_id+1).") to any in via ".$interface->if ;
                    //                $this->rules[] = "add ".((($zone->zoneid*1000)+10000)+2) .
                    //" pipe tablearg ip from any to table(".($table_id+2).") out via ".$interface->if ;
                    //
                    //                // dummynet 3,4
                    //                $this->rules[] = "add ".((($zone->zoneid*1000)+10000)+3) .
                    //" pipe tablearg ip from table(".($table_id+3).") to any in via ".$interface->if ;
                    //                $this->rules[] = "add ".((($zone->zoneid*1000)+10000)+4) .
                    //" pipe tablearg ip from table(".($table_id+3).") to any out via ".$interface->if ;
                    //                $this->rules[] = "add ".((($zone->zoneid*1000)+10000)+5) .
                    //" pipe tablearg ip from any to table(".($table_id+4).") in via ".$interface->if ;
                    //                $this->rules[] = "add ".((($zone->zoneid*1000)+10000)+6) .
                    //" pipe tablearg ip from any to table(".($table_id+4).") out via ".$interface->if ;

                    //                // dummynet 5,6
                    //                $this->rules[] = "add ".((($zone->zoneid*1000)+10000)+7) .
                    //" pipe tablearg ip from table(".($table_id+5).") to any in via ".$interface->if ;
                    //                $this->rules[] = "add ".((($zone->zoneid*1000)+10000)+8) .
                    //" pipe tablearg ip from table(".($table_id+5).") to any out via ".$interface->if ;
                    //                $this->rules[] = "add ".((($zone->zoneid*1000)+10000)+9) .
                    //" pipe tablearg ip from any to table(".($table_id+6).") in via ".$interface->if ;
                    //                $this->rules[] = "add ".((($zone->zoneid*1000)+10000)+10) .
                    //" pipe tablearg ip from any to table(".($table_id+6).") out via ".$interface->if ;

                    // statistics for this zone, placeholder to jump to
                    $this->rules[] = "add " . ((($zone->zoneid * 1000) + 10000) + 1) .
                        " count ip from any to any via " . $interface->if;

                    // jump to accounting section
                    $this->rules[] = "add " . ((($zone->zoneid * 1000) + 10000) + 998) .
                        " skipto 30000 all from any to any via " . $interface->if;
                    $this->rules[] = "add " . ((($zone->zoneid * 1000) + 10000) + 999) .
                        " deny all from any to any not via " . $interface->if;

                }
            }
        }

    }

    /**
     * Forward all non authenticated traffic from captive portal zones
     * rule number range 5001..5999
     */
    private function generateReflectRules()
    {
        $forward_port = 8000 ;
        $this->rules[] = "#======================================================================================";
        $this->rules[] = "# redirect non-authenticated clients to captive portal @ local port " .
            $forward_port." + zoneid  ";
        $this->rules[] = "#======================================================================================";
        foreach ($this->config->object()->captiveportal->children() as $cpzonename => $zone) {
            if (isset($zone->enable)) {
                // search interface
                $interface = $zone->interface->xpath("//".$zone->interface);
                if (count($interface) > 0) {
                    $interface = $interface[0]  ;
                    if ($interface->ipaddr != null) {
                        // add forward rule to this zone's http instance @ $forward_port + $zone->zoneid
                        $this->rules[] ="add ".(5000+$zone->zoneid)." fwd 127.0.0.1,".($forward_port + $zone->zoneid ).
                            " tcp from any to any dst-port 80 in via ".$interface->if;
                        $this->rules[] = "add ".(5000+$zone->zoneid)." allow ip from any to any dst-port ".
                            "80 via ".$interface->if;
                    }

                }
            }
        }
    }

    /**
     * for accounting statistics we setup a separate section in our config
     * rule number range 30000..65500
     */
    private function generateAccountingSection()
    {
        $this->rules[] = "#======================================================================================";
        $this->rules[] = "# setup accounting section, first rule is counting all CP traffic ";
        $this->rules[] = "# rule 65500 unlocks the traffic already authorized from a CP zone";
        $this->rules[] = "#======================================================================================";
        $this->rules[] = "add 30000 set 0 count ip from any to any  ";
        $this->rules[] = "add 65500 pass ip from any to any  ";
    }

    /**
     * generate closure tag, block all traffic coming from captiveportal interfaces
     * rule number range 6001..6999
     */
    private function generateClosure()
    {
        $cpinterfaces = [];
        # find all cp interfaces
        foreach ($this->config->object()->captiveportal->children() as $cpzonename => $zone) {
            if (isset($zone->enable)) {
                // search interface
                $interface = $zone->interface->xpath("//" . $zone->interface);
                if (count($interface) > 0) {
                    $interface = $interface[0];
                    if ($interface->if != null) {
                        // check if interface exists before appending it.
                        $cpinterfaces[$interface->if->__toString()] = 1;
                    }
                }
            }

        }

        // generate accept rules for every interface not in captive portal
        $ruleid = 6001 ;
        $this->rules[] = "#======================================================================================";
        $this->rules[] = "# accept traffic from all interfaces not used by captive portal (5001..5999) ";
        $this->rules[] = "#======================================================================================";
        foreach ($this->config->object()->interfaces->children() as $interface => $content) {
            if (!isset($cpinterfaces[$content->if->__toString()])) {
                $this->rules[] = "add ".($ruleid++)." allow all from any to any via ".$content->if ;
            }
        }


        $this->rules[] = "# let the responses from the captive portal web server back out";
        $this->rules[] = "add ".($ruleid++)." pass tcp from any to any out";

        // block every thing else (not mentioned before)
        $this->rules[] = "# block everything else";
        $this->rules[] = "add ".($ruleid)." skipto 65534 all from any to any";
        $this->rules[] = "add 65534 deny all from any to any";
    }


    /**
     * load ruleset
     * @param string $filename target filename
     */
    public function generate($filename)
    {
        /*
         * reset rules
         */
        $this->rules = [] ;

        /*
         * generate new
         */
        $this->generateDefaultRules();
        $this->generateThisHostRules();
        $this->generateZones();
        $this->generateReflectRules();
        $this->generateAccountingSection();
        $this->generateClosure();

        // ruleset array -> text
        $ruleset_txt = "";
        $prev_rule = "#";
        foreach ($this->rules as $rule) {
            if (trim($rule)[0] == '#' && trim($prev_rule)[0] != "#") {
                $ruleset_txt .= "\n";
            }
            $ruleset_txt .= $rule."\n";
            $prev_rule = $rule ;
        }

        // write to file
        file_put_contents($filename, $ruleset_txt);
    }
}
