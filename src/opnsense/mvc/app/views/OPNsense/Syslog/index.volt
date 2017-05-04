
<script type="text/javascript">
    $( document ).ready(function() {

        /*************************************************************************************************************
         * link general actions
         *************************************************************************************************************/

    	var data_get_map = {'syslog':"/api/syslog/settings/get"};
        mapDataToFormUI(data_get_map).done(function(data){
            $('.selectpicker').selectpicker('refresh');
            formatTokenizersUI();

            if($("#syslog\\.Remote\\.LogAll").is(':checked'))
                $("#categories").hide();
            else
                $("#categories").show();
           	
        });

        // link save button to API set action
        $("#applyAct").click(function(){
            $("#responseMsg").removeClass("hidden");
            saveFormToEndpoint(url="/api/syslog/settings/set",formid='syslog-general',callback_ok=function(){

                saveFormToEndpoint(url="/api/syslog/settings/set",formid='syslog-remote',callback_ok=function(){

                    $("#responseMsg").html('{{lang._("The changes have been applied successfully.")}}');

                    ajaxCall(url="/api/syslog/service/reload", sendData={},callback=function(data,status) {

                        $("#responseMsg").html($("#responseMsg").html() + '<br/>' + data['message']);

                    });
                });
            });
        });

        $("#clearAct").click(function(){
            $("#responseMsg").removeClass("hidden");
            ajaxCall(url="/api/syslog/service/resetLogFiles", sendData={},callback=function(data,status) {

                // action to run after reload
                $("#responseMsg").html(data['message']);

            });
        });

        /*************************************************************************************************************
         * link grid actions
         *************************************************************************************************************/

        console.log("init grid");
        $("#grid-categories").UIBootgrid(
            {   search:'/api/syslog/settings/searchCategories',
                toggle:'/api/syslog/settings/toggleCategoryRemote/',
                options:{rowCount:-1},
            }
        );
		
        // hide table header & pager
		$("#grid-categories-header").hide();
		$("#grid-categories-footer").hide();

		$("#syslog\\.Remote\\.LogAll").change(function(){
			if($(this).is(':checked'))
				$("#categories").hide();
			else
				$("#categories").show();
		});
    });
</script>

<div class="alert alert-info hidden" role="alert" id="responseMsg">
</div>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#general">{{ lang._('General') }}</a></li>
    <li><a data-toggle="tab" href="#remote">{{ lang._('Remote') }}</a></li>
</ul>

<div class="tab-content content-box">
    <div id="general" class="tab-pane fade in active">
	    <div class="col-md-12">
    		{{ partial("layout_partials/base_form", ['fields':mainForm['tabs'][0][2], 'id':'syslog-general']) }}
        </div>
    </div>
    <div id="remote" class="tab-pane fade">
        <div class="col-md-12">
            {{ partial("layout_partials/base_form", ['fields':mainForm['tabs'][1][2], 'id':'syslog-remote']) }}
    		<div id="categories">
    			<h2></h2>
    		    <table id="grid-categories" class="table table-condensed table-hover table-striped table-responsive">
    		        <thead>
    		        <tr>
    		            <th data-column-id="Description" data-width="12em" data-type="string">{{ lang._('Log events source') }}</th>
    		            <th data-column-id="LogRemote" data-width="32em" data-type="string" data-align="left" data-formatter="rowtoggle">{{ lang._('Remote logging') }}</th>
    		        </tr>
    		        </thead>
    		        <tbody>
    		        </tbody>
    		        <tfoot>
    		        </tfoot>
    		    </table>
    		</div>
            <hr/>
            <p>{{lang._('Syslog sends UDP datagrams to port 514 on the specified remote syslog server, unless another port is specified. Be sure to set syslogd on the remote server to accept remote syslog messages.')}}
            </p>
        </div>
    </div>
</div>

<br/>
<button class="btn btn-primary"  id="applyAct" type="button"><b>{{ lang._('Apply') }}</b></button>
<button class="btn btn-primary"  id="clearAct" type="button"><b>{{ lang._('Reset Log Files') }}</b></button>

{# include dialogs #}
