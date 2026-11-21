<?php

namespace tests\OPNsense\Trust\Api;

// @CodingStandardsIgnoreStart
require_once __DIR__ . '/../../Base/ApiControllerTestCase.php';
// @CodingStandardsIgnoreEnd

use OPNsense\Base\UserException;
use OPNsense\Trust\Api\CertController;
use tests\OPNsense\Base\ApiControllerTestCase;

class CertControllerTest extends ApiControllerTestCase
{
    private const WEBGUI = '0d000000-0000-4000-8000-000000000001';
    private const SPARE = '0d000000-0000-4000-8000-000000000002';

    private function certNames(): array
    {
        $names = [];
        foreach ($this->savedConfig()->cert as $cert) {
            $names[] = (string)$cert->descr;
        }
        return $names;
    }

    public function testDeleteUnused()
    {
        $this->assertEquals(
            ['result' => 'deleted'],
            $this->callAction(CertController::class, 'delAction', [self::SPARE])
        );
        $this->assertEquals(['webgui'], $this->certNames());
    }

    public function testDeleteInUseRefused()
    {
        try {
            $this->callAction(CertController::class, 'delAction', [self::WEBGUI]);
            $this->fail('delete was not refused');
        } catch (UserException $e) {
            $this->assertStringContainsString('{system.webgui}', $e->getMessage());
        }
        $this->assertEquals(['webgui', 'spare'], $this->certNames());
    }

    public function testDeleteNeedsPost()
    {
        $this->assertEquals(
            ['status' => 'failed'],
            $this->callAction(CertController::class, 'delAction', [self::SPARE], [], 'GET')
        );
        $this->assertEquals(['webgui', 'spare'], $this->certNames());
    }
}
