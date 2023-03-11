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
 # This partial is for the checkbox field type.
 #
 # This creates a simple HTML checkbox that the user can click to select an item.
 #
 # The value for this field will get translated as checked = 1, or unchecked = 0.
 #
 # This field support field control.
 #
 # Example usage in form XML:
 # <field>
 #     <id>enabled</id>
 #     <label>Enable DNSCrypt Proxy</label>
 #     <help>This will enable the dnscrypt-proxy service.</help>
 #     <type>checkbox</type>
 # </field>
 #
 # Compatible model field types:
 # BooleanField
 # TextField
 # IntegerField
 #
 # Intended to be used with BooleanField type in the model:
 # <enabled type="BooleanField">
 #     <required>Y</required>
 #     <default>0</default>
 # </enabled>
 #
 # Example partial call from another volt template:
 # {{      partial("OPNsense/Dnscryptproxy/layout_partials/fields/checkbox",[
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
 # The field id could be derived from this_field, but it's already acquired earlier by rows/field.volt, so it's just
 # assumed to be passed in to save a little work.
#}
<input
    type="checkbox"
    class="{{ this_field.style|default('') }}"
    id="{{ this_field_id }}"
>
{# =================================================================================================================== #}
<script>
{#/*
 # =============================================================================
 # checkbox, radio, dropdown: toggle functionality
 # =============================================================================
 # A toggle function for checkboxes, radio buttons, and dropdown menus.
 # XXX This function is mostly the same for four types of fields, and maybe this could be separated into a function.
 # XXX Maybe it could be called toggle_control().
 # After thinking about this, i don't think that a javascript function would offer much advantage. We'd have to create
 # a variable (array) stuff it with the info from the XML, then just pass the variable to the function, and it would
 # have to iterate through it, and also deal with the logic. This would also include the the logic of the state
 # conditions (checked = true/false for checkboxes, but something different for dropdowns and radio buttons).
 # This approach seems less work, even though it's mostly the same code 4 times.
 # XXX We could also turn this back into a macro, and simply call the macro here.
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
{#/*  Iterate through the values we found to start building our if blocks.  */#}
{%              for on_set in value_list %}
            if ($(this).prop("checked") == {{ on_set }} ) {
{#/*  Iterate through the fields only if the "on_set" value matches that of the current for loop's "on_set" variable. */#}
{%                  for target_field in this_field.control.action if target_field['on_set'] == on_set %}
{#/*  We use the field's value so we don't have to have a line of code for each version, check first that they're OK. */#}
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
</script>
