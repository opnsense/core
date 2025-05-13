<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2010 Vinicius Coque <vinicius.coque@bluepex.com>
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

require_once("guiconfig.inc");
require_once("system.inc");

use OPNsense\Core\AppConfig;

$contribDir = (new AppConfig())->application->contribDir;
$serviceproviders_xml = $contribDir . '/mobile-broadband-provider-info/serviceproviders.xml';
$serviceproviders_contents = file_get_contents($serviceproviders_xml);
$serviceproviders = simplexml_load_string($serviceproviders_contents);

function get_country_codes()
{
    global $contribDir;

    $dn_cc = [];

    $iso3166_tab = $contribDir . '/iana/tzdata-iso3166.tab';
    if (file_exists($iso3166_tab)) {
        $dn_cc_file = file($iso3166_tab);
        foreach ($dn_cc_file as $line) {
            if (preg_match('/^([A-Z][A-Z])\t(.*)$/', $line, $matches)) {
                $dn_cc[$matches[1]] = trim($matches[2]);
            }
        }
    }

    return $dn_cc;
}

function get_country_providers($country)
{
    global $serviceproviders;

    foreach($serviceproviders as $sp) {
        if ($sp->attributes()['code'] == strtolower($country)) {
            return $sp;
        }
    }

    return [];
}

function country_list()
{
    global $serviceproviders;

    $country_list = get_country_codes();

    foreach ($serviceproviders as $sp) {
        foreach($country_list as $code => $country) {
            if ($sp->attributes()['code'] == strtolower($code)) {
                  echo $country . ":" . $code . "\n";
            }
        }
    }
}

function providers_list($country)
{
    $serviceproviders = get_country_providers($country);

    foreach($serviceproviders as $sp) {
        echo (string)$sp->name . "\n";
    }
}

function provider_plan_data($country, $provider, $connection)
{
    header("Content-type: application/xml;");
    echo "<?xml version=\"1.0\" ?>\n";
    echo "<connection>\n";
    $serviceproviders = get_country_providers($country);
    $conndata = null;
    foreach($serviceproviders as $sp) {
        if (strtolower((string)$sp->name) == strtolower($provider)) {
            if (strtoupper($connection) == "CDMA") {
                $conndata = $sp->cdma;
            } else {
                foreach ($sp->gsm->apn as $apn) {
                    if ($apn->attributes()['value'] == $connection) {
                        $conndata = $apn;
                    }
                }
            }
            if (!empty($conndata)) {
                echo "<apn>" . $connection . "</apn>\n";
                echo "<username>" . (string)$conndata->username . "</username>\n";
                echo "<password>" . (string)$conndata->password . "</password>\n";
                foreach($conndata->dns as $dns) {
                    echo '<dns>' . $dns . "</dns>\n";
                }
            }
            break;
        }
    }
    echo "</connection>";
}

function provider_plans_list($country, $provider)
{
    $serviceproviders = get_country_providers($country);
    foreach($serviceproviders as $sp) {
        if (strtolower((string)$sp->name) == strtolower($provider)) {
            if (!empty($sp->gsm)) {
                foreach ($sp->gsm->apn as $apn) {
                    $apn_name = !empty((string)$apn->name) ? (string)$apn->name : (string)$apn ;
                    echo trim($apn_name . ":". (string)$apn->attributes()['value']) ."\n";
                }
            }
            if (!empty($sp->cdma)) {
                foreach ($sp->cdma as $apn) {
                    $apn_name = trim(!empty($apn->name) ? (string)$apn->name : (string)$sp->name);
                    echo $apn_name . ":CDMA" ."\n";
                }
            }
        }
    }
}

if (isset($_REQUEST['country']) && !isset($_REQUEST['provider'])) {
    providers_list($_REQUEST['country']);
} elseif (isset($_REQUEST['country']) && isset($_REQUEST['provider'])) {
    if (isset($_REQUEST['plan'])) {
        provider_plan_data($_REQUEST['country'],$_REQUEST['provider'],$_REQUEST['plan']);
    } else {
        provider_plans_list($_REQUEST['country'],$_REQUEST['provider']);
    }
} else {
    country_list();
}
