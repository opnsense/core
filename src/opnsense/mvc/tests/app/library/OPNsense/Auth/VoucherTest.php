<?php

namespace tests\OPNsense\Auth;

class VoucherTest extends \PHPUnit\Framework\TestCase
{
    private $generated;
    private $voucher;
    private $vouchergroup;

    protected function setUp(): void
    {
        $this->vouchergroup = 'testgroup-' . random_int(0, 1_000_000);
        $this->voucher = new \OPNsense\Auth\Voucher();
        $this->voucher->setProperties(array());
        $this->generated = $this->voucher->generateVouchers($this->vouchergroup, 100, 3600, 0);
    }

    protected function tearDown(): void
    {
        $this->voucher->dropVoucherGroup($this->vouchergroup);
    }

    public function testListVouchers(): void
    {
        $this->assertEquals(100, count($this->voucher->listVouchers($this->vouchergroup)));
    }

    /**
     * @depends testListVouchers
     */
    public function testAuthenticate(): void
    {
        $this->assertTrue($this->voucher->authenticate(
            $this->generated[0]['username'],
            $this->generated[0]['password']
        ));
        $this->assertFalse($this->voucher->authenticate(
            $this->generated[0]['username'],
            $this->generated[1]['password']
        ));
    }
}
