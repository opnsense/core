<ul class="nav nav-tabs " role="tablist"  id="maintabs">
    {% for tab in tabs|default([]) %}
        <li {% if activetab|default("") == tab[0] %} class="active" {% endif %}><a data-toggle="tab" href="#tab_{{tab[0]}}"><b>{{tab[1]}}</b></a></li>
    {% endfor %}
</ul>

<div class="content-box tab-content">
{% for tab in tabs|default([]) %}
        <div id="tab_{{tab[0]}}" class="tab-pane fade{% if activetab|default("") == tab[0] %} in active {% endif %}">
            <form id="frm_{{tab[0]}}" class="form-inline">
                <table class="table table-striped table-condensed table-responsive">
                    <colgroup>
                        <col class="col-md-3"/>
                        <col class="col-md-4"/>
                        <col class="col-md-5"/>
                    </colgroup>
                    <tbody>
                        <tr>
                            <td colspan="3" align="right">
                                <small>{{ lang._('toggle full help on/off') }} </small><a href="#"><i class="fa fa-toggle-off text-danger" id="show_all_help_{{tab[0]}}" type="button"></i></a>
                            </td>
                        </tr>
                        {% for field in tab[2]|default({})%}
                        {{ partial("layout_partials/form_input_tr",field)}}
                        {% endfor %}
                    <tr>
                        <td colspan="3"><button class="btn btn-primary"  id="save_{{tab[0]}}" type="button">Apply <i id="frm_{{tab[0]}}_progress" class=""></i></button></td>
                    </tr>
                    </tbody>
                </table>
            </form>
        </div>

{% endfor %}
</div>
