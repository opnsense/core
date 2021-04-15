<?php

/*
 * Copyright (C) 2008 Shrew Soft Inc. <mgrooms@shrew.net>
 * Copyright (C) 2007-2008 Scott Ullrich <sullrich@gmail.com>
 * Copyright (C) 2005-2006 Bill Marquette <bill.marquette@gmail.com>
 * Copyright (C) 2006 Paul Taylor <paultaylor@winn-dixie.com>
 * Copyright (C) 2003-2006 Manuel Kasper <mk@neon1.net>
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

require_once("auth.inc");

$acl = new OPNsense\Core\ACL();
$priv_list = $acl->getPrivList();

function set_language()
{
    global $config, $userindex;

    $lang = 'en_US';

    if (!empty($config['system']['language'])) {
        $lang = $config['system']['language'];
    }

    if (
        !empty($_SESSION['Username']) && array_key_exists($_SESSION['Username'], $userindex) &&
        !empty($config['system']['user'][$userindex[$_SESSION['Username']]]['language'])
    ) {
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

    function auth_log($message)
    {
        openlog("webgui", LOG_ODELAY, LOG_AUTH);
        log_error($message);
        closelog();
    }

    if (session_status() == PHP_SESSION_NONE) {
        // Handle HTTPS httponly and secure flags
        $currentCookieParams = session_get_cookie_params();
        session_set_cookie_params(
            $currentCookieParams["lifetime"],
            $currentCookieParams["path"],
            null,
            ($config['system']['webgui']['protocol'] == "https"),
            true
        );
        session_start();
    }

    // Detect protocol change
    if (!isset($_POST['login']) && !empty($_SESSION['Username']) && $_SESSION['protocol'] != $config['system']['webgui']['protocol']) {
        session_write_close();
        return false;
    }

    /* Validate incoming login request */
    if (isset($_POST['login']) && !empty($_POST['usernamefld']) && !empty($_POST['passwordfld'])) {
        $authFactory = new \OPNsense\Auth\AuthenticationFactory();
        $is_authenticated = $authFactory->authenticate("WebGui", $_POST['usernamefld'], $_POST['passwordfld']);

        if ($is_authenticated) {
            $authenticator = $authFactory->lastUsedAuth;
            // Generate a new id to avoid session fixation
            session_regenerate_id();
            $_SESSION['Username'] = $authenticator->getUserName($_POST['usernamefld']);
            $_SESSION['last_access'] = time();
            $_SESSION['protocol'] = $config['system']['webgui']['protocol'];
            if ($authenticator != null && $authenticator->shouldChangePassword($_SESSION['Username'], $_POST['passwordfld'])) {
                $_SESSION['user_shouldChangePassword'] = true;
            }
            if (!isset($config['system']['webgui']['quietlogin'])) {
                auth_log(sprintf("Successful login for user '%s' from: %s", $_POST['usernamefld'], $_SERVER['REMOTE_ADDR']));
            }
            if (!empty($_GET['url'])) {
                $tmp_url_parts = parse_url($_GET['url']);
                if (!empty($tmp_url_parts['host'])) {
                    $redir_uri = $tmp_url_parts['path'];
                    $redir_uri .= !empty($tmp_url_parts['query']) ? "?" . $tmp_url_parts['query'] : "";
                    $redir_uri .= !empty($tmp_url_parts['fragment']) ? "#" . $tmp_url_parts['fragment'] : "";
                } else {
                    $redir_uri = $_GET['url'];
                }
                header(url_safe("Location: {$redir_uri}"));
            } elseif (!empty($_SESSION['user_shouldChangePassword'])) {
                header("Location: system_usermanager_passwordmg.php");
            } else {
                if ($_SERVER['REQUEST_URI'] == "/") {
                    // default landing page
                    $acl = new OPNsense\Core\ACL();
                    $url = $acl->getLandingPage($_SESSION['Username']);
                    header(url_safe("Location: /{$url}"));
                } else {
                    header(url_safe("Location: {$_SERVER['REQUEST_URI']}"));
                }
            }
            exit;
        } else {
            auth_log("Web GUI authentication error for '{$_POST['usernamefld']}' from {$_SERVER['REMOTE_ADDR']}");
            $Login_Error = true;
        }
    }

    /* Show login page if they aren't logged in */
    if (empty($_SESSION['Username'])) {
        return false;
    }

    /* If session timeout isn't set, we don't mark sessions stale */
    if (empty($config['system']['webgui']['session_timeout'])) {
        /* Default to 4 hour timeout if one is not set */
        if ($_SESSION['last_access'] < (time() - 14400)) {
            $_GET['logout'] = true;
            $_SESSION['Logout'] = true;
        } else {
            $_SESSION['last_access'] = time();
        }
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
            auth_log(sprintf("Session timed out for user '%s' from: %s", $_SESSION['Username'], $_SERVER['REMOTE_ADDR']));
        } else {
            auth_log(sprintf("User logged out for user '%s' from: %s", $_SESSION['Username'], $_SERVER['REMOTE_ADDR']));
        }

        /* wipe out $_SESSION */
        $_SESSION = array();

        if (isset($_COOKIE[session_name()])) {
            $secure = $config['system']['webgui']['protocol'] == "https";
            setcookie(session_name(), '', time() - 42000, '/', null, $secure, true);
        }

        /* and destroy it */
        session_destroy();

        header(url_safe("Location: /"));
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
if ($_SERVER['REQUEST_URI']  == '/') {
    $page = '/index.php';
} else {
    /* reconstruct page uri to use actual script location, mimic realpath() behaviour */
    $page = $_SERVER['SCRIPT_NAME'];
    $tmp_uri = parse_url($_SERVER['REQUEST_URI']);
    if (!empty($tmp_uri['query'])) {
        $page .= '?' . $tmp_uri['query'];
    }
}
if ($_SESSION['Username'] != 'root' && !$acl->isPageAccessible($_SESSION['Username'], $page)) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $page = $acl->getLandingPage($_SESSION['Username']);
    if (!empty($page)) {
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
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <meta name="robots" content="noindex, nofollow, noodp, noydir" />
    <meta name="keywords" content="" />
    <meta name="description" content="" />
    <meta name="copyright" content="" />
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1" />

    <title><?=$http_code?></title>

    <link href="<?= cache_safe("/ui/themes/{$themename}/build/css/main.css") ?>" rel="stylesheet">
    <link href="<?= cache_safe("/ui/themes/{$themename}/build/images/favicon.png") ?>" rel="shortcut icon">

    <!--[if lt IE 9]><script src="//cdnjs.cloudflare.com/ajax/libs/html5shiv/3.7.2/html5shiv.min.js"></script><![endif]-->
  </head>
  <body class="page-login">
    <div id="errordesc">
      <h1>&nbsp;</h1>
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
    setcookie("cookie_test", bin2hex(random_bytes(16)), time() + 3600, '/', null, $config['system']['webgui']['protocol'] == "https", true);
    $have_cookies = isset($_COOKIE["cookie_test"]);
    ?><!doctype html>
<!--[if IE 8 ]><html lang="en" class="ie ie8 lte9 lte8 no-js"><![endif]-->
<!--[if IE 9 ]><html lang="en" class="ie ie9 lte9 no-js"><![endif]-->
<!--[if (gt IE 9)|!(IE)]><!--><html lang="en" class="no-js"><!--<![endif]-->
  <head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <meta name="robots" content="noindex, nofollow, noodp, noydir" />
    <meta name="keywords" content="" />
    <meta name="description" content="" />
    <meta name="copyright" content="" />
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1" />

    <title><?=gettext("Login"); ?></title>

    <link href="<?= cache_safe("/ui/themes/{$themename}/build/css/main.css") ?>" rel="stylesheet">
    <link href="<?= cache_safe("/ui/themes/{$themename}/build/images/favicon.png") ?>" rel="shortcut icon">

    <script src="/ui/js/jquery-3.5.1.min.js"></script>

    <?php if (file_exists("/usr/local/opnsense/www/themes/{$themename}/build/js/theme.js")) : ?>
    <script src="<?= cache_safe("/ui/themes/{$themename}/build/js/theme.js") ?>"></script>
    <?php endif ?>

    <!--[if lt IE 9]><script src="//cdnjs.cloudflare.com/ajax/libs/html5shiv/3.7.2/html5shiv.min.js"></script><![endif]-->

  </head>
  <body class="page-login">

  <div class="container">
    <?php
    if (is_ipaddr($http_host) && !$local_ip && !isset($config['system']['webgui']['nohttpreferercheck'])) {
        print_info_box(sprintf(gettext("You are accessing this router by an IP address not configured locally, which may be forwarded by NAT or other means. %sIf you did not setup this forwarding, you may be the target of a man-in-the-middle attack."), '<br /><br />'));
    }
    ?>

    <main class="login-modal-container">
      <header class="login-modal-head" style="height:50px;">
        <div class="navbar-brand">
    <?php if (file_exists("/usr/local/opnsense/www/themes/{$themename}/build/images/default-logo.svg")) : ?>
          <img src="<?= cache_safe("/ui/themes/{$themename}/build/images/default-logo.svg") ?>" height="30" alt="logo" />
    <?php else : ?>
          <img src="<?= cache_safe("/ui/themes/{$themename}/build/images/default-logo.png") ?>" height="30" alt="logo" />
    <?php endif ?>
        </div>
      </header>

      <div class="login-modal-content">
        <div id="inputerrors" class="text-danger"><?= !empty($Login_Error) ? $Login_Error : '&nbsp;' ?></div><br />

            <form class="clearfix" id="iform" name="iform" method="post" autocomplete="off">

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
        <a target="_blank" href="<?=$g['product_website']?>"><?=$g['product_name']?></a> (c) <?=$g['product_copyright_years']?>
        <a target="_blank" href="<?=$g['product_copyright_url']?>"><?=$g['product_copyright_owner']?></a>
      </div>

    </div>

    </body>
  </html>
<?php } // end function
