<section class="page-content-main">
    <div class="container-fluid">
        <h1>{{ lang._('DDNS') }}</h1>

        <div class="panel panel-default">
            <div class="panel-heading">{{ lang._('Settings') }}</div>
            <div class="panel-body">
                <div class="alert alert-info" role="alert" style="margin-bottom:16px;">
                    {{ lang._('Configure provider endpoints and token once, then use automatic checks for periodic updates.') }}
                </div>
                <form id="ddns-form" class="form-horizontal">
                    <div class="form-group">
                        <label class="col-sm-3 control-label">{{ lang._('Enabled') }}</label>
                        <div class="col-sm-9">
                            <input id="enabled" type="checkbox" checked>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-3 control-label">{{ lang._('Own IP (optional)') }}</label>
                        <div class="col-sm-9">
                            <input id="ownIp" class="form-control" placeholder="{{ lang._('e.g. 203.0.113.10') }}">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-3 control-label">{{ lang._('Automatic check (Cron)') }}</label>
                        <div class="col-sm-9">
                            <label style="margin-right:12px;"><input id="autoUpdate" type="checkbox"> {{ lang._('Active') }}</label>
                            <select id="intervalMinutes" class="form-control" style="display:inline-block; width:auto; min-width:120px; margin-left:8px;">
                                {% for minute in 1..60 %}
                                <option value="{{ minute }}">{{ minute }} {% if minute == 1 %}{{ lang._('Minute') }}{% else %}{{ lang._('Minutes') }}{% endif %}</option>
                                {% endfor %}
                            </select>
                            <small class="help-block" style="margin-top:6px;">{{ lang._('Next check in:') }} <span id="nextRunCountdown">-</span></small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-3 control-label">{{ lang._('IP query URL') }}</label>
                        <div class="col-sm-9">
                            <select id="queryUrlPreset" class="form-control" style="margin-bottom:8px;">
                                <option value="https://api.ipify.org">{{ lang._('api.ipify.org (HTTPS)') }}</option>
                                <option value="http://checkip.amazonaws.com">checkip.amazonaws.com</option>
                                <option value="http://ipinfo.io/ip">ipinfo.io/ip</option>
                                <option value="http://icanhazip.com/">icanhazip.com</option>
                                <option value="http://ifconfig.me/ip">ifconfig.me/ip</option>
                                <option value="http://ident.me/">ident.me</option>
                                <option value="http://myexternalip.com/raw">myexternalip.com/raw</option>
                                <option value="http://checkip.dns.he.net/">checkip.dns.he.net</option>
                                <option value="http://bot.whatismyipaddress.com/">bot.whatismyipaddress.com</option>
                                <option value="http://domains.google.com/checkip">domains.google.com/checkip</option>
                                <option value="http://ipecho.net/plain">ipecho.net/plain</option>
                                <option value="http://ddns.afraid.org/dynamic/check.php">ddns.afraid.org/dynamic/check.php</option>
                                <option value="custom">{{ lang._('Custom URL') }}</option>
                            </select>
                            <input id="queryUrl" class="form-control" value="https://api.ipify.org">
                            <small id="queryPresetHint" class="help-block" style="margin-top:4px;color:#666;">{{ lang._('Select an IP detection provider or use a custom URL.') }}</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-3 control-label">{{ lang._('Update URL template') }}</label>
                        <div class="col-sm-9">
                            <select id="tokenUpdatePreset" class="form-control" style="margin-bottom:8px;">
                                <option value="https://ddns.afraid.org/dynamic/update.php?{token}">{{ lang._('DDNS default (dynamic/update.php?TOKEN)') }}</option>
                                <option value="https://ddns.afraid.org:8080/dynamic/update.php?{token}">{{ lang._('DDNS default port 8080') }}</option>
                                <option value="https://dynv6.com/api/update?token={token}&amp;ipv4={ip}">{{ lang._('dynv6 token (dynv6.com/api/update)') }}</option>
                                <option value="https://ipv64.net/nic/update?key={token}&amp;ip={ip}">{{ lang._('IPv64 key/token (ipv64.net/nic/update)') }}</option>
                                <option value="https://www.duckdns.org/update?domains=YOURDOMAIN&amp;token={token}&amp;ip={ip}">{{ lang._('DuckDNS token (set domain in template)') }}</option>
                                <option value="custom">{{ lang._('Custom URL') }}</option>
                            </select>
                            <input id="tokenUpdateUrl" class="form-control" value="https://ddns.afraid.org/dynamic/update.php?{token}">
                            <small class="help-block">{{ lang._('Placeholders: {token} and optionally {ip} (depends on provider)') }}</small>
                            <small id="presetHint" class="help-block" style="margin-top:4px;color:#666;"></small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-3 control-label">{{ lang._('Detected public IP') }}</label>
                        <div class="col-sm-9">
                            <input id="detectedIp" class="form-control" readonly placeholder="{{ lang._('detected automatically...') }}">
                            <small id="detectedIpInfo" class="help-block">{{ lang._('Source: automatic via query URL') }}</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-3 control-label">{{ lang._('DDNS token') }}</label>
                        <div class="col-sm-9">
                            <div style="display:flex;gap:8px;align-items:center;">
                                <input id="token" type="password" class="form-control" placeholder="{{ lang._('Token or full v2 URL (sync.afraid.org/u/.../)') }}">
                                <button type="button" id="toggleTokenVisibilityBtn" class="btn btn-default btn-sm">{{ lang._('Show') }}</button>
                            </div>
                            <small class="help-block" style="margin-top:6px;">{{ lang._('Token is hidden by default. Use "Show" to reveal it.') }}</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-3 control-label">{{ lang._('Update URL preview') }}</label>
                        <div class="col-sm-9">
                            <div id="updateUrlPreview" style="padding:8px 10px;border:1px solid #ddd;border-radius:4px;background:#fafafa;word-break:break-all;">-</div>
                            <div style="margin-top:8px;">
                                <button type="button" id="copyPreviewBtn" class="btn btn-default btn-xs">{{ lang._('Copy preview') }}</button>
                                <span id="copyPreviewInfo" style="margin-left:8px;color:#666;"></span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="col-sm-offset-3 col-sm-9">
                            <button type="button" id="testBtn" class="btn btn-default">{{ lang._('Test connection') }}</button>
                            <button type="button" id="runBtn" class="btn btn-default">{{ lang._('Update now') }}</button>
                            <button type="button" id="saveBtn" class="btn btn-primary">{{ lang._('Save') }}</button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-3 control-label">{{ lang._('Status') }}</label>
                        <div class="col-sm-9">
                            <span id="statusBadge" style="display:inline-block;padding:6px 12px;border-radius:4px;background:#999;color:#fff;">{{ lang._('Unknown') }}</span>
                            <span id="currentStateBadge" style="display:inline-block;padding:6px 12px;border-radius:4px;background:#666;color:#fff;margin-left:8px;">{{ lang._('Not checked') }}</span>
                            <span id="statusText" style="margin-left:8px;"></span>
                            <div id="providerMessage" style="margin-top:8px;color:#555;"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-3 control-label">{{ lang._('Log') }}</label>
                        <div class="col-sm-9">
                            <textarea id="logEntries" class="form-control" rows="12" readonly style="font-family:monospace;font-size:12px;line-height:1.35;"></textarea>
                            <div style="margin-top:8px;">
                                <button type="button" id="clearLogBtn" class="btn btn-default btn-xs">{{ lang._('Clear log') }}</button>
                                <span id="clearLogInfo" style="margin-left:8px;color:#666;"></span>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<script>
    const I18N = {
        unknown: "{{ lang._('Unknown') }}",
        connected: "{{ lang._('Connected') }}",
        error: "{{ lang._('Error') }}",
        notCurrent: "{{ lang._('Not current') }}",
        current: "{{ lang._('Current') }}",
        copied: "{{ lang._('Copied to clipboard') }}",
        copyFailed: "{{ lang._('Copy failed') }}",
        noPreview: "{{ lang._('No preview to copy') }}",
        presetSelected: "{{ lang._('Preset selected.') }}",
        customQueryHint: "{{ lang._('Custom: use your own IP query URL.') }}",
        customTemplateHint: "{{ lang._('Custom: use your own provider URL, {token} required, {ip} optional.') }}",
        providerSelectHint: "{{ lang._('Select an IP detection provider or use a custom URL.') }}",
        saveOk: "{{ lang._('Settings saved') }}",
        saveFailed: "{{ lang._('Save failed') }}",
        scheduleSaved: "{{ lang._('Schedule saved') }}",
        scheduleSaveFailed: "{{ lang._('Failed to save schedule') }}",
        updateOk: "{{ lang._('Update successful') }}",
        updateFailed: "{{ lang._('Update failed') }}",
        testFailed: "{{ lang._('Test failed') }}",
        noConnection: "{{ lang._('No connection') }}",
        ipDetecting: "{{ lang._('IP is being detected ...') }}",
        ipDetectFailed: "{{ lang._('Could not detect IP') }}",
        sourceManual: "{{ lang._('Source: manual') }}",
        sourceAuto: "{{ lang._('Source: automatic via query URL') }}",
        sourceStored: "{{ lang._('Source: stored/automatic') }}",
        tokenShow: "{{ lang._('Show') }}",
        tokenHide: "{{ lang._('Hide') }}",
        disabledStateHint: "{{ lang._('Enable DDNS to run tests and updates.') }}",
        logCleared: "{{ lang._('Log cleared') }}",
        logClearFailed: "{{ lang._('Failed to clear log') }}",
        noConfirmedRun: "{{ lang._('No confirmed run yet') }}",
        lastAutoRunOk: "{{ lang._('Last automatic run successful') }}",
        lastAutoRunFailed: "{{ lang._('Last automatic run failed') }}",
        connectionOk: "{{ lang._('Connection successful') }}",
        connectionFailed: "{{ lang._('Connection failed') }}",
        providerDocsHint: "{{ lang._('Preset selected. Please verify the API documentation of your provider.') }}"
    };

    function collectPayload() {
        return {
            general: {
                enabled: $('#enabled').is(':checked') ? '1' : '0',
                autoUpdate: $('#autoUpdate').is(':checked') ? '1' : '0',
                intervalMinutes: ($('#intervalMinutes').val() || '5').trim(),
                ownIp: $('#ownIp').val().trim(),
                queryUrl: $('#queryUrl').val().trim(),
                tokenUpdateUrl: $('#tokenUpdateUrl').val().trim(),
                token: $('#token').val().trim()
            }
        };
    }

    function setStatus(color, text) {
        var bg = '#999';
        var label = I18N.unknown;
        if (color === 'green') {
            bg = '#2e7d32';
            label = I18N.connected;
        } else if (color === 'red') {
            bg = '#c62828';
            label = I18N.error;
        }
        $('#statusBadge').css('background', bg).text(label);
        $('#statusText').text(text || '');
    }

    function setProviderMessage(message) {
        $('#providerMessage').text(message || '');
    }

    function syncControlState() {
        var enabled = $('#enabled').is(':checked');
        var autoUpdate = $('#autoUpdate').is(':checked');

        $('#autoUpdate').prop('disabled', !enabled);
        $('#intervalMinutes').prop('disabled', !enabled || !autoUpdate);
        $('#runBtn').prop('disabled', !enabled);
        $('#testBtn').prop('disabled', !enabled);

        if (!enabled) {
            setProviderMessage(I18N.disabledStateHint);
        }
    }

    function setLogEntries(text) {
        var value = (text === 'null' || text === 'undefined' || text === null || text === undefined) ? '' : String(text);
        var area = $('#logEntries');
        area.val(value);
        var el = area.get(0);
        if (el) {
            el.scrollTop = el.scrollHeight;
        }
    }

    function nodeValue(value, fallback) {
        if (value === null || value === undefined) {
            return fallback || '';
        }
        if (typeof value === 'object') {
            if (value.hasOwnProperty('value')) {
                return String(value.value || fallback || '');
            }
            return fallback || '';
        }
        return String(value);
    }

    function toBool(value) {
        var v = (value || '').toString().toLowerCase();
        return v === '1' || v === 'true' || v === 'yes' || v === 'on';
    }

    function inferStatusFromCronTail(cronTailValue) {
        var raw = (cronTailValue || '').trim();
        if (!raw) {
            return null;
        }
        var lines = raw.split(/\r\n|\r|\n/).filter(function(line) { return line && line.trim() !== ''; });
        if (!lines.length) {
            return null;
        }
        var last = lines[lines.length - 1].toLowerCase();
        if (last.indexOf('updated (') >= 0 || last.indexOf('no update needed') >= 0) {
            return { color: 'green', text: I18N.lastAutoRunOk };
        }
        if (last.indexOf('failed') >= 0 || last.indexOf('rejected') >= 0 || last.indexOf('token missing') >= 0 || last.indexOf('resolve ip failed') >= 0) {
            return { color: 'red', text: I18N.lastAutoRunFailed };
        }
        return null;
    }

    function setCurrentState(state) {
        var label = I18N.notCurrent;
        var color = '#c62828';
        if (state === 'current') {
            label = I18N.current;
            color = '#2e7d32';
        }
        $('#currentStateBadge').css('background', color).text(label);
    }

    function startNextRunCountdown(nextRunEpoch, enabled, autoEnabled, cronActive) {
        if (!enabled || !autoEnabled || !cronActive) {
            if (window.__ddnsCountdownTimer) {
                clearInterval(window.__ddnsCountdownTimer);
                window.__ddnsCountdownTimer = null;
            }
            $('#nextRunCountdown').text('-');
            return;
        }

        var target = parseInt(nextRunEpoch || '0', 10);
        if (!target || target <= 0) {
            $('#nextRunCountdown').text('-');
            return;
        }

        function computeNextCronBoundary(nowEpoch, intervalMinutes) {
            var date = new Date(nowEpoch * 1000);
            var minute = date.getMinutes();
            var hour = date.getHours();
            var day = date.getDate();
            var month = date.getMonth();
            var year = date.getFullYear();

            var nextMinute = Math.ceil((minute + 1) / intervalMinutes) * intervalMinutes;
            if (nextMinute >= 60) {
                nextMinute -= 60;
                hour += 1;
            }

            var next = new Date(year, month, day, hour, nextMinute, 0);
            return Math.floor(next.getTime() / 1000);
        }

        function renderCountdown() {
            var now = Math.floor(Date.now() / 1000);
            var diff = target - now;
            if (diff <= 0) {
                var minsCfg = parseInt($('#intervalMinutes').val() || '5', 10);
                if (isNaN(minsCfg) || minsCfg < 1) {
                    minsCfg = 1;
                }
                if (minsCfg > 60) {
                    minsCfg = 60;
                }
                target = computeNextCronBoundary(now, minsCfg);
                diff = target - now;
            }
            var mins = Math.floor(diff / 60);
            var secs = diff % 60;
            $('#nextRunCountdown').text(mins + 'm ' + secs + 's');
        }

        renderCountdown();
        if (window.__ddnsCountdownTimer) {
            clearInterval(window.__ddnsCountdownTimer);
            window.__ddnsCountdownTimer = null;
        }
        window.__ddnsCountdownTimer = setInterval(renderCountdown, 1000);
    }

    function syncQueryUrlPresetFromValue(value) {
        var normalized = (value || '').trim();
        var known = [];
        $('#queryUrlPreset option').each(function() {
            var v = $(this).val();
            if (v !== 'custom') {
                known.push(v);
            }
        });

        if (known.indexOf(normalized) >= 0) {
            $('#queryUrlPreset').val(normalized);
            $('#queryUrl').prop('readonly', true);
            $('#queryPresetHint').text(I18N.presetSelected);
        } else {
            $('#queryUrlPreset').val('custom');
            $('#queryUrl').prop('readonly', false);
            $('#queryPresetHint').text(I18N.customQueryHint);
        }
    }

    function updateQueryUrlMode() {
        var preset = $('#queryUrlPreset').val();
        if (preset === 'custom') {
            $('#queryUrl').prop('readonly', false);
            $('#queryPresetHint').text(I18N.customQueryHint);
            return;
        }
        $('#queryUrl').val(preset);
        $('#queryUrl').prop('readonly', true);
        $('#queryPresetHint').text(I18N.presetSelected);
        refreshDetectedIp();
    }

    function updateTokenTemplateMode() {
        var preset = $('#tokenUpdatePreset').val();
        if (preset === 'custom') {
            $('#tokenUpdateUrl').prop('readonly', false);
            setPresetHint(I18N.customTemplateHint);
            renderUpdateUrlPreview();
            return;
        }

        $('#tokenUpdateUrl').val(preset);
        $('#tokenUpdateUrl').prop('readonly', true);
        setPresetHintByValue(preset);
        renderUpdateUrlPreview();
    }

    function setPresetHint(text) {
        $('#presetHint').text(text || '');
    }

    function setPresetHintByValue(value) {
        var hints = {
            'https://ddns.afraid.org/dynamic/update.php?{token}': "{{ lang._('DDNS default: dynamic/update.php?TOKEN')|e('js') }}",
            'https://ddns.afraid.org:8080/dynamic/update.php?{token}': "{{ lang._('DDNS via port 8080 if standard port is blocked.')|e('js') }}",
            'https://dynv6.com/api/update?token={token}&ipv4={ip}': "{{ lang._('dynv6: use your API token from account; IPv4 is passed as ipv4={ip}.')|e('js') }}",
            'https://ipv64.net/nic/update?key={token}&ip={ip}': "{{ lang._('IPv64: use your key/token from IPv64; IP is passed as ip={ip}.')|e('js') }}",
            'https://www.duckdns.org/update?domains=YOURDOMAIN&token={token}&ip={ip}': "{{ lang._('DuckDNS: replace YOURDOMAIN in template; token remains in token field.')|e('js') }}"
        };
        setPresetHint(hints[value] || I18N.providerDocsHint);
    }

    function syncTemplatePresetFromValue(value) {
        var normalized = (value || '').trim();
        var knownPresets = [
            'https://ddns.afraid.org/dynamic/update.php?{token}',
            'https://ddns.afraid.org:8080/dynamic/update.php?{token}',
            'https://dynv6.com/api/update?token={token}&ipv4={ip}',
            'https://ipv64.net/nic/update?key={token}&ip={ip}',
            'https://www.duckdns.org/update?domains=YOURDOMAIN&token={token}&ip={ip}'
        ];

        if (knownPresets.indexOf(normalized) >= 0) {
            $('#tokenUpdatePreset').val(normalized);
            $('#tokenUpdateUrl').prop('readonly', true);
            setPresetHintByValue(normalized);
        } else {
            $('#tokenUpdatePreset').val('custom');
            $('#tokenUpdateUrl').prop('readonly', false);
            setPresetHint(I18N.customTemplateHint);
        }
        renderUpdateUrlPreview();
    }

    function normalizeTokenValue(input) {
        var value = (input || '').trim();
        if (value === '') {
            return '';
        }

        var m1 = value.match(/^https?:\/\/[^/]*afraid\.org\/u\/([^/?]+)/i);
        if (m1 && m1[1]) {
            try { return decodeURIComponent(m1[1]); } catch (e) { return m1[1]; }
        }

        var m2 = value.match(/^https?:\/\/ddns\.afraid\.org\/dynamic\/update\.php\?([^&]+)/i);
        if (m2 && m2[1]) {
            try { return decodeURIComponent(m2[1]); } catch (e) { return m2[1]; }
        }

        return value.replace(/^\/+|\/+$/g, '');
    }

    function buildPreviewUrl() {
        var tokenRaw = normalizeTokenValue($('#token').val());
        var template = ($('#tokenUpdateUrl').val() || '').trim();
        var ip = ($('#detectedIp').val() || '').trim();
        var ipPart = ip !== '' ? encodeURIComponent(ip) : '{ip}';

        if (tokenRaw === '') {
            return '-';
        }

        var tokenEncoded = tokenRaw;
        var result = template !== '' ? template : 'https://ddns.afraid.org/dynamic/update.php?{token}';

        if (result.indexOf('{token}') >= 0) {
            result = result.replaceAll('{token}', tokenEncoded);
        } else if (result.indexOf('%s') >= 0) {
            result = result.replace('%s', tokenEncoded);
        } else {
            result = result.replace(/\/+$/, '') + '/' + tokenEncoded + '/';
        }

        return result.replaceAll('{ip}', ipPart);
    }

        function buildDisplayPreviewUrl() {
            var full = buildPreviewUrl();
            if (!full || full === '-') {
                return '-';
            }
            if ($('#token').attr('type') === 'password') {
                return full
                    .replace(/(token=)[^&\s]+/ig, '$1********')
                    .replace(/(key=)[^&\s]+/ig, '$1********')
                    .replace(/(\/u\/)[^/?\s]+/ig, '$1********')
                    .replace(/(dynamic\/update\.php\?)[^&\s]+/ig, '$1********');
            }
            return full;
        }

    function renderUpdateUrlPreview() {
            $('#updateUrlPreview').text(buildDisplayPreviewUrl());
    }

    function copyPreviewUrl() {
            var text = buildDisplayPreviewUrl();
        if (!text || text === '-') {
            $('#copyPreviewInfo').text(I18N.noPreview);
            return;
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                $('#copyPreviewInfo').text(I18N.copied);
            }).catch(function() {
                $('#copyPreviewInfo').text(I18N.copyFailed);
            });
            return;
        }

        var temp = $('<textarea>').val(text).appendTo('body').select();
        try {
            document.execCommand('copy');
            $('#copyPreviewInfo').text(I18N.copied);
        } catch (e) {
            $('#copyPreviewInfo').text(I18N.copyFailed);
        }
        temp.remove();
    }

    function loadSettings(skipDetectIp) {
        $.get('/api/ddns/settings/get', function(data) {
            if (!data || !data.general) {
                return;
            }
            var g = data.general;
            var enabledValue = nodeValue(g.enabled, '0');
            var autoUpdateValue = nodeValue(g.autoUpdate, '0');
            var intervalValue = nodeValue(g.intervalMinutes, '5');
            var ownIpValue = nodeValue(g.ownIp, '');
            var queryUrlValue = nodeValue(g.queryUrl, 'https://api.ipify.org');
            var tokenUpdateUrlValue = nodeValue(g.tokenUpdateUrl, 'https://ddns.afraid.org/dynamic/update.php?{token}');
            var tokenValue = nodeValue(g.token, '');
            var lastStatusValue = nodeValue(g.lastStatus, 'unknown');
            var lastMessageValue = nodeValue(g.lastMessage, '');
            var currentStateValue = nodeValue(g.currentState, 'unknown');
            var logEntriesValue = nodeValue(g.logEntries, '');
            var cronLogTailValue = nodeValue(g.cronLogTail, '');
            var providerMessageValue = nodeValue(g.lastProviderMessage, '');
            var nextRunEpochValue = nodeValue(g.nextRunEpoch, '0');
            var cronActiveValue = nodeValue(g.cronActive, '0');

            $('#enabled').prop('checked', toBool(enabledValue));
            $('#autoUpdate').prop('checked', toBool(autoUpdateValue));
            $('#intervalMinutes').val(intervalValue || '5');
            $('#ownIp').val(ownIpValue);
            $('#queryUrl').val(queryUrlValue || 'https://api.ipify.org');
            syncQueryUrlPresetFromValue(queryUrlValue || 'https://api.ipify.org');
            var templateValue = tokenUpdateUrlValue || 'https://ddns.afraid.org/dynamic/update.php?{token}';
            if (/^https?:\/\/[^/]*afraid\.org\/u\//i.test(templateValue)) {
                templateValue = 'https://ddns.afraid.org/dynamic/update.php?{token}';
            }
            $('#tokenUpdateUrl').val(templateValue);
            syncTemplatePresetFromValue(templateValue);
            $('#token').val(normalizeTokenValue(tokenValue));

            if (lastStatusValue === 'ok') {
                setStatus('green', lastMessageValue || I18N.connectionOk);
            } else if (lastStatusValue === 'failed') {
                setStatus('red', lastMessageValue || I18N.connectionFailed);
            } else {
                var inferred = inferStatusFromCronTail(cronLogTailValue);
                if (inferred) {
                    setStatus(inferred.color, inferred.text);
                } else {
                    setStatus('red', I18N.noConfirmedRun);
                }
            }
            setCurrentState(currentStateValue || 'unknown');
            setLogEntries((logEntriesValue || '').trim());
            setProviderMessage(providerMessageValue || '');
            syncControlState();
            startNextRunCountdown(nextRunEpochValue || '0', toBool(enabledValue), toBool(autoUpdateValue), toBool(cronActiveValue));

            if (!skipDetectIp) {
                refreshDetectedIp();
            }
        });
    }

    function refreshDetectedIp() {
        $('#detectedIp').val('');
        $('#detectedIpInfo').text(I18N.ipDetecting);

        $.ajax({
            url: '/api/ddns/settings/detectip',
            method: 'POST',
            data: collectPayload(),
            success: function(resp) {
                if (resp && resp.ip) {
                    $('#detectedIp').val(resp.ip);
                    $('#detectedIpInfo').text((resp.source === 'manual') ? I18N.sourceManual : I18N.sourceAuto);
                } else {
                    $('#detectedIp').val('');
                    $('#detectedIpInfo').text((resp && resp.message) ? resp.message : I18N.ipDetectFailed);
                }
                renderUpdateUrlPreview();
            },
            error: function() {
                $('#detectedIp').val('');
                $('#detectedIpInfo').text(I18N.ipDetectFailed);
                renderUpdateUrlPreview();
            }
        });
    }

    function saveSettings() {
        $.ajax({
            url: '/api/ddns/settings/set',
            method: 'POST',
            data: collectPayload(),
            success: function(resp) {
                if (resp && resp.result === 'saved') {
                    setStatus('green', I18N.saveOk);
                    loadSettings();
                } else {
                    var validationMessage = '';
                    if (resp && resp.validations) {
                        var keys = Object.keys(resp.validations);
                        if (keys.length > 0) {
                            validationMessage = resp.validations[keys[0]];
                        }
                    }
                    setStatus('red', validationMessage ? (I18N.saveFailed + ': ' + validationMessage) : I18N.saveFailed);
                }
                setProviderMessage('');
            },
            error: function() {
                setStatus('red', I18N.saveFailed);
                setProviderMessage('');
            }
        });
    }

    function saveSchedule() {
        $.ajax({
            url: '/api/ddns/settings/schedule',
            method: 'POST',
            data: collectPayload(),
            success: function(resp) {
                if (resp && resp.result === 'saved') {
                    setStatus('green', I18N.scheduleSaved);
                    if (resp.general) {
                        var enabledVal = nodeValue(resp.general.enabled, '1');
                        var autoVal = nodeValue(resp.general.autoUpdate, '0');
                        var intVal = nodeValue(resp.general.intervalMinutes, '5');
                        var nextVal = nodeValue(resp.general.nextRunEpoch, '0');
                        var cronVal = nodeValue(resp.general.cronActive, '0');
                        $('#enabled').prop('checked', toBool(enabledVal));
                        $('#autoUpdate').prop('checked', toBool(autoVal));
                        $('#intervalMinutes').val(intVal || '5');
                        startNextRunCountdown(nextVal || '0', toBool(enabledVal), toBool(autoVal), toBool(cronVal));
                        setLogEntries(nodeValue(resp.general.logEntries, ''));
                    }
                } else {
                    setStatus('red', I18N.scheduleSaveFailed);
                }
            },
            error: function() {
                setStatus('red', I18N.scheduleSaveFailed);
            }
        });
    }

    function testConnection() {
        $.ajax({
            url: '/api/ddns/settings/test',
            method: 'POST',
            data: collectPayload(),
            success: function(resp) {
                if (resp && resp.connected) {
                    setStatus('green', resp.message || I18N.connectionOk);
                } else {
                    setStatus('red', (resp && resp.message) ? resp.message : I18N.noConnection);
                }
                setProviderMessage((resp && resp.providerMessage) ? resp.providerMessage : '');
            },
            error: function() {
                setStatus('red', I18N.testFailed);
                setProviderMessage('');
            }
        });
    }

    function runUpdateNow() {
        $.ajax({
            url: '/api/ddns/settings/run',
            method: 'POST',
            data: collectPayload(),
            success: function(resp) {
                if (resp && resp.connected) {
                    setStatus('green', resp.message || I18N.updateOk);
                } else {
                    setStatus('red', (resp && resp.message) ? resp.message : I18N.updateFailed);
                }
                setProviderMessage((resp && resp.providerMessage) ? resp.providerMessage : '');
                setCurrentState((resp && resp.currentState) ? resp.currentState : ((resp && resp.connected) ? 'updated' : 'error'));
                startNextRunCountdown((resp && resp.nextRunEpoch) ? resp.nextRunEpoch : '0', $('#enabled').is(':checked'), $('#autoUpdate').is(':checked'), true);
                if (resp && resp.ip) {
                    $('#detectedIp').val(resp.ip);
                    $('#detectedIpInfo').text(I18N.sourceStored);
                    renderUpdateUrlPreview();
                }
                loadSettings();
            },
            error: function() {
                setStatus('red', I18N.updateFailed);
                setProviderMessage('');
                setCurrentState('error');
            }
        });
    }

    function clearLogEntries() {
        $.ajax({
            url: '/api/ddns/settings/clearlog',
            method: 'POST',
            data: collectPayload(),
            success: function(resp) {
                if (resp && resp.result === 'saved') {
                    setLogEntries('');
                    $('#clearLogInfo').text(I18N.logCleared);
                } else {
                    $('#clearLogInfo').text(I18N.logClearFailed);
                }
            },
            error: function() {
                $('#clearLogInfo').text(I18N.logClearFailed);
            }
        });
    }

    $(function() {
        $('#queryUrlPreset').on('change', updateQueryUrlMode);
        $('#queryUrl').on('blur', function() {
            syncQueryUrlPresetFromValue($('#queryUrl').val());
            refreshDetectedIp();
        });
        $('#tokenUpdatePreset').on('change', updateTokenTemplateMode);
        $('#tokenUpdateUrl').on('blur', function() {
            syncTemplatePresetFromValue($('#tokenUpdateUrl').val());
        });
        $('#token').on('input', renderUpdateUrlPreview);
            $('#toggleTokenVisibilityBtn').on('click', function() {
                var tokenInput = $('#token');
                if (tokenInput.attr('type') === 'password') {
                    tokenInput.attr('type', 'text');
                    $('#toggleTokenVisibilityBtn').text(I18N.tokenHide);
                } else {
                    tokenInput.attr('type', 'password');
                    $('#toggleTokenVisibilityBtn').text(I18N.tokenShow);
                }
                renderUpdateUrlPreview();
            });
        $('#tokenUpdateUrl').on('input', renderUpdateUrlPreview);
        $('#ownIp').on('blur', refreshDetectedIp);
        $('#copyPreviewBtn').on('click', copyPreviewUrl);
        $('#clearLogBtn').on('click', clearLogEntries);
        $('#saveBtn').on('click', saveSettings);
        $('#testBtn').on('click', testConnection);
        $('#runBtn').on('click', runUpdateNow);
        $('#enabled').on('change', function() {
            syncControlState();
            if (!$('#enabled').is(':checked')) {
                if (window.__ddnsCountdownTimer) {
                    clearInterval(window.__ddnsCountdownTimer);
                    window.__ddnsCountdownTimer = null;
                }
                $('#nextRunCountdown').text('-');
            }
            saveSchedule();
        });
        $('#autoUpdate').on('change', function() {
            syncControlState();
            if (!$('#autoUpdate').is(':checked')) {
                if (window.__ddnsCountdownTimer) {
                    clearInterval(window.__ddnsCountdownTimer);
                    window.__ddnsCountdownTimer = null;
                }
                $('#nextRunCountdown').text('-');
                saveSchedule();
            } else {
                var mins = parseInt($('#intervalMinutes').val() || '5', 10);
                var nextEpoch = Math.floor(Date.now() / 1000) + (mins * 60);
                startNextRunCountdown(String(nextEpoch), $('#enabled').is(':checked'), true, true);
                saveSchedule();
            }
        });
        $('#intervalMinutes').on('change', function() {
            var mins = parseInt($('#intervalMinutes').val() || '5', 10);
            if (mins < 1 || mins > 60) {
                $('#intervalMinutes').val('5');
            }
            if ($('#autoUpdate').is(':checked')) {
                var nextEpoch = Math.floor(Date.now() / 1000) + (parseInt($('#intervalMinutes').val() || '5', 10) * 60);
                startNextRunCountdown(String(nextEpoch), $('#enabled').is(':checked'), true, true);
            }
            saveSchedule();
        });
        renderUpdateUrlPreview();
        syncControlState();
        loadSettings(false);
        if (window.__ddnsAutoRefreshTimer) {
            clearInterval(window.__ddnsAutoRefreshTimer);
        }
        window.__ddnsAutoRefreshTimer = setInterval(function() {
            loadSettings(true);
        }, 15000);
    });
</script>
