{##
 # OPNsense® is Copyright © 2022 by Deciso B.V.
 # Copyright (C) 2022 agh1467@protonmail.com
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

{##
 # This partial is for building a form, including all fields. It's called
 # by other volt scipts and to build tabs, and boxes. The array 'this_part'
 # should be the tab, or box (or possibly other structure) being drawn.
 #
 # This is called by the following functions:
 # _macros::build_tabs()
 # _macros::
 #
 # The array named "this_part" should contain:
 #
 # this_part['id']          : 'id' attribute on 'tab' element in form XML,
 #                            intended to be unique on the page
 # this_part['description'] : 'description' attribute on 'tab' element in form XML
 #                            used as 'data-title' to set on form HTML element
 # this_part.field          : array of fields on this tab
 #}

{%  set help = this_part.xpath('//*/field/help') ? true : false %}
{%  set advanced = this_part.xpath('//*/field/advanced') ? true : false %}

{# This partial may be called by base_dialog, and it will define a different 'model' when called.
   This will set a 'model' to params['model'] in the case it's not overriden. #}
{% if model is not defined  %}
{%     if params['model'] is defined %}
{%         set model = params['model'] %}
{%     else %}
{%         set model = '' %}
{%     endif %}
{% endif %}

{# Start building the table for the fields. #}
<div class="table-responsive">
    <table class="table table-striped table-condensed">
        <colgroup>
            <col class="col-md-3"/>
            <col class="col-md-{{ 12-3-msgzone_width|default(4) }}"/>
            <col class="col-md-{{ msgzone_width|default(5) }}"/>
        </colgroup>
        <tbody>
{# Draw the help row if we have to draw the help or advanced switch. #}
{%  if advanced or help %}
{%      include "layout_partials/rows/help_or_advanced.volt" %}
{%  endif %}
{# Here we iterate through the children in order rather than use the this_part.field
   in order to keep the order when a model definition may be present. #}
{%  for node in this_part %}
{%      set node_type = node.getName() %}
{%      if node_type not in ['style','label'] %}
{# Ignore some elements which aren't actually row types. #}
{# Now call the appropriate partial for that row type. #}
{{  partial("layout_partials/rows/" ~ node_type,[
            'this_node': node,
            'model': model,
            'lang': lang,
            'params': params
]) }}
{%      endif %}

{%  endfor %}
            </tbody>
        </thead>
    </table>
</div>
