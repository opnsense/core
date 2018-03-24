<?php
/**
 *    Copyright (C) 2018 Deciso B.V.
 *    Copyright (C) 2018 Fabian Franz
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

namespace OPNsense\Backup;

use OPNsense\Core\Config;

/**
 * Class Nextcloud backup
 * @package OPNsense\Backup
 */
class Nextcloud extends Base implements IBackupProvider
{

    /**
     * get required (user interface) fields for backup connector
     * @return array configuration fields, types and description
     */
    public function getConfigurationFields()
    {
        $fields = array(
            array(
                "name" => "nextcloud_enabled",
                "type" => "checkbox",
                "label" => gettext("Enable"),
                "value" => null
            ),
            array(
                "name" => "nextcloud_url",
                "type" => "text",
                "label" => gettext("URL"),
                "help" => gettext("The Base URL to Nextcloud. For example: https://cloud.example.com"),
                "value" => null
            ),
            array(
                "name" => "nextcloud_user",
                "type" => "text",
                "label" => gettext("User Name"),
                "help" => gettext("The name you use for logging into your Nextcloud account"),
                "value" => null
            ),
            array(
                "name" => "nextcloud_password",
                "type" => "password",
                "label" => gettext("Password"),
                "help" => gettext("The app password which has been generated for you"),
                "value" => null
            ),
            array(
                "name" => "nextcloud_backupdir",
                "type" => "text",
                "label" => gettext("Directory Name"),
                "value" => 'OPNsense-Backup'
            )
        );
        $cnf = Config::getInstance();
        if ($cnf->isValid()) {
            $config = $cnf->object();
            foreach ($fields as &$field) {
                $fieldname = $field['name'];
                if (isset($config->system->remotebackup->$fieldname)) {
                    $field['value'] = (string)$config->system->remotebackup->$fieldname;
                }
            }
        }

        return $fields;
    }

    /**
     * backup provider name
     * @return string user friendly name
     */
    public function getName()
    {
        return gettext("Nextcloud");
    }

    /**
     * validate and set configuration
     * @param array $conf configuration array
     * @return array of validation errors when not saved
     */
    public function setConfiguration($conf)
    {
        $input_errors = array();
        // format of backup directory name
        if (isset($_POST['nextcloud_backupdir']) && preg_match('/^[a-z0.9-]+$/i', $_POST['nextcloud_backupdir'])) {
            $input_errors[] = gettext('The Backup Directory can only consist of alphanumeric characters.');
        }
        // required fields
        if (isset($config['system']['remotebackup']['nextcloud_enabled']) && $config['system']['remotebackup']['nextcloud_enabled'])
        {
            $fieldval = array(
                'nextcloud_url' => gettext('An URL for the Nextcloud server must be set.'),
                'nextcloud_user' => gettext('An user for the Nextcloud server must be set.'),
                'nextcloud_password' => gettext('An password for a Nextcloud server must be set.')
            );
            foreach ($fieldval as $fieldname => $errormsg) {
                if (empty($config['system']['remotebackup'][$fieldname])) {
                    $input_errors[] = $errormsg;
                }
            }
        }
        if (count($input_errors) == 0) {
            $config = Config::getInstance()->object();
            if (!isset($config->system->remotebackup)) {
                $config->system->remotebackup = array();
            }
            foreach ($this->getConfigurationFields() as $field) {
                $fieldname = $field['name'];
                if ($field['type'] == 'file') {
                    if (!empty($conf[$field['name']])) {
                        $config->system->remotebackup->$fieldname = base64_encode($conf[$field['name']]);
                    }
                } elseif (!empty($conf[$field['name']])) {
                    $config->system->remotebackup->$fieldname = $conf[$field['name']];
                } else {
                    unset($config->system->remotebackup->$fieldname);
                }
            }
            Config::getInstance()->save();
        }

        return $input_errors;
    }

