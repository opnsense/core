<script>
    $( document ).ready(function() {
        $("#{{formGridLagg['table_id']}}").UIBootgrid(
            {   search:'/api/interfaces/lagg_settings/search_item',
                get:'/api/interfaces/lagg_settings/get_item/',
                set:'/api/interfaces/lagg_settings/set_item/',
                add:'/api/interfaces/lagg_settings/add_item/',
                del:'/api/interfaces/lagg_settings/del_item/',
                options: {
                    formatters: {
                        members: function (column, row) {
                            return row[column.id].replace(',', '<br/>');
                        }
                    }
                }
            }
        );

        $("#lagg\\.proto").change(function(){
            $(".proto").closest("tr").hide();
            $(".proto_"+$(this).val()).each(function(){
                $(this).closest("tr").show();
            });
        });

        $("#reconfigureAct").SimpleActionButton();
    });
</script>
<div class="tab-content content-box">
    {{ partial('layout_partials/base_bootgrid_table', formGridLagg)}}
</div>
{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/interfaces/lagg_settings/reconfigure'}) }}
{{ partial('layout_partials/base_dialog',['fields':formDialogEdit,'id':formGridLagg['edit_dialog_id'],'label':lang._('Edit Lagg')])}}
