<?php

/*
 * Copyright (C) 2014-2025 Deciso B.V.
 * Copyright (C) 2010 Ermal Luçi
 * Copyright (C) 2007-2008 Scott Ullrich <sullrich@gmail.com>
 * Copyright (C) 2005-2006 Bill Marquette <bill.marquette@gmail.com>
 * Copyright (C) 2006 Paul Taylor <paultaylor@winn-dixie.com>
 * Copyright (C) 2003-2006 Manuel Kasper <mk@neon1.net>
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

require_once("interfaces.inc");
require_once("util.inc");

function auth_log($message, $prio = LOG_ERR)
{
    openlog('audit', LOG_ODELAY, LOG_AUTH);
    log_msg($message, $prio);
    reopenlog();
}

$groupindex = index_groups();
$userindex = index_users();

/**
 * check if $http_host is a local configured ip address
 */
function isAuthLocalIP($http_host)
{
    foreach (config_read_array('virtualip', 'vip') as $vip) {
        if ($vip['subnet'] == $http_host) {
            return true;
        }
    }

    $address_in_list = function ($interface_list_ips, $http_host) {
        foreach ($interface_list_ips as $ilips => $ifname) {
            // remove scope from link-local IPv6 addresses
            $ilips = preg_replace('/%.*/', '', $ilips);
            if (strcasecmp($http_host, $ilips) == 0) {
                return true;
            }
        }
    };

    $auth_cache = '/var/lib/php/tmp/isAuthLocalIP.cache.json';

    /* try using cached addresses */
    $interface_list_ips = get_cached_json_content($auth_cache);
    if (!empty($interface_list_ips) && $address_in_list($interface_list_ips, $http_host)) {
        return true;
    }

    /* fetch addresses and store in cache */
    $interface_list_ips = get_configured_ip_addresses();
    file_put_contents($auth_cache, json_encode($interface_list_ips));
    chown($auth_cache, 'wwwonly'); /* XXX frontend owns file */
    chgrp($auth_cache, 'wheel'); /* XXX backend can work with it */

    return $address_in_list($interface_list_ips, $http_host);
}

function index_groups()
{
    global $config, $groupindex;

    $groupindex = [];

    if (isset($config['system']['group'])) {
        $i = 0;

        foreach ($config['system']['group'] as $groupent) {
            if (isset($groupent['name'])) {
                $groupindex[$groupent['name']] = $i;
                $i++;
            }
        }
    }

    return $groupindex;
}

function index_users()
{
    global $config;

    $userindex = [];

    if (!empty($config['system']['user'])) {
        $i = 0;

        foreach ($config['system']['user'] as $userent) {
            if (!empty($userent) && !empty($userent['name'])) {
                $userindex[$userent['name']] = $i;
            }

            $i++;
        }
    }

    return $userindex;
}

function getUserGroups($username)
{
    global $config;

    $member_groups = [];

    $user = getUserEntry($username);
    if ($user !== false) {
        $allowed_groups = local_user_get_groups($user);
        if (isset($config['system']['group'])) {
            foreach ($config['system']['group'] as $group) {
                if (in_array($group['name'], $allowed_groups)) {
                    $member_groups[] = $group['name'];
                }
            }
        }
    }

    return $member_groups;
}

function &getUserEntry($name)
{
    global $config, $userindex;

    if (isset($userindex[$name])) {
        return $config['system']['user'][$userindex[$name]];
    }

    $ret = false; /* XXX "fixes" return by reference */
    return $ret;
}

function &getUserEntryByUID($uid)
{
    global $config;

    if (is_array($config['system']['user'])) {
        foreach ($config['system']['user'] as & $user) {
            if ($user['uid'] == $uid) {
                return $user;
            }
        }
    }

    $ret = false; /* XXX "fixes" return by reference */
    return $ret;
}

function &getGroupEntry($name)
{
    global $config, $groupindex;

    if (isset($groupindex[$name])) {
        return $config['system']['group'][$groupindex[$name]];
    }

    $ret = []; /* XXX "fixes" return by reference */
    return $ret;
}

function get_user_privileges(&$user)
{
    $privs = [];

    /* legacy listtags() ensures 'priv' will be an array when offered, also when there's only one  */
    foreach (!empty($user['priv']) ? (array)$user['priv'] : [] as $item) {
        foreach (array_filter(explode(',', $item)) as $priv) {
            $privs[] = $priv;
        }
    }

    foreach (local_user_get_groups($user) as $name) {
        $group = getGroupEntry($name);
        foreach (!empty($group['priv']) ? (array)$group['priv'] : [] as $item) {
            foreach (array_filter(explode(',', $item)) as $priv) {
                $privs[] = $priv;
            }
        }
    }

    return $privs;
}

function userHasPrivilege($userent, $privid = false)
{
    if (!$privid || !is_array($userent)) {
        return false;
    }

    $privs = get_user_privileges($userent);

    if (!is_array($privs)) {
        return false;
    }

    if (!in_array($privid, $privs)) {
        return false;
    }
    return true;
}

