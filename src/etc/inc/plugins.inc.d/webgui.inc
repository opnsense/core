<?php

/*
 * Copyright (C) 2016-2020 Franco Fichtner <franco@opnsense.org>
 * Copyright (C) 2004-2007 Scott Ullrich <sullrich@gmail.com>
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
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
  *INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

function webgui_configure()
{
    return array(
        'earlybootup' => array('webgui_configure_do'),
        'local' => array('webgui_configure_do'),
        'newwanip' => array('webgui_configure_do:2'),
        'webgui' => array('webgui_configure_do'),
    );
}

function webgui_configure_do($verbose = false, $interface = '')
{
    global $config;

    $interfaces = array();
    if (!empty($config['system']['webgui']['interfaces'])) {
        $interfaces = explode(',', $config['system']['webgui']['interfaces']);
        /* place loopback with good IPv4 first for server.bind */
        array_unshift($interfaces, 'lo0');
    }

    @mkdir('/var/lib/php/sessions', 0750, true);
    /* do not trust PHP with mkdir permission on existing directory */
    @chmod('/var/lib/php/sessions', 0750);
    @chown('/var/lib/php/sessions', 'root');
    @chgrp('/var/lib/php/sessions', 'wheel');

    if (!empty($interface) && !in_array($interface, $interfaces)) {
        return;
    }

    if ($verbose) {
        echo 'Starting web GUI...';
        flush();
    }

    $listeners = count($interfaces) ? array() : array('0.0.0.0', '::');

    foreach (interfaces_addresses($interfaces) as $tmpaddr => $info) {
        if ($info['scope']) {
            /* link-local does not seem to be supported */
            continue;
        }

        $listeners[] = $tmpaddr;
    }

    chdir('/usr/local/www');

    /* defaults */
    $portarg = '80';
    $crt = '';
    $key = '';
    $ca = '';

    /* non-standard port? */
    if (isset($config['system']['webgui']['port']) && $config['system']['webgui']['port'] != '') {
        $portarg = "{$config['system']['webgui']['port']}";
    }

    if ($config['system']['webgui']['protocol'] == "https") {
        $cert =& lookup_cert($config['system']['webgui']['ssl-certref']);
        if (!is_array($cert) && !$cert['crt'] && !$cert['prv']) {
            $a_ca = &config_read_array('ca');
            $a_cert = &config_read_array('cert');
            log_error("Creating SSL certificate for this host");
            $cert = array();
            $cert['refid'] = uniqid();
            $cert['descr'] = 'Web GUI SSL certificate';
            mwexec(
                /* XXX ought to be replaced by PHP calls */
                '/usr/local/bin/openssl req -new -extensions server_cert ' .
                '-config /usr/local/etc/ssl/opnsense.cnf ' .
                '-newkey rsa:4096 -sha256 -days 825 -nodes -x509 ' .
                '-subj "/C=NL/ST=Zuid-Holland/L=Middelharnis/O=OPNsense" ' .
                '-keyout /tmp/ssl.key -out /tmp/ssl.crt'
            );
            $crt = file_get_contents('/tmp/ssl.crt');
            $key = file_get_contents('/tmp/ssl.key');
            unlink('/tmp/ssl.key');
            unlink('/tmp/ssl.crt');
            cert_import($cert, $crt, $key);
            $a_cert[] = $cert;
            $config['system']['webgui']['ssl-certref'] = $cert['refid'];
            write_config('Created web GUI SSL certificate');
        } else {
            $crt = base64_decode($cert['crt']);
            $key = base64_decode($cert['prv']);
        }

        if (!$config['system']['webgui']['port']) {
            $portarg = '443';
        }

        $ca = ca_chain($cert);
    }

    if (!webgui_generate_config($portarg, $crt, $key, $ca, $listeners)) {
        if ($verbose) {
            echo "deferred.\n";
        }
        return;
    }

    /* only stop the frontend when the generation was successful */
    killbypid('/var/run/lighty-webConfigurator.pid', 'TERM', true);

    /* flush Phalcon volt templates */
    foreach (glob('/usr/local/opnsense/mvc/app/cache/*.php') as $filename) {
        unlink($filename);
    }

    /* regenerate the php.ini files in case the setup has changed */
    configd_run('template reload OPNsense/WebGui');

    /*
     * Force reloading all php-cgi children to
     * avoid hiccups with moved include files.
     */
    killbyname('php-cgi', 'HUP');

    /* start lighthttpd */
    if (!count($listeners) || mwexec('/usr/local/sbin/lighttpd -f /var/etc/lighty-webConfigurator.conf')) {
        if ($verbose) {
            echo "failed.\n";
        }
    } else {
        if ($verbose) {
            echo "done.\n";
        }
    }
}