    /**
     * @return array filelist
     */
    function backup() {
        $cnf = Config::getInstance();
        if ($cnf->isValid()) {
            $config = $cnf->object();
            if (isset($config->system->remotebackup)
                    && isset($config->system->remotebackup->nextcloud_enabled)
                    && !empty($config->system->remotebackup->nextcloud_enabled)) {
                $config_remote = $config->system->remotebackup;
                $url = $config_remote->nextcloud_url;
                $username = $config_remote->nextcloud_user;
                $password = $config_remote->nextcloud_password;
                $backupdir = $config_remote->nextcloud_backupdir;
                $hostname = $config->system->hostname . '.' .$config->system->domain;
                $configname = 'config-' . $hostname . '-' .  date("Y-m-d_h:m:s") . '.xml';
                // backup source data to local strings (plain/encrypted)
                $confdata = file_get_contents('/conf/config.xml');
                $confdata_enc = chunk_split(
                    $this->encrypt($confdata, (string)$config->system->remotebackup->nextcloud_password)
                );
                try {
                    $directories = $this->listFiles($url, $username, $password, '/');
                    if (!in_array("/$backupdir/",$directories)) {
                        $this->create_directory($url, $username, $password, $backupdir);
                    }
                    $this->upload_file_content(
                        $url,
                        $username,
                        $password,
                        $backupdir,
                        $configname,
                        $confdata_enc);
                    // do not list directories
                    return array_filter(
                        $this->listFiles($url, $username, $password, "/$backupdir/", false),
                        function ($filename){
                            return (substr($filename, -1) !== '/');
                        }
                    );
                } catch (\Exception $e) {
                    return array();
                }
            }
        }
    }
    public function listFiles($url, $username, $password, $directory = '/', $only_dirs=true) {
        $result = $this->curl_request(
            "$url/remote.php/dav/files/$username$directory",
            $username,
            $password,
            'PROPFIND',
            "Error while fetching filelist from Nextcloud");
        // workaround - simplexml seems to be broken when using namespaces - remove them.
        $xml = simplexml_load_string(str_replace( ['<d:', '</d:'], ['<', '</'] , $result['response']));
        $ret = array();
        foreach ($xml->children() as $response)
        {
            // d:response
            if ($response->getName() == 'response') {
                $fileurl =  (string)$response->href;
                $dirname = explode( "/remote.php/dav/files/$username", $fileurl,2)[1];
                if ($response->propstat->prop->resourcetype->children()->count() > 0 &&
                    $response->propstat->prop->resourcetype->children()[0]->getName() == 'collection' &&
                    $only_dirs)
                {
                    $ret[] = $dirname;
                } elseif(!$only_dirs) {
                    $ret[] = $dirname;
                }
            }
        }
        return $ret;
    }
    public function upload_file_content($url, $username, $password, $backupdir, $filename, $local_file_content) {
        $this->curl_request(
            $url . "/remote.php/dav/files/$username/$backupdir/$filename",
            $username,
            $password,
            'PUT',
            'cannot execute PUT',
            $local_file_content
            );
    }
    public function create_directory($url, $username, $password, $backupdir) {
        $this->curl_request($url . "/remote.php/dav/files/$username/$backupdir",
            $username,
            $password,
            'MKCOL',
            'cannot execute MKCOL');
    }
    public function curl_request($url, $username, $password, $method, $error_message, $postdata = null) {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method, // Create a file in WebDAV is PUT
            CURLOPT_RETURNTRANSFER => true, // Do not output the data to STDOUT
            CURLOPT_VERBOSE => 0,           // same here
            CURLOPT_MAXREDIRS => 0,         // no redirects
            CURLOPT_TIMEOUT => 60,          // maximum time: 1 min
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_USERPWD => $username . ":" . $password,
            CURLOPT_HTTPHEADER => array(
                "User-Agent: OPNsense Firewall"
            )
        ));
        if ($postdata != null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $postdata);
        }
        $response = curl_exec($curl);
        $err = curl_error($curl);
        $info = curl_getinfo($curl);
        if (!($info['http_code'] == 200 || $info['http_code'] == 207 || $info['http_code'] == 201) || $err) {
            syslog(LOG_ERR, $error_message);
            syslog(LOG_ERR,json_encode($info));
            throw new \Exception();
        }
        curl_close($curl);
        return array('response' => $response, 'info' => $info);
    }

    /**
     * Is this provider enabled
     * @return boolean enabled status
     */
    public function isEnabled()
    {
        $cnf = Config::getInstance();
        if ($cnf->isValid()) {
            $config =$cnf->object();
            return isset($config->system->remotebackup) && isset($config->system->remotebackup->nextcloud_enabled)
                && !empty($config->system->remotebackup->nextcloud_enabled);
        }
        return false;
    }
}
