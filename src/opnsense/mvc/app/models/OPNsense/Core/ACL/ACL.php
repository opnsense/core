<?php

/**
 *    Copyright (C) 2017 Deciso B.V.
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

namespace OPNsense\Core\ACL;

/**
 * Class ACL, access control list wrapper
 * @package OPNsense\Core
 */
class ACL
{
    /**
     * @param $xmlNode
     */
    private $aclXML = null;

    /**
     * Construct new ACL for an application
     * @param string $module_root location on disk for this ACL
     * @throws \Exception
     */
    public function __construct($module_root)
    {
        $acl_cfg_xml = $module_root . '/ACL/ACL.xml';
        if (file_exists($acl_cfg_xml)) {
            // load ACL xml file and perform some basic validation
            $this->aclXML = simplexml_load_file($acl_cfg_xml);
            if ($this->aclXML === false) {
                throw new \Exception('ACL xml ' . $acl_cfg_xml . ' not valid');
            }
            if ($this->aclXML->getName() != "acl") {
                throw new \Exception('ACL xml ' . $acl_cfg_xml . ' seems to be of wrong type');
            }
        }
    }

    /**
     * return raw xml definition
     * @return SimpleXMLElement|null
     */
    public function getXML()
    {
        return $this->aclXML;
    }

    /**
     * get ACL contents as simple named array, containing name and endpoint match criteria
     * @return array
     */
    public function get()
    {
        $result = array();
        if ($this->aclXML) {
            foreach ($this->aclXML as $aclID => $ACLnode) {
                // an acl should minimal have a name, without one skip processing.
                if (isset($ACLnode->name)) {
                    $aclPayload = array();
                    $aclPayload['name'] = (string)$ACLnode->name;
                    if (isset($ACLnode->patterns->pattern)) {
                        $aclPayload['match'] = array();
                        foreach ($ACLnode->patterns->pattern as $pattern) {
                            $aclPayload['match'][] = (string)$pattern;
                        }
                    }
                    $result[$aclID] = $aclPayload;
                }
            }
        }
        return $result;
    }

    /**
     * update provided acl array with content from this list
     * @param array &$acltags
     */
    public function update(&$acltags)
    {
        foreach ($this->get() as $aclID => $ACLnode) {
            $acltags[$aclID] = $ACLnode;
        }
    }
}
