<?php

/*
	Copyright (C) 2007, 2008 Scott Ullrich <sullrich@gmail.com>
        Copyright (C) 2005-2006 Bill Marquette <bill.marquette@gmail.com>
        Copyright (C) 2006 Paul Taylor <paultaylor@winn-dixie.com>.
        Copyright (C) 2003-2006 Manuel Kasper <mk@neon1.net>.
	(from priv.inc) Copyright (C) 2008 Shrew Soft Inc
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

require_once("auth.inc");
require_once("filter.inc");
require_once("functions.inc");

// provided via legacy_bindings.inc 
global $priv_list;
$acl = new OPNsense\Core\ACL();
$priv_list = $acl->getLegacyPrivList();


function cmp_page_matches($page, & $matches, $fullwc = true) {
	if (!is_array($matches))
		return false;

	/* skip any leading fwdslash */
	$test = strpos($page, "/");
	if ($test !== false && $test == 0)
		$page = substr($page, 1);

	/* look for a match */
	foreach ($matches as $match) {

		/* possibly ignore full wildcard match */
		if (!$fullwc && !strcmp($match ,"*"))
			continue;

		/* compare exact or wildcard match */
		$match =  str_replace(array(".", "*","?"), array("\.", ".*","\?"), $match);
		$result = preg_match("@^/{$match}$@", "/{$page}");

		if ($result)
			return true;
	}

	return false;
}


function isAllowed($username, $page)
{
	global $_SESSION;

	if (!isset($username)) {
		return false;
	}

	/* root access check */
	$user = getUserEntry($username);
	if (isset($user)) {
		if (isset($user['uid'])) {
			if ($user['uid'] == 0) {
				return true;
			}
		}
	}

	/* user privelege access check */
	if (cmp_page_matches($page, $_SESSION['page-match'])) {
		return true;
	}

	return false;
}

function isAllowedPage($page)
{
	if (!isset($_SESSION['Username'])) {
		return false;
	}

	/* root access check */
	$user = getUserEntry($_SESSION['Username']);
	if (isset($user)) {
		if (isset($user['uid'])) {
			if ($user['uid'] == 0) {
				return true;
			}
		}
	}

	/* user privelege access check */
	return cmp_page_matches($page, $_SESSION['page-match']);
}


function getPrivPages(& $entry, & $allowed_pages) {
	global $priv_list;

	if (!isset($entry['priv']) || !is_array($entry['priv']))
		return;

	foreach ($entry['priv'] as $pname) {
		if (strncmp($pname, "page-", 5))
			continue;
		$priv = &$priv_list[$pname];
		if (!is_array($priv))
			continue;
		$matches = &$priv['match'];
		if (!is_array($matches))
			continue;
		foreach ($matches as $match)
			$allowed_pages[] = $match;
	}
}



function getAllowedPages($username) {
	global $config, $_SESSION;

	if (!function_exists("ldap_connect"))
		return;

	$allowed_pages = array();
	$allowed_groups = array();

	if (isset($config['system']['webgui']['authmode'])) {
		$authcfg = auth_get_authserver($config['system']['webgui']['authmode']);
	} else {
		$authcfg['type'] = 'local';
	}
	// obtain ldap groups if we are in ldap mode
	if ($authcfg['type'] == "ldap")
		$allowed_groups = @ldap_get_groups($username, $authcfg);
	else {
		// search for a local user by name
		$local_user = getUserEntry($username);
		getPrivPages($local_user, $allowed_pages);

		// obtain local groups if we have a local user
		$allowed_groups = local_user_get_groups($local_user);
	}

	// build a list of allowed pages
	if (is_array($config['system']['group']) && is_array($allowed_groups)) {
		foreach ($config['system']['group'] as $group) {
			if (in_array($group['name'], $allowed_groups)) {
				getPrivPages($group, $allowed_pages);
			}
		}
	}

	$_SESSION['page-match'] = $allowed_pages;

	return $allowed_pages;
}


