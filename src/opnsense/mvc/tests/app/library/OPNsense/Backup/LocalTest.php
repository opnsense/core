<?php

namespace tests\OPNsense\Backup\Local;

class BackupLocalTest extends \PHPUnit\Framework\TestCase
{
    private $backup;

    protected function setUp(): void
    {
        $this->backup = new \OPNsense\Backup\Local();
    }

    public function testCurrentEncryptionAndDecryption()
    {
        $localBackup = new \OPNsense\Backup\Local();
        $encrypted = $this->backup->encrypt('test message 1', 'password 1');
        $this->assertStringContainsString('---- BEGIN config.xml ----', $encrypted);
        $this->assertEquals('test message 1', $this->backup->decrypt($encrypted, 'password 1'));
    }

    public function testWrongPassword()
    {
        $localBackup = new \OPNsense\Backup\Local();
        $encrypted = $localBackup->encrypt('test message 1', 'password 1');
        $this->assertNull($localBackup->decrypt($encrypted, 'password 2'));
    }

    /* Test older backup formats */

    public function testAes256CbcSha512()
    {
        /*
        echo -n "plain text" | openssl enc -aes-256-cbc -a -md sha512 -salt -pbkdf2 -iter 100000 -pass pass:'password'
        */
        $result = "---- BEGIN config.xml ----\n";
        $result .= "Version: 22.1\n";
        $result .= "Cipher: AES-256-CBC\n";
        $result .= "PBKDF2: 100000\n";
        $result .= "Hash: SHA512\n\n";
        $result .= "U2FsdGVkX19UgpJkIGNpAfGjOT4J3tUVmYojxYLPU3c=\n";
        $result .= "---- END config.xml ----\n";
        $this->assertEquals('plain text', $this->backup->decrypt($result, 'password'));
    }

    public function testAes256CbcMd5()
    {
        /*
        echo -n "plain text" | openssl enc -aes-256-cbc -a -md md5 -pass pass:'password'
        */
        $result = "---- BEGIN config.xml ----\n";
        $result .= "Version: 21.1\n";
        $result .= "Cipher: AES-256-CBC\n";
        $result .= "Hash: MD5\n\n";
        $result .= "U2FsdGVkX184SjUAQcAEabH4+AdRnDYwakiPmu4rRkY=\n";
        $result .= "---- END config.xml ----\n";
        $this->assertEquals('plain text', $this->backup->decrypt($result, 'password'));
    }
}
