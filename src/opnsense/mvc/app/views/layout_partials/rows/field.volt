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
{##-
 # This is a partial for building an HTML table row within a tab (form).
 #
 # This gets called by base_form.volt, and base_dialog.volt.
 #
 # Expects to receive an array by the name of this_field.
 #
 # The following keys may be used in this partial:
 #
 # this_field.id                : unique id of the attribute
 # this_field.type              : type of input or field. Valid types are:
 #           text                    single line of text
 #           password                password field for sensitive input. The contents will not be displayed.
 #           textbox                 multiline text box
 #           checkbox                checkbox
 #           dropdown                single item selection from dropdown
 #           select_multiple         multiple item select from dropdown
 #           hidden                  hidden fields not for user interaction
 #           info                    static text (help icon, no input or editing)
 #           command                 command button, with optional input field
 #           radio                   radio buttons
 #           managefile              upload/download/remove box for a file
 #           startstoptime           time input for a start time, and stop time.
 # this_field.label             : attribute label (visible text)
 # this_field.size              : size (width in characters) attribute if applicable
 # this_field.height            : height (length in characters) attribute if applicable
 # this_field.help              : help text
 # this_field.advanced          : property "is advanced", only display in advanced mode
 # this_field.hint              : input control hint
 # this_field.style             : css class to add
 # this_field.width             : width in pixels if applicable
 # this_field.allownew          : allow new items (for list) if applicable
 # this_field.readonly          : if true, input fields will be readonly
 # this_field.start_hour_id     : id for the start hour field
 # this_field.start_min_id      : id for the start minute field
 # this_field.stop_hour_id      : id for the stop hour field
 # this_field.stop_min_id       : id for the stop minute field
 # this_field['button_label']      : label for the command button
 # this_field['input']             : boolean field to enable input on command field
 # this_field['buttons']['button'] : array of buttons for radio button field
 #}
{# Set the field id supporting both attribute and legacy sub-element definition, giving preference to the attr method.
   This flattens it out to a string, regardless of which one is selected.
   Subsequent <id> sub element definitions beyond the first are ignored.
   Multple attributes of the same name aren't possible as it's invalid XML syntax and will throw. #}
{%  set this_field_id = get_field_id(this_node, model, lang, params) %}
{# {%  set field_id = (this_field['id']|default(this_field.id)).__toString() %} #}
{%  set this_field_label = get_xml_prop(this_node, 'label') %}
{%  set this_field_type = get_xml_prop(this_node, 'type') %}
{# This should catch the conditions of no id's defined in both attr and sub-elements. #}
{# XXX Maybe xpath can be used to do this test? and test for other required conditions throughout the XML. #}
{#
{%  if this_field_id|length == 0 %}
{# Error handling, throw will get caught by Crash Reporter and displayed to the user.
{% set msg = lang._("Element <field> missing 'id' definition in XML:") %}
<?php $msg .= chr(0x0A) . var_export($this_field, true); ?>
<?php throw new \Phalcon\Mvc\View\Exception($msg); ?>
{%  else %}
{%     if params['model'] %}
{# Prepend the model (if defined) onto the field id since it will be needed by mapDataToFormUI.
{%         set this_field_id = params['model']~'.'~field_id %}
{%     else %}
{%         set this_field_id = field_id %}
{%     endif %}
#}
{# Set up the help, and advanced text settings for this field's row. #}
<tr id="row_{{ this_field_id }}"
    {{ this_node.advanced ? 'data-advanced="true"' : '' }}
    {{ this_node.hidden ? 'style="display: none;"' : '' }}>
{# ----------------------- Column 1 - Item label ---------------------------- #}
    <td>
        <div class="control-label" id="control_label_{{ this_field_id }}">
{%      if this_node.help %}
{# Add the help icon if help is defined. #}
            <a id="help_for_{{ this_field_id }}"
               href="#"
               class="showhelp">
                <i class="fa fa-info-circle"></i>
            </a>
{%      else %}
{# Add a "muted" help icon which does nothing. #}
                <i class="fa fa-info-circle text-muted"></i>
{%      endif %}
                <b>{{ this_field_label }}</b>
        </div>
    </td>
{# ------------------- Column 2 - Item field + help message. ---------------- #}
    <td>
{# Call the partial for the given field type. #}
{# We pass in this_field_id because it's prepared earlier (before the row is created,
   and we don't want to have to put that code in each field volt. #}
{{      partial("layout_partials/fields/" ~ this_field_type,[
                'this_field': this_node,
                'this_field_id': this_field_id,
                'lang': lang,
                'params': params
]) }}
{# If help is defined, add it after the field definition so it will appear below it. #}
{%      if this_node.help %}
        <div
            class="hidden"
            data-for="help_for_{{ this_field_id }}"
        >
            <small>{{ lang._('%s')|format(this_node.help) }}</small>
        </div>
{%      endif %}
    </td>
{# ------------ Column 3 - Help block to display validation messages ------- #}
{# Add a span to be used by the validator to dispay messages to the user when a validation error occurs. #}
    <td>
        <span
            class="help-block"
            id="help_block_{{ this_field_id }}"
        ></span>
    </td>
</tr>
{# {%  endif %} #}
