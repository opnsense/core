<?php

/**
 *    Copyright (C) 2017-2018 EURO-LOG AG
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace OPNsense\Monit\Api;

use \OPNsense\Base\ApiControllerBase;
use \OPNsense\Core\Config;
use \OPNsense\Monit\Monit;
use \OPNsense\Base\UIModelGrid;

/**
 * Class SettingsController
 * @package OPNsense\Monit
 */
class SettingsController extends ApiControllerBase
{

    /**
     * @var null|object the monit model object
     */
    public $mdlMonit = null;

    /**
     * @var array list with valid model node types
     */
    private $nodeTypes = array('general', 'alert', 'service', 'test');

    /**
     * @var array with syntax information to build test conditions
     */
    private $testSyntax = [
        'serviceTestMapping' => [
            'process'    => ['Existence', 'Process Resource', 'Process Disk I/O', 'UID', 'GID', 'PID', 'PPID', 'Uptime', 'Connection', 'Custom'],
            'file'       => ['Existence', 'File Checksum', 'Timestamp', 'File Size', 'File Content', 'Permisssion', 'UID', 'GID', 'Custom'],
            'fifo'       => ['Existence', 'Timestamp', 'Permisssion', 'UID', 'GID', 'Custom'],
            'filesystem' => ['Existence', 'Filesystem Mount Flags', 'Space Usage', 'Inode Usage', 'Disk I/O', 'Permisssion', 'Custom'],
            'directory'  => ['Existence', 'Timestamp', 'Permisssion', 'UID', 'GID', 'Custom'],
            'host'       => ['Network Ping', 'Connection', 'Custom'],
            'system'     => ['System Resource', 'Uptime', 'Custom'],
            'custom'     => ['Program Status', 'Custom'],
            'network'    => ['Network Interface', 'Custom']
        ],
        'operators' => ['greater', 'less', 'equal', 'notequal'],
        'units' => [
            'timeUnits'       => ['seconds', 'minutes', 'hours', 'days'],
            'amountUnits'     => ['byte', 'kilobyte', 'megabyte', 'gigabyte'],
            'spaceAmountUnits'     => ['byte', 'kilobyte', 'megabyte', 'gigabyte', 'percent'],
            'diskAmountUnits' => ['byte', 'kilobyte', 'megabyte', 'gigabyte', 'operations'],
            'netAmountUnits'  => ['byte', 'kilobyte', 'megabyte', 'gigabyte', 'packets'],
        ],
        'testConditionMapping' => [
            'Existence' => [
                'exist',
                'not exist'
            ],
            'System Resource' => [
                'loadavg (1min)'     => ['_OPERATOR' => ['_VALUE' => null]],
                'loadavg (5min)'     => ['_OPERATOR' => ['_VALUE' => null]],
                'loadavg (15min)'    => ['_OPERATOR' => ['_VALUE' => null]],
                'cpu usage'          => ['_OPERATOR' => ['_VALUE' => null, '_UNIT' => 'percent']],
                'cpu user usage'     => ['_OPERATOR' => ['_VALUE' => null, '_UNIT' => 'percent']],
                'cpu system usage'   => ['_OPERATOR' => ['_VALUE' => null, '_UNIT' => 'percent']],
                'cpu wait usage'     => ['_OPERATOR' => ['_VALUE' => null, '_UNIT' => 'percent']],
                'memory usage'       => ['_OPERATOR' => ['_VALUE' => null, '_UNIT' => 'percent']],
                'swap usage'         => ['_OPERATOR' => ['_VALUE' => null, '_UNIT' => 'percent']]
            ],
            'Process Resource' => [
                'cpu'                => ['_OPERATOR' => ['_VALUE' => null, '_UNIT' => 'percent']],
                'total cpu'          => ['_OPERATOR' => ['_VALUE' => null, '_UNIT' => 'percent']],
                'threads'            => ['_OPERATOR' => ['_VALUE' => null]],
                'children'           => ['_OPERATOR' => ['_VALUE' => null]],
                'memory usage'       => ['_OPERATOR' => ['_VALUE' => null, '_UNIT' => 'amountUnits']],
                'total memory usage' => ['_OPERATOR' => ['_VALUE' => null, '_UNIT' => 'percent']]
            ],
            'Process Disk I/O' => [
                'disk read rate'  => ['_OPERATOR' => ['_VALUE' => null, '_UNIT' => 'operations', '_RATE' => '/s']],
                'disk write rate' => ['_OPERATOR' => ['_VALUE' => null, '_UNIT' => 'operations', '_RATE' => '/s']]
            ],
            'File Checksum' => [
                'failed md5 checksum',
                'changed md5 checksum',
                'failed checksum expect' => ['_VALUE' => null]
            ],
            'Timestamp' => [
                'access time'       => ['_OPERATOR' => ['_VALUE' => null, '_UNIT' => 'timeUnits']],
                'modification time' => ['_OPERATOR' => ['_VALUE' => null, '_UNIT' => 'timeUnits']],
                'change time'       => ['_OPERATOR' => ['_VALUE' => null, '_UNIT' => 'timeUnits']],
                'timestamp'         => ['_OPERATOR' => ['_VALUE' => null, '_UNIT' => 'timeUnits']],
                'changed access time',
                'changed modification time',
                'changed change time',
                'changed timestamp'
            ],
            'File Size' => [
                'size' => ['_OPERATOR' => ['_VALUE' => null, '_UNIT' => 'amountUnits']],
                'changed size'
            ],
            'File Content' => [
                'content =' => ['_VALUE' => null],
                'content !=' => ['_VALUE' => null]
            ],
            'Filesystem Mount Flags' => [
                'changed fsflags'
            ],
            'Space Usage' => [
                'space' => ['_OPERATOR' => ['_VALUE' => null, '_UNIT' => 'spaceAmountUnits']],
                'space free' => ['_OPERATOR' => ['_VALUE' => null, '_UNIT' => 'spaceAmountUnits']]
            ],
            'Inode Usage' => [
                'inodes' => ['_OPERATOR' => ['_VALUE' => null, '_UNIT' => 'amountUnits']],
                'inodes free' => ['_OPERATOR' => ['_VALUE' => null, '_UNIT' => 'amountUnits']]
            ],
            'Disk I/O' => [
                'read rate'  => ['_OPERATOR' => ['_VALUE' => null, '_UNIT' => 'diskAmountUnits', '_RATE' => '/s']],
                'write rate' => ['_OPERATOR' => ['_VALUE' => null, '_UNIT' => 'diskAmountUnits', '_RATE' => '/s']],
                'service time' => ['_OPERATOR' => ['_VALUE' => null, '_UNIT' => 'millisecond']]
            ],
            'Permisssion' => [
                'failed permission' => ['_VALUE' => null],
                'changed permission'
            ],
            'UID' => [
                'failed uid' => ['_VALUE' => null]
            ],
            'GID' => [
                'failed uid' => ['_VALUE' => null]
            ],
            'PID' => [
                'changed pid'
            ],
            'PPID' => [
                'changed ppid'
            ],
            'Uptime' => [
                'uptime' => ['_OPERATOR' => ['_VALUE' => null, '_UNIT' => 'timeUnits']]
            ],
            'Program Status' => [
                'status' => ['_OPERATOR' => ['_VALUE' => null]],
                'changed status'
            ],
            'Network Interface' => [
                'failed link',
                'changed link capacity',
                'saturation' => ['_OPERATOR' => ['_VALUE' => null, '_UNIT' => 'percent']],
                'upload' =>  ['_OPERATOR' => ['_VALUE' => null, '_UNIT' => 'netAmountUnits', '_RATE' => '/s']],
                'download' =>  ['_OPERATOR' => ['_VALUE' => null, '_UNIT' => 'netAmountUnits', '_RATE' => '/s']],
            ],
            'Network Ping' => [
                'failed ping4',
                'failed ping6'
            ],
            'Custom' => null
        ]
    ];

