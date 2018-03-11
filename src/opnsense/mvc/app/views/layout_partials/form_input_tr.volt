{#
 # Copyright (c) 2014-2015 Deciso B.V.
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
 # ------------------------------------------------------------------------------
 #
 # Handle input table row, usage the following parameters (as associative array):
 #
 # id          :   unique id of the attribute
 # label       :   attribute label (visible text)
 # size        :   size (width in characters) attribute if applicable
 # height      :   height (length in characters) attribute if applicable
 # help        :   help text
 # advanced    :   property "is advanced", only display in advanced mode
 # hint        :   input control hint
 # style       :   css class to add
 # maxheight   :   maximum height in rows if applicable
 # width       :   width in pixels if applicable
 # allownew    :   allow new items (for list) if applicable
 # readonly    :   if true, input fields will be readonly
 #}

<tr id="row_{{ id }}" {% if advanced|default(false)=='true' %} data-advanced="true"{% endif %}>
    <td>
        <div class="control-label" id="control_label_{{ id }}">
            {% if help|default(false) %}
                <a id="help_for_{{ id }}" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>
            {% elseif help|default(false) == false %}
                <i class="fa fa-info-circle text-muted"></i>
            {% endif %}
            <b>{{label}}</b>
        </div>
    </td>
    <td>
        {% if type == "text" %}
            <input type="text" class="form-control" size="{{size|default("50")}}" id="{{ id }}" {{ readonly ? 'readonly="readonly"' : '' }} >
        {% elseif type == "checkbox" %}
            <input type="checkbox" id="{{ id }}">
        {% elseif type == "select_multiple" %}
            <select multiple="multiple" {% if size|default(false) %}size="{{size}}"{% endif %} id="{{ id }}" {% if style|default(false) %}class="{{style}}" {% endif %} {% if hint|default(false) %}data-hint="{{hint}}"{% endif %} {% if maxheight|default(false) %}data-maxheight="{{maxheight}}"{% endif %} data-width="{{width|default("334px")}}" data-allownew="{{allownew|default("false")}}" data-nbdropdownelements="{{nbDropdownElements|default("10")}}"></select>
            <br/><a href="#" class="text-danger" id="clear-options_{{ id }}"><i class="fa fa-times-circle"></i></a> <small>{{ lang._('Clear All') }}</small>
        {% elseif type == "dropdown" %}
            <select {% if size|default(false) %}size="{{size}}"{% endif %} id="{{ id }}" class="{{style|default('selectpicker')}}" data-width="{{width|default("334px")}}"></select>
        {% elseif type == "password" %}
            <input type="password" class="form-control" size="{{size|default("50")}}" id="{{ id }}" {{ readonly ? 'readonly="readonly"' : '' }} >
        {% elseif type == "textbox" %}
            <textarea rows="{{height|default("5")}}" id="{{ id }}" {{ readonly ? 'readonly="readonly"' : '' }}></textarea>
        {% elseif type == "info" %}
            <span id="{{ id }}"></span>
        {% endif %}
        {% if help|default(false) %}
            <output class="hidden" for="help_for_{{ id }}">
                <small>{{help}}</small>
            </output>
        {% endif %}
    </td>
    <td>
        <span class="help-block" id="help_block_{{ id }}"></span>
    </td>
</tr>
