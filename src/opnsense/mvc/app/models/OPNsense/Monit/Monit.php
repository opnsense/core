<?php

/*
 * Copyright (C) 2016-2019 EURO-LOG AG
 * Copyright (c) 2019 Deciso B.V.
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

namespace OPNsense\Monit;

use Phalcon\Messages\Message;
use OPNsense\Base\BaseModel;

/**
 * Class Monit
 * @package OPNsense\Monit
 */
class Monit extends BaseModel
{
    /**
     * array with service types and their possible test types
     */
    private $serviceTestMapping = [
        'process'    => ['Existence', 'ProcessResource', 'ProcessDiskIO',
                         'UID', 'GID', 'PID', 'PPID', 'Uptime', 'Connection', 'Custom'],
        'file'       => ['Existence', 'FileChecksum', 'Timestamp', 'FileSize',
                         'FileContent', 'Permisssion', 'UID', 'GID', 'Custom'],
        'fifo'       => ['Existence', 'Timestamp', 'Permisssion', 'UID', 'GID', 'Custom'],
        'filesystem' => ['Existence', 'FilesystemMountFlags',
                         'SpaceUsage', 'InodeUsage', 'DiskIO', 'Permisssion', 'Custom'],
        'directory'  => ['Existence', 'Timestamp', 'Permisssion', 'UID', 'GID', 'Custom'],
        'host'       => ['NetworkPing', 'Connection', 'Custom'],
        'system'     => ['SystemResource', 'Uptime', 'Custom'],
        'custom'     => ['ProgramStatus', 'Custom'],
        'network'    => ['NetworkInterface', 'Custom']
    ];

    /**
     * array with condition patterns for test types
     */
    private $conditionPatterns = [
        'Existence' => [
            'exist', 'not exist'
        ],
        'SystemResource' => [
            'loadavg (1min)', 'loadavg (5min)', 'loadavg (15min)', 'cpu usage',
            'cpu user usage', 'cpu system usage', 'cpu wait usage', 'memory usage',
            'swap usage'
        ],
        'ProcessResource' => [
            'cpu', 'total cpu', 'threads', 'children', 'memory usage',
            'total memory usage'
        ],
        'ProcessDiskIO' => [
            'disk read rate', 'disk write rate'
        ],
        'FileChecksum' => [
            'failed md5 checksum', 'changed md5 checksum', 'failed checksum expect'
        ],
        'Timestamp' => [
            'access time', 'modification time', 'change time', 'timestamp',
            'changed access time', 'changed modification time', 'changed change time',
            'changed timestamp'
        ],
        'FileSize' => [
            'size', 'changed size'
        ],
        'FileContent' => [
            'content =', 'content !='
        ],
        'FilesystemMountFlags' => [
            'changed fsflags'
        ],
        'SpaceUsage' => [
            'space', 'space free'
        ],
        'InodeUsage' => [
            'inodes',
            'inodes free'
        ],
        'DiskIO' => [
            'read rate',
            'write rate',
            'service time'
        ],
        'Permisssion' => [
            'failed permission',
            'changed permission'
        ],
        'UID' => [
            'failed uid'
        ],
        'GID' => [
            'failed uid'
        ],
        'PID' => [
            'changed pid'
        ],
        'PPID' => [
            'changed ppid'
        ],
        'Uptime' => [
            'uptime'
        ],
        'ProgramStatus' => [
            'status',
            'changed status'
        ],
        'NetworkInterface' => [
            'failed link',
            'changed link capacity',
            'saturation',
            'upload',
            'download'
        ],
        'NetworkPing' => [
            'failed ping',
            'failed ping4',
            'failed ping6'
        ],
        'Connection' => [
            'failed host',
            'failed port',
            'failed unixsocket'
        ],
        'Custom' => []
    ];

