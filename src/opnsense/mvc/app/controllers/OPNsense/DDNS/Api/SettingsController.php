<?php

/*
 * Copyright (C) 2026 Deciso B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace OPNsense\DDNS\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Base\UserException;
use OPNsense\Core\ACL;

class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'DDNS';
    protected static $internalModelClass = '\\OPNsense\\DDNS\\DDNS';

    private array $traceBuffer = [];

    public function getAction()
    {
        $result = [];
        if ($this->request->isGet()) {
            $result['general'] = $this->loadStoredSettings();
        }
        return $result;
    }

    public function setAction()
    {
        $result = ['result' => 'failed'];
        if ($this->request->isPost()) {
            $model = $this->getModel();
            $storedSettings = $this->loadStoredSettings(false);
            $postedSettings = $this->request->getPost('general');
            if (!is_array($postedSettings)) {
                $postedSettings = [];
            }
            $incoming = array_merge($storedSettings, $postedSettings);
            foreach ($incoming as $key => $value) {
                if (!is_scalar($value)) {
                    $incoming[$key] = '';
                } else {
                    $incoming[$key] = trim((string)$value);
                }
            }
            if (($incoming['token'] ?? '') === '') {
                $maybeToken = $this->normalizeToken((string)($incoming['tokenUpdateUrl'] ?? ''));
                if ($maybeToken !== '') {
                    $incoming['token'] = $maybeToken;
                }
            }

            $model->setNodes($incoming);
            $model->general->enabled = (($incoming['enabled'] ?? '0') === '1') ? '1' : '0';
            $model->general->autoUpdate = (($incoming['autoUpdate'] ?? '0') === '1') ? '1' : '0';
            $model->general->intervalMinutes = (string)$this->getIntervalMinutes($incoming);
            $model->general->queryUrl = (string)($incoming['queryUrl'] ?? (string)$model->general->queryUrl);
            $model->general->tokenUpdateUrl = (string)($incoming['tokenUpdateUrl'] ?? (string)$model->general->tokenUpdateUrl);
            $model->general->ownIp = (string)($incoming['ownIp'] ?? (string)$model->general->ownIp);
            $model->general->token = $this->encryptTokenForStorage((string)($incoming['token'] ?? (string)$model->general->token));
            $valMsgs = $model->performValidation();

            foreach ($valMsgs as $msg) {
                if (!array_key_exists('validations', $result)) {
                    $result['validations'] = [];
                }
                $result['validations']['general.' . $msg->getField()] = $msg->getMessage();
            }

            if ($valMsgs->count() > 0) {
                $firstMsg = '';
                foreach ($valMsgs as $msg) {
                    $firstMsg = $msg->getField() . ': ' . $msg->getMessage();
                    break;
                }
                $model->general->logEntries = $this->appendLogEntry(
                    (string)$model->general->logEntries,
                    false,
                    'Speichern fehlgeschlagen',
                    $firstMsg,
                    ''
                );
                $saveError = $this->saveModel();
                if ($saveError !== null) {
                    $result['message'] = $saveError;
                }
            }

            if ($valMsgs->count() === 0) {
                $autoEnabled = ((string)$model->general->autoUpdate) === '1';
                $interval = $this->getIntervalMinutes(['intervalMinutes' => (string)$model->general->intervalMinutes]);
                $model->general->nextRunEpoch = $autoEnabled ? (string)$this->computeNextCronEpoch($interval) : '0';
                if (!$autoEnabled) {
                    $model->general->currentState = 'unknown';
                }
                $model->general->logEntries = $this->appendLogEntry(
                    (string)$model->general->logEntries,
                    true,
                    'Einstellungen gespeichert',
                    'autoUpdate=' . (((string)$model->general->autoUpdate === '1') ? 'on' : 'off') . ', interval=' . (string)$model->general->intervalMinutes . 'm',
                    ''
                );
                $saveError = $this->saveModel();
                if ($saveError === null) {
                    $this->applyAutoUpdateSchedule($this->loadStoredSettings(false));
                    $result['result'] = 'saved';
                    $result['general'] = $this->loadStoredSettings();
                } else {
                    $result['result'] = 'failed';
                    $result['message'] = $saveError;
                }
            }
        }
        return $result;
    }

    public function testAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'failed', 'connected' => false, 'color' => 'red', 'message' => 'POST required'];
        }

        $settings = $this->request->getPost('general');
        if (empty($settings)) {
            $settings = $this->loadStoredSettings();
        }

        return $this->runUpdate($settings);
    }

    public function scheduleAction()
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed', 'message' => 'POST required'];
        }

        $model = $this->getModel();
        $posted = $this->request->getPost('general');
        if (!is_array($posted)) {
            $posted = [];
        }

        $previousEnabled = ((string)$model->general->enabled === '1');
        $previousAutoUpdate = ((string)$model->general->autoUpdate === '1');
        $enabled = (($posted['enabled'] ?? (string)$model->general->enabled) === '1') ? '1' : '0';
        $autoUpdate = (($posted['autoUpdate'] ?? (string)$model->general->autoUpdate) === '1') ? '1' : '0';
        $interval = (string)$this->getIntervalMinutes([
            'intervalMinutes' => (string)($posted['intervalMinutes'] ?? (string)$model->general->intervalMinutes)
        ]);

        $model->general->enabled = $enabled;
        $model->general->autoUpdate = $autoUpdate;
        $model->general->intervalMinutes = $interval;
        $isSchedulerActive = ($enabled === '1' && $autoUpdate === '1');
        $model->general->nextRunEpoch = $isSchedulerActive ? (string)$this->computeNextCronEpoch((int)$interval) : '0';
        if (!$isSchedulerActive) {
            $model->general->currentState = 'unknown';
        }
        $model->general->logEntries = $this->appendLogEntry(
            (string)$model->general->logEntries,
            true,
            'Zeitplan aktualisiert',
            'enabled=' . ($enabled === '1' ? 'on' : 'off') . ', autoUpdate=' . ($autoUpdate === '1' ? 'on' : 'off') . ', interval=' . $interval . 'm',
            ''
        );

        $saveError = $this->saveModel();
        if ($saveError !== null) {
            return ['result' => 'failed', 'message' => $saveError];
        }

        $current = $this->loadStoredSettings(false);
        $this->applyAutoUpdateSchedule($current);
        if ((!$previousEnabled || !$previousAutoUpdate) && $isSchedulerActive) {
            $this->traceLog('Auto-Prüfung aktiviert: starte ersten Lauf sofort');
            $this->runUpdate($current);
            $current = $this->loadStoredSettings(false);
        }
        return ['result' => 'saved', 'general' => $this->loadStoredSettings()];
    }

    public function runAction()
    {
        $settings = $this->loadStoredSettings();
        if ($this->request->isPost()) {
            $posted = $this->request->getPost('general');
            if (is_array($posted) && !empty($posted)) {
                $settings = array_merge($settings, $posted);
            }
        }
        return $this->runUpdate($settings);
    }

    public function clearlogAction()
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed', 'message' => 'POST required'];
        }

        $model = $this->getModel();
        $model->general->logEntries = '';
        $saveError = $this->saveModel();
        if ($saveError !== null) {
            return ['result' => 'failed', 'message' => $saveError];
        }

        if (@file_put_contents('/var/log/ddns_auto.log', '') === false) {
            error_log('DDNS: failed to clear /var/log/ddns_auto.log');
        }
        return ['result' => 'saved', 'general' => $this->loadStoredSettings()];
    }

    private function runUpdate(array $settings): array
    {
        $settings = array_merge($this->loadStoredSettings(), $settings);
        $this->traceLog('Run gestartet');

        if (($settings['enabled'] ?? '0') !== '1') {
            $this->traceLog('Abbruch: Plugin deaktiviert', false);
            return $this->statusResponse(false, 'Plugin disabled', '', '', $settings);
        }

        $this->traceLog('IP-Ermittlung gestartet');
        [$ipOk, $ip, $ipError, $ipSource] = $this->resolveIp($settings);
        if (!$ipOk) {
            $this->traceLog('IP-Ermittlung fehlgeschlagen: ' . $ipError, false);
            return $this->statusResponse(false, $ipError, '', '', $settings);
        }
        $this->traceLog('IP ermittelt: ' . $ip . ' (Quelle: ' . $ipSource . ')');

        $token = $this->normalizeToken((string)($settings['token'] ?? ''));
        if ($token === '') {
            $token = $this->normalizeToken((string)($settings['tokenUpdateUrl'] ?? ''));
        }
        if ($token === '') {
            $this->traceLog('Abbruch: Kein Token verfügbar', false);
            return $this->statusResponse(false, 'Token required', '', '', $settings);
        }

        $url = $this->buildTokenUpdateUrl($token, $ip, (string)($settings['tokenUpdateUrl'] ?? ''));
        if (!$this->isAllowedProviderUrl($url)) {
            $this->traceLog('Abbruch: Anbieter-URL ist nicht erlaubt', false);
            return $this->statusResponse(false, 'Update URL target is not allowed', '', '', $settings);
        }
        $this->traceLog('Update-URL: ' . $this->sanitizeUrlForLog($url));
        [$ok, $body, $error] = $this->httpRequest($url);

        if (!$ok) {
            $this->traceLog('Provider-Request fehlgeschlagen: ' . $error, false);
            return $this->statusResponse(false, 'Aktualisierung fehlgeschlagen: ' . $error, '', '', $settings);
        }

        $normalized = strtolower(trim((string)$body));
        $isNoChange = str_contains($normalized, 'has not changed') || str_contains($normalized, 'noch aktuell') || str_contains($normalized, 'is current') || str_contains($normalized, 'no update needed');
        $isError = (str_contains($normalized, 'error') && !$isNoChange)
            || str_contains($normalized, 'badauth')
            || str_contains($normalized, '911')
            || str_contains($normalized, 'invalid update url');

        $providerMessage = preg_replace('/^error:\s*/i', '', trim((string)$body));
        $this->traceLog('Provider-Antwort: ' . $this->sanitizeProviderMessage($providerMessage), !$isError);

        if ($isNoChange) {
            return $this->statusResponse(true, 'IP unverändert, kein Update nötig', $ip, $providerMessage, $settings);
        }

        if ($isError) {
            return $this->statusResponse(false, 'Aktualisierung fehlgeschlagen: ' . $providerMessage, $ip, $providerMessage, $settings);
        }

        return $this->statusResponse(true, 'Aktualisierung erfolgreich (' . $ipSource . ')', $ip, $providerMessage, $settings);
    }

    private function loadStoredSettings(bool $mergeCronTail = true): array
    {
        $cfg = $this->getModel();
        $cronActive = file_exists('/etc/cron.d/ddns_auto') ? '1' : '0';
        $storedLogEntries = (string)$cfg->general->logEntries;
        $cronLogTail = $this->readCronLogTail();
        if ($mergeCronTail && $cronLogTail !== '') {
            $merged = trim($storedLogEntries);
            $storedLogEntries = ($merged !== '' ? ($merged . "\n") : '') . "--- Cron Log ---\n" . $cronLogTail;
        }
        return [
            'enabled' => (string)$cfg->general->enabled,
            'autoUpdate' => (string)$cfg->general->autoUpdate,
            'intervalMinutes' => (string)$cfg->general->intervalMinutes,
            'queryUrl' => (string)$cfg->general->queryUrl,
            'tokenUpdateUrl' => (string)$cfg->general->tokenUpdateUrl,
            'ownIp' => (string)$cfg->general->ownIp,
            'token' => $this->decryptTokenForGui((string)$cfg->general->token),
            'lastStatus' => (string)$cfg->general->lastStatus,
            'lastMessage' => (string)$cfg->general->lastMessage,
            'lastProviderMessage' => (string)$cfg->general->lastProviderMessage,
            'currentState' => (string)$cfg->general->currentState,
            'lastCheckEpoch' => (string)$cfg->general->lastCheckEpoch,
            'nextRunEpoch' => (string)$cfg->general->nextRunEpoch,
            'logEntries' => $storedLogEntries,
            'cronLogTail' => $cronLogTail,
            'cronActive' => $cronActive,
        ];
    }

    private function readCronLogTail(): string
    {
        $path = '/var/log/ddns_auto.log';
        if (!is_readable($path)) {
            return '';
        }

        $content = @file_get_contents($path);
        if (!is_string($content) || $content === '') {
            return '';
        }

        $lines = preg_split('/\r\n|\r|\n/', $content);
        if (!is_array($lines)) {
            return '';
        }
        $lines = array_values(array_filter(array_map('trim', $lines), fn($line) => $line !== ''));
        if (count($lines) > 120) {
            $lines = array_slice($lines, -120);
        }
        return implode("\n", $lines);
    }

    public function detectipAction()
    {
        $settings = [];
        if ($this->request->isPost()) {
            $settings = $this->request->getPost('general') ?? [];
        }

        if (empty($settings)) {
            $settings = $this->loadStoredSettings();
        }

        [$ok, $ip, $error, $source] = $this->resolveIp($settings);
        if (!$ok) {
            $this->traceLog('DetectIP fehlgeschlagen: ' . $error, false);
            return [
                'status' => 'failed',
                'connected' => false,
                'color' => 'red',
                'message' => $error,
                'ip' => '',
                'source' => '',
            ];
        }

        return [
            'status' => 'ok',
            'connected' => true,
            'color' => 'green',
            'message' => 'IP detected automatically',
            'ip' => $ip,
            'source' => $source,
        ];
    }

    private function statusResponse(bool $connected, string $message, string $ip = '', string $providerMessage = '', array $settingsContext = []): array
    {
        $model = $this->getModel();
        $now = time();
        $baseSettings = $this->loadStoredSettings(false);
        $effectiveSettings = array_merge($baseSettings, $settingsContext);
        $interval = $this->getIntervalMinutes($effectiveSettings);
        $autoEnabled = ((string)($effectiveSettings['autoUpdate'] ?? '0')) === '1';
        $isCurrent = $connected && (str_contains(strtolower($message), 'unverändert') || str_contains(strtolower($providerMessage), 'has not changed'));

        $model->general->lastStatus = $connected ? 'ok' : 'failed';
        $model->general->lastMessage = $message;
        $model->general->lastProviderMessage = $providerMessage;
        $model->general->currentState = $isCurrent ? 'current' : ($connected ? 'updated' : 'error');
        $model->general->lastCheckEpoch = (string)$now;
        $model->general->nextRunEpoch = $autoEnabled ? (string)$this->computeNextCronEpoch($interval) : '0';
        $logEntries = (string)$model->general->logEntries;
        foreach ($this->traceBuffer as $entry) {
            $logEntries = $this->appendLogEntry($logEntries, (bool)$entry['ok'], (string)$entry['message'], '', '');
        }
        $this->traceBuffer = [];
        $model->general->logEntries = $this->appendLogEntry($logEntries, $connected, $message, $providerMessage, $ip);

        $saveError = $this->saveModel();
        if ($saveError !== null) {
            error_log('DDNS statusResponse: ' . $saveError);
        }

        return [
            'status' => $connected ? 'ok' : 'failed',
            'connected' => $connected,
            'color' => $connected ? 'green' : 'red',
            'message' => $message,
            'providerMessage' => $providerMessage,
            'ip' => $ip,
            'currentState' => $isCurrent ? 'current' : ($connected ? 'updated' : 'error'),
            'lastCheckEpoch' => (string)$now,
            'nextRunEpoch' => $autoEnabled ? (string)$this->computeNextCronEpoch($interval) : '0',
        ];
    }

    private function computeNextCronEpoch(int $intervalMinutes): int
    {
        $interval = $intervalMinutes;
        if ($interval < 1) {
            $interval = 1;
        }
        if ($interval > 60) {
            $interval = 60;
        }

        $now = time();
        $minute = (int)date('i', $now);
        $second = (int)date('s', $now);

        $nextMinute = (int)(ceil(($minute + 1) / $interval) * $interval);
        $hourCarry = 0;
        if ($nextMinute >= 60) {
            $nextMinute -= 60;
            $hourCarry = 1;
        }

        $next = mktime((int)date('H', $now) + $hourCarry, $nextMinute, 0, (int)date('n', $now), (int)date('j', $now), (int)date('Y', $now));
        return $next;
    }

    private function getIntervalMinutes(array $settings): int
    {
        $value = (int)($settings['intervalMinutes'] ?? 5);
        if ($value < 1) {
            $value = 1;
        }
        if ($value > 60) {
            $value = 60;
        }
        return $value;
    }

    private function appendLogEntry(string $existing, bool $ok, string $message, string $providerMessage, string $ip): string
    {
        $lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $existing)));
        $timestamp = date('Y-m-d H:i:s');
        $state = $ok ? 'OK' : 'ERR';
        $line = $timestamp . ' [' . $state . '] ' . $message;
        if ($ip !== '') {
            $line .= ' | IP: ' . $ip;
        }
        if (trim($providerMessage) !== '') {
            $line .= ' | Provider: ' . trim($providerMessage);
        }
        $lines[] = $line;
        if (count($lines) > 200) {
            $lines = array_slice($lines, -200);
        }
        return implode("\n", $lines);
    }

    private function traceLog(string $message, bool $ok = true): void
    {
        $this->traceBuffer[] = [
            'ok' => $ok,
            'message' => $message,
        ];
    }

    private function sanitizeUrlForLog(string $url): string
    {
        $value = trim($url);
        if ($value === '') {
            return '';
        }
        $value = preg_replace('#(token=)[^&]+#i', '$1***', $value);
        $value = preg_replace('#(key=)[^&]+#i', '$1***', $value);
        $value = preg_replace('#(/u/)[^/?]+#i', '$1***', $value);
        return (string)$value;
    }

    private function sanitizeProviderMessage(string $message): string
    {
        $value = trim($message);
        if ($value === '') {
            return '';
        }
        $value = preg_replace('#(token=)[^&\s]+#i', '$1***', $value);
        $value = preg_replace('#(key=)[^&\s]+#i', '$1***', $value);
        $value = preg_replace('#(/u/)[^/?\s]+#i', '$1***', $value);
        return (string)$value;
    }

    private function applyAutoUpdateSchedule(array $settings): void
    {
        $enabled = ((string)($settings['enabled'] ?? '0')) === '1';
        $autoUpdate = ((string)($settings['autoUpdate'] ?? '0')) === '1';
        $schedulerActive = $enabled && $autoUpdate;
        $interval = $this->getIntervalMinutes($settings);
        $cronPath = '/etc/cron.d/ddns_auto';

        if (!$schedulerActive) {
            if (file_exists($cronPath) && !@unlink($cronPath)) {
                error_log('DDNS: failed to remove cron file at ' . $cronPath);
            }
            $this->restartCronService();
            return;
        }

        $line = '*/' . $interval . ' * * * * root /usr/local/bin/python3 /usr/local/opnsense/scripts/ddns_auto_update.py >> /var/log/ddns_auto.log 2>&1';
        $written = @file_put_contents($cronPath, $line . "\n");
        if ($written === false) {
            error_log('DDNS: failed to write cron file at ' . $cronPath);
            return;
        }

        if (!@chmod($cronPath, 0644)) {
            error_log('DDNS: failed to chmod cron file at ' . $cronPath);
        }
        $this->restartCronService();
    }

    private function restartCronService(): void
    {
        $output = [];
        $exitCode = 0;
        @exec('service cron restart 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            error_log('DDNS: failed to restart cron (exit=' . $exitCode . '): ' . implode("\n", $output));
        }
    }

    private function throwReadOnly(): void
    {
        if ((new ACL())->hasPrivilege($this->getUserName(), 'user-config-readonly')) {
            throw new UserException(
                sprintf('User %s denied for write access (user-config-readonly set)', $this->getUserName())
            );
        }
    }

    private function saveModel(): ?string
    {
        try {
            $this->throwReadOnly();
            $this->save();
            return null;
        } catch (\Throwable $e) {
            error_log('DDNS SettingsController save failed: ' . get_class($e) . ': ' . $e->getMessage());
            return 'internal error while saving configuration';
        }
    }

    private function isAllowedProviderUrl(string $url): bool
    {
        $parsed = parse_url($url);
        if (!is_array($parsed)) {
            return false;
        }

        $scheme = strtolower((string)($parsed['scheme'] ?? ''));
        if ($scheme !== 'https' && $scheme !== 'http') {
            return false;
        }

        $host = strtolower((string)($parsed['host'] ?? ''));
        if ($host === '') {
            return false;
        }

        $allowed = [
            'ddns.afraid.org',
            'sync.afraid.org',
            'dynv6.com',
            'ipv64.net',
            'www.duckdns.org',
        ];
        return in_array($host, $allowed, true);
    }

    private function buildTokenUpdateUrl(string $tokenOrUrl, string $ip, string $customTemplate = ''): string
    {
        $value = $this->normalizeToken($tokenOrUrl);

        $value = trim($value, "/ \t\n\r\0\x0B");
        $template = trim($customTemplate);
        if ($template === '' || preg_match('#^https?://[^/]*afraid\.org/u/.+#i', $template)) {
            $template = 'https://ddns.afraid.org/dynamic/update.php?{token}';
        }

        if (str_contains($template, '{token}')) {
            $template = str_replace('{token}', $value, $template);
            return str_replace('{ip}', rawurlencode($ip), $template);
        }

        if (str_contains($template, '%s')) {
            $result = sprintf($template, $value);
            return str_replace('{ip}', rawurlencode($ip), $result);
        }

        $result = rtrim($template, '/') . '/' . $value . '/';
        return str_replace('{ip}', rawurlencode($ip), $result);
    }

    private function normalizeToken(string $tokenOrUrl): string
    {
        $value = trim($tokenOrUrl);
        if ($value === '') {
            return '';
        }

        if (str_contains($value, '{token}') || str_contains($value, '{') || str_contains($value, '}')) {
            return '';
        }

        if (preg_match('#^https?://[^/]*afraid\.org/u/([^/?]+)#i', $value, $m)) {
            $token = rawurldecode($m[1]);
            if (str_contains($token, '{') || str_contains($token, '}')) {
                return '';
            }
            return $token;
        }

        if (preg_match('#^https?://ddns\.afraid\.org/dynamic/update\.php\?([^&]+)#i', $value, $m)) {
            $token = rawurldecode($m[1]);
            if (str_contains($token, '{') || str_contains($token, '}')) {
                return '';
            }
            return $token;
        }

        return trim($value, "/ \t\n\r\0\x0B");
    }

    private function encryptTokenForStorage(string $token): string
    {
        $plain = trim($token);
        if ($plain === '') {
            return '';
        }
        if ($this->isEncryptedToken($plain)) {
            return $plain;
        }

        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plain, 'AES-256-CBC', $this->getTokenCryptoKey(), OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            return $plain;
        }

        return 'enc:v1:' . base64_encode($iv . $cipher);
    }

    private function decryptTokenForGui(string $stored): string
    {
        $value = trim($stored);
        if ($value === '') {
            return '';
        }
        if (!$this->isEncryptedToken($value)) {
            return $value;
        }

        $raw = base64_decode(substr($value, 7), true);
        if ($raw === false || strlen($raw) <= 16) {
            return '';
        }

        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $plain = openssl_decrypt($cipher, 'AES-256-CBC', $this->getTokenCryptoKey(), OPENSSL_RAW_DATA, $iv);
        return $plain === false ? '' : trim((string)$plain);
    }

    private function isEncryptedToken(string $value): bool
    {
        return str_starts_with($value, 'enc:v1:');
    }

    private function getTokenCryptoKey(): string
    {
        $host = gethostname();
        if (!is_string($host) || $host === '') {
            $host = 'opnsense';
        }
        return hash('sha256', $host . '|ddns-token-key-v1', true);
    }

    private function resolveIp(array $settings): array
    {
        $manualIp = trim((string)($settings['ownIp'] ?? ''));
        if ($manualIp !== '') {
            if (!filter_var($manualIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return [false, '', 'Manual IP is invalid', ''];
            }
            return [true, $manualIp, '', 'manual'];
        }

        $queryUrl = trim((string)($settings['queryUrl'] ?? ''));
        if ($queryUrl === '') {
            return [false, '', 'IP query URL missing', ''];
        }

        $candidates = [$queryUrl];
        foreach ($this->getFallbackIpResolvers() as $resolver) {
            if (!in_array($resolver, $candidates, true)) {
                $candidates[] = $resolver;
            }
        }

        $errors = [];
        foreach ($candidates as $index => $candidateUrl) {
            [$ok, $body, $error] = $this->httpRequest($candidateUrl);
            if (!$ok) {
                $errors[] = $candidateUrl . ' => ' . $error;
                $this->traceLog('Resolver fehlgeschlagen: ' . $candidateUrl . ' => ' . $error, false);
                continue;
            }

            $ip = $this->extractIpv4((string)$body);
            if ($ip === null) {
                $errors[] = $candidateUrl . ' => no IPv4 in response';
                $this->traceLog('Resolver ohne IPv4: ' . $candidateUrl, false);
                continue;
            }

            if ($index > 0) {
                $this->traceLog('Fallback-Resolver genutzt: ' . $candidateUrl);
            }
            return [true, $ip, '', 'auto'];
        }

        $errorText = 'IP query failed';
        if (!empty($errors)) {
            $errorText .= ': ' . implode(' | ', array_slice($errors, 0, 3));
        }
        return [false, '', $errorText, ''];
    }

    private function getFallbackIpResolvers(): array
    {
        return [
            'http://checkip.amazonaws.com',
            'http://ipinfo.io/ip',
            'http://icanhazip.com/',
            'http://ifconfig.me/ip',
            'http://ident.me/',
            'http://myexternalip.com/raw',
            'http://checkip.dns.he.net/',
            'http://bot.whatismyipaddress.com/',
            'http://domains.google.com/checkip',
            'http://ipecho.net/plain',
            'http://ddns.afraid.org/dynamic/check.php',
        ];
    }

    private function extractIpv4(string $content): ?string
    {
        if (preg_match('/\b((25[0-5]|2[0-4]\d|1?\d?\d)\.){3}(25[0-5]|2[0-4]\d|1?\d?\d)\b/', $content, $matches)) {
            return $matches[0];
        }
        return null;
    }

    private function httpRequest(string $url, ?string $username = null, ?string $password = null): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        if ($username !== null && $password !== null) {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
        }

        $body = curl_exec($ch);
        $err = curl_error($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $http < 200 || $http >= 300) {
            return [false, '', $err !== '' ? $err : 'HTTP ' . (string)$http];
        }

        return [true, (string)$body, ''];
    }
}
