<?php

/*
 * Copyright (C) 2015 Deciso B.V.
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

class PhalconGenerator
{

    public static function generateClasses()
    {
        $classMigrations = [
            'Phalcon\\Loader' => 'Phalcon\\Autoload\\Loader',
            'Phalcon\\Config' => 'Phalcon\\Config\\Config',
            'Phalcon\\Di' => 'Phalcon\\Di\\Di',
            'Phalcon\\Security\\Random' => 'Phalcon\\Encryption\\Security\\Random',
            'Phalcon\\Filter' => 'Phalcon\\Filter\\Filter',
            'Phalcon\\Validation\\Validator\\PresenceOf' => 'Phalcon\\Filter\\Validation\\Validator\\PresenceOf',
            'Phalcon\\Validation\\Validator\\ExclusionIn' => 'Phalcon\\Filter\\Validation\\Validator\\ExclusionIn',
            'Phalcon\\Validation\\Validator\\Regex' => 'Phalcon\\Filter\\Validation\\Validator\\Regex',
            'Phalcon\\Validation\\Validator\\InclusionIn' => 'Phalcon\\Filter\\Validation\\Validator\\InclusionIn',
            'Phalcon\\Validation\\Validator\\Email' => 'Phalcon\\Filter\\Validation\\Validator\\Email',
            'Phalcon\\Validation\\Validator\\Numericality' => 'Phalcon\\Filter\\Validation\\Validator\\Numericality',
            'Phalcon\\Validation\\Exception' => 'Phalcon\\Filter\\Validation\\Exception',
            'Phalcon\\Logger' => 'Phalcon\\Logger\\Logger'
        ];

        foreach ($classMigrations as $old => $new) {
            $newAsDir = str_replace('\\', '/', $new);
            $fullPath = dirname(__DIR__, 1) . '/' . $newAsDir;
            $dir = dirname($fullPath);
            $fileName = basename($fullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            if (!file_exists($fullPath . '.php')) {
                /*
                 * XXX: right now only write if file does not exist, this
                 * however is not resilient to file corruption, we could
                 * use checksumming for this purpose.
                 */
                $file = fopen($fullPath . '.php', 'w');

                $call = $fileName == 'Loader' ? <<<EOF

                    public function __call(\$fName, \$args) {
                        if (method_exists(\$this, \$fName)) {
                            return \$this->fName(...\$args);
                        } elseif (\$fName == 'setDirectories') {
                            /* Phalcon5 renamed registerDirs to setDirectories */
                            return \$this->registerDirs(...\$args);
                        }
                    }

                EOF
                    : '';

                $namespaceBase = substr_replace($new, "", -strlen($fileName) - 1);

                $class = <<<EOF
                <?php

                namespace OPNsense\\{$namespaceBase};

                use {$new} as Phalcon{$fileName};
                use {$old} as Phalcon{$fileName}4;

                if (class_exists("{$new}", false)) {
                    class {$fileName}Wrapper extends Phalcon{$fileName} {}
                } else {
                    class {$fileName}Wrapper extends Phalcon{$fileName}4 {}
                }

                class {$fileName} extends {$fileName}Wrapper
                {
                    {$call}
                }

                EOF;

                fwrite($file, trim($class)) ;
                fclose($file);
            }
        }
    }
}
