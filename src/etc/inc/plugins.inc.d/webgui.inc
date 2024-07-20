<?php

/*
 * Copyright (C) 2016-2021 Franco Fichtner <franco@opnsense.org>
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
    return [
        'early' => ['webgui_configure_do'],
        'local' => ['webgui_configure_do'],
        'newwanip' => ['webgui_configure_do:2'],
        'webgui' => ['webgui_configure_do'],
    ];
}

function webgui_services()
{
    return [[
        'pidfile' => '/var/run/lighty-webConfigurator.pid',
        'description' => gettext('Web GUI'),
        'php' => ['restart' => ['webgui_configure_defer']],
        'name' => 'webgui',
        'locked' => true,
    ]];
}

function webgui_configure_defer($verbose = false, $sleep = 3)
{
    service_log('Starting web GUI...', $verbose);
    configdp_run('webgui restart', [$sleep]);
    service_log("deferred.\n", $verbose);
}

function webgui_configure_do($verbose = false, $interface = '')
{
    global $config;

    $interfaces = [];
    if (!empty($config['system']['webgui']['interfaces'])) {
        $interfaces = explode(',', $config['system']['webgui']['interfaces']);
        /* place loopback with good IPv4 first for server.bind */
        array_unshift($interfaces, 'lo0');
    }

    /* 'newwanip' configuration is the only event to take second argument */
    if (!empty($interface)) {
        if (in_array($interface, $interfaces)) {
            /* funnel through configd to avoid race conditions */
            webgui_configure_defer($verbose, 0);
        }

        /* nothing else to do */
        return;
    }

    service_log('Starting web GUI...', $verbose);

    $listeners = count($interfaces) ? [] : ['0.0.0.0', '::'];

    foreach (interfaces_addresses($interfaces) as $tmpaddr => $info) {
        if (!$info['bind']) {
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
            /* XXX for now only if a certificate is not available */
            webgui_create_selfsigned(false);
            $cert =& lookup_cert($config['system']['webgui']['ssl-certref']);
        }

        $crt = base64_decode($cert['crt']);
        $key = base64_decode($cert['prv']);

        if (!$config['system']['webgui']['port']) {
            $portarg = '443';
        }

        $ca = ca_chain($cert);
    }

    webgui_generate_config($portarg, $crt, $key, $ca, $listeners);

    /* regenerate the php.ini files in case the setup has changed */
    configd_run('template reload OPNsense/WebGui');

    /* flush Phalcon volt templates */
    foreach (glob('/usr/local/opnsense/mvc/app/cache/*.php') as $filename) {
        unlink($filename);
    }

    /* we need a marker file for development mode to ensure quick action */
    if (empty($config['system']['deployment'])) {
        @unlink('/var/run/development');
    } else {
        @touch('/var/run/development');
    }

    /* stop the frontend when the generation was completed */
    killbypid('/var/run/lighty-webConfigurator.pid', 'INT');

    /* start lighttpd */
    if (!count($listeners) || mwexec('/usr/local/bin/flock -ne /var/run/lighty-webConfigurator.pid /usr/local/sbin/lighttpd -f /var/etc/lighty-webConfigurator.conf')) {
        service_log("failed.\n", $verbose);
    } else {
        service_log("done.\n", $verbose);
    }
}

function webgui_create_selfsigned($verbose = false)
{
    global $config;

    $a_ca = &config_read_array('ca');
    $a_cert = &config_read_array('cert');

    service_log('Creating self-signed web GUI certificate...', $verbose);

    $cert = [];
    $cert['refid'] = uniqid();
    $cert['descr'] = 'Web GUI TLS certificate';

    $dns = $config['system']['hostname'] . "." . $config['system']['domain'];

    mwexecf(
        '/usr/local/bin/openssl req -new -extensions server_cert ' .
        '-config /usr/local/etc/ssl/opnsense.cnf ' .
        '-newkey rsa:4096 -sha256 -days 397 -nodes -x509 ' .
        '-subj "/CN="%s"/C=NL/ST=Zuid-Holland/L=Middelharnis/O="%s" self-signed web certificate" ' .
        '-addext "subjectAltName = DNS:"%s -keyout /tmp/ssl.key -out /tmp/ssl.crt',
        [$dns, product::getInstance()->name(), $dns]
    );

    $crt = file_get_contents('/tmp/ssl.crt');
    $key = file_get_contents('/tmp/ssl.key');

    unlink('/tmp/ssl.key');
    unlink('/tmp/ssl.crt');

    cert_import($cert, $crt, $key);

    $a_cert[] = $cert;

    $config['system']['webgui']['ssl-certref'] = $cert['refid'];

    write_config('Created web GUI TLS certificate');

    service_log("done.\n", $verbose);
}

