<script>
    $( document ).ready(function() {
        $("#{{formGridVlan['table_id']}}").UIBootgrid(
            {   search:'/api/interfaces/vlan_settings/searchItem/',
                get:'/api/interfaces/vlan_settings/getItem/',
                set:'/api/interfaces/vlan_settings/setItem/',
                add:'/api/interfaces/vlan_settings/addItem/',
                del:'/api/interfaces/vlan_settings/delItem/'
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
