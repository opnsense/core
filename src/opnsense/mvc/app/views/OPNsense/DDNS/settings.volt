<section class="page-content-main">
    <div class="container-fluid">
        <h1>DDNS</h1>

        <div class="panel panel-default">
            <div class="panel-heading">Einstellungen</div>
            <div class="panel-body">
                <form id="ddns-form" class="form-horizontal">
                    <div class="form-group">
                        <label class="col-sm-3 control-label">Aktiviert</label>
                        <div class="col-sm-9">
                            <input id="enabled" type="checkbox" checked>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-3 control-label">Eigene IP (optional)</label>
                        <div class="col-sm-9">
                            <input id="ownIp" class="form-control" placeholder="z. B. 203.0.113.10">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-3 control-label">Auto-Prüfung (Cron)</label>
                        <div class="col-sm-9">
                            <label style="margin-right:12px;"><input id="autoUpdate" type="checkbox"> Aktiv</label>
                            <select id="intervalMinutes" class="form-control" style="display:inline-block; width:auto; min-width:120px; margin-left:8px;">
                                <option value="1">1 Minute</option>
                                <option value="2">2 Minuten</option>
                                <option value="3">3 Minuten</option>
                                <option value="4">4 Minuten</option>
                                <option value="5">5 Minuten</option>
                                <option value="6">6 Minuten</option>
                                <option value="7">7 Minuten</option>
                                <option value="8">8 Minuten</option>
                                <option value="9">9 Minuten</option>
                                <option value="10">10 Minuten</option>
                                <option value="11">11 Minuten</option>
                                <option value="12">12 Minuten</option>
                                <option value="13">13 Minuten</option>
                                <option value="14">14 Minuten</option>
                                <option value="15">15 Minuten</option>
                                <option value="16">16 Minuten</option>
                                <option value="17">17 Minuten</option>
                                <option value="18">18 Minuten</option>
                                <option value="19">19 Minuten</option>
                                <option value="20">20 Minuten</option>
                                <option value="21">21 Minuten</option>
                                <option value="22">22 Minuten</option>
                                <option value="23">23 Minuten</option>
                                <option value="24">24 Minuten</option>
                                <option value="25">25 Minuten</option>
                                <option value="26">26 Minuten</option>
                                <option value="27">27 Minuten</option>
                                <option value="28">28 Minuten</option>
                                <option value="29">29 Minuten</option>
                                <option value="30">30 Minuten</option>
                                <option value="31">31 Minuten</option>
                                <option value="32">32 Minuten</option>
                                <option value="33">33 Minuten</option>
                                <option value="34">34 Minuten</option>
                                <option value="35">35 Minuten</option>
                                <option value="36">36 Minuten</option>
                                <option value="37">37 Minuten</option>
                                <option value="38">38 Minuten</option>
                                <option value="39">39 Minuten</option>
                                <option value="40">40 Minuten</option>
                                <option value="41">41 Minuten</option>
                                <option value="42">42 Minuten</option>
                                <option value="43">43 Minuten</option>
                                <option value="44">44 Minuten</option>
                                <option value="45">45 Minuten</option>
                                <option value="46">46 Minuten</option>
                                <option value="47">47 Minuten</option>
                                <option value="48">48 Minuten</option>
                                <option value="49">49 Minuten</option>
                                <option value="50">50 Minuten</option>
                                <option value="51">51 Minuten</option>
                                <option value="52">52 Minuten</option>
                                <option value="53">53 Minuten</option>
                                <option value="54">54 Minuten</option>
                                <option value="55">55 Minuten</option>
                                <option value="56">56 Minuten</option>
                                <option value="57">57 Minuten</option>
                                <option value="58">58 Minuten</option>
                                <option value="59">59 Minuten</option>
                                <option value="60">60 Minuten</option>
                            </select>
                            <small class="help-block" style="margin-top:6px;">Nächste Prüfung in: <span id="nextRunCountdown">-</span></small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-3 control-label">IP-Abfrage URL (Homepage)</label>
                        <div class="col-sm-9">
                            <select id="queryUrlPreset" class="form-control" style="margin-bottom:8px;">
                                <option value="https://api.ipify.org">api.ipify.org (HTTPS)</option>
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
                                <option value="custom">Eigene URL (Custom)</option>
                            </select>
                            <input id="queryUrl" class="form-control" value="https://api.ipify.org">
                            <small id="queryPresetHint" class="help-block" style="margin-top:4px;color:#666;">Provider für IP-Erkennung auswählen oder eigene URL verwenden.</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-3 control-label">Update URL Template</label>
                        <div class="col-sm-9">
                            <select id="tokenUpdatePreset" class="form-control" style="margin-bottom:8px;">
                                <option value="https://ddns.afraid.org/dynamic/update.php?{token}">DDNS Standard (dynamic/update.php?TOKEN)</option>
                                <option value="https://ddns.afraid.org:8080/dynamic/update.php?{token}">DDNS Standard Port 8080</option>
                                <option value="https://dynv6.com/api/update?token={token}&amp;ipv4={ip}">dynv6 Token (dynv6.com/api/update)</option>
                                <option value="https://ipv64.net/nic/update?key={token}&amp;ip={ip}">IPv64 Key/Token (ipv64.net/nic/update)</option>
                                <option value="https://www.duckdns.org/update?domains=YOURDOMAIN&amp;token={token}&amp;ip={ip}">DuckDNS Token (Domain im Template setzen)</option>
                                <option value="custom">Eigene URL (Custom)</option>
                            </select>
                            <input id="tokenUpdateUrl" class="form-control" value="https://ddns.afraid.org/dynamic/update.php?{token}">
                            <small class="help-block">Platzhalter: {token} und optional {ip} (je nach Provider)</small>
                            <small id="presetHint" class="help-block" style="margin-top:4px;color:#666;"></small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-3 control-label">Erkannte öffentliche IP</label>
                        <div class="col-sm-9">
                            <input id="detectedIp" class="form-control" readonly placeholder="wird automatisch ermittelt...">
                            <small id="detectedIpInfo" class="help-block">Quelle: automatisch über Abfrage-URL</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-3 control-label">DDNS Token</label>
                        <div class="col-sm-9">
                            <div style="margin-bottom:6px;color:#c62828;font-weight:600;">TOKEN-FELD (sichtbar)</div>
                                <div style="display:flex;gap:8px;align-items:center;">
                                    <input id="token" type="password" class="form-control" placeholder="Token oder komplette v2 URL (sync.afraid.org/u/.../)">
                                    <button type="button" id="toggleTokenVisibilityBtn" class="btn btn-default btn-sm">Anzeigen</button>
                                </div>
                                <small class="help-block" style="margin-top:6px;">Token standardmäßig verborgen. Mit „Anzeigen“ entsperren.</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-3 control-label">Update-URL Vorschau</label>
                        <div class="col-sm-9">
                            <div id="updateUrlPreview" style="padding:8px 10px;border:1px solid #ddd;border-radius:4px;background:#fafafa;word-break:break-all;">-</div>
                            <div style="margin-top:8px;">
                                <button type="button" id="copyPreviewBtn" class="btn btn-default btn-xs">Vorschau kopieren</button>
                                <span id="copyPreviewInfo" style="margin-left:8px;color:#666;"></span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="col-sm-offset-3 col-sm-9">
                            <button type="button" id="runBtn" class="btn btn-default">Jetzt aktualisieren</button>
                            <button type="button" id="saveBtn" class="btn btn-primary">Speichern</button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-3 control-label">Status</label>
                        <div class="col-sm-9">
                            <span id="statusBadge" style="display:inline-block;padding:6px 12px;border-radius:4px;background:#999;color:#fff;">Unbekannt</span>
                            <span id="currentStateBadge" style="display:inline-block;padding:6px 12px;border-radius:4px;background:#666;color:#fff;margin-left:8px;">Nicht geprüft</span>
                            <span id="statusText" style="margin-left:8px;"></span>
                            <div id="providerMessage" style="margin-top:8px;color:#555;"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-3 control-label">Protokoll</label>
                        <div class="col-sm-9">
                            <textarea id="logEntries" class="form-control" rows="12" readonly style="font-family:monospace;font-size:12px;line-height:1.35;"></textarea>
                            <div style="margin-top:8px;">
                                <button type="button" id="clearLogBtn" class="btn btn-default btn-xs">Protokoll leeren</button>
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
        var label = 'Unbekannt';
        if (color === 'green') {
            bg = '#2e7d32';
            label = 'Verbunden';
        } else if (color === 'red') {
            bg = '#c62828';
            label = 'Fehler';
        }
        $('#statusBadge').css('background', bg).text(label);
        $('#statusText').text(text || '');
    }

    function setProviderMessage(message) {
        $('#providerMessage').text(message || '');
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
            return { color: 'green', text: 'Letzter Auto-Lauf erfolgreich' };
        }
        if (last.indexOf('failed') >= 0 || last.indexOf('rejected') >= 0 || last.indexOf('token missing') >= 0 || last.indexOf('resolve ip failed') >= 0) {
            return { color: 'red', text: 'Letzter Auto-Lauf fehlgeschlagen' };
        }
        return null;
    }

    function setCurrentState(state) {
        var label = 'Nicht aktuell';
        var color = '#c62828';
        if (state === 'current') {
            label = 'Aktuell';
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
            $('#queryPresetHint').text('Preset gewählt.');
        } else {
            $('#queryUrlPreset').val('custom');
            $('#queryUrl').prop('readonly', false);
            $('#queryPresetHint').text('Custom: eigene IP-Abfrage-URL verwenden.');
        }
    }

    function updateQueryUrlMode() {
        var preset = $('#queryUrlPreset').val();
        if (preset === 'custom') {
            $('#queryUrl').prop('readonly', false);
            $('#queryPresetHint').text('Custom: eigene IP-Abfrage-URL verwenden.');
            return;
        }
        $('#queryUrl').val(preset);
        $('#queryUrl').prop('readonly', true);
        $('#queryPresetHint').text('Preset gewählt.');
        refreshDetectedIp();
    }

    function updateTokenTemplateMode() {
        var preset = $('#tokenUpdatePreset').val();
        if (preset === 'custom') {
            $('#tokenUpdateUrl').prop('readonly', false);
            setPresetHint('Custom: eigene Anbieter-URL, {token} muss enthalten sein, {ip} optional.');
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
            'https://ddns.afraid.org/dynamic/update.php?{token}': 'DDNS Standard: Link-Format dynamic/update.php?TOKEN.',
            'https://ddns.afraid.org:8080/dynamic/update.php?{token}': 'DDNS über Port 8080, falls Standard-Port geblockt ist.',
            'https://dynv6.com/api/update?token={token}&ipv4={ip}': 'dynv6: API-Token aus Account nutzen; IPv4 wird über ipv4={ip} gesetzt.',
            'https://ipv64.net/nic/update?key={token}&ip={ip}': 'IPv64: Key/Token aus IPv64 verwenden; IP wird als ip={ip} übergeben.'
            , 'https://www.duckdns.org/update?domains=YOURDOMAIN&token={token}&ip={ip}': 'DuckDNS: YOURDOMAIN im Template ersetzen, Token bleibt im Token-Feld.'
        };
        setPresetHint(hints[value] || 'Preset gewählt. Prüfe bitte die API-Dokumentation deines Providers.');
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
            setPresetHint('Custom: eigene Anbieter-URL, {token} muss enthalten sein, {ip} optional.');
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
            $('#copyPreviewInfo').text('Keine Vorschau zum Kopieren');
            return;
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                $('#copyPreviewInfo').text('In Zwischenablage kopiert');
            }).catch(function() {
                $('#copyPreviewInfo').text('Kopieren fehlgeschlagen');
            });
            return;
        }

        var temp = $('<textarea>').val(text).appendTo('body').select();
        try {
            document.execCommand('copy');
            $('#copyPreviewInfo').text('In Zwischenablage kopiert');
        } catch (e) {
            $('#copyPreviewInfo').text('Kopieren fehlgeschlagen');
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
                setStatus('green', lastMessageValue || 'Verbindung erfolgreich');
            } else if (lastStatusValue === 'failed') {
                setStatus('red', lastMessageValue || 'Verbindung fehlgeschlagen');
            } else {
                var inferred = inferStatusFromCronTail(cronLogTailValue);
                if (inferred) {
                    setStatus(inferred.color, inferred.text);
                } else {
                    setStatus('red', 'Noch kein bestätigter Lauf');
                }
            }
            setCurrentState(currentStateValue || 'unknown');
            var mergedLog = (logEntriesValue || '').trim();
            var cronTail = (cronLogTailValue || '').trim();
            if (cronTail !== '') {
                mergedLog = (mergedLog !== '' ? (mergedLog + "\n") : '') + "--- Cron Log ---\n" + cronTail;
            }
            setLogEntries(mergedLog);
            setProviderMessage(providerMessageValue || '');
            startNextRunCountdown(nextRunEpochValue || '0', toBool(enabledValue), toBool(autoUpdateValue), toBool(cronActiveValue));

            if (!skipDetectIp) {
                refreshDetectedIp();
            }
        });
    }

    function refreshDetectedIp() {
        $('#detectedIp').val('');
        $('#detectedIpInfo').text('IP wird ermittelt ...');

        $.ajax({
            url: '/api/ddns/settings/detectip',
            method: 'POST',
            data: collectPayload(),
            success: function(resp) {
                if (resp && resp.ip) {
                    $('#detectedIp').val(resp.ip);
                    $('#detectedIpInfo').text((resp.source === 'manual') ? 'Quelle: manuell' : 'Quelle: automatisch über Abfrage-URL');
                } else {
                    $('#detectedIp').val('');
                    $('#detectedIpInfo').text((resp && resp.message) ? resp.message : 'IP konnte nicht ermittelt werden');
                }
                renderUpdateUrlPreview();
            },
            error: function() {
                $('#detectedIp').val('');
                $('#detectedIpInfo').text('IP konnte nicht ermittelt werden');
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
                    setStatus('green', 'Einstellungen gespeichert');
                    loadSettings();
                } else {
                    var validationMessage = '';
                    if (resp && resp.validations) {
                        var keys = Object.keys(resp.validations);
                        if (keys.length > 0) {
                            validationMessage = resp.validations[keys[0]];
                        }
                    }
                    setStatus('red', validationMessage ? ('Speichern fehlgeschlagen: ' + validationMessage) : 'Speichern fehlgeschlagen');
                }
                setProviderMessage('');
            },
            error: function() {
                setStatus('red', 'Speichern fehlgeschlagen');
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
                    setStatus('green', 'Zeitplan gespeichert');
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
                    setStatus('red', 'Zeitplan speichern fehlgeschlagen');
                }
            },
            error: function() {
                setStatus('red', 'Zeitplan speichern fehlgeschlagen');
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
                    setStatus('green', resp.message || 'Verbindung erfolgreich');
                } else {
                    setStatus('red', (resp && resp.message) ? resp.message : 'Keine Verbindung');
                }
                setProviderMessage((resp && resp.providerMessage) ? resp.providerMessage : '');
            },
            error: function() {
                setStatus('red', 'Test fehlgeschlagen');
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
                    setStatus('green', resp.message || 'Aktualisierung erfolgreich');
                } else {
                    setStatus('red', (resp && resp.message) ? resp.message : 'Aktualisierung fehlgeschlagen');
                }
                setProviderMessage((resp && resp.providerMessage) ? resp.providerMessage : '');
                setCurrentState((resp && resp.currentState) ? resp.currentState : ((resp && resp.connected) ? 'updated' : 'error'));
                startNextRunCountdown((resp && resp.nextRunEpoch) ? resp.nextRunEpoch : '0', $('#enabled').is(':checked'), $('#autoUpdate').is(':checked'), true);
                if (resp && resp.ip) {
                    $('#detectedIp').val(resp.ip);
                    $('#detectedIpInfo').text('Quelle: gespeichert/automatisch');
                    renderUpdateUrlPreview();
                }
                loadSettings();
            },
            error: function() {
                setStatus('red', 'Aktualisierung fehlgeschlagen');
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
                    $('#clearLogInfo').text('Protokoll geleert');
                } else {
                    $('#clearLogInfo').text('Löschen fehlgeschlagen');
                }
            },
            error: function() {
                $('#clearLogInfo').text('Löschen fehlgeschlagen');
            }
        });
    }

    $(function() {
        $('#toggleTokenVisibility').on('click', function() {
            var tokenInput = $('#token');
            var isHidden = tokenInput.attr('type') === 'password';
            tokenInput.attr('type', isHidden ? 'text' : 'password');
            $('#toggleTokenVisibility').text(isHidden ? 'Verbergen' : 'Anzeigen');
        });
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
                    $('#toggleTokenVisibilityBtn').text('Verbergen');
                } else {
                    tokenInput.attr('type', 'password');
                    $('#toggleTokenVisibilityBtn').text('Anzeigen');
                }
                renderUpdateUrlPreview();
            });
        $('#tokenUpdateUrl').on('input', renderUpdateUrlPreview);
        $('#ownIp').on('blur', refreshDetectedIp);
        $('#copyPreviewBtn').on('click', copyPreviewUrl);
        $('#clearLogBtn').on('click', clearLogEntries);
        $('#saveBtn').on('click', saveSettings);
        $('#runBtn').on('click', runUpdateNow);
        $('#enabled').on('change', function() {
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
        loadSettings(false);
        if (window.__ddnsAutoRefreshTimer) {
            clearInterval(window.__ddnsAutoRefreshTimer);
        }
        window.__ddnsAutoRefreshTimer = setInterval(function() {
            loadSettings(true);
        }, 15000);
    });
</script>
