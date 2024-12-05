<script>
    $(document).ready(function() {
        /* always hide "uuid" row in edit form */
        $("#row_uuid").hide();
        /* before rendering anything, check if ZFS is supported */
        ajaxGet('/api/core/snapshots/is_supported/', {}, function(data, status) {
            if (data && data.supported) {
                $("#grid-env").UIBootgrid({
                    get: '/api/core/snapshots/get/',
                    set: '/api/core/snapshots/set/',
                    add: '/api/core/snapshots/add/',
                    del: '/api/core/snapshots/del/',
                    search: '/api/core/snapshots/search',
                    commands: {
                        activate_be: {
                            method: function(event) {
                                let uuid = $(this).data("row-id") !== undefined ? $(this).data("row-id") : '';
                                ajaxCall('/api/core/snapshots/activate/' + uuid, {},  function(data, status){
                                    if (data.result) {
                                        $('#grid-env').bootgrid('reload');
                                    }
                                });
                            },
                            classname: 'fa fa-fw fa-check',
                            title: "{{ lang._('Activate') }}",
                            sequence: 10
                        },
                    },
                    options: {
                        selection: false,
                        multiSelect: false,
                        rowSelect: false,
                        formatters: {
                            "timestamp": function (column, row) {
                                return moment.unix(row[column.id]).local().format('YYYY-MM-DD HH:mm');
                            }
                        }
                    }
                });

                $("#supported_block").show();
            } else {
                $("#unsupported_block").show();
            }
        });
    });
</script>

<div id="supported_block" class="content-box" style="display: none;">
    <table id="grid-env" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="frmSnapshot">
        <thead>
            <tr>
                <th data-column-id="uuid" data-type="string" data-visible="false" data-identifier="true" data-sortable="false">{{ lang._('uuid') }}</th>
                <th data-column-id="name" data-type="string" data-visible="true" data-identifier="false">{{ lang._('Name') }}</th>
                <th data-column-id="active" data-type="string" data-visible="true" data-sortable="false">{{ lang._('Active') }}</th>
                <th data-column-id="mountpoint" data-type="string" data-visible="true">{{ lang._('Mountpoint') }}</th>
                <th data-column-id="size" data-type="string" data-visible="true" data-sortable="false">{{ lang._('Size') }}</th>
                <th data-column-id="created" data-type="string" data-formatter="timestamp">{{ lang._('Created') }}</th>
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

<div id="unsupported_block" style="padding: 10px; display: none;">
    <div class="alert alert-warning" role="alert">
        <i class="fa fa-fw fa-warning"></i>
        {{ lang._('Snapshots are only available when a ZFS file system is used.') }}
        <br/>
        {{ lang._('For more information on how to migrate to ZFS, please refer to our documentation or support resources.') }}
      </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':SnapshotForm,'id':'frmSnapshot', 'label':lang._('Edit snapshot')])}}
