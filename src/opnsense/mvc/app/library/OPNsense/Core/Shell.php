<?php

/*
 * Copyright (C) 2015 Deciso B.V.
 * Copyright (C) 2015-2025 Franco Fichtner <franco@opnsense.org>
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

namespace OPNsense\Core;

/**
 * Class Shell shell/command handling routines
 * @package OPNsense\Core
 */
class Shell
{
    /**
     * safe shell command formatter
     */
    public static function exec_safe($format, $args = [])
    {
        if (!is_array($format)) {
            $format = [$format];
        }

        if (!is_array($args)) {
            $args = [$args];
        }

        foreach ($args as $id => $arg) {
            $args[$id] = escapeshellarg($arg ?? '');
        }

        return vsprintf(implode(' ', $format), $args);
    }

    /**
     * pass commands through to stdout
     */
    public static function pass_safe($format, $args = [], &$result_code = null)
    {
        return passthru(self::exec_safe($format, $args), $result_code);
    }

    /**
     * run commands safely with failure reports by default
     * @param string $command command to execute
     * @param bool $mute
     * @return int
     */
    public static function run_safe($format, $args = [], $mute = false)
    {
        $command = self::exec_safe($format, $args);
        $result_code = 0;
        $output = [];

        /*
         * Redirect stderr to stdout for error logging and because
         * stderr appears to be passed through to the executing code.
         */
        exec("{$command} 2>&1", $output, $result_code);

        if ($result_code != 0 && $mute == false) {
            /* use this prefix for the log message for legacy emulation */
            $page = $_SERVER['SCRIPT_NAME'];
            if (empty($page)) {
                $files = get_included_files();
                $page = basename($files[0]);
            }

            /* log directly without opening syslog to avoid clobbering the subsequent callers */
            syslog(LOG_ERR, "{$page}: " . sprintf(
                'The command <%s> returned exit code %d and the output was "%s"',
                $command,
                $result_code,
                implode(' ', $output)
            ));
        }

        return $result_code;
    }

    /**
     * run commands and grab their output
     */
    public static function shell_safe($format, $args = [], $explode = false, $separator = "\n")
    {
        $ret = shell_exec(self::exec_safe($format, $args));

        /* shell_exec() has weird semantics */
        if ($ret === false || $ret === null) {
            $ret = '';
        }

        /* single string output */
        if (!$explode) {
            return trim($ret);
        }

        /* explode as array emulating exec()'s semantics */
        $ret = rtrim($ret);

        return strlen($ret) ? explode($separator, $ret) : [];
    }
}
