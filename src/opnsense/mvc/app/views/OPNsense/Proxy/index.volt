<script type="text/javascript">

    $( document ).ready(function() {
        // load initial data
        ajaxGet(url="/api/proxy/settings/get",sendData={},callback=function(data,status) {
            if (status == "success") {
                setFormData('frm_general',data);
            }
        });

        // form event handlers
        $("#save").click(function(){
            data = getFormData("frm_general");
            ajaxCall(url="/api/proxy/settings/set",sendData=data,callback=function(data,status){
                if ( status == "success") {
                    handleFormValidation("frm_general",data['validations']);
                    if (data['validations'] != undefined) {
                        BootstrapDialog.show({
                            type:BootstrapDialog.TYPE_WARNING,
                            title: 'Input validation',
                            message: 'Please correct validation errors in form'
                        });
                    }
                }
                // TODO: implement error handling
                //alert(status);

            });


        });

    });


</script>

<ul class="nav nav-tabs nav-justified" role="tablist"  id="maintabs">
    <li class="active"><a data-toggle="tab" href="#tabGeneral">General</a></li>
    <li><a data-toggle="tab" href="#sectionB">Section B</a></li>
</ul>
<div class="content-box tab-content">
    <div id="tabGeneral" class="tab-pane fade in active">
        <form id="frm_general" class="form-inline">
            <table class="table table-striped">
                <colgroup>
                    <col class="col-md-3">
                    <col class="col-md-4">
                    <col class="col-md-5">
                </colgroup>
                <tbody>
                    {{ partial("layout_partials/form_input_tr",
                        ['id': 'general.enabled',
                         'label':'enabled',
                         'type':'checkbox',
                         'help':'gfvjhgghfh'
                        ])
                    }}
                    {{ partial("layout_partials/form_input_tr", ['id': 'general.port','label':'test','type':'text']) }}

                    <tr>
                        <td colspan="3"><button class="btn btn-primary"  id="save" type="button">Save</button></td>
                    </tr>
                </tbody>
            </table>
        </form>
    </div>
    <div id="sectionB" class="tab-pane fade">

    </div>
</div>
