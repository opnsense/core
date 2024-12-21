<?php

namespace OPNsense\Auth\FieldTypes;

use OPNsense\Base\FieldTypes\BaseListField;

class ShellField extends BaseListField
{
    protected $internalIsContainer = false;
    private static $shell_list = null;

    protected function actionPostLoadingEvent()
    {
        if (self::$shell_list === null) {
            self::$shell_list = [];
            $fp = fopen('/etc/shells', 'r');
            while (($line = fgets($fp)) !== false) {
                if ($line[0] === '/' && strpos($line, '/usr/local/sbin/opnsense-') !== 0) {
                    $shell = trim($line);
                    self::$shell_list[$shell] = $shell;
                }
            }
            fclose($fp);
        }
        $this->internalOptionList = self::$shell_list;
    }
}