function userIsAdmin($username)
{
    return userHasPrivilege(getUserEntry($username), 'page-all');
}

function local_sync_accounts()
{
    global $config;

    /* we need to know the current state of users and groups, both configured and actual */
    $current_data = [];
    foreach (['usershow', 'groupshow'] as $command) {
        $section = $command == 'usershow' ? 'user' : 'group';
        $current_data[$section] = [];
        $data = "";
        exec("/usr/sbin/pw {$command} -a", $data, $ret);
        if (!$ret) {
            foreach ($data as $record) {
                $line = explode(':', $record);
                // filter system managed users and groups
                if (count($line) < 3 ||  !strncmp($line[0], '_', 1) || ($line[2] < 2000 && $line[0] != 'root') || $line[2] > 65000) {
                    continue;
                }
                $current_data[$section][$line[2]] = $line;
            }
        }
    }
    $config_map = [];
    foreach (['user', 'group'] as $section) {
        $config_map[$section] = [];
        if (!empty($config['system'][$section])) {
            foreach ($config['system'][$section] as $item) {
                if ($section == 'user' && (empty($item) || (empty($item['shell']) && !empty($item['uid'])))) {
                    /* no shell, no local user */
                    continue;
                }
                $this_id = $section == 'user' ? $item['uid'] : $item['gid'];
                $config_map[$section][(string)$this_id] = $item['name'];
            }
        }
    }
    /* remove conflicting users and groups (uid/gid or name mismatch) */
    foreach ($current_data as $section => $data) {
        foreach ($data as $oid => $object) {
            if (empty($config_map[$section][$oid]) || $config_map[$section][$oid] !== $object[0]) {
                if ($section == 'user') {
                    /*
                     * If a crontab was created to user, pw userdel will be interactive and
                     * can cause issues. Just remove crontab before run it when necessary
                     */
                    @unlink("/var/cron/tabs/{$object[0]}");
                    mwexecf('/usr/sbin/pw userdel -n %s', $object[0]);
                } else {
                    mwexecf('/usr/sbin/pw groupdel -g %s', $object[2]);
                }
            }
        }
    }

    /* sync all local users with a shell account configured (filtered in local_user_set()) */
    if (is_array($config['system']['user'])) {
        foreach ($config['system']['user'] as $user) {
            $userattrs = !empty($current_data['user'][$user['uid']]) ? $current_data['user'][$user['uid']] : [];
            local_user_set($user, false, $userattrs);
        }
    }

    /* sync all local groups */
    if (is_array($config['system']['group'])) {
        $uids = local_user_list_uids();
        foreach ($config['system']['group'] as $group) {
            local_group_set($group, $uids);
        }
    }
}

function local_user_set(&$user, $force_password = false, $userattrs = null)
{
    if (empty($user['password'])) {
        auth_log("Cannot set user {$user['name']}: password is missing");
        return;
    } elseif (empty($user['shell']) && $user['uid'] != 0) {
        /* no shell, no local user */
        return;
    }

    @mkdir('/home', 0755);

    /* integrated authentication handles passwords unless 'installer' user needs it locally */
    $user_pass = $force_password ? $user['password'] : '*';
    $user_name = $user['name'];
    $user_uid = $user['uid'];
    $comment = str_replace([':', '!', '@'], ' ', $user['descr']);

    $lock_account = 'lock';

    $is_expired = !empty($user['expires']) &&
        strtotime('-1 day') > strtotime(date('m/d/Y', strtotime($user['expires'])));

    $is_disabled = !empty($user['disabled']);

    $is_unlocked = !$is_disabled && !$is_expired;

    if ($is_unlocked || $user_uid == 0) {
        /*
         * The root account shall not be locked as this will have
         * side-effects such as cron not working correctly.  Our
         * auth framework will make sure not to allow login to a
         * disabled root user at the same time.
         */
        $lock_account = 'unlock';
    }

    if ($user_uid == 0) {
        $user_shell = !empty($user['shell']) ? $user['shell'] : '/usr/local/sbin/opnsense-shell';
        $user_group = 'wheel';
        $user_home = '/root';
    } else {
        $is_admin = userIsAdmin($user['name']);
        $user_shell = $is_admin && !empty($user['shell']) ? $user['shell'] : '/usr/sbin/nologin';
        $user_group = $is_admin ? 'wheel' : 'nobody';
        $user_home = "/home/{$user_name}";
    }

    // XXX: primary group id can only be wheel or nobody, otherwise we should map the correct numbers for comparison
    $user_gid = $user_group == 'wheel' ? 0 : 65534;

    if (!$force_password) {
        /* read from pw db if not provided (batch mode) */
        if ($userattrs === null) {
            $fd = popen("/usr/sbin/pw usershow -n {$user_name}", 'r');
            $pwread = fgets($fd);
            pclose($fd);
            if (substr_count($pwread, ':')) {
                $userattrs = explode(':', trim($pwread));
            }
        }
    }

    /* determine add or mod */
    if ($userattrs === null || $userattrs[0] != $user['name']) {
        $user_op = 'useradd -m -k /usr/share/skel -o';
    } elseif (
        $userattrs[0] == $user_name &&
        $userattrs[1] == '*' &&
        $userattrs[2] == $user_uid &&
        $userattrs[3] == $user_gid &&
        $userattrs[7] == $comment &&
        $userattrs[8] == $user_home &&
        $userattrs[9] == $user_shell
    ) {
        $user_op = null;
    } else {
        $user_op = 'usermod';
    }

    /* add or mod pw db */
    if ($user_op != null) {
        $cmd = "/usr/sbin/pw {$user_op} -q -u {$user_uid} -n {$user_name}" .
          " -g {$user_group} -s {$user_shell} -d {$user_home}" .
          " -c " . escapeshellarg($comment) . " -H 0 2>&1";
        $fd = popen($cmd, 'w');
        fwrite($fd, $user_pass);
        pclose($fd);
    }

    /* create user directory if required */
    @mkdir($user_home, 0700);
    @chown($user_home, $user_name);
    @chgrp($user_home, $user_group);

    /* write out ssh authorized key file */
    if ($is_unlocked && !empty($user['authorizedkeys'])) {
        @mkdir("{$user_home}/.ssh", 0700);
        @chown("{$user_home}/.ssh", $user_name);
        $keys = base64_decode($user['authorizedkeys']);
        $keys = preg_split('/[\n\r]+/', $keys);
        $keys[] = '';
        $keys = implode("\n", $keys);
        @file_put_contents("{$user_home}/.ssh/authorized_keys", $keys);
        @chown("{$user_home}/.ssh/authorized_keys", $user_name);
    } else {
        @unlink("{$user_home}/.ssh/authorized_keys");
    }

    mwexecf('/usr/sbin/pw %s %s', [$lock_account, $user_name], true);
}