function webgui_generate_config($port, $cert, $key, $ca, $listeners)
{
    global $config;

    $cert_location = '/var/etc/cert.pem';
    $key_location = '/var/etc/key.pem';

    @mkdir('/tmp/lighttpdcompress');
    shell_safe('rm -rf /tmp/lighttpdcompress/*');

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
    if (empty($config['syslog']['nologlighttpd'])) {
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

## modules to load
server.modules              = (
  "mod_access", "mod_expire", "mod_deflate", "mod_redirect", "mod_setenv",
  "mod_cgi", "mod_fastcgi", "mod_alias", "mod_rewrite", "mod_openssl" {$lighty_modules}
)

# additional optional modules to load or additional module configurations
include "/usr/local/etc/lighttpd_webgui/conf.d/*.conf"

server.max-keep-alive-requests = 15
server.max-keep-alive-idle = 30

## a static document-root, for virtual-hosting take look at the
## server.virtual-* options
server.document-root        = "/usr/local/www/"
server.tag = "OPNsense"

{$http_rewrite_rules}

# Maximum idle time with nothing being written (php downloading)
server.max-write-idle = 999

# Set shutdown time to 2 seconds for SIGINT handling
server.feature-flags = ( "server.graceful-shutdown-timeout" => 2 )

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
# ~     is for backupfiles from vi, emacs, joe, ...
# .core is for core dumps that may be created during PHP execution
# .inc  is often used for code includes which should in general not be part
#       of the document-root
url.access-deny             = ( "~", , ".core", ".inc" )


######### Options that are good to be but not necessary to be changed #######

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
        $chain = $cert;
        if (!empty($ca) && strlen(trim($ca))) {
            $chain .= "\n" . $ca;
        }

        file_put_contents($cert_location, $chain);
        touch($key_location);
        chmod($key_location, 0600);
        file_put_contents($key_location, $key);

        $lighty_config .= "\n## ssl configuration\n";
        $lighty_config .= "ssl.engine = \"enable\"\n";
        $lighty_config .= "ssl.privkey = \"{$key_location}\"\n";
        $lighty_config .= "ssl.pemfile = \"{$cert_location}\"\n";

        if (empty($config['system']['webgui']['ssl-ciphers'])) {
            /* harden TLS for PCI conformance */
            $lighty_config .= <<<EOD
ssl.openssl.ssl-conf-cmd = (
    "MinProtocol" => "TLSv1.2",
    "Options" => "-ServerPreference",
    "CipherString" => "EECDH+AESGCM:AES256+EECDH:CHACHA20:!SHA1:!SHA256:!SHA384"
)

EOD;
        } else {
            // use the same supported ciphers source as system_advanced_admin.php page do (its not a full list. but its openssl defaults)
            $sys_ciphers = json_decode(configd_run("system ssl ciphers"), true);
            $tls13_suites = array_keys(array_filter($sys_ciphers, function ($val) {
                return $val['version'] == "TLSv1.3";
            }));
            $suites_selected = explode(":", $config['system']['webgui']['ssl-ciphers']);
            $tls_suites_selected = array_diff($suites_selected, $tls13_suites);
            $tls13_suites_selected = array_intersect($tls13_suites, $suites_selected);
            $lighty_config .= "ssl.openssl.ssl-conf-cmd = (\n";
            $lighty_config .= "    \"MinProtocol\" => \"TLSv1\"";
            if ($tls13_suites_selected) {
                $lighty_config .= ",\n    \"Ciphersuites\" => \"" . implode(":", $tls13_suites_selected) . "\"";
            }
            if ($tls_suites_selected) {
                $lighty_config .= ",\n    \"CipherString\" => \"" . implode(":", $tls_suites_selected) . "\"";
            }
            $lighty_config .= "\n)\n";
        }

        if (!empty($config['system']['webgui']['ssl-hsts'])) {
            $lighty_config .= "\$HTTP[\"scheme\"] == \"https\" {\n";
            $lighty_config .= "    setenv.add-response-header = (\"Strict-Transport-Security\" => \"max-age=31536000\" )\n";
            $lighty_config .= "}\n";
        }

        $lighty_config .= "\n";
    }

    foreach ($listeners as $listener) {
        if (is_ipaddrv6($listener)) {
            $listener = "[{$listener}]";
        }
        $lighty_config .= "\$SERVER[\"socket\"] == \"{$listener}:{$lighty_port}\" {\n";
        $lighty_config .= "\t\$HTTP[\"url\"] =~ \"^/api/\" {\n\t\tserver.stream-response-body = 2\n\t}\n";
        if ($config['system']['webgui']['protocol'] == "https") {
            $lighty_config .= "\tssl.engine = \"enable\"" . PHP_EOL;
        }
        $lighty_config .= "}\n";
    }

    $lighty_config .= <<<EOD

## error-handler for status 404
#server.error-handler-404   = "/error-handler.html"
server.error-handler-404   = "/ui/404"

## to help the rc.scripts
server.pid-file            = "/var/run/lighty-webConfigurator.pid"

## enable debugging
debug.log-request-header   = "disable"
debug.log-response-header  = "disable"
debug.log-request-handling = "disable"
debug.log-file-not-found   = "disable"

# compression
deflate.mimetypes = ("text/plain", "text/css", "text/javascript", "text/xml")
deflate.allowed-encodings = ( "br", "gzip", "deflate" )
deflate.cache-dir = "/tmp/lighttpdcompress/"

{$server_upload_dirs}

{$server_max_request_size}

{$fastcgi_config}

{$cgi_config}

expire.url = ( "" => "access 50 hours" )

\$HTTP["url"] =~ "^/ui/js/widgets/" {
    expire.url = ( "" => "access 10 seconds" )
}


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

    @file_put_contents('/var/etc/lighty-webConfigurator.conf', $lighty_config);
}
