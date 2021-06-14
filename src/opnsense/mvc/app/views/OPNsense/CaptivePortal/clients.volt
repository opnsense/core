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
<script src="{{ cache_safe('/ui/js/moment-with-locales.min.js') }}"></script>

<script>

    $( document ).ready(function() {
        /**
         * update zone list
         */
        function updateZones() {
            ajaxGet("/api/captiveportal/session/zones/", {}, function(data, status) {
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
                        return  "<button type=\"button\" class=\"btn btn-xs btn-default command-disconnect\" data-row-id=\"" + row.sessionId + "\"><span class=\"fa fa-trash-o\"></span></button>";
                    }
                }
            };
            if ($("#grid-clients").hasClass('bootgrid-table')) {
                $("#grid-clients").bootgrid('clear');
            } else {
                let grid_clients = $("#grid-clients").bootgrid(gridopt).on("loaded.rs.jquery.bootgrid", function(){
                    // hook disconnect button
                    grid_clients.find(".command-disconnect").on("click", function(e) {
                        var zoneid = $('#cp-zones').find("option:selected").val();
                        var sessionId=$(this).data("row-id");
                        stdDialogConfirm('{{ lang._('Confirm disconnect') }}',
                            '{{ lang._('Do you want to disconnect the selected client?') }}',
                            '{{ lang._('Yes') }}', '{{ lang._('Cancel') }}', function () {
                            ajaxCall("/api/captiveportal/session/disconnect/" + zoneid + '/',
                                  {'sessionId': sessionId}, function(data,status){
                                // reload grid after delete
                                loadSessions();
                            });
                        });
                    });
                });
            }
            ajaxGet("/api/captiveportal/session/list/"+zoneid+"/", {}, function(data, status) {
                if (status == "success") {
                    // format records (our bootgrid doesn't like null and expects moment for datetime)
                    let table = [];
                    for (var i = 0; i < data.length; i++) {
                        let record = {};
                        $.each(data[i], function(key, value) {
                            record[key] = value !== null  ? value : "";
                        });
                        record['startTime'] = moment(parseInt(record['startTime'])*1000);
                        table.push(record);
                    }
                    $("#grid-clients").bootgrid('append', table);
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
                    <th data-column-id="sessionId" data-type="string" data-identifier="true" data-visible="false">{{ lang._('Session') }}</th>
                    <th data-column-id="userName" data-type="string">{{ lang._('Username') }}</th>
                    <th data-column-id="macAddress" data-type="string" data-css-class="hidden-xs hidden-sm" data-header-css-class="hidden-xs hidden-sm">{{ lang._('MAC address') }}</th>
                    <th data-column-id="ipAddress" data-type="string" data-css-class="hidden-xs hidden-sm" data-header-css-class="hidden-xs hidden-sm">{{ lang._('IP address') }}</th>
                    <th data-column-id="startTime" data-type="datetime">{{ lang._('Connected since') }}</th>
                    <th data-column-id="commands" data-searchable="false" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
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