    /**
     * initialize object properties
     */
    public function onConstruct()
    {
        $this->mdlMonit = new Monit();
    }

    /**
     * check if changes to the monit settings were made
     * @return array result
     */
    public function dirtyAction()
    {
        $result = array('status' => 'ok');
        $result['monit']['dirty'] = $this->mdlMonit->configChanged();
        return $result;
    }

    /**
     * query monit settings
     * @param $nodeType
     * @param $uuid
     * @param $syntax
     * @return array result
     * @throws \Exception
     */
    public function getAction($nodeType = null, $uuid = null, $syntax = null)
    {
        $result = array("result" => "failed");
        if ($syntax != null) {
            $result['syntax'] = $this->testSyntax;
            $result['result'] = 'ok';
            return $result;
        }
        if ($this->request->isGet() && $nodeType != null) {
            $this->validateNodeType($nodeType);
            if ($nodeType == 'general') {
                $node = $this->mdlMonit->getNodeByReference($nodeType);
            } else {
                if ($uuid != null) {
                    $node = $this->mdlMonit->getNodeByReference($nodeType . '.' . $uuid);
                } else {
                    $node = $this->mdlMonit->$nodeType->Add();
                }
            }
            if ($node != null) {
                $result['monit'] = array($nodeType => $node->getNodes());
                $result['result'] = 'ok';
            }
        }
        return $result;
    }

