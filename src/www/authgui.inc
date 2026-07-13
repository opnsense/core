<?php

/*
 * Copyright (C) 2025 Deciso B.V.
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

/* seconds a user has to complete the second (one time password) login step */
define('WEBGUI_OTP_STEP_TIMEOUT', 240);

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

    putenv('LANG=' . $lang_encoding);
    putenv('LANGUAGE=' . $lang_encoding);
    putenv('LC_ALL=' . $lang_encoding);
    setlocale(LC_ALL, $lang_encoding);

    $textdomain = 'OPNsense';

    textdomain($textdomain);
    bindtextdomain($textdomain, '/usr/local/share/locale');
    bind_textdomain_codeset($textdomain, $lang_encoding);
}

/* DNS ReBinding attack prevention, return true when rebind detected*/
function check_security_dns_rebind()
{
    global $config;
    if (!isset($config['system']['webgui']['nodnsrebindcheck'])) {
        /* either an IPv6 address with or without an alternate port */
        if (strstr($_SERVER['HTTP_HOST'], "]")) {
            $http_host_port = explode("]", $_SERVER['HTTP_HOST']);
            /* v6 address has more parts, drop the last part */
            if (count($http_host_port) > 1) {
                array_pop($http_host_port);
                $http_host = str_replace(["[", "]"], "", implode(":", $http_host_port));
            } else {
                $http_host = str_replace(["[", "]"], "", implode(":", $http_host_port));
            }
        } else {
            $http_host = explode(":", $_SERVER['HTTP_HOST']);
            $http_host = $http_host[0];
        }
        $this_host = [
            $config['system']['hostname'] . "." . $config['system']['domain'],
            $config['system']['hostname'],
            "localhost"
        ];
        if (!empty($config['system']['webgui']['althostnames'])) {
            $this_host = array_merge($this_host, explode(" ", $config['system']['webgui']['althostnames']));
        }
        if (is_ipaddr($http_host) || in_array($_SERVER['SERVER_ADDR'], ["127.0.0.1", "::1"])) {
            return false;
        } elseif (in_array(strtolower($http_host), array_map('strtolower', $this_host))) {
            return false;
        }
        return true;
    }
    return false;
}

/* HTTP referer detection, return true when being forwarded from an unknown referer*/
function check_security_http_referer_enforcement()
{
    global $config;
    if (!isset($config['system']['webgui']['nohttpreferercheck']) && isset($_SERVER['HTTP_REFERER'])) {
        $referrer_host = str_replace(["[", "]"], "", parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST));
        $this_host = [$config['system']['hostname'] . "." . $config['system']['domain'], $config['system']['hostname']];
        if (!empty($config['system']['webgui']['althostnames'])) {
            $this_host = array_merge($this_host, explode(" ", $config['system']['webgui']['althostnames']));
        }
        if ($referrer_host) {
            if (in_array(strtolower($referrer_host), array_map('strtolower', $this_host))) {
                return false;
            } elseif (isAuthLocalIP($referrer_host)) {
                return false;
            } elseif ($referrer_host == "127.0.0.1" || $referrer_host == "localhost") {
                // allow SSH port forwarded connections and links from localhost
                return false;
            }
        }
        return true;
    }
    return false;
}

