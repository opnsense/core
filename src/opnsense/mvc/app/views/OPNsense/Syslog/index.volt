
<script type="text/javascript">
    $( document ).ready(function() {

    	console.log("document ready");

        /*************************************************************************************************************
         * link general actions
         *************************************************************************************************************/

    	var data_get_map = {'GeneralSettings':"/api/syslog/settings/get"};
        mapDataToFormUI(data_get_map).done(function(data){
        	console.log("main form data mapped");
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

        /*************************************************************************************************************
         * link grid actions
         *************************************************************************************************************/

        console.log("init grid");
        $("#grid-sources").UIBootgrid(
            {   search:'/api/syslog/settings/searchSources',
                toggle:'/api/syslog/settings/toggleSourceRemote/',
                options:{rowCount:-1},
            }
        );
		
        //$("#grid-sources").bootgrid("getColumnSettings")[0].formatter = function(column, row){
        //    if (row.Predefined == 1) {
        //        return "<span class=\"glyphicon glyphicon-cog text-warning\"/>"
        //    } else {
        //        return "";
        //    }
        //};    

        // hide table header & pager
		$("#grid-sources-header").hide();
		$("#grid-sources-footer").hide();

		console.log("Script done");
    });
</script>

<div class="alert alert-info hidden" role="alert" id="responseMsg">
</div>

<div class="tab-content content-box">
	<div class="col-md-12">

		{{ partial("layout_partials/base_form", ['fields':mainForm, 'id':'GeneralSettings']) }}

		<div id="sources">
			<h2></h2>
		    <table id="grid-sources" class="table table-condensed table-hover table-striped table-responsive">
		        <thead>
		        <tr>
		            <th data-column-id="Description" data-width="12em" data-type="string">{{ lang._('Log events source') }}</th>
		            <th data-column-id="RemoteLog" data-width="32em" data-type="string" data-align="left" data-formatter="rowtoggle">{{ lang._('Remote logging') }}</th>
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
	</div>

</div>
{# include dialogs #}