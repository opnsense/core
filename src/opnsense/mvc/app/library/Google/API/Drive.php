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
namespace Google\API;

/**
 * Class Drive wrapper around Google API for Drive support
 * @package Google\API
 */
class Drive
{
    /**
     * @var null|\Google_Service_Drive service pointer
     */
    private $service = null;

    /**
     * @var null|\Google_Client pointer to client
     */
    private $client = null;

    /**
     * @var null|Google_Auth_AssertionCredentials credential object
     */
    private $cred = null;

    public function __construct()
    {
        // hook in Google's autoloader
        require_once(__DIR__."/src/Google/autoload.php");
    }

    /**
     * login to google drive
     * @param $client_id
     * @param $privateKeyB64 P12 key placed in a base64 container
     */
    public function login($client_id, $privateKeyB64)
    {
        $this->client = new \Google_Client();
        $key = base64_decode($privateKeyB64);

        $this->cred = new \Google_Auth_AssertionCredentials(
            $client_id,
            array('https://www.googleapis.com/auth/drive'),
            $key
        );
        $this->client->setAssertionCredentials($this->cred);
        $this->client->setApplicationName("OPNsense");

        $this->service = new \Google_Service_Drive($this->client);
    }

    /**
     * retrieve directory listing
     * @param $directoryId parent directory id
     * @param $filename title/filename of object
     * @return mixed list of files
     */
    public function listFiles($directoryId, $filename = null)
    {
        $query = "'".$directoryId."' in parents ";
        if ($filename != null) {
            $query .= " and title in '".$filename."'";
        }
        if ($this->client->getAuth()->isAccessTokenExpired()) {
            $this->client->getAuth()->refreshTokenWithAssertion($this->cred);
        }
        return $this->service->files->listFiles(array('q' => $query));
    }


    /**
     * @param $fileHandle (object from listFiles)
     * @return null|string
     */
    public function download($fileHandle)
    {
        if ($this->client->getAuth()->isAccessTokenExpired()) {
            $this->client->getAuth()->refreshTokenWithAssertion($this->cred);
        }
        $sUrl = $fileHandle->getDownloadUrl();
        $request = new \Google_Http_Request($sUrl, 'GET', null, null);
        $httpRequest = $this->client->getAuth()->authenticatedRequest($request);

        if ($httpRequest->getResponseHttpCode() == 200) {
            return $httpRequest->getResponseBody();
        } else {
            // Error, no content fetched
            return null;
        }
    }

    /**
     * Upload file
     * @param string $directoryId (parent id)
     * @param string $filename
     * @param string $content
     * @param string $mimetype
     * @return handle
     */
    public function upload($directoryId, $filename, $content, $mimetype = 'text/plain')
    {

        if ($this->client->getAuth()->isAccessTokenExpired()) {
            $this->client->getAuth()->refreshTokenWithAssertion($this->cred);
        }
        $parent = new \Google_Service_Drive_ParentReference();
        $parent->setId($directoryId);

        $file = new \Google_Service_Drive_DriveFile();
        $file->setTitle($filename);
        $file->setDescription($filename);
        $file->setMimeType('text/plain');
        $file->setParents(array($parent));

        $createdFile = $this->service->files->insert($file, array(
            'data' => $content,
            'mimeType' => $mimetype,
            'uploadType' => 'media',
        ));

        return $createdFile;
    }

    /**
     * delete file
     * @param $fileHandle (object from listFiles)
     */
    public function delete($fileHandle)
    {
        if ($this->client->getAuth()->isAccessTokenExpired()) {
            $this->client->getAuth()->refreshTokenWithAssertion($this->cred);
        }
        $this->service->files->delete($fileHandle['id']);
    }
}