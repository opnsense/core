<?php

/*
 * Copyright (C) 2018 David Harrigan
 * Copyright (C) 2018 Deciso B.V.
 * Copyright (C) 2018 Fabian Franz
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

namespace OPNsense\Backup;

use OPNsense\Core\Config;

/**
 * Class SCP backup
 * @package OPNsense\Backup
 */
class Scp extends Base implements IBackupProvider
{

    /**
     * get required (user interface) fields for backup connector
     * @return array configuration fields, types and description
     */
    public function getConfigurationFields()
    {
        $fields = array(
            array(
                'name' => 'enabled',
                'type' => 'checkbox',
                'label' => gettext('Enable'),
            ),
            array(
                'name' => 'hostname',
                'type' => 'text',
                'label' => gettext('Hostname'),
                'help' => gettext('Set the remote hostname.'),
            ),
            array(
                'name' => 'port',
                'type' => 'text',
                'label' => gettext('Port'),
                'help' => gettext('Set the remote port.'),
            ),
            array(
                'name' => 'username',
                'type' => 'text',
                'label' => gettext('Remote Username'),
                'help' => gettext('Set the remote username.'),
            ),
            array(
                'name' => 'remotedirectory',
                'type' => 'text',
                'label' => gettext('Remote Directory'),
                'help' => gettext('Set the remote directory to backup the config file to.'),
            )
        );
        $mdl = new ScpSettings();
        foreach ($fields as &$field) {
            $field['value'] = (string)$mdl->getNodeByReference($field['name']);
        }
        return $fields;
    }

    /**
     * backup provider name
     * @return string user friendly name
     */
    public function getName()
    {
        return gettext('Secure Copy');
    }

    /**
     * validate and set configuration
     * @param array $conf configuration array
     * @return array of validation errors when not saved
     */
    public function setConfiguration($conf)
    {
        $mdl = new ScpSettings();
        $this->setModelProperties($mdl, $conf);
        $validation_messages = $this->validateModel($mdl);
        if (empty($validation_messages)) {
            $mdl->serializeToConfig();
            Config::getInstance()->save();
        }
        return $validation_messages;
    }

    /**
     * @return array filelist
     */
    public function backup()
    {
        // not configured / issue, return empty list
        return array();
    }

    /**
     * Is this provider enabled
     * @return boolean enabled status
     */
    public function isEnabled()
    {
        $mdl = new ScpSettings();
        return (string)$mdl->enabled === "1";
    }
}
