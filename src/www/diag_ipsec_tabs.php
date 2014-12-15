<?php
/*
	Copyright (C) 2014 Deciso B.V.
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
	$tab_array = array();
	$tab_array[0] = array(gettext("Overview"), $_SERVER['PHP_SELF'] == '/diag_ipsec.php', "diag_ipsec.php");
	$tab_array[1] = array(gettext("Leases"), $_SERVER['PHP_SELF'] == '/diag_ipsec_leases.php', "diag_ipsec_leases.php");
	$tab_array[2] = array(gettext("SAD"), $_SERVER['PHP_SELF'] == '/diag_ipsec_sad.php', "diag_ipsec_sad.php");
	$tab_array[3] = array(gettext("SPD"), $_SERVER['PHP_SELF'] == '/diag_ipsec_spd.php', "diag_ipsec_spd.php");
	$tab_array[4] = array(gettext("Logs"), $_SERVER['PHP_SELF'] == '/diag_logs_ipsec.php', "diag_logs_ipsec.php");
	display_top_tabs($tab_array);
?>