    /**
     * set monit properties
     * @param $nodeType
     * @param $uuid
     * @return array status
     * @throws \Exception
     */
    public function setAction($nodeType = null, $uuid = null)
    {
        $result = array("result" => "failed", "validations" => array());
        if ($this->request->isPost() && $this->request->hasPost("monit") && $nodeType != null) {
            $this->validateNodeType($nodeType);
            if ($nodeType == 'general') {
                $node = $this->mdlMonit->getNodeByReference($nodeType);
            } else {
                if ($uuid != null) {
                    $node = $this->mdlMonit->getNodeByReference($nodeType . '.' . $uuid);
                } else {
                    $node = $this->mdlMonit->$nodeType->Add();
                }
            }
            if ($node != null) {
                $monitInfo = $this->request->getPost("monit");

                // perform plugin specific validations
                if ($nodeType == 'service') {
                    $tests = explode(',', $monitInfo[$nodeType]['tests']);
                    foreach ($tests as $testUUID) {
                        $test = $this->mdlMonit->getNodeByReference('test.' . $testUUID);
                        if ($test != null) {
                            $testName = $test->name->__toString();
                            $testType = $test->type->getNodeData()[$test->type->__toString()]['value'];
                            if (array_search($testType, $this->testSyntax['serviceTestMapping'][$monitInfo[$nodeType]['type']]) === false) {
                                $result["validations"]['monit.service.tests'] = "Test " . $testName . ' with type ' . $testType . ' not allowed for service type ' . $monitInfo[$nodeType]['type'];
                            }
                        }
                    }
                    switch ($monitInfo[$nodeType]['type']) {
                        case 'process':
                            if (empty($monitInfo[$nodeType]['pidfile']) && empty($monitInfo[$nodeType]['match'])) {
                                $result["validations"]['monit.service.pidfile'] = "Please set at least one of Pidfile or Match.";
                                $result["validations"]['monit.service.match'] = $result["validations"]['monit.service.pidfile'];
                            }
                            break;
                        case 'host':
                            if (empty($monitInfo[$nodeType]['address'])) {
                                $result["validations"]['monit.service.address'] = "Address is mandatory for 'Remote Host' checks.";
                            }
                            break;
                        case 'network':
                            if (empty($monitInfo[$nodeType]['address']) && empty($monitInfo[$nodeType]['interface'])) {
                                $result["validations"]['monit.service.address'] = "Please set at least one of Address or Interface.";
                                $result["validations"]['monit.service.interface'] = $result["validations"]['monit.service.address'];
                            }
                            break;
                        case 'system':
                            break;
                        default:
                            if (empty($monitInfo[$nodeType]['path'])) {
                                $result["validations"]['monit.service.path'] = "Path is mandatory.";
                            }
                    }
                }

                $node->setNodes($monitInfo[$nodeType]);
                $valMsgs = $this->mdlMonit->performValidation();
                foreach ($valMsgs as $field => $msg) {
                    $fieldnm = str_replace($node->__reference, "monit." . $nodeType, $msg->getField());
                    $result["validations"][$fieldnm] = $msg->getMessage();
                }
                if (empty($result["validations"])) {
                    unset($result["validations"]);
                    $result['result'] = 'ok';
                    $this->mdlMonit->serializeToConfig();
                    Config::getInstance()->save();
                    if ($this->mdlMonit->configDirty()) {
                        $result['status'] = 'ok';
                    }
                }
            }
        }
        return $result;
    }

