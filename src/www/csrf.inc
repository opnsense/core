<?php
/*
    Copyright (C) 2017 Deciso B.V.
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
class LegacyCSRF
{
    private $securityToken = null;
    private $securityTokenKey = null;
    private $di = null;
    private $security = null;
    private $session = null;
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
        $this->Session();
        // do not destroy token after successfull validation, some pages use ajax type requests
        $this->securityTokenKey = $this->security->getSessionToken() ;
        $this->securityToken = !empty($_POST[$tokenKey]) ? $_POST[$tokenKey] : "";
        // todo: add header check for ajax requests
        $result = $this->security->checkToken($this->securityToken, $this->securityTokenKey, false);
        // close session after validation
        session_write_close();
        return $result;
    }

    private function newToken()
    {
        $this->Session();
        if ($this->securityToken == null) {
            $this->securityToken = $this->security->getToken();
            $this->securityTokenKey = $this->security->getTokenKey();
        }
        return array('token'=>$this->securityToken, 'key' => $this->securityTokenKey);
    }

    public function csrfRewriteHandler($buffer)
    {
        $csrf = $this->newToken();
        $inputtag = "<input type=\"hidden\" id=\"__opnsense_csrf\" name=\"{$csrf['key']}\" value=\"{$csrf['token']}\"\/>";
        $buffer = preg_replace('#(<form[^>]*method\s*=\s*["\']post["\'][^>]*>)#i', '$1' . $inputtag, $buffer);
        // todo, add ajax handler :
        // <script type="text/javascript">
        // $( document ).ready(function() {
        //         $.ajaxSetup({
        //     'beforeSend': function(xhr) {
        //         xhr.setRequestHeader("X-CSRFToken", "{{ csrf_token }}" );
        //         xhr.setRequestHeader("X-CSRFTokenKey", "{{ csrf_tokenKey }}" );
        //     }
        // });
        // </script>


        return $buffer;
    }

}
$LegacyCSRFObject = new LegacyCSRF();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$LegacyCSRFObject->checkToken()) {
        header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden');
        echo "<html><head><title>CSRF check failed</title></head>
            <body>
            <p>CSRF check failed. Your form session may have expired, or you may not have
            cookies enabled.</p>
            </body></html>
        ";
        die;
    }
}
