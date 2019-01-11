{#
 # Copyright (c) 2019 Deciso B.V.
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1. Redistributions of source code must retain the above copyright notice,
 #    this list of conditions and the following disclaimer.
 #
 # 2. Redistributions in binary form must reproduce the above copyright notice,
 #    this list of conditions and the following disclaimer in the documentation
 #    and/or other materials provided with the distribution.
 #
 # THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
 # INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 # AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 # AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 # OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 # SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 # INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 # CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 # ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 # POSSIBILITY OF SUCH DAMAGE.
 #}

<script>
    'use strict';

    function fetchLog(logfile, filter) {
        ajaxCall('/api/logs/log/view', { logfile: logfile, filter: filter }, function(data, status) {
            if (status === 'success') {
                let tbody = $('#log-view tbody:first');
                tbody.html('');

                if (data['status'] !== 'ok') {
                    let tr = $('<tr>');
                    let td = $('<td colspan="2">');
                    td.text(data['status']);
                    tr.append(td);
                    tbody.append(tr);
                } else {
                    $.each(data['logLines'], function (key, logLine) {
                        let tr = $('<tr>');
                        let tdDate = $('<td>');
                        tdDate.text(logLine['date']);
                        let tdMessage =$('<td>');
                        tdMessage.html(logLine['message']);
                        tr.append(tdDate, tdMessage);
                        tbody.append(tr);
                    });
                }
            }
        });
    }

    $(document).ready(function () {
        const logfile = '{{ logfile }}';
        fetchLog(logfile, '');

        $('#clear-log').on('click', function() {
            ajaxCall('/api/logs/log/clear', { logfile: logfile }, function (data, status) {
                if (status === 'success' || data['status'] === 'ok') {
                    fetchLog(logfile, $('#filtertext').val());
                }
            })
        });
        $('#filtertext').on('change', function() {
            fetchLog(logfile, $(this).val());
        });
    })
</script>

<div class="bootgrid-header container-fluid">
    <div class="row">
        <div class="actionBar">
            <div class="search form-group" style="width: 350px;">
                <div class="input-group">
                    <div class="input-group-addon"><i class="fa fa-search"></i></div>
                    <input type="text" class="form-control" id="filtertext" placeholder="{{ lang._('Search for a specific message...') }}"/>
                </div>
            </div>
            <div class="actions btn-group">
                <button id="clear-log" class="btn btn-primary"><span class="fa fa-trash"></span> {{ lang._('Clear log') }}</button>
            </div>
        </div>
    </div>
</div>

<div class="table-responsive content-box">
    <table id="log-view" class="table table-striped">
        <thead>
            <tr>
                <th class="col-md-2 col-sm-3 col-xs-4">{{ lang._('Date') }}</th>
                <th class="col-md-10 col-sm-9 col-xs-8">{{ lang._('Message') }}</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
</div>
