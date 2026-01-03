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
      let s_filter_val = '{{default_log_severity}}';
      s_header = '<a href="#"><i class="fa fa-toggle-off text-danger" id="exact_severity" title="{{ lang._('Toggle between range (max level) and exact severity filter') }}"></i> {{ lang._('Multiselect') }}</a>';
      m_header = '<a href="#"><i class="fa fa-toggle-on text-success" id="exact_severity" title="{{ lang._('Toggle between range (max level) and exact severity filter') }}"></i> {{ lang._('Multiselect') }}</a>';
      var page = 0;
      // map available severity values to array
      severities = $('#severity_filter option').map(function(){
          return (this.value ? this.value : null);
      }).get();

      if (window.localStorage) {
          if (localStorage.getItem('log_filter_exact_{{module}}_{{scope}}')) {
              s_filter_val = localStorage.getItem('log_severity_{{module}}_{{scope}}') ? localStorage.getItem('log_severity_{{module}}_{{scope}}').split(',') : [];
              filter_exact = true;
          } else {
              s_filter_val = localStorage.getItem('log_severity_{{module}}_{{scope}}') ? localStorage.getItem('log_severity_{{module}}_{{scope}}').split(',') : s_filter_val;
          }
          $("#validFrom_filter").val(localStorage.getItem('log_validFrom_filter_{{module}}_{{scope}}') ? localStorage.getItem('log_validFrom_filter_{{module}}_{{scope}}') : 'day');
      }
      switch_mode(s_filter_val);

      let grid_log = $("#grid-log").UIBootgrid({
          options:{
              initialSearchPhrase: getUrlHash('search'),
              sorting:false,
              rowSelect: false,
              selection: false,
              virtualDOM: true,
              labels: {
                  infos: "{{ lang._('Showing %s to %s') | format('{{ctx.start}}','{{ctx.end}}') }}"
              },
              formatters:{
                  page: function (column, row) {
                      let severity = $('#severity_filter').val();
                      let debug = (Array.isArray(severity) && severity.includes('Debug')) || severity === "Debug";
                      if ($("input.search-field").val() !== "" || !debug) {
                          let btn = $(`
                            <button type="button" class="btn btn-xs btn-default action-page bootgrid-tooltip" data-row-id="${row.rnum}"
                                    title="{{ lang._('Go to page') }}">
                                <span class="fa fa-arrow-right fa-fw"></span>
                            </button>
                          `).on('click', function(event) {
                                if ($("#exact_severity").hasClass("fa-toggle-on")) {
                                    $("#severity_filter").selectpicker('deselectAll');
                                } else {
                                    $("#severity_filter").val("Debug");
                                }
                                $('#grid-log').bootgrid('setPageByRowId', parseInt($(this).data('row-id')));
                          });

                          return btn[0];
                      } else {
                          return "";
                      }
                  },
              },
              requestHandler: function(request){
                  if ( $('#severity_filter').val().length > 0) {
                      let selectedSeverity = $('#severity_filter').val();
                      // get selected severities or severities below or equal to selected
                      request['severity'] = filter_exact ? selectedSeverity : severities.slice(0,severities.indexOf(selectedSeverity) + 1);
                  }
                  let time_offsets = {
                        'day': 60*60*24,
                        'week': 7*60*60*24,
                        'month': 31*60*60*24,
                    }
                  if ($("#validFrom_filter").val().length > 0 && time_offsets[$("#validFrom_filter").val()]) {
                    let now = Date.now()  / 1000;
                    request['validFrom'] = now - time_offsets[$("#validFrom_filter").val()];
                  }
                  return request;
              },
          },
          search:'/api/diagnostics/log/{{module}}/{{scope}}'
      });
      $(".filter_act").change(function(event){
          event.stopPropagation();
          if (window.localStorage) {
              localStorage.setItem('log_severity_{{module}}_{{scope}}', $("#severity_filter").val());
              localStorage.setItem('log_validFrom_filter_{{module}}_{{scope}}', $("#validFrom_filter").val());
          }
          $('#grid-log').bootgrid('reload');
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
              let r_severity = filter_exact ? $('#severity_filter').val() : severities.slice(0,severities.indexOf($('#severity_filter').val()) + 1);
              params.push("severity=" + encodeURIComponent(r_severity.join(",")));
          }
          if (params.length > 0) {
              download_link = download_link + "?" + params.join("&");
          }
          $('<a></a>').attr('href',download_link).get(0).click();
      });

      updateServiceControlUI('{{service}}');

      // Move filters directly into the actionBar instead of nested groups for better flex behavior
      $("#filter_container").detach().insertAfter('#grid-log-header .search');
      $("#export-wrapper").detach().appendTo('#grid-log-header .actionBar');

      function switch_mode(value) {
          let select = $("#severity_filter");
          let header_val = filter_exact ? m_header : s_header;

          // switch select mode and reinit selectpicker
          select.prop("multiple", filter_exact);

          // destroy, reinit & reopen select after load if called from mode switch
          if (event && (event.currentTarget.id == 'exact_severity')) {
              select.selectpicker('destroy').on('loaded.bs.select', function () {
                  select.selectpicker('toggle');
              });
          }
          select.selectpicker({ header: header_val });

          // attach event handler each time header created
          $("#exact_severity").on("click", function(event) {
              event.stopPropagation();
              filter_exact = !filter_exact;
              let select = $("#severity_filter");

              // set new select value to current value or highest value of multiselect
              let new_val = Array.isArray(select.val()) ? select.val().pop() : select.val();

              if (window.localStorage) {
                  if (filter_exact) {
                      localStorage.setItem('log_filter_exact_{{module}}_{{scope}}', 1);
                  } else {
                      localStorage.removeItem('log_filter_exact_{{module}}_{{scope}}');
                  }
                  // store user choice
                  localStorage.setItem('log_severity_{{module}}_{{scope}}', new_val);
              }
              switch_mode(new_val);
          });

          select.val(value);
          // fetch data
          select.change();
      }

    });

