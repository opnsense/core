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
            'expired': "{{ lang._('Serve expired') }}",
            'prefetch': "{{ lang._('Prefetch') }}",
            'queries': "{{ lang._('Queries') }}",
        },
        'requestlist': {
            'avg': "{{ lang._('Request queue avg') }}",
            'max': "{{ lang._('Request queue max') }}",
            'overwritten': "{{ lang._('Request queue overwritten') }}",
            'exceeded': "{{ lang._('Request queue exceeded') }}",
            'current': {
                'all': "{{ lang._('Request queue size (all)') }}",
                'user': "{{ lang._('Request queue size (client)') }}"
            }
        }
    };

    const descriptionMapTime = {
        'now': "{{ lang._('Now') }}",
        'up': "{{ lang._('Uptime') }}",
        'elapsed': "{{ lang._('Elapsed') }}"
    };

    const descriptionNumQuery = {
        'query': {
        'type': {
                'A': "{{ lang._('A') }}",
                'AAAA': "{{ lang._('AAAA') }}",
                'CNAME': "{{ lang._('CNAME') }}",
                'SOA': "{{ lang._('SOA') }}",
                'PTR': "{{ lang._('PTR') }}",
                'TXT': "{{ lang._('TXT') }}",
                'SRV': "{{ lang._('SRV') }}",
                'NAPTR': "{{ lang._('NAPTR') }}",
            },
            'tls': {
                '__value__': "{{ lang._('DoT') }}"
            },
            'https': "{{ lang._('DoH') }}",
            'ipv6': "{{ lang._('IPv6') }}"
        }
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

                    // Sort the keys - ensure total and time is listed first.
                    let dataKeys = Object.keys(data['data']);
                    let sortOrder = ['total', 'time'];

                    dataKeys.sort(function(a, b) {
                        var indA = sortOrder.indexOf(a);
                        var indB = sortOrder.indexOf(b);
                        return (indA > -1 ? indA : 999) - (indB > -1 ? indB : 999);
                    });

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
                        } else if (key === "num") {
                            let title = document.createElement("h2");
                            title.innerHTML = "Query Types";
                            statsView.append(title);

                            let table = document.createElement('table');
                            table.classList.add('table');
                            table.classList.add('table-striped');
                            table.style.width = 'auto';
                            let tbody = document.createElement('tbody');
                            writeDescs(tbody, value, descriptionNumQuery);
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
