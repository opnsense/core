<?php

/**
 *    Copyright (C) 2019-2024 Deciso B.V.
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

namespace OPNsense\Core\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\ACL;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\System\SystemStatus;
use OPNsense\System\SystemStatusCode;

/**
 * Class SystemController
 * @package OPNsense\Core
 */
class SystemController extends ApiControllerBase
{
    public function haltAction()
    {
        if ($this->request->isPost()) {
            (new Backend())->configdRun('system halt', true);
            return ['status' => 'ok'];
        } else {
            return ['status' => 'failed'];
        }
    }

    public function rebootAction()
    {
        if ($this->request->isPost()) {
            (new Backend())->configdRun('system reboot', true);
            return ['status' => 'ok'];
        } else {
            return ['status' => 'failed'];
        }
    }

    public function statusAction()
    {
        $response = ["status" => "failed"];

        $backend = new Backend();
        $statuses = json_decode(trim($backend->configdRun('system status')), true);
        if ($statuses) {
            $order = SystemStatusCode::toValueNameArray();
            $acl = new ACL();

            foreach ($statuses as $subsystem => $status) {
                $statuses[$subsystem]['status'] = $order[$status['statusCode']];
                if (!empty($status['location'])) {
                    if (!$acl->isPageAccessible($this->getUserName(), $status['location'])) {
                        unset($statuses[$subsystem]);
                        continue;
                    }
                }
            }

            /* Sort on the highest notification (non-persistent) error level after the ACL check */
            $statusCodes = array_column($statuses, 'statusCode');
            sort($statusCodes);

            $response['metadata'] = [
                /* 'system' represents the status of the top notification after sorting */
                'system' => [
                    'status' => $order[$statusCodes[0] ?? 2],
                    'message' => gettext('No pending messages'),
                    'title' => gettext('System'),
                ],
                'translations' => [
                    'dialogTitle' => gettext('System Status'),
                    'dialogCloseButton' => gettext('Close')
                ]
            ];

            // sort on status code and priority where priority is the tie breaker
            uasort($statuses, function ($a, $b) {
                if ($a['statusCode'] === $b['statusCode']) {
                    return $a['priority'] <=> $b['priority'];
                }
                return $a['statusCode'] <=> $b['statusCode'];
            });

            foreach ($statuses as &$status) {
                if (!empty($status['timestamp'])) {
                    $age = time() - $status['timestamp'];

                    if ($age < 0) {
                        /* time jump, do nothing */
                    } elseif ($age < 60) {
                        if ($age == 1) {
                            $status['age'] = sprintf(gettext('%s second ago'), $age);
                        } else {
                            $status['age'] = sprintf(gettext('%s seconds ago'), $age);
                        }
                    } elseif ($age < 60 * 60) {
                         $age = intdiv($age, 60);
                        if ($age == 1) {
                            $status['age'] = sprintf(gettext('%s minute ago'), $age);
                        } else {
                            $status['age'] = sprintf(gettext('%s minutes ago'), $age);
                        }
                    } elseif ($age < 60 * 60 * 24) {
                         $age = intdiv($age, 60 * 60);
                        if ($age == 1) {
                            $status['age'] = sprintf(gettext('%s hour ago'), $age);
                        } else {
                            $status['age'] = sprintf(gettext('%s hours ago'), $age);
                        }
                    } elseif ($age < 60 * 60 * 24 * 7) {
                         $age = intdiv($age, 60 * 60 * 24);
                        if ($age == 1) {
                            $status['age'] = sprintf(gettext('%s day ago'), $age);
                        } else {
                            $status['age'] = sprintf(gettext('%s days ago'), $age);
                        }
                    } elseif ($age < 60 * 60 * 24 * 30) {
                         $age = intdiv($age, 60 * 60 * 24 * 7);
                        if ($age == 1) {
                            $status['age'] = sprintf(gettext('%s week ago'), $age);
                        } else {
                            $status['age'] = sprintf(gettext('%s weeks ago'), $age);
                        }
                    } elseif ($age < 60 * 60 * 24 * 365) {
                         $age = intdiv($age, 60 * 60 * 24 * 30);
                        if ($age == 1) {
                            $status['age'] = sprintf(gettext('%s month ago'), $age);
                        } else {
                            $status['age'] = sprintf(gettext('%s months ago'), $age);
                        }
                    } else {
                         $age = intdiv($age, 60 * 60 * 24 * 365);
                        if ($age == 1) {
                            $status['age'] = sprintf(gettext('%s year ago'), $age);
                        } else {
                            $status['age'] = sprintf(gettext('%s years ago'), $age);
                        }
                    }
                }
            }

            $response['subsystems'] = $statuses;
            unset($response['status']);
        }

        return $response;
    }

    public function dismissStatusAction()
    {
        if ($this->request->isPost() && $this->request->hasPost("subject")) {
            $acl = new ACL();
            $backend = new Backend();
            $subsystem = $this->request->getPost("subject");
            $system = json_decode(trim($backend->configdRun('system status')), true);
            if (array_key_exists($subsystem, $system)) {
                if (!empty($system[$subsystem]['location'])) {
                    $aclCheck = $system[$subsystem]['location'];
                    if (
                        $acl->isPageAccessible($this->getUserName(), $aclCheck) ||
                        !$acl->hasPrivilege($this->getUserName(), 'user-config-readonly')
                    ) {
                        $status = trim($backend->configdRun(sprintf('system dismiss status %s', $subsystem)));
                        if ($status == "OK") {
                            return [
                                "status" => "ok"
                            ];
                        }
                    }
                }
            }
        }

        return ["status" => "failed"];
    }
}
