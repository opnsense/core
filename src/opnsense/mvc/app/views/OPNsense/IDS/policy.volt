{#
 # Copyright (c) 2020 Deciso B.V.
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
 # THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
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
      /**
       * update service status
       */
      updateServiceControlUI('ids');
      $("#{{formGridPolicy['table_id']}}").UIBootgrid({
              search:'/api/ids/settings/search_policy',
              get:'/api/ids/settings/get_policy/',
              set:'/api/ids/settings/set_policy/',
              add:'/api/ids/settings/add_policy/',
              del:'/api/ids/settings/del_policy/',
              toggle:'/api/ids/settings/toggle_policy/'
          }
      );
      $("#{{formGridPolicyRule['table_id']}}").UIBootgrid({
              search:'/api/ids/settings/search_policy_rule',
              get:'/api/ids/settings/get_policy_rule/',
              set:'/api/ids/settings/set_policy_rule/',
              add:'/api/ids/settings/add_policy_rule/',
              del:'/api/ids/settings/del_policy_rule/',
              toggle:'/api/ids/settings/toggle_policy_rule/'
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
          let select_options = {};
          for (i=0; i < all_options.length; i++) {
              let this_section = all_options[i].split('.')[0];
              let this_item = $(this).data('data')[all_options[i]];
              if (this_section != prev_section) {
                  target_select = $("<select id='policy_content_" +
                      this_section +
                      "' data-live-search='true' data-size='5' multiple='multiple' class='policy_select' data-container='body'/>"
                  );
                  policy_content_container.append($("<label class='policy_label' for='policy_content_"+this_section+"'/>").text(this_section));
                  policy_content_container.append(target_select);
                  select_options[this_section] = [];
                  prev_section = this_section;
              }
              let option = null;
              if (this_item.selected) {
                  option = "<option selected='selected' value='"+all_options[i]+"'>" + this_item.value + "</option>";
              } else {
                  option = "<option value='"+all_options[i]+"'>" + this_item.value + "</option>";
              }

              select_options[this_section].push(option);
          }
          Object.keys(select_options).forEach(function(target){
              $("#policy_content_" + target).append(select_options[target]);
          });

          $('.policy_select').selectpicker('refresh');
          // reset data container to ensure string type result when no metadata is found
          $("#policy\\.content").data('data', '');
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
          return false;
      });

      $("#reconfigureAct").SimpleActionButton();
      // update history on tab state and implement navigation
      if (window.location.hash != "") {
          $('a[href="' + window.location.hash + '"]').click();
      } else {
          $('a[href="#policies"]').click();
      }

      $('.nav-tabs a').on('shown.bs.tab', function (e) {
          history.pushState(null, null, e.target.hash);
      });
    });
</script>

<style>
    .policy_label {
        margin-bottom: 1px;
        font-weight: bold;
    }
</style>


<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li><a data-toggle="tab" href="#policies" id="policies_tab">{{ lang._('Policies') }}</a></li>
    <li><a data-toggle="tab" href="#rules" id="rules_tab">{{ lang._('Rule adjustments') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="policies" class="tab-pane fade in">
        {{ partial('layout_partials/base_bootgrid_table', formGridPolicy)}}
    </div>
    <div id="rules" class="tab-pane fade in">
        {{ partial('layout_partials/base_bootgrid_table', formGridPolicyRule)}}
    </div>
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/ids/service/reconfigure'}) }}

{{ partial("layout_partials/base_dialog",['fields':formDialogPolicy,'id':formGridPolicy['edit_dialog_id'],'label':lang._('Rule details'),'hasSaveBtn':'true'])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogPolicyRule,'id':formGridPolicyRule['edit_dialog_id'],'label':lang._('Rule details'),'hasSaveBtn':'true'])}}
