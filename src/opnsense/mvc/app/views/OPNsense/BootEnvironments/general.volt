<script>
    $(document).ready(function() {
        // load initial data

        let grid_env = $("#grid-env").UIBootgrid({
            get: '/api/bootenvironments/general/get/',
            set: '/api/bootenvironments/general/set/',
            add: '/api/bootenvironments/general/add/',
            del: '/api/bootenvironments/general/delBootEnv/',
            search: '/api/bootenvironments/general/search',
            commands: {
                activate_be: {
                    method: function(event) {
                        let uuid = $(this).data("row-id") !== undefined ? $(this).data("row-id") : '';
                        ajaxCall('/api/bootenvironments/general/activate/' + uuid, {},  function(data, status){
                            if (data.result) {
                                BootstrapDialog.show({
                                    title: "{{ lang._('Activation successful') }}",
                                    type:BootstrapDialog.TYPE_INFO,
                                    message: $("<div/>").text(data.result).html(),
                                    cssClass: 'monospace-dialog',
                                    buttons: [{
                                        label: "{{ lang._('OK') }}",
                                        cssClass: 'btn-primary',
                                        action: function(dialog) {
                                            dialog.close();
                                        }
                                    }]
                                });
                                $('#grid-env').bootgrid('reload');
                            }
                        });
                    },
                    classname: 'fa fa-fw fa-check',
                    title: "{{ lang._('activate boot environment') }}",
                    sequence: 10
                },
            },
            options: {
                selection: false,
                multiSelect: false,
                rowSelect: false,
                formatters: {
                    "commands": function (column, row) {
                        let rowId = row.uuid;
                        let elements = '<div class="break">'
                        + '<button type="button" class="btn btn-xs btn-default command-activate_be bootgrid-tooltip" data-row-id="' + rowId + '" title="Activate"><span class="fa fa-fw fa-check"></span></button>'
                        + '<button type="button" class="btn btn-xs btn-default command-edit bootgrid-tooltip" data-row-id="' + rowId + '"><span class="fa fa-fw fa-pencil"></span></button>'
                        + '<button type="button" class="btn btn-xs btn-default command-copy bootgrid-tooltip" data-row-id="' + rowId + '"><span class="fa fa-fw fa-clone"></span></button>';
                        if (!row.virtual) {
                            elements += '<button type="button" class="btn btn-xs btn-default command-delete bootgrid-tooltip" data-row-id="' + rowId + '"><span class="fa fa-fw fa-trash-o"></span></button>';
                        }
                        return elements + '</div>';
                    }
                }
            }
        });

        $('#newBootEnvironment').SimpleActionButton({
            onAction: function(data, status) {
                $('#grid-env').bootgrid('reload');
            }
        });
    });
</script>
<h1>Boot Environments</h1>

<ul id="maintabs" class="nav nav-tabs" data-tabs="tabs">
    <li id="beGeneral" class="active">
        <a data-toggle="tab" href="#tab_general">{{ lang._('General') }}</a>
    </li>
</ul>

<div class="tab-content content-box">
    <div id="tab_general" class="tab-pane fade in active">
        <table id="grid-env" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="frmGeneral">
            <thead>
                <tr>
                    <th data-column-id="uuid" data-type="string" data-visible="false" data-identifier="true" data-sortable="false">{{ lang._('uuid') }}</th>
                    <th data-column-id="name" data-type="string" data-visible="true" data-identifier="false" data-sortable="false">{{ lang._('Name') }}</th>
                    <th data-column-id="active" data-type="string" data-visible="true" data-sortable="false">{{ lang._('Active') }}</th>
                    <th data-column-id="mountpoint" data-type="string" data-visible="true" data-sortable="false">{{ lang._('Mountpoint') }}</th>
                    <th data-column-id="size" data-type="string" data-visible="true" data-sortable="false">{{ lang._('Size') }}</th>
                    <th data-column-id="created" data-type="string" data-visible="true" data-sortable="false">{{ lang._('Created') }}</th>
                    <th data-column-id="commands" data-width="9em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6"></td>
                    <td>
                        <button data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-fw fa-plus"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<section class="page-content-main">
    <div class="content-box">
        <button class="btn btn-primary __mt __mb __ml" id="newBootEnvironment"
            data-endpoint='/api/bootenvironments/general/addBootEnv/'
            data-label="{{ lang._('Quick Create') }}"
            data-error-title="{{ lang._('Error creating boot environment.') }}"
            type="button"
        ></button>
    </div>
</section>

{{ partial("layout_partials/base_dialog",['fields':generalForm,'id':'frmGeneral', 'label':lang._('Edit boot environment')])}}
