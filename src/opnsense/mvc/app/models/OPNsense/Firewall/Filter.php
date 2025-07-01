<?php

/*
 * Copyright (C) 2020 Deciso B.V.
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

namespace OPNsense\Firewall;

use OPNsense\Core\Config;
use OPNsense\Base\Messages\Message;
use OPNsense\Base\BaseModel;
use OPNsense\Firewall\Util;
use OPNsense\TrafficShaper\TrafficShaper;

class Filter extends BaseModel
{
    /**
     * @inheritDoc
     */
    public function performValidation($validateFullModel = false)
    {
        $dntargets = (new TrafficShaper())->fetchAllTargets();
        $config = Config::getInstance()->object();
        $port_protos = ['TCP', 'UDP', 'TCP/UDP'];
        // standard model validations
        $messages = parent::performValidation($validateFullModel);
        foreach ([$this->rules->rule, $this->snatrules->rule] as $rules) {
            foreach ($rules->iterateItems() as $rule) {
                if ($validateFullModel || $rule->isFieldChanged()) {
                    // port / protocol validation
                    if (!empty((string)$rule->source_port) && !in_array($rule->protocol, $port_protos)) {
                        $messages->appendMessage(new Message(
                            gettext("Source ports are only valid for tcp or udp type rules."),
                            $rule->source_port->__reference
                        ));
                    }
                    if (!empty((string)$rule->destination_port) && !in_array($rule->protocol, $port_protos)) {
                        $messages->appendMessage(new Message(
                            gettext("Destination ports are only valid for tcp or udp type rules."),
                            $rule->destination_port->__reference
                        ));
                    }
                    // validate protocol family
                    $dest_is_addr = Util::isSubnet($rule->destination_net) || Util::isIpAddress($rule->destination_net);
                    $dest_proto = strpos($rule->destination_net, ':') === false ? "inet" : "inet6";
                    if ($dest_is_addr && $dest_proto != $rule->ipprotocol) {
                        $messages->appendMessage(new Message(
                            gettext("Destination address type should match selected TCP/IP protocol version."),
                            $rule->destination_net->__reference
                        ));
                    }
                    $src_is_addr = Util::isSubnet($rule->source_net) || Util::isIpAddress($rule->source_net);
                    $src_proto = strpos($rule->source_net, ':') === false ? "inet" : "inet6";
                    if ($src_is_addr && $src_proto != $rule->ipprotocol) {
                        $messages->appendMessage(new Message(
                            gettext("Source address type should match selected TCP/IP protocol version."),
                            $rule->source_net->__reference
                        ));
                    }
                    // when multiple values are offered for source/destination, prevent "any" being used in combination
                    foreach (['source_net', 'destination_net'] as $fieldname) {
                        if (
                            strpos($rule->$fieldname, ',') !== false &&
                            in_array('any', explode(',', $rule->$fieldname))
                        ) {
                            $messages->appendMessage(new Message(
                                gettext("Any can not be combined with other aliases"),
                                $rule->$fieldname->__reference
                            ));
                        }
                    }

                    if ($rule->icmptype && !$rule->icmptype->isEmpty() && !in_array($rule->protocol, ['ICMP'])) {
                        $messages->appendMessage(new Message(
                            gettext("Option only applies to ICMP packets"),
                            $rule->icmptype->__reference
                        ));
                    }

                    if (strpos($rule->source_net, ',') !== false && $rule->source_not == '1') {
                        $messages->appendMessage(new Message(
                            gettext("Inverting sources is only allowed for single targets to avoid mis-interpretations"),
                            $rule->source_not->__reference
                        ));
                    }
                    if (strpos($rule->destination_net, ',') !== false && $rule->destination_not == '1') {
                        $messages->appendMessage(new Message(
                            gettext("Inverting destinations is only allowed for single targets to avoid mis-interpretations"),
                            $rule->destination_net->__reference
                        ));
                    }

                    // Additional source nat validations
                    if ($rule->target !== null) {
                        $target_is_addr = Util::isSubnet($rule->target) || Util::isIpAddress($rule->target);
                        $target_proto = strpos($rule->target, ':') === false ? "inet" : "inet6";
                        if ($target_is_addr && $target_proto != $rule->ipprotocol) {
                            $messages->appendMessage(new Message(
                                gettext("Target address type should match selected TCP/IP protocol version."),
                                $rule->target->__reference
                            ));
                        }
                        if (!empty((string)$rule->target_port) && !in_array($rule->protocol, $port_protos)) {
                            $messages->appendMessage(new Message(
                                gettext("Target ports are only valid for tcp or udp type rules."),
                                $rule->target_port->__reference
                            ));
                        }
                    }

                    if (
                        !empty((string)$rule->interfacenot) && (
                        count(explode(',', $rule->interface)) != 1 || empty((string)$rule->interface)
                        )
                    ) {
                        $messages->appendMessage(new Message(
                            gettext("Inverting interfaces is only allowed for " .
                                "single targets to avoid mis-interpretations"),
                            $rule->interfacenot->__reference
                        ));
                    }
                    if ($rule->statetype == 'none') {
                        foreach (
                            [
                            'statetimeout', 'max', 'max-src-states', 'max-src-nodes', 'adaptivestart', 'adaptiveend',
                            'max-src-conn'
                            ] as $fieldname
                        ) {
                            if (!empty((string)$rule->$fieldname)) {
                                $messages->appendMessage(new Message(
                                    gettext("Invalid option when statetype is none."),
                                    $rule->$fieldname->__reference
                                ));
                            }
                        }
                    }
                    if (!in_array($rule->protocol, ['TCP', 'TCP/UDP'])) {
                        foreach (
                            [
                            'statetimeout', 'max-src-conn', 'tcpflags1', 'tcpflags2',
                            'max-src-conn-rate', 'max-src-conn-rates', 'overload'
                            ] as $fieldname
                        ) {
                            if (!empty((string)$rule->$fieldname)) {
                                $messages->appendMessage(new Message(
                                    gettext("Invalid option for other than TCP protocol choices."),
                                    $rule->$fieldname->__reference
                                ));
                            }
                        }
                    }
                    if (!empty((string)$rule->{'max-src-conn-rate'}) xor !empty((string)$rule->{'max-src-conn-rates'})) {
                        $tmp = empty((string)$rule->{'max-src-conn-rate'}) ? 'max-src-conn-rate' : 'max-src-conn-rates';
                        $messages->appendMessage(new Message(
                            gettext("Need to specify both a number of connections and a time interval."),
                            $rule->$tmp->__reference
                        ));
                    }

                    if (!empty((string)$rule->tcpflags1) && empty((string)$rule->tcpflags2)) {
                        $messages->appendMessage(new Message(
                            gettext("If you specify TCP flags that should be set " .
                                "you should specify out of which flags as well."),
                            $rule->tcpflags2->__reference
                        ));
                    }
                    if (empty((string)$rule->max) && ($rule->adaptivestart == '0' || $rule->adaptiveend == '0')) {
                        $messages->appendMessage(new Message(
                            gettext('Disabling adaptive timeouts is only supported in ".
                                "combination with a configured maximum number of states for the same rule.'),
                            $rule->max->__reference
                        ));
                    } elseif ($rule->adaptivestart == '0' xor $rule->adaptiveend == '0') {
                        $messages->appendMessage(new Message(
                            gettext("Adaptive timeouts must be disabled together."),
                            $rule->adaptivestart->__reference
                        ));
                    } elseif (!empty((string)$rule->adaptivestart) xor !empty((string)$rule->adaptiveend)) {
                        $messages->appendMessage(new Message(
                            gettext("The adaptive timouts values must be set together."),
                            $rule->adaptivestart->__reference
                        ));
                    } elseif (
                        !empty((string)$rule->max) &&
                        !empty((string)$rule->adaptiveend) &&
                        (int)$rule->max->getValue() > (int)$rule->adaptiveend->getValue()
                    ) {
                        $messages->appendMessage(new Message(
                            gettext("The value of adaptive.end must be greater than the Max states value."),
                            $rule->adaptiveend->__reference
                        ));
                    } elseif (
                        !empty((string)$rule->adaptivestart) &&
                        !empty((string)$rule->adaptiveend) &&
                        (int)$rule->adaptivestart->getValue() > (int)$rule->adaptiveend->getValue()
                    ) {
                        $messages->appendMessage(new Message(
                            gettext("The value of adaptive.end must be greater than adaptive.start value."),
                            $rule->adaptiveend->__reference
                        ));
                    }

                    if ((string)$rule->{'set-prio'} == '' && (string)$rule->{'set-prio-low'} != '') {
                        $messages->appendMessage(new Message(
                            gettext("Set priority for low latency and acknowledgements " .
                                "requires a set priority for normal packets."),
                            $rule->{'set-prio-low'}->__reference
                        ));
                    }
                    if (!empty((string)$rule->shaper1) || !empty((string)$rule->shaper2)) {
                        if (!empty((string)$rule->shaper2) && empty((string)$rule->shaper1)) {
                            $messages->appendMessage(new Message(
                                gettext("A shaper is required when configuring one in the reverse direction."),
                                $rule->shaper2->__reference
                            ));
                        } elseif (!empty((string)$rule->shaper2)) {
                            $tmp1 = $dntargets[(string)$rule->shaper1] ?? ['type' => '-'];
                            $tmp2 = $dntargets[(string)$rule->shaper2] ?? ['type' => '-'];
                            if ($tmp1['type'] != $tmp2['type']) {
                                $messages->appendMessage(new Message(
                                    gettext("Pipes and queues can not be combined."),
                                    $rule->shaper2->__reference
                                ));
                            }
                        }
                    }
                }
            }
        }

        foreach ($this->npt->rule->iterateItems() as $rule) {
            if ($validateFullModel || $rule->isFieldChanged()) {
                if (!empty((string)$rule->trackif)) {
                    if (!empty((string)$rule->destination_net)) {
                        $messages->appendMessage(new Message(
                            gettext('A track interface is only allowed without an external prefix.'),
                            $rule->trackif->__reference
                        ));
                    }

                    if (
                        (empty($config->interfaces->{$rule->interface}->ipaddrv6) ||
                        $config->interfaces->{$rule->interface}->ipaddrv6 != 'dhcp6') ||
                        empty($config->interfaces->{$rule->trackif}->{'track6-interface'}) ||
                        $config->interfaces->{$rule->trackif}->{'track6-interface'} != (string)$rule->interface
                    ) {
                        $messages->appendMessage(new Message(
                            gettext('This interface is not tracking the current rule interface.'),
                            $rule->trackif->__reference
                        ));
                    }
                }

                if (!empty((string)$rule->destination_net) && !empty((string)$rule->source_net)) {
                    /* defaults to /128 */
                    $dparts = explode('/', (string)$rule->destination_net . '/128');
                    $sparts = explode('/', (string)$rule->source_net . '/128');
                    if ($dparts[1] != $sparts[1]) {
                        $messages->appendMessage(new Message(
                            gettext("External subnet should match internal subnet."),
                            $rule->destination_net->__reference
                        ));
                    }
                }
            }
        }
        /* 1 to 1 mappings */
        foreach ($this->onetoone->rule->iterateItems() as $rule) {
            if ($validateFullModel || $rule->isFieldChanged()) {
                $ipprotos = [];
                $subnets = [];
                foreach (['source_net', 'destination_net', 'external'] as $fieldname) {
                    $subnets[$fieldname] = null;
                    if (Util::isSubnet($rule->$fieldname) || Util::isIpAddress($rule->$fieldname)) {
                        $ipprotos[$fieldname] = strpos($rule->$fieldname, ':') === false ? "inet" : "inet6";
                        $subnet_default = $ipprotos[$fieldname] == 'inet' ? '32' : '128';
                        $subnets[$fieldname] = explode('/', $rule->$fieldname . '/' . $subnet_default)[1];
                    }
                }
                if (count(array_unique(array_values($ipprotos))) > 1) {
                    foreach (array_keys($ipprotos) as $fieldname) {
                        $messages->appendMessage(new Message(
                            gettext("IP protocol families should match."),
                            $rule->$fieldname->__reference
                        ));
                    }
                }

                if ($rule->type == 'binat' && !empty((string)$rule->enabled)) {
                    /* binat rules are more strict,  when not enabled, we may skip the validations to ease migration */
                    if (empty($ipprotos['source_net'])) {
                        $messages->appendMessage(new Message(
                            gettext("For BINAT rules only addresses or subnets are allowed."),
                            $rule->source_net->__reference
                        ));
                    } elseif ($subnets['external'] != $subnets['source_net']) {
                        $messages->appendMessage(new Message(
                            gettext("External subnet should match internal subnet."),
                            $rule->external->__reference
                        ));
                    }
                }
            }
        }
        return $messages;
    }

    /**
     * Rollback this model to a previous version.
     * Make sure to remove this object afterwards, since its contents won't be updated.
     * @param $revision float|string revision number
     * @return bool action performed (backup revision existed)
     */
    public function rollback($revision)
    {
        $filename = Config::getInstance()->getBackupFilename($revision);
        if ($filename) {
            // fiddle with the dom, copy OPNsense->Firewall->Filter from backup to current config
            $sourcexml = simplexml_load_file($filename);
            if ($sourcexml->OPNsense->Firewall->Filter) {
                $sourcedom = dom_import_simplexml($sourcexml->OPNsense->Firewall->Filter);
                $targetxml = Config::getInstance()->object();
                $targetdom = dom_import_simplexml($targetxml->OPNsense->Firewall->Filter);
                $node = $targetdom->ownerDocument->importNode($sourcedom, true);
                $targetdom->parentNode->replaceChild($node, $targetdom);
                Config::getInstance()->save();
                return true;
            }
        }
        return false;
    }
}
