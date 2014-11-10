<?php
/*
	diag_authentication.php
	part of the pfSense project	(https://www.pfsense.org)
	Copyright (C) 2010 Ermal Luçi

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
	pfSense_MODULE:	auth
*/

##|+PRIV
##|*IDENT=page-diagnostics-authentication
##|*NAME=Diagnostics: Authentication page
##|*DESCR=Allow access to the 'Diagnostics: Authentication' page.
##|*MATCH=diag_authentication.php*
##|-PRIV

require("guiconfig.inc");
require_once("PEAR.inc");
require_once("radius.inc");

if ($_POST) {
	$pconfig = $_POST;
	unset($input_errors);

	$authcfg = auth_get_authserver($_POST['authmode']);
	if (!$authcfg)
		$input_errors[] = $_POST['authmode'] . " " . gettext("is not a valid authentication server");

	if (empty($_POST['username']) || empty($_POST['password']))
		$input_errors[] = gettext("A username and password must be specified.");

	if (!$input_errors) {
		if (authenticate_user($_POST['username'], $_POST['password'], $authcfg)) {
			$savemsg = gettext("User") . ": " . $_POST['username'] . " " . gettext("authenticated successfully.");
			$groups = getUserGroups($_POST['username'], $authcfg);
			$savemsg .= "<br />" . gettext("This user is a member of these groups") . ": <br />";
			foreach ($groups as $group)
				$savemsg .= "{$group} ";
		} else {
			$input_errors[] = gettext("Authentication failed.");
		}
	}
}
$pgtitle = array(gettext("Diagnostics"),gettext("Authentication"));
$shortcut_section = "authentication";

include("head.inc");

?>

<body>

<?php include("fbegin.inc"); ?>

<?php if ($savemsg) print_info_box($savemsg);?>

<form id="iform" name="iform" action="<?php echo $_SERVER['REQUEST_URI'];?>" method="post">
<section class="page-content-main">
	<div class="container-fluid">	
		<div class="row">
			
			<?php if ($input_errors) print_input_errors($input_errors);?>
		        				
			<section class="col-xs-12">
                <div class="content-box">              
					
					<header class="content-box-head col-xs-12">
        			   <h3>Test a server</h3>
        			</header>
				    
				    <div class="content-box-main col-xs-12">
				    <div class="table-responsive">
    			        <table class="table table-striped">
    				        <tbody>
        				        <tr>
        				          <td><?=gettext("Authentication Server"); ?></td>
        				          <td><select name="authmode" id="authmode" class="form-control" >
									<?php
										$auth_servers = auth_get_authserver_list();
										foreach ($auth_servers as $auth_server):
											$selected = "";
											if ($auth_server['name'] == $pconfig['authmode'])
												$selected = "selected=\"selected\"";
									?>
									<option value="<?=$auth_server['name'];?>" <?=$selected;?>><?=$auth_server['name'];?></option>
									<?php   endforeach; ?>
									</select>
								</td>
        				        </tr>
        				        <tr>
        				          <td><?=gettext("Username"); ?></td>
        				          <td><input type="text" class="form-control formfld unknown" size="20" id="username" name="username" value="<?=htmlspecialchars($pconfig['username']);?>"></td>
        				        </tr>
        				        <tr>
        				          <td><?=gettext("Password"); ?></td>
        				          <td><input type="password" class="form-control formfld pwd" size="20" id="password" name="password" value="<?=htmlspecialchars($pconfig['password']);?>"></td>
        				        </tr>
        				        <tr>
        				          <td>&nbsp;</td>
        				          <td><input id="save" name="save" type="submit" class="btn btn-primary" value="<?=gettext("Test");?>" /></td>
        				        </tr>
    				        </tbody>
    				    </table>
    				    
    				    
				    </div>
				    </div>
                            
				</div>
			</section>
		
		</div>
		
	</div>
</section>
</form>

<?php include('foot.inc');?>