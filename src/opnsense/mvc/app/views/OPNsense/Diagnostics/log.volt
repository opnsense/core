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
      var filter_exact = false;
      let s_filter_val = "Warning";
      // map available severity values to array
      severities = $('#severity_filter option').map(function(){
          return (this.value ? this.value : null);
      }).get();

      if (window.localStorage) {
          if (localStorage.getItem('log_filter_exact_{{module}}_{{scope}}')) {
              s_filter_val = localStorage.getItem('log_severity_{{module}}_{{scope}}') ? localStorage.getItem('log_severity_{{module}}_{{scope}}').split(',') : [];
              filter_exact = true;
              $("#exact_severity").toggleClass("fa-toggle-on fa-toggle-off").toggleClass("text-success text-danger");
              switch_mode(s_filter_val);
          } else {
              s_filter_val = localStorage.getItem('log_severity_{{module}}_{{scope}}') ? localStorage.getItem('log_severity_{{module}}_{{scope}}').split(',') : "Warning";
              $('#severity_filter').val(s_filter_val).change();
          }
      }

      let grid_log = $("#grid-log").UIBootgrid({
          options:{
              sorting:false,
              rowSelect: false,
              selection: false,
              rowCount:[20,50,100,200,500,1000,-1],
              formatters:{
                  page: function (column, row) {
                      if ($("input.search-field").val() !== "" || $('#severity_filter').val().length > 0) {
                          return '<button type="button" class="btn btn-xs btn-default action-page bootgrid-tooltip" data-row-id="' +
                                row.rnum + '" title="{{ lang._('Go to page') }}"><span class="fa fa-arrow-right fa-fw"></span></button>';
                      } else {
                          return "";
                      }
                  },
              },
              requestHandler: function(request){
                  if ( $('#severity_filter').val().length > 0) {
                      let selectedSeverity = $('#severity_filter').val();
                      // get selected severities or severeties below or equal to selected
                      request['severity'] = filter_exact ? selectedSeverity : severities.slice(0,severities.indexOf(selectedSeverity) + 1);
                  }
                  return request;
              },
          },
          search:'/api/diagnostics/log/{{module}}/{{scope}}'
      });
      $("#severity_filter").change(function(){
          if (window.localStorage) {
              localStorage.setItem('log_severity_{{module}}_{{scope}}', $("#severity_filter").val());
          }
          $('#grid-log').bootgrid('reload');
      });

      grid_log.on("loaded.rs.jquery.bootgrid", function(){
          $(".action-page").click(function(event){
              event.preventDefault();
              $("#grid-log").bootgrid("search",  "");
              let new_page = parseInt((parseInt($(this).data('row-id')) / $("#grid-log").bootgrid("getRowCount")))+1;
              $("input.search-field").val("");
              $("#severity_filter").selectpicker('deselectAll');
              // XXX: a bit ugly, but clearing the filter triggers a load event.
              setTimeout(function(){
                  $("ul.pagination > li:last > a").data('page', new_page).click();
              }, 100);
          });
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
          let params = [];

          if ($("input.search-field").val() !== "") {
              params.push("searchPhrase=" + encodeURIComponent($("input.search-field").val()));
          }
          if ( $('#severity_filter').val().length > 0) {
              params.push("severity=" + encodeURIComponent($('#severity_filter').val().join(",")));
          }
          if (params.length > 0) {
              download_link = download_link + "?" + params.join("&");
          }
          $('<a></a>').attr('href',download_link).get(0).click();
      });

      $("#exact_severity").on("click", function() {
          $(this).toggleClass("fa-toggle-on fa-toggle-off");
          $(this).toggleClass("text-success text-danger");
          if ($(this).hasClass("fa-toggle-on")) {
              filter_exact = true;
              if (window.localStorage) {
                  localStorage.setItem('log_filter_exact_{{module}}_{{scope}}', 1);
              }
          } else {
              filter_exact = false;
              if (window.localStorage) {
                  localStorage.removeItem('log_filter_exact_{{module}}_{{scope}}');
              }
          }
          let select = $("#severity_filter");

          // set new select value to current value or highest value of multiselect
          let new_val = Array.isArray(select.val()) ? select.val().pop() : select.val();

          // store user choice
          localStorage.setItem('log_severity_{{module}}_{{scope}}', new_val);

          switch_mode(new_val);
      });

      updateServiceControlUI('{{service}}');

      // move filter into action header
      $("#severity_filter_container").detach().prependTo('#grid-log-header > .row > .actionBar > .actions');
    });

    function switch_mode(value) {
        let select = $("#severity_filter");

        // switch select mode and destroy selectpicker
        select.prop("multiple", !select.prop("multiple"));
        select.selectpicker('destroy');

        // remove title option. bug in bs-select. fixed in v1.13.18 https://github.com/snapappointments/bootstrap-select/issues/2491
        select.find('option.bs-title-option').remove();

        select.selectpicker();
        select.val(value);
        // fetch data
        select.change();
    }
</script>

<div class="content-box">
    <div class="content-box-main">
        <div class="table-responsive">
            <table class="table table-striped table-condensed">
                <tbody>
                    <tr>
                        <td style="text-align:left">
                            <a href="#"><i class="fa fa-toggle-off text-danger" id="exact_severity"></i></a><small> {{ lang._('exact severity selection') }}</small>
                            <div class="hidden" data-for="help_for_exact_severity">
                                <small>{{ lang._('Filter by exact severity level(s) specified. When disabled, include messages with a lower level (more severe).') }}</small>
                            </div>
                        </td>
                        <td style="text-align:right">
                            <small>full help</small> <a href="#"><i class="fa fa-toggle-off text-danger" id="show_all_help"></i></a>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div  class="col-sm-12">
                <div class="hidden">
                    <!-- filter per type container -->
                    <div id="severity_filter_container" class="btn-group">
                        <select id="severity_filter"  data-title="{{ lang._('Severity') }}" class="selectpicker" data-width="200px">
                            <option value="Emergency">{{ lang._('Emergency') }}</option>
                            <option value="Alert">{{ lang._('Alert') }}</option>
                            <option value="Critical">{{ lang._('Critical') }}</option>
                            <option value="Error">{{ lang._('Error') }}</option>
                            <option value="Warning" selected>{{ lang._('Warning') }}</option>
                            <option value="Notice">{{ lang._('Notice') }}</option>
                            <option value="Informational">{{ lang._('Informational') }}</option>
                            <option value="Debug">{{ lang._('Debug') }}</option>
                        </select>
                    </div>
                </div>
                <table id="grid-log" class="table table-condensed table-hover table-striped table-responsive" data-store-selection="true">
                    <thead>
                    <tr>
                        <th data-column-id="timestamp" data-width="11em" data-type="string">{{ lang._('Date') }}</th>
                        <th data-column-id="facility" data-type="string" data-visible="false">{{ lang._('Facility') }}</th>
                        <th data-column-id="severity" data-type="string" data-width="2em">{{ lang._('Severity') }}</th>
                        <th data-column-id="process_name" data-width="2em" data-type="string">{{ lang._('Process') }}</th>
                        <th data-column-id="pid" data-width="2em" data-type="numeric" data-visible="false">{{ lang._('PID') }}</th>
                        <th data-column-id="line" data-type="string">{{ lang._('Line') }}</th>
                        <th data-column-id="rnum" data-type="numeric" data-formatter="page"  data-width="2em"></th>
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
