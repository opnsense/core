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
        $("#grid-categories").UIBootgrid(
                {   'search':'/api/firewall/category/searchItem',
                    'get':'/api/firewall/category/getItem/',
                    'set':'/api/firewall/category/setItem/',
                    'add':'/api/firewall/category/addItem/',
                    'del':'/api/firewall/category/delItem/'
                }
        );
    });

</script>


<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#grid-categories">{{ lang._('categories') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="categories" class="tab-pane fade in active">
        <table id="grid-categories" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogEdit">
            <thead>
            <tr>
                <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                <th data-column-id="auto" data-width="6em" data-type="string" data-formatter="boolean">{{ lang._('Auto') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
</div>

{# include dialog #}
{{ partial("layout_partials/base_dialog",['fields':formDialogEdit,'id':'DialogEdit','label':lang._('Edit category')])}}
