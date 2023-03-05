#!/usr/local/bin/php
<?php

/*
 * Copyright (C) 2023 Deciso B.V.
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

require_once("script/load_phalcon.php");

$cnf = OPNsense\Core\Config::getInstance()->object();

$uid_map = [];
$result = [];
if (isset($cnf->system->user)) {
    foreach ($cnf->system->user as $user) {
        $uid_map[(string)$user->uid] = (string)$user->name;
    }
}
if (isset($cnf->system->group)) {
    foreach ($cnf->system->group as $group) {
        $group_name = (string)$group->name;
        $gid = (string)$group->gid;
        if (empty($group_name) || empty($gid)) {
            continue;
        }
        $result[$gid] = ['name' => $group_name, 'members' => []];
        if (isset($group->member)) {
            foreach ($group->member as $member) {
                $member_uid = (string)$member;
                if (isset($uid_map[$member_uid])) {
                    $result[$gid]['members'][] = $uid_map[$member_uid];
                }
            }
        }
    }
}
echo json_encode($result);
