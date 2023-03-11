{#
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
 # THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
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

{##
 #
 #
 #
 # @xml SimpleXMLObject the XML data to look through for tabs
 #}
{%  macro build_tab_headers(xml, lang, params) %}
{%          for tab_element in xml.tab %}
{%              if loop.first %}
{# Create unordered list for the tabs, and try to pick an active tab, only on the first loop. #}
<ul class="nav nav-tabs" role="tablist" id="maintabs">
{# If we have no params['active_tab'] defined, if we have no subtabs, pick self, else pick first subtab. #}
{%                  if params['active_tab']|default('') == '' %}
{%                      if !(tab_element.subtab) %}
{%                          set params['active_tab'] = tab_element['id']|default() %}
{%                      else %}
{%                          set params['active_tab'] = tab_element.subtab[0]['id']|default() %}
{%                      endif %}
{%                  endif %}
{%              endif %}
{# If we have subtabs, then let's accommodate them. #}
{%              if tab_element.subtab %}
{# We need to look forward to understand if one of our subtabs is the assigned params['active_tab'] from the form. #}
{%                  set active_subtab = false %}
{%                  for node in tab_element.xpath('subtab/@id') %}
{%                      if node.__toString() == params['active_tab'] %}
{%                          set active_subtab = true %}
{%                      endif %}
{%                  endfor %}
{# Since we have a subtab, we need to accommodate it with an appropriate dropdown button to display the menu. #}
{# If one of our subtabs is params['active_tab'], then we need to set this tab as active also. #}
<li role="presentation" class="dropdown{% if active_subtab == true %} active{% endif %}">
  <a data-toggle="dropdown"
     href="#"
     class="dropdown-toggle
            pull-right
            visible-lg-inline-block
            visible-md-inline-block
            visible-xs-inline-block
            visible-sm-inline-block"
     role="button">
    <b><span class="caret"></span></b>
  </a>
{# The onclick sets the tab to be selected when the tab itself is clicked. #}
{# If one is defined in the XML, then use that, else pick the first subtab. #}
{%                  set tab_onclick = tab_element['on_click']|default(tab_element.subtab[0]['id']) %}
  <a data-toggle="tab"
     onclick="$('#subtab_item_{{ tab_onclick }}').click();"
     class="visible-lg-inline-block
            visible-md-inline-block
            visible-xs-inline-block
            visible-sm-inline-block"
     style="border-right:0px;">
{# This is the parent tab of the subtabs #}
     <b>{{ lang._('%s')|format(tab_element['description']) }}</b>
  </a>
  <ul class="dropdown-menu" role="menu">
{# Now we specify each subtab, iterate through the subtabs for this tab if present. #}
{%                  for subtab_element in tab_element.subtab %}
{%                      if loop.first %}
{# Assume the first subtab should be active if no params['active_tab'] is set. #}
{%                          if params['active_tab'] == '' %}
{%                              set params['active_tab'] = subtab_element['id']|default() %}
{%                          endif %}
{%                      endif %}
<li class="{% if params['active_tab'] == subtab_element['id'] %}active{% endif %}">
    <a data-toggle="tab"
       id="subtab_item_{{ subtab_element['id'] }}"
       href="#subtab_{{ subtab_element['id'] }}"
       style="{{ get_xml_prop(subtab_element, 'style') }}">
        {{ lang._('%s')|format(subtab_element['description']) }}
    </a>
</li>
{%                  endfor %}
    </ul>
  </li>
{%              else %} {# No subtabs, standard tab, no dropdown#}
<li {% if params['active_tab'] == tab_element['id'] %} class="active" {% endif %}>
  <a data-toggle="tab"
     id="tab_header_{{ tab_element['id'] }}"
     href="#tab_{{ tab_element['id'] }}"
     style="{{ get_xml_prop(tab_element, 'style') }}">
    <b>{{ lang._('%s')|format(tab_element['description']) }}</b>
  </a>
</li>
{%              endif %}
{%              if loop.last %}
{# Close the unordered list only on the last loop. #}
</ul>
{%              endif %}
{%          endfor %}
{%  endmacro %}


{##
 # This function builds box contents for each defined box in the XML. Similar
 # to tabs. Supports individual model definitions for each box with base_table.
 #
 #}
{%  macro build_field_contents(xml, lang, params) %}
{#  Since we have only fields, call the partial directly,
    we'll just put them in one box for now. It looks OK.
    Supports model definition via the root XML element. #}
<div class="content-box">
{{              partial("layout_partials/base_table",[
                    'this_part':xml,
                    'lang': lang,
                    'params': params
                ]) }}
</div>
{%  endmacro %}


{#/*
 #   This function is used throughout to provide support for getting values from xml nodes from either the attributes
 #   or a sub-element style definition. This allows for flexibility and backwards compatibility in the XML.
 #   This shouldn't be used for retreving ALL properties, but only those properties which should be allowed to be defined
 #   in either style.
 #
 #   XML attributes have certain restrictions such as no duplicate definitions.
 #   XML sub-elements do allow duplicates.
 #
 */#}
{% macro get_xml_prop(xml, property_name, required = false) %}
{# The below volt return doesn't work correctly because of the object->variable_name call. Doing it in PHP instead. #}
{# {%     return (xml[property_name]|default(xml.property_name)).__toString() %} #}
<?php $xml_prop = ((empty($xml[$property_name]) ? ($xml->$property_name) : ($xml[$property_name])))->__toString(); ?>
{%     if required %}
{%         if xml_prop == '' %}
{# XXX Needs to be wrapped in a lang call, but lang() isn't passed in currently. Needs rework. #}
{%                  set throw_msg = "Element '"~ property_name ~ "' undefined or empty in:" %}
<?php $throw_msg .= chr(0x0A) . var_export($xml, true); ?>
<?php throw new \Phalcon\Mvc\View\Exception($throw_msg); ?>
{%         endif %}
{%     endif %}
{%     return xml_prop %}
{% endmacro %}

{#/*
 # Function to get the id of a given XML field element.
 #
 # This function provides backwards compatibility between the legacy model.field_id style,
 # while supporting the newer approach of defining the model in the XML itself.
/*#}
{%  macro get_field_id(xml, model, lang, params = null) %}
{%      if xml.getName() == 'field' %}
{# Only operate on <field> XML elements. #}
{%          set field_id = get_xml_prop(xml, 'id') %}
{# Grab the id of the field from either the attr or sub-element. #}
{%          if field_id != '' %}
{# We have a field id at least. #}
{%              if model != '' %}
{# A model defined in the xml, that's the new style, let's use that.
  Technically the feild id could still have a model name in it like modelname.fieldname, but it won't break anything. #}
{%                  set this_field_id = model ~ "." ~ field_id %}
{%              else %}
{# No model defined, let's see if the <id> contains a period to signify the model name instead (legay). #}
{%                  if '.' in field_id %}
{%                      set this_field_id = field_id %}
{%                  else %}
{%                      set throw_msg = lang._("Element <id> missing model specification (Example: <id>model.id</id>):") %}
{%                  endif %}
{%              endif %}
{%          else %}
{%              set throw_msg = lang._("Element missing <id> sub-element definition (Example: <id>model.id</id>):") %}
{%          endif  %}
{%      endif %}
{%      if throw_msg is defined %}
{# We've got a throw message so one of the evaluations above failed, throw to inform the Crash Reporter. #}
<?php $throw_msg .= chr(0x0A) . var_export($xml, true); ?>
<?php throw new \Phalcon\Mvc\View\Exception($throw_msg); ?>
{%      else %}
{# Return this_field_id, which is hopefully defined at this point. #}
{%          return this_field_id %}
{%      endif %}
{%  endmacro %}

{##
 # This function builds box contents for each defined box in the XML. Similar
 # to tabs. Supports individual model definitions for each box with base_table.
 #
 #}
{%  macro build_container_contents(xml, lang, params = null) %}
{%      if xml.getName() == 'box' %}
<section class="col-xs-12">
    <div class="content-box">
{{              partial("layout_partials/base_table",[
                    'this_part':xml,
                    'lang': lang,
                    'params': params
                ]) }}
    </div>
</section>
{%      elseif xml.getName() == 'buttons' %}
<section class="page-content-main">
    <div class="content-box">
            <br>
{{  partial("layout_partials/rows/buttons",[
            'this_node': xml,
            'lang': lang,
            'params': params
]) }}<br><br><br>{# XXX Replace these brs with some style padding instead. #}
    </div>
</section>
{%      endif %}
{%  endmacro %}



{##
 # This macro builds a page using the form data as input.
 #
 # This is a super macro that builds pages with or without tabs, the tab
 # headers, tab contents, and the bootgrid_dialogs all at once.
 #
 # This is to save on having to put all of these commands in the main volt, and
 # to put the div definition in the right place on the page.
 #
 # this_page SimpleXMLObject from which to build the page
 #}

{%  macro build_tab_content(xml, lang, params) %}
{# Use the name of the element to specify the prefix, this will be 'tab' or 'subtab' #}
<div id="{{ xml.getName() }}_{{ xml['id'] }}"
     class="tab-pane fade in{% if params['active_tab'] == xml['id'] %} active{% endif %}">
{{              partial("layout_partials/base_table",[
                    'this_part': xml,
                    'lang': lang,
                    'params': params
                ]) }}
</div>
{%  endmacro %}


{%  macro build_xml_part(xml, lang, params = null) %}
{%      set tab_count = 0 %}
{# {%      for xml_element in xml if xml_element.getName() != 'tab' %} #}
{%      for xml_element in xml %}
{%          if tab_count != 0 and xml_element.getName() != 'tab' %}
{# Iterating from something that was tabs, to something that's not tabs, so reset the tab counter, and close the tab box. #}
{%              set tab_count = 0 %}
</div>
{%          endif %}
{%          if xml_element.getName() == 'tab' %}
{%              set tab_count += 1 %}
{%              if tab_count == 1 %}
{{                  build_tab_headers(xml, lang, params) }}
{# Building tabs, so let's open the tab box div. #}
<div class="tab-content content-box tab-content">
{%              endif %}
{%              if xml_element.subtab %}
{# Instead of iterating through elements, checking against getName(), we'll just assume that only subtabs are children of tabs. #}
{# All other elements will be ignored. #}
{%                  for subtab in xml_element.subtab %}
{{                      build_tab_content(subtab, lang, params) }}
{%                  endfor %}
{%              else %}
{{                  build_tab_content(xml_element, lang, params) }}
{%              endif %}
{%          elseif xml_element.getName() == 'box' %}
{# If there are tabs here, we need to build them now. #}
{{                  build_container_contents(xml_element, lang, params) }}
{%          elseif xml_element.getName() == 'field' %}
{# XXX Fields should be treated as a group like tabs are, and grouped into a box automatically. #}
{# XXX field contents function currently expects to be passed xml.field, and pass that on to base_table #}
{# XXX Base table then draws the table and iterates through all of the fields. It's not going to work for this approach. #}
{# XXX {%  for node in this_part %}
{# XXX We'll need to not use base_table.  Maybe draw the table ourselves, and call the field type in here? #}
{# If there are tabs here, we need to build them now. #}
{{                  build_field_contents(xml_element, lang, params) }}
{%          elseif xml_element.getName() == 'model' %}
{{                build_xml(xml_element, lang, params) }}
{%          else %}{# Catch all other element types. #}
{{                  build_container_contents(xml_element, lang, params) }}
{%          endif %}
{%      endfor %}
{%  endmacro %}


{%  macro build_form(xml, lang, params = null) %}

{# {%      for this_form in xml.xpath('//*/form') %} #}
{# Include a hidden apply changes field which becomes visible when the configuration changes without applying. #}
{%         include "layout_partials/floating/apply_changes.volt" %}
{# {%      endfor %} #}

{# {%                  set this_id = get_xml_prop(xml, 'id') %} #}
{%                  set params['model'] = get_xml_prop(xml, 'name') %}
{%                  set params['model_endpoint'] = get_xml_prop(xml, 'endpoint') %}
{%                  set params['title'] = get_xml_prop(xml, 'title') %}
{# Since this is a form element, we need to go deeper. Pass params since we've maybe added a few things. #}
{# XXX Do we need this _in_form variable? #}
{%                  if params['_in_form'] is not defined %}
{# Set a flag, so if we return here, we'll know we're in a form. #}
{%                      set params['_in_form'] = true %}
{%                      if params['model'] != '' and params['model_endpoint'] != '' %}
{# XXX Figure out if data-title should be required. #}
{# Open up the HTML form element. #}
<form id="frm_{{ params['model'] }}"
      class="form-inline"
      data-title="{{ params['title'] }}"
      data-model="{{ params['model'] }}"
      data-model-endpoint="{{ params['model_endpoint'] }}">
{%                      else %}
{# XXX Try to throw here inform the user of missing definition. #}
{# {%                          break %} #}
{%                      endif %}
{%                  else %}
{# XXX Try to throw here with some PHP instead of break. #}
{# {%                      break %} #}
{%                  endif %}
{{                  build_xml_part(xml, lang, params) }}
{%                  if params['_in_form'] == true %}
{# Close the HTML form element that was opened earlier. #}
</form>
{# Set our flag to false so we'll know we're not in a form if we return. #}
{%                      set params['_in_form'] = false %}
{%                      set params['model'] = '' %}
{%                      set params['model_endpoint'] = '' %}
{%                      set params['title'] = '' %}
{%                  endif %}
{%  endmacro %}

{#
 # This function is the starting function for processing an XML.
 #
 #}
{%  macro build_xml(xml, lang, params = null) %}
{%      if xml %}
{%          if params['active_tab'] is empty %}
{%              set params['active_tab'] = get_xml_prop(xml, 'activetab') %}
{%          endif %}
{%          if xml.getName() == 'model' %}
{{              build_form(xml, lang, params) }}
{%          else %}
{{              build_xml_part(xml.children(), lang, params) }}
{%          endif %}
{# After we've closed the form for the model, we'll be safe to build
   any dialogs for this model (which also include form elements).
Since they're defined within the model, let's assume that their belong to it. #}
{%      for bootgrid in xml.xpath('//*/bootgrid[dialog]') %}
{{          partial("layout_partials/bootgrid_dialog",[
                    'this_grid':bootgrid,
                    'lang': lang,
                    'params': params
                ]) }}
{%      endfor %}



{#  # Conditionally display buttons at the bottom of the page. #}
{%          if xml.button %}
<section class="page-content-main">
{# Alert class used to get padding to look good.
   Maybe there is another class that can be used. #}
  <div class="alert alert-info" role="alert">
{%              for button_element in xml.button %}
{%                  if button_element['type']|default('primary') in ['primary', 'group' ] %} {# Assume primary if not defined #}
{%                      if button_element['type']|default('') == 'primary' and
                           button_element['action'] %}
    <button class="btn btn-primary"
            id="btn_{{ plugin_safe_name }}_{{ button_element['action'] }}"
            type="button">
      <i class="{{ button_element['icon']|default('') }}"></i>
      &nbsp
      <b>{{ lang._('%s') | format(button_element.__toString()) }}</b>
      <i id="btn_{{ plugin_safe_name }}_progress"></i>
    </button>
{%                      elseif button_element['type'] == 'group' %}
{#  We set our own style here to put the button in the right place. #}
    <div class="btn-group"
         {{ (button_element['id']|default('') != '') ?
             'id="'~button_element['id']~'"' : '' }}>
      <button type="button"
              class="btn btn-default dropdown-toggle"
              data-toggle="dropdown">
        <i class="{{ button_element['icon'] }}"></i>
        &nbsp
        <b>{{ lang._('%s') | format(button_element['label']) }}</b>
        <i id="btn_{{ plugin_safe_name }}_progress"></i>
        &nbsp
        <i class="caret"></i>
      </button>
{%                          if button_element.dropdown %}
      <ul class="dropdown-menu" role="menu">
{%                              for dropdown_element in button_element.dropdown %}
        <li>
          <a id="drp_{{ plugin_safe_name }}_{{ dropdown_element['action'] }}">
            <i class="{{ button_element['icon'] }}"></i>
            &nbsp
            {{ lang._('%s') | format(dropdown_element.__toString()) }}
          </a>
        </li>
{%                              endfor %}
      </ul>
{%                          endif %}
    </div>
{%                      endif %}
{%                  endif %}
{%              endfor %}
  </div>
</section>
{%      endif %}
{%    endif %}
{%  endmacro %}
