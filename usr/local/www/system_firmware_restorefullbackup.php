<?php
/* $Id$ */
/*
	system_firmware_restorefullbackup.php
	Copyright (C) 2011 Scott Ullrich
	All rights reserved.

	originally part of m0n0wall (http://m0n0.ch/wall)
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

/*
	pfSense_BUILDER_BINARIES:	/etc/rc.restore_full_backup
	pfSense_MODULE:	backup
*/

##|+PRIV
##|*IDENT=page-diagnostics-restore-full-backup
##|*NAME=Diagnostics: Restore full backup
##|*DESCR=Allow access to the 'Diagnostics: Restore Full Backup' page.
##|*MATCH=system_firmware_restorefullbackup.php
##|-PRIV

/* Allow additional execution time 0 = no limit. */
ini_set('max_execution_time', '0');
ini_set('max_input_time', '0');

require_once("functions.inc");
require("guiconfig.inc");
require_once("filter.inc");
require_once("shaper.inc");

if($_POST['overwriteconfigxml'])
	touch("/tmp/do_not_restore_config.xml");

if($_GET['backupnow'])
	mwexec_bg("/etc/rc.create_full_backup");

if($_GET['downloadbackup']) {
	$filename = basename($_GET['downloadbackup']);
	$path = "/root/{$filename}";
	if(file_exists($path)) {
		session_write_close();
		ob_end_clean();
		session_cache_limiter('public');
		//$fd = fopen("/root/{$filename}", "rb");
		$filesize = filesize("/root/{$filename}");
		header("Cache-Control: ");
		header("Pragma: ");
		header("Content-Type: application/octet-stream");
		header("Content-Length: " .(string)(filesize($path)) );
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		header("Content-Transfer-Encoding: binary\n");
		if($file = fopen("/root/{$filename}", 'rb')){
			while( (!feof($file)) && (connection_status()==0) ){
				print(fread($file, 1024*8));
				flush();
			}
			fclose($file);
		}

		exit;
	}
}

if ($_GET['deletefile']) {
	$filename = $_GET['deletefile'];
	if(file_exists("/root/{$filename}")) {
		unlink("/root/" . $filename);
		$savemsg = gettext("$filename has been deleted.");
	}
}

if ($_POST['restorefile']) {
	$filename = $_POST['restorefile'];
	if(file_exists("/root/{$filename}")) {
		mwexec_bg("/etc/rc.restore_full_backup /root/" . escapeshellcmd($filename));
		$savemsg = gettext("The firewall is currently restoring $filename");
	}
}

$pgtitle = array(gettext("Diagnostics"),gettext("Restore full backup"));
include("head.inc");

?>

<body>
<?php include("fbegin.inc"); ?>

<!-- row -->
<section class="page-content-main">
	<div class="container-fluid">
        
        <div class="row">
            <?php
            	if ($input_errors) print_input_errors($input_errors);
            	if ($savemsg) print_info_box($savemsg);
            ?>
            <section class="col-xs-12">
                
                <? include('system_firmware_tabs.php'); ?>                
                
                <div class="content-box tab-content">

                    <?php if (is_subsystem_dirty('restore')): ?><p>
                    
                    <form action="reboot.php" method="post">
                        <input name="Submit" type="hidden" value="Yes" />
                        <?php print_info_box(gettext("The firewall configuration has been changed.") . "<br />" . gettext("The firewall is now rebooting."));?><br />
                    </form>
                    
                    <?php endif; ?>
    
                    <form action="system_firmware_restorefullbackup.php" method="post">
                
            			<table class="table table-striped" align="center" width="100%" border="0" cellpadding="6" cellspacing="0" summary="main area">
            				
            				<thead>
                				<tr>
                					<th colspan="1" class="listtopic"><?=gettext("Filename"); ?></th>
                					<th colspan="1" class="listtopic"><?=gettext("Date"); ?></th>
                					<th colspan="2" class="listtopic"><?=gettext("Size"); ?></th>
                				</tr>
            				</thead>
                            
                            <tbody>
                            
                                <?php
                    				chdir("/root");
                    				$available_restore_files = glob("pfSense-full-backup-*");
                    				$counter = 0;
                    				foreach($available_restore_files as $arf) {
                    					$counter++;
                    					$size = exec("gzip -l /root/$arf | grep -v compressed | awk '{ print $2 }'");
                    					echo "<tr>";
                    					echo "<td  class='listlr' width='50%' colspan='1'>";
                    					echo "<input type='radio' name='restorefile' value='$arf' /> $arf";
                    					echo "</td>";
                    					echo "<td  class='listr' width='30%' colspan='1'>";
                    					echo date ("F d Y H:i:s", filemtime($arf));
                    					echo "</td>";
                    					echo "<td  class='listr' width='40%' colspan='1'>";
                    					echo format_bytes($size);
                    					echo "</td>";
                    					echo "<td  class='listr nowrap' width='20%' colspan='1'>";
                    					echo "<a onclick=\"return confirm('" . gettext("Do you really want to delete this backup?") . "')\" href='system_firmware_restorefullbackup.php?deletefile=" . htmlspecialchars($arf) . "'>";
                    					echo gettext("Delete");
                    					echo "</a> | ";
                    					echo "<a href='system_firmware_restorefullbackup.php?downloadbackup=" . htmlspecialchars($arf) . "'>";
                    					echo gettext("Download");
                    					echo "</a>";
                    					echo "</td>";
                    					echo "</tr>";
                    				}
                    				if($counter == 0) {
                    					echo "<tr>";
                    					echo "<td  class='listlr' width='100%' colspan='4' align='center'>";
                    					echo gettext("Could not locate any previous backups.");
                    					echo "</td>";
                    					echo "</tr>";
                    				}
                                ?>
                				<tr>
                					<td width="78%" colspan="3">
                						&nbsp;<br />
                						<input type="checkbox" name="overwriteconfigxml" id="overwriteconfigxml" checked="checked" /> <?=gettext("do not restore config.xml."); ?>
                						<br />
                						<input name="Restore" type="submit" class="btn btn-primary" id="restore" value="<?=gettext("Restore"); ?>" />
                					</td>
                				</tr>
                				
                            </tbody>
                            
            			</table>
                    </form>

                </div>
            </section>
        </div>
	</div>
</section>

<script type="text/javascript">
//<![CDATA[
encrypt_change();
decrypt_change();
//]]>
</script>

<?php include("foot.inc"); ?>

<?php
if (is_subsystem_dirty('restore'))
	system_reboot();
?>
