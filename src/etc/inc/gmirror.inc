<?php
/*
	gmirror.inc
	Copyright (C) 2009-2014 Jim Pingle

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	notice, this list of conditions and the following disclaimer in the
	documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INClUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/

/* Create a status array for each mirror and its disk components. */
function gmirror_get_status() {
	$status = "";
	exec("/sbin/gmirror status -s", $status);
	$mirrors = array();

	/* Empty output = no mirrors found */
	if (count($status) > 0) {
		/* Loop through gmirror status output. */
		foreach ($status as $line) {
			/* Split the line by whitespace */
			$all = preg_split("/[\s\t]+/", trim($line), 3);
			if (count($all) == 3) {
				/* If there are three items on a line, it is mirror name, status, and component */
				$currentmirror = basename($all[0]);
				$mirrors[$currentmirror]['name'] = basename($all[0]);
				$mirrors[$currentmirror]['status'] = $all[1];
				if (!is_array($mirrors[$currentmirror]['components']))
					$mirrors[$currentmirror]['components'] = array();
				$mirrors[$currentmirror]['components'][] = $all[2];
			}
		}
	}
	/* Return an hash of mirrors and components */
	return $mirrors;
}


/* List all disks in the system (potential gmirror targets) */
function gmirror_get_disks() {
	$disklist = "";
	/* Get a list of disks in a scriptable way, exclude optical drives */
	exec("/sbin/geom disk status -s | /usr/bin/grep -v '[[:blank:]]*cd[[:digit:]]*' | /usr/bin/awk '{print $1;}'", $disklist);
	return $disklist;
}

/* List all potential gmirror consumers */
function gmirror_get_unused_consumers() {
	$consumerlist = "";
	/* Get a list of consumers, exclude existing mirrors and diskid entries */
	exec("/sbin/geom part status -s | /usr/bin/egrep -v '(mirror|diskid)' | /usr/bin/awk '{print $1, $3;}'", $consumerlist);
	$all_consumers = array();
	foreach ($consumerlist as $cl) {
		$parts = explode(" ", $cl);
		foreach ($parts as $part)
			$all_consumers[] = $part;
	}
	return $all_consumers;
}

/* List all existing geom mirrors */
function gmirror_get_mirrors() {
	$mirrorlist = "";
	exec("/sbin/gmirror list | /usr/bin/grep '^Geom name:' | /usr/bin/awk '{print $3;}'", $mirrorlist);
	return $mirrorlist;
}


/* List all consumers for a given mirror */
function gmirror_get_consumers_in_mirror($mirror) {
	if (!is_valid_mirror($mirror))
		return array();

	$consumers = array();
	exec("/sbin/gmirror status -s " . escapeshellarg($mirror) . " | /usr/bin/awk '{print $3;}'", $consumers);
	return $consumers;
}

/* Test if a mirror exists */
function is_valid_mirror($mirror) {
	$mirrors = gmirror_get_mirrors();
	return in_array($mirror, $mirrors);
}

/* Test if a disk is valid/exists */
function is_valid_disk($disk) {
	$adisks = gmirror_get_disks();
	return in_array(basename($disk), $adisks);
}

/* Test if a consumer is valid and in use in a mirror */
function is_consumer_used($consumer) {
	$found = false;
	$mirrors = gmirror_get_mirrors();
	foreach ($mirrors as $mirror) {
		$consumers = gmirror_get_consumers_in_mirror($mirror);
		if (in_array($consumer, $consumers))
			return true;
	}
	return false;
}

/* Test if a consumer is valid and not in use */
function is_consumer_unused($consumer) {
	$consumers = gmirror_get_unused_consumers();
	return in_array($consumer, $consumers);
}

/* Test if a consumer is valid (either a disk or partition) */
function is_valid_consumer($consumer) {
	return (is_consumer_unused($consumer) || is_consumer_used($consumer));
}


/* Show all metadata on the physical consumer */
function gmirror_get_consumer_metadata($consumer) {
	if (!is_valid_consumer($consumer))
		return array();
	$output = "";
	exec("/sbin/gmirror dump " . escapeshellarg($consumer), $output);
	return array_map('trim', $output);
}

/* Return a list of all potential consumers on a disk with sizes. The geom part
	list output is a little odd, we can't get the output for just the disk, if the disk contains
	slices those get output also. */
function gmirror_get_all_unused_consumer_sizes_on_disk($disk) {
	if (!is_valid_disk($disk) || !is_consumer_unused($disk))
		return array();
	$output = "";
	exec("/sbin/geom part list " . escapeshellarg($disk) . " | /usr/bin/egrep '(Name:|Mediasize:)' | /usr/bin/cut -c4- | /usr/bin/sed -l -e 'N;s/\\nMediasize://;P;D;' | /usr/bin/cut -c7-", $output);
	$disk_contents = array();
	foreach ($output as $line) {
		list($name, $size, $humansize) = explode(" ", $line, 3);
		$consumer = array();
		$consumer['name'] = $name;
		$consumer['size'] = $size;
		$consumer['humansize'] = $humansize;
		$disk_contents[] = $consumer;
	}
	return $disk_contents;
}

?>
