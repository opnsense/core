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
 # This partial is for the dropdown field type.
 #
 # This partial is used by rows/fields.volt.
 #
 # Example usage in XML:
 # <field>
 #     <id>server_selection_method</id>
 #     <label>Server selection method</label>
 #     <type>dropdown</type>
 #     <help>Select to use manual server selection options, instead of ... </help>
 # </field>
 #
 # Compatible model field types:
 # OptionField
 # JsonKeyValueStoreField
 #
 # Intended to be used with OptionField type in the model:
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
 # Example partial call from another volt template:
 # {{      partial("OPNsense/Dnscryptproxy/layout_partials/fields/dropdown",[
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
{% include "layout_partials/fields/select_multiple_dropdown.volt" %}
