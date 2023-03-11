{##
 # OPNsense® is Copyright © 2022 by Deciso B.V.
 # Copyright (C) 2022 agh1467@protonmail.com
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
 # THIS SOFTWARE IS PROVIDED "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
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

{#
 # XXX Need description updates
 #
 # Example Usage in an XML:
 #  <field>
 #      <id>status</id>
 #      <label>dnscrypt-proxy status</label>
 #      <type>status</type>
 #      <style>label-opnsense</style>
 #      <labels>
 #          <success>clean</success>
 #          <danger>dirty</danger>
 #      </labels>
 #  </field>
 #
 # Example Model definition:
 #  <status type=".\PluginStatusField">
 #      <configdcmd>dnscryptproxy state</configdcmd>
 #  </status>
 #
 # Example partial call in a Volt tempalte:
 # {{ partial("OPNsense/Dnscryptproxy/layout_partials/fields/status",[
 #     this_field':this_field,
 #     'field_id':field_id
 # ]) }}
 #
 # Expects to be passed
 # field_id         The id of the field, includes model name. Example: settings.enabled
 # this_field       The field itself.
 # this_field.style A style to use by default.
 #
 # Available CSS styles to use:
 # label-primary
 # label-success
 # label-info
 # label-warning
 # label-danger
 # label-opnsense
 # label-opnsense-sm
 # label-opnsense-xs
 #}

{#/* XXX This won't work for multiple models on the same page. This may need to support multiple models. */#}
{#/* Look into the attachments, and see how the id's will need to change to support multiple models.
  we can then loop through and include for each model in the XML in build_xml().
  We could do a couple of things:
  1. Multiple buttons, one for each model.
  2. Single button, for all models, detects when at least one is dirty, and saves all model data (regardless of state).

  The second option is more challenging as it requires evaluating all of the models, first, and then deciding the outcome after.
  The current approach uses a direct callback. I think we'll have to do deferred objects, and build an array to figure it out. */#}
{# Add hidden apply changes box, shown when configuration changed, but unsaved. #}
<div class="col-xs-12" id="alt_{{ params['plugin_safe_name'] }}_apply_changes" style="display: none;">
    <div class="alert alert-info"
         id="alt_{{ params['plugin_safe_name'] }}_apply_changes_info"
         style="min-height: 65px;">
        <form method="post">
            <button type="button"
                    id="btn_{{ params['plugin_safe_name'] }}_apply_changes"
                    class="btn btn-primary pull-right">
                <b>Apply changes</b>
                <i id="btn_{{ params['plugin_safe_name'] }}_apply_changes_progress" {# Progress spinner to activate when applying changes. #}
                   class=""></i>
            </button>
        </form>
        <div style="margin-top: 8px;">
            {{ lang._('The %s configuration has been changed. You must apply the changes in order for them to take effect.')|format(params['plugin_label']) }}
        </div>
    </div>
</div>


<script>
{#/*
 # This is a toggle function to show or hide the Apply Changes section at the top of the page.
*/#}
$( document ).ready(function() {
{#/* Toggle the apply changes message for when the config is dirty/clean. */#}
    toggleApplyChanges();
});

function toggleApplyChanges(){
    const dfObj = new $.Deferred();
{#/*
    # Function to check if the config is dirty and display the Apply Changes box/button */#}
    var api_url = "/api/{{ params['plugin_api_name'] }}/settings/state";
    ajaxCall(url=api_url, sendData={}, callback=function(data,status){
{#/*            # when done, disable progress animation. */#}
        if ('state' in data) {
            var apply_field = "alt_{{ params['plugin_api_name'] }}_apply_changes";
            if (data['state'] == "dirty"){
{#                  # Do a slide down for a clean entrance, then scroll to show the box. #}
                $("#" + apply_field).slideDown(1000);
                var element = document.getElementById(apply_field);
                const y = element.getBoundingClientRect().top + window.scrollY;
                window.scroll({
                  top: (y - 140),
                  behavior: 'smooth'
                });
            } else if (data['state'] == "clean" ){
{#                  # Do a slide up for a clean exit. #}
                $("#" + apply_field).slideUp(1000);
            }
        }
        dfObj.resolve();
    });
    return dfObj;
}
{#/*
    # Apply event handler for the Apply Changes button.
    # The ID should be unique. */#}
$('button[id="btn_{{ params['plugin_api_name'] }}_apply_changes"]').click(function() {
    const dfObj = new $.Deferred();
    var this_btn = $(this);
    busyButton(this_btn);
    reconfigureService(this_btn, dfObj, clearButtonAndToggle, [this_btn]);
    return dfObj;
});
</script>
