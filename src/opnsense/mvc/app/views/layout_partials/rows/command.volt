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
{%  set this_node_id = get_xml_prop(this_node, 'id', true) %}
{%  set this_node_description = get_xml_prop(this_node, 'label') %}
{{ partial("OPNsense/Dnscryptproxy/layout_partials/rows/header",[
        'this_node': this_node,
        'lang': lang
]) }}
{# XXX Maybe add a header with the description value from the <command> element. #}
{#      Create a safe id derived from the bootgrid id, escaping periods. #}
{# XXX Make safe_id procedure a macro. #}
{# XXX Maybe close previous table and open new if the colspan appraoch doesn't pan out. #}
<?php $safe_id = preg_replace('/\./','_',$this_node_id); ?>
{%  for this_parameter in this_node.parameters.parameter %}
{%      set this_parameter_id = safe_id ~ "_" ~ get_xml_prop(this_parameter, 'id', true) %}
{%      set this_parameter_type = get_xml_prop(this_parameter, 'type', true) %}
{%      set this_parameter_style = get_xml_prop(this_parameter, 'style') %}
{%      set this_parameter_size = get_xml_prop(this_parameter, 'size') %}
<tr id="row_{{ this_parameter_id }}"
    {{ this_node['advanced']|default(false) ? 'data-advanced="true"' : '' }}>
{# ----------------------- Column 1 - Item label ---------------------------- #}
    <td colspan="1">
        <div class="control-label" id="control_label_{{ this_field_id }}">
{%      if this_parameter.help %}
{# Add the help icon if help is defined. #}
            <a id="help_for_{{ this_parameter_id }}"
                       href="#"
                       class="showhelp">
                        <i class="fa fa-info-circle"></i>
                    </a>
{%      else %}
{# Add a "muted" help icon which does nothing. #}
                    <i class="fa fa-info-circle text-muted"></i>
{%      endif %}
                    <b>{{ get_xml_prop(this_parameter, 'label') }}</b>
        </div>
    </td>
{# ------------------- Column 2 - Type + help message. ---------------- #}
    <td colspan="2">
{# Built-in: input field for the user to enter in values to send to the command. #}
{%      if this_parameter_type == "text" %}
    <input id="inpt_{{ this_parameter_id }}_command"
           class="form-control {{ this_parameter_style }}"
           type="text"
           size="{{this_parameter_size|default("36")}}"
           style="height: 34px;
                  padding-left:11px;
                  display: inline;"/>
{# XXX     ^^^^ Migrate this style to CSS #}
{# Built-in: selectpicker (dropdown box) with various selections #}
{%      elseif this_parameter_type == "selectpicker" %}
    <select id="{{ this_parameter_id }}"
            class="selectpicker {{ this_parameter.style }}"
            data-size="{{ this_parameter.size|default(10) }}"
            data-width="{{ this_parameter.width|default("334px") }}"
            data-live-search="true"
            {{ this_parameter.separator is defined ?
            'data-separator="'~this_parameter.separator~'"' : '' }}>
{%          for option in this_parameter.options.option %}
                    <option value="{{ lang._('%s')|format(option) }}">
                        {{ lang._('%s')|format(option) }}
                    </option>
{%          endfor %}
                </select>
{# Built-in: creates XXX I don't know what this is here for. #}
{%      elseif this_parameter_type == "field" %}
    <input id="{{ this_parameter_id }}"
           class="form-control {{ this_parameter.style }}"
           type="text"
           size="{{ this_parameter.size|default("50") }}"
           {{ this_parameter.readonly ?
           'readonly="readonly"' : '' }}
           {{ (this_parameter.hint) ?
           'placeholder="'~this_parameter.hint~'"' : '' }}
            style="height: 34px;
                   display: inline-block;
                   width: 161px;
                   vertical-align: middle;
                   margin-left: 3px;">
{# XXX      ^^^^ style can probably go into CSS #}
{%      endif %}
{%      if this_parameter.help %}
        <div class="hidden" data-for="help_for_{{ this_parameter_id }}">
            <small>{{ lang._('%s')|format(this_parameter.help) }}</small>
        </div>
{%      endif %}
    </td>
</tr>
<tr>
    <td colspan="1">{# Intentionally left blank #}</td>
    <td colspan="2">
{%      for button in this_node.buttons.button %}
{%          set button_id = get_xml_prop(button, 'id', true) %}
{%          set button_label = get_xml_prop(button, 'label', true) %}
{# https://forum.phalcon.io/discussion/19045/accessing-object-properties-whose-name-contain-a-hyphen-in-volt
   Below we reference some variables which have dashes in their names, Volt has no built-in way to do this.
   Using PHP to do this for now until I figure a way to get in commands to the compiler. #}
    <button id="btn_{{ this_node_id }}_{{ button_id }}_command"
            class="btn btn-primary"
            type="button"
{%              if button['action'] == "SimpleActionButton" %}
            data-label="{{ button_label }}"
            data-endpoint="{{ button.endpoint }}"
            data-error-title="<?php echo $button->{'error-title'}; ?>"
            data-service-widget="<?php echo $button->{'service-widget'}; ?>"
{%              endif %}
    >
{# If SimpleActionButton no label or progress spinner, since that will be provided by SimpleActionButton. #}
{%              if button['type'] != "SimpleActionButton" %}
        <b>{{ lang._('%s')|format(button_label) }}</b>
        <i id="btn_{{ this_node_id }}_{{ button_id }}_command_progress"></i>
{%              endif %}
    </button>
{%      endfor %}
    </td>
</tr>
{%  endfor %}




{#
 # Command Output area.
 #}
{%  if this_node.output is true %}
<tr>
    <td colspan="3">
        <pre
            id="pre_{{ this_node_id }}_command_output"
            style="white-space: pre-wrap;"
        >{{ this_node.placeholder_text|default('') }}</pre>
    </td>
</tr>
{%  endif %}



<script>
{#/*
 # =============================================================================
 # command: attachments for command field types
 # =============================================================================
 # Attaches to the command button sets up the classes and
 # defines the API to be called when clicked
*/#}
{%  for button in this_node.buttons.button %}
{%      set button_id = this_node_id~'_'~get_xml_prop(button, 'id', true) %}
{%      set button_label = get_xml_prop(button, 'label') %}
$('#btn_{{ button_id }}_command').click(function(){
    var command_input = [];
{%      for this_parameter in this_node.parameters.parameter %}
{%          set this_parameter_id = safe_id ~ "_" ~ get_xml_prop(this_parameter, 'id', true) %}
{%          set this_parameter_type = get_xml_prop(this_parameter, 'type', true) %}
{%          set this_parameter_style = get_xml_prop(this_parameter, 'style') %}
{%          set this_parameter_size = get_xml_prop(this_parameter, 'size') %}
{%          if this_parameter_type == "text" %}
    command_input.push($("#inpt_" + $.escapeSelector("{{ this_parameter_id }}_command")).val());
{%          elseif this_parameter_type == "selectpicker" %}
    command_input = [ $("button[data-id=" + $.escapeSelector("{{ this_parameter_id }}")).attr('title') ];
{%          endif %}
{#/* XXX Probably not needed.
{%              if this_node.output.__toString() == "true" %}
//        $('#pre_{{ this_node_id }}_command_output').text("Executing...");
{%              endif %}
*/#}
    $("#btn_{{ button_id }}_command_progress").addClass("fa fa-spinner fa-pulse");
{%          if button.endpoint %}
    ajaxCall(url='{{ button.endpoint }}', sendData={'command_input':command_input}, callback=function(data,status) {
        if (data['status'] != "ok") {
{%              if this_node.output.__toString() == "true" %}
            $('#pre_{{ this_node_id }}_command_output').text(data['status']);
{%              endif %}
        } else {
{%              if this_node.output %}
            $('#pre_{{ this_node_id }}_command_output').text(data['response']);
{%              endif %}
        }
{#         toggle("tr_{{ this_node_id }}_command_output", 'tr','visible' ); #}
        $("#btn_{{ button_id }}_command_progress").removeClass("fa fa-spinner fa-pulse");
    });
{%          endif %}
});
{%      endfor %}
{%  endfor %}
</script>
