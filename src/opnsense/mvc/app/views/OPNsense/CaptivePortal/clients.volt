{#
 # Copyright (c) 2014-2024 Deciso B.V.
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

<script src="{{ cache_safe('/ui/js/moment-with-locales.min.js') }}"></script>

<script>

    $( document ).ready(function() {
        ajaxGet("/api/captiveportal/session/zones/", {}, function(data, status) {
            if (status == "success") {
                $('#zone-selection').empty();
                $.each(data, function(key, value) {
                    $('#zone-selection').append($("<option></option>").attr("value", key).text(value));
                });
                $('.selectpicker').selectpicker('refresh');
            }
        });

        $("#zone-selection").on("changed.bs.select", function (e) {
            $("#grid-clients").bootgrid('reload');
        });
        $("#grid-clients").UIBootgrid({
            search:'/api/captiveportal/session/search/',
            datakey: 'sessionId',
            commands: {
                disconnect: {
                    title: "{{ lang._('Disconnect') }}",
                    method: function() {
                        let sessid = $(this).data("row-id") !== undefined ? $(this).data("row-id") : '';
                        stdDialogConfirm(
                            "{{ lang._('Confirm disconnect') }}",
                            "{{ lang._('Do you want to disconnect the selected client?') }}",
                            "{{ lang._('Yes') }}",
                            "{{ lang._('Cancel') }}",
                            function () {
                                ajaxCall("/api/captiveportal/session/disconnect",{'sessionId': sessid}, function(data,status){
                                    $("#grid-clients").bootgrid('reload');
                                });
                        });
                    },
                    classname: 'fa fa-trash-o fa-fw',
                    sequence: 1,
                }
            },
            options: {
                selection: false,
                multiSelect: false,
                useRequestHandlerOnGet: true,
                requestHandler: function(request) {
                    request['selected_zones'] = $("#zone-selection").val();
                    return request;
                },
                formatters: {
                    ipAddress: function(column, row) {
                        // Try to get enriched arrays first
                        let ipv4Addresses = Array.isArray(row.ipv4Addresses) ? row.ipv4Addresses : [];
                        let ipv6Addresses = Array.isArray(row.ipv6Addresses) ? row.ipv6Addresses : [];

                        // Fallback: if arrays are empty but ipAddress exists, use it
                        if (ipv4Addresses.length === 0 && ipv6Addresses.length === 0 && row.ipAddress) {
                            // Classify the stored IP
                            if (row.ipAddress.indexOf(':') >= 0) {
                                ipv6Addresses = [row.ipAddress];
                            } else {
                                ipv4Addresses = [row.ipAddress];
                            }
                        }

                        // Combine all addresses (IPv4 first, then IPv6)
                        let allAddresses = ipv4Addresses.concat(ipv6Addresses);

                        if (allAddresses.length === 0) {
                            return '<span class="text-muted">-</span>';
                        }

                        // Show all addresses with tooltip for full content
                        // Escape HTML for tooltip title attribute
                        let tooltip = allAddresses.join('\n').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                        let displayHtml = allAddresses.join('<br>');
                        // Wrap in span with tooltip - use data-html="false" for plain text tooltip
                        return '<span data-toggle="tooltip" data-placement="top" title="' + tooltip + '">' + displayHtml + '</span>';
                    },
                    userName: function(column, row) {
                        // Extract IP from username@ip format and show just username
                        let userName = row.userName || '';
                        if (userName && userName.indexOf('@') >= 0) {
                            return userName.split('@')[0] || userName;
                        }
                        return userName;
                    }
                }
            }
        });

        $("#zone-selection-wrapper").detach().insertBefore('#grid-clients-header .search');
    });
</script>

<style>
    [data-column-id="ipAddress"] {
        white-space: normal !important;
        word-break: break-all;
        line-height: 1.5;
        max-height: 80px;
        overflow: hidden;
        text-overflow: ellipsis;
    }
</style>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs"></ul>
<div class="tab-content content-box col-xs-12 __mb">
    <div class="btn-group" id="zone-selection-wrapper">
        <select class="selectpicker" multiple="multiple" data-live-search="true" id="zone-selection" data-width="auto" title="{{ lang._('All Zones') }}">
        </select>
    </div>
    <table id="grid-clients" class="table table-condensed table-hover table-striped table-responsive">
        <thead>
            <tr>
                <th data-column-id="sessionId" data-type="string" data-identifier="true" data-visible="false">{{ lang._('Session') }}</th>
                <th data-column-id="zoneid" data-width="7em"  data-type="string" data-visible="false">{{ lang._('Zoneid') }}</th>
                <th data-column-id="userName" data-type="string" data-width="10em" data-formatter="userName">{{ lang._('Username') }}</th>
                <th data-column-id="macAddress" data-type="string" data-width="10em" data-css-class="hidden-xs hidden-sm" data-header-css-class="hidden-xs hidden-sm">{{ lang._('MAC address') }}</th>
                <th data-column-id="ipAddress" data-type="string" data-width="9em" data-formatter="ipAddress" data-css-class="hidden-xs hidden-sm" data-header-css-class="hidden-xs hidden-sm">{{ lang._('IP Address') }}</th>
                <th data-column-id="bytes_in" data-type="string" data-width="7em" data-formatter="bytes" data-css-class="hidden-xs hidden-sm" data-header-css-class="hidden-xs hidden-sm">{{ lang._('Bytes (in)') }}</th>
                <th data-column-id="bytes_out" data-type="string" data-width="7em" data-formatter="bytes" data-css-class="hidden-xs hidden-sm" data-header-css-class="hidden-xs hidden-sm">{{ lang._('Bytes (out)') }}</th>
                <th data-column-id="startTime" data-type="datetime" data-width="10em">{{ lang._('Connected since') }}</th>
                <th data-column-id="last_accessed" data-type="datetime" data-width="10em" data-css-class="hidden-xs hidden-sm" data-header-css-class="hidden-xs hidden-sm">{{ lang._('Last accessed') }}</th>
                <th data-column-id="commands" data-searchable="false" data-width="5em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
</div>
