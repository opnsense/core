{#
 # Copyright (c) 2019 Deciso B.V.
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1.  Redistributions of source code must retain the above copyright notice,
 #     this list of conditions and the following disclaimer.
 #
 # 2.  Redistributions in binary form must reproduce the above copyright notice,
 #     this list of conditions and the following disclaimer in the documentation
 #     and/or other materials provided with the distribution.
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
      $("#grid-log").UIBootgrid({
          options:{
              sorting:false,
              rowSelect: false,
              selection: false,
              rowCount:[20,50,100,200,500,1000,-1],
          },
          search:'/api/diagnostics/log/{{module}}/{{scope}}'
      });

      $("#flushlog").on('click', function(event){
        event.preventDefault();
        BootstrapDialog.show({
          type: BootstrapDialog.TYPE_DANGER,
          title: "{{ lang._('Log') }}",
          message: "{{ lang._('Do you really want to flush this log?') }}",
          buttons: [{
            label: "{{ lang._('No') }}",
            action: function(dialogRef) {
              dialogRef.close();
            }}, {
              label: "{{ lang._('Yes') }}",
              action: function(dialogRef) {
                  ajaxCall("/api/diagnostics/log/{{module}}/{{scope}}/clear", {}, function(){
                      dialogRef.close();
                      $('#grid-log').bootgrid('reload');
                  });
              }
            }]
        });
      });
      // download (filtered) items
      $("#exportbtn").click(function(event){
          let download_link = "/api/diagnostics/log/{{module}}/{{scope}}/export";
          if ($("input.search-field").val() !== "") {
              download_link = download_link + "?searchPhrase=" + encodeURIComponent($("input.search-field").val());
          }
          $('<a></a>').attr('href',download_link).get(0).click();
      });
      updateServiceControlUI('{{service}}');
    });
</script>

<div class="content-box">
    <div class="content-box-main">
        <div class="table-responsive">
            <div  class="col-sm-12">
                <table id="grid-log" class="table table-condensed table-hover table-striped table-responsive" data-store-selection="true">
                    <thead>
                    <tr>
                        <th data-column-id="pos" data-type="numeric" data-identifier="true"  data-visible="false">#</th>
                        <th data-column-id="timestamp" data-width="11em" data-type="string">{{ lang._('Date') }}</th>
                        <th data-column-id="process_name" data-width="2em" data-type="string">{{ lang._('Process') }}</th>
                        <th data-column-id="line" data-type="string">{{ lang._('Line') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    </tbody>
                    <tfoot>
                      <td></td>
                      <td>
                        <button id="exportbtn"
                            data-toggle="tooltip" title="" type="button"
                            class="btn btn-xs btn-default pull-right"
                            data-original-title="{{ lang._('download selection')}}">
                            <span class="fa fa-cloud-download"></span>
                        </button>
                      </td>
                    </tfoot>
                </table>
                <table class="table">
                    <tbody>
                        <tr>
                            <td>
                              <button class="btn btn-primary pull-right" id="flushlog">
                                  {{ lang._('Clear log') }}
                              </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