    /**
     * validate full model using all fields and data in a single (1 deep) array
     * @param bool $validateFullModel validate full model or only changed fields
     * @return \Phalcon\Messages\Messages
     */
    public function performValidation($validateFullModel = false)
    {
        // standard model validations
        $messages = parent::performValidation($validateFullModel);
        $all_nodes = $this->getFlatNodes();
        foreach ($all_nodes as $key => $node) {
            if ($validateFullModel || $node->isFieldChanged()) {
                // the item container may have different validations attached.
                $parentNode = $node->getParentNode();
                // perform plugin specific validations
                switch ($parentNode->getInternalXMLTagName()) {
                    case 'test':
                        // test node validations
                        switch ($node->getInternalXMLTagName()) {
                            case 'type':
                                $testUuid = $parentNode->getAttribute('uuid');
                                if (
                                    strcmp((string)$node, 'Custom') != 0 &&
                                    $node->isFieldChanged() &&
                                    $this->isTestServiceRelated($testUuid)
                                ) {
                                    $messages->appendMessage(new Message(
                                        sprintf(
                                            gettext("Cannot change the test type to '%s'. Test '%s' is linked to a service."),
                                            (string)$node,
                                            (string)$this->getNodeByReference('test.' . $parentNode->getAttribute('uuid'))->name
                                        ),
                                        $key
                                    ));
                                }
                                break;
                            case 'condition':
                                // only 'Custom' or the same test type (see $conditionPatterns)
                                // are allowed if test is linked to a service
                                $type = $this->getTestType((string)$node);
                                if (
                                    strcmp($type, 'Custom') != 0 &&
                                    strcmp((string)$parentNode->type, $type) != 0 &&
                                    $this->isTestServiceRelated($parentNode->getAttribute('uuid'))
                                ) {
                                    $messages->appendMessage(new Message(
                                        sprintf(
                                            gettext("Condition '%s' would change the type of the test '%s' but it is linked to a service."),
                                            (string)$node,
                                            (string)$this->getNodeByReference('test.' . $parentNode->getAttribute('uuid'))->name
                                        ),
                                        $key
                                    ));
                                } else {
                                    // set the test tytpe according to the condition
                                    $parentNode->type = $type;
                                }
                                break;
                        }
                        break;
                    case 'service':
                        // service node validations
                        switch ($node->getInternalXMLTagName()) {
                            case 'tests':
                                // test dependencies defined in $this->serviceTestMapping
                                foreach (explode(',', (string)$parentNode->tests) as $testUUID) {
                                    $test = $this->getNodeByReference('test.' . $testUUID);
                                    if ($test != null) {
                                        if (!empty($this->serviceTestMapping[(string)$parentNode->type])) {
                                            $options = $this->serviceTestMapping[(string)$parentNode->type];
                                            if (!in_array((string)$test->type, $options)) {
                                                $validationMsg = sprintf(
                                                    gettext("Test %s with type %s not allowed for this service type"),
                                                    (string)$test->name,
                                                    $test->type->getNodeData()[(string)$test->type]['value']
                                                );
                                                $messages->appendMessage(
                                                    new Message($validationMsg, $key)
                                                );
                                            }
                                        }
                                    }
                                }
                                break;
                            case 'pidfile':
                                if (
                                    empty((string)$node) && (string)$parentNode->type == 'process'
                                      && empty((string)$parentNode->match)
                                ) {
                                    $messages->appendMessage(new Message(
                                        gettext("Please set at least one of Pidfile or Match."),
                                        $key
                                    ));
                                }
                                break;
                            case 'match':
                                if (
                                    empty((string)$node) && (string)$parentNode->type == 'process'
                                      && empty((string)$parentNode->pidfile)
                                ) {
                                    $messages->appendMessage(new Message(
                                        gettext("Please set at least one of Pidfile or Match."),
                                        $key
                                    ));
                                }
                                break;
                            case 'address':
                                if (empty((string)$node) && (string)$parentNode->type == 'host') {
                                    $messages->appendMessage(new Message(
                                        gettext("Address is mandatory for 'Remote Host' checks."),
                                        $key
                                    ));
                                } elseif (
                                    empty((string)$node) && (string)$parentNode->type == 'network'
                                      && empty((string)$parentNode->interface)
                                ) {
                                    $messages->appendMessage(new Message(
                                        gettext("Please set at least one of Address or Interface."),
                                        $key
                                    ));
                                }
                                break;
                            case 'interface':
                                if (
                                    empty((string)$node) && (string)$parentNode->type == 'network'
                                      && empty((string)$parentNode->address)
                                ) {
                                    $messages->appendMessage(new Message(
                                        gettext("Please set at least one of Address or Interface."),
                                        $key
                                    ));
                                }
                                break;
                            case 'path':
                                if (
                                    empty((string)$node) && in_array(
                                        (string)$parentNode->type,
                                        ['file', 'fifo', 'filesystem', 'directory']
                                    )
                                ) {
                                    $messages->appendMessage(new Message(
                                        gettext("Path is mandatory."),
                                        $key
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
        return parent::serializeToConfig($validateFullModel, $disable_validation);
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

    /**
     * determine if services have links to this test node
     * @param uuid of the test node
     * @return bool
     */
    public function isTestServiceRelated($testUUID = null)
    {
        $serviceNodes = $this->service->getNodes();
        foreach ($this->service->iterateItems() as $serviceNode) {
            if (in_array($testUUID, explode(',', (string)$serviceNode->tests))) {
                return true;
            }
        }
        return false;
    }

    /**
     * get test type from condition string
     * @param condition string
     * @return string
     */
    public function getTestType($condition)
    {
        $condition = preg_replace('/\s\s+/', ' ', $condition);
        $keyLength = 0;
        $foundOperand = '';
        $foundTestType = 'Custom';

        foreach ($this->conditionPatterns as $testType => $operandList) {
            // find the operand for this condition using the longest match
            foreach ($operandList as $operand) {
                $operandLength = strlen($operand);
                if (
                    !strncmp($condition, $operand, $operandLength) &&
                    $operandLength > $keyLength
                ) {
                    $keyLength = $operandLength;
                    $foundOperand = $operand;
                    $foundTestType = $testType;
                }
            }
        }

        // 'memory usage' can be ambiguous but 'percent' unit makes it clear
        if (strcmp('memory usage', $foundOperand) == 0) {
            if (preg_match('/^.*\spercent|%\s*$/', $condition)) {
                $foundTestType = 'SystemResource';
            } else {
                $foundTestType = 'ProcessResource';
            }
        }
        return $foundTestType;
    }
}
