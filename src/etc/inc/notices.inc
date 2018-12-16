<?php

/*
 * Copyright (C) 2018 Deciso B.V.
 * Copyright (C) 2009 Scott Ullrich <sullrich@gmail.com>
 * Copyright (C) 2005 Colin Smith <ethethlay@gmail.com>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in the
 * documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * RISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 */

function file_notice($notice)
{
    $queue = get_notices();
    $queue[time()] = array('notice' => $notice);
    file_put_contents('/tmp/notices', serialize($queue));
}

function get_notices()
{
    $toreturn = array();

    if (file_exists('/tmp/notices')) {
        $queue = unserialize(file_get_contents('/tmp/notices'));
        if ($queue) {
            foreach($queue as $time => $notice) {
                if (strtolower($notice['category']) == strtolower($category)) {
                    $toreturn[$time] = $notice;
                }
            }
        }
    }

    return $toreturn;
}

function close_notice($id)
{
    $ids = array();

    if (file_exists('/tmp/notices')) {
        if ($id == 'all') {
            @unlink('/tmp/notices');
        } else {
            $notices = get_notices();
            foreach (array_keys($notices) as $time) {
                if ($id == $time) {
                    unset($notices[$id]);
                    break;
                }
            }
            if (count($notices) > 0) {
                file_put_contents('/tmp/notices', serialize($notices));
            } else {
                @unlink('/tmp/notices');
            }
        }
    }
}

function print_notices($notices)
{
    $toreturn = '';

    if (is_array($notices) && count($notices) > 0) {
        foreach ($notices as $notice) {
            $toreturn .= "<li>{$notice['id']} - {$notice['notice']}</li>";
        }
    }

    return $toreturn;
}

function are_notices_pending()
{
    return file_exists('/tmp/notices');
}
