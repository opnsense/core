<?php

/*
 * Copyright (C) 2017 Deciso B.V.
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

class LegacyCSRF
{
    private $di = null;
    private $security = null;
    private $session = null;
    private $is_html_output = false;
    public function __construct()
    {
        global $config;
        // register rewrite handler
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
            $secure = $config['system']['webgui']['protocol'] == 'https';
            setcookie(session_name(), session_id(), null, '/', null, $secure, true);
        }
        ob_start(array($this,'csrfRewriteHandler'), 5242880);
    }

    public function checkToken()
    {
        $result = false; // default, not valid
        $securityTokenKey = $_SESSION['$PHALCON/CSRF/KEY$'];
        if (empty($_POST[$securityTokenKey])) {
            if (!empty($_SERVER['HTTP_X_CSRFTOKEN'])) {
                $result = $_SERVER['HTTP_X_CSRFTOKEN'] == $_SESSION['$PHALCON/CSRF$'];
            }
        } else {
            $result = $_POST[$securityTokenKey] == $_SESSION['$PHALCON/CSRF$'];
        }
        // close session after validation
        session_write_close();
        return $result;
    }

    private function newToken()
    {
        $random = new \Phalcon\Security\Random();
        // only request new token when session has none
        if (empty($_SESSION['$PHALCON/CSRF/KEY$']) || empty($_SESSION['$PHALCON/CSRF$'])) {
            $_SESSION['$PHALCON/CSRF$'] = $random->base64Safe(16);
            $_SESSION['$PHALCON/CSRF/KEY$'] = $random->base64Safe(16);
        }
        return array('token' => $_SESSION['$PHALCON/CSRF$'], 'key' => $_SESSION['$PHALCON/CSRF/KEY$']);
    }

    public function csrfRewriteHandler($buffer)
    {
        // quick check if output looks like html, don't rewrite other document types
        if (stripos($buffer, '<html') !== false) {
            $this->is_html_output = true;
        }
        if ($this->is_html_output) {
            $csrf = $this->newToken();
            $inputtag = "<input type=\"hidden\" name=\"{$csrf['key']}\" value=\"{$csrf['token']}\" autocomplete=\"new-password\" />";
            $buffer = preg_replace('#(<form[^>]*method\s*=\s*["\']post["\'][^>]*>)#i', '$1' . $inputtag, $buffer);
            // csrf token for Ajax type requests
            $script = "
            <script>
              $( document ).ready(function() {
                  $.ajaxSetup({
                  'beforeSend': function(xhr) {
                      xhr.setRequestHeader(\"X-CSRFToken\", \"{$csrf['token']}\" );
                  }
                });
              });
            </script>
            ";
            $buffer = str_ireplace('</head>', $script.'</head>', $buffer);
        }
        return $buffer;
    }
}

$LegacyCSRFObject = new LegacyCSRF();


if ($_SERVER['REQUEST_METHOD'] !== 'GET' && !$LegacyCSRFObject->checkToken()) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden');
    echo sprintf("<html><head><title>%s</title></head>
                  <body>
                  <p>%s</p>
                  </body></html>",
                  gettext('CSRF check failed'),
                  gettext('CSRF check failed. Your form session may have expired, or you may not have cookies enabled.')
                );
    die;
}
