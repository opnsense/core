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
 # This is a partial for an 'onoff' field, which is very similar to a radio button
 # with the 'button-group' built-in style, however, only includes two pre-defined
 # buttons: On, Off
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

{# We define a hidden input to hold the
   value of the setting from the config #}
{# XXX Size shouldn't matter for this hidden field. #}
        <input
            type="text"
            class="form-control hidden"
            size="{{ this_field.size|default("50") }}"
            id="{{ this_field_id }}"
            readonly="readonly"
        >
{# Figure out if we should use a builtin style or legacy. #}
{%      if this_field.builtin in [ 'legacy', 'button-group' ] %}
{%          set builtin = this_field.builtin %}
{%      else %}
{%          set builtin = 'legacy' %}
{%      endif %}
{%      if builtin == 'legacy' %}
        <div class="radio">
{%      elseif builtin == 'button-group' %}
        <div class="btn-group btn-group-xs" data-toggle="buttons">
{%      endif %}
{%      for this_button in this_field.buttons.button|default({}) %}
{%          if builtin == 'legacy' %}
            <label>
{%          elseif builtin == 'button-group' %}
            <label class="btn btn-default">
{%          endif %}
                <input type="radio"
                       name="rdo_{{ this_field_id }}"
                       value="{{ this_button['value'] }}"/>
{# Use non-breakable spaces to give the label some breathing room. #}
                &nbsp;{{ lang._('%s')|format (this_button) }}&nbsp;
            </label>
{%      endfor %}
        </div>

<script>
{#/*
 # =============================================================================
 # checkbox, radio, dropdown: toggle functionality
 # =============================================================================
 # A toggle function for checkboxes, radio buttons, and dropdown menus.
 # XXX This function is mostly the same for four types of fields, and maybe this could be separated into a function.
 # XXX Maybe it could be called toggle_control().
*/#}
{%  if this_field.control %}
{%      if this_field.control.action %}
{#/*  Attach to the element associated with the field id,
    or the text field associated with the radio buttons */#}
$("#" + $.escapeSelector("{{ this_field_id }}")).change(function(e){
{#/*  This prevents the field from acting out if it is in a disabled state. */#}
    if ($(this).hasClass("disabled") == false) {
{#/*  This pulls the on_set key values out of all of the field's attributes,
and then creates an array of the unique values. */#}
{%          set on_set_values_xml = this_field.control.xpath('action/@on_set') %}
{%          set value_list = [] %}
{%          set value_list_array = [] %}
{%          for xml_node in on_set_values_xml %}
<?php $value_list_array[] = $xml_node->__toString() ?>
{%          endfor %}
<?php $value_list = array_unique($value_list_array); ?>
{#/*  Iterate through the values we found to start building our if blocks. */#}
{%          for on_set in value_list %}
{#/*  Start if statments looking at different value based on field type */#}
        if ($(this).val() == "{{ on_set }}") {
{#/*  Iterate through the fields only if the "on_set" value matches that of the current for loop's "on_set" variable. */#}
{%              for target_field in this_field.control.action if target_field['on_set'] == on_set %}
{#/*  We use the field's value so we don't have to have a line of code for each version, check the data from the XML is acceptible. */#}
{%                  if target_field['do_state'] in [ "disabled", "enabled", "hidden", "visible" ] %}
            toggle("{{ target_field }}", "{{ target_field['type'] }}", "{{ target_field['do_state'] }}");
{%                  endif %}
{%              endfor %}
        }
{%          endfor %}
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
{#/*
 # =============================================================================
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
