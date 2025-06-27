<script>
    $( document ).ready(function() {
        $("#{{formGridPSK['table_id']}}").UIBootgrid(
            {   search:'/api/ipsec/pre_shared_keys/search_item/',
                get:'/api/ipsec/pre_shared_keys/get_item/',
                set:'/api/ipsec/pre_shared_keys/set_item/',
                add:'/api/ipsec/pre_shared_keys/add_item/',
                del:'/api/ipsec/pre_shared_keys/del_item/'
            }
        );
        updateServiceControlUI('ipsec');
        $("#reconfigureAct").SimpleActionButton();
    });
</script>
<div class="tab-content content-box">
    {{ partial('layout_partials/base_bootgrid_table', formGridPSK)}}
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/ipsec/service/reconfigure'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogPSK,'id':formGridPSK['edit_dialog_id'],'label':lang._('Edit pre-shared-key')])}}
