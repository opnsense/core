{#

OPNsense® is Copyright © 2014 – 2016 by Deciso B.V.
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

1.  Redistributions of source code must retain the above copyright notice,
this list of conditions and the following disclaimer.

2.  Redistributions in binary form must reproduce the above copyright notice,
this list of conditions and the following disclaimer in the documentation
and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.

#}

<script type="text/javascript">
//   /api/diagnostics/interface/getInterfaceNames
    $( document ).ready(function() {
        var interface_descriptions = {};
        $("#update").click(function(){
            var record_spec = [];
            // read heading, contains field specs
            $("#grid-log > thead > tr > th ").each(function(){
                record_spec.push({'column-id': $(this).data('column-id'),
                                  'type': $(this).data('type'),
                                  'class': $(this).attr('class')
                                 });
            });
            // read last digest (record hash) from top data row
            var last_digest = $("#grid-log > tbody > tr:first > td:first").text();
            if (last_digest == undefined) {
                last_digest = "";
            }
            // fetch new log lines and add on top of grid-log
            ajaxGet(url='/api/diagnostics/firewall/log/' + last_digest+'/10', {}, callback=function(data, status) {
                if (data != undefined && data.length > 0) {
                    while ((record=data.pop()) != null) {
                        if (record['__digest__'] != last_digest) {
                            var log_tr = $("<tr>");
                            $.each(record_spec, function(idx, field){
                                var field_content = field['column-id'];
                                log_tr.append($('<td>').addClass(field['class']).text(record[field_content]));
                            });
                            $("#grid-log > tbody > tr:first").before(log_tr);
                        }
                    }
                }
            });
        });

        // fetch interface mappings on load
        ajaxGet(url='/api/diagnostics/interface/getInterfaceNames', {}, callback=function(data, status) {
            interface_descriptions = data;
        });

    });
</script>

<div class="content-box">
    <div class="content-box-main">
        <div class="table-responsive">
            <div  class="col-sm-12">
                <div class="table-responsive">
                  <button id="update" type="button" class="btn btn-default">
                      <span>{{ lang._('Refresh') }}</span>
                      <span class="fa fa-refresh"></span>
                  </button>
                    <table id="grid-log" class="table table-condensed table-hover table-striped table-responsive">
                        <thead>
                          <tr>
                              <th class="hidden" data-column-id="__digest__" data-type="string">{{ lang._('Hash') }}</th>
                              <th data-column-id="__timestamp__" data-type="string">{{ lang._('Time') }}</th>
                              <th data-column-id="src" data-type="string">{{ lang._('Source') }}</th>
                              <th data-column-id="dst" data-type="string">{{ lang._('Destination') }}</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr/>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
