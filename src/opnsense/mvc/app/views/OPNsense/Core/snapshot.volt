<script>
    $(document).ready(function() {
        /* always hide "uuid" row in edit form */
        $("#row_uuid").hide();
        /* before rendering anything, check if ZFS is supported */
        ajaxGet('/api/core/snapshots/is_supported/', {}, function(data, status) {
            if (data && data.supported) {
                $("#{{formGridSnapshot['table_id']}}").UIBootgrid({
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
                                        $("#{{formGridSnapshot['table_id']}}").bootgrid('reload');
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
    {{ partial('layout_partials/base_bootgrid_table', formGridSnapshot + {'command_width': '135', 'hide_delete': true}) }}
</div>

<div id="unsupported_block" style="padding: 10px; display: none;">
    <div class="alert alert-warning" role="alert">
        <i class="fa fa-fw fa-warning"></i>
        {{ lang._('Snapshots are only available when a ZFS file system is used.') }}
        <br/>
        {{ lang._('For more information on how to migrate to ZFS, please refer to our documentation or support resources.') }}
      </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':SnapshotForm,'id':formGridSnapshot['edit_dialog_id'], 'label':lang._('Edit snapshot')])}}
