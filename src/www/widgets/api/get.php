<?php
/*
    Copyright (C) 2016 Deciso B.V.
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

/*
  Simple wrapper to retrieve data for widgets using legacy code.
  Returns a json object containing a list of available plugins and for all requested plugins (parameter load) the
  collected data.
*/

header("Last-Modified: " . gmdate( "D, j M Y H:i:s" ) . " GMT" );
header("Expires: " . gmdate( "D, j M Y H:i:s", time() ) . " GMT" );
header("Cache-Control: no-store, no-cache, must-revalidate" );
header("Cache-Control: post-check=0, pre-check=0", FALSE);
header("Pragma: no-cache");

require_once("guiconfig.inc");
// require legacy scripts, so plugins don't have to load them
require_once("system.inc");
require_once("config.inc");
require_once("filter.inc");
require_once("interfaces.inc");

// parse request, load parameter contains all plugins that should be loaded for this request
if (!empty($_REQUEST['load'])) {
    $loadPluginsList = explode(',', $_REQUEST['load']);
} else {
    $loadPluginsList = array();
}

// add metadata
$result['system'] = $g['product_name'];
// available plugins
$result['plugins'] = array();
// collected data
$result['data'] = array();

// probe plugins
foreach (glob(__DIR__."/plugins/*.inc") as $filename) {
    $pluginName = basename($filename, '.inc');
    $result['plugins'][] = $pluginName;
    if (in_array($pluginName, $loadPluginsList)) {
        require $filename;
        $pluginFunctionName = $pluginName."_api";
        if (function_exists($pluginName."_api")) {
            $result['data'][$pluginName] = $pluginFunctionName();
        }
    }
}
// output result
legacy_html_escape_form_data($result);
echo json_encode($result);
