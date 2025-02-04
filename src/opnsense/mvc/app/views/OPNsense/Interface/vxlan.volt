<script>
    $( document ).ready(function() {
        $("#{{formGridVxlan['table_id']}}").UIBootgrid(
            {   search:'/api/interfaces/vxlan_settings/searchItem/',
                get:'/api/interfaces/vxlan_settings/getItem/',
                set:'/api/interfaces/vxlan_settings/setItem/',
                add:'/api/interfaces/vxlan_settings/addItem/',
                del:'/api/interfaces/vxlan_settings/delItem/'
            }
        );
        $("#reconfigureAct").SimpleActionButton();
    });
</script>
<div class="tab-content content-box">
    {{ partial('layout_partials/base_bootgrid_table', formGridVxlan)}}
</div>
<section class="page-content-main">
    <div class="content-box">
        <div class="col-md-12">
            <br/>
            <div id="VxlanChangeMessage" class="alert alert-info" style="display: none" role="alert">
                {{ lang._('After changing settings, please remember to apply them.') }}
            </div>
            <button class="btn btn-primary" id="reconfigureAct"
                    data-endpoint='/api/interfaces/vxlan_settings/reconfigure'
                    data-label="{{ lang._('Apply') }}"
                    data-error-title="{{ lang._('Error reconfiguring vxlan') }}"
                    type="button"
            ></button>
            <br/><br/>
        </div>
    </div>
</section>


{{ partial("layout_partials/base_dialog",['fields':formDialogVxlan,'id':formGridVxlan['edit_dialog_id'],'label':lang._('Edit VxLan')])}}
