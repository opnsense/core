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
 #
 # -----------------------------------------------------------------------------
 #}

{#
 # This is a partial for an 'onoff' field.
 #
 # This field is very similar to a radio button with the 'button-group' built-in style,
 # however, only includes two pre-defined buttons: On, Off
 #
 # The same button could be defined manually as a radio button, but this is just a shortcut.
 #
 # This field supports field control.
 #
 # Example usage in an form XML:
 # <field>
 #   <id>enabled</id>
 #   <label>Enable DNSCrypt Proxy</label>
 #   <help>This will enable the dnscrypt-proxy service.</help>
 #   <type>onoff</type>
 # </field>
 #
 # Example usage in form XML (with controls):
 # <field>
 #     <id>blocked_names_enabled</id>
 #     <type>onoff</type>
 #     <label>Enable Blocked Names</label>
 #     <help>Control the blacklist functionality.</help>
 #     <control>
 #         <action on_set="1" type="field" do_state="enabled">settings.blocked_names_type</action>
 #         <action on_set="1" type="field" do_state="enabled">settings.blocked_names_logging</action>
 #         <action on_set="0" type="field" do_state="disabled">settings.blocked_names_type</action>
 #         <action on_set="0" type="field" do_state="disabled">settings.blocked_names_file_external</action>
 #         <action on_set="0" type="field" do_state="disabled">settings.blocked_names_file_manual</action>
 #         <action on_set="0" type="field" do_state="disabled">settings.blocked_names_logging</action>
 #         <action on_set="0" type="field" do_state="disabled">blocked_names_internal_entries</action>
 #     </control>
 # </field>
 #
 # Compatible model field types:
 # BooleanField
 # TextField
 # IntegerField
 #
 # Intended to be used with a BooleanField in the model:
 # <blocked_names_enabled type="BooleanField">
 #     <required>Y</required>
 #     <default>0</default>
 # </blocked_names_enabled>
 #
 # Example partial call in a Volt tempalte:
 # {{ partial("OPNsense/Dnscryptproxy/layout_partials/fields/onoff",[
 #     'this_field':this_field,
 #     'field_id':field_id
 # ]) }}
 #
 # Expects to be passed
 # field_id         The id of the field, includes model name. Example: settings.enabled
 # this_field       The field itself.
 #
 #}

{# We define a hidden input to hold the
   value of the setting from the config #}
<input type="text"
       class="form-control hidden"
       id="{{ this_field_id }}"
       readonly="readonly">
<div class="btn-group btn-group-xs" data-toggle="buttons">
{%  for this_key, this_value in [ 'On': 1 , 'Off': 0 ] %}
  <label class="btn btn-default">
    <input type="radio"
           name="rdo_{{ this_field_id }}"
           value="{{ this_value }}"/>
{# Use non-breakable spaces to give the label some breathing room. #}
             &nbsp;{{ lang._('%s')|format (this_key) }}&nbsp;
  </label>
{%  endfor %}
</div>
<script>
{#/* =============================================================================
  # field control
  # ==============================================================================
  # This will attach a change event which will call the toggle function based
  # on the configuration specified in the XML for this field.
  # An example control structure for an on/off switch:
  # <control>
  #   <action on_set="1" type="field" do_state="enabled">settings.listen_addresses</action>
  #   <action on_set="0" type="field" do_state="disabled">settings.listen_addresses</action>
  # </control>
  # XXX Maybe add support for attr/sub-element definition style.
*/#}
{%  if this_field.control %}
{%      if this_field.control.action %}
{#/* Attach to the element associated with the field id,
     or the text field associated with the radio buttons */#}
    $("#" + $.escapeSelector("{{ this_field_id }}")).change(function(e){
{#/* This prevents the field from acting out if it is in a disabled state. */#}
        if ($(this).hasClass("disabled") == false) {
{#/* This pulls the on_set key values out of all of the field's attributes,
    and then creates an array of the unique values. */#}
{%              set on_set_values_xml = this_field.control.xpath('action/@on_set') %}
{#/* {%              set value_list = [] %} */#}
{#/* {%              set value_list_array = [] %} */#}
{%              for xml_node in on_set_values_xml %}
<?php $value_list_array[] = $xml_node->__toString() ?>
{%              endfor %}
<?php $value_list = array_unique($value_list_array); ?>
{#/*  Iterate through the values we found to start building our if blocks. */#}
{%                  for on_set in value_list %}
{#/*  Start if statments looking at different value based on field type */#}
            if ($(this).val() == "{{ on_set }}") {
{#/*  Iterate through the fields only if the "on_set" value
      matches that of the current for loop's "on_set" variable. */#}
{%                  for target_field in this_field.control.action if target_field['on_set'] == on_set %}
{#/*  We use the field's value so we don't have to have a line
      of code for each version, check first that they're OK. */#}
{%                      if target_field['do_state'] in [ "disabled", "enabled", "hidden", "visible" ] %}
                toggle("{{ target_field }}", "{{ target_field['type'] }}", "{{ target_field['do_state'] }}");
{%                      endif %}
{%                  endfor %}
            }
{%              endfor %}
        }
    });
{%      endif %}
{%  endif %}
{#/*
 # =============================================================================
 # radio: click activity
 # =============================================================================
 # Click event for radio type objects
 # Store which radio button was selected, since this value will be dynamic depending on which radio button is clicked.
 # This looks a bit strange because all of the radio input tags have the same name attribute,
 # and differ in the content of the surrounding <label> tag, and value attribute. So when this is clicked,
 # it sets the value of the field to be the same as the value of the radio button that was selected.
 # Then we trigger a change event to set any enable/disabled fields.
*/#}
    $('input[name=rdo_' + $.escapeSelector("{{ this_field_id }}") + ']').parent('label').click(function () {
        $('#' + $.escapeSelector("{{ this_field_id }}")).val($(this).children('input').val());
        $('#' + $.escapeSelector("{{ this_field_id }}")).trigger("change");;
    });
{#/* =============================================================================
 # radio: change activity
 # =============================================================================
 # Change function which updates the values of the approprite radio button.
 */#}
    $('#' + $.escapeSelector("{{ this_field_id }}")).change(function(e){
{#/*    # Set whichever radiobutton accordingly, may already be selected.
        # This covers the initial page load situation. */#}
        var field_value = $('#' + $.escapeSelector("{{ this_field_id }}")).val();
{#/*    This catches the first pass, if change event is initiated before the
        value of the target field is set by mapDataToFormUI() */#}
        if (field_value != "") {
            $('input[name=rdo_' + $.escapeSelector("{{ this_field_id }}") + '][value=' + field_value + ']').parent('label').addClass("active");
        }
    });
</script>
