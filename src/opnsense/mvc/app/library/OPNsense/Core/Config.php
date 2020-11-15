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

namespace OPNsense\Core;

use Phalcon\DI\FactoryDefault;
use Phalcon\Logger\Adapter\Syslog;

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
     * config file handle
     * @var null|file
     */
    private $config_file_handle = null;

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
     * return last known status of this configuration (valid or not)
     * @return bool return (last known) status of this configuration
     */
    public function isValid()
    {
        return $this->statusIsValid;
    }

    /**
     * check if array is a sequential type.
     * @param &array $arrayData array structure to check
     * @return bool
     */
    private function isArraySequential(&$arrayData)
    {
        return ctype_digit(implode('', array_keys($arrayData)));
    }

    /**
     * serialize xml to array structure (backwards compatibility mode)
     * @param null|array $forceList force specific tags to be contained in a list.
     * @param DOMNode $node node to read
     * @return string|array converted node data
     * @throws ConfigException when config could not be parsed
     */
    public function toArray($forceList = null, $node = null)
    {
        $result = array();
        $this->checkvalid();
        // root node
        if ($node == null) {
            $node = $this->simplexml;
        }

        // copy attributes to @attribute key item
        foreach ($node->attributes() as $AttrKey => $AttrValue) {
            if (!isset($result['@attributes'])) {
                $result['@attributes'] = array();
            }
            $result['@attributes'][$AttrKey] = $AttrValue->__toString();
        }
        // iterate xml children
        foreach ($node->children() as $xmlNode) {
            $xmlNodeName = $xmlNode->getName();
            if ($xmlNode->count() > 0) {
                $tmpNode = $this->toArray($forceList, $xmlNode);
                if (isset($result[$xmlNodeName])) {
                    $old_content = $result[$xmlNodeName];
                    // check if array content is associative, move items to new list
                    // (handles first item of specific type)
                    if (!$this->isArraySequential($old_content)) {
                        $result[$xmlNodeName] = array();
                        $result[$xmlNodeName][] = $old_content;
                    }
                    $result[$xmlNodeName][] = $tmpNode;
                } elseif (isset($forceList[$xmlNodeName])) {
                    // force tag in an array
                    $result[$xmlNodeName] = array();
                    $result[$xmlNodeName][] = $tmpNode;
                } else {
                    $result[$xmlNodeName] = $tmpNode;
                }
            } else {
                if (isset($result[$xmlNodeName])) {
                    // repeating item
                    if (!is_array($result[$xmlNodeName])) {
                        // move first item into list
                        $tmp = $result[$xmlNodeName];
                        $result[$xmlNodeName] = array();
                        $result[$xmlNodeName][] = $tmp;
                    }
                    $result[$xmlNodeName][] = $xmlNode->__toString();
                } else {
                    // single content item
                    if (isset($forceList[$xmlNodeName])) {
                        $result[$xmlNodeName] = array();
                        if ($xmlNode->__toString() != null && trim($xmlNode->__toString()) !== "") {
                            $result[$xmlNodeName][] = $xmlNode->__toString();
                        }
                    } else {
                        $result[$xmlNodeName] = $xmlNode->__toString();
                    }
                }
            }
        }

        return $result;
    }


    /**
     * convert an arbitrary config xml file to an array
     * @param $filename config xml filename to parse
     * @param null $forceList items to treat as list
     * @return array interpretation of config file
     * @throws ConfigException when config could not be parsed
     */
    public function toArrayFromFile($filename, $forceList = null)
    {
        $fp = fopen($filename, "r");
        $xml = $this->loadFromStream($fp);
        fclose($fp);
        return $this->toArray($forceList, $xml);
    }

    /**
     * update (reset) config with array structure (backwards compatibility mode)
     * @param array $source source array structure
     * @param null $node simplexml node
     * @param null|string $parentTagName
     * @throws ConfigException when config could not be parsed
     */
    public function fromArray(array $source, $node = null, $parentTagName = null)
    {
        $this->checkvalid();

        // root node
        if ($node == null) {
            $this->simplexml = simplexml_load_string('<' . $this->simplexml[0]->getName() . '/>');
            $node = $this->simplexml;
            // invalidate object on warnings/errors (prevent save from happening)
            set_error_handler(
                function () {
                    $this->statusIsValid = false;
                }
            );
        }

        foreach ($source as $itemKey => $itemValue) {
            if (
                (is_bool($itemValue) && $itemValue == false) ||   // skip empty booleans
                $itemKey === null || trim($itemKey) === ""        // skip empty tag names
            ) {
                continue;
            }
            if ($itemKey === '@attributes') {
                // copy xml attributes
                foreach ($itemValue as $attrKey => $attrValue) {
                    $node->addAttribute($attrKey, $attrValue);
                }
                continue;
            } elseif (is_numeric($itemKey)) {
                // recurring tag (content), use parent tagname.
                $childNode = $node->addChild($parentTagName);
            } elseif (is_array($itemValue) && $this->isArraySequential($itemValue)) {
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

        // restore error handling on initial call
        if ($node == $this->simplexml) {
            restore_error_handler();
        }
    }

    /**
     * check if there's a valid config loaded, throws an error if config isn't valid.
     * @throws ConfigException when config could not be parsed
     */
    private function checkvalid()
    {
        if (!$this->statusIsValid) {
            throw new ConfigException('no valid config loaded');
        }
    }


    /**
     * Execute a xpath expression on config.xml (full DOM implementation)
     * @param string $query xpath expression
     * @return \DOMNodeList nodes
     * @throws ConfigException when config could not be parsed
     */
    public function xpath($query)
    {
        $this->checkvalid();

        $configxml = dom_import_simplexml($this->simplexml);
        $dom = new \DOMDocument('1.0');
        $dom_sxe = $dom->importNode($configxml, true);
        $dom->appendChild($dom_sxe);
        $xpath = new \DOMXPath($dom);
        return  $xpath->query($query);
    }


    /**
     * object representation of xml document via simplexml, references the same underlying model
     * @return SimpleXML configuration object
     * @throws ConfigException when config could not be parsed
     */
    public function object()
    {
        $this->checkvalid();
        return $this->simplexml;
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
            $this->simplexml = null;
            // there was an issue with loading the config, try to restore the last backup
            $backups = $this->getBackups();
            $logger = new Syslog("config", array('option' => LOG_PID, 'facility' => LOG_LOCAL4));
            if (count($backups) > 0) {
                // load last backup
                $logger->error(gettext('No valid config.xml found, attempting last known config restore.'));
                foreach ($backups as $backup) {
                    try {
                        $this->restoreBackup($backup);
                        return;
                    } catch (ConfigException $e) {
                        $logger->error("failed restoring " . $backup);
                    }
                }
            }
            // in case there are no backups, restore defaults.
            $logger->error(gettext('No valid config.xml found, attempting to restore factory config.'));
            $this->restoreBackup('/usr/local/etc/config.xml');
        }
    }

    /**
     * force a re-init of the object and reload the object
     */
    public function forceReload()
    {
        if ($this->config_file_handle !== null) {
            fclose($this->config_file_handle);
            $this->config_file_handle = null;
        }
        $this->init();
    }

    /**
     * load xml config from file handle
     * @param file $fp config xml source
     * @return \SimpleXMLElement root node
     * @throws ConfigException when config could not be parsed
     */
    private function loadFromStream($fp)
    {
        fseek($fp, 0);
        $xml = stream_get_contents($fp);
        if (trim($xml) == '') {
            throw new ConfigException('empty file');
        }

        set_error_handler(
            function () {
                // reset simplexml pointer on parse error.
                $result = null;
            }
        );

        $result = simplexml_load_string($xml);

        restore_error_handler();
        if ($result == null) {
            throw new ConfigException("invalid config xml");
        } else {
            return $result;
        }
    }

    /**
     * Load config file
     * @throws ConfigException
     */
    private function load()
    {
        $this->simplexml = null;
        $this->statusIsValid = false;

        // exception handling
        if (!file_exists($this->config_file)) {
            throw new ConfigException('file not found');
        }

        if (!is_resource($this->config_file_handle)) {
            if (is_writable($this->config_file)) {
                $this->config_file_handle = fopen($this->config_file, "r+");
            } else {
                // open in read-only mode
                $this->config_file_handle = fopen($this->config_file, "r");
            }
        }

        $this->simplexml = $this->loadFromStream($this->config_file_handle);
        $this->statusIsValid = true;
    }

    /**
     * return xml text representation of this config
     * @return mixed string interpretation of this object
     */
    public function __toString()
    {
        // reformat XML (pretty print)
        $dom = new \DOMDocument('1.0');

        // make sure our root element is always called "opnsense"
        $root = $dom->createElement('opnsense');
        $dom->appendChild($root);

        foreach ($this->simplexml as $node) {
            $domNode = dom_import_simplexml($node);
            $domNode = $root->ownerDocument->importNode($domNode, true);
            $root->appendChild($domNode);
        }

        $dom->formatOutput = true;
        $dom->preserveWhiteSpace = false;

        $dom->loadXML($dom->saveXML());

        return $dom->saveXML();
    }

    /**
     * update config revision information (ROOT.revision tag)
     * @param array|null $revision revision tag (associative array)
     * @param \SimpleXMLElement|null pass trough xml node
     */
    private function updateRevision($revision, $node = null, $timestamp = null)
    {
        // if revision info is not provided, create a default.
        if (!is_array($revision)) {
            $revision = array();
            // try to fetch userinfo from session
            if (!empty($_SESSION["Username"])) {
                $revision['username'] = $_SESSION["Username"];
            } else {
                $revision['username'] = "(system)";
            }
            if (!empty($_SERVER['REMOTE_ADDR'])) {
                $revision['username'] .= "@" . $_SERVER['REMOTE_ADDR'];
            }
            if (!empty($_SERVER['REQUEST_URI'])) {
                // when update revision is called from a controller, log the endpoint uri
                $revision['description'] = sprintf(gettext('%s made changes'), $_SERVER['REQUEST_URI']);
            } else {
                // called from a script, log script name and path
                $revision['description'] = sprintf(gettext('%s made changes'), $_SERVER['SCRIPT_NAME']);
            }
        }

        // always set timestamp
        $revision['time'] = empty($timestamp) ? microtime(true) : $timestamp;

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

    /**
     * backup current config
     * @return string target filename
     */
    public function backup()
    {
        $timestamp = microtime(true);
        $target_dir = dirname($this->config_file) . "/backup/";

        if (!file_exists($target_dir)) {
            // create backup directory if it is missing
            mkdir($target_dir);
        }
        if (file_exists($target_dir . "config-" . $timestamp . ".xml")) {
            // The new target backup filename shouldn't exists, because of the use of microtime.
            // in the unlikely event that we can process events too fast for microtime(), suffix with a more
            // precise tiestamp to ensure we can't miss a backup
            $target_filename = "config-" . $timestamp . "_" .  hrtime()[1] . ".xml";
        } else {
            $target_filename = "config-" . $timestamp . ".xml";
        }
        copy($this->config_file, $target_dir . $target_filename);

        return $target_dir . $target_filename;
    }

    /**
     * return list of config backups
     * @param bool $fetchRevisionInfo fetch revision information and return detailed information. (key/value)
     * @return array list of backups
     * @throws ConfigException when config could not be parsed
     */
    public function getBackups($fetchRevisionInfo = false)
    {
        $target_dir = dirname($this->config_file) . "/backup/";
        if (file_exists($target_dir)) {
            $backups = glob($target_dir . "config*.xml");
            // sort by date (descending)
            rsort($backups);
            if (!$fetchRevisionInfo) {
                return $backups;
            } else {
                $result = array ();
                foreach ($backups as $filename) {
                    // try to read backup info from xml
                    $xmlNode = @simplexml_load_file($filename, "SimpleXMLElement", LIBXML_NOERROR | LIBXML_ERR_NONE);
                    if (isset($xmlNode->revision)) {
                        $result[$filename] = $this->toArray(null, $xmlNode->revision);
                        $result[$filename]['version'] = $xmlNode->version->__toString();
                        $result[$filename]['filesize'] = filesize($filename);
                    }
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

        if ($this->isValid()) {
            // if current config is valid,
            $simplexml = $this->simplexml;
            $config_file_handle = $this->config_file_handle;
            try {
                // try to restore config
                copy($filename, $this->config_file);
                $this->load();
                return true;
            } catch (ConfigException $e) {
                // copy / load failed, restore previous version
                $this->simplexml = $simplexml;
                $this->config_file_handle = $config_file_handle;
                $this->statusIsValid = true;
                $this->save(null, true);
                return false;
            }
        } else {
            // we don't have a valid config loaded, just copy and load the requested one
            copy($filename, $this->config_file);
            $this->load();
            return true;
        }
    }

    /**
     * @return int number of backups to keep
     */
    public function backupCount()
    {
        if (
            $this->statusIsValid && isset($this->simplexml->system->backupcount)
            && intval($this->simplexml->system->backupcount) >= 0
        ) {
            return intval($this->simplexml->system->backupcount);
        } else {
            return 100;
        }
    }

    /**
     * return backup file path if revision exists
     * @param $revision revision timestamp (e.g. 1583766095.9337)
     * @return bool|string filename when available or false when not found
     */
    public function getBackupFilename($revision)
    {
        $tmp = preg_replace("/[^0-9.]/", "", $revision);
        $bckfilename = dirname($this->config_file) . "/backup/config-{$tmp}.xml";
        if (is_file($bckfilename)) {
            return $bckfilename;
        } else {
            return false;
        }
    }

    /**
     * remove old backups
     */
    private function cleanupBackups()
    {
        $revisions = $this->backupCount();

        $cnt = 1;
        foreach ($this->getBackups() as $filename) {
            if ($cnt > $revisions) {
                @unlink($filename);
            }
            ++$cnt;
        }
    }

    /**
     * save config to filesystem
     * @param array|null $revision revision tag (associative array)
     * @param bool $backup do not backup current config
     * @throws ConfigException when config could not be parsed
     */
    public function save($revision = null, $backup = true)
    {
        $this->checkvalid();

        // update revision information ROOT.revision tag, align timestamp to backup output
        $this->updateRevision($revision, null, microtime(true));

        if ($this->config_file_handle !== null) {
            if (flock($this->config_file_handle, LOCK_EX)) {
                fseek($this->config_file_handle, 0);
                ftruncate($this->config_file_handle, 0);
                fwrite($this->config_file_handle, (string)$this);
                // flush, unlock, but keep the handle open
                fflush($this->config_file_handle);
                $backup_filename = $backup ? $this->backup() : null;
                if ($backup_filename) {
                    // use syslog to trigger a new configd event, which should signal a syshook config (in batch).
                    // Althought we include the backup filename, the event handler is responsible to determine the
                    // last processed event itself. (it's merely added for debug purposes)
                    $logger = new Syslog("config", array('option' => LOG_PID, 'facility' => LOG_LOCAL5));
                    $logger->info("config-event: new_config " . $backup_filename);
                }
                flock($this->config_file_handle, LOCK_UN);
            } else {
                throw new ConfigException("Unable to lock config");
            }
        }

        /* cleanup backups */
        $this->cleanupBackups();
    }

    /**
     * cleanup, close file handle
     */
    public function __destruct()
    {
        if ($this->config_file_handle !== null) {
            fclose($this->config_file_handle);
        }
    }


    /**
     * lock configuration
     * @param boolean $reload reload config from open file handle to enforce synchronicity
     */
    public function lock($reload = true)
    {
        if ($this->config_file_handle !== null) {
            flock($this->config_file_handle, LOCK_EX);
            if ($reload) {
                $this->load();
            }
        }
        return $this;
    }

    /**
     * unlock configuration
     */
    public function unlock()
    {
        if (is_resource($this->config_file_handle)) {
            flock($this->config_file_handle, LOCK_UN);
        }
        return $this;
    }
}
