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
    'use strict';

    $( document ).ready(function() {
        /**
         * fetch system activity
         */
        function updateTop() {
            var gridopt = {
                ajax: false,
                selection: true,
                multiSelect: true,
            };
            if ($("#grid-top").hasClass('bootgrid-table')) {
                $("#grid-top").bootgrid('clear');
            } else {
                $("#grid-top")
                    .bootgrid(gridopt)
                    .on("loaded.rs.jquery.bootgrid", function (e) {
                        if ($('#grid-top tbody tr').length == 1 && $("#grid-top").bootgrid("getSearchPhrase") == '') {
                            $("#grid-top td").text("{{ lang._('Waiting for data...') }}");
                        }
                    });
            }
            ajaxGet("/api/diagnostics/activity/getActivity", {}, function (data, status) {
                        if (status == "success") {
                            let table = [];
                            $("#grid-top > tbody").html('');
                            $.each(data['details'], function (key, record) {
                                table.push(record);
                            });
                            $("#grid-top").bootgrid('append', table);
                            var header_txt = "";
                            $.each(data['headers'], function (key, value) {
                                header_txt += value;
                                header_txt += "<br/>";
                            });
                            $("#header_data").html(header_txt);
                            $('#header_data').fadeOut('slow');
                            $('#header_data_show').fadeIn('slow');
                        }
                    }
            );
        }


        // initial fetch
        updateTop();

        // hide show header info button
        $('#header_data_show').hide();
        $("#refresh").click(updateTop);

        // link show header info
        $("#header_data_show").click(function(){
            $('#header_data').fadeIn('slow');
            $('#header_data_show').hide();
        });

    });


</script>

<div class="content-box">
    <div class="content-box-main">
        <div class="table-responsive">
            <div  class="col-sm-12">
                <table id="grid-top" class="table table-condensed table-hover table-striped table-responsive">
                    <thead>
                    <tr>
                        <th data-column-id="THR" data-type="numeric" data-identifier="true" data-visible="false">{{ lang._('THR') }}</th>
                        <th data-column-id="PID" data-type="string">{{ lang._('PID') }}</th>
                        <th data-column-id="USERNAME" data-type="string" data-css-class="hidden-xs hidden-sm" data-header-css-class="hidden-xs hidden-sm">{{ lang._('USERNAME') }}</th>
                        <th data-column-id="PRI" data-type="string" data-css-class="hidden-xs hidden-sm" data-header-css-class="hidden-xs hidden-sm">{{ lang._('PRI') }}</th>
                        <th data-column-id="NICE" data-type="string" data-css-class="hidden-xs hidden-sm" data-header-css-class="hidden-xs hidden-sm">{{ lang._('NICE') }}</th>
                        <th data-column-id="SIZE" data-type="memsize" data-css-class="hidden-xs hidden-sm" data-header-css-class="hidden-xs hidden-sm">{{ lang._('SIZE') }}</th>
                        <th data-column-id="RES" data-type="memsize" data-css-class="hidden-xs hidden-sm" data-header-css-class="hidden-xs hidden-sm">{{ lang._('RES') }}</th>
                        <th data-column-id="STATE" data-type="string">{{ lang._('STATE') }}</th>
                        <th data-column-id="C" data-type="string" data-css-class="hidden-xs hidden-sm" data-header-css-class="hidden-xs hidden-sm">{{ lang._('C') }}</th>
                        <th data-column-id="TIME" data-type="string" data-css-class="hidden-xs hidden-sm" data-header-css-class="hidden-xs hidden-sm">{{ lang._('TIME') }}</th>
                        <th data-column-id="WCPU" data-type="string">{{ lang._('WCPU') }}</th>
                        <th data-column-id="COMMAND" data-type="string">{{ lang._('COMMAND') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    </tbody>
                    <tfoot>
                    </tfoot>
                </table>
            </div>
            <div  class="col-sm-12">
                <div class="row">
                    <div class="col-xs-12">
                        <div class="pull-left" data-toggle="popover">
                            <small id="header_data"></small>
                        </div>
                        <div class="pull-right">
                            <button id="header_data_show" class="btn btn-default"><i class="fa fa-info-circle"></i></button>
                            <button id="refresh" type="button" class="btn btn-default">
                                <span>{{ lang._('Refresh') }}</span>
                                <span class="fa fa-refresh"></span>
                            </button>
                        </div>
                    </div>
                </div>
                <hr/>
            </div>
        </div>
    </div>
</div>