    /**
     * delete monit settings
     * @param $nodeType
     * @param $uuid
     * @return array status
     * @throws \Exception
     */
    public function delAction($nodeType = null, $uuid = null)
    {
        $result = array("result" => "failed");
        if ($nodeType != null) {
            $this->validateNodeType($nodeType);
            if ($uuid != null) {
                $node = $this->mdlMonit->getNodeByReference($nodeType . '.' . $uuid);
                if ($node != null) {
                    if ($this->mdlMonit->$nodeType->del($uuid) == true) {
                        // delete relations
                        if ($nodeType == 'test') {
                            $nodeName = $this->mdlMonit->getNodeByReference($nodeType . '.' . $uuid . '.name');
                            if ($nodeName != null) {
                                $nodeName = $nodeName->__toString();
                                $this->deleteRelations('service', 'tests', $uuid, 'test', $nodeName, $this->mdlMonit);
                            }
                        }
                        $this->mdlMonit->serializeToConfig();
                        Config::getInstance()->save();
                        if ($this->mdlMonit->configDirty()) {
                            $result['status'] = 'ok';
                        }
                    }
                }
            }
        }
        return $result;
    }

    /**
     * toggle monit items (enable/disable)
     * @param $nodeType
     * @param $uuid
     * @return array result
     */
    public function toggleAction($nodeType = null, $uuid = null)
    {
        $result = array("result" => "failed");
        if ($this->request->isPost() && $nodeType != null) {
            if ($uuid != null) {
                $node = $this->mdlMonit->getNodeByReference($nodeType . '.' . $uuid);
                if ($node != null) {
                    if ($node->enabled->__toString() == "1") {
                        $node->enabled = "0";
                    } else {
                        $node->enabled = "1";
                    }
                    $this->mdlMonit->serializeToConfig();
                    Config::getInstance()->save();
                    if ($this->mdlMonit->configDirty()) {
                        $result['status'] = 'ok';
                    }
                } else {
                    $result['result'] = "not found";
                }
            } else {
                $result['result'] = "uuid not given";
            }
        }
        return $result;
    }

    /**
     * search monit settings
     * @param $nodeType
     * @return array result
     * @throws \Exception
     */
    public function searchAction($nodeType = null)
    {
        $this->sessionClose();
        if ($this->request->isPost() && $nodeType != null) {
            $this->validateNodeType($nodeType);
            $grid = new UIModelGrid($this->mdlMonit->$nodeType);
            $fields = array();
            switch ($nodeType) {
                case 'alert':
                    $fields = array("enabled", "recipient", "noton", "events", "description");
                    break;
                case 'service':
                    $fields = array("enabled", "name", "type");
                    break;
                case 'test':
                    $fields = array("name", "type", "condition", "action");
                    break;
            }
            return $grid->fetchBindRequest($this->request, $fields);
        }
    }

