<?php
/*
	Copyright (C) 2014 Deciso B.V.
	Copyright (C) 2012
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

include('guiconfig.inc');

$pgtitle = array(gettext("Diagnostics"),gettext("Sockets"));

include('head.inc');

?>
<body>
<?php include("fbegin.inc");

$showAll = isset($_GET['showAll']);
$showAllText = $showAll ? gettext("Show only listening sockets") : gettext("Show all socket connections");
$showAllOption = $showAll ? "" : "?showAll";

?>

<section class="page-content-main">
	<div class="container-fluid">
		<div class="row">

			<section class="col-xs-12">

      <p><?= sprintf(gettext('Information about listening sockets for both %s and %s.'), sprintf('<a href="#IPv4">%s</a>', gettext('IPv4')),sprintf('<a href="#IPv6">%s</a>', gettext('IPv6'))) ?></p>
      <p><?= sprintf(gettext('For explanation about the meaning of the information listed for each socket click %shere%s.'),'<a href="#about">','</a>') ?></p>
      <p><input type="button" class="btn btn-default" value="<?=$showAllText?>" onclick="window.location.href='diag_sockets.php<?=$showAllOption?>'"/><br/><?= gettext('To show information about both listening and connected sockets click this.') ?></p>

				<?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
			</section>


<?php
	if (isset($_GET['showAll']))
	{
		$internet4 = shell_exec('sockstat -4');
		$internet6 = shell_exec('sockstat -6');
	} else {
		$internet4 = shell_exec('sockstat -4lL');
		$internet6 = shell_exec('sockstat -6lL');
	}
	foreach (array(&$internet4, &$internet6) as $tabindex => $table) {
		$elements = ($tabindex == 0 ? 7 : 7);
		$name = ($tabindex == 0 ? 'IPv4' : 'IPv6');
?>


			<section class="col-xs-12">

                <div class="content-box">

                    <header class="content-box-head container-fluid">
				        <h3><a name="<?=$name;?>"></a><?=$name;?></h3>
				    </header>

				     <table class="table table-striped table-sort sortable __nomb" id="IPv4" summary="ipv4 routes">
						<?php
							foreach (explode("\n", $table) as $i => $line) {
								if ($i == 0)
									$class = 'listhdrr';
								else
									$class = 'listlr';

								if (trim($line) == "")
									continue;
								print("<tr id=\"$name$i\">\n");
								$j = 0;
								foreach (explode(' ', $line) as $entry) {
									if ($entry == '' || $entry == "ADDRESS") continue;
									if ($i == 0)
										print("<th class=\"$class\">$entry</th>\n");
									else
										print("<td class=\"$class\">$entry</td>\n");
									if ($i > 0)
										$class = 'listr';
									$j++;
								}
								print("</tr>\n");
							}?>
					</table>
                </div>
			</section>
<?php
	}
?>

			<section class="col-xs-12">

                <div class="content-box">

                    <header class="content-box-head container-fluid">
				        <h3><a name="about"></a>Socket information explanation</h3>
				    </header>

				    <div class="content-box-main col-xs-12">
            <p><?= sprintf(gettext('This page show the output for the commands: "sockstat -4lL" and "sockstat -6lL".%s' .
                                    'Or in case of showing all sockets the output for: "sockstat -4" and "sockstat -6".%s%s' .
                                    'The information listed for each socket is:'),'<br />','<br />','<br />') ?></p>
				    </div>

					<table class="table table-striped table-sort sortable __nomb" id="IPv4" summary="ipv4 routes">
          <tr><td class="listlr">USER	      </td><td class="listr"><?= gettext('The user who owns the socket.') ?></td></tr>
          <tr><td class="listlr">COMMAND	      </td><td class="listr"><?= gettext('The command which holds the socket.') ?></td></tr>
          <tr><td class="listlr">PID	      </td><td class="listr"><?= gettext('The process ID of the command which holds the socket.') ?></td></tr>
          <tr><td class="listlr">FD	      </td><td class="listr"><?= gettext('The file descriptor number of the socket.') ?></td></tr>
          <tr><td class="listlr">PROTO	      </td><td class="listr"><?= gettext('The transport protocol associated with the socket for Internet sockets, or the type of socket (stream or data-gram) for UNIX sockets.') ?></td></tr>
          <tr><td class="listlr">ADDRESS	      </td><td class="listr"><?= sprintf(gettext("(UNIX sockets only) For bound sockets, this is the file-name of the socket. %sFor other sockets, it is the name, PID and file descriptor number of the peer, or ``(none)'' if the socket is neither bound nor connected."),'<br />') ?></td></tr>
          <tr><td class="listlr">LOCAL ADDRESS    </td><td class="listr"><?= gettext('(Internet sockets only) The address the local end of the socket is bound to (see getsockname(2)).') ?></td></tr>
          <tr><td class="listlr">FOREIGN ADDRESS  </td><td class="listr"><?= gettext('(Internet sockets only) The address the foreign end of the socket is bound to (see getpeername(2)).') ?></td></tr>
					</table>

                </div>
			</section>
		</div>
	</div>
</section>

<?php include('foot.inc'); ?>
