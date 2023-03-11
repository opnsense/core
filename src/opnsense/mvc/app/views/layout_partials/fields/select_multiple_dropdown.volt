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
 # This partial is for use with both select_multiple and dropdown field types.
 #
 # These field types can be used in three primary ways:
 # 1. Dropdwon - A dropdown box, with a pre-determined selection for the user to select one item from.
 # 2. Select Multple (Select Picker) - Similar to the dropdown, but allows multiple selection
 # 3. Select Multple (Tokenize) - No menu is provided, but a box with "tokenized" items is displayed.
 #                                This allows the user to add or remove items from the list.
 #
 # This field support field control.
 #
 # Example dropdown (single selectpicker) usage in XML:
 # <field>
 #     <id>server_selection_method</id>
 #     <label>Server selection method</label>
 #     <type>dropdown</type>
 #     <help>Select to use manual server selection options, instead of ... </help>
 # </field>
 #
 # Example select_multiple (multiple selectpicker) usage in XML:
 # <field>
 #     <id>server_names</id>
 #     <label>Server Names</label>
 #     <type>select_multiple</type>
 #     <style>selectpicker</style>
 #     <allownew>false</allownew>
 #     <help>Explicitely define which servers to use. With servers ... </help>
 # </field>
 #
 # Example select_multiple (tokenizer)
 # <field>
 #     <id>listen_addresses</id>
 #     <label>Listen Addresses</label>
 #     <hint>127.0.0.1:5353, [::1]:5353</hint>
 #     <help>Set the IP address and port combinations this service should ... </help>
 #     <style>tokenize</style>
 #     <allownew>true</allownew>
 #     <type>select_multiple</type>
 # </field>
 #
 # dropdown and select_multiple (selectpicker) are intended to be used with OptionField type in the model:
 # <server_selection_method type="OptionField">
 #     <required>Y</required>
 #     <default>0</default>
 #     <Multiple>N</Multiple>
 #     <OptionValues>
 #         <option value="0">Automatic</option>
 #         <option value="1">Manual</option>
 #     </OptionValues>
 #     <ValidationMessage>A server selection method must be selected.</ValidationMessage>
 # </server_selection_method>
 #
 # select_multiple (tokenize) is intended to be used with a list field type like CSVListField in the model:
 # <listen_addresses type="CSVListField">
 #     <required>Y</required>
 #     <default>127.0.0.1:5353,[::1]:5353</default>
 #     <Multiple>Y</Multiple>
 # </listen_addresses>
 #
 # Example partial call from another volt template:
 # {{      partial("OPNsense/Dnscryptproxy/layout_partials/fields/select_multiple",[
 #                 'this_field': this_node,
 #                 'this_field_id': this_field_id,
 #                 'lang': lang,
 #                 'params': params
 # ]) }}
 #
 # List of objects expected in the environment:
 #  this_field     : (SimpleXMLObject) a field XML node
 #  this_field_id  : (String) the id of the field
 #
 # This field is structured very similarly to the select_multiple field, so a combined template is called instead.
#}

{% set this_field_style = get_xml_prop(this_field, 'style')|default('selectpicker') %}
{% set this_field_type = get_xml_prop(this_field, 'type', true) %}
<select
    id="{{ this_field_id }}"
    class="{{ this_field_style }}"
    {{ this_field_type == 'select_multiple' ? 'multiple' : '' }}
    data-width="{{ this_field.width|default("334px") }}"{# XXX Could this default be defined in a central location? #}
    data-allownew="{{ this_field.allownew|default('false') }}"
    data-sortable="{{ this_field.sortable|default('false') }}"
    data-live-search="{{ this_field.search|default('true') }}"
    data-actions-box="{{ this_field.actions_box|default('false') }}"
    {{ this_field.title is defined ? 'title="' ~ this_field.title ~ '"' : '' }}
    {{ this_field.max_options is defined ? 'data-max-options="' ~ this_field.max_options ~ '"' : '' }}
    {{ this_field.header is defined ? 'data-header="' ~ this_field.header ~ '"' : '' }}
    {{ this_field.hint is defined ? 'data-hint="' ~ this_field.hint ~ '"' : '' }}
    {{ this_field.size is defined ? 'data-size="' ~ this_field.size ~ '"' : '' }}
    {{ this_field.selected_text_format is defined ? 'data-selected-text-format="' ~ this_field.selected_text_format ~ '"' : '' }}
    {{ this_field.separator is defined ? 'data-separator="' ~ this_field.separator ~ '"' : '' }}
></select>
{{ this_field_style != "tokenize" ? '<br />' : '' }}
<a href="#"
   class="text-danger"
   id="clear-options_{{ this_field_id }}">
    <i class="fa fa-times-circle"></i>
    <small>{{ lang._('%s')|format('Clear All') }}</small>
</a>
{%  if this_field_style == "tokenize" %}
&nbsp;&nbsp;
<a href="#"
   class="text-danger"
   id="copy-options_{{ this_field_id }}">
    <i class="fa fa-copy"></i>
    <small>{{ lang._('%s')|format('Copy') }}</small>
</a>
&nbsp;&nbsp;
{#  This doesn't seem to work, returns error:
Uncaught TypeError: navigator.clipboard is undefined #}
<a href="#"
   class="text-danger"
   id="paste-options_{{ this_field_id }}"
   style="display:none">
    <i class="fa fa-paste"></i>
    <small>{{ lang._('%s')|format('Paste') }}</small>
</a>
{%  endif %}

<script>
{#/*
 # =============================================================================
 # toggle functionality
 # =============================================================================
 # A toggle function for checkboxes, radio buttons, and dropdown menus.
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
{%                  set on_set_values_xml = this_field.control.xpath('action/@on_set') %}
{%                  set value_list = [] %}
{%                  set value_list_array = [] %}
{%                  for xml_node in on_set_values_xml %}
<?php $value_list_array[] = $xml_node->__toString() ?>
{%                  endfor %}
<?php $value_list = array_unique($value_list_array); ?>
{#/*  Iterate through the values we found to start building our if blocks. */#}
{%                  for on_set in value_list %}
{#/*  Start if statments looking at different value based on field type */#}
            if ($(this).val() == "{{ on_set }}") {
{#/*  Iterate through the fields only if the "on_set" value matches that of the current for loop's "on_set" variable. */#}
{%                      for target_field in this_field.control.action if target_field['on_set'] == on_set %}
{#/*  We use the field's value so we don't have to have a line of code for each version, check first that they're OK. */#}
{%                          if target_field['do_state'] in [ "disabled", "enabled", "hidden", "visible" ] %}
                toggle("{{ target_field }}", "{{ target_field['type'] }}", "{{ target_field['do_state'] }}");
{%                          endif %}
{%                      endfor %}
            }
{%                  endfor %}
        }
    });
{%      endif %}
{%  endif %}
</script>
