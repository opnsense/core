{#

OPNsense® is Copyright © 2014 – 2015 by Deciso B.V.
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

1.  Redistributions of source code must retain the above copyright notice,
this list of conditions and the following disclaimer.

2.  Redistributions in binary form must reproduce the above copyright notice,
this list of conditions and the following disclaimer in the documentation
and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.

#}

<script type="text/javascript">

    $( document ).ready(function() {

        function addFilters(request) {
            var selected =$('#ruleclass').find("option:selected").val();
            if ( selected != "") {
                request['classtype'] = selected;
            }
            return request;
        }

        $("#grid-installedrules").UIBootgrid(
                {   search:'/api/ids/settings/searchinstalledrules',
                    get:'/api/ids/settings/getRuleInfo/',
                    set:'/api/ids/settings/setRuleInfo/',
                    options:{
                        multiSelect:false,
                        selection:false,
                        requestHandler:addFilters,
                        formatters:{
                            rowtoggle: function (column, row) {
                                if (parseInt(row[column.id], 2) == 1) {
                                    var toggle = "<span style=\"cursor: pointer;\" class=\"fa fa-check-square-o command-toggle\" data-value=\"1\" data-row-id=\"" + row.sid + "\"></span>";
                                } else {
                                    var toggle = "<span style=\"cursor: pointer;\" class=\"fa fa-square-o command-toggle\" data-value=\"0\" data-row-id=\"" + row.sid + "\"></span>";
                                }
                                toggle += " &nbsp; <button type=\"button\" class=\"btn btn-xs btn-default command-edit\" data-row-id=\"" + row.sid + "\"><span class=\"fa fa-info-circle\"></span></button> ";
                                return toggle;
                            }
                        }
                    },
                    toggle:'/api/ids/settings/toggleRule/'
                }
        );



        // list all known classtypes and add to selection box
        ajaxGet(url="/api/ids/settings/listRuleClasstypes",sendData={}, callback=function(data, status) {
            if (status == "success") {
                $.each(data['items'], function(key, value) {
                    $('#ruleclass').append($("<option></option>").attr("value",value).text(value));
                });
                $('.selectpicker').selectpicker('refresh');
                // link on change event
                $('#ruleclass').on('change', function(){
                    $('#grid-installedrules').bootgrid('reload');
                });
            }
        });


    });


</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#rules">{{ lang._('Rules') }}</a></li>
    <li><a data-toggle="tab" href="#item2">{{ lang._('Item2') }}</a></li>
    <li><a data-toggle="tab" href="#item3">{{ lang._('Item3') }}</a></li>
</ul>
<div class="tab-content content-box tab-content">
    <div id="rules" class="tab-pane fade in active">
        <div class="bootgrid-header container-fluid">
            <div class="row">
                <div class="col-sm-12 actionBar">
                    <b>Classtype &nbsp;</b>
                    <select id="ruleclass" class="selectpicker" data-width="200px"><option value="">ALL</option></select>
                </div>
            </div>
        </div>

        <!-- tab page "installed rules" -->
        <table id="grid-installedrules" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogRule">
            <thead>
            <tr>
                <th data-column-id="sid" data-type="number" data-visible="true" data-identifier="true" >sid</th>
                <th data-column-id="source" data-type="string">Source</th>
                <th data-column-id="classtype" data-type="string">ClassType</th>
                <th data-column-id="msg" data-type="string">Message</th>
                <th data-column-id="enabled" data-formatter="rowtoggle" data-sortable="false">enabled / info</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
    <div id="item2" class="tab-pane fade in">

    </div>
    <div id="item3" class="tab-pane fade in">

    </div>
    <div class="col-md-12">
        <hr/>
        <button class="btn btn-primary"  id="reconfigureAct" type="button"><b>Apply</b><i id="reconfigureAct_progress" class=""></i></button>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogRule,'id':'DialogRule','label':'Rule details','hasSaveBtn':'false','msgzone_width':1])}}
