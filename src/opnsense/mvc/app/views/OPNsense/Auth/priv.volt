{#
 # Copyright (c) 2024 Deciso B.V.
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
    'use strict';

    $( document ).ready(function () {
        let grid_group = $("#grid-group").UIBootgrid({
            search:'/api/auth/priv/search/',
            get:'/api/auth/priv/get_item/',
            set:'/api/auth/priv/set_item/',
            datakey: 'id',
            commands: {
                copy: {
                    classname: undefined
                }
            },
            options: {
                formatters: {
                    lines: function (column, row) {
                        if (row[column.id]) {
                            return row[column.id].replaceAll("\n", "<br/>");
                        } else {
                            return '';
                        }
                    },
                    count: function (column, row) {
                        if (row[column.id]) {
                            return row[column.id].length;
                        } else {
                            return '';
                        }
                    }
                }
            }
        });

    });

</script>

<div class="tab-content content-box">
    <div id="group" class="tab-pane fade in active">
        <table id="grid-group" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogPriv">
            <thead>
                <tr>
                    <th data-column-id="id" data-type="string" data-identifier="true">{{ lang._('ID') }}</th>
                    <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                    <th data-column-id="match" data-type="string" data-formatter="lines">{{ lang._('Match') }}</th>
                    <th data-column-id="users" data-type="string" data-formatter="count" data-sortable="false">{{ lang._('Users') }}</th>
                    <th data-column-id="groups" data-type="string" data-formatter="count" data-sortable="false">{{ lang._('Groups') }}</th>
                    <th data-column-id="commands" data-width="10em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditPriv,'id':'DialogPriv','label':lang._('Edit Privilege')])}}
