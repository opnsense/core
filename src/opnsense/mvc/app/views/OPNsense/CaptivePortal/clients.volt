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
<script src="/ui/js/moment-with-locales.min.js" type="text/javascript"></script>

<script type="text/javascript">

    $( document ).ready(function() {
        /**
         * update zone list
         */
        function updateZones() {
            ajaxGet(url="/api/captiveportal/session/zones/", sendData={}, callback=function(data, status) {
                if (status == "success") {
                    $('#cp-zones').html("");
                    $.each(data, function(key, value) {
                        $('#cp-zones').append($("<option></option>").attr("value", key).text(value));
                    });
                    $('.selectpicker').selectpicker('refresh');
                    // link on change event
                    $('#cp-zones').on('change', function(){
                        loadSessions();
                    });
                    // initial load sessions
                    loadSessions();
                }
            });
        }

        /**
         * load sessions for selected zone, hook events
         */
        function loadSessions() {
            var zoneid = $('#cp-zones').find("option:selected").val();
            var gridopt = {
                ajax: false,
                selection: true,
                multiSelect: true,
                formatters: {
                    "commands": function (column, row) {
                        return  "<button type=\"button\" class=\"btn btn-xs btn-default command-disconnect\" data-row-id=\"" + row.sessionid + "\"><span class=\"fa fa-trash-o\"></span></button>";
                    }
                },
                converters: {
                    // convert datetime type fields from unix timestamp to readable format
                    datetime: {
                        from: function (value) { return moment(parseInt(value)*1000); },
                        to: function (value) { return value.format("lll"); }
                    }
                }
            };
            $("#grid-clients").bootgrid('destroy');
            ajaxGet(url="/api/captiveportal/session/list/"+zoneid+"/", sendData={}, callback=function(data, status) {
                if (status == "success") {
                    $("#grid-clients > tbody").html('');
                    $.each(data, function(key, value) {
                        var fields = ["sessionId", "userName", "macAddress", "ipAddress", "startTime"];
                        tr_str = '<tr>';
                        for (var i = 0; i < fields.length; i++) {
                            if (value[fields[i]] != null) {
                                tr_str += '<td>' + value[fields[i]] + '</td>';
                            } else {
                                tr_str += '<td></td>';
                            }
                        }
                        tr_str += '</tr>';
                        $("#grid-clients > tbody").append(tr_str);
                    });
                    // hook disconnect button
                    var grid_clients = $("#grid-clients").bootgrid(gridopt);
                    grid_clients.on("loaded.rs.jquery.bootgrid", function(){
                        grid_clients.find(".command-disconnect").on("click", function(e) {
                            var sessionId=$(this).data("row-id");
                            stdDialogRemoveItem('{{ lang._('Disconnect selected client?') }}',function() {
                                ajaxCall(url="/api/captiveportal/session/disconnect/" + zoneid + '/',
                                        sendData={'sessionId': sessionId}, callback=function(data,status){
                                            // reload grid after delete
                                            loadSessions();
                                        });
                            });
                        });
                    });
                    // hide actionBar on mobile
                    $('.actionBar').addClass('hidden-xs hidden-sm');
                }
            });
        }

        // init with first selected zone
        updateZones();
    });
</script>

<div class="content-box">
    <div class="content-box-main">
        <div class="table-responsive">
            <div class="col-sm-12">
                <div class="pull-right">
                    <select id="cp-zones" class="selectpicker" data-width="200px"></select>
                    <hr/>
                </div>
            </div>
            <div>
            <table id="grid-clients" class="table table-condensed table-hover table-striped table-responsive">
                <thead>
                <tr>
                    <th data-column-id="sessionid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('Session') }}</th>
                    <th data-column-id="userName" data-type="string">{{ lang._('userName') }}</th>
                    <th data-column-id="macAddress" data-type="string" data-css-class="hidden-xs hidden-sm"  data-header-css-class="hidden-xs hidden-sm">{{ lang._('macAddress') }}</th>
                    <th data-column-id="ipAddress" data-type="string" data-css-class="hidden-xs hidden-sm"  data-header-css-class="hidden-xs hidden-sm">{{ lang._('ipAddress') }}</th>
                    <th data-column-id="startTime" data-type="datetime">{{ lang._('connected since') }}</th>
                    <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false"></th>
                </tr>
                </thead>
                <tbody>
                </tbody>
                <tfoot>
                </tfoot>
            </table>
            </div>
        </div>
    </div>
</div>
