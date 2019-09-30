<?php

/*
 * Copyright (C) 2014-2019 Franco Fichtner <franco@opnsense.org>
 * Copyright (C) 2010 Ermal Luçi
 * Copyright (C) 2005-2006 Colin Smith <ethethlay@gmail.com>
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
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

require_once 'plugins.inc.d/dhcpd.inc'; /* XXX remove me */

/* XXX remove this file, merging it into system.inc */

function service_by_name($name, $filter = array())
{
    $services = plugin_services();

    foreach ($services as $service) {
        if ($service['name'] != $name) {
            continue;
        }
        if (!count($filter)) {
            /* force match if filter wasn't set (standard behaviour) */
            $filter['name'] = $name;
        }
        foreach ($filter as $key => $value) {
            if (isset($service[$key]) && $service[$key] == $value) {
                return $service;
            }
        }
    }

    return array();
}

function service_status($service)
{
    if (!empty($service['nocheck'])) {
        return true;
    }

    if (isset($service['pidfile'])) {
        return isvalidpid($service['pidfile']);
    }

    return is_process_running($service['name']);
}

function service_control_icon($service, $xs = false)
{
    $output = '';

    if (service_status($service)) {
        $output .= '<span class="label label-opnsense label-opnsense-%s label-success"><i class="fa fa-play fa-fw"></i></span>' . PHP_EOL;
    } else {
        $output .= '<span class="label label-opnsense label-opnsense-%s label-danger"><i class="fa fa-stop fa-fw"></i></span>' . PHP_EOL;
    }

    return sprintf($output, $xs ? 'xs' : 'sm');
}

function service_control_links($service, $xs = false)
{
    $service_id = isset($service['id']) ? $service['id'] : '';

    $template  = '<span data-service_id="%s" data-service_action="%s" data-service="%s" ';
    $template .= 'class="btn btn-%s btn-default %s"><i class="%s"></i></span>' . PHP_EOL;

    $output = '';

    if (service_status($service)) {
        $output .= sprintf(
            $template,
            $service_id,
            'restart',
            $service['name'],
            $xs ? 'xs' : 'sm',
            'srv_status_act',
            'fa fa-refresh fa-fw'
        );
        if (empty($service['nocheck'])) {
            $output .= sprintf(
                $template,
                $service_id,
                'stop',
                $service['name'],
                $xs ? 'xs' : 'sm',
                'srv_status_act',
                'fa fa-stop fa-fw'
            );
        }
    } else {
        $output .= sprintf(
            $template,
            $service_id,
            'start',
            $service['name'],
            $xs ? 'xs' : 'sm',
            'srv_status_act',
            'fa fa-play fa-fw'
        );
    }

    return $output;
}

function service_control_start($name, $extras)
{
    $filter = array();

    if (!empty($extras['id'])) {
        $filter['id'] = $extras['id'];
    }

    $service = service_by_name($name, $filter);
    if (!isset($service['name'])) {
        return sprintf(gettext("Could not start unknown service `%s'"), htmlspecialchars($name));
    }

    if (isset($service['configd']['start'])) {
        foreach ($service['configd']['start'] as $cmd) {
            configd_run($cmd);
        }
    } elseif (isset($service['php']['start'])) {
        foreach ($service['php']['start'] as $cmd) {
            $params = array();
            if (isset($service['php']['args'])) {
                foreach ($service['php']['args'] as $param) {
                    $params[] = $service[$param];
                }
            }
            call_user_func_array($cmd, $params);
        }
    } elseif (isset($service['mwexec']['start'])) {
        foreach ($service['mwexec']['start'] as $cmd) {
            mwexec($cmd);
        }
    } else {
        return sprintf(gettext("Could not start service `%s'"), htmlspecialchars($name));
    }

    return sprintf(gettext('%s has been started.'), htmlspecialchars($name));
}

function service_control_stop($name, $extras)
{
    $filter = array();

    if (!empty($extras['id'])) {
        $filter['id'] = $extras['id'];
    }

    $service = service_by_name($name, $filter);
    if (!isset($service['name'])) {
        return sprintf(gettext("Could not stop unknown service `%s'"), htmlspecialchars($name));
    }

    if (isset($service['configd']['stop'])) {
        foreach ($service['configd']['stop'] as $cmd) {
            configd_run($cmd);
        }
    } elseif (isset($service['php']['stop'])) {
        foreach ($service['php']['stop'] as $cmd) {
            $cmd();
        }
    } elseif (isset($service['mwexec']['stop'])) {
        foreach ($service['mwexec']['stop'] as $cmd) {
            mwexec($cmd);
        }
    } elseif (isset($service['pidfile'])) {
        killbypid($service['pidfile'], 'TERM', true);
    } else {
        /* last resort, but not very elegant */
        killbyname($service['name']);
    }

    return sprintf(gettext("%s has been stopped."), htmlspecialchars($name));
}

function service_control_restart($name, $extras)
{
    $filter = array();

    if (!empty($extras['id'])) {
        $filter['id'] = $extras['id'];
    }

    $service = service_by_name($name, $filter);
    if (!isset($service['name'])) {
        return sprintf(gettext("Could not restart unknown service `%s'"), htmlspecialchars($name));
    }

    if (isset($service['configd']['restart'])) {
        foreach ($service['configd']['restart'] as $cmd) {
            configd_run($cmd);
        }
    } elseif (isset($service['php']['restart'])) {
        foreach ($service['php']['restart'] as $cmd) {
            $params = array();
            if (isset($service['php']['args'])) {
                foreach ($service['php']['args'] as $param) {
                    $params[] = $service[$param];
                }
            }
            call_user_func_array($cmd, $params);
        }
    } elseif (isset($service['mwexec']['restart'])) {
        foreach ($service['mwexec']['restart'] as $cmd) {
            mwexec($cmd);
        }
    } else {
        return sprintf(gettext("Could not restart service `%s'"), htmlspecialchars($name));
    }

    return sprintf(gettext("%s has been restarted."), htmlspecialchars($name));
}
