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

require_once("plugins.inc"); // can be removed, see checkPredefinedTargets()

use OPNsense\Core\Config;
use OPNsense\Core\Backend;
use OPNsense\Base\BaseModel;
use OPNsense\Base\ModelException;
use Phalcon\Filter;


// TODO: remote log all (!* -> *.* @server)
// TODO: bind_address select in UI
// TODO: sanitize socket path, see setLocalSocket()
// TODO: remove test staff

/**
 * Class Syslog
 * @package OPNsense\Syslog
 */
class Syslog extends BaseModel
{
    private static $LOGS_DIRECTORY = "/var/log";

    private $Modified = false;
    private $BatchMode = false;

    private function getPredefinedTargets()
    {
        $systemLog = self::$LOGS_DIRECTORY.'/system.log';

        return array(
        array('program' => 'dhcpd,dhcrelay,dhclient,dhcp6c',                      'filter' => '*.*',  'type' => 'file', 'target' => self::$LOGS_DIRECTORY.'/dhcpd.log',   'category' => 'dhcpd'),
        array('program' => 'filterlog',                                           'filter' => '*.*',  'type' => 'file', 'target' => self::$LOGS_DIRECTORY.'/filter.log',  'category' => 'filter'),
        array('program' => 'apinger',                                             'filter' => '*.*',  'type' => 'file', 'target' => self::$LOGS_DIRECTORY.'/gateways.log','category' => 'gateways'),
        array('program' => 'ntp,ntpd,ntpdate',                                    'filter' => '*.*',  'type' => 'file', 'target' => self::$LOGS_DIRECTORY.'/ntpd.log',    'category' => 'ntpd'),
        array('program' => 'captiveportal',                                       'filter' => '*.*',  'type' => 'file', 'target' => self::$LOGS_DIRECTORY.'/portalauth.log','category' => 'portalauth'),
        array('program' => 'ppp',                                                 'filter' => '*.*',  'type' => 'file', 'target' => self::$LOGS_DIRECTORY.'/ppps.log',    'category' => null),
        array('program' => 'relayd',                                              'filter' => '*.*',  'type' => 'file', 'target' => self::$LOGS_DIRECTORY.'/relayd.log',  'category' => 'relayd'),
        array('program' => 'dnsmasq,filterdns,unbound',                           'filter' => '*.*',  'type' => 'file', 'target' => self::$LOGS_DIRECTORY.'/resolver.log','category' => 'resolver'),
        array('program' => 'radvd,routed,rtsold,olsrd,zebra,ospfd,bgpd,miniupnpd','filter' => '*.*',  'type' => 'file', 'target' => self::$LOGS_DIRECTORY.'/routing.log', 'category' => null),
        array('program' => 'hostapd',                                             'filter' => '*.*',  'type' => 'file', 'target' => self::$LOGS_DIRECTORY.'/wireless.log','category' => 'wireless'),

        array('program' => null,  'filter' => 'local3.*',                             'type' => 'file',   'target' => self::$LOGS_DIRECTORY.'/vpn.log',   'category' => 'vpn'),
        array('program' => null,  'filter' => 'local7.*',                             'type' => 'file',   'target' => self::$LOGS_DIRECTORY.'/dhcpd.log', 'category' => 'dhcpd'),
        array('program' => null,  'filter' => '*.notice;kern.debug;lpr.info;mail.crit;daemon.none', 'type' => 'file', 'target' => $systemLog,             'category' => 'system'),
        array('program' => null,  'filter' => 'news.err;local0.none;local3.none;local4.none', 'type' => 'file', 'target' => $systemLog,                   'category' => 'system'),
        array('program' => null,  'filter' => 'local7.none',                          'type' => 'file',   'target' => $systemLog,                         'category' => null),
        array('program' => null,  'filter' => 'security.*',                           'type' => 'file',   'target' => $systemLog,                         'category' => 'system'),
        array('program' => null,  'filter' => 'auth.info;authpriv.info;daemon.info',  'type' => 'file',   'target' => $systemLog,                         'category' => 'system'),
        array('program' => null,  'filter' => 'auth.info;authpriv.info;user.*',       'type' => 'pipe',   'target' => 'exec /usr/local/sbin/sshlockout_pf 15','category' => null),
        array('program' => null,  'filter' => '*.emerg',                              'type' => 'all',    'target' => '*',                                'category' => 'system'),
        );
    }

