<?php
/**
 *    Copyright (C) 2015 Deciso B.V.
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
namespace OPNsense\TrafficShaper;

use OPNsense\Base\BaseModel;

/**
 * Class TrafficShaper
 * @package OPNsense\TrafficShaper
 */
class TrafficShaper extends BaseModel
{
    /**
     * generate new Id by filling a gap or add 1 to the last
     * @param int $startAt start search at number
     * @param array $allIds all reserved id's
     * @return int next number
     */
    private function generateNewId($startAt, $allIds)
    {
        $newId = $startAt;
        for ($i=0; $i < count($allIds); ++$i) {
            if ($allIds[$i] > $newId && isset($allIds[$i+1])) {
                if ($allIds[$i+1] - $allIds[$i] > 1) {
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
     * Add new pipe to shaper, generate new number if none is given.
     * The first 10000 id's are automatically reserved for internal usage.
     * @param null $pipenr new pipe number
     * @return ArrayField
     */
    public function addPipe($pipenr = null)
    {
        $allpipes = array();
        foreach ($this->pipes->pipe->__items as $uuid => $pipe) {
            if ($pipenr != null && $pipenr == $pipe->number->__toString()) {
                // pipe found, return
                return $pipe;
            } elseif ($pipenr == null) {
                // collect pipe numbers to find first possible item
                $allpipes[] = $pipe->number->__toString();
            }
        }
        sort($allpipes);

        if ($pipenr == null) {
            // generate new pipe number
            $newId = $this->generateNewId(10000, $allpipes);
        } else {
            $newId = $pipenr;
        }

        $pipe = $this->pipes->pipe->add();
        $pipe->number = $newId;
        return $pipe;
    }

    /**
     * Add new queue to shaper, generate new number if none is given.
     * The first 10000 id's are automatically reserved for internal usage.
     * @param null $queuenr new queue number
     * @return ArrayField
     */
    public function addQueue($queuenr = null)
    {
        $allqueues = array();
        foreach ($this->queues->queue->__items as $uuid => $queue) {
            if ($queuenr != null && $queuenr == $queue->number->__toString()) {
                // queue found, return
                return $queue;
            } elseif ($queuenr == null) {
                // collect pipe numbers to find first possible item
                $allqueues[] = $queue->number->__toString();
            }
        }
        sort($allqueues);

        if ($queuenr == null) {
            // generate new queue number
            $newId = $this->generateNewId(10000, $allqueues);
        } else {
            $newId = $queuenr;
        }

        $queue = $this->queues->queue->add();
        $queue->number = $newId;
        return $queue;
    }

    /**
     * retrieve last generated rule sequence number
     */
    public function getMaxRuleSequence()
    {
        $seq = 0;
        foreach ($this->rules->rule->__items as $rule) {
            if ((string)$rule->sequence > $seq) {
                $seq = (string)$rule->sequence;
            }
        }

        return $seq;
    }

    /**
     * retrieve last generated rule sequence number
     */
    public function isEnabled()
    {
        foreach ($this->pipes->pipe->__items as $item) {
            if ((string)$item->enabled == "1") {
                return true;
            }
        }
        return false;
    }
}
