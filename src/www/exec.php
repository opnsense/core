<?php
/* $Id$ */
/*
	Copyright (C) 2014 - Deciso B.V.
	Exec+ v1.02-000 - Copyright 2001-2003
	Created by technologEase (http://www.technologEase.com)
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

$unsecure=true; // disabel editor for security purpose, need to be removed later
if ($unsecure) {
	exit;
}
$allowautocomplete = true;

require("guiconfig.inc");

if (($_POST['submit'] == "Download") && file_exists($_POST['dlPath'])) {
	session_cache_limiter('public');
	$fd = fopen($_POST['dlPath'], "rb");
	header("Content-Type: application/octet-stream");
	header("Content-Length: " . filesize($_POST['dlPath']));
	header("Content-Disposition: attachment; filename=\"" .
		trim(htmlentities(basename($_POST['dlPath']))) . "\"");
	if (isset($_SERVER['HTTPS'])) {
		header('Pragma: ');
		header('Cache-Control: ');
	} else {
		header("Pragma: private");
		header("Cache-Control: private, must-revalidate");
	}

	fpassthru($fd);
	exit;
} else if (($_POST['submit'] == "Upload") && is_uploaded_file($_FILES['ulfile']['tmp_name'])) {
	move_uploaded_file($_FILES['ulfile']['tmp_name'], "/tmp/" . $_FILES['ulfile']['name']);
	$ulmsg = "Uploaded file to /tmp/" . htmlentities($_FILES['ulfile']['name']);
	unset($_POST['txtCommand']);
}

if($_POST)
	conf_mount_rw();

// Function: is Blank
// Returns true or false depending on blankness of argument.

function isBlank( $arg ) { return preg_match( "/^\s*$/", $arg ); }


// Function: Puts
// Put string, Ruby-style.

function puts( $arg ) { echo "$arg\n"; }


// "Constants".

$Version    = '';
$ScriptName = $REQUEST['SCRIPT_NAME'];

// Get year.

$arrDT   = localtime();
$intYear = $arrDT[5] + 1900;

$closehead = false;
$pgtitle = array(gettext("Diagnostics"),gettext("Execute command"));
include("head.inc");
?>

<script type="text/javascript">
//<![CDATA[

   // Create recall buffer array (of encoded strings).

<?php

if (isBlank( $_POST['txtRecallBuffer'] )) {
	puts( "   var arrRecallBuffer = new Array;" );
} else {
	puts( "   var arrRecallBuffer = new Array(" );
	$arrBuffer = explode( "&", $_POST['txtRecallBuffer'] );
	for ($i=0; $i < (count( $arrBuffer ) - 1); $i++)
		puts( "      '" . htmlspecialchars($arrBuffer[$i], ENT_QUOTES | ENT_HTML401) . "'," );
	puts( "      '" . htmlspecialchars($arrBuffer[count( $arrBuffer ) - 1], ENT_QUOTES | ENT_HTML401) . "'" );
	puts( "   );" );
}

?>

   // Set pointer to end of recall buffer.
   var intRecallPtr = arrRecallBuffer.length-1;

   // Functions to extend String class.
   function str_encode() { return escape( this ) }
   function str_decode() { return unescape( this ) }

   // Extend string class to include encode() and decode() functions.
   String.prototype.encode = str_encode
   String.prototype.decode = str_decode

   // Function: is Blank
   // Returns boolean true or false if argument is blank.
   function isBlank( strArg ) { return strArg.match( /^\s*$/ ) }

   // Function: frmExecPlus onSubmit (event handler)
   // Builds the recall buffer from the command string on submit.
   function frmExecPlus_onSubmit( form ) {

      if (!isBlank(form.txtCommand.value)) {
		  // If this command is repeat of last command, then do not store command.
		  if (form.txtCommand.value.encode() == arrRecallBuffer[arrRecallBuffer.length-1]) { return true }

		  // Stuff encoded command string into the recall buffer.
		  if (isBlank(form.txtRecallBuffer.value))
			 form.txtRecallBuffer.value = form.txtCommand.value.encode();
		  else
			 form.txtRecallBuffer.value += '&' + form.txtCommand.value.encode();
	  }

      return true;
   }

   // Function: btnRecall onClick (event handler)
   // Recalls command buffer going either up or down.
   function btnRecall_onClick( form, n ) {

      // If nothing in recall buffer, then error.
      if (!arrRecallBuffer.length) {
         alert( '<?=gettext("Nothing to recall"); ?>!' );
         form.txtCommand.focus();
         return;
      }

      // Increment recall buffer pointer in positive or negative direction
      // according to <n>.
      intRecallPtr += n;

      // Make sure the buffer stays circular.
      if (intRecallPtr < 0) { intRecallPtr = arrRecallBuffer.length - 1 }
      if (intRecallPtr > (arrRecallBuffer.length - 1)) { intRecallPtr = 0 }

      // Recall the command.
      form.txtCommand.value = arrRecallBuffer[intRecallPtr].decode();
   }

   // Function: Reset onClick (event handler)
   // Resets form on reset button click event.
   function Reset_onClick( form ) {

      // Reset recall buffer pointer.
      intRecallPtr = arrRecallBuffer.length;

      // Clear form (could have spaces in it) and return focus ready for cmd.
      form.txtCommand.value = '';
      form.txtCommand.focus();

      return true;
   }
//]]>
</script>
</head>
<body>
	<?php include("fbegin.inc"); ?>

	<section class="page-content-main">
		<div class="container-fluid">
			<div class="row">

			    <form action="<?=$_SERVER['REQUEST_URI'];?>" method="post" enctype="multipart/form-data" name="frmExecPlus" onsubmit="return frmExecPlus_onSubmit( this );">

				<?php if ($ulmsg) echo "<p><strong>" . $ulmsg . "</strong></p>\n"; ?>

				<?php if (!isBlank($_POST['txtCommand']) || !isBlank($_POST['txtPHPCommand'])):?>
				<section class="col-xs-12">
	                <div class="content-box">

	                    <header class="content-box-head container-fluid">
				        <h3><?=gettext("Execute result"); ?></h3>
				    </header>

						<div class="content-box-main col-xs-12">
<?php
if (!isBlank($_POST['txtCommand'])) {
   puts("<pre>");
   puts("\$ " . htmlspecialchars($_POST['txtCommand']));
   putenv("PATH=/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:/usr/local/sbin");
   putenv("SCRIPT_FILENAME=" . strtok($_POST['txtCommand'], " "));	/* PHP scripts */
   $ph = popen($_POST['txtCommand'] . ' 2>&1', "r" );
   while ($line = fgets($ph)) echo htmlspecialchars($line);
   pclose($ph);
   puts("&nbsp;</pre>");
}

