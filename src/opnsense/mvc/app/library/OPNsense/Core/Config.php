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
namespace OPNsense\Core;

use \Phalcon\DI\FactoryDefault;

/**
 * Class Config provides access to systems config xml
 * @package Core
 */
class Config extends Singleton
{

    /**
     * config file location ( path + name )
     * @var string
     */
    private $config_file = "";

    /**
     * XMLDocument type reference to config
     * @var \DOMDocument
     */
    private $configxml = null ;

    /**
     * SimpleXML type reference to config
     * @var SimpleXML
     */
    private $simplexml = null;

    /**
     * status field: valid config loaded
     * @var bool
     */
    private $isValid = false;

    /**
     * serialize xml to array structure (backwards compatibility mode)
     * @param DOMNode $node
     * @return string|array
     * @throws ConfigException
     */
    public function toArray($node = null)
    {
        $result = array();
        $this->checkvalid();
        // root node
        if ($node == null) {
            $node = $this->simplexml;
        }

        foreach ($node->children() as $xmlNode) {
            if ($xmlNode->count() > 0) {
                $tmpNode = $this->toArray($xmlNode);
                if (array_key_exists($xmlNode->getName(), $result)) {
                    $old_content = $result[$xmlNode->getName()];
                    // check if array content is associative, if move items to list
                    if (array_keys($old_content) !== range(0, count($old_content) - 1)) {
                        $result[$xmlNode->getName()] = array();
                        $result[$xmlNode->getName()][] = $old_content;
                    }
                    $result[$xmlNode->getName()][] = $tmpNode;
                } else {
                    $result[$xmlNode->getName()] = $tmpNode;
                }
            } else {
                if (array_key_exists($xmlNode->getName(), $result)) {
                    // repeating item
                    if (!is_array($result[$xmlNode->getName()])) {
                        // move first item into list
                        $tmp = $result[$xmlNode->getName()];
                        $result[$xmlNode->getName()] = array();
                        $result[$xmlNode->getName()][] = $tmp;
                    }
                    $result[$xmlNode->getName()][] = $xmlNode->__toString();
                } else {
                    // single content item
                    $result[$xmlNode->getName()] = $xmlNode->__toString();
                }
            }
        }

        return $result;
    }

    /**
     * update config with array structure (backwards compatibility mode)
     * @param $source source array structure
     * @param null $node simplexml node
     * @return number of updates
     * @throws ConfigException
     */
    public function fromArray($source, $node = null)
    {
        $this->checkvalid();
        $updatecount = 0;
        // root node
        if ($node == null) {
            $node = $this->simplexml;
        }

        $activeNodes = array();
        $useIndex = false ; // tag recurring, use index
        foreach ($source as $itemKey => $itemValue) {
            // find / create target node
            if (is_numeric($itemKey)) {
                $useIndex = true;
                // recurring item, select by number
                $items = $node->xpath("./*");
                if (count($items) > $itemKey) {
                    $targetNode = $items[$itemKey];
                } else {
                    // new sequence, add item
                    $targetNode = $node->addChild($node->getName());
                }
            } elseif (!isset($node->{$itemKey})) {
                // new node, add
                if (is_numeric($itemKey)) {
                    $targetNode = $node->xpath("..")[0]->addChild($node->getName());
                } else {
                    $targetNode = $node->addChild($itemKey);
                }
            } else {
                // if items are a recurring list, skip one level.
                $targetNode = $node->xpath("//".$itemKey);
                if (count($targetNode) > 1) {
                    $targetNode = $node ;
                } else {
                    $targetNode = $node->{$itemKey};
                }
            }

            // add active node
            $activeNodes[] = $itemKey;

            // (propagate) update
            if (is_array($itemValue)) {
                $updatecount += $this->fromArray($itemValue, $targetNode);
            } elseif ($itemValue != $targetNode->{$itemKey}->__toString()) {
                $targetNode->{$itemKey} = $itemValue;
                $updatecount++;
            }

        }


        // remove nodes not in source
        $removeItems = array();
        $itemId = 0;
        foreach ($node->children() as $xmlNode) {
            if ((!in_array($itemId, $activeNodes) && $useIndex) ||
                !in_array($xmlNode->getName(), $activeNodes)
            ) {
                $removeItems[] = $xmlNode;
            }
            $itemId++;
        }

        foreach ($removeItems as $xmlNode) {
            $dom=dom_import_simplexml($xmlNode);
            $dom->parentNode->removeChild($dom);
            $updatecount++;
        }

        // return number of changes
        return $updatecount;

    }