function webgui_generate_config($port, $cert, $key, $ca, $listeners)
{
    global $config;

    $cert_location = 'cert.pem';
    $ca_location = 'ca.pem';

    @mkdir('/tmp/lighttpdcompress');

    $http_rewrite_rules = <<<EOD
# Phalcon ui and api routing
alias.url += ( "/ui/" => "/usr/local/opnsense/www/" )
alias.url += ( "/api/"  => "/usr/local/opnsense/www/" )
url.rewrite-if-not-file = ( "^/ui/([^\?]+)(\?(.*))?" => "/ui/index.php?$3" ,
                            "^/api/([^\?]+)(\?(.*))?" => "/api/api.php?$3"
)

EOD;
    $server_upload_dirs = "server.upload-dirs = ( \"/root/\", \"/tmp/\", \"/var/\" )\n";
    $server_max_request_size = "server.max-request-size    = 2097152";
    $cgi_config = "cgi.assign                 = ( \".cgi\" => \"\" )";

    $lighty_port = $port;

    $lighty_use_syslog = '';
    if (!isset($config['syslog']['nologlighttpd'])) {
        $lighty_use_syslog = <<<EOD
## where to send error/access-messages to
server.syslog-facility = "daemon"
server.errorlog-use-syslog="enable"

EOD;
    }
    if (!empty($config['system']['webgui']['httpaccesslog'])) {
        $lighty_use_syslog .= 'accesslog.use-syslog="enable"' . "\n";
    }

    $fast_cgi_path = "/tmp/php-fastcgi.socket";

    $fastcgi_config = <<<EOD
#### fastcgi module
## read fastcgi.txt for more info
fastcgi.server = ( ".php" =>
  ( "localhost" =>
    (
      "socket" => "{$fast_cgi_path}",
      "max-procs" => 2,
      "bin-environment" => (
        "PHP_FCGI_CHILDREN" => "3",
        "PHP_FCGI_MAX_REQUESTS" => "100"
      ),
      "bin-path" => "/usr/local/bin/php-cgi"
    )
  )
)

EOD;

    $lighty_modules = !empty($config['system']['webgui']['httpaccesslog']) ? ', "mod_accesslog"' : "";


    $lighty_config = <<<EOD
#
# lighttpd configuration file
#
# use a it as base for lighttpd 1.0.0 and above
#
############ Options you really have to take care of ####################

server.event-handler  = "freebsd-kqueue"
server.network-backend  = "writev"

## modules to load
server.modules              =   (
  "mod_access", "mod_expire", "mod_deflate", "mod_redirect", "mod_setenv",
  "mod_cgi", "mod_fastcgi","mod_alias", "mod_rewrite", "mod_openssl" {$lighty_modules}
)

server.max-keep-alive-requests = 15
server.max-keep-alive-idle = 30

## a static document-root, for virtual-hosting take look at the
## server.virtual-* options
server.document-root        = "/usr/local/www/"
server.tag = "OPNsense"

{$http_rewrite_rules}

# Maximum idle time with nothing being written (php downloading)
server.max-write-idle = 999

{$lighty_use_syslog}

# files to check for if .../ is requested
server.indexfiles           = ( "index.php", "index.html",
                                "index.htm", "default.htm" )

# mimetype mapping
mimetype.assign             = (
  "wpad.dat"      =>      "application/x-ns-proxy-autoconfig",
  ".pdf"          =>      "application/pdf",
  ".sig"          =>      "application/pgp-signature",
  ".spl"          =>      "application/futuresplash",
  ".class"        =>      "application/octet-stream",
  ".ps"           =>      "application/postscript",
  ".torrent"      =>      "application/x-bittorrent",
  ".dvi"          =>      "application/x-dvi",
  ".gz"           =>      "application/x-gzip",
  ".pac"          =>      "application/x-ns-proxy-autoconfig",
  ".swf"          =>      "application/x-shockwave-flash",
  ".tar.gz"       =>      "application/x-tgz",
  ".tgz"          =>      "application/x-tgz",
  ".tar"          =>      "application/x-tar",
  ".zip"          =>      "application/zip",
  ".mp3"          =>      "audio/mpeg",
  ".m3u"          =>      "audio/x-mpegurl",
  ".wma"          =>      "audio/x-ms-wma",
  ".wax"          =>      "audio/x-ms-wax",
  ".ogg"          =>      "audio/x-wav",
  ".wav"          =>      "audio/x-wav",
  ".gif"          =>      "image/gif",
  ".jpg"          =>      "image/jpeg",
  ".jpeg"         =>      "image/jpeg",
  ".png"          =>      "image/png",
  ".svg"          =>      "image/svg+xml",
  ".xbm"          =>      "image/x-xbitmap",
  ".xpm"          =>      "image/x-xpixmap",
  ".xwd"          =>      "image/x-xwindowdump",
  ".css"          =>      "text/css",
  ".html"         =>      "text/html",
  ".htm"          =>      "text/html",
  ".js"           =>      "text/javascript",
  ".asc"          =>      "text/plain",
  ".c"            =>      "text/plain",
  ".conf"         =>      "text/plain",
  ".text"         =>      "text/plain",
  ".txt"          =>      "text/plain",
  ".dtd"          =>      "text/xml",
  ".xml"          =>      "text/xml",
  ".mpeg"         =>      "video/mpeg",
  ".mpg"          =>      "video/mpeg",
  ".mov"          =>      "video/quicktime",
  ".qt"           =>      "video/quicktime",
  ".avi"          =>      "video/x-msvideo",
  ".asf"          =>      "video/x-ms-asf",
  ".asx"          =>      "video/x-ms-asf",
  ".wmv"          =>      "video/x-ms-wmv",
  ".bz2"          =>      "application/x-bzip",
  ".tbz"          =>      "application/x-bzip-compressed-tar",
  ".tar.bz2"      =>      "application/x-bzip-compressed-tar"
 )

# Use the "Content-Type" extended attribute to obtain mime type if possible
#mimetypes.use-xattr        = "enable"

## deny access the file-extensions
#
# ~    is for backupfiles from vi, emacs, joe, ...
# .inc is often used for code includes which should in general not be part
#      of the document-root
url.access-deny             = ( "~", ".inc" )


######### Options that are good to be but not neccesary to be changed #######

## bind to port (default: 80)

EOD;

    $lighty_config .= "server.bind  = \"{$listeners[0]}\"\n";
    $lighty_config .= "server.port  = {$lighty_port}\n";

    $cert = str_replace("\r", "", $cert);
    $key = str_replace("\r", "", $key);
    $ca = str_replace("\r", "", $ca);

    $cert = str_replace("\n\n", "\n", $cert);
    $key = str_replace("\n\n", "\n", $key);
    $ca = str_replace("\n\n", "\n", $ca);

    if (!empty($cert) && !empty($key)) {
        $fd = fopen("/var/etc/{$cert_location}", "w");
        if (!$fd) {
            log_error('Error: cannot open cert.pem');
            return 0;
        }
        chmod("/var/etc/{$cert_location}", 0600);
        fwrite($fd, $cert);
        fwrite($fd, "\n");
        fwrite($fd, $key);
        fclose($fd);
        if (!(empty($ca) || (strlen(trim($ca)) == 0))) {
            $fd = fopen("/var/etc/{$ca_location}", "w");
            if (!$fd) {
                log_error('Error: cannot open ca.pem');
                return 0;
            }
            chmod("/var/etc/{$ca_location}", 0600);
            fwrite($fd, $ca);
            fclose($fd);
        }

        $lighty_config .= "\n## ssl configuration\n";
        $lighty_config .= "ssl.engine = \"enable\"\n";
        $lighty_config .= "ssl.disable-client-renegotiation = \"enable\"\n";
        $lighty_config .= "ssl.dh-file = \"" . get_dh_parameters(4096) . "\"\n";
        $lighty_config .= "ssl.ec-curve = \"secp384r1\"\n";
        $lighty_config .= "ssl.pemfile = \"/var/etc/{$cert_location}\"\n";
        if (!empty($ca)) {
            $lighty_config .= "ssl.ca-file = \"/var/etc/{$ca_location}\"\n";
        }
        $lighty_config .= "setenv.add-environment = (\n";
        $lighty_config .= "     \"HTTPS\" => \"on\"\n";
        $lighty_config .= ")\n";

        // Harden SSL a bit for PCI conformance testing
        $lighty_config .= "ssl.openssl.ssl-conf-cmd = (\"MinProtocol\" => \"TLSv1\")\n";
        $lighty_config .= "ssl.honor-cipher-order = \"enable\"\n";
        if (empty($config['system']['webgui']['ssl-ciphers'])) {
            $ciphers = array(
                'ECDHE-ECDSA-AES256-GCM-SHA384',
                'ECDHE-RSA-AES256-GCM-SHA384',
                'ECDHE-ECDSA-CHACHA20-POLY1305',
                'ECDHE-RSA-CHACHA20-POLY1305',
                'ECDHE-ECDSA-AES128-GCM-SHA256',
                'ECDHE-RSA-AES128-GCM-SHA256',
                'ECDHE-ECDSA-AES256-SHA384',
                'ECDHE-RSA-AES256-SHA384',
                'ECDHE-ECDSA-AES128-SHA256',
                'ECDHE-RSA-AES128-SHA256'
            );
            $lighty_config .= 'ssl.cipher-list = "' . join(':', $ciphers) . '"' . PHP_EOL;
        } else {
            $lighty_config .= 'ssl.cipher-list = "' . $config['system']['webgui']['ssl-ciphers'] . '"' . PHP_EOL;
        }
        if (!empty($config['system']['webgui']['ssl-hsts'])) {
            $lighty_config .= "setenv.add-response-header  = (\n";
            $lighty_config .= "    \"Strict-Transport-Security\" => \"max-age=15768000;\"\n";
            $lighty_config .= ")\n";
        }

        $lighty_config .= "\n";
    }

    foreach ($listeners as $listener) {
        if (is_ipaddrv6($listener)) {
            $listener = "[{$listener}]";
        }
        $lighty_config .= "\$SERVER[\"socket\"] == \"{$listener}:{$lighty_port}\" {\n";
        if ($config['system']['webgui']['protocol'] == "https") {
            $lighty_config .= '    ssl.engine = "enable"' . PHP_EOL;
        }
        $lighty_config .= "}\n";
    }

    $lighty_config .= <<<EOD

## error-handler for status 404
#server.error-handler-404   = "/error-handler.html"
server.error-handler-404   = "/ui/404"

## to help the rc.scripts
server.pid-file            = "/var/run/lighty-webConfigurator.pid"

## virtual directory listings
server.dir-listing         = "disable"

## enable debugging
debug.log-request-header   = "disable"
debug.log-response-header  = "disable"
debug.log-request-handling = "disable"
debug.log-file-not-found   = "disable"

# gzip compression
deflate.cache-dir = "/tmp/lighttpdcompress/"
deflate.mimetypes = ("text/plain","text/css", "text/xml", "text/javascript" )

{$server_upload_dirs}

{$server_max_request_size}

{$fastcgi_config}

{$cgi_config}

expire.url = ( "" => "access 50 hours" )

EOD;

    /* add HTTP to HTTPS redirect */
    if (
        $config['system']['webgui']['protocol'] == 'https' &&
        !isset($config['system']['webgui']['disablehttpredirect'])
    ) {
        $redirectport = $lighty_port != "443" ? ":{$lighty_port}" : '';
        foreach ($listeners as $listener) {
            if (is_ipaddrv6($listener)) {
                $listener = "[{$listener}]";
            }
            $lighty_config .= <<<EOD

\$SERVER["socket"] == "{$listener}:80" {
    \$HTTP["host"] =~ "(.*)" {
        url.redirect = ( "^/(.*)" => "https://%1{$redirectport}/$1" )
    }
    ssl.engine = "disable"
}

EOD;
        }
    }

    if (false === file_put_contents('/var/etc/lighty-webConfigurator.conf', $lighty_config)) {
        log_error('Error: cannot write configuration');
        return 0;
    }

    return 1;
}
