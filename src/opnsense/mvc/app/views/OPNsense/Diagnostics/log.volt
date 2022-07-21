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
      }
      switch_mode(s_filter_val);

      let grid_log = $("#grid-log").UIBootgrid({
          options:{
              sorting:false,
              rowSelect: false,
              selection: false,
              rowCount:[20,50,100,200,500,1000,5000],
              labels: {
                  infos: "{{ lang._('Showing %s to %s') | format('{{ctx.start}}','{{ctx.end}}') }}"
              },
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
                      // get selected severities or severities below or equal to selected
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
          if (page > 0) {
              $("ul.pagination > li:last > a").data('page', page).click();
              page = 0;
          }

          $(".action-page").click(function(event){
              event.preventDefault();
              $("#grid-log").bootgrid("search",  "");
              page = parseInt((parseInt($(this).data('row-id')) / $("#grid-log").bootgrid("getRowCount")))+1;
              $("input.search-field").val("");
              if ($("#exact_severity").hasClass("fa-toggle-on")) {
                  $("#severity_filter").selectpicker('deselectAll');
              } else {
                  $("#severity_filter").val("Debug").change();
              }
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
              let r_severity = filter_exact ? $('#severity_filter').val() : severities.slice(0,severities.indexOf($('#severity_filter').val()) + 1);
              params.push("severity=" + encodeURIComponent(r_severity.join(",")));
          }
          if (params.length > 0) {
              download_link = download_link + "?" + params.join("&");
          }
          $('<a></a>').attr('href',download_link).get(0).click();
      });

      updateServiceControlUI('{{service}}');

      // move filter into action header
      $("#severity_filter_container").detach().prependTo('#grid-log-header > .row > .actionBar > .actions');


      function switch_mode(value) {
          let select = $("#severity_filter");

          // switch select mode and destroy selectpicker
          select.prop("multiple", filter_exact);
          select.selectpicker('destroy');

          // remove title option. bug in bs-select. fixed in v1.13.18 https://github.com/snapappointments/bootstrap-select/issues/2491
          select.find('option.bs-title-option').remove();

          let header_val = filter_exact ? m_header : s_header;
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
              // keep it open
              select.selectpicker('toggle');
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
                    <div id="severity_filter_container" class="btn-group">
                        <select id="severity_filter" data-title="{{ lang._('Severity') }}" class="selectpicker" data-width="200px">
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
                <table id="grid-log" class="table table-condensed table-hover table-striped table-responsive">
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
