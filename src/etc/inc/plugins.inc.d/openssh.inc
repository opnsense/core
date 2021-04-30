<?php

/*
 * Copyright (C) 2004 Scott Ullrich <sullrich@gmail.com>
 * Copyright (C) 2004 Fred Mol <fredmol@xs4all.nl>
 * Copyright (C) 2015-2021 Franco Fichtner <franco@opnsense.org>
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

function openssh_enabled()
{
    global $config;

    return isset($config['system']['ssh']['enabled']) ||
        (!isset($config['system']['ssh']['noauto']) && is_install_media());
}

function openssh_configure()
{
    return array(
        'earlybootup' => array('openssh_configure_do'),
        'local' => array('openssh_configure_do'),
        'newwanip' => array('openssh_configure_do:2'),
    );
}

function openssh_services()
{
    $services = array();

    if (openssh_enabled()) {
        $pconfig = array();
        $pconfig['description'] = gettext('Secure Shell Daemon');
        $pconfig['configd']['restart'] = array('openssh restart');
        $pconfig['configd']['start'] = array('openssh start');
        $pconfig['configd']['stop'] = array('openssh stop');
        $pconfig['pidfile'] = '/var/run/sshd.pid';
        $pconfig['name'] = 'openssh';
        $services[] = $pconfig;
    }

    return $services;
}

/**
 *  sync configuration via xmlrpc
 * @return array
 */
function openssh_xmlrpc_sync()
{
    $result = [];
    $result[] = array(
        'description' => gettext('OpenSSH'),
        'section' => 'system.ssh',
        'id' => 'ssh',
        'services' => ["openssh"],
    );

    return $result;
}


function openssh_stop()
{
    /* if run from a shell session, `-af' and the full path is needed */
    mwexecf('/bin/pkill -af %s', '/usr/local/sbin/sshd', true);
}

function openssh_configure_do($verbose = false, $interface = '')
{
    global $config;

    $sshcfg = null;

    if (isset($config['system']['ssh']['enabled'])) {
        $sshcfg = $config['system']['ssh'];
    } elseif (!isset($config['system']['ssh']['noauto']) && is_install_media()) {
        /* only revert to installer config when ssh is not set at all */
        $sshcfg = array('permitrootlogin' => 1, 'passwordauth' => 1);
    }

    if ($sshcfg === null) {
        openssh_stop();
        return;
    }

    $interfaces = array();
    if (!empty($sshcfg['interfaces'])) {
        $interfaces = explode(',', $sshcfg['interfaces']);
        $interfaces[] = 'lo0';
    }

    if (!empty($interface) && !in_array($interface, $interfaces)) {
        return;
    }

    openssh_stop();

    /* make sshd key store */
    @mkdir('/conf/sshd', 0777, true);

    /* make ssh home directory */
    @mkdir('/var/empty', 0555, true);

    $keys = array(
        /* .pub files are implied */
        'rsa' => 'ssh_host_rsa_key',
        'ecdsa' => 'ssh_host_ecdsa_key',
        'ed25519' => 'ssh_host_ed25519_key',
    );

    $keys_dep = array(
        /* .pub files are implied */
        'dsa' => 'ssh_host_dsa_key',
    );

    $keys_all = array_merge($keys, $keys_dep);

    /* Check for all needed key files. If any are missing, the keys need to be regenerated. */
    $generate_keys = false;
    foreach ($keys as $name) {
        $file = "/conf/sshd/{$name}";
        if (!file_exists($file) || !file_exists("{$file}.pub")) {
            $generate_keys = true;
            break;
        }
    }

    if ($generate_keys) {
        if (is_subsystem_dirty('sshdkeys')) {
            return;
        }
        log_error('Started creating your SSH keys. SSH startup is being delayed a wee bit.');
        mark_subsystem_dirty('sshdkeys');
        foreach ($keys as $type => $name) {
            $file = "/conf/sshd/{$name}";
            @unlink("{$file}.pub");
            @unlink($file);
            mwexecf('/usr/local/bin/ssh-keygen -t %s -N "" -f %s', array($type, $file));
        }
        clear_subsystem_dirty('sshdkeys');
        log_error('Completed creating your SSH keys. SSH will now be started.');
    }

    $sshport = isset($sshcfg['port']) ? $sshcfg['port'] : 22;

    $sshconf = "# This file was automatically generated by /usr/local/etc/inc/plugins.inc.d/openssh.inc\n";
    $sshconf .= "Port {$sshport}\n";
    $sshconf .= "Protocol 2\n";
    $sshconf .= "Compression yes\n";
    $sshconf .= "ClientAliveInterval 30\n";
    $sshconf .= "UseDNS no\n";
    $sshconf .= "X11Forwarding no\n";
    $sshconf .= "PubkeyAuthentication yes\n";
    $sshconf .= "Subsystem sftp internal-sftp\n";
    $sshconf .= "AllowGroups wheel";
    if (!empty($sshcfg['group'][0])) {
        $sshconf .= " {$sshcfg['group'][0]}";
    }
    $sshconf .= "\n";
    if (isset($sshcfg['permitrootlogin'])) {
        $sshconf .= "PermitRootLogin yes\n";
    } else {
        $sshconf .= "PermitRootLogin no\n";
    }
    if (isset($sshcfg['passwordauth'])) {
        $sshconf .= "ChallengeResponseAuthentication yes\n";
        $sshconf .= "PasswordAuthentication yes\n";
    } else {
        $sshconf .= "ChallengeResponseAuthentication no\n";
        $sshconf .= "PasswordAuthentication no\n";
    }
    if (!empty($sshcfg['kex'])) {
        $sshconf .= "KexAlgorithms {$sshcfg['kex']}\n";
    }
    if (!empty($sshcfg['ciphers'])) {
        $sshconf .= "Ciphers {$sshcfg['ciphers']}\n";
    }
    if (!empty($sshcfg['macs'])) {
        $sshconf .= "MACs {$sshcfg['macs']}\n";
    }
    if (!empty($sshcfg['keys'])) {
        $sshconf .= "HostKeyAlgorithms {$sshcfg['keys']}\n";
    }
    foreach ($keys_all as $name) {
        $file = "/conf/sshd/{$name}";
        if (!file_exists($file)) {
            continue;
        }
        $sshconf .= "HostKey {$file}\n";
    }

    $listeners = array();

    foreach (interfaces_addresses($interfaces) as $tmpaddr => $info) {
        if ($info['scope']) {
            /* link-local does not seem to be supported */
            continue;
        }

        if (count($listeners) < 16) {
            $listeners[] = $tmpaddr;
        } else {
            log_error("The SSH listening address $tmpaddr cannot be added due to MAX_LISTEN_SOCKS limit reached.");
        }
    }

    foreach ($listeners as $listener) {
        $sshconf .= "ListenAddress {$listener}\n";
    }

    file_put_contents("/usr/local/etc/ssh/sshd_config", $sshconf);

    if ($verbose) {
        echo 'Configuring OpenSSH...';
        flush();
    }

    if ((count($interfaces) && !count($listeners)) || mwexecf('/usr/bin/protect -i /usr/local/sbin/sshd')) {
        if ($verbose) {
            echo "failed.\n";
        }
    } else {
        if ($verbose) {
            echo "done.\n";
        }
    }
}
