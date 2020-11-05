{#

OPNsense® is Copyright © 2020 by Deciso B.V.
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

<script>
    $( document ).ready(function() {
      $("#grid-policy").UIBootgrid({
              search:'/api/ids/settings/searchPolicy',
              get:'/api/ids/settings/getPolicy/',
              set:'/api/ids/settings/setPolicy/',
              add:'/api/ids/settings/addPolicy/',
              del:'/api/ids/settings/delPolicy/',
              toggle:'/api/ids/settings/togglePolicy/'
          }
      );
      // policy content handling
      let policy_content_container = $("<div id='policy_content_container'/>");
      $("#policy\\.content").after(policy_content_container);
      $("#policy\\.content").change(function () {
          policy_content_container.empty();
          let all_options = Object.keys($(this).data('data'));
          all_options.sort();
          let prev_section = null;
          let target_select = null;
          for (i=0; i < all_options.length; i++) {
              let this_section = all_options[i].split('.')[0];
              let this_item = $(this).data('data')[all_options[i]];
              if (this_section != prev_section) {
                  target_select = $("<select id='policy_content_"+
                      this_section+
                      "' data-live-search='true' data-size='5' multiple='multiple' class='policy_select'/>"
                  );
                  policy_content_container.append($("<label for='policy_content_"+this_section+"'/>").text(this_section));
                  policy_content_container.append(target_select);
                  prev_section = this_section;
              }
              let option = $("<option/>").val(all_options[i]).text(this_item.value);
              if (this_item.selected) {
                  option.prop("selected", true);
              }
              target_select.append(option);
          }
          $('.policy_select').selectpicker('refresh');
          $('.policy_select').change(function(){
              let selections = [];
              $(".policy_select").each(function(){
                  if ($(this).val().length > 0) {
                      selections = selections.concat($(this).val());
                  }
              });
              $("#policy\\.content").data('data', selections.join(','));
          });
          $('.policy_select').change();
      });
    });
</script>

<div class="tab-content content-box">
    <div class="col-md-12">
      <table id="grid-policy" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogPolicy">
          <thead>
          <tr>
              <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
              <th data-column-id="prio" data-type="string">{{ lang._('Priority') }}</th>
              <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
              <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
              <th data-column-id="uuid" data-type="string" data-identifier="true"  data-visible="false">{{ lang._('ID') }}</th>
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


{{ partial("layout_partials/base_dialog",['fields':formDialogPolicy,'id':'DialogPolicy','label':lang._('Rule details'),'hasSaveBtn':'true'])}}
