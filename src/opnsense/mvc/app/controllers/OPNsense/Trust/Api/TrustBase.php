<?php

/**
 *    Copyright (C) 2014-2015 Deciso B.V.
 *    Copyright (C) 2008 Shrew Soft Inc. <mgrooms@shrew.net>
 *    Copyright (C) 2017 Smart-Soft
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace OPNsense\Trust\Api;

use \OPNsense\Base\ApiControllerBase;

/**
 * Base class for trust management
 * Class TrustBase
 * @package OPNsense\Trust\Api
 */
class TrustBase extends ApiControllerBase
{
    /**
     * keylen dropdown
     * @var array
     */
    protected $keylens = [
        "512" => ["value" => "512", "selected" => "0"],
        "1024" => ["value" => "1024", "selected" => "0"],
        "2048" => ["value" => "2048", "selected" => "1"],
        "4096" => ["value" => "4096", "selected" => "0"],
        "8192" => ["value" => "8192", "selected" => "0"]
    ];

    /**
     * digest dropdown
     * @var array
     */
    protected $digest_algs = [
        "sha1" => ["value" => "SHA1", "selected" => "0"],
        "sha224" => ["value" => "SHA224", "selected" => "0"],
        "sha256" => ["value" => "SHA256", "selected" => "1"],
        "sha384" => ["value" => "SHA384", "selected" => "0"],
        "sha512" => ["value" => "SHA512", "selected" => "0"]
    ];

    /**
     * Validation input strings
     * @param $isset_fields
     * @param $invalid_fields
     * @param $post
     * @param $method
     * @return array
     */
    protected function Validation($isset_fields, $invalid_fields, $post, $method)
    {
        // Validation
        $result = ["result" => "failed", "validations" => []];
        foreach ($isset_fields as $field) {
            /* check for bad control characters */
            if (is_string($post[$field]) && preg_match("/[\\x00-\\x08\\x0b\\x0c\\x0e-\\x1f]/", $post[$field])) {
                $result["validations"]["{$method}.{$field}"] = gettext("The field contains invalid characters.");
            }
            if (!isset($post[$field]) || empty($post[$field])) {
                $result["validations"]["{$method}.{$field}"] = gettext("The field is required.");
            }
        }
        foreach ($invalid_fields as $field) {
            if (preg_match("/[\!\@\#\$\%\^\(\)\~\?\>\<\&\/\\\,\.\"\']/", $post[$field])) {
                $result["validations"]["{$method}.{$field}"] = gettext("The field contains invalid characters.");
            }
        }
        if (isset($post["dn_email"]) && preg_match("/[\!\#\$\%\^\(\)\~\?\>\<\&\/\\\,\"\']/", $post["dn_email"])) {
            $result["validations"]["{$method}.dn_email"] = gettext("The field 'Distinguished name Email Address' contains invalid characters.");
        }
        if (isset($post["dn_commonname"]) && preg_match("/[\!\@\#\$\%\^\(\)\~\?\>\<\&\/\\\,\"\']/", $post["dn_commonname"])) {
            $result["validations"]["{$method}.dn_commonname"] = gettext("The field 'Distinguished name Common Name' contains invalid characters.");
        }
        if (isset($post["keylen"]) && !in_array($post["keylen"], array_keys($this->keylens))) {
            $result["validations"]["{$method}.keylen"] = gettext("Please select a valid Key Length.");
        }
        if (isset($post["digest_alg"]) && !in_array($post["digest_alg"], array_keys($this->digest_algs))) {
            $result["validations"]["{$method}.digest_alg"] = gettext("Please select a valid Digest Algorithm.");
        }
        return $result;
    }
}