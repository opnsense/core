{#
 # Copyright (c) 2025 Deciso B.V.
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
 # THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
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
    $( document ).ready(function() {
        ajaxGet('/api/ntpd/service/meta', {}, function (meta, status) {
            function headerFormatters() {
                let result = {};
                for (let key in meta['key']) {
                    result[key] = function (column) {
                        return `<span class="has-tooltip" data-toggle="tooltip" title="${meta['key'][key]}">${column.title}</span>`;
                    };
                }
                return result;
            }

            const table = $('#ntpd-table').UIBootgrid({
                search: '/api/ntpd/service/status',
                options: {
                    navigation: 0,
                    selection: false,
                    multiSelect: false,
                    headerFormatters: {
                        ...headerFormatters()
                    },
                    formatters: {
                        /* status is mapped to status_symbol, status itself is translated */
                        status: (col, row) => {
                            const val = row[col.id], metaVal = meta.symbols?.status?.[row.status_symbol];
                            return metaVal ? `<span class="has-tooltip" data-toggle="tooltip" title="${metaVal}">${val}</span>` : val;
                        },
                        type: (col, row) => {
                            const val = row[col.id], metaVal = meta.symbols?.connection_type?.[row.type];
                            return metaVal ? `<span class="has-tooltip" data-toggle="tooltip" title="${metaVal}">${val}</span>` : val;
                        }
                    }
                }
            });
        });

        ajaxGet('/api/ntpd/service/gps', {}, function (data, status) {
            const gps = data.gps || {};
            const container = document.getElementById('gps-content');

            if (!gps.ok) {
                $('#gps-status').remove();
                return;
            }

            let html = '';

            if (gps.lat !== undefined && gps.lon !== undefined) {
                html += `
                <div class="gps-item">
                    <strong>Latitude:</strong>
                    ${gps.lat.toFixed(5)} 
                    (${gps.lat_deg}&deg; ${(gps.lat_min * 60).toFixed(5)}${gps.lat_dir})
                </div>
                <div class="gps-item">
                    <strong>Longitude:</strong>
                    ${gps.lon.toFixed(5)} 
                    (${gps.lon_deg}&deg; ${(gps.lon_min * 60).toFixed(5)}${gps.lon_dir})
                </div>
                <div class="gps-item">
                    <a target="_gmaps" href="https://maps.google.com/?q=${gps.lat},${gps.lon}">
                    View on Google Maps
                    </a>
                </div>
                `;
            }

            if (gps.alt !== undefined) {
                html += `
                <div class="gps-item">
                    <strong>Altitude:</strong> ${gps.alt} ${gps.alt_unit || ''}
                </div>
                `;
            }

            if (gps.sat !== undefined || gps.gps_satview !== undefined) {
                html += `
                <div class="gps-item">
                    <strong>Satellites:</strong>
                    ${gps.gps_satview ? `in view ${gps.gps_satview}` : ''}
                    ${gps.gps_satview && gps.sat ? ', ' : ''}
                    ${gps.sat ? `in use ${gps.sat}` : ''}
                </div>
                `;
            }

            container.innerHTML = html || '<p>No valid GPS fields available.</p>';
        });
    });

</script>

<style>
.has-tooltip {
  cursor: help;
}
.has-tooltip::after {
  content: " ⓘ";
  font-size: 0.8em;
  color: currentColor; /* theme-agnostic */
  opacity: 0.6;
}
</style>

<div class="tab-content content-box" style="padding: 10px;">
    <table id="ntpd-table" class="table table-condensed table-hover table-striped table-responsive">
        <thead>
            <tr>
                <th data-column-id="status" data-formatter="status" data-sortable="false">{{ lang._('Status') }}</th>
                <th data-column-id="server" data-formatter="server" data-sortable="false">{{ lang._('Server') }}</th>
                <th data-column-id="refid" data-formatter="refid" data-sortable="false">{{ lang._('Ref ID') }}</th>
                <th data-column-id="stratum" data-formatter="stratum" data-sortable="false">{{ lang._('Stratum') }}</th>
                <th data-column-id="type" data-formatter="type" data-sortable="false">{{ lang._('Type') }}</th>
                <th data-column-id="when" data-formatter="when" data-sortable="false">{{ lang._('When') }}</th>
                <th data-column-id="poll" data-formatter="poll" data-sortable="false">{{ lang._('Poll') }}</th>
                <th data-column-id="reach" data-formatter="reach" data-sortable="false">{{ lang._('Reach') }}</th>
                <th data-column-id="delay" data-formatter="delay" data-sortable="false">{{ lang._('Delay') }}</th>
                <th data-column-id="offset" data-formatter="offset" data-sortable="false">{{ lang._('Offset') }}</th>
                <th data-column-id="jitter" data-formatter="jitter" data-sortable="false">{{ lang._('Jitter') }}</th>
            </tr>
        </thead>
    </table>

    <div id="gps-status">
        <div>
            <h4>GPS Information</h4>
            <div id="gps-content"></div>
        </div>
    </div>
</div>
