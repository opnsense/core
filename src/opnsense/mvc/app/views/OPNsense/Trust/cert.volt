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
           let grid_cso = $("#grid-cert").UIBootgrid({
               search:'/api/trust/cert/search/',
               get:'/api/trust/cert/get/',
               add:'/api/trust/cert/add/',
               set:'/api/trust/cert/set/',
               del:'/api/trust/cert/del/',
               toggle:'/api/trust/cert/toggle/',
               options:{
                   selection: false,
                   formatters:{
                   }
               }
           });

       });

   </script>

   <ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
       <li class="active"><a data-toggle="tab" href="#cert">{{ lang._('Certificates') }}</a></li>
   </ul>
   <div class="tab-content content-box">
       <div id="cert" class="tab-pane fade in active">
            <table id="grid-cert" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogCert">
               <thead>
                   <tr>
                       <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                       <th data-column-id="descr" data-type="string">{{ lang._('Description') }}</th>
                       <th data-column-id="caref" data-type="string">{{ lang._('Issuer') }}</th>
                       <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                       <th data-column-id="valid_from" data-type="datetime">{{ lang._('Valid from') }}</th>
                       <th data-column-id="valid_to" data-type="datetime">{{ lang._('Valid to') }}</th>
                       <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
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

   {{ partial("layout_partials/base_dialog",['fields':formDialogEditCert,'id':'DialogCert','label':lang._('Edit Certificate')])}}