function session_auth(&$Login_Error = null, &$Login_OTP = false)
{
    global $config;

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
        session_set_cookie_params(["SameSite" => "Lax"]);
        session_start();
    }

    // Detect protocol change
    if (!isset($_POST['login']) && !empty($_SESSION['Username']) && $_SESSION['protocol'] != $config['system']['webgui']['protocol']) {
        session_write_close();
        return false;
    }
    // check additional security measures
    if (empty($_SESSION['Username'])) {
        if (check_security_dns_rebind()) {
            display_error_form(sprintf(gettext('A potential %sDNS Rebind attack%s has been detected.%sTry to access the router by IP address instead of by hostname. You can disable this check if needed under System: Settings: Administration.'), '<a href="http://en.wikipedia.org/wiki/DNS_rebinding">', '</a>', '<br />'));
            exit;
        } elseif (check_security_http_referer_enforcement()) {
            display_error_form(sprintf(
                gettext('The HTTP_REFERER "%s" does not match the predefined settings. You can disable this check if needed under System: Settings: Administration.'),
                html_safe($_SERVER['HTTP_REFERER'])
            ));
            exit;
        }
    }

    $authFactory = new \OPNsense\Auth\AuthenticationFactory();
    $username = null;
    $password = null;
    $otp_code = null;
    $is_authenticated = false;
    $shouldChangePassword = false;

    if (isset($_POST['login']) && !empty($_POST['usernamefld']) && !empty($_POST['passwordfld'])) {
        $username = $_POST['usernamefld'];
        $password = $_POST['passwordfld'];
        if ($authFactory->authenticateStep1("WebGui", $username, $password)) {
            // First factor (password) is valid!
            $otp_authname = $authFactory->findOTPAuthenticator("WebGui", $username);
            if ($otp_authname !== null) {
                // User has OTP configured. Defer login to collect OTP.
                // We do NOT stash the password in the session! The nonce is echoed back by the
                // token form so only the party which passed step 1 can offer a token, the
                // authenticator name binds the token check to the server found in this step.
                session_regenerate_id();
                $otp_nonce = bin2hex(random_bytes(16));
                $_SESSION['login_otp_pending'] = [
                    'username' => $username,
                    'authname' => $otp_authname,
                    'nonce' => $otp_nonce,
                    'shouldChangePassword' => $authFactory->shouldChangePassword($authFactory->lastUsedAuth, $username, $password),
                    'expires' => time() + WEBGUI_OTP_STEP_TIMEOUT,
                ];
                session_write_close();
                $Login_OTP = $otp_nonce;
                return false;
            } else {
                // User does not use OTP. Immediately authenticated.
                $is_authenticated = true;
                $shouldChangePassword = $authFactory->shouldChangePassword($authFactory->lastUsedAuth, $username, $password);
            }
        } else {
            // Password invalid. Immediate failure.
            auth_log("Web GUI authentication error for '{$username}' from {$_SERVER['REMOTE_ADDR']}");
            $Login_Error = gettext('Wrong username or password.');
            return false;
        }
    } elseif (isset($_POST['login_otp'])) {
        /* second login step, collect OTP and authenticate using stashed username */
        $pending = !empty($_SESSION['login_otp_pending']) ? $_SESSION['login_otp_pending'] : null;
        unset($_SESSION['login_otp_pending']);
        $otp_nonce = isset($_POST['otp_nonce']) ? (string)$_POST['otp_nonce'] : '';
        if (
            !empty($pending) && !empty($pending['authname']) && $pending['expires'] >= time() &&
            !empty($pending['nonce']) && hash_equals($pending['nonce'], $otp_nonce)
        ) {
            $username = $pending['username'];
            $otp_code = isset($_POST['otp_code']) ? $_POST['otp_code'] : '';
            if ($authFactory->authenticateStep2("WebGui", $username, $otp_code, $pending['authname'])) {
                $is_authenticated = true;
                $shouldChangePassword = !empty($pending['shouldChangePassword']);
            } else {
                auth_log("Web GUI authentication error for '{$username}' from {$_SERVER['REMOTE_ADDR']}");
                $Login_Error = gettext('Wrong username or password.');
                return false;
            }
        } else {
            $Login_Error = gettext('Login session expired, please try again.');
            return false;
        }
    } elseif (!empty($_SESSION['login_otp_pending'])) {
        /* any other request while a token was pending restarts the login sequence */
        unset($_SESSION['login_otp_pending']);
    }

    /* Validate incoming login request */
    if ($is_authenticated) {
        $authenticator = $authFactory->lastUsedAuth;
        // Generate a new id to avoid session fixation, drop the previous session as it may
        // still contain stashed credentials from the token collection step
        session_regenerate_id(true);
        $_SESSION['Username'] = $authenticator->getUserName($username);
        $_SESSION['last_access'] = time();
        $_SESSION['protocol'] = $config['system']['webgui']['protocol'];
        if ($shouldChangePassword) {
            $_SESSION['user_shouldChangePassword'] = true;
        }
        if (!isset($config['system']['webgui']['quietlogin'])) {
            auth_log(sprintf("Successful login for user '%s' from: %s", $username, $_SERVER['REMOTE_ADDR']), LOG_NOTICE);
        }
        if (!empty($_GET['url'])) {
            $uri = (new OPNsense\Core\SanitizeFilter())->sanitize($_GET['url'], 'local_uri');
            if (!empty($uri)) {
                /**
                 * Intentionally kept url_safe(), although vsprintf isn't a safety measure here.
                 * ref https://github.com/opnsense/core/issues/1168
                 */
                header(url_safe("Location: {$uri}"));
            }
        } elseif (!empty($_SESSION['user_shouldChangePassword'])) {
            header("Location: /ui/user_portal");
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
            auth_log(sprintf("Session timed out for user '%s' from: %s", $_SESSION['Username'], $_SERVER['REMOTE_ADDR']), LOG_NOTICE);
        } else {
            auth_log(sprintf("User logged out for user '%s' from: %s", $_SESSION['Username'], $_SERVER['REMOTE_ADDR']), LOG_NOTICE);
        }

        /* wipe out $_SESSION */
        $_SESSION = array();

        if (isset($_COOKIE[session_name()])) {
            $secure = $config['system']['webgui']['protocol'] == "https";
            setcookie(session_name(), '', time() - 42000, '/', '', $secure, true);
        }

        /* and destroy it */
        session_destroy();

        header(url_safe("Location: /"));
        exit;
    }

    session_write_close();
    return true;
}

