<script>
    $( document ).ready(function() {
        $("#{{formGridLoopback['table_id']}}").UIBootgrid(
            {   search:'/api/interfaces/loopback_settings/searchItem/',
                get:'/api/interfaces/loopback_settings/getItem/',
                set:'/api/interfaces/loopback_settings/setItem/',
                add:'/api/interfaces/loopback_settings/addItem/',
                del:'/api/interfaces/loopback_settings/delItem/'
            }
        );
        $("#reconfigureAct").SimpleActionButton();
    });
</script>
<div class="tab-content content-box">
    {{ partial('layout_partials/base_bootgrid_table', formGridLoopback)}}
</div>
<section class="page-content-main">
    <div class="content-box">
        <div class="col-md-12">
            <br/>
            <div id="loopbackChangeMessage" class="alert alert-info" style="display: none" role="alert">
                {{ lang._('After changing settings, please remember to apply them.') }}
            </div>
            <button class="btn btn-primary" id="reconfigureAct"
                    data-endpoint='/api/interfaces/loopback_settings/reconfigure'
                    data-label="{{ lang._('Apply') }}"
                    data-error-title="{{ lang._('Error reconfiguring loopbacks') }}"
                    type="button"
            ></button>
            <br/><br/>
        </div>
    </div>
</section>


{{ partial("layout_partials/base_dialog",['fields':formDialogLoopback,'id':formGridLoopback['edit_dialog_id'],'label':lang._('Edit Loopback')])}}