function session_auth() {
	global $config, $_SESSION;

	// Handle HTTPS httponly and secure flags
	$currentCookieParams = session_get_cookie_params();
	session_set_cookie_params(
		$currentCookieParams["lifetime"],
		$currentCookieParams["path"],
		NULL,
		($config['system']['webgui']['protocol'] == "https"),
		true
	);

	if (session_status() == PHP_SESSION_NONE) {
		session_start();
	}

	// Detect protocol change
	if (!isset($_POST['login']) && !empty($_SESSION['Logged_In']) && $_SESSION['protocol'] != $config['system']['webgui']['protocol'])
		return false;

	/* Validate incoming login request */
	if (isset($_POST['login']) && !empty($_POST['usernamefld']) && !empty($_POST['passwordfld'])) {
		if (isset($config['system']['webgui']['authmode'])) {
			$authcfg = auth_get_authserver($config['system']['webgui']['authmode']);
		} else {
			$authcfg = null;
		}
		if (authenticate_user($_POST['usernamefld'], $_POST['passwordfld'], $authcfg) ||
		    authenticate_user($_POST['usernamefld'], $_POST['passwordfld'])) {
			// Generate a new id to avoid session fixation
			session_regenerate_id();
			$_SESSION['Logged_In'] = "True";
			$_SESSION['Username'] = $_POST['usernamefld'];
			$_SESSION['last_access'] = time();
			$_SESSION['protocol'] = $config['system']['webgui']['protocol'];
			if (!isset($config['system']['webgui']['quietlogin'])) {
				log_error(sprintf(gettext("Successful login for user '%1\$s' from: %2\$s"), $_POST['usernamefld'], $_SERVER['REMOTE_ADDR']));
			}
			if (isset($_POST['postafterlogin']))
				return true;
			else {
				header("Location: {$_SERVER['REQUEST_URI']}");
			}
			exit;
		} else {
			/* give the user an error message */
			$_SESSION['Login_Error'] = gettext('Wrong username or password.');
			log_error("webConfigurator authentication error for '{$_POST['usernamefld']}' from {$_SERVER['REMOTE_ADDR']}");
			if (isAjax()) {
				echo "showajaxmessage('{$_SESSION['Login_Error']}');";
				return;
			}
		}
	}

	/* Show login page if they aren't logged in */
	if (empty($_SESSION['Logged_In']))
		return false;

	/* If session timeout isn't set, we don't mark sessions stale */
	if (!isset($config['system']['webgui']['session_timeout'])) {
		/* Default to 4 hour timeout if one is not set */
		if ($_SESSION['last_access'] < (time() - 14400)) {
			$_GET['logout'] = true;
			$_SESSION['Logout'] = true;
		} else
			$_SESSION['last_access'] = time();
	} else if (intval($config['system']['webgui']['session_timeout']) == 0) {
		/* only update if it wasn't ajax */
		if (!isAjax())
			$_SESSION['last_access'] = time();
	} else {
		/* Check for stale session */
		if ($_SESSION['last_access'] < (time() - ($config['system']['webgui']['session_timeout'] * 60))) {
			$_GET['logout'] = true;
			$_SESSION['Logout'] = true;
		} else {
			/* only update if it wasn't ajax */
			if (!isAjax())
				$_SESSION['last_access'] = time();
		}
	}

	/* user hit the logout button */
	if (isset($_GET['logout'])) {

		if (isset($_SESSION['Logout']))
			log_error(sprintf(gettext("Session timed out for user '%1\$s' from: %2\$s"), $_SESSION['Username'], $_SERVER['REMOTE_ADDR']));
		else
			log_error(sprintf(gettext("User logged out for user '%1\$s' from: %2\$s"), $_SESSION['Username'], $_SERVER['REMOTE_ADDR']));

		/* wipe out $_SESSION */
		$_SESSION = array();

		if (isset($_COOKIE[session_name()]))
			setcookie(session_name(), '', time()-42000, '/');

		/* and destroy it */
		session_destroy();

		$scriptName = explode("/", $_SERVER["SCRIPT_FILENAME"]);
		$scriptElms = count($scriptName);
		$scriptName = $scriptName[$scriptElms-1];

		if (isAjax())
			return false;

		/* redirect to page the user is on, it'll prompt them to login again */
		header("Location: {$scriptName}");

		return false;
	}

	return true;
}


/* Authenticate user - exit if failed */
if (!session_auth()) {
    display_login_form();
    exit;
}

