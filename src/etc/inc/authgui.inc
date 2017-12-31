<?php

/*
    Copyright (C) 2008 Shrew Soft Inc. <mgrooms@shrew.net>
    Copyright (C) 2007-2008 Scott Ullrich <sullrich@gmail.com>
    Copyright (C) 2005-2006 Bill Marquette <bill.marquette@gmail.com>
    Copyright (C) 2006 Paul Taylor <paultaylor@winn-dixie.com>
    Copyright (C) 2003-2006 Manuel Kasper <mk@neon1.net>
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

// provided via legacy_bindings.inc
$acl = new OPNsense\Core\ACL();
$priv_list = $acl->getPrivList();

function cmp_page_matches($page, &$matches, $fullwc = true)
{
    if (!is_array($matches)) {
        return false;
    }

    /* skip any leading fwdslash */
    $test = strpos($page, "/");
    if ($test !== false && $test == 0) {
        $page = substr($page, 1);
    }

    /* look for a match */
    foreach ($matches as $match) {
        /* possibly ignore full wildcard match */
        if (!$fullwc && !strcmp($match ,"*")) {
            continue;
        }

        /* compare exact or wildcard match */
        $match =  str_replace(array(".", "*","?"), array("\.", ".*","\?"), $match);
        $result = preg_match("@^/{$match}$@", "/{$page}");

        if ($result) {
            return true;
        }
    }

    return false;
}

function isAllowedPage($page)
{
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['Username'])) {
        session_write_close();
        return false;
    }

    /* root access check */
    $user = getUserEntry($_SESSION['Username']);
    session_write_close();
    if (isset($user)) {
        if (isset($user['uid'])) {
            if ($user['uid'] == 0) {
                return true;
            }
        }
    }
    if ($page == "/") {
        $page = "/index.php";
    }

    /* user privelege access check */
    $allowedpages = getAllowedPages($_SESSION['Username']);
    return cmp_page_matches($page, $allowedpages);
}


function getPrivPages(&$entry, &$allowed_pages)
{
    global $priv_list;

    if (!isset($entry['priv']) || !is_array($entry['priv'])) {
        return;
    }

    foreach ($entry['priv'] as $pname) {
        if (strncmp($pname, "page-", 5)) {
            continue;
        }
        $priv = &$priv_list[$pname];
        if (!is_array($priv)) {
            continue;
        }
        $matches = &$priv['match'];
        if (!is_array($matches)) {
            continue;
        }
        foreach ($matches as $match) {
            $allowed_pages[] = $match;
        }
    }
}



function getAllowedPages($username) {
    global $config;

    $allowed_pages = array();
    $allowed_groups = array();

    // search for a local user by name
    $local_user = getUserEntry($username);
    getPrivPages($local_user, $allowed_pages);

    // obtain local groups if we have a local user
    $allowed_groups = local_user_get_groups($local_user);

    // build a list of allowed pages
    if (is_array($config['system']['group']) && is_array($allowed_groups)) {
        foreach ($config['system']['group'] as $group) {
            // a bit odd, we have seem some cases in the wild where $group doesn't contain a name attribute.
            // this shouldn't happen, but to avoid warnings we will check over here.
            if (isset($group['name']) && in_array($group['name'], $allowed_groups)) {
                getPrivPages($group, $allowed_pages);
            }
        }
    }

    return $allowed_pages;
}

function set_language()
{
    global $config, $userindex;

    $lang = 'en_US';

    if (!empty($config['system']['language'])) {
        $lang = $config['system']['language'];
    }

    if (!empty($_SESSION['Username']) && array_key_exists($_SESSION['Username'], $userindex) &&
        !empty($config['system']['user'][$userindex[$_SESSION['Username']]]['language'])) {
        $lang = $config['system']['user'][$userindex[$_SESSION['Username']]]['language'];
    }

    $lang_encoding = $lang . '.UTF-8';
    $textdomain = 'OPNsense';

    putenv('LANG=' . $lang_encoding);
    textdomain($textdomain);
    bindtextdomain($textdomain, '/usr/local/share/locale');
    bind_textdomain_codeset($textdomain, $lang_encoding);
}