</script>

<div class="content-box">
    <div class="content-box-main">
        <div class="table-responsive">
            <div  class="col-sm-12">
                <div class="hidden">
                    <!-- filter per type container -->
                    <div id="filter_container" class="btn-group">
                        <select id="severity_filter"  data-title="{{ lang._('Severity') }}" class="filter_act" data-width="200px">
                            <option value="Emergency">{{ lang._('Emergency') }}</option>
                            <option value="Alert">{{ lang._('Alert') }}</option>
                            <option value="Critical">{{ lang._('Critical') }}</option>
                            <option value="Error">{{ lang._('Error') }}</option>
                            <option value="Warning" selected>{{ lang._('Warning') }}</option>
                            <option value="Notice">{{ lang._('Notice') }}</option>
                            <option value="Informational">{{ lang._('Informational') }}</option>
                            <option value="Debug">{{ lang._('Debug') }}</option>
                        </select>
                        <select id="validFrom_filter" data-title="{{ lang._('History') }}" class="filter_act selectpicker" data-width="200px">
                            <option selected="selected" value="day">{{ lang._('Last day') }}</option>
                            <option value="week">{{ lang._('Last week') }}</option>
                            <option value="month">{{ lang._('Last month') }}</option>
                            <option value="all">{{ lang._('No limit') }}</option>
                        </select>
                    </div>
                </div>
                <div id="export-wrapper" class="btn-group">
                    <button id="exportbtn" class="btn btn-default" data-toggle="tooltip" title="" type="button" data-original-title="{{ lang._('Download selection')}}">
                        <span class="fa fa-cloud-download"></span>
                    </button>
                </div>
                <table id="grid-log" class="table table-condensed table-hover table-striped table-responsive">
                    <thead>
                    <tr>
                        <th data-column-id="timestamp" data-width="11em" data-type="string">{{ lang._('Date') }}</th>
                        <th data-column-id="facility" data-type="string" data-visible="false">{{ lang._('Facility') }}</th>
                        <th data-column-id="severity" data-type="string" data-width="2em">{{ lang._('Severity') }}</th>
                        <th data-column-id="process_name" data-width="2em" data-type="string">{{ lang._('Process') }}</th>
                        <th data-column-id="pid" data-width="2em" data-type="numeric" data-visible="false">{{ lang._('PID') }}</th>
                        <th data-column-id="line" data-type="string">{{ lang._('Line') }}</th>
                        <th data-column-id="rnum" data-type="numeric" data-formatter="page" data-width="2em" data-identifier="true"></th>
                    </tr>
                    </thead>
                    <tbody>
                    </tbody>
                    <tfoot>
                      <td></td>
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
