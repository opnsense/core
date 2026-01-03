<script>
    $( document ).ready(function() {
        $("#{{formGridLoopback['table_id']}}").UIBootgrid(
            {   search:'/api/interfaces/loopback_settings/search_item/',
                get:'/api/interfaces/loopback_settings/get_item/',
                set:'/api/interfaces/loopback_settings/set_item/',
                add:'/api/interfaces/loopback_settings/add_item/',
                del:'/api/interfaces/loopback_settings/del_item/',
                options: {
                    formatters: {
                        "loFormatter": function (column, row) {
                            return "lo" + row[column.id];
                        }
                    }
                },
            }
        );
        $("#reconfigureAct").SimpleActionButton();
    });
</script>
<div class="tab-content content-box">
    {{ partial('layout_partials/base_bootgrid_table', formGridLoopback)}}
</div>
{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/interfaces/loopback_settings/reconfigure'}) }}
{{ partial('layout_partials/base_dialog',['fields':formDialogLoopback,'id':formGridLoopback['edit_dialog_id'],'label':lang._('Edit Loopback')])}}
