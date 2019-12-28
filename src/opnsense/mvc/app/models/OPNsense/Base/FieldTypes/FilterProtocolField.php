<?php

/**
 *    Copyright (C) 2015-2019 Deciso B.V.
 *    Copyright (C) 2020 Fabian Franz
 *    Copyright (C) 2004-2007 Scott Ullrich <sullrich@gmail.com>
 *    Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>
 *    Copyright (C) 2006 Peter Allgeyer <allgeyer@web.de>
 *    Copyright (C) 2008-2010 Ermal Luçi
 *    Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
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

namespace OPNsense\Base\FieldTypes;


class FilterProtocolField extends BaseListField
{
    /* IPv6 extension headers are skipped by the packet filter, we cannot police them */
    const IPv6_EXT = array('IPV6-ROUTE', 'IPV6-FRAG', 'IPV6-OPTS', 'IPV6-NONXT', 'MOBILITY-HEADER'); //NOSONAR proto name

    /**
     * @var null source field
     */
    protected $internalSourceField = null;

    /**
     * @var null source file pattern
     */
    protected $internalSourceFile = null;

    /**
     * @var bool automatically select all when none is selected
     */
    private $internalSelectAll = false;

    /**
     * @var bool sort by value (default is by key)
     */
    private $internalSortByValue = false;


    /**
     * @param string $value automatically select all when none is selected
     */
    public function setSelectAll($value)
    {
        if (strtoupper(trim($value)) == 'Y') {
            $this->internalSelectAll = true;
        } else {
            $this->internalSelectAll = false;
        }
    }


    /**
     * populate selection data
     */
    protected function actionPostLoadingEvent()
    {
        $this->internalOptionList = $this->getProtocols();
    }

    /**
     * change default sorting order (value vs key)
     * @param $value boolean value Y/N
     */
    public function setSortByValue($value)
    {
        if (trim(strtoupper($value)) == "Y") {
            $this->internalSortByValue = true;
        } else {
            $this->internalSortByValue = false;
        }
    }


    /**
     * @param string $value source field, pattern for source file
     */
    public function setSourceField($value)
    {
        $this->internalSourceField = basename($this->internalParentNode->$value);
    }

    /**
     * get valid options, descriptions and selected value
     * @return array
     */
    public function getNodeData()
    {
        // set sorting by key (default) or value
        if ($this->internalSortByValue) {
            natcasesort($this->internalOptionList);
        } else {
            ksort($this->internalOptionList);
        }
        return parent::getNodeData();
    }

    /**
     * @return array
     */
    protected function getProtocols(): array
    {
        $protocols = array('any' => 'any', 'tcp' => 'TCP', 'udp' => 'UDP', 'tcp/udp' => 'TCP/UDP', 'icmp' => 'ICMP',
            'esp' => 'ESP', 'ah' => 'AH', 'gre' => 'GRE', 'igmp' => 'IGMP', 'pim' => 'PIM', 'ospf' => 'OSPF');
        foreach (explode("\n", file_get_contents('/etc/protocols')) as $line) {
            if (substr($line, 0, 1) != "#") {
                $parts = preg_split('/\s+/', $line);
                if (count($parts) >= 4 && $parts[1] > 0) {
                    $protocol = trim(strtoupper($parts[0]));
                    if (!in_array($protocol, FilterProtocolField::IPv6_EXT) &&
                        !array_key_exists($protocol, $protocols)) {
                        $protocols[$protocol] = $protocol;
                    }
                }
            }
        }
        return $protocols;
    }
}
