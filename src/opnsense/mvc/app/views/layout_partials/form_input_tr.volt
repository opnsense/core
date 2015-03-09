<tr for="{{ id }}">
    <td >
        <div class="control-label" for="{{ id }}">
                {{label}}
                {% if help|default(false) %} <a id="help_for_{{ id }}" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> {% endif %}
        </div>
    </td>
    <td >
        {% if type == "text" %}
            <input type="text" class="form-control" size="{{size|default("50")}}" id="{{ id }}" >
        {% elseif type == "checkbox"  %}
            <input type="checkbox" id="{{ id }}" >
        {% endif %}

        {% if help|default(false) %}
            <br/>
            <small class="hidden" for="help_for_{{ id }}" >{{help}}</small>
        {% endif %}
    </td>
    <td>
        <span class="help-block" for="{{ id }}" ></span>
    </td>
</tr>
