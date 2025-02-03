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
<section class="page-content-main">
    <div class="content-box">
        <div class="col-md-12">
            <br/>
            <button class="btn btn-primary" id="reconfigureAct"
                    data-endpoint='/api/interfaces/lagg_settings/reconfigure'
                    data-label="{{ lang._('Apply') }}"
                    data-error-title="{{ lang._('Error reconfiguring laggs') }}"
                    type="button"
            ></button>
            <br/><br/>
        </div>
    </div>
    <div id="laggChangeMessage" class="alert alert-info" style="display: none" role="alert">
        {{ lang._('After changing settings, please remember to apply them.') }}
    </div>
</section>


{{ partial("layout_partials/base_dialog",['fields':formDialogEdit,'id':formGridLagg['edit_dialog_id'],'label':lang._('Edit Lagg')])}}
