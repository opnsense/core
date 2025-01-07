{# requires getFormGrid() input to render #}
<table id="{{ table_id }}" class="table table-condensed table-hover table-striped" data-editDialog="{{ edit_dialog_id }}" data-editAlert="{{ edit_alert_id }}">
    <thead>
        <tr>
            {% for field in fields %}
            <th {% for k,v in field %} data-{{k}}="{{v}}"{% endfor %} >{{field['label']}}</th>
            {% endfor %}
            <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">
                {{ lang._('Commands') }}
            </th>
        </tr>
    </thead>
    <tbody></tbody>
    <tfoot>
        <tr>
            <td>

            </td>
            <td>
                <button data-action="add" type="button" class="btn btn-xs btn-primary">
                    <span class="fa fa-plus"></span>
                </button>
                <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default">
                    <span class="fa fa-trash-o"></span>
                </button>
            </td>
        </tr>
    </tfoot>
</table>
