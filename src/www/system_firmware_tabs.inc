<?php

/*
	Copyright (C) 2014-2015 Deciso B.V.
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

$active_tab = isset($active_tab) ? $active_tab : $_SERVER['PHP_SELF'];
$tab_array = array();
//$tab_array[] = array(gettext("Manual Update"), $active_tab == "/system_firmware.php", "system_firmware.php");
$tab_array[] = array(gettext("Auto Update"), $active_tab == "/system_firmware_check.php", "system_firmware_check.php");
$tab_array[] = array(gettext("Updater Settings"), $active_tab == "/system_firmware_settings.php", "system_firmware_settings.php");
//$tab_array[] = array(gettext("Restore Full Backup"), $active_tab == "/system_firmware_restorefullbackup.php", "system_firmware_restorefullbackup.php");

display_top_tabs($tab_array);
