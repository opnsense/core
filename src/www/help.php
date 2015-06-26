<?php
/*
    Copyright (C) 2014-2015 Deciso B.V.
    Copyright (C) 2009 Jim Pingle <jimp@pfsense.org>
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


require_once("guiconfig.inc");

/* Define hash of jumpto url maps */

/* Links to categories could probably be more specific. */
$helppages = array(
	/* Redirect help to specific page instead of default location */
	'license.php' => 'http://opnsense.org/about/legal-notices/',
);

$pagename = "";
/* Check for parameter "page". */
if ($_GET && isset($_GET['page'])) {
	$pagename = $_GET['page'];
}

/* If "page" is not found, check referring URL */
if (empty($pagename)) {
	/* Attempt to parse out filename */
	$uri_split = "";
	preg_match("/\/(.*)\?(.*)/", $_SERVER["HTTP_REFERER"], $uri_split);

	/* If there was no match, there were no parameters, just grab the filename
		Otherwise, use the matched filename from above. */
	if (empty($uri_split[0])) {
		$pagename = ltrim(parse_url($_SERVER["HTTP_REFERER"], PHP_URL_PATH), '/');
	} else {
		$pagename = $uri_split[1];
	}

	/* If the page name is still empty, the user must have requested / (index.php) */
	if (empty($pagename)) {
		$pagename = "index.php";
	}

	/* If the filename is wizard.php, reparse looking for the .xml filename */
	if ($pagename == 'wizard.php') {
		$param_split = explode('&', $uri_split[2]);
		foreach ($param_split as $param) {
			if (substr($param, 0, 4) == "xml=") {
				$xmlfile = explode('=', $param);
				$pagename = $xmlfile[1];
			}
		}
	}
}

/* Using the derived page name, attempt to find in the URL mapping hash */
if (array_key_exists($pagename, $helppages)) {
	$helppage = $helppages[$pagename];
}

/* If we haven't determined a proper page, use a generic help page
	 stating that a given page does not have help yet. */

if (empty($helppage)) {

	$helppage = "http://wiki.opnsense.org/index.php/GUI:".strtoupper(str_replace(".php","",$pagename));

}

/* Redirect to help page. */
header("Location: {$helppage}");

?>
