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
 # This is a partial for a 'startstoptime' field
 # XXX Needs description
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

{# The structure and elements mostly came from the original
   firewall_schedule_edit.php #}
{# We define a hidden input to hold the
   value of the setting from the config #}
{%      if (this_field.start_hour_id is defined and
            this_field.start_min_id is defined and
            this_field.stop_hour_id is defined and
            this_field.stop_min_id is defined) %}
{# Make the background inherit from the row. #}
            <table style="background-color: inherit;">
                <tr>
                    <td>{{ lang._('%s')|format('Start Time') }}</td>
                    <td>{{ lang._('%s')|format('Stop Time') }}</td>
                </tr>
                <tr>
{%          for time, ids in {
                    "start":[
                        this_field.start_hour_id,
                        this_field.start_min_id
                    ],
                    "stop":[
                        this_field.stop_hour_id,
                        this_field.stop_min_id
                    ]
                } %}
                    <td>
                        <div>
{# Original div used input-group class, but this causes z-index issues with the dropdown menu
   appearing behind boxes below it. So it's been removed. #}
{# These <select> elements will trigger dropdown boxes getting added. #}
                            <select
                                class="selectpicker form-control"
                                data-width="55"
                                data-size="10"
                                data-live-search="true"
                                id="{{ ids[0] }}"
                            ></select>
{# The setFormData() assumes all <selects> are backed by an array datatype like an OptionField type in the model.
   When retreiving the data through the search API, it expects to receive an array. That array should
   be the OptionValues described in the model. It will then sift through the array, and locate any
   with the selected=>1 and mark them as such. When this field is erroneously backed by a
   non-array type field, it results in one option being added to the list:
   # <option value="resolve" selected="selected"></option>
   This is a result of a "bug" in jQuery in the .each() function. Attempting to iterate through
   an empty string will result in only the word 'resolve' being returned.
   The following javascript code demonstrates this behavior:
   #  var r = 0;
   #  var str = '';
   #  for (r in str) {
   #     console.log(r);
   #  } #}
                            <select
                                class="selectpicker form-control"
                                data-width="55"
                                data-size="10"
                                data-live-search="true"
                                id="{{ ids[1] }}"
                            ></select>
                        </div>
                    </td>
{%          endfor %}
                </tr>
            </table>
{%      endif %}
