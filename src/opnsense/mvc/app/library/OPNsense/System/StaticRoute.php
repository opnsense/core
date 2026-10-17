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

namespace OPNsense\System;

use OPNsense\Core\FileObject;
use OPNsense\Core\Shell;

/**
 * Wrapper class around static routes and the system representation of them.
 * Needs a trampoline for part of the functionality (root access)
 */
class StaticRoute
{
    /**
     * In progress queue
     */
    private static $todo_file = '/tmp/.static_routes.todo';

    /**
     * When changing or removing networks via the user-interface we need to keep track of them
     * for simplicity we assume that a network touched via the UI is owned by it.
     *
     * This in practice means if we're adding a static network which also has an anchor via an advanced routing
     * or vpn deamon, we will cleanup those routes and install ours in place.
     *
     * In theory we could add a "safety fence" here by tagging all our routes with RTF_PROTO1 or RTF_PROTO2, but
     * that's also not a 100% guarantee. Previous implementations of our static routing code behaved simalarly.
     *
     * @param string $network network we claim ownership
     */
    static public function claim($network)
    {
        $fobj = new FileObject(self::$todo_file, 'a+', 0600, LOCK_EX, 'wwwonly:wheel');
        $data = $fobj->readJson() ?? [];
        $tmp = ['network' => $network];
        if (!in_array($tmp, $data)) {
            $data[] = $tmp;
        }
        $fobj->truncate(0)->writeJson($data);
    }

    /**
     * Backwards compatible cleanup of "claimed" networks
     */
    static public function cleanup()
    {
        $fobj = new FileObject(self::$todo_file, 'a+', 0600, LOCK_EX, 'wwwonly:wheel');
        foreach (($fobj->readJson() ?? []) as $record) {
            if (empty($record['network'])) {
                continue;
            }
            $inet = is_subnetv6($record['network']) ? 'inet6' : 'inet';
            Shell::run_safe('/sbin/route delete -%s %s', [$inet, $record['network']], true);
        }
        $fobj->truncate(0);
        unset($fobj); /* unlock */
    }
}