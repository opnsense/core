<?php
/*
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

require("guiconfig.inc");
include("head.inc");

$pgtitle = gettext("License");

?>

<body>
<?php include("fbegin.inc"); ?>


<section class="page-content-main">
	<div class="container-fluid ">
		<div class="row">
		    <section class="col-xs-11">
            <p align="justify">
            <strong>
              <span class="text-primary"><a href="http://www.opnsense.org" target="_blank">OPNsense&reg;</a></span> is Copyright &copy; <?=$g['product_copyright_years'];?> by Deciso B.V.<br />All rights reserved.<br />
            </strong>
            Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:<br />
            <br />
            1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.<br />
            <br />
            2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.<br />
            <br />
            <strong>
            THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,<br />
            INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF  MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. 
            IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, 
            PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) 
            HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) 
            ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.<br />
            </strong><br />
            OPNsense is based on <a href="http://www.freebsd.org" target="_blank">FreeBSD</a><br />
            Copyright &copy; The FreeBSD Project. All rights reserved.<br />
            <br />
            OPNsense is a fork of <a href="http://www.pfsense.org" target="_blank">pfSense&reg;</a> <small>(Copyright &copy; 2004-2014 Electric Sheep Fencing, LLC. All rights reserved.)</small> a fork from <a href="http://m0n0.ch/wall" target="_blank">m0n0wall&reg;</a> <small>(Copyright &copy; 2002-2013 Manuel Kasper)</small>.<br />
            <br />
            OPNsense includes various freely availbale software packages and ports.<br />
            The current ports are listed <a href="https://github.com/opnsense/tools/blob/master/config/current/ports" target="_blank">here</a>.<br />
            <br />
            The authors of OPNsense would like to thank all contributors for their efforts.<br />
          </p>
		    </section>
		</div>
	</div>
</section>

<?php include("foot.inc"); ?>
