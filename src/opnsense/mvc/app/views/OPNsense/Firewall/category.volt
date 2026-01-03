{#
 # Copyright (c) 2020 Deciso B.V.
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

    $( document ).ready(function() {
        /*************************************************************************************************************
         * link grid actions
         *************************************************************************************************************/
        $("#{{formGridCategory['table_id']}}").UIBootgrid(
                {   search:'/api/firewall/category/search_item',
                    get:'/api/firewall/category/get_item/',
                    set:'/api/firewall/category/set_item/',
                    add:'/api/firewall/category/add_item/',
                    del:'/api/firewall/category/del_item/',
                    options:{
                        formatters:{
                            color: function (column, row) {
                                if (row.color != "") {
                                    return "<i style='color:#"+row.color+";' class='fa fa-circle'></i>";
                                }
                            },
                        }
                    }

                }
        );

    });

</script>

<style>
    .color-picker {
        width: 32px !important;
        padding: 3px !important;
    }
</style>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#grid-categories">{{ lang._('Categories') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="categories" class="tab-pane fade in active">
        {{ partial('layout_partials/base_bootgrid_table', formGridCategory)}}
    </div>
</div>

{# include dialog #}
{{ partial("layout_partials/base_dialog",['fields':formDialogEdit,'id':formGridCategory['edit_dialog_id'],'label':lang._('Edit category')])}}
