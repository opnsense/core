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
<section class="page-content-main">
    <div class="content-box">
        <div class="col-md-12">
            <br/>
            <div id="VlanChangeMessage" class="alert alert-info" style="display: none" role="alert">
                {{ lang._('After changing settings, please remember to apply them.') }}
            </div>
            <button class="btn btn-primary" id="reconfigureAct"
                    data-endpoint='/api/interfaces/vlan_settings/reconfigure'
                    data-label="{{ lang._('Apply') }}"
                    data-error-title="{{ lang._('Error reconfiguring vlan') }}"
                    type="button"
            ></button>
            <br/><br/>
        </div>
    </div>
</section>


{{ partial("layout_partials/base_dialog",['fields':formDialogVlan,'id':formGridVlan['edit_dialog_id'],'label':lang._('Edit Vlan')])}}
