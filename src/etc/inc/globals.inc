<?php

/*
    Copyright (C) 2004-2010 Scott Ullrich
    Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
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

global $g;

openlog('opnsense', LOG_ODELAY, LOG_USER);
register_shutdown_function('closelog');

$g = array(
	"factory_shipped_username" => "root",
	"factory_shipped_password" => "opnsense",
	"dhcpd_chroot_path" => "/var/dhcpd",
	"unbound_chroot_path" => "/var/unbound",
	"admin_group" => "admins",
	"product_name" => "OPNsense",
	"product_website" => "https://opnsense.org",
	"product_email" => "project@opnsense.org",
	"product_copyright_owner" => "Deciso B.V.",
	"product_copyright_years" => "2014-2015",
	"product_copyright_url" => "https://www.deciso.com/",
	"latest_config" => "11.2",
);

function is_install_media()
{
	/*
	 * Despite unionfs underneath, / is still not writeable,
	 * making the following the perfect test for install media.
	 */

	$file = '/.probe.for.install.media';
	$fd = @fopen($file, 'w');
	if ($fd) {
		fclose($fd);
		unlink($file);
		return false;
	}

	return true;
}

function match_wireless_interface($int)
{
	$wireless_regex = '/^(ndis|wi|ath|an|ral|ural|iwi|wlan|rum|run|bwn|zyd|mwl|bwi|ipw|iwn|malo|uath|upgt|urtw|wpi)/';
	return preg_match($wireless_regex, $int);
}
