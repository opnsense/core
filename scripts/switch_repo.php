#!/usr/local/bin/php
<?php

/*
	Copyright (C) 2015 Franco Fichtner <franco@opnsense.org>
	All rights reserved

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

/* test scipt only, not in production */
$pkg_mirror = 'http://pkg.opnsense.org';
$pkg_flavour = 'latest';

if (count($argv) > 1) {
	$pkg_flavour = $argv[1];
}
if (count($argv) > 2) {
	$pkg_mirror = $argv[2];
}

$pkg_sample = file_get_contents('/usr/local/etc/pkg/repos/origin.conf.sample');
$pkg_sample = explode(PHP_EOL, $pkg_sample);
$pkg_config = '';

foreach ($pkg_sample as $pkg_line) {
	if (!strlen($pkg_line)) {
		continue;
	} elseif (!strncasecmp($pkg_line, '  url:', 6)) {
		$pkg_line = sprintf(
			'  url: "pkg+%s/${ABI}/%s",',
			$pkg_mirror,
			$pkg_flavour
		);
	}
	$pkg_config .= $pkg_line . PHP_EOL;
}

file_put_contents('/usr/local/etc/pkg/repos/origin.conf', $pkg_config);
