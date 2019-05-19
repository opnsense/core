<?php
/*
 * Copyright (C) 2019 Fabian Franz
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

namespace  OPNsense\Auth\JWT;

class HS256Test extends \PHPUnit\Framework\TestCase
{
    public static $hmac_key = 'test';

    public function testCreateAndVerify() {

        $hs256 = new HS256(HS256Test::$hmac_key);

        $claims = array('iss' => 'OPNsense', 'sub' => 'Fabian');

        $jwt = $hs256->sign($claims);

        echo $jwt . PHP_EOL;

        $result = $hs256->parseToken($jwt);

        $this->assertTrue($result);
    }
    public function testCreateAndVerifyFails() {

        $hs256 = new HS256(HS256Test::$hmac_key);

        $claims = array('iss' => 'OPNsense', 'sub' => 'Fabian');

        $jwt = $hs256->sign($claims);

        $result = $hs256->parseToken($jwt . "BREAK VERIFY");

        $this->assertFalse($result);
    }
}