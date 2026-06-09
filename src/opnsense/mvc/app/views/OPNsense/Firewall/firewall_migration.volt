{#
 # Copyright (c) 2026 Deciso B.V.
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
 # THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
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
        $("#remove_rules").click(function(){
          BootstrapDialog.show({
              type:BootstrapDialog.TYPE_WARNING,
              title: '{{ lang._('Flush') }}',
              message: "{{ lang._('Are you sure you want to remove all legacy firewall rules.') }}",
              buttons: [{
                  label: '{{ lang._('Yes') }}',
                  action: function(dialogRef){
                      dialogRef.close();
                      $("#flushAct_progress").addClass("fa fa-spinner fa-pulse");
                      ajaxCall("/api/firewall/migration/flush", {}, function(data,status) {
                            if (data.status === 'ok') {
                                window.location = '/ui/firewall/filter/';
                            }
                      });
                  }
              },{
                  label: '{{ lang._('No') }}',
                  action: function(dialogRef){
                      dialogRef.close();
                  }
              }]
          });
        });
        $("#remove_outbound").click(function(){
            BootstrapDialog.show({
                type:BootstrapDialog.TYPE_WARNING,
                title: "{{ lang._('Flush') }}",
                message: "{{ lang._('Are you sure you want to remove all legacy outbound NAT rules.') }}",
                buttons: [{
                    label: "{{ lang._('Yes') }}",
                    action: function(dialogRef){
                        dialogRef.close();
                        $("#flushAct_progress").addClass("fa fa-spinner fa-pulse");
                        ajaxCall("/api/firewall/migration/flush_outbound", {}, function(data,status) {
                            if (data.status === 'ok') {
                                window.location = '/ui/firewall/source_nat/';
                            }
                        });
                    }
                }, {
                    label: "{{ lang._('No') }}",
                    action: function(dialogRef){
                        dialogRef.close();
                    }
                }]
            });
        });
        ajaxCall("/api/firewall/migration/count_rules", {}, function(data) {
            if (data.status === "ok" && data.count > 0) {
                $("#legacy_rules_count").text(data.count);
                $("#migration_rules_tab").removeClass("hidden");
                $("#filter-rules-migration").removeClass("hidden");
                $("#migration_rules_tab_link").tab("show");
            }
        });
        ajaxCall("/api/firewall/migration/count_outbound", {}, function(data) {
            if (data.status === "ok" && data.count > 0) {
                $("#legacy_outbound_count").text(data.count);
                $("#migration_outbound_tab").removeClass("hidden");
                $("#source-nat-migration").removeClass("hidden");
                if ($("#migration_rules_tab").hasClass("hidden")) {
                    $("#migration_outbound_tab_link").tab("show");
                }
            }
        });
    });
</script>
<style>
    div.miglist {
        counter-reset: list-number;
        margin:10px;
    }

    div.miglist div:before {
        counter-increment: list-number;
        content: counter(list-number);

        margin-right: 10px;
        margin-bottom:10px;
        width:30px;
        height:30px;
        display:inline-flex;
        align-items:center;
        justify-content: center;
        font-size:14px;
        background-color:#d7385e;
        border-radius:50%;
        color:#fff;
    }

    .badge {
        background-color: #31708f;
    }

</style>

<ul class="nav nav-tabs" role="tablist">
    <li id="migration_rules_tab" class="hidden">
        <a id="migration_rules_tab_link" href="#filter-rules-migration" data-toggle="tab">
            {{ lang._('Firewall rules') }}
        </a>
    </li>
    <li id="migration_outbound_tab" class="hidden">
        <a id="migration_outbound_tab_link" href="#source-nat-migration" data-toggle="tab">
            {{ lang._('Outbound NAT rules') }}
        </a>
    </li>
</ul>

