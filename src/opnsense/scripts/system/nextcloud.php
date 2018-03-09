#!/usr/local/bin/php
<?php
require_once 'config.inc';

$testconfig = array(
    'system' => array(
        'remotebackup' => array(
            'nextcloud_url' => 'http://localhost:4567',
            'nextcloud_user' => 'testuser',
            'nextcloud_password' => 'testuser',
            'nextcloud_backupdir' => 'opnsense-backup'
        ),
        'hostname' => 'opn',
        'domain' => 'sense'
    )
);

function upload_file_nextcloud($cfg, $file)
{
    $config_remote = $cfg['system']['remotebackup'];
    $url = $config_remote['nextcloud_url'];
    $username = $config_remote['nextcloud_user'];
    $password = $config_remote['nextcloud_password'];
    $backupdir = $config_remote['nextcloud_backupdir'];
    $hostname = $cfg['system']['hostname']. '.' .$cfg['system']['domain'];
    $configname = 'config-' . $hostname . '-' .  date("Y-m-d_h:m:s") . '.xml';
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url . "/remote.php/dav/files/$username/",
        CURLOPT_CUSTOMREQUEST => "PROPFIND", // Create a file in WebDAV is PUT
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
    $response = curl_exec($curl);
    $err = curl_error($curl);
    if($err) {
        echo "cannot execute PROPFIND";
        return -1;
    }
    $info = curl_getinfo($curl);
    if (!($info['http_code'] == 200 || $info['http_code'] == 207)) {
        echo "cannot execute PROPFIND";
        echo print_r($info);
        return -1;
    }
    curl_close($curl);
    
    if (stristr($response, $backupdir) === FALSE) {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url . "/remote.php/dav/files/$username/$backupdir",
            CURLOPT_CUSTOMREQUEST => "MKCOL", // MKDIR in WebDAV is MKCOL
            CURLOPT_RETURNTRANSFER => true, // Do not output the data to STDOUT
            CURLOPT_VERBOSE => 0,           // same here
            CURLOPT_MAXREDIRS => 0,         // no redirects
            CURLOPT_TIMEOUT => 10,          // maximum time: 1 min
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_USERPWD => $username . ":" . $password,
            CURLOPT_HTTPHEADER => array(
              "User-Agent: OPNsense Firewall"
            )
        ));
        curl_exec($curl);
        $err = curl_error($curl);
        $info = curl_getinfo($curl);
        curl_close($curl);
        if (!($info['http_code'] == 200 || $info['http_code'] == 207 || $info['http_code'] == 201))
        {
            echo "cannot execute MKCOL";
            echo print_r($info);
            return -1;
        }
    }

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url . "/remote.php/dav/files/$username/$backupdir/$configname",
        CURLOPT_CUSTOMREQUEST => "PUT", // Create a file in WebDAV is PUT
        CURLOPT_RETURNTRANSFER => true, // Do not output the data to STDOUT
        CURLOPT_VERBOSE => 0,           // same here
        CURLOPT_ENCODING => "",         // no encoding - we upload binary data
        CURLOPT_MAXREDIRS => 0,         // no redirects
        CURLOPT_TIMEOUT => 60,          // maximum time: 1 min
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_POSTFIELDS => file_get_contents($file),
        CURLOPT_USERPWD => $username . ":" . $password,
        CURLOPT_HTTPHEADER => array(
          "User-Agent: OPNsense Firewall"
        )
    ));
    $response = curl_exec($curl);
    $err = curl_error($curl);
    if ($err) {
        echo "cannot execute PUT";
        return -1;
    }
    $info = curl_getinfo($curl);
    if (!($info['http_code'] == 201)) {
        echo "cannot execute MKCOL";
        echo print_r($info);
        return -1;
    }
    curl_close($curl);

    return 0;
}

exit(upload_file_nextcloud($config, '/conf/config.xml'));
//$result = upload_file_nextcloud($testconfig, __file__);
