<?php
/* $Id$ */
/*
	halt.php
	part of pfSense
	Copyright (C) 2004 Scott Ullrich
	All rights reserved.

	part of m0n0wall as reboot.php (http://m0n0.ch/wall)
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
	pfSense_MODULE:	header
*/

##|+PRIV
##|*IDENT=page-diagnostics-haltsystem
##|*NAME=Diagnostics: Halt system page
##|*DESCR=Allow access to the 'Diagnostics: Halt system' page.
##|*MATCH=halt.php*
##|-PRIV

require("guiconfig.inc");
require("functions.inc");
require("captiveportal.inc");


$pgtitle = array(gettext("Diagnostics"),gettext("Halt system"));
include('head.inc');
?>

<body>
<?php include("fbegin.inc"); ?>

<section class="page-content-main">
	<div class="container-fluid col-xs-12 col-sm-10 col-md-9">
		<div class="row">
		    <section class="col-xs-12">
			    
			<?php if ($_POST['Submit'] == gettext("Yes")):
				print_info_box(gettext("The system is halting now. This may take one minute.")); ?>
			<pre>
			<?php 	system_halt(); ?>
			</pre>
			<?php else: ?>

			    
			<form action="<?=$_SERVER['REQUEST_URI'];?>" method="post">
				<p><strong><?=gettext("Are you sure you want to halt the system?");?></strong></p>
				
				<div class="btn-group">
				   <input type="submit" name="Submit" class="btn btn-primary" value="<?=gettext("Yes");?>" />
				  <a href="/" class="btn btn-default"><?=gettext("No");?></a>
				</div>
				<br /><br />
			</form>

			<?php endif; ?>
		    </section>
		</div>
	</div>
</section>

<?php include("foot.inc"); ?>