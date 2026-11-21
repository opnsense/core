<?php

namespace tests\OPNsense\Trust\Api;

// @CodingStandardsIgnoreStart
require_once __DIR__ . '/../../Base/ApiControllerTestCase.php';
// @CodingStandardsIgnoreEnd

use OPNsense\Base\UserException;
use OPNsense\Trust\Api\CaController;
use tests\OPNsense\Base\ApiControllerTestCase;

class CaControllerTest extends ApiControllerTestCase
{
    private const ROOT_A = '0a000000-0000-4000-8000-000000000001';
    private const INTER_B = '0a000000-0000-4000-8000-000000000002';
    private const SPARE_C = '0a000000-0000-4000-8000-000000000003';
    private const CRL_D = '0a000000-0000-4000-8000-000000000004';
    private const ROOT_E = '0a000000-0000-4000-8000-000000000005';
    private const INTER_F = '0a000000-0000-4000-8000-000000000006';
    private const NOREF_G = '0a000000-0000-4000-8000-000000000007';
    private const SWAN_H = '0a000000-0000-4000-8000-000000000008';

    private function caNames(): array
    {
        $names = [];
        foreach ($this->savedConfig()->ca as $ca) {
            $names[] = (string)$ca->descr;
        }
        return $names;
    }

    private function assertDeleteRefused(string $uuids, string $message): void
    {
        $before = $this->caNames();
        try {
            $this->callAction(CaController::class, 'delAction', [$uuids]);
            $this->fail('delete was not refused');
        } catch (UserException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
        $this->assertEquals($before, $this->caNames());
    }

    public function testDeleteUnused()
    {
        $this->assertEquals(
            ['result' => 'deleted'],
            $this->callAction(CaController::class, 'delAction', [self::SPARE_C])
        );
        $this->assertNotContains('spareC', $this->caNames());
    }

    public function testDeleteRefusedWhenItIssuedACa()
    {
        $this->assertDeleteRefused(self::ROOT_A, 'Cannot delete "rootA", it issued CA "interB".');
    }

    public function testDeleteRefusedWhenItIssuedACertificate()
    {
        $this->assertDeleteRefused(self::INTER_B, 'cert - leafB {cert}');
    }

    public function testDeleteRefusedWithItsCrl()
    {
        $this->assertDeleteRefused(self::CRL_D, 'crl - crlD {crl}');
    }

    public function testDeleteRefusedWhenUsedByAModel()
    {
        $this->assertDeleteRefused(
            self::SWAN_H,
            'Swanctl - remH {Swanctl.remotes.remote.0c000000-0000-4000-8000-000000000002}'
        );
    }

    public function testBulkDeleteChecksEveryCa()
    {
        $this->assertDeleteRefused(self::SPARE_C . ',' . self::INTER_B, 'cert - leafB {cert}');
    }

    public function testBulkDeleteWithTheCaItIssued()
    {
        $this->callAction(CaController::class, 'delAction', [self::ROOT_E . ',' . self::INTER_F]);
        $this->assertEmpty(array_intersect(['rootE', 'interF'], $this->caNames()));
    }

    public function testDeleteWithEmptyRefid()
    {
        $this->callAction(CaController::class, 'delAction', [self::NOREF_G]);
        $this->assertNotContains('norefG', $this->caNames());
    }
}
