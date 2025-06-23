<script>
    $( document ).ready(function() {
        $("#{{formGridVxlan['table_id']}}").UIBootgrid(
            {   search:'/api/interfaces/vxlan_settings/search_item/',
                get:'/api/interfaces/vxlan_settings/get_item/',
                set:'/api/interfaces/vxlan_settings/set_item/',
                add:'/api/interfaces/vxlan_settings/add_item/',
                del:'/api/interfaces/vxlan_settings/del_item/',
                options: {
                    formatters: {
                        "vxlanFormatter": function (column, row) {
                            return "vxlan" + row[column.id];
                        }
                    }
                },
            }
        );
        $("#reconfigureAct").SimpleActionButton();
    });
</script>
<div class="tab-content content-box">
    {{ partial('layout_partials/base_bootgrid_table', formGridVxlan)}}
</div>
{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/interfaces/vxlan_settings/reconfigure'}) }}
{{ partial('layout_partials/base_dialog',['fields':formDialogVxlan,'id':formGridVxlan['edit_dialog_id'],'label':lang._('Edit VxLan')])}}
