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

require_once("IXR/IXR_Library.php");

/**
 * Simple XMLRPC client based on the components from IXR
 * mainly used for backward compatibility of ha sync feature
 * Class SimpleXMLRPC_Client
 */
class SimpleXMLRPC_Client
{
    /**
     * Authentication header to use
     * @var null
     */
    private $authHeader = null;

    /**
     * remote host
     * @var null
     */
    private $server = null;

    /**
     * @var bool debug mode
     */
    public $debug = false;

    /**
     * process error
     * @var null
     */
    public $error = null;

    /**
     * request timeout
     * @var int
     */
    private $timeout = 60;

    /**
     * (last) response message
     * @var null
     */
    private $message = null;

    /**
     * (last) request data send to remote host
     * @var null|string
     */
    private $request_send = null;

    /**
     * (last) response data received
     * @var null|string
     */
    private $response_received = null;

    /**
     * init client
     * @param string $url request url
     * @param int $timeout timeout (in seconds) to wait for response
     */
    public function __construct($url, $timeout = 60)
    {
        $this->url = $url;
        $this->timeout = $timeout;
        $bits = parse_url($url);
        $this->server = $bits['host'];
    }

    /**
     * set credentials to use (basic auth)
     * @param string $username
     * @param string $password
     */
    public function setCredentials($username, $password)
    {
        $this->authHeader = 'Authorization: Basic ' . base64_encode("$username:$password") . "\r\n";
    }

    public function query()
    {
        // create xmlrpc request object
        $args = func_get_args();
        $method = array_shift($args);
        $request = new IXR_Request($method, $args);
        $request_xml = $request->getXml();

        // setup http headers
        $headers = 'Host: ' . $this->server . "\r\n";
        $headers .= "User-Agent: XML_RPC\r\n";
        $headers .= "Content-Type: text/xml\r\n";
        $headers .= 'Content-Length: ' . $request->getLength() . "\r\n";
        if ($this->authHeader != null) {
            $headers .= $this->authHeader;
        }

        $this->request_send = $headers . $request_xml;
        if ($this->debug) {
            echo sprintf(">>> send %d bytes: \n%s\n", strlen($this->request_send), $this->request_send);
        }

        // setup a stream context
        $context = stream_context_create(array('http' => array(
            'method' => "POST",
            'header' => $headers,
            'content' => $request_xml,
            'timeout' => $this->timeout
            ),
            "ssl" => array(
                "verify_peer" => false,
                "verify_peer_name" => false,
            )
        ));

        $this->response_received = @file_get_contents($this->url, false, $context);
        if ($this->response_received === false) {
            $this->error = 'fetch error. remote host down?';
            return false;
        }

        if ($this->debug) {
            echo sprintf(">>> received %d bytes: \n%s\n", strlen($this->response_received), $this->response_received);
        }

        $this->message = new IXR_Message($this->response_received);
        if (!$this->message->parse()) {
            $this->error = 'parse error. not well formed';
            return false;
        } elseif ($this->message->messageType == 'fault') {
            $this->error = $this->message->faultString;
            return false;
        }

        return true;
    }

    /**
     * response
     * @return null
     */
    public function getResponse()
    {
        if ($this->message == null) {
            return null;
        } else {
            // methodResponses can only have one param - return that
            return $this->message->params[0];
        }
    }

    /**
     * get exchange details (send, received data)
     * @return string
     */
    public function getDetails()
    {
        $result = "send >>> \n" . $this->request_send;
        $result .= "received >>> \n" . $this->response_received;
        if ($this->error != null) {
            $result .= "error >>> \n" . $this->error;
        }

        return $result;
    }
}
