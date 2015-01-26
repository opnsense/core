
	show template... {{title|default("??") }}

{% for item in items %}
	{{ partial('layout_partials/std_input_field',item) }}
{% endfor  %}

<br>
{% for section in data.childnodes.section.__items %}
	{{ section.node1 }} <br>
{% endfor %}
