<?php
/**
 *    Copyright (C) 2016 E.Bevz, Deciso B.V.
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

namespace OPNsense\Syslog\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Backend;
use Opnsense\Core\Config;

class M1_0_0 extends BaseModelMigration
{
	public function run($model)
	{
		parent::run($model);

		// delete clog logfiles
		$backend = new Backend();
		foreach($model->LogTargets->Target->__items as $uuid => $target)
		{
			if($target->ActionType->__toString() == 'file')
				$backend->configdRun("syslog clearlog " . $target->Target->__toString());
		}

		// restart syslogd to generate new plain text logfiles
		$backend->configdRun("syslog stop");
		$backend->configdRun("syslog start");

		// import settings from legacy config section
		$config = Config::getInstance()->toArray();
		$model->Reverse = isset($config['syslog']['reverse']) ? "1" : "0";
		if (!empty($config['syslog']['nentries']))
			$model->NumEntries = $config['syslog']['nentries'];
		$model->DisableLogFiles = isset($config['syslog']['disablelocallogging']) ? "1" : "0";
		$model->LogWebServer = !isset($config['syslog']['nologlighttpd']) ? "1" : "0";
		$model->Firewall->LogDefaultBlock = !isset($config['syslog']['nologdefaultblock']) ? "1" : "0";
		$model->Firewall->LogDefaultPass = !isset($config['syslog']['nologdefaultpass']) ? "1" : "0";
		$model->Firewall->LogBogons = !isset($config['syslog']['nologbogons']) ? "1" : "0";
		$model->Firewall->LogPrivateNets = !isset($config['syslog']['nologprivatenets']) ? "1" : "0";
		$model->Firewall->FilterDescriptions = !empty($config['syslog']['filterdescriptions']) ? "val_" . $config['syslog']['filterdescriptions'] : "val_0";
		$model->Remote->Enable = isset($config['syslog']['enable']) ? "1" : "0";
		$model->Remote->LogAll = isset($config['syslog']['logall']) ? "1" : "0";
		$model->Remote->Proto = !empty($config['syslog']['ipproto']) ? $config['syslog']['ipproto'] : "ipv4";

		// Remote servers
		$servers = array();
		if (!empty($config['syslog']['remoteserver']))
			$servers[] = $config['syslog']['remoteserver'];
		if (!empty($config['syslog']['remoteserver2']))
			$servers[] = $config['syslog']['remoteserver2'];
		if (!empty($config['syslog']['remoteserver3']))
			$servers[] = $config['syslog']['remoteserver3'];
		if (count($servers) > 0)
			$model->Remote->Servers = implode(',',$servers);

		// Bind address
		if(!empty($config['syslog']['sourceip']))
		{
			$model->Remote->SourceIP = $config['syslog']['sourceip'];
	        $bindip = "";
            $sourceip = $config['syslog']['sourceip'];
            $proto = !empty($config['syslog']['ipproto']) ? $config['syslog']['ipproto'] : "ipv4";
            $bindip = chop($backend->configdRun("syslog get_bind_address {$sourceip} {$proto}")); 

            // additional sanity check
            if($bindip !== "" && inet_pton($bindip) === false)
                $bindip = "";

            if($bindip != "")
            	$model->Remote->BindAddress = $bindip;
        }

		// categories
		foreach($model->LogCategories->Category->__items as $uuid => $category)
		{
			if (isset($config['syslog'][$category->Name->__toString()]))
				$category->LogRemote = "1";
		}

		// save and restart
		$model->serializeToConfig();
		Config::getInstance()->save();
		$backend->configdRun("template reload OPNsense.Syslog");
		$backend->configdRun("syslog stop");
		$backend->configdRun("syslog start");
	}
}
