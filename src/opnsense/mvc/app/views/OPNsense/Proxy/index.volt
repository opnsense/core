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
        $("#save_proxy-forward").click(function(){
            // save data for Proxy TAB
            saveFormToEndpoint(url="/api/proxy/settings/set",formid="frm_proxy-forward",callback_ok=function(){
                // on correct save, perform reconfigure. set progress animation when reloading
                $("#frm_proxy-forward_progress").addClass("fa fa-spinner fa-pulse");

                //
                ajaxCall(url="/api/proxy/service/reconfigure", sendData={}, callback=function(data,status){
                    // when done, disable progress animation.
                    $("#frm_proxy-forward_progress").removeClass("fa fa-spinner fa-pulse");

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

        setTimeout(function(){
            $('select[class="tokenize"]').each(function(){
                if ($(this).prop("size")==0) {
                    //number_of_items = $(this).children('option').length;
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

<!-- TODO: explain TABS
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
             ]}
        ],
        ['proxy-forward','Forward Proxy',
            {['id': 'proxy.forward.interfaces',
            'label':'Proxy interfaces',
            'type':'select_multiple',
            'style':'tokenize',
            'help':'Select interface(s) the proxy will bind to.',
            'hint':'Type or select interface'
            ],
            ['id': 'proxy.forward.port',
            'label':'Proxy port',
            'type':'text',
            'help':'The port the proxy service will listen to.'
            ],
            ['id': 'proxy.forward.addACLforInterfaceSubnets',
            'label':'Allow interface subnets',
            'type':'checkbox',
            'help':'When enabled the subnets of the selected interfaces will be added to the allow access list.'
            ],
            ['id': 'proxy.forward.transparentProxyMode',
            'label':'Enable Transparent HTTP proxy',
            'type':'checkbox',
            'help':'Enable transparent proxe mode to forward all requests for destination port 80 to the proxy server without any additional configuration.'
            ],
            ['id': 'proxy.forward.alternateDNSservers',
            'label':'Use alternate DNS-servers',
            'type':'select_multiple',
            'style':'tokenize',
            'help':'Type IPs of alternative DNS servers you like to use.',
            'hint':'Type or select interface',
            'allownew':'true'
            ]}
        ]
    },
        'activetab':'proxy-general'
    ])
}}

