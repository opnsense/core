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

        $("#testAct").SimpleActionButton({
            onAction: function(data) {
                const domains = $("#blocklisttester\\.domains").val() || [];

                $("#testAct_spinner").show();
                ajaxCall('/api/unbound/diagnostics/test_blocklist', {
                    'domain': domains,
                    'src': $("#tester_src").val(),
                }, function(data, status) {
                    var $result = $("#blocklist_tester_result").empty();
                    $result.append($("<strong>")).text("{{lang._('Result:')}}");
                    $result.append($("<br>"))

                    if (status === "error") {
                        $result.append($("<pre style='white-space: pre-wrap; word-break: keep-all;'/>").text(JSON.stringify(data, null, 2)));
                    } else {
                        var domains = Object.keys(data);
                        for (var i = 0; i < domains.length; i++) {
                            var domain = domains[i];
                            $result.append($("<span/>").text(domain));
                            $result.append($("<pre style='white-space: pre-wrap; word-break: keep-all;'/>")
                                .text(JSON.stringify(data[domain], null, 2)));
                        }
                    
                    }
                    $("testAct_spinner").hide();
                });
            }
        });

        $("#tester_exec").click(function() {
            const domains = $("#unbound\\.blocklist\\.domain_tester").val() || [];

            $("#tester_exec_spinner").show();
            ajaxCall('/api/unbound/diagnostics/test_blocklist', {
                'domain': domains,
                'src': $("#tester_src").val(),
            }, function(data, status) {
                var $result = $("#blocklist_tester_result").empty();
                $result.append($("<strong>")).text("{{lang._('Result:')}}");
                $result.append($("<br>"))

                if (status === "error") {
                    $result.append($("<pre style='white-space: pre-wrap; word-break: keep-all;'/>").text(JSON.stringify(data, null, 2)));
                } else {
                    var domains = Object.keys(data);
                    for (var i = 0; i < domains.length; i++) {
                        var domain = domains[i];
                        $result.append($("<span/>").text(domain));
                        $result.append($("<pre style='white-space: pre-wrap; word-break: keep-all;'/>")
                            .text(JSON.stringify(data[domain], null, 2)));
                    }
                
                }
                $("#tester_exec_spinner").hide();
            });
        });

        updateServiceControlUI('unbound');
        // formatTokenizersUI();
   });
</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li><a data-toggle="tab" href="#blocklists" id="blocklists_tab">{{ lang._('Blocklists') }}</a></li>
    <li><a data-toggle="tab" href="#blocklist_tester" id="blocklist_tester_tab">{{ lang._('Tester') }}</a></li>
    <li><a data-toggle="tab" href="#blocklist_tester_old" id="blocklist_tester_tab">{{ lang._('Tester_old') }}</a></li>
</ul>

<div class="tab-content content-box">
    <div id="blocklists" class="tab-pane fade in active">
        {{ partial('layout_partials/base_bootgrid_table', formGridDnsbl)}}
    </div>

    <div id="blocklist_tester" class="tab-pane fade in">
        {{ partial('layout_partials/base_form', ['fields':formDnsblTester,'id':'frm_BlocklistTester']) }}
        <button class="btn btn-primary" id="testAct"  data-label="{{ lang._('Test') }}"></button>
    </div>

    <div id="blocklist_tester_old" class="tab-pane fade in">
        <table class="table table-condensed table-striped">
            <thead>
                <tr>
                <th>{{ lang._('Property') }}</th>
                <th>{{ lang._('Value') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <div>
                        <td>{{ lang._('Domain') }}</td>
                        <td>
                            <div id="select_unbound.blocklist.domain_tester" style>
                                <select name="domains[]" multiple="multiple" 
                                        id="unbound.blocklist.domain_tester"
                                        class="tokenize" 
                                        data-container="body" 
                                        data-hint="Nothing selected" 
                                        data-none-selected-text="{{ lang._('Nothing selected') }}" 
                                        data-width="346px" 
                                        data-allownew="true" 
                                        data-sortable="false" 
                                        style="display: none;">
                                </select>
                                <div>
                                    <a href="#" class="text-danger" id="clear-options_unbound.blocklist.domain_tester"><i class="fa fa-times-circle"></i> <small>Clear All</small></a>
                                    &nbsp;&nbsp;
                                    <a href="#" class="text-danger" id="copy-options_unbound.blocklist.domain_tester"><i class="fa fa-copy"></i> <small>Copy</small></a>
                                    &nbsp;&nbsp;
                                    <a href="#" class="text-danger" id="paste-options_unbound.blocklist.domain_tester" style=""><i class="fa fa-paste"></i> <small>Paste</small></a>
                                    &nbsp;&nbsp;
                                    <a href="#" class="text-danger" id="to-text_unbound.blocklist.domain_tester"><i class="fa fa-file-text-o"></i> <small>Text</small> </a>
                                </div>
                            </div>
                        </td>
                    </div>
                </tr>
                <tr>
                    <td>{{ lang._('Source') }}</td>
                    <td><input type="text"  id='tester_src'></td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td>
                        <button class="btn btn-primary" id="tester_exec">
                            {{ lang._('Test') }}
                            <i id="tester_exec_spinner" class="fa fa-spinner fa-pulse" aria-hidden="true" style="display:none;"></i>
                        </button>
                    </td>
                </tr>
            </tfoot>
            </table>
            <div id="blocklist_tester_result">
            </div>
        </table>
    </div>
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/unbound/service/dnsbl', 'data_exclude_scope': 'blocklist_tester_tab', 'data_change_message_content': 'After changing settings, please remember to apply in order to update the blocklists.'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogDnsbl,'id':formGridDnsbl['edit_dialog_id'],'label':lang._('Edit Blocklist')])}}