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
        let gridLoaded = false;
        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            $('#reconfigureAct').closest('content-box').show();
            if (e.target.id === 'blocklists_tab' && !gridLoaded) {
                $("#{{formGridDnsbl['table_id']}}").UIBootgrid({
                    search:'/api/unbound/settings/searchDnsbl/',
                    get:'/api/unbound/settings/getDnsbl/',
                    set:'/api/unbound/settings/setDnsbl/',
                    add:'/api/unbound/settings/addDnsbl/',
                    del:'/api/unbound/settings/delDnsbl/',
                    toggle:'/api/unbound/settings/toggleDnsbl/',
                    options: {
                        formatters: {
                            'listDisplay': function(column, row) {
                                let id = `%${column.id}` in row ? `%${column.id}` : column.id;
                                return row[id].split(',').join("<br/>");
                            }
                        }
                    }
                });
                gridLoaded = true;
            } else if (e.target.id === 'blocklist_tester_tab') {
                $('#reconfigureAct').closest('content-box').hide();
            }
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

        $("#tester_exec").click(function() {
            $("#tester_exec_spinner").show();
            ajaxCall('/api/unbound/diagnostics/testBlocklist', {
                'domain': $("#tester_domain").val(),
                'src': $("#tester_src").val(),
            }, function(data, status) {
                $("#blocklist_tester_result").empty();
                $("#blocklist_tester_result").append($("<span/>")).text("{{lang._('Result')}}");
                if (data.status !== undefined || data.action !== undefined) {
                    $("#blocklist_tester_result").append($("<pre  style='white-space: pre-wrap; word-break: keep-all;'/>").text(JSON.stringify(data, null, 2)));
                } else {
                    $("#blocklist_tester_result").append($("<span/>").text("-"));
                }
                $("#tester_exec_spinner").hide();
            });
        });

        updateServiceControlUI('unbound');
   });
</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li><a data-toggle="tab" href="#blocklists" id="blocklists_tab">{{ lang._('Blocklists') }}</a></li>
    <li><a data-toggle="tab" href="#blocklist_tester" id="blocklist_tester_tab">{{ lang._('Tester') }}</a></li>
</ul>
<div class="tab-content content-box __mb">
    <div id="blocklists" class="tab-pane fade in active">
        {{ partial('layout_partials/base_bootgrid_table', formGridDnsbl)}}
    </div>

    <div id="blocklist_tester" class="tab-pane fade in">
        <table class="table table-condensed table-striped">
            <thead>
                <tr>
                <th>{{ lang._('Property') }}</th>
                <th>{{ lang._('Value') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ lang._('Domain') }}</td>
                    <td><input type="text" id='tester_domain'></td>
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
{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/unbound/service/dnsbl'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogDnsbl,'id':formGridDnsbl['edit_dialog_id'],'label':lang._('Edit Blocklist')])}}
