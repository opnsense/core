{#
# Copyright (c) 2024-2025 Cedrik Pischem
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without modification,
# are permitted provided that the following conditions are met:
#
# 1. Redistributions of source code must retain the above copyright notice,
#    this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright notice,
#    this list of conditions and the following disclaimer in the documentation
#    and/or other materials provided with the distribution.
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
#}

{#
 # This template generates a Bootgrid table based on the provided parameters and field definitions.
 #
 # Accepted Parameters:
 # - table_id: (string) The ID of the table.
 # - edit_dialog: (string) The ID of the associated edit dialog.
 # - edit_alert: (string) The ID of the alert section displayed for configuration changes.
 # - add_button_id: (string) The ID of the add button.
 # - fields: (array) A presorted list of field definitions. The defaults are handled by the backend.
 #   - data-column-id: (string) The column identifier for rendering (e.g. 'name').
 #   - data-column-label: (string) The display name of the column (e.g. 'Name').
 #   - data-column-visible: (boolean) Determines if the column is generated.
 #   - data-visible: (boolean) Determines if the column is rendered but hidden per default.
 #   - data-type: (string) The type of the field (e.g., 'text', 'checkbox').
 #   - data-formatter: (string) The formatter for the column (e.g., 'boolean').
 #   - data-width: (string) The width of the column (e.g., '6em').
 #   - data-identifier: (boolean) XXX: Unknown what this does.
 #   - data-sortable: (boolean) Weather data in this column is sortable.
 #
 # Example Usage:
 # {{ partial("layout_partials/bootgrid_table", {
 #     'table_id': 'exampleGrid',
 #     'edit_dialog': 'DialogExample',
 #     'edit_alert': 'ConfigurationChangeMessage',
 #     'add_button_id': 'addExampleBtn',
 #     'fields': formDialogExample
 # }) }}
 #}

<div style="display: block;">
    <table id="{{ table_id }}" class="table table-condensed table-hover table-striped"
           data-editDialog="{{ edit_dialog }}" data-editAlert="{{ edit_alert }}">
        <thead>
            <tr>
                {# Hardcoded 'uuid' column at the beginning #}
                <th
                    data-column-id="uuid"
                    data-type="string"
                    data-identifier="true"
                    data-visible="false"
                >{{ lang._('ID') }}</th>

                {# Dynamic columns #}
                {% for field in fields %}
                    {% if field['data-column-id'] and field['column_visible'] == true %}
                        {% if field['data-column-id'] == 'enabled' %}
                            <th
                                data-column-id="{{ field['data-column-id'] }}"
                                data-width="6em"
                                data-type="boolean"
                                data-formatter="rowtoggle"
                            >{{ lang._(field['data-column-label']) }}</th>
                        {% else %}
                            <th
                                data-column-id="{{ field['data-column-id'] }}"
                                data-type="{{ field['data-type'] }}"
                                {% if field['data-visible'] == false %}data-visible="false"{% endif %}
                                {% if field['data-sortable'] == false %}data-sortable="false"{% endif %}
                                {% if field['data-identifier'] == true %}data-identifier="true"{% endif %}
                                {% if field['data-formatter'] %}data-formatter="{{ field['data-formatter'] }}"{% endif %}
                                {% if field['data-width'] %}data-width="{{ field['data-width'] }}"{% endif %}
                            >{{ lang._(field['data-column-label']) }}</th>
                        {% endif %}
                    {% endif %}
                {% endfor %}

                {# Hardcoded 'commands' column at the end #}
                <th
                    data-column-id="commands"
                    data-width="7em"
                    data-formatter="commands"
                    data-sortable="false"
                >{{ lang._('Commands') }}</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
        <tfoot>
            <tr>
                <td></td>
                <td>
                    <button id="{{ add_button_id }}" data-action="add" type="button" class="btn btn-xs btn-primary">
                        <span class="fa fa-plus"></span>
                    </button>
                    <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default">
                        <span class="fa fa-trash-o"></span>
                    </button>
                </td>
            </tr>
        </tfoot>
    </table>
</div>

