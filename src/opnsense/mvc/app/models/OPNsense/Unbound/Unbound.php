<?php

/*
 * Copyright (C) 2023 Deciso B.V.
 * Copyright (C) 2021 Michael Muenz <m.muenz@gmail.com>
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

namespace OPNsense\Unbound;

use OPNsense\Base\BaseModel;
use OPNsense\Base\Messages\Message;
use OPNsense\Core\Backend;

class Unbound extends BaseModel
{
    public function performValidation($validateFullModel = false)
    {
        $messages = parent::performValidation($validateFullModel);

        if (
            ($validateFullModel || $this->general->enabled->isFieldChanged() || $this->general->port->isFieldChanged()) &&
            !empty((string)$this->general->enabled)
        ) {
            foreach (json_decode((new Backend())->configdpRun('service list'), true) as $service) {
                if (empty($service['dns_ports'])) {
                    continue;
                }
                if (!is_array($service['dns_ports'])) {
                    syslog(LOG_ERR, sprintf('Service %s (%s) reported a faulty "dns_ports" entry.', $service['description'], $service['name']));
                    continue;
                }
                if ($service['name'] != 'unbound' && in_array((string)$this->general->port, $service['dns_ports'])) {
                    $messages->appendMessage(new Message(
                        sprintf(gettext('%s is currently using this port.'), $service['description']),
                        'general.' . $this->general->port->getInternalXMLTagName()
                    ));
                    break;
                }
            }
        }

        foreach ($this->dnsbl->blocklist->iterateItems() as $node) {
            if ($node->isFieldChanged() || $validateFullModel) {
                /* Extract all subnets (eg x.x.x.x/24 --> 24) and protocol families */
                $sizes = array_unique(
                    array_map(fn($x) => explode("/", $x)[1] ?? null, explode(",", $node->source_nets))
                );
                $ipproto = array_unique(
                    array_map(fn($x) => strpos($x, ':') == false ? 'inet' : 'inet6', explode(",", $node->source_nets))
                );
                if (count($sizes) > 1) {
                    $messages->appendMessage(new Message(
                        gettext('All offered networks should be equally sized to avoid overlaps.'),
                        $node->source_nets->__reference
                    ));
                }
                if (count($ipproto) > 1) {
                    $messages->appendMessage(new Message(
                        gettext('All offered networks should use the same IP protocol.'),
                        $node->source_nets->__reference
                    ));
                }
            }
        }

        return $messages;
    }
}