function session_auth(&$Login_Error)
{
    global $config;

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
    if (!isset($_POST['login']) && !empty($_SESSION['Logged_In']) && $_SESSION['protocol'] != $config['system']['webgui']['protocol']) {
        session_write_close();
        return false;
    }

    /* Validate incoming login request */
    if (isset($_POST['login']) && !empty($_POST['usernamefld']) && !empty($_POST['passwordfld'])) {
        $authcfg = auth_get_authserver("Local Database");
        $authcfg_fallback = auth_get_authserver("Local Database");

        if (isset($config['system']['webgui']['authmode'])) {
            $authcfg = auth_get_authserver($config['system']['webgui']['authmode']);
        }

        if (!empty($config['system']['webgui']['authmode_fallback'])) {
            if ($config['system']['webgui']['authmode_fallback'] == "__NO_FALLBACK__") {
                // no fallback
                $authcfg_fallback = false;
            } else {
                $authcfg_fallback = auth_get_authserver($config['system']['webgui']['authmode_fallback']);
            }
        }

        if ($authcfg == $authcfg_fallback) {
            // it doesn't make sense to fallback to the same authenticator
            $authcfg_fallback = false;
        }

        // authenticate using config settings, or local if failed
        if (authenticate_user($_POST['usernamefld'], $_POST['passwordfld'], $authcfg) ||
            ($authcfg_fallback !== false && authenticate_user($_POST['usernamefld'], $_POST['passwordfld'], $authcfg_fallback))
            ) {
            // Generate a new id to avoid session fixation
            session_regenerate_id();
            $_SESSION['Logged_In'] = "True";
            $_SESSION['Username'] = $_POST['usernamefld'];
            $_SESSION['last_access'] = time();
            $_SESSION['protocol'] = $config['system']['webgui']['protocol'];
            if (!isset($config['system']['webgui']['quietlogin'])) {
                log_error(sprintf("Successful login for user '%s' from: %s", $_POST['usernamefld'], $_SERVER['REMOTE_ADDR']));
            }
            if (!empty($_GET['url'])) {
                header(url_safe("Location: {$_GET['url']}"));
            } else {
                header(url_safe("Location: {$_SERVER['REQUEST_URI']}"));
            }
            exit;
        } else {
            log_error("Web GUI authentication error for '{$_POST['usernamefld']}' from {$_SERVER['REMOTE_ADDR']}");
            $Login_Error = true;
        }
    }

    /* Show login page if they aren't logged in */
    if (empty($_SESSION['Logged_In'])) {
        session_write_close();
        return false;
    }

    /* If session timeout isn't set, we don't mark sessions stale */
    if (!isset($config['system']['webgui']['session_timeout'])) {
        /* Default to 4 hour timeout if one is not set */
        if ($_SESSION['last_access'] < (time() - 14400)) {
            $_GET['logout'] = true;
            $_SESSION['Logout'] = true;
        } else {
            $_SESSION['last_access'] = time();
        }
    } else if (intval($config['system']['webgui']['session_timeout']) == 0) {
          $_SESSION['last_access'] = time();
    } else {
        /* Check for stale session */
        if ($_SESSION['last_access'] < (time() - ($config['system']['webgui']['session_timeout'] * 60))) {
            $_GET['logout'] = true;
            $_SESSION['Logout'] = true;
        } else {
            $_SESSION['last_access'] = time();
        }
    }

    /* user hit the logout button */
    if (isset($_GET['logout'])) {
        if (isset($_SESSION['Logout'])) {
            log_error(sprintf("Session timed out for user '%s' from: %s", $_SESSION['Username'], $_SERVER['REMOTE_ADDR']));
        } else {
            log_error(sprintf("User logged out for user '%s' from: %s", $_SESSION['Username'], $_SERVER['REMOTE_ADDR']));
        }

        /* wipe out $_SESSION */
        $_SESSION = array();

        if (isset($_COOKIE[session_name()])) {
            $secure = $config['system']['webgui']['protocol'] == "https";
            setcookie(session_name(), '', time()-42000, '/', null, $secure, true);
        }

        /* and destroy it */
        session_destroy();

        $scriptName = explode("/", $_SERVER["SCRIPT_FILENAME"]);
        $scriptElms = count($scriptName);
        $scriptName = $scriptName[$scriptElms-1];

        /* redirect to page the user is on, it'll prompt them to login again */
        header(url_safe("Location: {$scriptName}"));
        exit;
    }

    session_write_close();
    return true;
}

/* XXX spagehtti code :( */
$Login_Error = false;

/* Authenticate user - exit if failed */
if (!session_auth($Login_Error)) {
    set_language();
    display_login_form($Login_Error ? gettext('Wrong username or password.') : null);
    exit;
}

set_language();

/*
 * redirect to first allowed page if requesting a wrong url
 */
if (!isAllowedPage($_SERVER['REQUEST_URI'])) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $allowedpages = getAllowedPages($_SESSION['Username']);
    if (count($allowedpages) > 0) {
        $page = str_replace('*', '', $allowedpages[0]);
        $username = empty($_SESSION["Username"]) ? "(system)" : $_SESSION['Username'];
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $username .= '@' . $_SERVER['REMOTE_ADDR'];
        }
        log_error("{$username} attempted to access {$_SERVER['REQUEST_URI']} but does not have access to that page. Redirecting to {$page}.");
        header(url_safe("Location: /{$page}"));
        exit;
    } else {
        display_error_form("201", gettext("No page assigned to this user! Click here to logout."));
        exit;
    }
}

/*
 * determine if the user is allowed access to the requested page
 */
