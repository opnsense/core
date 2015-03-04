
<div>
    <ul class="nav nav-tabs" id="myTab">
        <li><a data-toggle="tab" href="#sectionA">Section A</a></li>
        <li><a data-toggle="tab" href="#sectionB">Section B</a></li>
        <li class="dropdown">
            <a data-toggle="dropdown" class="dropdown-toggle" href="#">Dropdown <b class="caret"></b></a>
            <ul class="dropdown-menu">
                <li><a data-toggle="tab" href="#dropdown1">Dropdown1</a></li>
                <li><a data-toggle="tab" href="#dropdown2">Dropdown2</a></li>
            </ul>
        </li>
    </ul>
    <div class="tab-content">
        <div id="sectionA" class="tab-pane fade in active">
            <form id="frm_general">
                {{ partial("layout_partials/form_input", ['id': 'general.port','label':'test']) }}
                <div class="checkbox">
                    <label>
                        <input type="checkbox" id="general.enabled"> check
                    </label>
                </div>

            </form>
        </div>
        <div id="sectionB" class="tab-pane fade">

        </div>
        <div id="dropdown1" class="tab-pane fade">

        </div>
        <div id="dropdown2" class="tab-pane fade">

        </div>
    </div>
</div>


<br/>
<button class="btn btn-default"  id="save" type="button">save</button>

<script type="text/javascript">

    $( document ).ready(function() {
        ajaxGet(url="/api/proxy/settings/get",sendData={},callback=function(data,status) {
            if (status == "success") {
                setFormData('frm_general',data);
            }
        });
    });

    $("#save").click(function(){
        data = getFormData("frm_general");
        ajaxCall(url="/api/proxy/settings/set",sendData=data,callback=function(data,status){
            if ( status == "success") {
                handleFormValidation("frm_general",data['validations']);
            }
            // TODO: implement error handling
            //alert(status);

        });


    });


</script>