    /**
     * @throws ConfigException
     */
    private function checkvalid()
    {
        if (!$this->isValid) {
            throw new ConfigException('no valid config loaded') ;
        }
    }


    /**
     * Execute a xpath expression on config.xml
     * @param $query
     * @return \DOMNodeList
     * @throws ConfigException
     */
    public function xpath($query)
    {
        $this->checkvalid();
        $xpath = new \DOMXPath($this->configxml);
        return  $xpath->query($query);
    }


    /**
     * object representation of xml document via simplexml, references the same underlying model
     * @return SimpleXML
     * @throws ConfigException
     */
    public function object()
    {
        $this->checkvalid();
        return $this->simplexml;
    }

    /**
     * get DOMDocument
     * @return XMLDocument
     * @throws ConfigException
     */
    public function getDOM()
    {
        $this->checkvalid();
        return $this->configxml;

    }


    /**
     * init new config object, try to load current configuration
     * (executed via Singleton)
     */
    protected function init()
    {
        $this->config_file = FactoryDefault::getDefault()->get('config')->globals->config_path . "config.xml";
        try {
            $this->load();
        } catch (\Exception $e) {
            $this->configxml = null ;
        }

    }


    /**
     * Load config file
     * @throws ConfigException
     */
    private function load()
    {
        // exception handling
        if (!file_exists($this->config_file)) {
            throw new ConfigException('file not found') ;
        }
        $xml = file_get_contents($this->config_file);
        if (trim($xml) == '') {
            throw new ConfigException('empty file') ;
        }

        $this->configxml = new \DOMDocument('1.0');
        $this->configxml->loadXML($xml);
        $this->simplexml = simplexml_import_dom($this->configxml);
        $this->isValid = true;
    }

    /**
     * return xml text representation of this config
     * @return mixed string interpretation of this object
     */
    public function __toString()
    {
        // reformat XML (pretty print)
        $dom = new \DOMDocument;
        $dom->formatOutput = true;
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($this->configxml->saveXML());

        return $dom->saveXML();

    }

    /**
     * backup current (running) config
     * @param string|null $message log message
     */
    private function backup($message)
    {
        $target_dir = dirname($this->config_file)."/backup/";
        $target_filename = "config-".time().".xml";

        if (file_exists($target_dir)) {
            copy($this->config_file, $target_dir.$target_filename);
        }

    }

    /**
     * save config to filesystem
     * @param string|null $message log message
     * @param bool $nobackup do not backup current config
     * @throws ConfigException
     */
    public function save($message = null, $nobackup = false)
    {
        $xml_text = $this->__toString();
        if ($nobackup == false) {
            $this->backup($message);
        }

        // save configuration, try to obtain a lock before doing so.
        $target_filename = $this->config_file;
        if (file_exists($target_filename)) {
            $fp = fopen($target_filename, "r+");
        } else {
            // apparently we're missing the config, not expected but open a new one.
            $fp = fopen($target_filename, "w+");
        }

        if (flock($fp, LOCK_EX)) {
            // lock aquired, truncate and write new data
            ftruncate($fp, 0);
            fwrite($fp, $xml_text);
            // flush and unlock
            fflush($fp);
            flock($fp, LOCK_UN);
        } else {
            throw new ConfigException("Unable to lock config");
        }


    }
}
