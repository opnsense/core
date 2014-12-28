<?php
/*
    # Copyright (C) 2014 Deciso B.V.
    #
    # All rights reserved.
    #
    # Redistribution and use in source and binary forms, with or without
    # modification, are permitted provided that the following conditions are met:
    #
    # 1. Redistributions of source code must retain the above copyright notice,
    #    this list of conditions and the following disclaimer.
    #
    # 2. Redistributions in binary form must reproduce the above copyright
    #    notice, this list of conditions and the following disclaimer in the
    #    documentation and/or other materials provided with the distribution.
    #
    # THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    # INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    # AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    # AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    # OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    # SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    # INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    # CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    # ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    # POSSIBILITY OF SUCH DAMAGE.

    --------------------------------------------------------------------------------------
    package : Core
    function: provides access to systems config xml

*/


namespace OPNsense\Core;

/**
 * Class ConfigException
 * @package Core
 */
class ConfigException extends \Exception { }

/**
 * Class Config
 * @package Core
 */
class Config extends Singleton {

    /**
     * config file location ( path + name )
     * @var string
     */
    private $config_file = "";

    /**
     * XMLDocument type reference to config
     * @var XMLDocument
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
    private $isValid = False;

    /**
     * Load config file
     * @throws ConfigException
     */
    private function load(){
        // exception handling
        if ( !file_exists($this->config_file) ) throw new ConfigException('file not found') ;
        $xml = file_get_contents($this->config_file);
        if (trim($xml) == '') {
            throw new ConfigException('empty file') ;
        }

        $this->configxml = new \DOMDocument;
        $this->configxml->loadXML($xml);
        $this->simplexml = simplexml_import_dom($this->configxml);
        $this->isValid = true;

    }

    /**
     * @throws ConfigException
     */
    private function checkvalid(){
        if ( !$this->isValid ) throw new ConfigException('no valid config loaded') ;
    }

    /*
     * parse configuration and dump to std output (test)
     * @param DOMElement $node
     * @param string $nodename
     * @throws ConfigException
     */
    public function dump($node=null,$nodename=""){
        $this->checkvalid();
        // root node
        if ($node == null ) $node = $this->configxml;

        $subNodes = $node->childNodes ;
        foreach($subNodes as $subNode){
            if ( $subNode->nodeType  == XML_TEXT_NODE &&(strlen(trim($subNode->wholeText))>=1)) {
                print($nodename.".". $node->tagName." " .$subNode->nodeValue ."\n");
            }

            if ( $subNode->hasChildNodes() ){
                if ( $nodename != "" ) $tmp = $nodename.".".$node->tagName;
                elseif ($node != $this->configxml) $tmp = $node->tagName;
                else $tmp = "";

                $this->dump($subNode,$tmp);
            }

        }

    }

    /*
     * init new config object, try to load current configuration
     * (executed via Singleton)
     */
    protected function init() {
        $this->config_file = \Phalcon\DI\FactoryDefault::getDefault()->get('config')->globals->config_path . "config.xml";
        try {
            $this->load();
        } catch (\Exception $e){
            $this->configxml = null ;
        }

    }

    /*
     * Execute a xpath expression on config.xml
     * @param $query
     * @return \DOMNodeList
     * @throws ConfigException
     */
    function xpath($query){
        $this->checkvalid();
        $xpath = new \DOMXPath($this->configxml);
        return  $xpath->query($query);
    }

    /*
     * object representation of xml document via simplexml, references the same underlying model
     * @return SimpleXML
     * @throws ConfigException
     */
    function object(){
        $this->checkvalid();
        return $this->simplexml;
    }

}