function display_error_form($http_code, $desc)
{
    $themename = htmlspecialchars(get_current_theme());

?><!doctype html>
<!--[if IE 8 ]><html lang="en" class="ie ie8 lte9 lte8 no-js"><![endif]-->
<!--[if IE 9 ]><html lang="en" class="ie ie9 lte9 no-js"><![endif]-->
<!--[if (gt IE 9)|!(IE)]><!--><html lang="en" class="no-js"><!--<![endif]-->
  <head>

    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

    <meta name="robots" content="noindex, nofollow, noodp, noydir" />
    <meta name="keywords" content="" />
    <meta name="description" content="" />
    <meta name="copyright" content="" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />

    <title><?=$http_code?></title>

    <link href="/ui/themes/<?= $themename ?>/build/css/main.css" rel="stylesheet">
    <link href="/ui/themes/<?= $themename ?>/build/images/favicon.png" rel="shortcut icon">

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

function display_login_form($Login_Error)
{
    global $config, $g;

    $themename = htmlspecialchars(get_current_theme());

    unset($input_errors);

    /*
     * Check against locally configured IP addresses, which will catch when
     * someone port-forwards WebGUI access from WAN to an internal IP on the
     * router.
     */
    $local_ip = isAuthLocalIP($http_host);

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
    setcookie("cookie_test", time() + 3600 ,  time() + 3600, '/', null, $config['system']['webgui']['protocol'] == "https", true);
    $have_cookies = isset($_COOKIE["cookie_test"]);

?><!doctype html>
<!--[if IE 8 ]><html lang="en" class="ie ie8 lte9 lte8 no-js"><![endif]-->
<!--[if IE 9 ]><html lang="en" class="ie ie9 lte9 no-js"><![endif]-->
<!--[if (gt IE 9)|!(IE)]><!--><html lang="en" class="no-js"><!--<![endif]-->
  <head>

    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

    <meta name="robots" content="noindex, nofollow, noodp, noydir" />
    <meta name="keywords" content="" />
    <meta name="description" content="" />
    <meta name="copyright" content="" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />

    <title><?=gettext("Login"); ?></title>

    <link href="/ui/themes/<?= $themename ?>/build/css/main.css" rel="stylesheet">
    <link href="/ui/themes/<?= $themename ?>/build/images/favicon.png" rel="shortcut icon">

    <script type="text/javascript" src="/ui/js/jquery-3.2.1.min.js"></script>
    <script type="text/javascript" src="/ui/js/jquery-migrate-3.0.1.min.js"></script>

<?php
    if (file_exists("/usr/local/opnsense/www/themes/".$themename."/build/js/theme.js")):?>
    <script type="text/javascript" src="/ui/themes/<?=$themename?>/build/js/theme.js"></script>
<?php
    endif;?>

    <!--[if lt IE 9]><script src="//cdnjs.cloudflare.com/ajax/libs/html5shiv/3.7.2/html5shiv.min.js"></script><![endif]-->

  </head>
  <body class="page-login">

  <div class="container">
    <?php
        if (is_ipaddr($http_host) && !$local_ip && !isset($config['system']['webgui']['nohttpreferercheck'])) {
            print_info_box(sprintf(gettext("You are accessing this router by an IP address not configured locally, which may be forwarded by NAT or other means. %sIf you did not setup this forwarding, you may be the target of a man-in-the-middle attack."),'<br /><br />'));
        }
            ?>

    <main class="login-modal-container">
      <header class="login-modal-head" style="height:55px;">
        <div class="navbar-brand">
          <img src="/ui/themes/<?= $themename ?>/build/images/default-logo.png" height="30" alt="logo"/>
        </div>
      </header>

      <div class="login-modal-content">
        <div id="inputerrors" class="text-danger"><?= !empty($Login_Error) ? $Login_Error : '&nbsp;' ?></div><br />

            <form class="clearfix" id="iform" name="iform" method="post" autocomplete="off" action="<?=$_SERVER['REQUEST_URI'];?>">

        <div class="form-group">
          <label for="usernamefld"><?=gettext("Username:"); ?></label>
          <input id="usernamefld" type="text" name="usernamefld" class="form-control user" tabindex="1" autofocus="autofocus" autocapitalize="off" autocorrect="off" />
        </div>

        <div class="form-group">
          <label for="passwordfld"><?=gettext("Password:"); ?></label>
          <input id="passwordfld" type="password" name="passwordfld" class="form-control pwd" tabindex="2" />
        </div>

        <button type="submit" name="login" value="1" class="btn btn-primary pull-right"><?=gettext("Login"); ?></button>

      </form>

      <?php if (!$have_cookies && isset($_POST['login'])) : ?>
        <br /><br />
        <span class="text-danger">
          <?= gettext("Your browser must support cookies to login."); ?>
        </span>
      <?php endif; ?>

          </div>

      </main>
      <div class="login-foot text-center">
        <a target="_blank" href="<?=$g['product_website']?>" class="redlnk"><?=$g['product_name']?></a> (c) <?=$g['product_copyright_years']?>
        <a href="<?=$g['product_copyright_url']?>" class="tblnk"><?=$g['product_copyright_owner']?></a>
      </div>

    </div>

    </body>
  </html>
<?php } // end function
