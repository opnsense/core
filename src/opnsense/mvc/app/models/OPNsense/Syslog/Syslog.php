<?php
/**
 *    Copyright (C) 2016 Deciso B.V.
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
namespace OPNsense\Syslog;

use OPNsense\Base\BaseModel;

require_once("plugins.inc");

/**
 * Class Syslog
 * @package OPNsense\Syslog
 */
class Syslog extends BaseModel
{
    private static $LOGS_DIRECTORY = "/var/log";
    private static $CORE_SYSTEM_SOURCE = 'CORE_SYSTEM_SOURCE';

    private function getPredefinedSources()
    {
        return array(
        // programs,comma,separated         =>      (logfilename,           send to remote if remote logging is on, description)
        'dhcpd,dhcrelay,dhclient,dhcp6c'    => array('file' => 'dhcpd',     'remote' => '1',    'description' => gettext('DHCP service events')),
        'filterlog'                         => array('file' => 'filter',    'remote' => '1',    'description' => gettext('Firewall events')),
        'apinger'                           => array('file' => 'gateways',  'remote' => '1',    'description' => gettext('Gateway Monitor events')),
        'ntp,ntpd,ntpdate'                  => array('file' => 'ntpd',      'remote' => '0',    'description' => gettext('Internet time events')),
        'captiveportal'                     => array('file' => 'portalauth','remote' => '1',    'description' => gettext('Portal Auth events')),
        'ppp'                               => array('file' => 'ppps',      'remote' => '0',    'description' => gettext('Point-to-Point events')),
        'relayd'                            => array('file' => 'relayd',    'remote' => '1',    'description' => gettext('Server Load Balancer events')),
        'dnsmasq,filterdns,unbound'         => array('file' => 'resolver',  'remote' => '0',    'description' => gettext('Domain name resolver events')),
        'radvd,routed,rtsold,olsrd,zebra,ospfd,bgpd,miniupnpd' => array('file' => 'routing','remote' => '0',  'description' => gettext('Routing events')),
        'hostapd'                           => array('file' => 'wireless',  'remote' => '1',    'description' => gettext('Wireless events')),
        // special handler for holding remote setting:
        self::$CORE_SYSTEM_SOURCE           => array('file' => 'system',    'remote' => '1',    'description' => gettext('System events')),
        );
    }

    private static $PredefinedSystemSelectors = array(
                    array('type' => 'file', 'name' => 'vpn',    'filter'    => 'local3.*'),
                    array('type' => 'file', 'name' => 'dhcpd',  'filter'    => 'local7.*'),
                    array('type' => 'file', 'name' => 'system', 'filter'    => '*.notice;kern.debug;mail.crit;daemon.none;local0.none;local3.none;local4.none;local7.none;security.*;auth.info;authpriv.info;daemon.info'),
                    array('type' => 'pipe', 'name' => 'exec /usr/local/sbin/sshlockout_pf 15', 'filter' => 'auth.info;authpriv.info;user.*'),
                    array('type' => 'all',  'name' => '*',      'filter'    => '*.emerg'),
                    );

    protected function init()
    {
        $this->checkPredefinedSources();
        $this->regenerateSystemSelectors();
        $this->CoreSystemSourceName = self::$CORE_SYSTEM_SOURCE;
        $this->AllPrograms = $this->getAllPrograms();
    }

    private function getAllPrograms()
    {
        $all = array();
        foreach($this->LogSources->Source->__items as $uuid => $source)
            if($source->Program->__toString() != self::$CORE_SYSTEM_SOURCE)
                $all[] = $source->Program->__toString();
        return join(',', $all);
    }

    private function checkPredefinedSources()
    {
        $wasModified = false;

        // look at our current model content
        $programs = array();
        foreach($this->LogSources->Source->__items as $uuid => $source)
            $programs[] = $source->Program->__toString();

        // scan plugins
        $plugins_data = plugins_syslog();
        foreach($plugins_data as $name => $params)
        {
            $program = join(",", $params['facility']);
            if(!in_array($program, $programs))
            {
                $source = $this->LogSources->Source->add();
                $source->Name = $name;
                $source->Program = $program;
                $source->Description = "Plugin $name events";
                $source->RemoteLog = '0';
                $source->Target = self::$LOGS_DIRECTORY . "/" . $name . ".log";
                $wasModified = true;
            }
        }

        // scan core predefined
        foreach($this->getPredefinedSources() as $predefined => $params)
        {
            if(!in_array($predefined, $programs))
            {
                $source = $this->LogSources->Source->add();
                $source->Name = $params['file'];
                $source->Program = $predefined;
                $source->Description = $params['description'];
                $source->RemoteLog = $params['remote'];
                $source->Target = self::$LOGS_DIRECTORY . "/" . $params['file'] . ".log";
                $wasModified = true;
            }
        }

        // TODO: API to add selectors, remove this function, replace by addAction(source, filter, type, target) requests
        // TODO: remove old obsolete
        // TODO: regenerate and restart if modified
        // TODO: rename selector to action
        // TODO: replace sources to categories in GUI
        // TODO: we can add remote actions instead of tracking "Remote" flags
    }

    private function regenerateSystemSelectors()
    {
        // delete all
        foreach($this->SystemSelectors->Selector->__items as $uuid => $selector)
            $this->SystemSelectors->Selector->del($uuid);

        // regenerate all
        foreach(self::$PredefinedSystemSelectors as $predefined)
        {
            $selector = $this->SystemSelectors->Selector->add();
            $selector->Filter       = $predefined['filter'];
            $selector->ActionType   = $predefined['type'];
            $selector->Target       = $predefined['type'] == 'file' ? self::$LOGS_DIRECTORY . "/" . $predefined['name'] . ".log" : $predefined['name'];
        }
    }

    public function showSelectors()
    {
        $selectors = array();
        foreach($this->Selectors->Selector->__items as $uuid => $selector)
            $selectors[] = $selector->LogSource->Program->__toString();

        return $selectors;
    }

    public function test()
    {
        $sources = array();
        foreach($this->LogSources->Source->__items as $uuid => $item)
            $sources[] = array(
                    'program' => $item->Program->__toString(),
                    'description' => $item->Description->__toString(),
                    'remote' => $item->RemoteLog->__toString(),
                    );

        $selectors = array();
        foreach($this->SystemSelectors->Selector->__items as $uuid => $selector)
            $selectors[] = array(
                'filter' => $selector->Filter->__toString(),
                );

        return array('sources' => $sources, 'selectors' => $selectors);
    }
}
