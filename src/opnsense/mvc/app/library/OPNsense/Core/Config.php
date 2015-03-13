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
    private $statusIsValid = false;


    /**
     * @return bool return (last known) status of this configuration
     */
    public function isValid()
    {
        return $this->statusIsValid;
    }

    /**
     * serialize xml to array structure (backwards compatibility mode)
     * @param null|array $forceList force specific tags to be contained in a list.
     * @param DOMNode $node
     * @return string|array
     * @throws ConfigException
     */
    public function toArray($forceList = null, $node = null)
    {
        $result = array();
        $this->checkvalid();
        // root node
        if ($node == null) {
            $node = $this->simplexml;
        }

        foreach ($node->children() as $xmlNode) {
            if ($xmlNode->count() > 0) {
                $tmpNode = $this->toArray($forceList, $xmlNode);
                if (array_key_exists($xmlNode->getName(), $result)) {
                    $old_content = $result[$xmlNode->getName()];
                    // check if array content is associative, if move items to list
                    if (array_keys($old_content) !== range(0, count($old_content) - 1) ||
                        array_key_exists($xmlNode->getName(), $forceList)) {
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
                    if (array_key_exists($xmlNode->getName(), $forceList)) {
                        $result[$xmlNode->getName()] = array();
                        $result[$xmlNode->getName()][] = $xmlNode->__toString();
                    } else {
                        $result[$xmlNode->getName()] = $xmlNode->__toString();
                    }
                }
            }
        }

        return $result;
    }

    /**
     * update (reset) config with array structure (backwards compatibility mode)
     * @param $source source array structure
     * @param null $node simplexml node
     * @param null|string $parentTagName
     * @throws ConfigException
     */
    public function fromArray($source, $node = null, $parentTagName = null)
    {
        $this->checkvalid();

        // root node
        if ($node == null) {
            $this->configxml = new \DOMDocument('1.0');
            $this->configxml->loadXML('<'.$this->simplexml[0]->getName().'/>');
            $this->simplexml = simplexml_import_dom($this->configxml);
            $node = $this->simplexml ;
        }

        foreach ($source as $itemKey => $itemValue) {
            if (is_numeric($itemKey)) {
                // recurring tag (content), use parent tagname.
                $childNode = $node->addChild($parentTagName);
            } elseif (is_array($itemValue) && !(array_keys($itemValue) !== range(0, count($itemValue) - 1))) {
                // recurring tag, skip placeholder.
                $childNode = $node;
            } else {
                // add new child
                $childNode = $node->addChild($itemKey);
            }

            // set content, propagate container items.
            if (is_array($itemValue)) {
                $this->fromArray($itemValue, $childNode, $itemKey);
            } else {
                $childNode[0] = $itemValue;
            }
        }
    }

    /**
     * @throws ConfigException
     */
    private function checkvalid()
    {
        if (!$this->statusIsValid) {
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

        set_error_handler(
            function() {
                throw new ConfigException("invalid config xml") ;
            }
        );

        $this->configxml = new \DOMDocument('1.0');
        $this->configxml->loadXML($xml);
        $this->simplexml = simplexml_import_dom($this->configxml);

        restore_error_handler();
        $this->statusIsValid = true;
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
     * update config revision information (ROOT.revision tag)
     * @param array|null $revision revision tag (associative array)
     * @param \SimpleXMLElement|null pass trough xml node
     */
    private function updateRevision($revision, $node = null)
    {
        // input must be an array
        if (is_array($revision)) {
            if ($node == null) {
                if (isset($this->simplexml->revision)) {
                    $node = $this->simplexml->revision;
                } else {
                    $node = $this->simplexml->addChild("revision");
                }
            }
            foreach ($revision as $revKey => $revItem) {
                if (isset($node->{$revKey})) {
                    // key already in revision object
                    $childNode = $node->{$revKey};
                } else {
                    $childNode = $node->addChild($revKey);
                }
                if (is_array($revItem)) {
                    $this->updateRevision($revItem, $childNode);
                } else {
                    $childNode[0] = $revItem;
                }
            }
        }
    }

    /**
     * backup current (running) config
     */
    public function backup()
    {
        $target_dir = dirname($this->config_file)."/backup/";
        $target_filename = "config-".time().".xml";

        if (!file_exists($target_dir)) {
            // create backup directory if it's missing
            mkdir($target_dir);
        }
        copy($this->config_file, $target_dir.$target_filename);

    }

    /**
     * return list of config backups
     * @param bool $fetchRevisionInfo fetch revision information and return detailed information. (key/value)
     * @return array list of backups
     */
    public function getBackups($fetchRevisionInfo = false)
    {
        $target_dir = dirname($this->config_file)."/backup/";
        if (file_exists($target_dir)) {
            $backups = glob($target_dir."config*.xml");
            // sort by date (descending)
            rsort($backups);
            if (!$fetchRevisionInfo) {
                return $backups;
            } else {
                $result = array ();
                foreach ($backups as $filename) {
                    // try to read backup info from xml
                    $xmlNode = simplexml_load_file($filename, "SimpleXMLElement", LIBXML_NOERROR |  LIBXML_ERR_NONE);
                    if (isset($xmlNode->revision)) {
                        $result[$filename] = $this->toArray(null, $xmlNode->revision);
                    }
                    // append filesize to revision info object
                    $result[$filename]['filesize'] = filesize($filename);
                }

                return $result;
            }
        }

        return array();
    }

    /**
     * restore and load backup config
     * @param $filename
     * @return bool  restored, valid config loaded
     * @throws ConfigException no config loaded
     */
    public function restoreBackup($filename)
    {

        if ($this->statusIsValid) {
            // if current config is valid,
            $configxml = $this->configxml;
            $simplexml = $this->simplexml;
            try {
                // try to restore config
                copy($filename, $this->config_file) ;
                $this->load();
                return true;
            } catch (ConfigException $e) {
                // copy / load failed, restore previous version
                $this->configxml = $configxml;
                $this->simplexml = $simplexml;
                $this->statusIsValid = true;
                $this->save(null, true);
            }
        } else {
            // we don't have a valid config loaded, just copy and load the requested one
            copy($filename, $this->config_file) ;
            $this->load();
            return true;
        }

        return false;
    }

    /**
     * save config to filesystem
     * @param array|null $revision revision tag (associative array)
     * @param bool $backup do not backup current config
     * @throws ConfigException
     */
    public function save($revision = null, $backup = true)
    {
        $this->checkvalid();

        if ($backup) {
            $this->backup();
        }

        // update revision information ROOT.revision tag
        $this->updateRevision($revision);

        // serialize to text
        $xml_text = $this->__toString();

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
