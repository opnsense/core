<?php
/* Redirector for Contextual Help System
 * (c) 2009 Jim Pingle <jimp@pfsense.org>
 *
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

	/* If the filename is pkg_edit.php or wizard.php, reparse looking
		for the .xml filename */
	if (($pagename == "pkg.php") || ($pagename == "pkg_edit.php") || ($pagename == "wizard.php")) {
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
