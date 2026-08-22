<script>
    $( document ).ready(function() {
        $("#{{formGridWireless['table_id']}}").UIBootgrid(
            {   search:'/api/interfaces/wireless_settings/search_item/',
                get:'/api/interfaces/wireless_settings/get_item/',
                set:'/api/interfaces/wireless_settings/set_item/',
                add:'/api/interfaces/wireless_settings/add_item/',
                del:'/api/interfaces/wireless_settings/del_item/'
            }
        );
        $("#reconfigureAct").SimpleActionButton();
    });
</script>
<div class="tab-content content-box">
    {{ partial('layout_partials/base_bootgrid_table', formGridWireless)}}
</div>
{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/interfaces/wireless_settings/reconfigure'}) }}
{{ partial('layout_partials/base_dialog',['fields':formDialogWireless,'id':formGridWireless['edit_dialog_id'],'label':lang._('Edit Wireless')])}}
