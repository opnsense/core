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

namespace OPNsense\Routing;

use OPNsense\Base\BaseModel;
use OPNsense\Base\Messages\Message;
use OPNsense\Core\Config;

class GatewayGroups extends BaseModel
{
    private ?Gateways $gatewaysModel = null;

    private function getGatewaysModel()
    {
        return $this->gatewaysModel ??= new Gateways();
    }

    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);
        $cfg = Config::getInstance()->object();
        $gateways = (new Gateways())->gatewaysIndexedByName();

        foreach ($this->gateway_group->iterateItems() as $group) {
            if ($validateFullModel || $group->isFieldChanged()) {
                $ref = $group->__reference;

                /* name changed? */
                $new = $group->name->getValue();
                if (!empty($cfg->gateways) && !empty($cfg->gateways->gateway_group)) {
                    foreach ($cfg->gateways->gateway_group as $grp) {
                        $uuid = (string)$grp->attributes()->uuid;
                        if ($uuid === explode('.', $ref)[1]) {
                            $old = (string)$grp->name;
                            if ($old !== $new) {
                                $messages->appendMessage(
                                    new Message(gettext("Changing name on a gateway group is not allowed."), $ref . ".name")
                                );
                            }
                        }
                    }
                }

                /* at least one gateway selected in a tier? */
                if (count($this->gatewaysInGroup($group)) == 0) {
                    foreach (['item', 'item2', 'item3', 'item4', 'item5'] as $property) {
                        $messages->appendMessage(
                            new Message(gettext("At least one tier must be set."), $ref . "." . $property)
                        );
                    }
                }

                /* name overlap with regular gateways? */
                foreach ($gateways as $gwname => $gateway) {
                    if ($new === $gwname) {
                        $messages->appendMessage(
                            new Message(sprintf(gettext("A gateway group cannot have the same name with a gateway '%s' please choose another name."), $new), $ref . ".name")
                        );
                    }
                }
            }

        }

        return $messages;
    }

    public function gatewaysInGroup($groupNode)
    {
        $result = [];
        foreach (['item', 'item2', 'item3', 'item4', 'item5'] as $property) {
            foreach (explode(',', $groupNode->$property->getValue()) as $gwname) {
                if (!empty($gwname)) {
                    $result[] = $gwname;
                }
            }
        }

        return $result;
    }

    /**
     * get gateway groups where gateway is configured
     * @param string $gwname gateway name
     * @return array gateway group nodes
     *
     */
    public function gatewayGroupsByGateway($gwname)
    {
        $result = [];

        foreach ($this->gateway_group->iterateItems() as $grp) {
            foreach ($this->gatewaysInGroup($grp) as $gw) {
                if ($gwname === $gw) {
                    $result[] = $grp;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Get gateway groups from persisted config indexed by name,
     * <item> properties are replaced by a single "tiers" array, sorted by index
     */
    public function getGroupsConfig()
    {
        $result = [];

        foreach ($this->gateway_group->iterateItems() as $grp) {
            $name = $grp->name->getValue();

            $result[$name] = $grp->getNodeContent();

            $result[$name]['tiers'] = [];
            foreach (['item', 'item2', 'item3', 'item4', 'item5'] as $idx => $property) {
                $result[$name]['tiers'][$idx + 1] = explode(',',  $result[$name][$property] ?? '');
                unset($result[$name][$property]);
            }

            ksort($result[$name]['tiers']);
        }

        return $result;
    }

    /**
     * Check if 'up' or 'down' based on given gateway group trigger setting
     * and gateway status
     *
     * @param string $triggersetting Trigger setting, e.g. 'latency'
     * @param string $status Status string (from dpinger)
     * @return boolean up or down
     */
    private function gatewayIsUp($triggersetting, $status)
    {
        $is_up = true;
        switch ($status) {
            case 'down':
            case 'force_down':
                $is_up = false;
                break;
            case 'delay+loss':
                $is_up = stristr($triggersetting, 'latency') === false &&
                            stristr($triggersetting, 'loss') === false;
                break;
            case 'delay':
                $is_up = stristr($triggersetting, 'latency') === false;
                break;
            case 'loss':
                $is_up = stristr($triggersetting, 'loss') === false;
                break;
            default:
                $is_up = true;
        }

        return $is_up;
    }

    /**
     * Check if given gateway is part of a gateway group. If so,
     * looks at the old & new status and determines if this
     * gateway should transition based on group trigger settings.
     *
     * Multiple gateway groups may contain the same gateway. This function
     * will return true on the first hit.
     *
     * @param string @gw_name gateway name
     * @param string @old_status old status
     * @param string @new_status new status
     * @return boolean gateway should change state
     */
    public function gatewayStateChange($gw_name, $old_status, $new_status)
    {
        foreach ($this->gatewayGroupsByGateway($gw_name) as $gw_group) {
            $trigger = $gw_group->trigger->getValue();
            $up_old = $this->gatewayIsUp($trigger, $old_status);
            $up_new = $this->gatewayIsUp($trigger, $new_status);

            if ($up_old !== $up_new) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get active gateway groups including gateway details.
     * $status_info is used to determine gateway availability.
     * Unavailable gateways are not included except if it's the only one in the group.
     *
     * @param array $status_info gateway status info (from dpinger)
     * @return array usable gateway groups
     */
    public function getActiveGroups($status_info)
    {
        $all_gateways = $this->getGatewaysModel()->gatewaysIndexedByName();
        $result = [];

        foreach ($this->getGroupsConfig() as $group_name => $gw_group) {
            $all_tiers = [];
            foreach ($gw_group['tiers'] as $tieridx => $tier) {
                $all_tiers[$tieridx] = [];
                if (!isset($result[$group_name])) {
                    $result[$group_name] = [];
                }
                // check status for all gateways in this tier
                foreach ($tier as $gwname) {
                    if (!empty($status_info[$gwname])) {
                        $gateway = $all_gateways[$gwname];
                        $is_up = $this->gatewayIsUp($gw_group['trigger'], $status_info[$gwname]['status']);
                        $gateway_item = [
                            'name' => $gateway['name'],
                            'int' => $gateway['if'],
                            'gwip' => $gateway['gateway'] ?? '',
                            'ipprotocol' => $gateway['ipprotocol'],
                            'poolopts' => isset($gw_group['poolopts']) ? $gw_group['poolopts'] : null,
                            'weight' => !empty($gateway['weight']) ? $gateway['weight'] : '1',
                        ];
                        $all_tiers[$tieridx][] = $gateway_item;
                        if ($is_up) {
                            $result[$group_name][] = $gateway_item;
                        }
                    }
                }
                // exit when tier has usable gateways
                if (!empty($result[$group_name])) {
                    break;
                }
            }
            // XXX: backwards compatibility, when no tiers are up, we seem to select all from the first
            //      found tier. not very useful, since we already seem to know these are down.
            if (empty($result[$group_name])) {
                $result[$group_name] = $all_tiers[array_keys($gw_group['tiers'])[0]];
            }
        }

        return $result;
    }

    /**
     * return gateway groups (only names)
     * @return array list of names
     */
    public function getGroupNames()
    {
        $result = [];

        foreach ($this->gateway_group->iterateItems() as $grp) {
            $result[] = $grp->name->getValue();
        }

        return $result;
    }

    /**
     * return protocol family
     * @param string $name gateway group name
     * @return string ipprotocol family (inet, inet6, null when not found)
     */
    public function getGroupIPProto($name)
    {
        $all_gateways = $this->getGatewaysModel()->gatewaysIndexedByName();
        foreach ($this->getGroupsConfig() as $grp) {
            if ($grp['name'] !== $name) {
                continue;
            }

            foreach ($grp['tiers'] as $tier) {
                foreach ($tier as $gw) {
                    if (!empty($all_gateways[$gw])) {
                        return $all_gateways[$gw]['ipprotocol'];
                    }
                }
            }
        }

        return null;
    }
}