/*
 * Once here, the user has authenticated with the web server.
 * We give them access only to the appropriate pages based on
 * the user or group privileges.
 */
$allowedpages = getAllowedPages($_SESSION['Username']);

/*
 * redirect to first allowed page if requesting a wrong url
 */
if (!isAllowedPage($_SERVER['REQUEST_URI'])) {
    if (count($allowedpages) > 0) {
        $page = str_replace('*', '', $allowedpages[0]);
	header("Location: /{$page}");

        $username = empty($_SESSION["Username"]) ? "(system)" : $_SESSION['Username'];
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $username .= '@' . $_SERVER['REMOTE_ADDR'];
        }
        log_error("{$username} attempted to access {$_SERVER['REQUEST_URI']} but does not have access to that page. Redirecting to {$page}.");

        exit;
    } else {
        display_error_form("201", gettext("No page assigned to this user! Click here to logout."));
        exit;
    }
}

/*
 * Close session data to allow other scripts from same host to come in.
 * A session can be reactivated from calling session_start again
 */
session_write_close();

/*
 * determine if the user is allowed access to the requested page
 */
function display_error_form($http_code, $desc)
{
    global $config, $g;
    $g['theme'] = get_current_theme();
    if (isAjax()) {
        printf(gettext('Error: %1$s Description: %2$s'), $http_code, $desc);
        return;
    }

?><!doctype html>
<!--[if IE 8 ]><html lang="en" class="ie ie8 lte9 lte8 no-js"><![endif]-->
<!--[if IE 9 ]><html lang="en" class="ie ie9 lte9 no-js"><![endif]-->
<!--[if (gt IE 9)|!(IE)]><!--><html lang="en" class="no-js"><!--<![endif]-->
	<head>

		<meta charset="UTF-8" />
		<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

		<meta name="robots" content="index, follow, noodp, noydir" />
		<meta name="keywords" content="" />
		<meta name="description" content="" />
		<meta name="copyright" content="" />
		<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />

		<title><?=$http_code?></title>

		<link href="/themes/<?=$g['theme'];?>/build/css/main.css" media="screen, projection" rel="stylesheet">
		<link href="/themes/<?=$g['theme'];?>/assets/images/favicon.png" rel="shortcut icon">

		<!--[if lt IE 9]><script src="//cdnjs.cloudflare.com/ajax/libs/html5shiv/3.7.2/html5shiv.min.js"></script><![endif]-->
	</head>
	<body class="page-login">
		<div id="errordesc">
			<h1>&nbsp</h1>
			<a href="/index.php?logout">
			<p id="errortext" style="vertical-align: middle; text-align: center;">
				<span style="color: #000000; font-weight: bold;">
					<?=$desc;?>
				</span>
			</p>
		</div>
	</body>
</html><?php

} // end function

