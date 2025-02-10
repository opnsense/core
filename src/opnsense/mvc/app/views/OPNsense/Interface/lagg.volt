<script>
    $( document ).ready(function() {
        $("#{{formGridLagg['table_id']}}").UIBootgrid(
            {   search:'/api/interfaces/lagg_settings/searchItem',
                get:'/api/interfaces/lagg_settings/getItem/',
                set:'/api/interfaces/lagg_settings/setItem/',
                add:'/api/interfaces/lagg_settings/addItem/',
                del:'/api/interfaces/lagg_settings/delItem/',
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
