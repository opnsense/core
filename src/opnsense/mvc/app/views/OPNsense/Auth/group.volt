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
        let grid_group = $("#{{formGridGroup['table_id']}}").UIBootgrid({
            search:'/api/auth/group/search/',
            get:'/api/auth/group/get/',
            add:'/api/auth/group/add/',
            set:'/api/auth/group/set/',
            del:'/api/auth/group/del/',
            commands: {
                copy: {
                    classname: undefined
                }
            },
            options: {
                formatters: {
                    member_count: function (column, row) {
                        if (row[column.id]) {
                            return row[column.id].split(',').length;
                        } else {
                            return 0;
                        }
                    }
                }
            }
        });

    });

</script>

<div class="tab-content content-box">
    {{ partial('layout_partials/base_bootgrid_table', formGridGroup)}}
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditGroup,'id':formGridGroup['edit_dialog_id'],'label':lang._('Edit Group')])}}
