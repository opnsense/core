{#
 # Copyright (c) 2023 Deciso B.V.
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
 #  INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
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
        $("#grid-overview").UIBootgrid(
            {
                search: '/api/interfaces/overview/ifinfo',
                options: {
                    formatters: {
                        "interface": function (column, row) {
                            return row.descr + ' (' + row.if_ident + ')';
                        },
                        "routes": function (column, row) {
                            let elements = '';
                            if (row.routes) {
                                row.routes.forEach(function (route) {
                                    elements += '<span">' + route + '</span><br/>';
                                });
                            }
                            return elements;
                        
                        },
                        "status": function (column, row) {
                            let element = row.status;

                            if (!row.enabled) {
                                element += ' (disabled)';
                            }

                            return element;
                        }
                    }
                }
            }
        )
    });
</script>

<div class="tab-content content-box">
    <table id="grid-overview" class="table table-condensed table-hover table-striped">
        <thead>
            <tr>
                <th data-formatter="interface" data-type="string">{{ lang._('Interface') }}</th>
                <th data-column-id="device" data-type="string">{{ lang._('Device') }}</th>
                <th data-column-id="status" data-formatter="status" data-type="string">{{ lang._('Status') }}</th>
                <th data-column-id="gateway" data-type="string">{{ lang._('Gateway') }}</th>
                <th data-column-id="routes" data-formatter="routes" data-type="string">{{ lang._('Direct Routes') }}</th>
                <!-- <th data-column-id="origin"  data-type="string">{{ lang._('Origin') }}</th>
                <th data-column-id="etheraddr"  data-type="string">{{ lang._('Mac') }}</th>
                <th data-column-id="ipaddress" data-type="string" >{{ lang._('IP address') }}</th>
                <th data-column-id="descr" data-type="string">{{ lang._('Description') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th> -->
            </tr>
        </thead>
        <tbody>
        </tbody>
        <tfoot>
        </tfoot>
    </table>
</div>