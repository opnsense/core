<?php

/*
 * Copyright (C) 2015 Deciso B.V.
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

namespace OPNsense\TrafficShaper;

use OPNsense\Base\Messages\Message;
use OPNsense\Base\BaseModel;

/**
 * Class TrafficShaper
 * @package OPNsense\TrafficShaper
 */
class TrafficShaper extends BaseModel
{
    /**
     * {@inheritdoc}
     */
    public function performValidation($validateFullModel = false)
    {
        // standard model validations
        $max_bandwidth = 4294967295; // bps
        $messages = parent::performValidation($validateFullModel);
        $all_nodes = $this->getFlatNodes();
        foreach ($all_nodes as $key => $node) {
            if ($validateFullModel || $node->isFieldChanged()) {
                $parentNode = $node->getParentNode();
                if (in_array($node->getInternalXMLTagName(), ['bandwidth', 'bandwidthMetric'])) {
                    $currentval = (int)(string)$parentNode->bandwidth;
                    $maximumval = $max_bandwidth;
                    if ($parentNode->bandwidthMetric == "Kbit") {
                        $maximumval /= 1000;
                    } elseif ($parentNode->bandwidthMetric == "Mbit") {
                        $maximumval /= 1000000;
                    } elseif ($parentNode->bandwidthMetric == "Gbit") {
                        $maximumval /= 1000000000;
                    }
                    if ($currentval > $maximumval) {
                        $messages->appendMessage(new Message(
                            sprintf(
                                gettext('%d %s/s exceeds the maximum bandwidth of %d %s/s.'),
                                $currentval,
                                $parentNode->bandwidthMetric,
                                $maximumval,
                                $parentNode->bandwidthMetric
                            ),
                            $key
                        ));
                    }
                }
            }
        }
        return $messages;
    }


    /**
     * generate new Id by filling a gap or add 1 to the last
     * @param int $startAt start search at number
     * @param array $allIds all reserved id's
     * @return int next number
     */
    private function generateNewId($startAt, $allIds)
    {
        $newId = $startAt;
        for ($i = 0; $i < count($allIds); ++$i) {
            if ($allIds[$i] > $newId && isset($allIds[$i + 1])) {
                if ($allIds[$i + 1] - $allIds[$i] > 1) {
                    // gap found
                    $newId = $allIds[$i] + 1;
                    break;
                }
            } elseif ($allIds[$i] >= $newId) {
                // last item is higher than target
                $newId = $allIds[$i] + 1;
            }
        }

        return $newId;
    }

    /**
     * Generate a new pipe number
     * The first 10000 id's are automatically reserved for internal usage.
     * @return ArrayField
     */
    public function newPipeNumber()
    {
        $allpipes = array();
        foreach ($this->pipes->pipe->iterateItems() as $pipe) {
            $allpipes[] = (string)$pipe->number;
        }
        sort($allpipes);
        return $this->generateNewId(10000, $allpipes);
    }

    /**
     * Generate a new queue number
     * The first 10000 id's are automatically reserved for internal usage.
     * @return ArrayField
     */
    public function newQueueNumber()
    {
        $allqueues = array();
        foreach ($this->queues->queue->iterateItems() as $queue) {
            $allqueues[] = (string)$queue->number;
        }
        sort($allqueues);
        return $this->generateNewId(10000, $allqueues);
    }

    /**
     * retrieve last generated rule sequence number
     */
    public function getMaxRuleSequence()
    {
        $seq = 0;
        foreach ($this->rules->rule->iterateItems() as $rule) {
            if ((string)$rule->sequence > $seq) {
                $seq = (string)$rule->sequence;
            }
        }

        return $seq;
    }

    /**
     * return whether the shaper is currently in use
     */
    public function isEnabled()
    {
        foreach ($this->pipes->pipe->iterateItems() as $item) {
            if ((string)$item->enabled == '1') {
                return true;
            }
        }
        return false;
    }
}