    /**
     * import system notification settings
     * @return array result
     */
    public function notificationAction()
    {
        $result = array("result" => "failed");
        if ($this->request->isPost()) {
            $this->sessionClose();

            $cfg = Config::getInstance();
            $cfgObj = $cfg->object();
            $node = $this->mdlMonit->getNodeByReference('general');
            $generalSettings = array();

            // inherit SMTP settings from System->Settings->Notifications
            if (!empty($cfgObj->notifications->smtp->ipaddress)) {
                $generalSettings['mailserver'] = $cfgObj->notifications->smtp->ipaddress;
            }
            if (!empty($cfgObj->notifications->smtp->port)) {
                $generalSettings['port'] = $cfgObj->notifications->smtp->port;
            }
            $generalSettings['username'] = $cfgObj->notifications->smtp->username;
            $generalSettings['password'] = $cfgObj->notifications->smtp->password;
            if ((!empty($cfgObj->notifications->smtp->tls) && $cfgObj->notifications->smtp->tls == 1)  ||
                (!empty($cfgObj->notifications->smtp->ssl) && $cfgObj->notifications->smtp->ssl == 1)) {
                $generalSettings['ssl'] = 1;
            } else {
                $generalSettings['ssl'] = 0;
            }

            // apply them
            $node->setNodes($generalSettings);
            $valMsgs = $this->mdlMonit->performValidation();
            foreach ($valMsgs as $field => $msg) {
                $fieldnm = str_replace($node->__reference, "monit.general.", $msg->getField());
                $result["validations"][$fieldnm] = $msg->getMessage();
            }
            if (empty($result["validations"])) {
                unset($result["validations"]);
                $this->mdlMonit->serializeToConfig();
                Config::getInstance()->save();
                if ($this->mdlMonit->configDirty()) {
                    $result['status'] = 'ok';
                    $result['result'] = 'OK';
                }
            }
        }
        return $result;
    }

    /**
     * validate nodeType
     * @param $nodeType
     * @throws \Exception
     */
    private function validateNodeType($nodeType = null)
    {
        if (array_search($nodeType, $this->nodeTypes) === false) {
            throw new \Exception('unknown nodeType: ' . $nodeType);
        }
    }

    /**
     * delete relations
     * @param string|null $nodeType
     * @param string|null $nodeField
     * @param string|null $relUuid
     * @param string|null $relNodeType
     * @param string|null $relNodeName
     * @throws \Exception
     */
    private function deleteRelations(
        $nodeType = null,
        $nodeField = null,
        $relUuid = null,
        $relNodeType = null,
        $relNodeName = null
    ) {
        $nodes = $this->mdlMonit->$nodeType->getNodes();
        // get nodes with relations
        foreach ($nodes as $nodeUuid => $node) {
            // get relation uuids
            foreach ($node[$nodeField] as $fieldUuid => $field) {
                // remove uuid from field
                if ($fieldUuid == $relUuid) {
                    $refField = $nodeType . '.' . $nodeUuid . '.' . $nodeField;
                    $relNode = $this->mdlMonit->getNodeByReference($refField);
                    $nodeRels = str_replace($relUuid, '', $relNode->__toString());
                    $nodeRels = str_replace(',,', ',', $nodeRels);
                    $nodeRels = rtrim($nodeRels, ',');
                    $nodeRels = ltrim($nodeRels, ',');
                    $this->mdlMonit->setNodeByReference($refField, $nodeRels);
                    if ($relNode->isEmptyAndRequired()) {
                        $nodeName = $this->mdlMonit->getNodeByReference($nodeType . '.' . $nodeUuid . '.name')->__toString();
                        throw new \Exception("Cannot delete $relNodeType '$relNodeName' from $nodeType '$nodeName'");
                    }
                }
            }
        }
    }
}
