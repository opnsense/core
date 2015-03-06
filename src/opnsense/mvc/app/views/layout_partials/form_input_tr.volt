<tr for="{{ id }}">
    <td >
        <label class="control-label" for="{{ id }}">{{label}}</label>
    </td>
    <td >
        {% if type == "text" %}
            <input type="text" class="form-control" size="{{size|default("50")}}" id="{{ id }}" >
        {% elseif type == "checkbox"  %}
            <input type="checkbox" id="{{ id }}" >
        {% endif %}

        {% if help|default(false) %}
            <br/>
            <small>{{help}}</small>
        {% endif %}
    </td>
    <td>
        <span class="help-block" for="{{ id }}"></span>
    </td>
</tr>