/* Authenticate user - exit if failed */
if (!session_auth($Login_Error, $Login_OTP)) {
    set_language();
    if (empty($Login_Error) && !$Login_OTP && !empty($_POST['usernamefld'])) {
        /* incomplete credentials posted (e.g. missing password) */
        $Login_Error = gettext('Wrong username or password.');
    }
    display_login_form($Login_Error, $Login_OTP);
    exit;
}

set_language();

/*
 * redirect to first allowed page if requesting a wrong url
 */
if ($_SERVER['REQUEST_URI'] == '/') {
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
        $username = empty($_SESSION['Username']) ? '(unknown)' : $_SESSION['Username'];
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $username .= '@' . $_SERVER['REMOTE_ADDR'];
        }
        log_msg("{$username} attempted to access {$_SERVER['REQUEST_URI']} but does not have access to that page. Redirecting to {$page}.");
        header(url_safe("Location: /{$page}"));
        exit;
    } else {
        display_error_form(gettext('No page assigned to this user! Click here to logout.'));
        exit;
    }
}

/*
 * determine if the user is allowed access to the requested page
 */
function display_error_form($text)
{
    $product = product::getInstance();

    ?><!doctype html>
<html lang="<?= get_current_lang() ?>" class="no-js">
  <head>

    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <meta name="robots" content="noindex, nofollow" />
    <meta name="keywords" content="" />
    <meta name="description" content="" />
    <meta name="copyright" content="" />
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1" />
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">

    <title><?= gettext('Error') ?> | <?= $product->name() ?></title>

    <link href="<?= cache_safe(get_themed_filename('/css/main.css')) ?>" rel="stylesheet">
    <link href="<?= cache_safe(get_themed_filename('/images/favicon.png')) ?>" rel="shortcut icon">

    <script src="/ui/js/jquery-3.5.1.min.js"></script>

  </head>
  <body class="page-login">
    <div id=container">
      <p>&nbsp;</p>
      <p style="text-align: center;">
        <a href="/index.php?logout"><?= $text ?></a>
      </p>
    </div>
  </body>
</html>
<?php }

