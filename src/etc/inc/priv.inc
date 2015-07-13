<?php

/*
	Copyright (C) 2008 Shrew Soft Inc
	Copyright (C) 2007-2008 Scott Ullrich <sullrich@gmail.com>
        Copyright (C) 2005-2006 Bill Marquette <bill.marquette@gmail.com>
        Copyright (C) 2006 Paul Taylor <paultaylor@winn-dixie.com>
        Copyright (C) 2003-2006 Manuel Kasper <mk@neon1.net>
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
require_once("script/load_phalcon.php");
global $priv_list;
$acl = new OPNsense\Core\ACL();
$priv_list = $acl->getLegacyPrivList();


function cmp_page_matches($page, & $matches, $fullwc = true) {
	if (!is_array($matches))
		return false;

	/* skip any leading fwdslash */
	$test = strpos($page, "/");
	if ($test !== false && $test == 0)
		$page = substr($page, 1);

	/* look for a match */
	foreach ($matches as $match) {

		/* possibly ignore full wildcard match */
		if (!$fullwc && !strcmp($match ,"*"))
			continue;

		/* compare exact or wildcard match */
		$match =  str_replace(array(".", "*","?"), array("\.", ".*","\?"), $match);
		$result = preg_match("@^/{$match}$@", "/{$page}");

		if ($result)
			return true;
	}

	return false;
}


function isAllowed($username, $page)
{
	global $_SESSION;

	if (!isset($username)) {
		return false;
	}

	/* root access check */
	$user = getUserEntry($username);
	if (isset($user)) {
		if (isset($user['uid'])) {
			if ($user['uid'] == 0) {
				return true;
			}
		}
	}

	/* user privelege access check */
	if (cmp_page_matches($page, $_SESSION['page-match'])) {
		return true;
	}

	return false;
}

function isAllowedPage($page)
{
	if (!isset($_SESSION['Username'])) {
		return false;
	}

	/* root access check */
	$user = getUserEntry($_SESSION['Username']);
	if (isset($user)) {
		if (isset($user['uid'])) {
			if ($user['uid'] == 0) {
				return true;
			}
		}
	}

	/* user privelege access check */
	return cmp_page_matches($page, $_SESSION['page-match']);
}


?>
