<?php

/**
 *    Copyright (C) 2015-2023 Deciso B.V.
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
     * construct a new Drive object
     */
    public function __construct()
    {
        // hook in Google's autoloader
        require_once("/usr/local/share/google-api-php-client/vendor/autoload.php");
    }

    /**
     * login to google drive
     * @param $client_id
     * @param $privateKeyB64 P12 key placed in a base64 container
     */
    public function login($client_id, $privateKeyB64)
    {
        openssl_pkcs12_read(base64_decode($privateKeyB64), $certinfo, "notasecret");
        if (empty($certinfo)) {
            throw new \Exception("Invalid P12 key, openssl_pkcs12_read() failed");
        }
        $this->client = new \Google_Client();

        $service_account = [
            "type" => "service_account",
            "private_key" => $certinfo['pkey'],
            "client_email" => $client_id,
            "client_id" => $client_id,
            "auth_uri" => "https://accounts.google.com/o/oauth2/auth",
            "token_uri" => "https://oauth2.googleapis.com/token",
            "auth_provider_x509_cert_url" => "https://www.googleapis.com/oauth2/v1/certs"
        ];

        $this->client->setAuthConfig($service_account);
        $this->client->addScope("https://www.googleapis.com/auth/drive");
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
        $query = "'" . $directoryId . "' in parents ";
        if ($filename != null) {
            $query .= " and title in '" . $filename . "'";
        }
        return $this->service->files->listFiles(['q' => $query, 'supportsAllDrives' => true]);
    }


    /**
     * download a file by given GDrive file handle
     * @param $fileHandle (object from listFiles)
     * @return null|string
     */
    public function download($fileHandle)
    {
        $response = $this->service->files->get($fileHandle->id, ['alt' => 'media', 'supportsAllDrives' => true]);
        return $response->getBody()->getContents();
    }

    /**
     * Upload file
     * @param string $directoryId (parent id)
     * @param string $filename
     * @param string $content
     * @param string $mimetype
     * @return \Google_Service_Drive_DriveFile handle
     */
    public function upload($directoryId, $filename, $content, $mimetype = 'text/plain')
    {

        $file = new \Google_Service_Drive_DriveFile();
        $file->setName($filename);
        $file->setDescription($filename);
        $file->setMimeType('text/plain');
        $file->setParents([$directoryId]);

        $createdFile = $this->service->files->create($file, [
            'data' => $content,
            'mimeType' => $mimetype,
            'uploadType' => 'media',
            'supportsAllDrives' => true
        ]);

        return $createdFile;
    }

    /**
     * delete file
     * @param $fileHandle (object from listFiles)
     */
    public function delete($fileHandle)
    {
        $this->service->files->delete($fileHandle['id'], ['supportsAllDrives' => true]);
    }
}
