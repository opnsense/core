<?php

/*
 * Copyright (C) 2025 Deciso B.V.
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

namespace OPNsense\Auth\SSOProviders;

class Provider
{
    public readonly string $id;             /* unique id for this provider */
    public readonly string $appcode;        /* short app code */
    public readonly string $name;           /* the name of this provider */
    public readonly string $login_uri;      /* (default) link to start SSO procedure */
    public readonly string $service;        /* service this provider belongs to */
    public readonly string $html_content;   /* optional html content to render for "login using" phrase*/

    /**
     * load properties of this object on construct
     */
    public function __construct(array $props)
    {
        foreach (get_class_vars(get_class($this)) as $key => $value) {
            if (isset($props[$key])) {
                $this->$key = $props[$key];
            } else {
                $this->$key = '';
            }
        }
    }

    /**
     *  @return array this providers settings as array
     */
    public function asArray(): array
    {
        $result = ['type' => 'sso'];
        foreach (get_class_vars(get_class($this)) as $key => $value) {
            $result[$key] = $this->$key;
        }
        return $result;
    }

    /**
     * @return string html content to use to render login link
     */
    public function renderLink()
    {
        if (!empty($this->html_content)) {
            return $this->html_content;
        } else {
            return sprintf(
                gettext("Login using <a href='%s'>%s</a>"),
                $this->login_uri,
                $this->name
            );
        }
    }
}
