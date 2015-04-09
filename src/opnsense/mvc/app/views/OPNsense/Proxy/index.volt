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

        data_get_map = {'frm_proxy':"/api/proxy/settings/get"};

        // load initial data
        $.each(data_get_map, function(data_index, data_url) {
            ajaxGet(url=data_url,sendData={},callback=function(data,status) {
                if (status == "success") {
                    $("form").each(function( index ) {
                        if ( $(this).attr('id').split('-')[0] == data_index) {
                            // related form found, load data
                            setFormData($(this).attr('id'),data);
                        }
                    });
                }
            });
        });

        // form event handlers
        $("#save_proxy-general").click(function(){

            // save data for General TAB
            saveFormToEndpoint(url="/api/proxy/settings/set",formid="frm_proxy-general",callback_ok=function(){
                // on correct save, perform reconfigure. set progress animation when reloading
                $("#frm_proxy-general_progress").addClass("fa fa-spinner fa-pulse");

                //
                ajaxCall(url="/api/proxy/service/reconfigure", sendData={}, callback=function(data,status){
                    // when done, disable progress animation.
                    $("#frm_proxy-general_progress").removeClass("fa fa-spinner fa-pulse");

                    if (status != "success" || data['status'] != 'ok' ) {
                        // fix error handling
                        BootstrapDialog.show({
                            type:BootstrapDialog.TYPE_WARNING,
                            title: 'Proxy General TAB',
                            message: JSON.stringify(data)
                        });
                    }
                });

            });
        });
        $("#save_proxy-forward-general").click(function(){
            // save data for Proxy TAB
            saveFormToEndpoint(url="/api/proxy/settings/set",formid="frm_proxy-forward-general",callback_ok=function(){
                // on correct save, perform reconfigure. set progress animation when reloading
                $("#frm_proxy-forward-general_progress").addClass("fa fa-spinner fa-pulse");

                //
                ajaxCall(url="/api/proxy/service/reconfigure", sendData={}, callback=function(data,status){
                    // when done, disable progress animation.
                    $("#frm_proxy-forward-general_progress").removeClass("fa fa-spinner fa-pulse");

                    if (status != "success" || data['status'] != 'ok' ) {
                        // fix error handling
                        BootstrapDialog.show({
                            type:BootstrapDialog.TYPE_WARNING,
                            title: 'Proxy Server TAB',
                            message: JSON.stringify(data)
                        });
                    }
                });

            });
        });

        // handle help messages show/hide
        $('[id*="show_all_help"]').click(function() {
            $('[id*="show_all_help"]').toggleClass("fa-toggle-on fa-toggle-off");
            $('[id*="show_all_help"]').toggleClass("text-success text-danger");
            if ($('[id*="show_all_help"]').hasClass("fa-toggle-on")) {
                $('[for*="help_for"]').addClass("show");
                $('[for*="help_for"]').removeClass("hidden");
            } else {
                $('[for*="help_for"]').addClass("hidden");
                $('[for*="help_for"]').removeClass("show");
            }
        });

        // handle advanced show/hide
        $('[data-advanced*="true"]').hide(function(){
            $('[data-advanced*="true"]').after("<tr data-advanced='hidden_row'></tr>"); // the table row is added to keep correct table striping
        });
        $('[id*="show_advanced"]').click(function() {
            $('[id*="show_advanced"]').toggleClass("fa-toggle-on fa-toggle-off");
            $('[id*="show_advanced"]').toggleClass("text-success text-danger");
            if ($('[id*="show_advanced"]').hasClass("fa-toggle-on")) {
                $('[data-advanced*="true"]').show();
                $('[data-advanced*="hidden_row"]').remove(); // the table row is deleted to keep correct table striping
            } else {
                $('[data-advanced*="true"]').after("<tr data-advanced='hidden_row'></tr>").hide(); // the table row is added to keep correct table striping
            }
        });

        // Apply tokenizer
        setTimeout(function(){
            $('select[class="tokenize"]').each(function(){
                if ($(this).prop("size")==0) {
                    maxDropdownHeight=String(36*5)+"px"; // default number of items

                } else {
                    number_of_items = $(this).prop("size");
                    maxDropdownHeight=String(36*number_of_items)+"px";
                }
                hint=$(this).data("hint");
                width=$(this).data("width");
                allownew=$(this).data("allownew");
                maxTokenContainerHeight=$(this).data("maxheight");

                $(this).tokenize({
                    displayDropdownOnFocus: true,
                    newElements: allownew,
                    placeholder:hint
                });
                $(this).parent().find('ul[class="TokensContainer"]').parent().css("width",width);
                $(this).parent().find('ul[class="Dropdown"]').css("max-height", maxDropdownHeight);
                if ( maxDropdownHeight != undefined ) {
                    $(this).parent().find('ul[class="TokensContainer"]').css("max-height", maxTokenContainerHeight);
                }
            })
        },500);

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
        ['proxy-general','General Proxy Settings',
            {['id': 'proxy.general.enabled',
            'label':'Enable proxy',
            'type':'checkbox',
            'help':'Enable or disable the proxy service.'
            ],
            ['id': 'proxy.general.icpPort',
            'label':'ICP port',
            'type':'text',
            'help':'The port number where Squid sends and receives ICP queries to
                    and from neighbor caches. Leave blank to disable (default). The standard UDP port for ICP is 3130.'
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
            ['id': 'proxy.general.suppressVersion',
            'label':'Suppress version string',
            'type':'checkbox',
            'help':'Suppress Squid version string info in HTTP headers and HTML error pages.',
            'advanced':'true'
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
                'help':'Enable transparent proxe mode to forward all requests for destination port 80 to the proxy server without any additional configuration.'
                ],
                ['id': 'proxy.forward.addACLforInterfaceSubnets',
                'label':'Allow interface subnets',
                'type':'checkbox',
                'help':'When enabled the subnets of the selected interfaces will be added to the allow access list.',
                'advanced':'true'
                ]}
            ]}
        ]
    },
        'activetab':'proxy-general'
    ])
}}
