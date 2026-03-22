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

            $("#grid-sessions").UIBootgrid({
                search:'/api/wireguard/service/show',
                options:{
                    multiSelect: false,
                    rowSelect: false,
                    selection: false,
                    formatters:{
                        bytes: function(column, row) {
                            if (row[column.id] && row[column.id] > 0) {
                                return byteFormat(row[column.id], 2);
                            }
                            return row[column.id];
                        },
                        epoch: function(column, row) {
                            if (row[column.id] !== null) {
                                return row[column.id]
                            } else {
                                return '';
                            }
                        },
                        seconds: function(column, row) {
                            if (row[column.id] !== null) {
                                return row[column.id] + "s";
                            } else {
                                return '';
                            }
                        },
                        status: function(column, row) {
                            if (row.type === 'peer' && row['peer-status'] === 'stale') {
                                return '<span class="fa fa-question-circle fa-fw" data-toggle="tooltip" title="{{ lang._('Stale') }}"></span>';
                            }

                            if (
                                (row.type === 'interface' && row.status === 'up') ||
                                (row.type === 'peer' && row['peer-status'] === 'online')
                            ) {
                                return '<span class="fa fa-check-circle fa-fw text-success" data-toggle="tooltip" title="{{ lang._('Online') }}"></span>';
                            }

                            return '<span class="fa fa-times-circle fa-fw text-danger" data-toggle="tooltip" title="{{ lang._('Offline') }}"></span>';
                        },



                    },
                    requestHandler: function(request){
                        if ( $('#type_filter').val().length > 0) {
                            request['type'] = $('#type_filter').val();
                        }
                        return request;
                    },
                }
            });

            $("#type_filter").change(function(){
                $('#grid-sessions').bootgrid('reload');
            });

            $("#grid-sessions").on('loaded.rs.jquery.bootgrid', function() {
                $('[data-toggle="tooltip"]').tooltip();
            });

            $("#type_filter_container").detach().insertAfter('#grid-sessions-header .search');
       });

   </script>

   <div class="tab-content content-box">
       <div class="hidden">
           <!-- filter per type container -->
           <div id="type_filter_container" class="btn-group">
               <select id="type_filter"  data-title="{{ lang._('Type') }}" class="selectpicker" multiple="multiple" data-width="200px">
                    <option value="interface">{{ lang._('Instance') }}</option>
                    <option value="peer">{{ lang._('Peer') }}</option>
               </select>
           </div>
       </div>
       <table id="grid-sessions" class="table table-condensed table-hover table-striped table-responsive">
           <thead>
             <tr>
                 <th data-column-id="status" data-formatter="status" data-type="string" data-width="6em" >{{ lang._('Status') }}</th>
                 <th data-column-id="if" data-type="string" data-width="6em">{{ lang._('Device') }}</th>
                 <th data-column-id="type" data-type="string" data-width="6em">{{ lang._('Type') }}</th>
                 <th data-column-id="public-key" data-type="string" data-width="26em" data-identifier="true" data-visible="false">{{ lang._('Public key') }}</th>
                 <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                 <th data-column-id="endpoint" data-type="string">{{ lang._('Port / Endpoint') }}</th>
                 <th data-column-id="latest-handshake-epoch" data-formatter="epoch" data-type="numeric" data-visible="false">{{ lang._('Handshake') }}</th>
                 <th data-column-id="latest-handshake-age" data-formatter="seconds" data-type="numeric">{{ lang._('Handshake Age') }}</th>
                 <th data-column-id="transfer-tx" data-formatter="bytes" data-type="numeric">{{ lang._('Sent') }}</th>
                 <th data-column-id="transfer-rx" data-formatter="bytes" data-type="numeric">{{ lang._('Received') }}</th>
             </tr>
           </thead>
           <tbody>
           </tbody>
       </table>
   </div>
