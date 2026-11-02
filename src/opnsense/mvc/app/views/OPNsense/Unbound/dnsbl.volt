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

        $("#tester_exec").click(function(){
            const domains = $("#blocklisttester\\.domains").val() || [];

            $("#tester_exec_spinner").show();
            ajaxCall('/api/unbound/diagnostics/test_blocklist', {
                'domain': domains.join(","),
                'src': $("#blocklisttester\\.source").val(),
            }, function(data, status) {
                var $result = $("#blocklist_tester_result").empty();
                $result.append($("<h1>").text("{{lang._('Result:')}}"));

                if (status === "error") {
                    $result.append($("<pre style='white-space: pre-wrap; word-break: keep-all;'/>").text(JSON.stringify(data, null, 2)));
                } else {
                    var domains = Object.keys(data);
                    for (var i = 0; i < domains.length; i++) {
                        var domain = domains[i];
                        $result.append($("<strong/>").text(domain));
                        $result.append($("<pre style='white-space: pre-wrap; word-break: keep-all;'/>")
                            .text(JSON.stringify(data[domain], null, 2).replace(/"/g,"")));
                    }
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
        <div id="blocklist_tester_result">
        </div>
    </div>
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/unbound/service/dnsbl', 'data_exclude_scope': 'blocklist_tester_tab', 'data_change_message_content': 'After changing settings, please remember to apply in order to update the blocklists.'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogDnsbl,'id':formGridDnsbl['edit_dialog_id'],'label':lang._('Edit Blocklist')])}}