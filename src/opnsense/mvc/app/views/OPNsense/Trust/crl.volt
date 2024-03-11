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
        let grid_crl = $("#grid-crl").UIBootgrid({
            search:'/api/trust/crl/search/',
            get:'/api/trust/crl/get/',
            set:'/api/trust/crl/set/',
            del:'/api/trust/crl/del/',
            datakey: 'refid'
        });

        $("#DialogCrl").click(function(){
            $(this).html($("#crl\\.descr").val() !== '' ? $("#crl\\.descr").val() : '-');
            $(this).show();
        });
    });

</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#cert">{{ lang._('Index') }}</a></li>
    <li><a data-toggle="tab" href="#edit_crl" id="DialogCrl" style="display: none;"> </a></li>
</ul>
<div class="tab-content content-box">
    <div id="cert" class="tab-pane fade in active">
        <table id="grid-crl" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogCrl">
            <thead>
                <tr>
                    <th data-column-id="refid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="descr" data-type="string">{{ lang._('CA Name') }}</th>
                    <th data-column-id="crl_descr" data-type="string">{{ lang._('CRL Name') }}</th>
                    <th data-column-id="commands" data-width="11em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td>
                        <button data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-fw fa-plus"></span></button>
                        <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-fw fa-trash-o"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    <div id="edit_crl" class="tab-pane fade in">
        <form id="frm_DialogCrl">
            <input  type="text" class="form-control" size="50" id="crl.caref">
            <input type="text" class="form-control" size="50" id="crl.descr">
        </form>
    </div>
</div>
