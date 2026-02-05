<?php

/*
 * Copyright (C) 2026 Deciso B.V.
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
 *  THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *  INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *  AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *  AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *  OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *  SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *  INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *  CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *  POSSIBILITY OF SUCH DAMAGE.
 */

namespace tests\OPNsense\Core;

use OPNsense\Core\Shell;

class ShellTest extends \PHPUnit\Framework\TestCase
{

    /**
     * test construct
     */
    public function testit()
    {
	$fail = '/usr/bin/true';
        $simple = '/this/cmd';
        $normal = '/this/cmd %s %% foo';
        $normal_a = ['/this/cmd', '%s', '%%', 'foo'];
        $normal_r1 = "/this/cmd 'ok' % foo";
        $normal_r2 = "/this/cmd '' % foo";
        $normal_r3 = "/this/cmd '0' % foo";
        $complex = '/this/cmd %s%%%s foo %s';
        $complex_r1 = "/this/cmd '1'%'1' foo '1.01'";
        $complex_r2 = "/this/cmd ';rmfall '\'''\'''%'#comment\"' foo '&& echo hello'";
        $unsupported = '/this/cmd %d';

        $this->assertEquals(Shell::exec_safe([]), '');
        $this->assertEquals(Shell::exec_safe([], []), '');
        $this->assertEquals(Shell::exec_safe(''), '');
        $this->assertEquals(Shell::exec_safe('', ''), $fail);

        $this->assertEquals(Shell::exec_safe($fail), $fail);

        $this->assertEquals(Shell::exec_safe($simple), $simple);
        $this->assertEquals(Shell::exec_safe($simple, []), $simple);
        $this->assertEquals(Shell::exec_safe($simple, ['fail']), $fail);
        $this->assertEquals(Shell::exec_safe($simple, ['fail', 'too']), $fail);

        $this->assertEquals(Shell::exec_safe($normal), $fail);
        $this->assertEquals(Shell::exec_safe($normal, 'ok'), $normal_r1);
        $this->assertEquals(Shell::exec_safe($normal, ['ok']), $normal_r1);
        $this->assertEquals(Shell::exec_safe($normal, [null]), $normal_r2);
        $this->assertEquals(Shell::exec_safe($normal, ['']), $normal_r2);
        $this->assertEquals(Shell::exec_safe($normal, [0]), $normal_r3);
        $this->assertEquals(Shell::exec_safe($normal, ['ok', 'not']), $fail);
        $this->assertEquals(Shell::exec_safe($normal_a), $fail);
        $this->assertEquals(Shell::exec_safe($normal_a, 'ok'), $normal_r1);
        $this->assertEquals(Shell::exec_safe($normal_a, ['ok']), $normal_r1);
        $this->assertEquals(Shell::exec_safe($normal_a, ['ok', 'not']), $fail);

        $this->assertEquals(Shell::exec_safe($complex, ['1', 1, 1.01]), $complex_r1);
        $this->assertEquals(Shell::exec_safe($complex, [';rmfall \'\'', '#comment"', '&& echo hello']), $complex_r2);

        $this->assertEquals(Shell::exec_safe($unsupported), $fail);
        $this->assertEquals(Shell::exec_safe($unsupported, 'foo'), $fail);
        $this->assertEquals(Shell::exec_safe($unsupported, 1), $fail);
    }
}
