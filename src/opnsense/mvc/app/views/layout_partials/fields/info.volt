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
 # This partial is for the info field type.
 #
 # This will simply statically display the value of the setting.
 #
 # The user will not be able to edit the field.
 #
 # Example usage in form XML:
 # <field>
 #     <id>first_time_setup</id>
 #     <label>First Time Setup</label>
 #     <type>info</type>
 #     <help>Help text.</help>
 # </field>
 #
 # No specific model field type is intended for this field, it will work with any type which returns a string.
 # Such as IntegerField, BooleanField, TextField.
 #
 # Example partial call from another volt template:
 # {{      partial("OPNsense/Dnscryptproxy/layout_partials/fields/info",[
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
<span
    class="{{ this_field.style }}"
    id="{{ this_field_id }}">
</span>
