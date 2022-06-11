{#
 # Copyright (c) 2017 Franco Fichtner <franco@opnsense.org>
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
 #
 # formData : the form data
 #}

{% for tab in formData['tabs']|default([]) %}
    {% if tab['subtabs']|default(false) %}
        {# Tab with dropdown #}
        {% for subtab in tab['subtabs']|default({})%}
                <div id="subtab_{{subtab[0]}}" class="tab-pane fade{% if formData['activetab']|default("") == subtab[0] %} in active {% endif %}">
                    {{ partial("layout_partials/base_form",['fields':subtab[2],'id':'frm_'~subtab[0],'data_title':subtab[1],'apply_btn_id':'save_'~subtab[0]])}}
                </div>
        {% endfor %}
    {% endif %}
    {% if tab['subtabs']|default(false)==false %}
            <div id="tab_{{tab[0]}}" class="tab-pane fade{% if formData['activetab']|default("") == tab[0] %} in active {% endif %}">
                {{ partial("layout_partials/base_form",['fields':tab[2],'id':'frm_'~tab[0],'apply_btn_id':'save_'~tab[0]])}}
            </div>
    {% endif %}
{% endfor %}