function local_user_set_password(&$user, $password = null)
{
    $local = config_read_array('system', 'webgui');
    $hash = false;

    if ($password == null) {
        /* generate a random password */
        $password = random_bytes(50);

        /* XXX since PHP 8.2.18 we need to avoid NUL char */
        while (($i = strpos($password, "\0")) !== false) {
            $password[$i] = random_bytes(1);
        }
    }

    if (
        !empty($local['enable_password_policy_constraints']) &&
        !empty($local['password_policy_compliance'])
    ) {
          $process = proc_open(
              '/usr/local/bin/openssl passwd -6 -stdin',
              [['pipe', 'r'], ['pipe', 'w']],
              $pipes
          );
        if (is_resource($process)) {
            fwrite($pipes[0], $password);
            fclose($pipes[0]);
            $hash = trim(stream_get_contents($pipes[1]));
            fclose($pipes[1]);
            proc_close($process);
        }
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT, [ 'cost' => 11 ]);
    }

    if ($hash !== false && strpos($hash, '$') === 0) {
        $user['password'] = $hash;
    } else {
        auth_log("Failed to hash password for user {$user['name']}");
    }
}

/* collect a list of know uids */
function local_user_list_uids()
{
    $uids = [];
    foreach (explode("\n", shell_safe('/usr/sbin/pw usershow -a')) as $line) {
        $parts = explode(":", $line);
        if (count($parts) > 2) {
            $uids[] = $parts[2];
        }
    }
    return $uids;
}

function local_user_get_groups($user)
{
    global $config;

    $groups = [];

    if (!isset($config['system']['group'])) {
        return $groups;
    }

    foreach ($config['system']['group'] as $group) {
        if (isset($group['member'])) {
            foreach ($group['member'] as $member) {
                if (in_array($user['uid'], explode(',', $member))) {
                    $groups[] = $group['name'];
                    break;
                }
            }
        }
    }

    sort($groups);

    return $groups;
}

function local_group_set($group, $uids = null)
{
    if (!isset($group['name']) || !isset($group['gid'])) {
        return;
    }
    if (empty($uids)) {
        $uids = local_user_list_uids();
    }

    $group_name = $group['name'];
    $group_gid = $group['gid'];
    $group_members = '';

    if (!empty($group['member']) && count($group['member']) > 0) {
        $members = [];
        foreach ($group['member'] as $member) {
            if (!in_array($member, $uids)) {
                continue;
            }
            $members = array_merge($members, explode(',', $member));
        }
        $group_members = implode(',', $members);
    }

    $ret = mwexecf('/usr/sbin/pw groupshow %s', $group_name, true);
    if ($ret) {
        $group_op = 'groupadd';
    } else {
        $group_op = 'groupmod';
    }

    mwexecf('/usr/sbin/pw %s %s -g %s -M %s', [$group_op, $group_name, $group_gid, $group_members]);
}


/**
 * @param $name string name of the authentication system configured on the authentication server page or 'Local Database' for local authentication
 * @return array|bool false if the authentication server was not found, otherwise the configuration of the authentication server
 */
function auth_get_authserver($name)
{
    $servers = auth_get_authserver_list();
    if (isset($servers[$name])) {
        return $servers[$name];
    }
    return false;
}

function auth_get_authserver_list($service = '')
{
    return (new \OPNsense\Auth\AuthenticationFactory())->listServers($service);
}
