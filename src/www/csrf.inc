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
        $this->di = new \Phalcon\DI\FactoryDefault();
        $this->security = new Phalcon\Security();
        $this->security->setDi($this->di);
        // register rewrite handler
        ob_start(array($this,'csrfRewriteHandler'), 5242880);
    }

    private function Session()
    {
        global $config;
        if ($this->session == null) {
            $this->session = new Phalcon\Session\Adapter\Files();
            $this->session->start();
            $secure = $config['system']['webgui']['protocol'] == 'https';
            setcookie(session_name(), session_id(), null, '/', null, $secure, true);
            $this->di->setShared('session', $this->session);
        }
    }

    public function checkToken()
    {
        $result = false; // default, not valid
        $this->Session();
        $securityTokenKey = $_SESSION['$PHALCON/CSRF/KEY$'];
        if (empty($_POST[$securityTokenKey])) {
            if (!empty($_SERVER['HTTP_X_CSRFTOKEN'])) {
                $result = $this->security->checkToken(null, $_SERVER['HTTP_X_CSRFTOKEN'], false);
            }
        } else {
            $result = $this->security->checkToken($securityTokenKey, $_POST[$securityTokenKey], false);
        }
        // close session after validation
        session_write_close();
        return $result;
    }

    private function newToken()
    {
        $this->Session();
        // only request new token when session has none
        $securityTokenKey = $_SESSION['$PHALCON/CSRF/KEY$'];
        $securityToken = $_SESSION['$PHALCON/CSRF$'];
        if (empty($securityToken) || empty($securityTokenKey)) {
            $securityToken = $this->security->getToken();
            $securityTokenKey = $this->security->getTokenKey();
        }
        return array('token'=>$securityToken, 'key' => $securityTokenKey);
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
