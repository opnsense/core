<?php

/*
	Copyright (C) 2014 Deciso B.V.
	Copyright (C) Scott Ullrich
	Copyright (C) 2009 Jim Pingle
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/

global $buffer_rules_rdr, $buffer_rules_normal;
$buffer_rules_rdr = array();
$buffer_rules_normal = array();

/* format filter logs */
function conv_log_filter($logfile, $nentries, $tail = 50, $filtertext = '', $filterinterface = null)
{
	global $config;

	/* Make sure this is a number before using it in a system call */
	if (!(is_numeric($tail)))
		return;

	if ($filtertext!=""){
		$tail = 5000;
	}

	/* Always do a reverse tail, to be sure we're grabbing the 'end' of the log. */
	$logarr = "";

	exec("/usr/local/sbin/clog " . escapeshellarg($logfile) . " | grep -v \"CLOG\" | grep -v \"\033\" | /usr/bin/grep 'filterlog:' | /usr/bin/tail -r -n {$tail}", $logarr);

	$filterlog = array();
	$counter = 0;

	foreach ($logarr as $logent) {
		if($counter >= $nentries)
			break;

		$flent = parse_filter_line($logent);
		if (isset($flent) && is_array($flent)) {
			if ($filterinterface == null || strtoupper($filterinterface) == $flent['interface']) {
				if ( (!is_array($filtertext) && match_filter_line ($flent, $filtertext)) ||
					( is_array($filtertext) && match_filter_field($flent, $filtertext))
				) {
					$counter++;
					$filterlog[] = $flent;
				}
			}
		}

	}
	/* Since the lines are in reverse order, flip them around if needed based on the user's preference */
	return isset($config['syslog']['reverse']) ? $filterlog : array_reverse($filterlog);
}

function escape_filter_regex($filtertext) {
	/* If the caller (user) has not already put a backslash before a slash, to escape it in the regex, */
	/* then this will do it. Take out any "\/" already there, then turn all ordinary "/" into "\/".  */
	return str_replace('/', '\/', str_replace('\/', '/', $filtertext));
}

function match_filter_line($flent, $filtertext = "") {
	if (!$filtertext)
		return true;
	$filtertext = escape_filter_regex(str_replace(' ', '\s+', $filtertext));
	return @preg_match("/{$filtertext}/i", implode(" ", array_values($flent)));
}

function match_filter_field($flent, $fields) {
	foreach ($fields as $key => $field) {
		if ($field == "All")
			continue;
		if ((strpos($field, '!') === 0)) {
			$field = substr($field, 1);
			if (strtolower($key) == 'act') {
				if (in_arrayi($flent[$key], explode(" ", $field)))
					return false;
			} else {
				$field_regex = escape_filter_regex($field);
				if (@preg_match("/{$field_regex}/i", $flent[$key]))
					return false;
			}
		} else {
			if (strtolower($key) == 'act') {
				if (!in_arrayi($flent[$key], explode(" ", $field)))
					return false;
			} else {
				$field_regex = escape_filter_regex($field);
				if (!@preg_match("/{$field_regex}/i", $flent[$key]))
					return false;
			}
		}
	}
	return true;
}

// Case Insensitive in_array function
function in_arrayi($needle, $haystack) {
	return in_array(strtolower($needle), array_map('strtolower', $haystack));
}

