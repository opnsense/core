{#
 # Copyright (c) 2014-2025 Deciso B.V.
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1. Redistributions of source code must retain the above copyright notice,
 #    this list of conditions and the following disclaimer.
 #
 # 2. Redistributions in binary form must reproduce the above copyright notice,
 #    this list of conditions and the following disclaimer in the documentation
 #    and/or other materials provided with the distribution.
 #
 # THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 # INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 # AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 # AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 # OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 # SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 # INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 # CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 # ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 # POSSIBILITY OF SUCH DAMAGE.
 #}

<script>

    $( document ).ready(function() {
        let this_page = window.location.href.replace(/[/]/g, '').toLowerCase().endsWith('forward') ? 'Forward' : 'Dot';
        $('tr[id="row_unbound.forwarding.info"]').addClass('hidden');
        /* Handle retrieval and saving of the single system forwarding checkbox */
        let data_get_map = {'frm_ForwardingSettings':"/api/unbound/settings/get"};
        mapDataToFormUI(data_get_map).done(function(data) {
            /* only called on page load */
            if (data.frm_ForwardingSettings.unbound.forwarding.enabled == "1") {
                toggleNameservers(true);
            }
        });

        $(".forwarding-enabled").click(function() {
            saveFormToEndpoint(url="/api/unbound/settings/set", formid='frm_ForwardingSettings');

            let checked = ($(this).is(':checked'));
            toggleNameservers(checked);
        });

        function toggleNameservers(checked) {
            if (checked) {
                ajaxGet(url="/api/unbound/settings/get_nameservers", {}, callback=function(data, status) {
                    $('tr[id="row_unbound.forwarding.info"]').removeClass('hidden');
                    if (data.length && !data.includes('')) {
                        $('div[id="control_label_unbound.forwarding.info"]').append(
                            "<span>{{ lang._('The following nameservers are used:') }}</span>"
                        );
                        $('span[id="unbound.forwarding.info"]').append(
                            "<div><b>" + data.join(", ") + "</b></div>"
                        );
                    } else {
                        $('div[id="control_label_unbound.forwarding.info"]').append(
                            "<span>{{ lang._('There are no system nameservers configured. Please do so in ') }}<a href=\"/system_general.php\">System: Settings: General</a></span>"
                        );
                    }

                });
            } else {
                $('tr[id="row_unbound.forwarding.info"]').addClass('hidden');
                $('div[id="control_label_unbound.forwarding.info"]').children().not(':first').remove();
                $('span[id="unbound.forwarding.info"]').children().remove();
            }
        }

        $("#{{formGridDot['table_id']}}").UIBootgrid(
                {   'search':'/api/unbound/settings/search'+this_page+'/',
                    'get':'/api/unbound/settings/get'+this_page+'/',
                    'set':'/api/unbound/settings/set'+this_page+'/',
                    'add':'/api/unbound/settings/add'+this_page+'/',
                    'del':'/api/unbound/settings/del'+this_page+'/',
                    'toggle':'/api/unbound/settings/toggle'+this_page+'/'
                }
        );

        $("div.actionBar").parent().prepend($('<td id="heading-wrapper" class="col-sm-2 theading-text">{{ lang._('Custom forwarding') }}</div>'));

        /* Hide/unhide verify field based on type */
        if ("{{selected_forward}}" == "forward") {
            $('tr[id="row_dot.verify"]').addClass('hidden');
            $('tr[id="row_dot.forward_tcp_upstream"]').removeClass('hidden');
        } else {
            $('tr[id="row_dot.verify"]').removeClass('hidden');
            $('tr[id="row_dot.forward_tcp_upstream"]').addClass('hidden');
            /* remove advanced option toggle (currently no advanced options for DNS over TLS) */
            $("#show_advanced_formDialog{{ formGridDot['edit_dialog_id'] }}").closest('td').html('');
        }

        /**
         * Reconfigure unbound - activate changes
         */
        $("#reconfigureAct").SimpleActionButton();

	updateServiceControlUI('unbound');
    });

</script>

<style>
    .theading-text {
        font-weight: 800;
        font-style: italic;
    }

    #infosection {
        margin: 1em;
    }
</style>

<div class="content-box __mb">
    {# include base forwarding form #}
    {{ partial("layout_partials/base_form",['fields':forwardingForm,'id':'frm_ForwardingSettings'])}}
</div>
<div class="content-box __mb">
    {{ partial('layout_partials/base_bootgrid_table', formGridDot)}}
    <div id="infosection" class="tab-content col-xs-12 __mb">
        {{ lang._('Please note that entries without a specific domain (and thus all domains) specified in both Query Forwarding and DNS over TLS
        are considered duplicates, DNS over TLS will be preferred. If "Use System Nameservers" is checked, Unbound will use the DNS servers entered
        in System->Settings->General or those obtained via DHCP or PPP on WAN if the "Allow DNS server list to be overridden by DHCP/PPP on WAN" is checked.') }}
    </div>
</div>
{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/unbound/service/reconfigure', 'data_service_widget': 'unbound'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogEdit,'id':formGridDot['edit_dialog_id'],'label':lang._('Edit server')])}}
