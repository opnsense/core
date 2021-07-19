{#
 # Copyright (c) 2018 Deciso B.V.
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

    const descriptionMapThread = {
        'recursion': {
            'time': {
                'avg': "{{ lang._('Recursion time (average)') }}",
                'median': "{{ lang._('Recursion time (median)') }}"
            }
        },
        'tcpusage': "{{ lang._('TCP usage') }}",
        'num': {
            'queries_ip_ratelimited': "{{ lang._('IP ratelimited queries') }}",
            'recursivereplies': "{{ lang._('Recursive replies') }}",
            'cachemiss': "{{ lang._('Cache misses') }}",
            'cachehits': "{{ lang._('Cache hits') }}",
            'zero_ttl': "{{ lang._('Zero TTL') }}",
            'prefetch': "{{ lang._('Prefetch') }}",
            'queries': "{{ lang._('Queries') }}",
        }
    };

    const descriptionMapTime = {
        'now': "{{ lang._('Now') }}",
        'up': "{{ lang._('Uptime') }}",
        'elapsed': "{{ lang._('Elapsed') }}"
    };

    function writeDescs(parent, data, descriptions) {
        $.each(descriptions, function(descKey, descValue) {
            if (typeof descValue !== 'object') {
                let tr = document.createElement("tr");
                let th = document.createElement("th");
                th.innerHTML = descValue + ':';
                let td = document.createElement("td");
                td.innerHTML = data[descKey];

                tr.append(th);
                tr.append(td);
                parent.append(tr);
            } else {
                writeDescs(parent, data[descKey], descriptions[descKey]);
            }
        });
    }

    /**
     * fetch Unbound stats
     */
    function updateStats() {
        ajaxGet("/api/unbound/diagnostics/stats",
            {}, function (data, status) {
                if (status === "success") {
                    // Clear old view
                    let statsView = $("#statsView");
                    statsView.html('');

                    // Sort the keys in order to ensure that Thread 0 will come before Thread 1.
                    let dataKeys = Object.keys(data['data']);
                    dataKeys.sort();
                    dataKeys.forEach(function(key) {
                        let value = data['data'][key];
                        if (key === 'total' || key.substr(0, 6) === 'thread') {
                            let description;
                            if (key === 'total') {
                                description = 'Total';
                            } else {
                                description = 'Thread ' + key.substr(6);
                            }

                            let title = document.createElement("h2");
                            title.innerHTML = description;
                            statsView.append(title);

                            let table = document.createElement('table');
                            table.classList.add('table');
                            table.classList.add('table-striped');
                            table.style.width = 'auto';
                            let tbody = document.createElement('tbody');
                            writeDescs(tbody, value, descriptionMapThread);
                            table.append(tbody);
                            statsView.append(table);
                        } else if (key === "time") {
                            let title = document.createElement("h2");
                            title.innerHTML = "Times";
                            statsView.append(title);

                            let table = document.createElement('table');
                            table.classList.add('table');
                            table.classList.add('table-striped');
                            table.style.width = 'auto';
                            let tbody = document.createElement('tbody');
                            writeDescs(tbody, value, descriptionMapTime);
                            table.append(tbody);
                            statsView.append(table);
                        }
                    });
                }
            }
        );
    }

    $(document).ready(function() {

        // Autorefresh every 10 seconds.
        setInterval(function() {
            if ($("#auto_refresh").is(':checked')) {
                updateStats();
            }
        }, 10000);

        // initial fetch
        updateStats();

	updateServiceControlUI('unbound');
    });
</script>

<div class="content-box">
    <div class="content-box-main">
        <div class="table-responsive">
            <div class="col-sm-12" id="statsView">

            </div>
            <div class="col-sm-12">
                <div class="row">
                    <div class="col-xs-12">
                        <div class="pull-right">
                            <label>
                                <input id="auto_refresh" type="checkbox" checked="checked">
                                <span class="fa fa-refresh"></span> {{ lang._('Auto refresh') }}
                            </label>
                        </div>
                    </div>
                </div>
                <hr/>
            </div>
        </div>
    </div>
</div>
