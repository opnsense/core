<?php

/*
 * Copyright (C) 2015-2021 Deciso B.V.
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

namespace OPNsense\Base;

use OPNsense\Core\Config;
use Phalcon\Mvc\Controller;
use Phalcon\Logger;
use Phalcon\Logger\Adapter\Syslog;
use Phalcon\Translate\InterpolatorFactory;
use OPNsense\Core\ACL;

/**
 * Class ControllerRoot wrap shared OPNsense controller features (auth, logging)
 * @package OPNsense\Base
 */
class ControllerRoot extends Controller
{
    /**
     * @var null|ViewTranslator translator to use
     */
    public $translator;


    /**
     * log handle
     */
    protected $logger = null;

    /**
     * @var null|string logged in username, populated during authentication
     */
    protected $logged_in_user = null;

    /**
     * Wrap close session, for long running operations.
     */
    protected function sessionClose()
    {
        session_write_close();
    }

    /**
     * set system language according to configuration
     */
    protected function setLang()
    {
        $config = Config::getInstance()->object();
        $lang = 'en_US';

        foreach ($config->system->children() as $key => $node) {
            if ($key == 'language') {
                $lang = (string)$node;
                break;
            }
        }

        if ($this->session->has('Username')) {
            $username = $this->session->get('Username');
            foreach ($config->system->user as $user) {
                if ($username == (string)$user->name && isset($user->language)) {
                    $lang = (string)$user->language;
                    break;
                }
            }
        }

        $locale = $lang . '.UTF-8';
        $interpolator = new InterpolatorFactory();
        $this->translator = new ViewTranslator($interpolator, array(
            'directory' => '/usr/local/share/locale',
            'defaultDomain' => 'OPNsense',
            'locale' => $locale,
        ));

        /* somehow this is not done by Phalcon */
        bind_textdomain_codeset('OPNsense', $locale);
        putenv('LANG=' . $locale);
    }

    /**
     * get system logger
     * @param string $ident syslog identifier
     * @return Syslog log handler
     */
    protected function getLogger($ident = "api")
    {
        if ($this->logger == null) {
            $this->logger = new Logger(
                'messages',
                [
                    'main' => new Syslog($ident, array(
                        'option' => LOG_PID,
                        'facility' => LOG_LOCAL4
                    ))
                ]
            );
        }
        return $this->logger;
    }

    /**
     * return logged-in username
     * @return string username
     */
    public function getUserName()
    {
        return $this->logged_in_user;
    }

    /**
     * perform authentication, redirect user on non successful auth
     * @return bool
     */
    public function doAuth()
    {
        $cnf = Config::getInstance()->object();
        if (!empty($cnf->system->webgui->session_timeout)) {
            $session_timeout = $cnf->system->webgui->session_timeout * 60;
        } else {
            $session_timeout = 14400;
        }
        $redirect_uri = "/?url=" . $_SERVER['REQUEST_URI'];
        if ($this->session->has("Username") == false) {
            // user unknown
            $this->getLogger()->error("no active session, user not found");
            $this->response->redirect($redirect_uri, true);
            $this->setLang();
            return false;
        } elseif (
            $this->session->has("last_access")
            && $this->session->get("last_access") < (time() - $session_timeout)
        ) {
            // session expired / cleanup session data
            $this->getLogger()->error("session expired");
            $this->session->remove("Username");
            $this->session->remove("last_access");
            $this->response->redirect($redirect_uri, true);
            $this->setLang();
            return false;
        }

        $this->setLang();

        $this->session->set("last_access", time());

        // Authorization using legacy acl structure
        $acl = new ACL();
        if (!$acl->isPageAccessible($this->session->get("Username"), $_SERVER['REQUEST_URI'])) {
            $this->getLogger()->error("uri " . $_SERVER['REQUEST_URI'] .
                " not accessible for user " . $this->session->get("Username"));
            $this->response->redirect("/", true);
            return false;
        }

        return true;
    }
}
