{#
 # Copyright (c) 2019 Deciso B.V.
 # Copyright (c) 2019 Michael Muenz <m.muenz@gmail.com>
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
   $(document).ready(function() {
        $("#{{formGridDnsbl['table_id']}}").UIBootgrid({
            search:'/api/unbound/settings/searchDnsbl/',
            get:'/api/unbound/settings/getDnsbl/',
            set:'/api/unbound/settings/setDnsbl/',
            add:'/api/unbound/settings/addDnsbl/',
            del:'/api/unbound/settings/delDnsbl/',
            toggle:'/api/unbound/settings/toggleDnsbl/',
        });

        if (window.location.hash != "") {
            $('a[href="' + window.location.hash + '"]').click();
        } else {
            $('a[href="#blocklists"]').click();
        }

        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            history.pushState(null, null, e.target.hash);
        });

        $("#reconfigureAct").SimpleActionButton();

        $("#grid-domains").UIBootgrid({
            options: {
                static: true,
                navigation: 0,
                selection: false,
                multiSelect: false,
            }
        })

        $("#tester_exec").click(function(){
            const domains = $("#dnsbltester\\.settings\\.domains").val() || [];

            $("#tester_exec_spinner").show();
            ajaxCall('/api/unbound/diagnostics/test_blocklist', {
                'domain': domains.join(","),
                'src': $("#dnsbltester\\.settings\\.source").val(),
            }, function(data, status) {
                if (status == "success" &&  data.length > 0) {
                    $("#grid-domains").bootgrid('replace', data);
                }
                $("#tester_exec_spinner").hide();
            });
        });

        updateServiceControlUI('unbound');
        formatTokenizersUI();
   });
</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li><a data-toggle="tab" href="#blocklists" id="blocklists_tab">{{ lang._('Blocklists') }}</a></li>
    <li><a data-toggle="tab" href="#blocklist_tester" id="blocklist_tester_tab">{{ lang._('Tester') }}</a></li>
</ul>

<div class="tab-content content-box">
    <div id="blocklists" class="tab-pane fade in active">
        {{ partial('layout_partials/base_bootgrid_table', formGridDnsbl)}}
    </div>

    <div id="blocklist_tester" class="tab-pane fade in">
        {{ partial('layout_partials/base_form', ['fields':formDnsblTester,'id':'frm_BlocklistTester']) }}
        <section class="grid-bottom-reserve __mt">
             <div class="alert content-box" style="display: flex; align-items: center; margin-bottom: 0;">
                   <button class="btn btn-primary"  id="tester_exec" type="button">
                        {{ lang._('Test') }}
                        <i id="tester_exec_spinner" class="fa fa-spinner fa-pulse" aria-hidden="true" style="display:none;"></i>
                   </button>
             </div>
        </section>
        <table id="grid-domains" class="table table-condensed table-hover table-striped table-responsive">
            <thead>
                <tr>
                    <th data-column-id="domain" data-width="10em" data-formatter="domain">{{ lang._('Domain') }}</th>
                    <th data-column-id="status" data-width="4em" data-formatter="status">{{ lang._('Status') }}</th>
                    <th data-column-id="action" data-width="4em" data-formatter="action">{{ lang._('Action') }}</th>
                    <th data-column-id="bl" data-formatter="bl">{{ lang._('Blocklist') }}</th>
                    <th data-column-id="category" data-width="10em" data-formatter="category">{{ lang._('Category') }}</th>
                    <th data-column-id="description" data-width="6em" data-formatter="description">{{ lang._('Description') }}</th>
                    <th data-column-id="prio" data-width="6em" data-formatter="prio">{{ lang._('Priority') }}</th>
                    <th data-column-id="hidx" data-width="5em" data-formatter="hidx">{{ lang._('HIDX') }}</th>
                    <th data-column-id="passlist" data-formatter="passlist">{{ lang._('Passlist') }}</th>
                    <th data-column-id="source_nets" data-formatter="source_nets" data-type="expand" >{{ lang._('Source nets') }}</th>
                    <th data-column-id="address" data-formatter="address">{{ lang._('Return address') }}</th>
                    <th data-column-id="rcode" data-width="5em" data-formatter="rcode">{{ lang._('Rcode') }}</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/unbound/service/dnsbl', 'data_exclude_scope': 'blocklist_tester_tab', 'data_change_message_content': 'After changing settings, please remember to apply in order to update the blocklists.'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogDnsbl,'id':formGridDnsbl['edit_dialog_id'],'label':lang._('Edit Blocklist')])}}