    // WARNING: in legacy code was selector "local7.*" for remote logging only , mapped to portalauth category ???

    /*************************************************************************************************************
     * Public API
     *************************************************************************************************************/

    /**
     * Set Syslog target.
     * @param $source program name, null if no program name
     * @param $filter comma-separated list of selectors facility.level (without spaces)
     * @param $type type of action (file, pipe, remote, all)
     * @param $target action target
     * @param $category log category mapping, null if no category
     * @throws \ModelException
     */
    public function setTarget($source, $filter, $type, $target, $category)
    {
        $source = str_replace(' ', '', $source);
        $filter = str_replace(' ', '', $filter);
        $type = trim($type);
        $target = trim($target);
        $category = trim($category);

        $this->setSource($source);

        // we would not add category if it not exists 

        foreach($this->LogTargets->Target->__items as $uuid => $item) 
        {
            if($item->Source->__toString() == $source
            && $item->Filter->__toString() == $filter
            && $item->ActionType->__toString() == $type
            && $item->Target->__toString() == $target
            && $item->Category->__toString() == $category)
                return;
        }

        $item = $this->LogTargets->Target->add();
        $item->Source = $source;
        $item->Filter = $filter;
        $item->ActionType = $type;
        $item->Target = $target;
        $item->Category = $category;

        $this->Modified = true;
        $this->saveIfModified();
    }

    /**
     * Remove Syslog target.
     * @param $source program name, null if no program name
     * @param $filter comma-separated list of selectors facility.level (without spaces)
     * @param $type type of action (file, pipe, remote, all)
     * @param $target action target
     * @throws \ModelException
     */
    public function delTarget($source, $filter, $type, $target)
    {
        $source = str_replace(' ', '', $source);
        $filter = str_replace(' ', '', $filter);
        $type = trim($type);
        $target = trim($target);

        foreach($this->LogTargets->Target->__items as $uuid => $item) 
        {
            if($item->Source->__toString() == $source
            && $item->Filter->__toString() == $filter
            && $item->ActionType->__toString() == $type
            && $item->Target->__toString() == $target)
            {
                $this->LogTargets->Target->del($uuid);
                $this->Modified = true;
                $this->saveIfModified();
            }
        }
    }

    /**
     * Add or update Syslog category. For mapping in GUI.
     * @param $name category name
     * @param $description category description
     * @throws \ModelException
     */
    public function setCategory($name, $description)
    {
        $name = trim($name);
        $description = trim($description);

        foreach($this->LogCategories->Category->__items as $uuid => $category) 
        {
            if($category->Name->__toString() == $name)
            {
                if($category->Description->__toString() == $description)
                    return;

                $category->Description = $description;
                $this->Modified = true;
                $this->saveIfModified();
                return;
            }
        }

        $category = $this->LogCategories->Category->add();
        $category->Name = $name;
        $category->Description = $description;
        $category->LogRemote = $logRemote;
        $this->Modified = true;
        $this->saveIfModified();
    }

    /**
     * Add or update Syslog category. For mapping in GUI.
     * @param $name category name
     * @param $description category description
     * @throws \ModelException
     */
    public function setLocalSocket($path)
    {
        $path = trim($path);

        foreach($this->LocalSockets->Socket->__items as $uuid => $socket) 
        {
            if($socket->Path->__toString() == $path)
                return;
        }

        $socket = $this->LocalSockets->Socket->add();
        $socket->Path = $path;
        $this->Modified = true;
        $this->saveIfModified();
    }

    /**
     * Delete all logfiles for given name
     * @param $name log name without path and suffix ( for example 'system' to delete /var/log/system.log* )
     */
    public function clearLog($name)
    {
        $filter = new Filter();
        $filter->add('logfilename', function($value){ return preg_replace("/[^0-9,a-z,A-Z,_]/", "", $value);});

        $name = $filter->sanitize($name, 'logfilename');
        $name = self::$LOGS_DIRECTORY . "/$name.log";

        $backend = new Backend();
        $status = $backend->configdRun("syslog clearlog {$name}");
        $backend->configdRun("syslog start");

        return array("status" => $status);
    }