function parse_filter_line($line)
{
	$flent = array();
	$log_split = '';

	if (!preg_match('/(.*)\s(.*)\sfilterlog:\s(.*)$/', $line, $log_split)) {
		return '';
	}

	list($all, $flent['time'], $host, $rule) = $log_split;

	if (trim($flent['time']) == '') {
		log_error(sprintf(gettext('There was an error parsing a rule: no time (`%s\')'), $log_split));
		return '';
	}

	$rule_data = explode(',', $rule);
	$field = 0;

	$flent['rulenum'] = $rule_data[$field++];
	$flent['subrulenum'] = $rule_data[$field++];
	$flent['anchor'] = $rule_data[$field++];
	$field++; // skip field
	$flent['realint'] = $rule_data[$field++];
	$flent['interface']  = convert_real_interface_to_friendly_descr($flent['realint']);
	$flent['reason'] = $rule_data[$field++];
	$flent['act'] = $rule_data[$field++];
	$flent['direction'] = $rule_data[$field++];
	$flent['version'] = $rule_data[$field++];

	if ($flent['version'] != '4' && $flent['version'] != '6') {
		log_error(sprintf(
			gettext('There was an error parsing rule number: %s -- not IPv4 or IPv6 (`%s\')'),
			$flent['rulenum'],
			$rule
		));
		return '';
	}

	if ($flent['version'] == '4') {
		$flent['tos'] = $rule_data[$field++];
		$flent['ecn'] = $rule_data[$field++];
		$flent['ttl'] = $rule_data[$field++];
		$flent['id'] = $rule_data[$field++];
		$flent['offset'] = $rule_data[$field++];
		$flent['flags'] = $rule_data[$field++];
		$flent['protoid'] = $rule_data[$field++];
		$flent['proto'] = strtoupper($rule_data[$field++]);
	} else {
		$flent['class'] = $rule_data[$field++];
		$flent['flowlabel'] = $rule_data[$field++];
		$flent['hlim'] = $rule_data[$field++];
		$flent['proto'] = $rule_data[$field++];
		$flent['protoid'] = $rule_data[$field++];
	}

	$flent['length'] = $rule_data[$field++];
	$flent['srcip'] = $rule_data[$field++];
	$flent['dstip'] = $rule_data[$field++];

	/* bootstrap src and dst for non-port protocols */
	$flent['src'] = $flent['srcip'];
	$flent['dst'] = $flent['dstip'];

	if (trim($flent['src']) == '' || trim($flent['dst']) == '') {
		log_error(sprintf(
			gettext('There was an error parsing rule number: %s -- no src or dst (`%s\')'),
			$flent['rulenum'],
			$rule
		));
		return '';
	}

	if ($flent['protoid'] == '6' || $flent['protoid'] == '17') { // TCP or UDP
		$flent['srcport'] = $rule_data[$field++];
		$flent['dstport'] = $rule_data[$field++];

		$flent['src'] = $flent['srcip'] . ':' . $flent['srcport'];
		$flent['dst'] = $flent['dstip'] . ':' . $flent['dstport'];

		$flent['datalen'] = $rule_data[$field++];
		if ($flent['protoid'] == '6') { // TCP
			$flent['tcpflags'] = $rule_data[$field++];
			$flent['seq'] = $rule_data[$field++];
			$flent['ack'] = $rule_data[$field++];
			$flent['window'] = $rule_data[$field++];
			$flent['urg'] = $rule_data[$field++];
			$flent['options'] = explode(";",$rule_data[$field++]);
		}
	} elseif ($flent['protoid'] == '1') { // ICMP
		$flent['icmp_type'] = $rule_data[$field++];
		switch ($flent['icmp_type']) {
		case 'request':
		case 'reply':
			$flent['icmp_id'] = $rule_data[$field++];
			$flent['icmp_seq'] = $rule_data[$field++];
			break;
		case 'unreachproto':
			$flent['icmp_dstip'] = $rule_data[$field++];
			$flent['icmp_protoid'] = $rule_data[$field++];
			break;
		case 'unreachport':
			$flent['icmp_dstip'] = $rule_data[$field++];
			$flent['icmp_protoid'] = $rule_data[$field++];
			$flent['icmp_port'] = $rule_data[$field++];
			break;
		case 'unreach':
		case 'timexceed':
		case 'paramprob':
		case 'redirect':
		case 'maskreply':
			$flent['icmp_descr'] = $rule_data[$field++];
			break;
		case 'needfrag':
			$flent['icmp_dstip'] = $rule_data[$field++];
			$flent['icmp_mtu'] = $rule_data[$field++];
			break;
		case 'tstamp':
			$flent['icmp_id'] = $rule_data[$field++];
			$flent['icmp_seq'] = $rule_data[$field++];
			break;
		case 'tstampreply':
			$flent['icmp_id'] = $rule_data[$field++];
			$flent['icmp_seq'] = $rule_data[$field++];
			$flent['icmp_otime'] = $rule_data[$field++];
			$flent['icmp_rtime'] = $rule_data[$field++];
			$flent['icmp_ttime'] = $rule_data[$field++];
			break;
		default :
			if (isset($rule_data[$field++])) {
				$flent['icmp_descr'] = $rule_data[$field++];
			}
			break;
		}
	} elseif ($flent['protoid'] == '112') { // CARP
		$flent['type'] = $rule_data[$field++];
		$flent['ttl'] = $rule_data[$field++];
		$flent['vhid'] = $rule_data[$field++];
		$flent['version'] = $rule_data[$field++];
		$flent['advskew'] = $rule_data[$field++];
		$flent['advbase'] = $rule_data[$field++];
	}

	return $flent;
}

/* AJAX specific handlers */
function handle_ajax($nentries, $tail = 50) {
	global $config;
	if(isset($_GET['lastsawtime']) || isset($_POST['lastsawtime'])) {
		global $filter_logfile,$filterent;
		if(isset($_GET['lastsawtime']))
			$lastsawtime = $_GET['lastsawtime'];
		if(isset($_POST['lastsawtime']))
			$lastsawtime = $_POST['lastsawtime'];
		/*  compare lastsawrule's time stamp to filter logs.
		 *  afterwards return the newer records so that client
		 *  can update AJAX interface screen.
		 */
		$new_rules = "";
		$filterlog = conv_log_filter($filter_logfile, $nentries, $tail);
		/* We need this to always be in forward order for the AJAX update to work properly */
		$filterlog = isset($config['syslog']['reverse']) ? array_reverse($filterlog) : $filterlog;
		foreach($filterlog as $log_row) {
			$row_time = strtotime($log_row['time']);
			if ($log_row['act']=="pass") {
				$img="<span class='glyphicon glyphicon-play text-success' alt={$log_row['act']}></span>";
			} elseif ($log_row['act']=="block") {
				$img="<span class='glyphicon glyphicon-remove text-danger' alt={$log_row['act']}></span>";
			} elseif ($log_row['act']=="reject") {
				$img="<span class='glyphicon glyphicon-remove text-warning' alt={$log_row['act']}></span>";
			}
			if($row_time > $lastsawtime) {
				if ($log_row['proto'] == "TCP")
					$log_row['proto'] .= ":{$log_row['tcpflags']}";

				$img = "<a href=\"#\" onClick=\"javascript:getURL('diag_logs_filter.php?getrulenum={$log_row['rulenum']},{$log_row['rulenum']}', outputrule);\">{$img}</a>";
				$new_rules .= "{$img}||{$log_row['time']}||{$log_row['interface']}||{$log_row['srcip']}||{$log_row['dst']}||{$log_row['proto']}||" . time() . "||\n";
			}
		}
		echo $new_rules;
		exit;
	}
}

?>
