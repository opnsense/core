<?php

/*
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

require_once("notices.sasl.inc");
require_once("notices.smtp.inc");

/****f* notices/file_notice
 * NAME
 *   file_notice
 * INPUTS
 *	 $id, $notice, $category, $url, $priority
 * RESULT
 *   Files a notice and kicks off the various alerts, smtp, system log, etc.
 ******/
function file_notice($id, $notice, $category = 'General', $url = '', $priority = 1) {
	/*
	 * $category - Category that this notice should be displayed under. This can be arbitrary,
	 *	       but a page must be set to receive this messages for it to be displayed.
	 *
	 * $priority - A notice's priority. Higher numbers indicate greater severity.
	 *	       0 = informational, 1 = warning, 2 = error, etc. This may also be arbitrary,
	 */
	if(!$queue = get_notices()) $queue = array();
	$queuekey = time();
	$toqueue = array(
				'id'		=> $id,
				'notice'	=> $notice,
				'url'		=> $url,
				'category'	=> $category,
				'priority'	=> $priority,
			);
	$queue[$queuekey] = $toqueue;
	$queueout = fopen('/tmp/notices', 'w');
	if(!$queueout) {
		log_error(sprintf('Could not open %s for writing', '/tmp/notices'));
		return;
	}
	fwrite($queueout, serialize($queue));
	fclose($queueout);
	log_error("New alert found: $notice");
	notify_via_smtp($notice);
	return $queuekey;
}

/****f* notices/get_notices
 * NAME
 *   get_notices
 * INPUTS
 *	 $category
 * RESULT
 *   Returns a specific notices text
 ******/
function get_notices($category = 'all')
{
	if (file_exists('/tmp/notices')) {
		$queue = unserialize(file_get_contents('/tmp/notices'));
		if (!$queue) {
			return false;
		}
		if ($category != 'all') {
			foreach($queue as $time => $notice) {
				if (strtolower($notice['category']) == strtolower($category)) {
					$toreturn[$time] = $notice;
				}
			}
			return $toreturn;
		}

		return $queue;
	}

	return false;
}

/****f* notices/close_notice
 * NAME
 *   close_notice
 * INPUTS
 *	 $id
 * RESULT
 *   Removes a notice from the list
 ******/
function close_notice($id)
{

	$ids = array();
	if(!$notices = get_notices()) return;
	if($id == "all") {
		@unlink('/tmp/notices');
		return;
	}
	foreach(array_keys($notices) as $time) {
		if($id == $time) {
			unset($notices[$id]);
			break;
		}
	}
	foreach($notices as $key => $notice) {
		$ids[$key] = $notice['id'];
	}
	foreach($ids as $time => $tocheck) {
		if($id == $tocheck) {
			unset($notices[$time]);
			break;
		}
	}
	if(count($notices) != 0) {
		$queueout = fopen('/tmp/notices', 'w');
		fwrite($queueout, serialize($notices));
		fclose($queueout);
	} else {
		@unlink('/tmp/notices');
	}
}

/****f* notices/print_notices
 * NAME
 *   print_notices
 * INPUTS
 *	 $notices, $category
 * RESULT
 *   prints notices to the GUI
 ******/
function print_notices($notices, $category = "all")
{
	if (!is_array($notices) || count($notices) == 0) {
		/* nothing to do */
		return;
	}

	foreach ($notices as $notice) {
		if($category != "all") {
			if(in_array($notice['category'], $category)) $categories[] = $notice['category'];
		} else {
			$categories[] = $notice['category'];
		}
	}
	$categories = array_unique($categories);
	sort($categories);
	$toreturn = '';
	foreach($categories as $category) {
		$toreturn .= "<ul><li>{$category}<ul>";
		foreach($notices as $notice) {
			if(strtolower($notice['category']) == strtolower($category)) {
				if($notice['id'] != '') {
					if($notice['url'] != '') {
						$toreturn .= "<li><a href={$notice['url']}>{$notice['id']}</a> - {$notice['notice']}</li>";
					} else {
						$toreturn .= "<li>{$notice['id']} - {$notice['notice']}</li>";
					}
				}
			}
		}
		$toreturn .= "</ul></li></ul>";
	}

	return $toreturn;
}


/****f* notices/are_notices_pending
 * NAME
 *   are_notices_pending
 * INPUTS
 *	 $category to check
 * RESULT
 *   returns true if notices are pending, false if they are not
 ******/
function are_notices_pending($category = 'all')
{
	if (file_exists('/tmp/notices')) {
		return true;
	}

	return false;
}

/****f* notices/notify_via_smtp
 * NAME
 *   notify_via_smtp
 * INPUTS
 *	 notification string to send as an email
 * RESULT
 *   returns true if message was sent
 ******/
function notify_via_smtp($message, $force = false) {
	global $config;
	if(file_exists("/var/run/booting"))
		return;

	if(isset($config['notifications']['smtp']['disable']) && !$force)
		return;

	/* Do NOT send the same message twice */
	if(file_exists("/var/db/notices_lastmsg.txt")) {
		$lastmsg = trim(file_get_contents("/var/db/notices_lastmsg.txt"));
		if($lastmsg == $message)
			return;
	}

	/* Store last message sent to avoid spamming */
	$fd = fopen("/var/db/notices_lastmsg.txt", "w");
	fwrite($fd, $message);
	fclose($fd);

	send_smtp_message($message, "{$config['system']['hostname']}.{$config['system']['domain']} - Notification");
	return;
}

function send_smtp_message($message, $subject = "(no subject)") {
	global $config;

	if(!$config['notifications']['smtp']['ipaddress'])
		return;

	if(!$config['notifications']['smtp']['notifyemailaddress'])
		return;

	$smtp = new smtp_class;

	$from = "opnsense@{$config['system']['hostname']}.{$config['system']['domain']}";
	$to = $config['notifications']['smtp']['notifyemailaddress'];

	$smtp->host_name = $config['notifications']['smtp']['ipaddress'];
	$smtp->host_port = empty($config['notifications']['smtp']['port']) ? 25 : $config['notifications']['smtp']['port'];

	$smtp->direct_delivery = 0;
	$smtp->ssl = (isset($config['notifications']['smtp']['ssl'])) ? 1 : 0;
	$smtp->tls = (isset($config['notifications']['smtp']['tls'])) ? 1 : 0;
	$smtp->debug = 0;
	$smtp->html_debug = 0;
	$smtp->localhost=$config['system']['hostname'].".".$config['system']['domain'];

	if($config['notifications']['smtp']['fromaddress'])
		$from = $config['notifications']['smtp']['fromaddress'];

	// Use SMTP Auth if fields are filled out
	if($config['notifications']['smtp']['username'] &&
	   $config['notifications']['smtp']['password']) {
		$smtp->authentication_mechanism = "PLAIN";
		$smtp->user = $config['notifications']['smtp']['username'];
		$smtp->password = $config['notifications']['smtp']['password'];
	}

	$headers = array(
		"From: {$from}",
		"To: {$to}",
		"Subject: {$subject}",
		"Date: ".date("r")
	);

	if($smtp->SendMessage($from, preg_split('/\s*,\s*/', trim($to)), $headers, $message)) {
		log_error(sprintf('Message sent to %s OK', $to));
		return;
	} else {
		$msg = sprintf('Could not send the message to %s -- Error: %s', $to, $smtp->error);
		log_error($msg);
		return ($msg);
	}
}
