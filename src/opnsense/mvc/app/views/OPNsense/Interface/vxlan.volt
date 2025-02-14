<script>
    $( document ).ready(function() {
        $("#{{formGridVxlan['table_id']}}").UIBootgrid(
            {   search:'/api/interfaces/vxlan_settings/searchItem/',
                get:'/api/interfaces/vxlan_settings/getItem/',
                set:'/api/interfaces/vxlan_settings/setItem/',
                add:'/api/interfaces/vxlan_settings/addItem/',
                del:'/api/interfaces/vxlan_settings/delItem/',
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
