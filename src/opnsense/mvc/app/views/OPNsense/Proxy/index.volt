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
            saveFormToEndpoint(url="/api/proxy/settings/set",formid="frm_general",callback_ok=function(){
                // on correct save, perform reconfigure. set progress animation when reloading
                $("#frm_general_progress").addClass("fa fa-spinner fa-pulse");

                //
                ajaxCall(url="/api/proxy/service/reconfigure", sendData={}, callback=function(data,status){
                    // when done, disable progress animation.
                    $("#frm_general_progress").removeClass("fa fa-spinner fa-pulse");

                    if (status != "success" || data['status'] != 'ok' ) {
                        // fix error handling
                        BootstrapDialog.show({
                            type:BootstrapDialog.TYPE_WARNING,
                            title: 'Proxy',
                            message: JSON.stringify(data)
                        });
                    }
                });

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
            <table class="table table-striped table-condensed table-responsive">
                <colgroup>
                    <col class="col-md-3"/>
                    <col class="col-md-4"/>
                    <col class="col-md-5"/>
                </colgroup>
                <tbody>
                    {{ partial("layout_partials/form_input_tr",
                        ['id': 'general.enabled',
                         'label':'enabled',
                         'type':'checkbox',
                         'help':'test'
                        ])
                    }}
                    {{ partial("layout_partials/form_input_tr",
                        ['id': 'general.port',
                         'label':'port',
                         'type':'text'
                        ])
                    }}

                    <tr>
                        <td colspan="3"><button class="btn btn-primary"  id="save" type="button">Apply <i id="frm_general_progress" class=""></i></button></td>
                    </tr>
                </tbody>
            </table>
        </form>
    </div>
    <div id="sectionB" class="tab-pane fade">

    </div>
</div>
