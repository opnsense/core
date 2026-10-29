<script>
    $( document ).ready(function() {
        $("#{{formGridTap['table_id']}}").UIBootgrid(
            {   search:'/api/interfaces/tap_settings/search_item/',
                get:'/api/interfaces/tap_settings/get_item/',
                set:'/api/interfaces/tap_settings/set_item/',
                add:'/api/interfaces/tap_settings/add_item/',
                del:'/api/interfaces/tap_settings/del_item/'
            }
        );
        $("#reconfigureAct").SimpleActionButton();
    });
</script>
<div class="tab-content content-box">
    {{ partial('layout_partials/base_bootgrid_table', formGridTap)}}
</div>
{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/interfaces/tap_settings/reconfigure'}) }}
{{ partial('layout_partials/base_dialog',['fields':formDialogTap,'id':formGridTap['edit_dialog_id'],'label':lang._('Edit TAP')])}}
