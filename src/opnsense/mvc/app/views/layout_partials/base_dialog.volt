{#

OPNsense® is Copyright © 2014 – 2015 by Deciso B.V.
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

1.  Redistributions of source code must retain the above copyright notice,
this list of conditions and the following disclaimer.

2.  Redistributions in binary form must reproduce the above copyright notice,
this list of conditions and the following disclaimer in the documentation
and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.

---------------------------------------------------------------------------------------------------------------
Generate input dialog, uses the following parameters (as associative array):

fields          :   list of field type objects, see form_input_tr tag for details
id              :   form id, used as unique id for this modal form. inner form to place data is called frm_[id]
                    save button is identified by btn_[id]_save
label           :   dialog label

#}


{# Find if there are help supported or advanced field on this page #}
{% set help=false %}
{% set advanced=false %}
{% for field in fields|default({})%}
    {% for name,element in field %}
        {% if name=='help' %}
            {% set help=true %}
        {% endif %}
        {% if name=='advanced' %}
            {% set advanced=true %}
        {% endif %}
    {% endfor %}
    {% if help|default(false) and advanced|default(false) %}
        {% break %}
    {% endif %}
{% endfor %}

<div class="modal fade" id="{{id}}" tabindex="-1" role="dialog" aria-labelledby="{{id}}Label" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="{{id}}Label">{{label}}</h4>
            </div>
            <div class="modal-body">
                <form id="frm_{{id}}">
                    <table class="table table-striped table-condensed table-responsive">
                        <colgroup>
                            <col class="col-md-3"/>
                            <col class="col-md-{{ 12-3-msgzone_width|default(5) }}"/>
                            <col class="col-md-{{ msgzone_width|default(5) }}"/>
                        </colgroup>
                        <tbody>
                        {%  if advanced|default(false) or help|default(false) %}
                        <tr>
                            <td align="left"><a href="#">{% if advanced|default(false) %}<i class="fa fa-toggle-off text-danger" id="show_advanced_formDialogPipe" type="button"></i> </a><small>{{ lang._('advanced mode') }} </small>{% endif %}</td>
                            <td colspan="2" align="right">
                                {% if help|default(false) %}<small>{{ lang._('full help') }} </small><a href="#"><i class="fa fa-toggle-off text-danger" id="show_all_help_formDialogPipe" type="button"></i></a>{% endif %}
                            </td>
                        </tr>
                        {% endif %}
                        {% for field in fields|default({})%}
                            {{ partial("layout_partials/form_input_tr",field)}}
                        {% endfor %}
                        </tbody>
                    </table>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                {% if hasSaveBtn|default('true') == 'true' %}
                <button type="button" class="btn btn-primary" id="btn_{{id}}_save">Save changes<i id="btn_{{id}}_save_progress" class=""></i></button>
                {% endif %}
            </div>
        </div>
    </div>
</div>
