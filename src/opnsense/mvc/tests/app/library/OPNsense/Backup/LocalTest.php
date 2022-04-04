<?php

namespace OPNsense\Backup;

function shell_exec($s)
{
    return '0.0dev';
}

class BackupLocalTest extends \PHPUnit\Framework\TestCase
{
    private $backup;

    protected function setUp(): void
    {
        $this->backup = new \OPNsense\Backup\Local();
    }

    public function testCurrentEncryptionAndDecryption()
    {
        $encrypted = $this->backup->encrypt('test message 1', 'password 1');
        $this->assertStringContainsString('---- BEGIN config.xml ----', $encrypted);
        $this->assertEquals('test message 1', $this->backup->decrypt($encrypted, 'password 1'));
    }

    public function testWrongPassword()
    {
        $encrypted = $this->backup->encrypt('test message 1', 'password 1');
        $this->assertNull($this->backup->decrypt($encrypted, 'password 2'));
    }

    public function testMalformedData()
    {
        $this->assertNull($this->backup->decrypt('', 'password'));
        $this->assertNull($this->backup->decrypt(str_repeat('abcdef0123456789', 10000), 'password'));
    }

    /* Test older backup formats */

    public function testAes256CbcSha512()
    {
        /*
        echo -n "plain text" | openssl enc -aes-256-cbc -a -md sha512 -salt -pbkdf2 -iter 100000 -pass pass:'password'
        */
        $data = "---- BEGIN config.xml ----\n";
        $data .= "Version: 22.1\n";
        $data .= "Cipher: AES-256-CBC\n";
        $data .= "PBKDF2: 100000\n";
        $data .= "Hash: SHA512\n\n";
        $data .= "U2FsdGVkX19UgpJkIGNpAfGjOT4J3tUVmYojxYLPU3c=\n";
        $data .= "---- END config.xml ----\n";
        $this->assertEquals('plain text', $this->backup->decrypt($data, 'password'));
    }

    public function testAes256CbcMd5()
    {
        /*
        echo -n "plain text" | openssl enc -aes-256-cbc -a -md md5 -pass pass:'password'
        */
        $data = "---- BEGIN config.xml ----\n";
        $data .= "Version: 21.1\n";
        $data .= "Cipher: AES-256-CBC\n";
        $data .= "Hash: MD5\n\n";
        $data .= "U2FsdGVkX184SjUAQcAEabH4+AdRnDYwakiPmu4rRkY=\n";
        $data .= "---- END config.xml ----\n";
        $this->assertEquals('plain text', $this->backup->decrypt($data, 'password'));
    }
}