<div class="tab-content content-box">
    <div id="filter-rules-migration" class="tab-pane fade hidden">
        <pre class="migration-text">
{{ lang._('
    To switch from the legacy rules to the new rules interface, a migration is needed.
    As this can be a risky operation, manual intervention is required.

    This module assists you in moving your rules to the new application and offers pointers to
    various components available to guide you through the process.

    When using a ZFS based setup, you can use snapshots to revert back to the old situation when accidents happen.
    The other option is to use configuration history to undo changes or backup your configuration [1].

    To prevent being locked out during the process, do not disable the anti-lockout rule and access the machine
    via your LAN interface [2].

    With all preparations in place, we can export the rules into a format our new rules interface understands [3].

    {tip} Use a tool like Microsoft Excel to inspect and modify rules in the CSV file before importing them or when certain validations fail.

    Now we can import the existing rules into the new user interface [4].

    After validating the rules are as expected, you can remove all legacy rules via [5] which forwards you to the new rules page after completion.

') }}
        </pre>

        <div class="miglist">
            <div>
                <i class="fa fa-fw fa-book"></i>
                <a target="_new" href="https://docs.opnsense.org/manual/snapshots.html">{{ lang._('Snapshots') }}</a> /
                <a target="_new" href="https://docs.opnsense.org/manual/backups.html#history">{{ lang._('Configuration history') }}</a>
            </div>
            <div>
                <i class="fa fa-fw fa-check"></i>
                <a target="_new" href="/system_advanced_firewall.php">{{ lang._('Do not disable anti-lockout in advanced settings') }}</a>
            </div>
            <div>
                <i class="fa fa-fw fa-file-csv"></i>
                <a href="/api/firewall/migration/download_rules">{{ lang._('Export current rules') }}</a>
                <span id="legacy_rules_count" class="badge"></span>
            </div>
            <div>
                <i class="fa fa-fw fa-upload"></i>
                <a target="_new" href="/ui/firewall/filter/">{{ lang._('Import rules using the button in the grid footer') }}</a>
            </div>
            <div>
                <i class="fa fa-fw fa-trash"></i>
                <a id="remove_rules" style="cursor: pointer;">{{ lang._('Remove all legacy firewall rules') }}</a>
                <i id="flushAct_progress" class=""></i>
            </div>
        </div>
    </div>

    <div id="source-nat-migration" class="tab-pane fade hidden">
        <pre class="migration-text">
{{ lang._('
    To switch from the legacy outbound NAT rules to the new Source NAT rules interface, a migration is needed.
    As this can be a risky operation, manual intervention is required.

    This module assists you in exporting legacy outbound NAT rules into a format the new Source NAT rules interface understands.

    When using a ZFS based setup, you can use snapshots to revert back to the old situation when accidents happen.
    The other option is to use configuration history to undo changes or backup your configuration [1].

    With all preparations in place, you can export the legacy outbound NAT rules [2].

    {tip} Use a tool like Microsoft Excel to inspect and modify rules in the CSV file before importing them or when certain validations fail.

    Now you can import the exported rules into the new Source NAT user interface [3].

    After validating the imported rules, review the configured Source NAT mode in the new interface and apply the firewall configuration.

') }}
        </pre>

        <div class="miglist">
            <div>
                <i class="fa fa-fw fa-book"></i>
                <a target="_new" href="https://docs.opnsense.org/manual/snapshots.html">{{ lang._('Snapshots') }}</a> /
                <a target="_new" href="https://docs.opnsense.org/manual/backups.html#history">{{ lang._('Configuration history') }}</a>
            </div>
            <div>
                <i class="fa fa-fw fa-file-csv"></i>
                <a href="/api/firewall/migration/download_outbound">{{ lang._('Export legacy outbound NAT rules') }}</a>
                <span id="legacy_outbound_count" class="badge"></span>
            </div>
            <div>
                <i class="fa fa-fw fa-upload"></i>
                <a target="_new" href="/ui/firewall/source_nat/">{{ lang._('Import rules using the button in the grid footer') }}</a>
            </div>
            <div>
                <i class="fa fa-fw fa-trash"></i>
                <a id="remove_outbound" style="cursor: pointer;">{{ lang._('Remove all legacy outbound NAT rules') }}</a>
                <i id="flushAct_progress" class=""></i>
            </div>
        </div>
    </div>
</div>
