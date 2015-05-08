{#

OPNsense® is Copyright © 2014 – 2015 by Deciso B.V.
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

1.  Redistributions of source code must retain the above copyright notice,
    this list of conditions and the following disclaimer.

2.  Redistributions in binary form must reproduce the above copyright notice,
    this list of conditions and the following disclaimer in the documentation
    and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.

#}

<script type="text/javascript">

    $( document ).ready(function() {

        var data_get_map = {'frm_proxy':"/api/proxy/settings/get"};



        // load initial data
        mapDataToFormUI(data_get_map).done(function(){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
            // request service status on load and update status box
            ajaxCall(url="/api/proxy/service/status", sendData={}, callback=function(data,status) {
                updateServiceStatusUI(data['status']);
            });
        });

        // form save event handlers for all defined forms
        $('[id*="save_"]').each(function(){
            $(this).click(function() {
                var frm_id = $(this).closest("form").attr("id");
                var frm_title = $(this).closest("form").attr("data-title");
                // save data for General TAB
                saveFormToEndpoint(url="/api/proxy/settings/set",formid=frm_id,callback_ok=function(){
                    // on correct save, perform reconfigure. set progress animation when reloading
                    $("#"+frm_id+"_progress").addClass("fa fa-spinner fa-pulse");

                    //
                    ajaxCall(url="/api/proxy/service/reconfigure", sendData={}, callback=function(data,status){
                        // when done, disable progress animation.
                        $("#"+frm_id+"_progress").removeClass("fa fa-spinner fa-pulse");

                        if (status != "success" || data['status'] != 'ok' ) {
                            // fix error handling
                            BootstrapDialog.show({
                                type:BootstrapDialog.TYPE_WARNING,
                                title: frm_title,
                                message: JSON.stringify(data),
                                draggable: true
                            });
                        } else {
                            // request service status after successful save and update status box (wait a few seconds before update)
                            setTimeout(function(){
                                ajaxCall(url="/api/proxy/service/status", sendData={}, callback=function(data,status) {
                                    updateServiceStatusUI(data['status']);
                                });
                            },3000);
                        }
                    });
                });
            });
        });


    });


</script>

<!-- TODO: explain TABS and SUBTABS
content_location,tab_name,
    field_array
activetab: content_location
-->
<!-- TODO: explain usage of select_multiple
special options:
style: used as class, defined classes are: tokenize
hint: show default text used for tokenize select
allownew: set to "true" if new items can be added to the list, default is "false"
size: for tokenize this defines the max shown items (default = 5) of the dropdown list, if it does not fit a scrollbar is shown
maxheight: define max height of select box, default=170px to hold 5 items
-->

{{ partial("layout_partials/base_tabs",
    ['tabs': {
        ['proxy-general','General Proxy Settings','subtabs': {
            [ 'proxy-general-settings','General Proxy Settings',
                {['id': 'proxy.general.enabled',
                'label':'Enable proxy',
                'type':'checkbox',
                'help':'Enable or disable the proxy service.'
                ],
                ['id': 'proxy.general.icpPort',
                'label':'ICP port',
                'type':'text',
                'help':'The port number where Squid sends and receives ICP queries to
                        and from neighbor caches. Leave blank to disable (default). The standard UDP port for ICP is 3130.',
                'advanced':'true'
                ],
                ['id': 'proxy.general.logging.enable.accessLog',
                'label':'Enable access logging',
                'type':'checkbox',
                'help':'Enable access logging.',
                'advanced':'true'
                ],
                ['id': 'proxy.general.logging.enable.storeLog',
                'label':'Enable store logging',
                'type':'checkbox',
                'help':'Enable store logging.',
                'advanced':'true'
                ],
                ['id': 'proxy.general.alternateDNSservers',
                'label':'Use alternate DNS-servers',
                'type':'select_multiple',
                'style':'tokenize',
                'help':'Type IPs of alternative DNS servers you like to use. <div class="text-info"><b>TIP: </b>You can also paste a comma seperated list into this field.</div>',
                'hint':'Type IP adresses, followed by Enter or comma.',
                'allownew':'true',
                'advanced':'true'
                ],
                ['id': 'proxy.general.dnsV4First',
                'label':'Enable DNS v4 first',
                'type':'checkbox',
                'help':'This option reverses the order of preference to make Squid contact dual-stack websites over IPv4 first.
                Squid will still perform both IPv6 and IPv4 DNS lookups before connecting.
                <div class="alert alert-warning"><b class="text-danger">Warning:</b> This option will restrict the situations under which IPv6
                    connectivity is used (and tested). Hiding network problems
                    which would otherwise be detected and warned about.</div>',
                'advanced':'true'
                ],
                ['id': 'proxy.general.useViaHeader',
                'label':'Use Via header',
                'type':'checkbox',
                'help':'If set (default), Squid will include a Via header in requests and
                        replies as required by RFC2616.',
                'advanced':'true'
                ],
                ['id':'proxy.general.forwardedForHandling',
                'label':'X-Forwarded for header handling',
                'type':'dropdown',
                'help':'Select what to do with X-Forwarded for header.',
                'advanced':'true'
                ],
                ['id': 'proxy.general.suppressVersion',
                'label':'Suppress version string',
                'type':'checkbox',
                'help':'Suppress Squid version string info in HTTP headers and HTML error pages.',
                'advanced':'true'
                ],
                ['id':'proxy.general.uriWhitespaceHandling',
                'label':'Whitespace handling of URI',
                'type':'dropdown',
                'help':'Select what to do with URI that contain whitespaces.<br/>
                        <div class="text-info"><b>NOTE:</b> the current Squid implementation of encode and chop violates
                        RFC2616 by not using a 301 redirect after altering the URL.</div>',
                'advanced':'true'
                ]}
            ],
            [ 'proxy-general-cache-local','Local Cache Settings',
                {['id': 'proxy.general.cache.local.enabled',
                'label':'Enable local cache.',
                'type':'checkbox',
                'help':'Enable or disable the local cache.<br/>
                        Curently only ufs directory cache type is supported.<br/>
                        <b class="text-danger">Do not enable on embedded systems with SD or CF cards as this may break your drive.</b>'
                ],
                ['id': 'proxy.general.cache.local.size',
                'label':'Cache size in Megabytes',
                'type':'text',
                'help':'Enter the storage size for the local cache (default is 100).',
                'advanced':'true'
                ],
                ['id': 'proxy.general.cache.local.l1',
                'label':'Number of first-level subdirectories',
                'type':'text',
                'help':'Enter the number of first-level subdirectories for the local cache (default is 16).',
                'advanced':'true'
                ],
                ['id': 'proxy.general.cache.local.l2',
                'label':'Number of second-level subdirectories',
                'type':'text',
                'help':'Enter the number of first-level subdirectories for the local cache (default is 256).',
                'advanced':'true'
                ]}
            ],
            [ 'proxy-general-traffic','Traffic Management Settings',
                    {['id': 'proxy.general.traffic.enabled',
                    'label':'Enable traffic management.',
                    'type':'checkbox',
                    'help':'Enable or disable traffic management.'
                    ],
                    ['id': 'proxy.general.traffic.maxDownloadSize',
                    'label':'Maximum download size (Kb)',
                    'type':'text',
                    'help':'Enter the maxium size for downloads in kilobytes (leave empty to disable).'
                    ],
                    ['id': 'proxy.general.traffic.maxUploadSize',
                    'label':'Maximum upload size (Kb)',
                    'type':'text',
                    'help':'Enter the maxium size for uploads in kilobytes (leave empty to disable).'
                    ],
                    ['id': 'proxy.general.traffic.OverallBandwidthTrotteling',
                    'label':'Overall bandwidth throtteling (Kbps)',
                    'type':'text',
                    'help':'Enter the allowed overall bandtwith in kilobits per second (leave empty to disable).'
                    ],
                    ['id': 'proxy.general.traffic.perHostTrotteling',
                    'label':'Per host bandwidth throtteling (Kbps)',
                    'type':'text',
                    'help':'Enter the allowed per host bandtwith in kilobits per second (leave empty to disable).'
                    ]}
            ]}
        ],
        ['proxy-forward','Forward Proxy','subtabs': {
            [ 'proxy-forward-general','General Forward Settings',
                {['id': 'proxy.forward.interfaces',
                'label':'Proxy interfaces',
                'type':'select_multiple',
                'style':'tokenize',
                'help':'Select interface(s) the proxy will bind to.',
                'hint':'Type or select interface.'
                ],
                ['id': 'proxy.forward.port',
                'label':'Proxy port',
                'type':'text',
                'help':'The port the proxy service will listen to.'
                ],
                ['id': 'proxy.forward.transparentMode',
                'label':'Enable Transparent HTTP proxy',
                'type':'checkbox',
                'help':'Enable transparent proxy mode to forward all requests for destination port 80 to the proxy server without any additional configuration.'
                ],
                ['id': 'proxy.forward.addACLforInterfaceSubnets',
                'label':'Allow interface subnets',
                'type':'checkbox',
                'help':'When enabled the subnets of the selected interfaces will be added to the allow access list.',
                'advanced':'true'
                ]}
            ],
            [ 'proxy-forward-ftp','FTP Proxy Settings',
                {['id': 'proxy.forward.ftpInterfaces',
                'label':'FTP proxy interfaces',
                'type':'select_multiple',
                'style':'tokenize',
                'help':'Select interface(s) the ftp proxy will bind to.',
                'hint':'Type or select interface (Leave blank to disable ftp proxy).'
                ],
                ['id': 'proxy.forward.ftpPort',
                'label':'FTP proxy port',
                'type':'text',
                'help':'The port the proxy service will listen to.'
                ],
                ['id': 'proxy.forward.ftpTransparentMode',
                'label':'Enable Transparent mode',
                'type':'checkbox',
                'help':'Enable transparent ftp proxy mode to forward all requests for destination port 21 to the proxy server without any additional configuration.'
                ]}
            ],
            [ 'proxy-forward-acl','Access Control List',
                {['id': 'proxy.forward.acl.allowedSubnets',
                'label':'Allowed Subnets',
                'type':'select_multiple',
                'style':'tokenize',
                'help':'Type subnets you want to allow acces to the proxy server, use a comma or press Enter for new item. <div class="text-info"><b>TIP: </b>You can also paste a comma separated list into this field.</div>',
                'hint':'Type subnet adresses (ex. 192.168.2.0/24)',
                'allownew':'true'
                ],
                ['id': 'proxy.forward.acl.unrestricted',
                'label':'Unrestricted IP adresses',
                'type':'select_multiple',
                'style':'tokenize',
                'help':'Type IP adresses you want to allow acces to the proxy server, use a comma or press Enter for new item. <div class="text-info"><b>TIP: </b>You can also paste a comma separated list into this field.</div>',
                'hint':'Type IP adresses (ex. 192.168.1.100)',
                'allownew':'true'
                ],
                ['id': 'proxy.forward.acl.bannedHosts',
                'label':'Banned host IP adresses',
                'type':'select_multiple',
                'style':'tokenize',
                'help':'Type IP adresses you want to deny acces to the proxy server, use a comma or press Enter for new item. <div class="text-info"><b>TIP: </b>You can also paste a comma separated list into this field.</div>',
                'hint':'Type IP adresses (ex. 192.168.1.100)',
                'allownew':'true'
                ],
                ['id': 'proxy.forward.acl.whiteList',
                'label':'Whitelist',
                'type':'select_multiple',
                'style':'tokenize',
                'help':'Whitelist destination domains.<br/>
                        You may use a regular expression, use a comma or press Enter for new item.<br/>
                        <div class="alert alert-info">
                            <b>Examples:</b><br/>
                            <b class="text-primary">.mydomain.com</b> -> matches on <b>*.mydomain.com</b><br/>
                            <b class="text-primary">^http(s|)://([a-zA-Z]+)\.mydomain\.*</b> -> matches on <b>http(s)://*.mydomain.*</b><br/>
                            <b class="text-primary">\\.+\.gif$</b> -> matches on <b>\*.gif</b> but not on <b class="text-danger">\*.gif\test</b><br/>
                            <b class="text-primary">\\.+[0-9]+\.gif$</b> -> matches on <b>\123.gif</b> but not on <b class="text-danger">\test.gif</b><br/>
                        </div>
                        <div class="text-info"><b>TIP: </b>You can also paste a comma separated list into this field.</div>',
                'hint':'Regular expressions are allowed. ',
                'allownew':'true'
                ],
                ['id': 'proxy.forward.acl.blackList',
                'label':'Blacklist',
                'type':'select_multiple',
                'style':'tokenize',
                'help':'Blacklist destination domains.<br/>
                You may use a regular expression, use a comma or press Enter for new item.<br/>
                <div class="alert alert-info">
                    <b>Examples:</b><br/>
                    <b class="text-primary">.mydomain.com</b> -> matches on <b>*.mydomain.com</b><br/>
                    <b class="text-primary">^http(s|)://([a-zA-Z]+)\.mydomain\.*</b> -> matches on <b>http(s)://*.mydomain.*</b><br/>
                    <b class="text-primary">\\.+\.gif$</b> -> matches on <b>\*.gif</b> but not on <b class="text-danger">\*.gif\test</b><br/>
                    <b class="text-primary">\\.+[0-9]+\.gif$</b> -> matches on <b>\123.gif</b> but not on <b class="text-danger">\test.gif</b><br/>
                </div>
                <div class="text-info"><b>TIP: </b>You can also paste a comma separated list into this field.</div>',
                'hint':'Regular expressions are allowed.',
                'allownew':'true'
                ],
                ['id': 'proxy.forward.acl.browser',
                'label':'Block browser/user-agents',
                'type':'select_multiple',
                'style':'tokenize',
                'help':'Block user-agents.<br/>
                You may use a regular expression, use a comma or press Enter for new item.<br/>
                <div class="alert alert-info">
                    <b>Examples:</b><br/>
                    <b class="text-primary">^(.)+Macintosh(.)+Firefox/37\.0</b> -> matches on <b>Macintosh version of Firefox revision 37.0</b><br/>
                    <b class="text-primary">^Mozilla</b> -> matches on <b>all Mozilla based browsers</b><br/>
                </div>
                <div class="text-info"><b>TIP: </b>You can also paste a comma separated list into this field.</div>',
                'hint':'Regular expressions are allowed.',
                'allownew':'true',
                'advanced':'true'
                ],
                ['id': 'proxy.forward.acl.mimeType',
                'label':'Block specific MIME type reply',
                'type':'select_multiple',
                'style':'tokenize',
                'help':'Block specific MIME type reply.<br/>
                You may use a regular expression, use a comma or press Enter for new item.<br/>
                <div class="alert alert-info">
                    <b>Examples:</b><br/>
                    <b class="text-primary">video/flv</b> -> matches on <b>Flash Video</b><br/>
                    <b class="text-primary">application/x-javascript</b> -> matches on <b>javascripts</b><br/>
                </div>
                <div class="text-info"><b>TIP: </b>You can also paste a comma separated list into this field.</div>',
                'hint':'Regular expressions are allowed.',
                'allownew':'true',
                'advanced':'true'
                ],
                ['id': 'proxy.forward.acl.safePorts',
                'label':'Allowed destination TCP port',
                'type':'select_multiple',
                'style':'tokenize',
                'help':'Allowed destination TCP ports, you may use ranges (ex. 222-226) and add comments with collon (ex. 22:ssh).<br/>
                        <div class="text-info"><b>TIP: </b>You can also paste a comma separated list into this field.</div>',
                'hint':'Type port number or range.',
                'allownew':'true',
                'advanced':'true'
                ],
                ['id': 'proxy.forward.acl.sslPorts',
                'label':'Allowed SSL ports',
                'type':'select_multiple',
                'style':'tokenize',
                'help':'Allowed destination SSL ports, you may use ranges (ex. 222-226) and add comments with collon (ex. 22:ssh).<br/>
                <div class="text-info"><b>TIP: </b>You can also paste a comma separated list into this field.</div>',
                'hint':'Type port number or range.',
                'allownew':'true',
                'advanced':'true'
                ]}
            ],
            [ 'proxy-general-authentication', 'Athentication Settings',
                {['id':'proxy.forward.authentication.method',
                'label':'Authentication method',
                'type':'dropdown',
                'help':'Select Authentication method'
                ],
                ['id': 'proxy.forward.authentication.realm',
                'label':'Authentication Prompt',
                'type':'text',
                'help':'The prompt will be displayed in the autherntication request window.'
                ],
                ['id': 'proxy.forward.authentication.credentialsttl',
                'label':'Authentication TTL (hours)',
                'type':'text',
                'help':'This specifies for how long (in hours) the proxy server assumes an externally validated username and password combination is valid (Time To Live).<br/>
                        When the TTL expires, the user will be prompted for credentials again. '
                ],
                ['id': 'proxy.forward.authentication.children',
                'label':'Authentication processes',
                'type':'text',
                'help':'The total number of authenticator processes to spawn.'
                ]}
            ]}
        ]
    },
        'activetab':'proxy-general-settings'
    ])
}}
