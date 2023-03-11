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
{#              close table and start new one with header #}
{%  set node_label = get_xml_prop(this_node, 'label') %}
{%  set node_id = get_xml_prop(this_node, 'id') %}
<tr {% if this_node.advanced|default(false)=='true' %} data-advanced="true"{% endif %}>
    <th colspan="3">
        <h2>
{%              if this_node.help %}
            <a id="help_for_hdr_{{ node_id }}" href="#" class="showhelp">
                <i class="fa fa-info-circle"></i>
            </a>
{%              elseif this_node.help|default(false) == false %}
            <i class="fa fa-info-circle text-muted"></i>
{%              endif %}
            {{ lang._('%s')|format(node_label) }}
    </h2>
{%              if this_node.help %}
            <div class="hidden" data-for="help_for_hdr_{{ node_id }}">
                <small>{{ lang._('%s')|format(this_node.help) }}</small>
            </div>
{%              endif %}
    </th>
</tr>
