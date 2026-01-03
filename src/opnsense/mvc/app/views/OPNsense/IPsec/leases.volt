{#
 # Copyright (c) 2022 Deciso B.V.
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

    $( document ).ready(function() {
        ajaxGet('/api/ipsec/leases/pools', {}, function(data, status){
            if (data.pools !== undefined) {
                $.each(data.pools, function(pool, row) {
                    $("#pool_filter").append($("<option>").val(pool).text(pool));
                });
                $("#pool_filter").selectpicker('refresh');
            }
            $("#grid-leases").UIBootgrid({
                search:'/api/ipsec/leases/search',
                options:{
                    requestHandler: function(request){
                        if ( $('#pool_filter').val().length > 0) {
                            request['pool'] = $('#pool_filter').val();
                        }
                        return request;
                    },
                    formatters:{
                        online: function (column, row) {
                            if (row.online) {
                                return '<i class="fa fa-exchange text-success"></i>';
                            } else {
                                return '<i class="fa fa-exchange text-danger"></i>';
                            }
                        }
                    }
                }
            });
            $("#pool_filter_container").detach().insertAfter('#grid-leases-header .search');
            $("#pool_filter").change(function(){
                $('#grid-leases').bootgrid('reload');
            });
        });

    });

</script>

<div class="tab-content content-box">
    <div class="hidden">
        <!-- filter per type container -->
        <div id="pool_filter_container" class="btn-group">
            <select id="pool_filter"  data-title="{{ lang._('Pool') }}" class="selectpicker" multiple="multiple" data-width="200px"></select>
        </div>
    </div>
    <table id="grid-leases" class="table table-condensed table-hover table-striped table-responsive">
        <thead>
          <tr>
              <th data-column-id="online" data-width="8em" data-formatter="online">{{ lang._('Online') }}</th>
              <th data-column-id="address" data-type="string" data-identifier="true">{{ lang._('Address') }}</th>
              <th data-column-id="pool" data-type="string" data-identifier="true">{{ lang._('Pool') }}</th>
              <th data-column-id="user" data-type="string">{{ lang._('User') }}</th>
          </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
</div>
