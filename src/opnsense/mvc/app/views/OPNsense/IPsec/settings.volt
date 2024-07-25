<script>
    $( document ).ready(function() {

        $('[id*="save_"]').each(function(){
            $(this).closest('tr').hide();
        });

        mapDataToFormUI({'mainform': '/api/ipsec/settings/get'}).done(function(){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
            updateServiceControlUI('ipsec');
            $("#reconfigureAct").SimpleActionButton({
                onPreAction: function() {
                    const dfObj = new $.Deferred();
                    saveFormToEndpoint("/api/ipsec/settings/set", 'mainform', function(){
                        dfObj.resolve();
                    });
                    return dfObj;
                }
            });
        });
    });

</script>

<ul class="nav nav-tabs" role="tablist" id="maintabs">
    {{ partial("layout_partials/base_tabs_header",['formData':formSettings]) }}
</ul>

<form id="mainform">
    <div class="content-box tab-content">
        {{ partial("layout_partials/base_tabs_content",['formData':formSettings]) }}
    </div>
</form>

<div class="content-box tab-content">
    <div class="col-md-12">
        <br/>
        <button class="btn btn-primary" id="reconfigureAct"
                data-endpoint='/api/ipsec/service/reconfigure'
                data-label="{{ lang._('Apply') }}"
                data-error-title="{{ lang._('Error reconfiguring IPsec') }}"
                type="button"
        ></button>
        <br/><br/>
    </div>
</div>
