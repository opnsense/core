<tr for="{{ id }}">
    <td >
        <div class="control-label" for="{{ id }}">
            {% if help|default(false) %}
                <a id="help_for_{{ id }}" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a>
            {% elseif help|default(false) == false %}
                <i class="fa fa-info-circle text-muted"></i>
            {% endif %}
            <b>{{label}}</b>
        </div>
    </td>
    <td >
        {% if type == "text" %}
            <input type="text" class="form-control" size="{{size|default("50")}}" id="{{ id }}" >
        {% elseif type == "checkbox"  %}
            <input type="checkbox" id="{{ id }}" >
        {% elseif type == "select_multiple" %}
            <select multiple="multiple" {% if size|default(false) %}size="{{size}}"{% endif %}  id="{{ id }}" {% if style|default(false) %}class="{{style}}" {% endif %}  {% if hint|default(false) %}data-hint="{{hint}}"{% endif %}  {% if maxheight|default(false) %}data-maxheight="{{maxheight}}"{% endif %} data-width="{{width|default("348px")}}" data-allownew="{{allownew|default("false")}}"></select>
        {% endif %}

        {% if help|default(false) %}
            <small class="hidden" for="help_for_{{ id }}" >{{help}}</small>
        {% endif %}
    </td>
    <td>
        <span class="help-block" for="{{ id }}" ></span>
    </td>
</tr>