if (!isBlank($_POST['txtPHPCommand'])) {
   puts("<pre>");
   require_once("config.inc");
   require_once("functions.inc");
   echo eval($_POST['txtPHPCommand']);
   puts("&nbsp;</pre>");
}

?>

						</div>
	                </div>
				</section>
				<? endif; ?>

				<section class="col-xs-12">
                    <div class="content-box">

                        <header class="content-box-head container-fluid">
				        <h3><?=gettext("Execute Shell command"); ?></h3>
				    </header>

						 <div class="content-box-main">
							 <?php if (isBlank($_POST['txtCommand'])): ?>
							<p class="text-danger container-fluid"><strong><?=gettext("Note: this function is unsupported. Use it " .
							"on your own risk"); ?>!</strong></p>
							<?php endif; ?>

							<table class="table table-striped __nomb">
					        <tbody>
					        <tr>
					          <td width="150"><?=gettext("Command"); ?>:</td>
					          <td><input id="txtCommand" name="txtCommand" type="text" class="form-control" size="80" value="<?=htmlspecialchars($_POST['txtCommand']);?>" /></td>
					        </tr>
					         <tr>
								      <td>&nbsp;&nbsp;&nbsp;</td>
								      <td>
								         <input type="hidden" name="txtRecallBuffer" value="<?=htmlspecialchars($_POST['txtRecallBuffer']) ?>" />
								         <input type="button" class="btn" name="btnRecallPrev" value="<" onclick="btnRecall_onClick( this.form, -1 );" />
								         <input type="submit" class="btn btn-primary" value="<?=gettext("Execute"); ?>" />
								         <input type="button" class="btn" name="btnRecallNext" value=">" onclick="btnRecall_onClick( this.form,  1 );" />
								         <input type="button"  class="btn" value="<?=gettext("Clear"); ?>" onclick="return Reset_onClick( this.form );" />
								      </td>
								    </tr>
					        </tbody>
							</table>

						 </div>
                    </div>
				</section>



				<section class="col-xs-12">
                    <div class="content-box">

                        <header class="content-box-head container-fluid">
				        <h3><?=gettext("Download"); ?></h3>
				    </header>

						 <div class="content-box-main ">

							<table class="table table-striped __nomb">
					        <tbody>
								<tr>
										<td width="150"><?=gettext("File to download"); ?>:</td>
										<td><input name="dlPath" type="text" class="form-control file" id="dlPath" size="50" /></td>
								</tr>
								<tr>
										<td valign="top">&nbsp;</td>
										<td valign="top">
											<input name="submit" type="submit"  class="btn btn-primary" id="download" value="<?=gettext("Download"); ?>" />
									</td>
								</tr>
					        </tbody>
							</table>

						 </div>
                    </div>
				</section>



				<section class="col-xs-12">
                    <div class="content-box">

                        <header class="content-box-head container-fluid">
				        <h3><?=gettext("Upload"); ?></h3>
				    </header>

						 <div class="content-box-main">

							 <table class="table table-striped __nomb">
					        <tbody>
								<tr>
										<td width="150"><?=gettext("File to upload"); ?>:</td>
										<td><input name="ulfile" type="file" class="formfld file" id="ulfile" /></td>
								</tr>
								<tr>
										<td valign="top">&nbsp;</td>
										<td valign="top">
											<input name="submit" type="submit"  class="btn btn-primary" id="upload" value="<?=gettext("Upload"); ?>" />
									</td>
								</tr>
					        </tbody>
							</table>

						 </div>
                    </div>
				</section>



				<section class="col-xs-12">
                    <div class="content-box">

                        <header class="content-box-head container-fluid">
				        <h3><?=gettext("PHP Execute"); ?></h3>
				    </header>

						 <div class="content-box-main col-xs-12">

							<textarea id="txtPHPCommand" name="txtPHPCommand" rows="9" cols="80"><?=htmlspecialchars($_POST['txtPHPCommand']);?></textarea>
							<br />

							<input type="submit" class="btn btn-primary" value="<?=gettext("Execute"); ?>" />
							<p><strong><?=gettext("Example"); ?>:</strong>   interfaces_carp_setup();</p>

						 </div>
                    </div>
				</section>


				</form>

			</div>
		</div>
	</section>

	<?php include("foot.inc"); ?>



<!--


<?php



?>


	<tr>
	  <td colspan="2" valign="top" class="vnsepcell"><?=gettext("PHP Execute"); ?></td>
	</tr>
	<tr>
		<td align="right"><?=gettext("Command"); ?>:</td>
		<td class="type"><textarea id="txtPHPCommand" name="txtPHPCommand" rows="9" cols="80"><?=htmlspecialchars($_POST['txtPHPCommand']);?></textarea></td>
	</tr>
    <tr>
      <td valign="top">&nbsp;&nbsp;&nbsp;</td>
      <td valign="top" class="label">
         <input type="submit" class="button" value="<?=gettext("Execute"); ?>" />
	 <p>
	 <strong><?=gettext("Example"); ?>:</strong>   interfaces_carp_setup();
	 </p>
      </td>
    </tr>

  </table>
</form>
</div>
<?php include("fend.inc"); ?>
<script type="text/javascript">
//<![CDATA[
document.forms[0].txtCommand.focus();
//]]>
</script>
</body>
</html>

<?php

if($_POST)
	conf_mount_ro();

?>

-->
