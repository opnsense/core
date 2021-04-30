<?php

/*
 * Copyright (C) 2015 Deciso B.V.
 * Copyright (C) 2017 Fabian Franz
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

namespace OPNsense\Proxy;

use OPNsense\Base\BaseModel;

/**
 * Class Proxy
 * @package OPNsense\Proxy
 */
class Proxy extends BaseModel
{
    public function performValidation($validateFullModel = false)
    {
        // perform standard validations
        $result = parent::performValidation($validateFullModel);
        // add validation for PAC match
        foreach ($this->getFlatNodes() as $key => $node) {
            if ($validateFullModel || $node->isFieldChanged()) {
                // if match_type has changed we need to make some fields required
                if ($node->getInternalXMLTagName() == "match_type") {
                    $match = $node->getParentNode();
                    $match_type = (string)$match->match_type;
                    switch ($match_type) {
                        case 'url_matches':
                            if (strlen((string)$match->url) == 0) {
                                $result->appendMessage(new \Phalcon\Messages\Message(
                                    gettext('URL must be set.'),
                                    'pac.match.url'
                                ));
                            }
                            break;
                        case 'hostname_matches':
                        case 'dns_domain_is':
                        case 'is_resolvable':
                            if (strlen((string)$match->hostname) == 0) {
                                $result->appendMessage(new \Phalcon\Messages\Message(
                                    gettext('Hostname must be set.'),
                                    'pac.match.hostname'
                                ));
                            }
                            break;
                        case 'destination_in_net':
                        case 'my_ip_in_net':
                            if (strlen((string)$match->network) == 0) {
                                $result->appendMessage(new \Phalcon\Messages\Message(
                                    gettext('Network must be set.'),
                                    'pac.match.network'
                                ));
                            }
                        case 'plain_hostname':
                        case 'dns_domain_levels':
                        case 'weekday_range':
                        case 'date_range':
                        case 'time_range':
                            break; // no special validation
                    }
                }
            }
        }
        return $result;
    }
}
