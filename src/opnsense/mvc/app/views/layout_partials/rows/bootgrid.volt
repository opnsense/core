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

{##
 # This is a partial for building the bootgrid HTML table row.
 #
 # This is called by base_form.volt for field types 'bootgrid'
 #
 # Expects to receive an array by the name of this_field.
 #
 # The following keys may be used in this partial:
 #
 # this_field.id             : target for this bootgrid
 # this_field['type']              : type of input or field. Valid types are:
 #           bootgrid                bootgrid field
 # this_field.api.add        : API for bootgrid to add entries
 # this_field.api.del        : API for bootgrid to delete entries
 # this_field.api.set        : API for bootgrid to set entries (edit/update)
 # this_field.api.get        : API for bootgrid to get entries (edit/read)
 # this_field.api.toggle     : API for bootgrid to toggle entries (enable/disable)
 # this_field.api.export     : API for bootgrid to export entries
 # this_field.api.import     : API for bootgrid to import entries
 # this_field.api.clear      : API for clearing log files from the bootgrid
 # this_field['columns']['column'] : array of columns for the bootgrid
 # this_field['dialog']            : array containing the fields for the dialog
 # this_field.label             : attribute label (visible text)
 # this_field.help              : help text
 # this_field['advanced']          : property "is advanced", only display in advanced mode
 # this_field.style             : css class to add
 #}
{%    set this_node_id = get_xml_prop(this_node, 'id', true) %}
{%    set this_node_label = get_xml_prop(this_node, 'label') %}
{#      Create a safe id derived from the bootgrid id, escaping periods. #}
{# XXX Macro this advanced/help section. #}
<tr id="row_{{ this_node_id }}"
    {{ this_node['advanced']|default(false) ? 'data-advanced="true"' : '' }}
>
    <td colspan="3">
{%      if this_node_label %}
        <div class="control-label" id="control_label_{{ this_node_id }}">
{%          if this_node.help %}
            <a
                id="help_for_{{ this_node_id }}"
                href="#"
                class="showhelp"
            >
                    <i class="fa fa-info-circle"></i>
            </a>
{%          elseif this_node.help|default(false) == false %}
            <i class="fa fa-info-circle text-muted"></i>
{%          endif %}
            <b>{{ lang._('%s')|format(this_node_label) }}</b>
        </div>
{%          if this_node.help|default(false) %}
        <div class="hidden" data-for="help_for_{{ this_node_id }}">
            <small>{{ lang._('%s')|format(this_node.help) }}</small>
        </div>
{%          endif %}
{%      endif %}
{# data-editDialog value must match button values on the edit dialog.
   The bootgrid plugin uses it like:
   $("#btn_"+editDlg+"_save").unbind('click');
   $("#"+editDlg).modal('hide');
   so this means that the dialog can't have
   a . (or other unsafe) in the name since
   it isn't escaped before using the var in a selector
   in opnsense_bootgrid_plugin.js #}
        <table id="bootgrid_{{ this_node_id }}"
               class="table
                      table-condensed
                      table-hover
                      table-striped
                      table-responsive
                      bootgrid-table"
               data-editDialog="{{ this_node.dialog|default(false) ? 'bootgrid_dialog_' ~ this_node_id : '' }}">
            <thead>
                <tr>
{%      if this_node.api.toggle %}
                    <th data-column-id="enabled"
                        data-width="0em"
                        data-type="string"
                        data-formatter="rowtoggle">
                        {{ lang._('%s')|format('Enabled') }}
                    </th>
{%      endif %}
{%      for column in this_node.columns.column %}
{%          set data_formatter = "" %}
                    <th
                        data-type="{{ column['type']|default('string') }}"
                        data-visible="{{ column['visible']|default('true') }}"
                        data-visible-in-selection="{{ column['visible-in-selection']|default('true') }}"
                        data-sortable="{{ column['sortable']|default('true') }}"
                        {{ (column['id']|default('') != '') ?
                            'data-column-id="'~column['id']~'"' : '' }}
                        {{ (column['size']|default('') != '') ?
                            'data-size="'~column['size']~'"' : '' }}
                        {{ (column['data-formatter']|default('') != '') ?
                            'data-formatter="'~column['data-formatter']~'"' : '' }}
                        {{ (column['width']|default('') != '') ?
                            'data-width="'~column['width']~'"' : '' }}
                        {{ (column['width']|default('') != '') ?
                            'width="'~column['width']~'"' : '' }}>
                        {{ lang._('%s')|format(column|default('')) }}
                    </th>
{%          if loop.last %}
                {% set last_row_index = loop.index0 %}
{%          endif %}
{%      endfor %}
{# Automatically add command column if all APIs are defined, use commandsWithInfo if info API is defined. #}
{%      if this_node.api.del and
           this_node.api.set and
           this_node.api.get and
           this_node.api.add %}
                    <th
                        data-column-id="commands"
                        data-formatter="{{ (this_node.api.info) ? 'commandsWithInfo' : 'commands' }}"
                        data-sortable="false"
                        data-width="{{ (this_node.api.info) ? '9em' : '7em' }}">
                        {{ lang._('%s')|format('Commands') }}
                    </th>
{%      endif %}
{# Column to house the UUID of each row in the table.
   Hidden from the user by default, but can be made visible in the column select box in the top right. #}
                    <th
                        data-column-id="uuid"
                        data-type="string"
                        data-identifier="true"
                        data-visible="false"
                        data-visible-in-selection="true">
                        {{ lang._('%s')|format('ID') }}
                    </th>
                </tr>
            </thead>
            <tbody
{# XXX other places this is usually the style field. #}
{%      if this_node.class|default('') != '' %}
{#              # This is for if another class is specified in the form data. #}
                class="{{ this_node.class }}"
{%      endif %}
{%      if this_node.style|default('') != '' %}
{#              # This is for if another style is specified in the form data. #}
                style="{{ this_node.style }}"
{%      endif %}
            ></tbody>
            <tfoot>
                <tr>{# Start a new row for our buttons. #}
{%      if this_node.api.add or
           this_node.api.del %}
{#  We use the index from the foreach loop above
    to put the commands one column after the last field. #}
                    <td colspan="{{ (last_row_index + 1) }}"></td>
                    <td>
{%          if this_node.api.add %}
                        <button
                            data-action="add"
                            type="button"
                            class="btn btn-xs btn-default"
                        >
                            <span class="fa fa-plus"></span>
                        </button>
{%          endif %}
{%          if this_node.api.del %}
                        <button
                            data-action="deleteSelected"
                            type="button"
                            class="btn btn-xs btn-default"
                        >
                            <span class="fa fa-trash-o"></span>
                        </button>
{%          endif %}
                    </td>
{%      endif %}
{%      if this_node.api.import or
           this_node.api.export %}
                </tr>
{# Close the previous row, and start a new one.
   This still looks good even when there isn't an add/del button. #}
                <tr>
{# We use the index from the foreach loop above
   to put the commands one column after the last field. #}
                    <td colspan="{{ (last_row_index + 1) }}"></td>
                    <td>
{%          if this_node.api.export %}
                        <button id="btn_bootgrid_{{ this_node_id }}_export"
                                data-toggle="tooltip"
                                title="{{ lang._('%s')|format('Download') }}"
                                type="button"
                                class="btn btn-md btn-default pull-right"
                                style="margin-left: 6px;">
                            <span class="fa fa-cloud-download"></span>
                        </button>
{%          endif %}
{%          if this_node.api.import %}
                        <button id="btn_bootgrid_{{ this_node_id }}_import"
                                data-toggle="tooltip"
                                title="{{ lang._('%s')|format('Upload') }}"
                                type="button"
                                class="btn btn-md btn-default pull-right">
                            <span class="fa fa-cloud-upload"></span>
                        </button>
{%          endif %}
                    </td>
{%      endif %}
                </tr>
            </tfoot>
        </table>
{%      if this_node.api.clear %}
        <div class="alert alert-info" role="alert" style="min-height: 65px;">
            <form method="post">
                <button type="button"
                        id="btn_bootgrid_{{ this_node_id }}_clear"
                        class="btn btn-danger pull-right"
                        >
                        <i class="fa fa-fw fa-trash-o"></i>
                        &nbsp{{ lang._('Clear Log') }}
                </button>
            </form>
            <div style="margin-top: 8px;">
                {{ lang._('This log can be cleared using the button on the right.') }}
            </div>
        </div>
{%      endif %}
    </td>
</tr>
<script>
{#/* =============================================================================
 # bootgrid: UIBootgrid attachments (API definition)
 # =============================================================================
 # Builds out the UIBootgrid attachments according to form definition
*/#}
{#/* These UIBootgrid calls must execute in document.ready()
     otherwise they will result in a 403 permission denied due to CSRF token missing. */#}
{% if this_node.api and this_node.api.children() %}
$( document ).ready(function() {
    $('#' + 'bootgrid_' + $.escapeSelector("{{ this_node_id }}")).UIBootgrid({
{%              for api in this_node.api.children()|default([]) %}
        '{{ api.getName() }}':'{{ api }}/{{ this_node_id }}/',
{%              endfor %}
        'options':{
            'selection':
{%-             if (this_node.builtin == 'logs') -%}
                    false
{%-             else -%}
                    {{- this_node.columns['selection']|default('false') }}
{%-             endif %}
{%              if this_node.row_count %},
            'rowCount':[{{ this_node.row_count }}]
{%              endif %}
{%              if this_node.grid_options %},
            {{- this_node.grid_options }}
{%              endif %}
        }
    });
});
{% endif %}
{#/*
 # Create an event hanlder for whenever a create/update/delete call is made to the bootgrid API.
     This isn't truly ideal but was the first successful method I've discovered.
*/#}
{%  if this_node.api.add or this_node.api.set or this_node.api.del  %}
    $(document).on("ajaxSuccess", function(event, jqxhr, settings) {

        if ((
{%      for api in this_node.xpath('api/*[self::add or self::set or self::del]')|default([]) %}
                settings.url.startsWith('{{ api }}/{{ this_node_id }}/'){% if not loop.last %} ||{%  endif %}

{%      endfor %}
            )) {
{#/*        Run the toggle for the apply changes pane. Won't show if config isn't dirty. */#}
            if (typeof toggleApplyChanges === "function") {
                toggleApplyChanges();
            }
        }
    });
{%  endif %}
{#/*
 # =============================================================================
 # bootgrid: import button
 # =============================================================================
 # Allows importing a list into a field
{# XXX This import function is probably not exclusive to bootgrids, but could be useful in other contexts. */#}
{%  if this_node.api.import %}
{%      set this_node_label = get_xml_prop(this_node, 'label', true) %}
{#/*
 # Mostly from the Firewall alias plugin
 #  Since base_dialog() only has buttons for Save, Close, and Cancel,
 #  we build our own dialog using some wrapper functions, and
 #  perform validation on the data to be imported. */#}
    $('#btn_bootgrid_' + $.escapeSelector("{{ this_node_id }}") + '_import').click(function(){
        let $msg = $("<div/>");
        let $imp_file = $("<input type='file' id='btn_bootgrid_{{ this_node_id }}_select' />");
        let $table = $("<table class='table table-condensed'/>");
        let $tbody = $("<tbody/>");
        $table.append(
          $("<thead/>").append(
            $("<tr>").append(
              $("<th/>").text('{{ lang._('Source') }}')
            ).append(
              $("<th/>").text('{{ lang._('Message') }}')
            )
          )
        );
        $table.append($tbody);
        $table.append(
          $("<tfoot/>").append(
            $("<tr/>").append($("<td colspan='2'/>").text(
              "{{ lang._('Errors were encountered, no records were imported.') }}"
            ))
          )
        );

        $imp_file.click(function(){
{#/*        # Make sure upload resets when new file is provided
            # (bug in some browsers) */#}
            this.value = null;
        });
        $msg.append($imp_file);
        $msg.append($("<hr/>"));
        $msg.append($table);
        $table.hide();
{#/*      # Show the dialog to the user for importing. */#}
        BootstrapDialog.show({
          title: "{{ lang._('Import %s')|format(this_node_label) }}",
          message: $msg,
          type: BootstrapDialog.TYPE_INFO,
          draggable: true,
          buttons: [{
              label: '<i class="fa fa-cloud-upload" aria-hidden="true"></i>',
              action: function(sender){
                  $table.hide();
                  $tbody.empty();
                  if ($imp_file[0].files[0] !== undefined) {
                      const reader = new FileReader();
                      reader.readAsBinaryString($imp_file[0].files[0]);
                      reader.onload = function(readerEvt) {
                          let import_data = null;
                          try {
                              import_data = JSON.parse(readerEvt.target.result);
                          } catch (error) {
                              $tbody.append(
                                $("<tr/>").append(
                                  $("<td>").text("*")
                                ).append(
                                  $("<td>").text(error)
                                )
                              );
                              $table.show();
                          }
                          if (import_data !== null) {
                              ajaxCall("{{ this_node.api.import }}", {'data': import_data,'target': '{{ this_node_id }}' }, function(data,status) {
                                  if (data.validations !== undefined) {
                                      Object.keys(data.validations).forEach(function(key) {
                                          $tbody.append(
                                            $("<tr/>").append(
                                              $("<td>").text(key)
                                            ).append(
                                              $("<td>").text(data.validations[key])
                                            )
                                          );
                                      });
                                      $table.show();
                                  } else {
                                      std_bootgrid_reload('bootgrid_{{ this_node_id }}')
                                      sender.close();
                                  }
                              });
                          }
                      }
                  }
              }
          },{
             label: "{{ lang._('Cancel') }}",
             action: function(sender){
                sender.close();
             }
           }]
        });
    });
{%  endif %}
{#/*
 # =============================================================================
 # bootgrid: export button
 # =============================================================================
 # Allows exporting a list out for external storage or manupulation
 #
 # Mostly came from the firewall plugin.
*/#}
{%  if this_node.api.export %}
    $("#btn_bootgrid_{{ this_node_id }}_export").click(function(){
{#      Make ajax call to URL. #}
        return $.ajax({
            type: 'GET',
            url: "{{ this_node.api.export }}",
            complete: function(data,status) {
                if (data) {
                    var output_data = '';
                    var ext = '';
                    try {
                        var response = jQuery.parseJSON(data);
                        output_data = JSON.stringify(data, null, 2);
                        ext = 'json';

                    } catch {
                        // Assume text
                        output_data = data['responseText'];
                        ext = 'txt';
                    }
                    let a_tag = $('<a></a>').attr('href','data:application/json;charset=utf8,' + encodeURIComponent(output_data))
                        .attr('download','{{ this_node_id }}_export.' + ext).appendTo('body');

                    a_tag.ready(function() {
                        if ( window.navigator.msSaveOrOpenBlob && window.Blob ) {
                            var blob = new Blob( [ output_data ], { type: "text/csv" } );
                            navigator.msSaveOrOpenBlob( blob, '{{ this_node_id }}_export.' + ext );
                        } else {
                            a_tag.get(0).click();
                        }
                    });
                }
            },
            data: { "target": "{{ this_node_id }}"}
        });
    });
{%  endif %}
{#/*
 #=============================================================================
 # bootgrid: clear button
 # =============================================================================
 # Allows clearing the log file that the bootgrid is displaying the contents of.
 #
*/#}
{%  if this_node.api.clear %}
    $("#btn_bootgrid_{{ this_node_id }}_clear").click(function(){
        event.preventDefault();
        BootstrapDialog.show({
            type: BootstrapDialog.TYPE_DANGER,
            title: "{{ lang._('Log') }}",
            message: "{{ lang._('Do you really want to flush this log?') }}",
            buttons: [{
                label: "{{ lang._('No') }}",
                action: function(dialogRef) {
                    dialogRef.close();
                }
            }, {
                label: "{{ lang._('Yes') }}",
                action: function(dialogRef) {
                    ajaxCall("{{ this_node.api.clear }}", {}, function(){
                        dialogRef.close();
                        $('#bootgrid_{{ this_node_id }}').bootgrid('reload');
                    });
                }
            }]
        });
    });
{%  endif %}
</script>
