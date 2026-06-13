{#
 # Copyright (c) 2014-2026 Deciso B.V.
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
 # Generate input dialog, uses the following parameters (as associative array):
 #
 # fields          :   list of field type objects, see form_input_tr tag for details
 # id              :   form id, used as unique id for this form.
 # apply_btn_id    :   id to use for apply button (leave empty to ignore)
 # data_title      :   data-title to set on form
 #}

{# Find if there are help supported or advanced field on this page #}
{% set base_form_id=id %}

<form id="{{base_form_id}}" class="form-inline" data-title="{{data_title|default('')}}">
    {% for section in fields['sections'] %}
        <div class="table-responsive {{section['style']|default('')}}">
        <table class="table table-striped table-condensed" style="table-layout: fixed; width: 100%;">
            <colgroup>
            {% if msgzone_width is defined %}
                <col class="col-md-3"/>
                <col class="col-md-{{ 12 - 3 - msgzone_width }}"/>
                <col class="col-md-{{ msgzone_width }}"/>
            {% else %}
                <col style="width: 25%;" />
                <col style="width: 40%;" />
                <col style="width: 35%;" />
            {% endif %}
            </colgroup>
            {% if section['type'] %}
            <thead {% if section['static']|default('false')=='false' %} style="cursor: pointer;"{% endif %}>
            <tr{% if section['advanced']|default('false')=='true' %} data-advanced="true"{% endif %}>
                <th colspan="3">
                    <div style="padding-bottom: 5px; padding-top: 5px; font-size: 16px;">
                        {% if section['static']|default('false')=='false' %}
                        {% if section['collapse']|default('false')=='true' %}
                        <i class="fa fa-angle-right" aria-hidden="true"></i>
                        {% else %}
                        <i class="fa fa-angle-down" aria-hidden="true"></i>
                        {% endif %}
                        &nbsp;
                        {% endif %}
                        <b>{{section['label']}}</b>
                    </div>
                </th>
            </tr>
            </thead>
            {% endif %}
            <tbody class="collapsible">
            {%  if not section['type'] and (fields['advanced']|default(false) or fields['help']|default(false)) %}
            <tr>
                <td>{% if fields['advanced']|default(false) %}<a href="#"><i class="fa fa-toggle-off text-danger" id="show_advanced_formDialog{{base_form_id}}"></i></a> <small>{{ lang._('advanced mode') }}</small>{% endif %}</td>
                <td colspan="2" style="text-align:right;">
                    {% if fields['help']|default(false) %}<small>{{ lang._('full help') }}</small> <a href="#"><i class="fa fa-toggle-off text-danger" id="show_all_help_formDialog{{base_form_id}}"></i></a>{% endif %}
                </td>
            </tr>
            {% endif %}
            {% for field in section['children']%}
                {% if field['type'] == 'subheader' %}
                    <tr{% if field['advanced']|default('false') == 'true' %} data-advanced="true"{% endif %}>
                        <td colspan="3">
                            <div style="padding-bottom: 5px; padding-top: 5px; font-size: 16px; padding-left: 5px;">
                                <i class="fa fa-ellipsis-v" aria-hidden="true"></i>
                                &nbsp;
                                <b>{{ field['label'] }}</b>
                            </div>
                        </td>
                    </tr>
                {% elseif field['type'] != 'ignore' %}
                    {{ partial("layout_partials/form_input_tr", field)}}
                {% endif %}
            {% endfor %}
            {% if loop.last and apply_btn_id|default('') != '' %}
                    <tr>
                        <td colspan="3">
                            <button class="btn btn-primary" id="{{apply_btn_id}}" type="button"><b>{{ lang._('Apply') }}</b> <i id="{{base_form_id}}_progress" class=""></i></button>
                        </td>
                    </tr>
            {% endif %}
            </tbody>
        </table>
        </div>
    {% endfor %}
</form>


{# Ensure all fields stay the same width relative to each other inside the modal #}
<style>
  @media (max-width: 760px) {
    .form-inline .bootstrap-select:not(.bs-container),
    .form-inline .tokenize ul.tokens-container {
      width: 100% !important;
      min-width: 0 !important;
    }
  }
</style>
