<?php

/*
 * Copyright (C) 2022 Deciso B.V.
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

namespace OPNsense\Interfaces;

use OPNsense\Base\BaseModel;
use OPNsense\Interfaces\FieldTypes\ArrayField;

class Iface extends BaseModel
{
    /**
     * parse model and config xml to object model using types in FieldTypes.
     *
     * @param \SimpleXMLElement $xml           model xml data (from items section)
     * @param \SimpleXMLElement $config_data   (current) config data
     * @param BaseField         $internal_data output structure using FieldTypes,rootnode is internalData
     *
     * @throws ModelException      parse error
     * @throws ReflectionException
     */
    protected function parseXml(&$xml, &$config_data, &$internal_data)
    {
        if ('interfaces' == $config_data->getName()) {
            $config_data = $this->toAPISchema($config_data, 'interface');

            return parent::parseXml($xml, $config_data, $internal_data);
        }

        return parent::parseXml($xml, $config_data, $internal_data);
    }

    /**
     * render xml document from model including all parent nodes.
     * (parent nodes are included to ease testing).
     *
     * @return \SimpleXMLElement xml representation of the model
     */
    public function toXML()
    {
        $config_data = parent::toXML();

        return $this->toConfigSchema($config_data);
    }

    private function toAPISchema($root, $itemTagName)
    {
        $newRoot = new \SimpleXMLElement("<{$root->getName()}/>");

        foreach ($root->children() as $ifname => $node) {
            $newChild = $newRoot->addChild($itemTagName);
            $newChild->addAttribute('uuid', ArrayField::encodeUUID($ifname));

            $type = 'none';
            $type6 = 'none';

            foreach ($node->children() as $key => $value) {
                switch ($key) {
                    case 'ipaddr':
                        switch ($value) {
                            case 'dhcp':
                            case 'ppp':
                            case 'pppoe':
                            case 'ppptp':
                            case 'l2tp':
                                $type = $value;
                                $value = '';
                                break;

                            default:
                                $type = 'static';
                        }
                        break;

                    case 'ipaddrv6':
                        switch ($value) {
                            case 'dhcp6':
                            case 'slaac':
                            case '6rd':
                            case '6to4':
                            case 'track6':
                                $type6 = $value;
                                $value = '';
                                break;

                            default:
                                $type6 = 'static';
                        }
                        break;
                }

                $newChild->addChild($key, $value);
            }

            $newChild->addChild('type', $type);
            $newChild->addChild('type6', $type6);
        }

        return $newRoot;
    }

    private function toConfigSchema($root)
    {
        $newRoot = new \SimpleXMLElement("<{$root->getName()}/>");

        foreach ($root->children() as $node) {
            $ifname = ArrayField::decodeUUID($node->attributes()['uuid']);
            $newChild = $newRoot->addChild($ifname);

            $ipaddr = null;
            $ipaddrv6 = null;

            foreach ($node->children() as $key => $value) {
                switch ($key) {
                    case 'type':
                        switch ($value) {
                            case 'static':
                                $ipaddr = null;
                                break;

                            case 'none':
                                $ipaddr = '';
                                break;

                            default:
                                $ipaddr = $value;
                        }
                        break;

                    case 'type6':
                        switch ($value) {
                            case 'static':
                                $ipaddrv6 = null;
                                break;

                            case 'none':
                                $ipaddrv6 = '';
                                break;

                            default:
                                $ipaddrv6 = $value;
                        }
                        break;
                }
            }

            foreach ($node->children() as $key => $value) {
                switch ($key) {
                    case 'type':
                    case 'type6':
                        $value = '';
                        break;

                    case 'ipaddr':
                        if (null !== $ipaddr) {
                            $value = $ipaddr;
                        }
                        break;

                    case 'ipaddrv6':
                        if (null !== $ipaddrv6) {
                            $value = $ipaddrv6;
                        }
                        break;
                }

                if ('' != $value) {
                    $newChild->addChild($key, $value);
                }
            }
        }

        return $newRoot;
    }
}