function display_login_form($Login_Error, $Login_OTP = false)
{
    global $config;

    $product = product::getInstance();

    setcookie("cookie_test", bin2hex(random_bytes(16)), time() + 3600, '/', '', $config['system']['webgui']['protocol'] == 'https', true);
    $have_cookies = isset($_COOKIE["cookie_test"]);
    ?><!doctype html>
<html lang="<?= get_current_lang() ?>" class="no-js">
  <head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <meta name="robots" content="noindex, nofollow" />
    <meta name="keywords" content="" />
    <meta name="description" content="" />
    <meta name="copyright" content="" />
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1" />
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">

    <title><?= gettext('Login') ?> | <?= $product->name() ?></title>

    <link href="<?= cache_safe(get_themed_filename('/css/main.css')) ?>" rel="stylesheet">
    <link href="<?= cache_safe(get_themed_filename('/images/favicon.png')) ?>" rel="shortcut icon">

    <script src="/ui/js/jquery-3.5.1.min.js"></script>

    <?php if (get_themed_filename('/js/theme.js', true)) : ?>
    <script src="<?= cache_safe(get_themed_filename('/js/theme.js')) ?>"></script>
    <?php endif ?>

  </head>
  <body class="page-login">

  <div class="container">
    <main class="login-modal-container">
      <header class="login-modal-head" style="height:50px;">
        <div class="navbar-brand">
    <?php if (get_themed_filename('/images/default-logo.svg', true)) : ?>
          <img src="<?= cache_safe(get_themed_filename('/images/default-logo.svg')) ?>" height="30" alt="logo" />
    <?php else : ?>
          <img src="<?= cache_safe(get_themed_filename('/images/default-logo.png')) ?>" height="30" alt="logo" />
    <?php endif ?>
        </div>
      </header>

      <div class="login-modal-content">
        <div id="inputerrors" class="text-danger"><?= !empty($Login_Error) ? $Login_Error : '&nbsp;' ?></div><br />

            <form class="clearfix" id="iform" name="iform" method="post" autocomplete="off">

<?php    if ($Login_OTP): ?>
        <div class="form-group">
          <label for="otp_code"><?=gettext("One time password:"); ?></label>
          <input id="otp_code" type="text" name="otp_code" class="form-control" tabindex="1" autofocus="autofocus" autocomplete="one-time-code" inputmode="numeric" autocapitalize="off" autocorrect="off" />
          <span class="help-block"><?=gettext("Enter the one-time password from your authenticator."); ?></span>
        </div>

        <input type="hidden" name="otp_nonce" value="<?=html_safe($Login_OTP); ?>" />
        <button type="submit" name="login_otp" value="1" class="btn btn-primary pull-right"><?=gettext("Continue"); ?></button>
<?php    else: ?>
        <div class="form-group">
          <label for="usernamefld"><?=gettext("Username:"); ?></label>
          <input id="usernamefld" type="text" name="usernamefld" class="form-control user" tabindex="1" autofocus="autofocus" autocapitalize="off" autocorrect="off" />
        </div>

        <div class="form-group">
          <label for="passwordfld"><?=gettext("Password:"); ?></label>
          <input id="passwordfld" type="password" name="passwordfld" class="form-control pwd" tabindex="2" />
        </div>

        <button type="submit" name="login" value="1" class="btn btn-primary pull-right"><?=gettext("Login"); ?></button>
<?php    endif ?>

      </form>

<?php    if (!$Login_OTP): ?>
<?php    foreach ((new \OPNsense\Auth\AuthenticationFactory())->listSSOproviders('WebGui') as $provider):?>
<div class="login-sso-link-container">
    <?=$provider->renderLink();?>
</div>
<?php    endforeach;?>
<?php    endif ?>

      <?php if (!$have_cookies && isset($_POST['login'])) : ?>
        <br /><br />
        <span class="text-danger">
            <?= gettext("Your browser must support cookies to login."); ?>
        </span>
      <?php endif; ?>

          </div>

      </main>
      <div class="login-foot text-center">
        <a target="_blank" href="<?= $product->website() ?>"><?= $product->name() ?></a> (c) <?= $product->copyright_years() ?>
        <a target="_blank" href="<?= $product->copyright_url() ?>"><?= $product->copyright_owner() ?></a>
      </div>

    </div>

    </body>
  </html>
<?php }
