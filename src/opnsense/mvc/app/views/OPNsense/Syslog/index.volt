
<script type="text/javascript">
    $( document ).ready(function() {

    	console.log("document ready");

        /*************************************************************************************************************
         * link general actions
         *************************************************************************************************************/

    	var data_get_map = {'GeneralSettings':"/api/syslog/settings/get"};
        mapDataToFormUI(data_get_map).done(function(data){
            $('.selectpicker').selectpicker('refresh');
            formatTokenizersUI();
        });

        // link save button to API set action
        $("#applyAct").click(function(){
            $("#responseMsg").removeClass("hidden");
            saveFormToEndpoint(url="/api/syslog/settings/set",formid='GeneralSettings',callback_ok=function(){

                // action to run after successful save, for example reconfigure service.
                ajaxCall(url="/api/syslog/service/reload", sendData={},callback=function(data,status) {

                    // action to run after reload
                    $("#responseMsg").html(data['message']);

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

<div class="tab-content content-box">
	<div class="col-md-12">

		{{ partial("layout_partials/base_form", ['fields':mainForm, 'id':'GeneralSettings']) }}

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
		<button class="btn btn-primary"  id="applyAct" type="button"><b>{{ lang._('Apply') }}</b></button>
		<button class="btn btn-primary"  id="clearAct" type="button"><b>{{ lang._('Reset Log Files') }}</b></button>
		<hr/>
		<p>{{lang._('Syslog sends UDP datagrams to port 514 on the specified remote syslog server, unless another port is specified. Be sure to set syslogd on the remote server to accept remote syslog messages.')}}
		</p>
	</div>

</div>
{# include dialogs #}