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
 * Simple wrapper around xmlrpc sync using configuration data.
 * In the long run we should likely switch to RESTful implementations as xmlrpc is rather old and less common
 * nowadays.
 *
 * @param string $method method to call
 * @param array $params parameters to pass
 * @param bool $debug debug mode
 * @return array
 */
function xmlrpc_execute($method, $params = [], $debug = false)
{
    global $config;
    $synchronizeto = null;
    $hasync = $config['hasync'];
    if (is_ipaddrv6($hasync['synchronizetoip'])) {
        $hasync['synchronizetoip'] = "[{$hasync['synchronizetoip']}]";
    }

    if (!empty($hasync['synchronizetoip'])) {
        // determine target url
        if (substr($hasync['synchronizetoip'], 0, 4) == 'http') {
            // URL provided
            if (substr($hasync['synchronizetoip'], strlen($hasync['synchronizetoip']) - 1, 1) == '/') {
                $synchronizeto = $hasync['synchronizetoip'] . "xmlrpc.php";
            } else {
                $synchronizeto = $hasync['synchronizetoip'] . "/xmlrpc.php";
            }
        } elseif (!empty($config['system']['webgui']['protocol'])) {
            // no url provided, assume the backup is using the same settings as our box.
            $port = $config['system']['webgui']['port'];
            if (!empty($port)) {
                $synchronizeto =  $config['system']['webgui']['protocol'] . '://' . $hasync['synchronizetoip'] . ':' . $port . "/xmlrpc.php";
            } else {
                $synchronizeto =  $config['system']['webgui']['protocol'] . '://' . $hasync['synchronizetoip'] . "/xmlrpc.php";
            }
        }

        $username = empty($hasync['username']) ? "root" : $hasync['username'];
        $client = new SimpleXMLRPC_Client($synchronizeto, 240);
        $client->debug = $debug;
        $client->setCredentials($username, $hasync['password']);
        if ($client->query($method, $params)) {
            return $client->getResponse();
        } else {
            throw new Exception($client->error);
        }
    }
    return false;
}


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
     * request url
     * @var string
     */
    private $url = '';

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
        $this->authHeader = 'Authorization: Basic ' . base64_encode("$username:$password");
    }

    public function query()
    {
        /* XXX: xmlrpc is legacy, callers always import legacy config */
        global $config;
        $tls_verify = !empty($config['hasync']) && !empty($config['hasync']['verifypeer']);

        // create xmlrpc request object
        $args = func_get_args();
        $method = array_shift($args);
        $request = new IXR_Request($method, $args);
        $request_xml = $request->getXml();

        // setup http headers
        $headers = ['Host: ' . $this->server];
        $headers[] = "User-Agent: XML_RPC";
        $headers[] = "Content-Type: text/xml";
        if ($this->authHeader != null) {
            $headers[] = $this->authHeader;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        if (!$tls_verify) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLINFO_HEADER_OUT, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request_xml);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $this->response_received = curl_exec($ch);
        if ($this->debug) {
            print_r(curl_getinfo($ch));
        }
        if ($this->response_received === false) {
            $this->error = 'fetch error. remote host down? (' . curl_error($ch) . ')';
            curl_close($ch);
            return false;
        }

        curl_close($ch);

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
