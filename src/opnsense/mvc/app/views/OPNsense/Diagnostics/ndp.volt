{#

OPNsense® is Copyright © 2014 – 2016 by Deciso B.V.
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

<script>
    $( document ).ready(function() {
        /**
         * fetch system NDP table
         */
        function updateNDP() {
            if ($("#grid-ndp").hasClass('bootgrid-table')) {
                $("#grid-ndp").bootgrid('clear');
            } else {
                $("#grid-ndp").bootgrid({
                    ajax: false,
                    selection: false,
                    multiSelect: false
                });
            }
            ajaxGet("/api/diagnostics/interface/getNdp", {}, function (data, status) {
                        if (status == "success") {
                            $("#grid-ndp").bootgrid('append', data);
                        }
                    }
            );
        }

        // initial fetch
        $("#refresh").click(updateNDP);
        $("#refresh").click();
    });
</script>

<div class="content-box">
    <div class="content-box-main">
        <div class="table-responsive">
            <div  class="col-sm-12">
                <table id="grid-ndp" class="table table-condensed table-hover table-striped table-responsive">
                    <thead>
                    <tr>
                        <th data-column-id="ip" data-type="string"  data-identifier="true">{{ lang._('IPv6') }}</th>
                        <th data-column-id="mac" data-type="string" data-identifier="true">{{ lang._('MAC') }}</th>
                        <th data-column-id="manufacturer" data-type="string" data-css-class="hidden-xs hidden-sm" data-header-css-class="hidden-xs hidden-sm">{{ lang._('Manufacturer') }}</th>
                        <th data-column-id="intf" data-type="string" data-css-class="hidden-xs hidden-sm" data-header-css-class="hidden-xs hidden-sm">{{ lang._('Interface') }}</th>
                        <th data-column-id="intf_description" data-type="string" data-css-class="hidden-xs hidden-sm" data-header-css-class="hidden-xs hidden-sm">{{ lang._('Interface name') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
            <div  class="col-sm-12">
                <div class="row">
                    <div class="col-xs-12">
                        <div class="pull-right">
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
