<?php

/*
 *  Copyright (C) 2016 EURO-LOG AG
 *  Copyright (c) 2019 Deciso B.V.
 *  All rights reserved.
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions are met:
 *
 *  1. Redistributions of source code must retain the above copyright notice,
 *     this list of conditions and the following disclaimer.
 *
 *  2. Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in the
 *     documentation and/or other materials provided with the distribution.
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


namespace OPNsense\Monit;

use OPNsense\Base\BaseModel;

/**
 * Class Monit
 * @package OPNsense\Monit
 */
class Monit extends BaseModel
{
    /**
     *
     */
    private $testSyntax = [
        'process'    => ['Existence', 'Process Resource', 'Process Disk I/O',
                         'UID', 'GID', 'PID', 'PPID', 'Uptime', 'Connection', 'Custom'],
        'file'       => ['Existence', 'File Checksum', 'Timestamp', 'File Size',
                         'File Content', 'Permisssion', 'UID', 'GID', 'Custom'],
        'fifo'       => ['Existence', 'Timestamp', 'Permisssion', 'UID', 'GID', 'Custom'],
        'filesystem' => ['Existence', 'Filesystem Mount Flags',
                         'Space Usage', 'Inode Usage', 'Disk I/O', 'Permisssion', 'Custom'],
        'directory'  => ['Existence', 'Timestamp', 'Permisssion', 'UID', 'GID', 'Custom'],
        'host'       => ['Network Ping', 'Connection', 'Custom'],
        'system'     => ['System Resource', 'Uptime', 'Custom'],
        'custom'     => ['Program Status', 'Custom'],
        'network'    => ['Network Interface', 'Custom']
    ];


    /**
     * validate full model using all fields and data in a single (1 deep) array
     * @param bool $validateFullModel validate full model or only changed fields
     * @return \Phalcon\Validation\Message\Group
     */
    public function performValidation($validateFullModel = false) {
        // standard model validations
        $messages = parent::performValidation($validateFullModel);
        $all_nodes = $this->getFlatNodes();
        foreach ($all_nodes as $key => $node) {
            if ($validateFullModel || $node->isFieldChanged()) {
                // the item container may have different validations attached.
                $parentNode = $node->getParentNode();
                // perform plugin specific validations
                switch ($parentNode->getInternalXMLTagName()) {
                    case 'service':
                        // service type node validations
                        switch ($node->getInternalXMLTagName()) {
                            case 'tests':
                                // test dependencies defined in $this->testSyntax
                                foreach (explode(',', (string)$parentNode->tests) as $testUUID) {
                                    $test = $this->getNodeByReference('test.' . $testUUID);
                                    if ($test != null) {
                                        if (!empty($this->testSyntax[(string)$parentNode->type])) {
                                          $options = $this->testSyntax[(string)$parentNode->type];
                                          if (!in_array((string)$test->type, $options)) {
                                            $validationMsg = sprintf(
                                              gettext("Test %s with type %s not allowed for this service type"),
                                              (string)$test->name,
                                              $test->type->getNodeData()[(string)$test->type]['value']
                                            );
                                            $messages->appendMessage(
                                              new \Phalcon\Validation\Message($validationMsg, $key)
                                            );
                                          }
                                        }
                                    }
                                }
                                break;
                            case 'pidfile':
                                if (empty((string)$node) && (string)$parentNode->type == 'process'
                                      && empty((string)$parentNode->match)) {
                                    $messages->appendMessage(new \Phalcon\Validation\Message(
                                      gettext("Please set at least one of Pidfile or Match."), $key
                                    ));
                                }
                                break;
                            case 'match':
                                if (empty((string)$node) && (string)$parentNode->type == 'process'
                                      && empty((string)$parentNode->pidfile)) {
                                    $messages->appendMessage(new \Phalcon\Validation\Message(
                                      gettext("Please set at least one of Pidfile or Match."), $key
                                    ));
                                }
                                break;
                            case 'address':
                                if (empty((string)$node) && (string)$parentNode->type == 'host') {
                                    $messages->appendMessage(new \Phalcon\Validation\Message(
                                      gettext("Address is mandatory for 'Remote Host' checks."), $key
                                    ));
                                } elseif (empty((string)$node) && (string)$parentNode->type == 'network'
                                      && empty((string)$parentNode->interface) ) {
                                    $messages->appendMessage(new \Phalcon\Validation\Message(
                                      gettext("Please set at least one of Address or Interface."), $key
                                    ));
                                }
                                break;
                            case 'interface':
                                if (empty((string)$node) && (string)$parentNode->type == 'network'
                                      && empty((string)$parentNode->address) ) {
                                    $messages->appendMessage(new \Phalcon\Validation\Message(
                                      gettext("Please set at least one of Address or Interface."), $key
                                    ));
                                }
                                break;
                            case 'path':
                                if (empty((string)$node) && in_array((string)$parentNode->type,
                                      ['file', 'fifo', 'filesystem', 'directory'])) {
                                    $messages->appendMessage(new \Phalcon\Validation\Message(
                                      gettext("Path is mandatory."), $key
                                    ));
                                }
                                break;
                        }
                        break;
                    default:
                        break;
                }
            }
        }
        return $messages;
    }

    /**
     * mark configuration as changed when data is pushed back to the config
     */
    public function serializeToConfig($validateFullModel = false, $disable_validation = false)
    {
        @touch("/tmp/monit.dirty");
        return parent::performValidation($validateFullModel, $disable_validation);
    }


    /**
     * get configuration state
     * @return bool
     */
    public function configChanged()
    {
        return file_exists("/tmp/monit.dirty");
    }

    /**
     * mark configuration as consistent with the running config
     * @return bool
     */
    public function configClean()
    {
        return @unlink("/tmp/monit.dirty");
    }
}
