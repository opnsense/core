<script>
    $( document ).ready(function() {
        $("#{{formGridVlan['table_id']}}").UIBootgrid(
            {   search:'/api/interfaces/vlan_settings/search_item/',
                get:'/api/interfaces/vlan_settings/get_item/',
                set:'/api/interfaces/vlan_settings/set_item/',
                add:'/api/interfaces/vlan_settings/add_item/',
                del:'/api/interfaces/vlan_settings/del_item/'
            }
        );
        $("#reconfigureAct").SimpleActionButton();
    });
</script>
<div class="tab-content content-box">
    {{ partial('layout_partials/base_bootgrid_table', formGridVlan)}}
</div>
{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/interfaces/vlan_settings/reconfigure'}) }}
{{ partial('layout_partials/base_dialog',['fields':formDialogVlan,'id':formGridVlan['edit_dialog_id'],'label':lang._('Edit Vlan')])}}