    /**
     * Reset all logfiles
     */
    public function resetLogFiles()
    {
        $backend = new Backend();
        $result = array();
        $deleted = array();
        foreach($this->LogTargets->Target->__items as $uuid => $target) {
            if($target->ActionType == 'file') {
                $pathname = $target->Target->__toString();
                if(!in_array($pathname, $deleted)) {
                    $status = $backend->configdRun("syslog clearlog {$pathname}");
                    $result[] = array('name' => $pathname, 'status' => $status);
                    $deleted[] = $pathname;
                }
            }
        }

        $backend->configdRun("syslog start");

        return array("status" => $result);
    }

    /**
     * get full logfile path
     * @param $logname name of log
     */
    public function getLogFileName($logname)
    {
        foreach($this->LogTargets->Target->__items as $uuid => $target)
            if(basename($target->Target->__toString(), '.log') == $logname)
                return $target->Target->__toString();

        return '';
    }

    /*************************************************************************************************************
     * Protected Area
     *************************************************************************************************************/

    protected function init()
    {
        $this->BatchMode = true;
        $this->checkPredefinedCategories();
        $this->checkPredefinedTargets();
        $this->BatchMode = false;
        
        $this->saveIfModified();
    }

    private function checkPredefinedCategories()
    {
        $this->setCategory('system',    gettext('System events'));
        $this->setCategory('dhcpd',     gettext('DHCP service events'));
        $this->setCategory('filter',    gettext('Firewall events'));
        $this->setCategory('gateways',  gettext('Gateway Monitor events'));
        $this->setCategory('ntpd',      gettext('Internet time events'));
        $this->setCategory('portalauth',gettext('Portal Auth events'));
        $this->setCategory('relayd',    gettext('Server Load Balancer events'));
        $this->setCategory('resolver',  gettext('Domain name resolver events'));
        $this->setCategory('wireless',  gettext('Wireless events'));
        $this->setCategory('vpn',       gettext('VPN (PPTP, IPsec, OpenVPN) events'));
    }

    private function checkPredefinedTargets()
    {
        foreach($this->getPredefinedTargets() as $target)
        {
            $this->setTarget($target['program'], $target['filter'], $target['type'], $target['target'], $target['category']);
        }

        // NOTE: in more convivient way, plugins can set targets in setup script by Syslog::setTarget() call

        // scan plugins
        $plugins_data = plugins_syslog();
        foreach($plugins_data as $name => $params)
        {
            $program = join(",", $params['facility']);
            $target =  self::$LOGS_DIRECTORY."/".$name.".log";
            $this->setTarget($program, '*.*', 'file', $target, null);
        }
    }

    /**
    * Set Syslog source.
    * @param $program category name
    * @throws \ModelException
    */
    public function setSource($program)
    {
        $program = str_replace(' ', '', $program);

        if($program == '')
            return;

        foreach($this->LogSources->Source->__items as $uuid => $source) 
        {
            if($source->Program->__toString() == $program)
                return;
        }

        $source = $this->LogSources->Source->add();
        $source->Program = $program;
        $this->Modified = true;
        $this->saveIfModified();
        return;
    }

    private function saveIfModified()
    {
       
        if($this->BatchMode === true)
            return;
        
        if($this->Modified === false)
            return;

        $valMsgs = $this->performValidation();
        $errorMsg = "Validation error: ";
        foreach ($valMsgs as $field => $msg) {
            $errorMsg .= $msg->getField() . '(' . $msg->getMessage() .'); ';
        }
        if($valMsgs->count() > 0)
            throw new ModelException($errorMsg);

        $this->serializeToConfig();
        Config::getInstance()->save();
        $this->Modified = false;
    }

    public function test()
    {
        $sources = array();
        foreach($this->LogSources->Source->__items as $uuid => $item)
            $sources[] = array(
                    'program' => $item->Program->__toString(),
                    );

        foreach($this->LogCategories->Category->__items as $uuid => $item)
            $categories[] = array(
                    'name' => $item->Name->__toString(),
                    'description' => $item->Description->__toString(),
                    'remote' => $item->LogRemote->__toString(),
                    );

        $selectors = array();
        return array('sources' => $sources, 'selectors' => $selectors, 'categories' => $categories, 'filename' => $this->getLogFileName("filter"));
    }
}
