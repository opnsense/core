<?php

/*
 * Copyright (C) 2026 Greelan
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

namespace OPNsense\Ntpd\Migrations;

use OPNsense\Base\BaseModelMigration;
use OPNsense\Core\Config;
use OPNsense\Core\Syslog;

class M1_0_0 extends BaseModelMigration
{
    /**
     * config.xml cannot hold control bytes or invalid UTF-8
     */
    private function isText($value)
    {
        return preg_match('/^[^\x00-\x08\x0b\x0c\x0e-\x1f\x7f]*$/u', $value) === 1;
    }

    /**
     * legacy checked integers with is_numeric() and stored them as entered, ntpd read "07" as 7
     */
    private function toInteger($value)
    {
        $value = trim($value);
        return ctype_digit($value) ? (string)(int)$value : $value;
    }

    public function run($model)
    {
        $config = Config::getInstance()->object();
        $legacy = $config->ntpd ?? null;
        $logger = new Syslog('config', null, LOG_LOCAL2);

        if ($legacy !== null) {
            if (!empty((string)$legacy->custom_options)) {
                $logger->warning('NTP custom options are no longer supported and were not migrated');
            }

            /* legacy inverted booleans: present in config = restriction disabled */
            foreach (['kod', 'limited', 'nomodify', 'nopeer', 'notrap'] as $field) {
                if (!empty((string)$legacy->$field)) {
                    $model->$field = '0';
                }
            }

            /* legacy 'query' inverted and renamed to 'noquery': absent in config = restriction enabled */
            $model->noquery = empty((string)$legacy->query) ? '1' : '0';

            /* normalize standard booleans from legacy "on" to model "1" */
            $booleans = [
                'clientmode', 'logpeer', 'logsys', 'clockstats', 'loopstats', 'peerstats', 'statsgraph', 'noserve'
            ];
            foreach ($booleans as $field) {
                if (!empty((string)$legacy->$field)) {
                    $model->$field = '1';
                }
            }

            /* make sure stale interfaces do not cause a validation issue */
            $model->interface = (string)$legacy->interface;
            $model->interface->normalizeValue();
            if ((string)$legacy->interface !== '' && (string)$model->interface === '') {
                $logger->warning(sprintf(
                    'NTP interfaces %s no longer exist, NTP now listens on all interfaces',
                    (string)$legacy->interface
                ));
            }

            /* copy same-named scalar fields, legacy wrote the default for 0 */
            foreach (['orphan', 'maxclock'] as $field) {
                if (!empty((string)$legacy->$field)) {
                    $model->$field = $this->toInteger((string)$legacy->$field);
                }
            }

            if (!empty((string)$legacy->leapsec)) {
                $leapsec = base64_decode((string)$legacy->leapsec);
                if ($this->isText($leapsec)) {
                    $model->leapsec = $leapsec;
                } else {
                    $logger->warning('NTP leap second file contains binary data and was not migrated');
                }
            }

            /* GPS fields */
            if (!empty($legacy->gps)) {
                if ((string)$legacy->gps->speed !== '') {
                    $model->gps->speed = (string)$legacy->gps->speed;
                }

                if (!empty((string)$legacy->gps->initcmd)) {
                    $initcmd = base64_decode((string)$legacy->gps->initcmd);
                    if ($this->isText($initcmd)) {
                        $model->gps->initcmd = $initcmd;
                    } else {
                        $logger->warning('NTP GPS initialization commands contain binary data and were not migrated');
                    }
                }

                /* GPS prefer is inverted in legacy: present = disabled */
                if (!empty((string)$legacy->gps->prefer)) {
                    $model->gps->prefer = '0';
                }

                /* convert nmea bitmask (e.g. 3 = RMC+GGA) to a list of its bits (e.g. "1,2") */
                $nmea_int = (int)(string)$legacy->gps->nmea;
                if ($nmea_int > 0) {
                    $nmea_parts = [];
                    foreach ([1, 2, 4, 8] as $bit) {
                        if ($nmea_int & $bit) {
                            $nmea_parts[] = $bit;
                        }
                    }
                    $model->gps->nmea = implode(',', $nmea_parts);
                }

                /* normalize GPS booleans from legacy "on" to model "1" */
                foreach (['flag1', 'flag2', 'flag3', 'flag4', 'subsec', 'noselect'] as $field) {
                    if (!empty((string)$legacy->gps->$field)) {
                        $model->gps->$field = '1';
                    } else {
                        $model->gps->$field = '0';
                    }
                }

                /* ntpd ignores prefer on a noselect clock, the model rejects both */
                if ((string)$model->gps->noselect == '1') {
                    $model->gps->prefer = '0';
                }

                /* legacy type "U-Blox" is option key "ublox" */
                if ((string)$legacy->gps->type !== '') {
                    $model->gps->type = strtolower(str_replace('-', '', (string)$legacy->gps->type));
                }

                /* copy same-named GPS scalar fields, legacy stored them untrimmed */
                foreach (['port', 'fudge1', 'fudge2', 'stratum', 'refid'] as $field) {
                    $value = trim((string)$legacy->gps->$field);
                    if ($value !== '') {
                        $model->gps->$field = $field == 'stratum' ? $this->toInteger($value) : $value;
                    }
                }
                /* keep the legacy port listed while its device is absent, as for a loaded port */
                $model->gps->port->eventPostLoading();
            }

            /* PPS fields */
            if (!empty($legacy->pps)) {
                /* hand edits only, the legacy PPS page dropped them on save */
                foreach (['prefer', 'noselect'] as $field) {
                    if (!empty((string)$legacy->pps->$field)) {
                        $logger->warning(sprintf('NTP PPS option %s was not migrated', $field));
                    }
                }

                /* normalize PPS booleans from legacy "on" to model "1" */
                foreach (['flag2', 'flag3', 'flag4'] as $field) {
                    if (!empty((string)$legacy->pps->$field)) {
                        $model->pps->$field = '1';
                    }
                }

                /* copy same-named PPS scalar fields, legacy stored them untrimmed */
                foreach (['port', 'fudge1', 'stratum', 'refid'] as $field) {
                    $value = trim((string)$legacy->pps->$field);
                    if ($value !== '') {
                        $model->pps->$field = $field == 'stratum' ? $this->toInteger($value) : $value;
                    }
                }
                /* keep the legacy port listed while its device is absent, as for a loaded port */
                $model->pps->port->eventPostLoading();
            }
        }

        /* time servers: merge $config->system->timeservers host list with ntpd parallel-list flags */
        $split = function ($list) {
            $list = trim((string)$list);
            return $list !== '' ? preg_split('/\s+/', $list) : [];
        };
        $hosts = $split($config->system->timeservers ?? '');
        $prefer = $split($legacy->prefer ?? '');
        $iburst = $split($legacy->iburst ?? '');
        $noselect = $split($legacy->noselect ?? '');
        $ispool = $split($legacy->ispool ?? '');

        foreach ($hosts as $host) {
            $node = $model->timeservers->server->Add();
            $node->hostname = $host;
            $node->prefer = in_array($host, $prefer) ? '1' : '0';
            $node->iburst = in_array($host, $iburst) ? '1' : '0';
            $node->enabled = in_array($host, $noselect) ? '0' : '1';
            $node->pool = (in_array($host, $ispool) || preg_match('/\.pool\.ntp\.org$/', $host)) ? '1' : '0';
        }

        /* skip legacy values the model rejects instead of failing the whole migration */
        $skipped = [];
        foreach ($model->performValidation() as $msg) {
            $field = $msg->getField();
            /* log a field once, also when it fails more than one validator */
            if (isset($skipped[$field])) {
                continue;
            }
            $skipped[$field] = true;
            $node = $model->getNodeByReference($field);
            $logger->error(sprintf(
                'NTP migration skipped %s "%s": %s',
                $field,
                $node !== null ? (string)$node : '',
                $msg->getMessage()
            ));
            if (preg_match('/^timeservers\.server\.([^.]+)\./', $field, $matches)) {
                $model->timeservers->server->del($matches[1]);
            } elseif ($node !== null) {
                $node->applyDefault();
            }
        }

        /* ntpd now runs on any time source, legacy did not run it without time servers (#332) */
        if (empty($hosts)) {
            foreach (['gps', 'pps'] as $clock) {
                if ($model->$clock->port->isSet()) {
                    $logger->warning(sprintf(
                        'NTP %s port %s was not migrated as NTP did not run without time servers',
                        strtoupper($clock),
                        $model->$clock->port->getValue()
                    ));
                    $model->$clock->port = '';
                }
            }
        } elseif (!$model->isEnabled()) {
            $logger->warning('NTP no longer runs as none of its time servers could be migrated');
        }

        parent::run($model);
    }

    public function post($model)
    {
        $config = Config::getInstance()->object();
        if (isset($config->ntpd)) {
            unset($config->ntpd);
        }
        /*
         * keep system.timeservers: HA sync sends an empty value for a missing section, which would clear
         * the time servers of a backup still running legacy NTP, while the empty ntpd container leaves it be
         */
    }
}
