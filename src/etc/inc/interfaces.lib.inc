<?php

/*
	Copyright (c) 2015 Franco Fichtner <franco@opnsense.org>
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

function legacy_interface_listget($flag = '')
{
	$cmd = '/sbin/ifconfig -l';
	$ifs = null;

	if ($flag === 'up') {
		$cmd .= 'u';
	} else if ($flag === 'down') {
		$cmd .= 'd';
	}

	exec($cmd . ' 2>&1', $out, $ret);
	if ($ret) {
		log_error('The command `' . $cmd . '\' failed to execute');
		return ($ifs);
	}

	if (isset($out[0])) {
		$ifs = explode(' ', $out[0]);
	}

	return ($ifs);
}

function legacy_interface_flags($ifs, $flag)
{
	/* $flags isn't escaped because it can be an argument list */
	$cmd = '/sbin/ifconfig ' . escapeshellarg($ifs) . ' ' . $flag;

	exec($cmd . ' 2>&1', $out, $ret);
	if ($ret) {
		log_error('The command `' . $cmd . '\' failed to execute');
	}
}

function legacy_interface_create($ifs)
{
	$cmd = '/sbin/ifconfig ' . escapeshellarg($ifs) . ' create';
	$new = null;

	exec($cmd . ' 2>&1', $out, $ret);
	if ($ret) {
		log_error('The command `' . $cmd . '\' failed to execute');
		return ($new);
	}

	if (isset($out[0])) {
		$new = $out[0];
	}

	return ($new);
}

function legacy_interface_destroy($ifs)
{
	$cmd = '/sbin/ifconfig ' . escapeshellarg($ifs) . ' destroy';

	exec($cmd . ' 2>&1', $out, $ret);
	if ($ret) {
		log_error('The command `' . $cmd . '\' failed to execute');
	}
}

function legacy_interface_setaddress($ifs, $addr)
{
	$cmd = '/sbin/ifconfig ' . escapeshellarg($ifs) . ' alias ' . escapeshellarg($addr);

	exec($cmd . ' 2>&1', $out, $ret);
	if ($ret) {
		log_error('The command `' . $cmd . '\' failed to execute');
	}
}

function legacy_interface_deladdress($ifs, $addr)
{
	$cmd = '/sbin/ifconfig ' . escapeshellarg($ifs) . ' -alias ' . escapeshellarg($addr);

	exec($cmd . ' 2>&1', $out, $ret);
	if ($ret) {
		log_error('The command `' . $cmd . '\' failed to execute');
	}
}

function legacy_interface_rename($ifs, $name)
{
	$cmd = '/sbin/ifconfig ' . escapeshellarg($ifs) . ' name ' . escapeshellarg($name);

	exec($cmd . ' 2>&1', $out, $ret);
	if ($ret) {
		log_error('The command `' . $cmd . '\' failed to execute');
	}
}

function legacy_interface_mtu($ifs, $mtu)
{
	$cmd = '/sbin/ifconfig ' . escapeshellarg($ifs) . ' mtu ' . escapeshellarg($mtu);

	exec($cmd . ' 2>&1', $out, $ret);
	if ($ret) {
		log_error('The command `' . $cmd . '\' failed to execute');
	}
}

function legacy_bridge_member($ifs, $member)
{
	$cmd = '/sbin/ifconfig ' . escapeshellarg($ifs) . ' addm ' . escapeshellarg($member);

	exec($cmd . ' 2>&1', $out, $ret);
	if ($ret) {
		log_error('The command `' . $cmd . '\' failed to execute');
	}
}

function legacy_vlan_tag($ifs, $member, $tag)
{
	$cmd = '/sbin/ifconfig ' . escapeshellarg($ifs) . ' vlandev ' . escapeshellarg($member) . ' vlan ' . escapeshellarg($tag);

	exec($cmd . ' 2>&1', $out, $ret);
	if ($ret) {
		log_error('The command `' . $cmd . '\' failed to execute');
	}
}

function legacy_interface_stats($ifs)
{
	$cmd = '/usr/local/sbin/ifinfo ' . escapeshellarg($ifs);
	$stats = array();

	exec($cmd . ' 2>&1', $out, $ret);
	if ($ret) {
		log_error('The command `' . $cmd . '\' failed to execute');
		return $stats;
	}

	if (count($out)) {
		/* first one is header */
		array_shift($out);

		foreach ($out as $line) {
			$stat = explode(':', $line);
			$stats[trim($stat[0])] = trim($stat[1]);
		}
	}

	return $stats;
}
