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

class RS512Test extends \PHPUnit\Framework\TestCase
{
public static $pem_private = <<<DOC
-----BEGIN RSA PRIVATE KEY-----
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
-----END RSA PRIVATE KEY-----
DOC;

public static $pem_public = <<<DOC
-----BEGIN PUBLIC KEY-----
MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAvTV+hpSyVYGdvb6AMYiW
HmnxejKksbq8AfyTCEexPxkeEVEUEROAklNyIhfgTlbRREtNvRjYCiSc3mXZ5SFN
MprTj+IHPXemPBIv7IRh+TgnwW7zTVv750ywFUeXrLgtJwgKdJedCu/rZPKBDfnG
la9YLhIv68GrDLz58TmtaM5JDKwAIUHnTP6j2a4GjaZ6Z+Eq6qPgxOjW28dBSzJS
pPNEXs/Ut5OxCaGES9LXi42sWgEb14B/1+Spde70H7x4MVfAjmTmJ+K/kk1D5Z5x
TDeBvVl767VVCPrsF+fa+pMe79x8cSJbA9v2YlpMmJ3menUXQxmC1azOvISD4IMI
NaDXQeiu0dHP9CLBVziC0WIFgrazIx+ncgQycOzRhVMmnB2bWPIwae3QMTFKONfJ
TDJKGKl6FIEAU9tVPnYGf2VmE15zl8z6QVzW6felYaOXuwdL3+62rZNzlHFgP77v
90GmRV4cHaUb/EUkFCKLvMwNUUeFLuS0erQETq17oczF3uljrw5T42WtQTzNCpaG
rjPbRjB7QPqcJEG2LCXIIXPc0zdYZhHE7Ia2uvHGiNvShctMzgyn+sJVPiV0VhIU
b5EgedNCExBtKhsXkRWmmfhdSZ6oTJJHDMrtdZSoC5rW9ILVfmTKfvIzoT0bRNpB
0XLeB131AZWyuOChnCItOQcCAwEAAQ==
-----END PUBLIC KEY-----
DOC;
    public function testCreateAndVerify() {

        $rs512 = new RS512(RS512Test::$pem_private, RS512Test::$pem_public);

        $claims = array('iss' => 'OPNsense', 'sub' => 'Fabian');

        $jwt = $rs512->sign($claims);

        echo $jwt . PHP_EOL;

        $result = $rs512->parseToken($jwt);

        $this->assertTrue($result);
    }
    public function testCreateAndVerifyFails() {

        $rs512 = new RS512(RS512Test::$pem_private, RS512Test::$pem_public);

        $claims = array('iss' => 'OPNsense', 'sub' => 'Fabian');

        $jwt = $rs512->sign($claims);

        $result = $rs512->parseToken($jwt . "BREAK VERIFY");

        $this->assertFalse($result);
    }
}