function display_login_form()
{
    global $config, $g;
    $g['theme'] = get_current_theme();

    unset($input_errors);


    if (isAjax()) {
        exit;
    }

    /* Check against locally configured IP addresses, which will catch when someone
	   port forwards WebGUI access from WAN to an internal IP on the router. */
    $FilterIflist = filter_generate_optcfg_array() ;
    $local_ip = false;
    if (strstr($_SERVER['HTTP_HOST'], ":")) {
        $http_host_port = explode(":", $_SERVER['HTTP_HOST']);
        $http_host = $http_host_port[0];
    } else {
        $http_host = $_SERVER['HTTP_HOST'];
    }
    foreach ($FilterIflist as $iflist) {
        if ($iflist['ip'] == $http_host) {
            $local_ip = true;
        }
        if ($iflist['ipv6'] == $http_host) {
            $local_ip = true;
        }
    }

    if (isset($config['virtualip']['vip'])) {
        foreach ($config['virtualip']['vip'] as $vip) {
            if ($vip['subnet'] == $http_host) {
                $local_ip = true;
            }
        }
    }

    if (isset($config['openvpn']['openvpn-server'])) {
        foreach ($config['openvpn']['openvpn-server'] as $ovpns) {
            if (is_ipaddrv4($http_host) && !empty($ovpns['tunnel_network']) && ip_in_subnet($http_host, $ovpns['tunnel_network'])) {
                $local_ip = true;
                break;
            }

            if (is_ipaddrv6($http_host) && !empty($ovpns['tunnel_networkv6']) && ip_in_subnet($http_host, $ovpns['tunnel_networkv6'])) {
                $local_ip = true;
                break;
            }
        }
    }
    setcookie("cookie_test", time() + 3600);
    $have_cookies = isset($_COOKIE["cookie_test"]);

?><!doctype html>
<!--[if IE 8 ]><html lang="en" class="ie ie8 lte9 lte8 no-js"><![endif]-->
<!--[if IE 9 ]><html lang="en" class="ie ie9 lte9 no-js"><![endif]-->
<!--[if (gt IE 9)|!(IE)]><!--><html lang="en" class="no-js"><!--<![endif]-->
	<head>

		<meta charset="UTF-8" />
		<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

		<meta name="robots" content="index, follow, noodp, noydir" />
		<meta name="keywords" content="" />
		<meta name="description" content="" />
		<meta name="copyright" content="" />
		<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />

		<title><?=gettext("Login"); ?></title>

		<link href="/themes/<?=$g['theme'];?>/build/css/main.css" media="screen, projection" rel="stylesheet">
		<link href="/themes/<?=$g['theme'];?>/assets/images/favicon.png" rel="shortcut icon">

		<!--[if lt IE 9]><script src="//cdnjs.cloudflare.com/ajax/libs/html5shiv/3.7.2/html5shiv.min.js"></script><![endif]-->

	</head>
	<body class="page-login">

	<div class="container">
		<?php
        if (is_ipaddr($http_host) && !$local_ip && !isset($config['system']['webgui']['nohttpreferercheck'])) {
            print_info_box(gettext("You are accessing this router by an IP address not configured locally, which may be forwarded by NAT or other means. <br /><br />If you did not setup this forwarding, you may be the target of a man-in-the-middle attack."));
        }
                $loginautocomplete = isset($config['system']['webgui']['loginautocomplete']) ? '' : 'autocomplete="off"';
            ?>


		<main class="login-modal-container">
			<header class="login-modal-head" style="height:55px;">
				<div class="navbar-brand">
					<img src="/themes/<?=$g['theme'];?>/assets/images/default-logo.png" height="30" width="150"/>
				</div>
			</header>

			<div class="login-modal-content">
				<?php if (isset($_SESSION['Login_Error'])) {
?>
				<div id="inputerrors" class="text-danger"><?=$_SESSION['Login_Error'];?></div><br />
				<?php unset($_SESSION['Login_Error']);
} // endif ?>

				    <form class="clearfix" id="iform" name="iform" method="post" <?= $loginautocomplete ?> action="<?=$_SERVER['REQUEST_URI'];?>">

			  <div class="form-group">
			    <label for="usernamefld"><?=gettext("Username:"); ?></label>
			    <input id="usernamefld" type="text" name="usernamefld" class="form-control user" tabindex="1" autofocus="autofocus" autocapitalize="off" autocorrect="off" />
			  </div>

			  <div class="form-group">
			    <label for="passwordfld"><?=gettext("Password:"); ?></label>
			    <input id="passwordfld" type="password" name="passwordfld" class="form-control pwd" tabindex="2" />
			  </div>

			  <input type="hidden" name="login" value="1" /><!-- XXX login workaround -->

			  <button type="submit" name="login" class="btn btn-primary pull-right"><?=gettext("Login"); ?></button>

			</form>

			<?php if (!$have_cookies && isset($_POST['login'])) :
?>
					<br /><br />
					<span class="text-danger">
						<?= gettext("Your browser must support cookies to login."); ?>
					</span>
					<?php
endif; ?>

			    </div>

			</main>

		</div>

			<footer class="login-foot container-fluid">
			<a target="_blank" href="<?=$g['product_website']?>" class="redlnk"><?=$g['product_name']?></a> is &copy;
					<?=$g['product_copyright_years']?> by <a href="<?=$g['product_copyright_url']?>" class="tblnk"><?=$g['product_copyright_owner']?></a> All Rights Reserved.
			</footer>

		</body>
	</html><?php

} // end function
