<?php

/*
	Copyright (C) 2014 Deciso B.V.
	Copyright (C) 2010 Ermal Luçi
	Copyright (C) 2007, 2008 Scott Ullrich <sullrich@gmail.com>
        Copyright (C) 2005-2006 Bill Marquette <bill.marquette@gmail.com>
        Copyright (C) 2006 Paul Taylor <paultaylor@winn-dixie.com>.
        Copyright (C) 2003-2006 Manuel Kasper <mk@neon1.net>.
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
 * NOTE : Portions of the mschapv2 support was based on the BSD licensed CHAP.php
 * file courtesy of Michael Retterklieber.
 */

/* include globals from notices.inc /utility/XML parser files */
require_once("IPv6.inc");
require_once("certs.inc");
require_once("radius.inc");


// Will be changed to false if security checks fail
$security_passed = true;

/* If this function doesn't exist, we're being called from Captive Portal or
   another internal subsystem which does not include authgui.inc */
if (function_exists("display_error_form") && !isset($config['system']['webgui']['nodnsrebindcheck'])) {
	/* DNS ReBinding attack prevention */
	$found_host = false;

	/* Either a IPv6 address with or without a alternate port */
	if(strstr($_SERVER['HTTP_HOST'], "]")) {
		$http_host_port = explode("]", $_SERVER['HTTP_HOST']);
		/* v6 address has more parts, drop the last part */
		if(count($http_host_port) > 1) {
			array_pop($http_host_port);
			$http_host = str_replace(array("[", "]"), "", implode(":", $http_host_port));
		} else {
			$http_host = str_replace(array("[", "]"), "", implode(":", $http_host_port));
		}
	} else {
		$http_host = explode(":", $_SERVER['HTTP_HOST']);
		$http_host = $http_host[0];
	}
	if(is_ipaddr($http_host) or $_SERVER['SERVER_ADDR'] == "127.0.0.1" or
			strcasecmp($http_host, "localhost") == 0 or $_SERVER['SERVER_ADDR'] == "::1")
		$found_host = true;
	if(strcasecmp($http_host, $config['system']['hostname'] . "." . $config['system']['domain']) == 0 or
			strcasecmp($http_host, $config['system']['hostname']) == 0)
		$found_host = true;

	if(isset($config['dyndnses']['dyndns']) && is_array($config['dyndnses']['dyndns']) && !$found_host)
		foreach($config['dyndnses']['dyndns'] as $dyndns)
			if(strcasecmp($dyndns['host'], $http_host) == 0) {
				$found_host = true;
				break;
			}

	if(isset($config['dnsupdates']['dnsupdate']) && is_array($config['dnsupdates']['dnsupdate']) && !$found_host)
		foreach($config['dnsupdates']['dnsupdate'] as $rfc2136)
			if(strcasecmp($rfc2136['host'], $http_host) == 0) {
				$found_host = true;
				break;
			}

	if(!empty($config['system']['webgui']['althostnames']) && !$found_host) {
		$althosts = explode(" ", $config['system']['webgui']['althostnames']);
		foreach ($althosts as $ah)
			if(strcasecmp($ah, $http_host) == 0 or strcasecmp($ah, $_SERVER['SERVER_ADDR']) == 0) {
				$found_host = true;
				break;
			}
	}

	if($found_host == false) {
		if(!security_checks_disabled()) {
			display_error_form("501", gettext("Potential DNS Rebind attack detected, see http://en.wikipedia.org/wiki/DNS_rebinding<br />Try accessing the router by IP address instead of by hostname."));
			exit;
		}
		$security_passed = false;
	}
}

// If the HTTP_REFERER is something other than ourselves then disallow.
if(function_exists("display_error_form") && !isset($config['system']['webgui']['nohttpreferercheck'])) {
	if(isset($_SERVER['HTTP_REFERER'])) {
		if(file_exists('/tmp/setupwizard_lastreferrer')) {
			if($_SERVER['HTTP_REFERER'] == file_get_contents('/tmp/setupwizard_lastreferrer')) {
				unlink('/tmp/setupwizard_lastreferrer');
				header("Refresh: 1; url=index.php");
				echo "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\"\n        \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">";
				echo "<html><head><title>" . gettext("Redirecting...") . "</title></head><body>" . gettext("Redirecting to the dashboard...") . "</body></html>";
				exit;
			}
		}
		$found_host = false;
		$referrer_host = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
		$referrer_host = str_replace(array("[", "]"), "", $referrer_host);
		if($referrer_host) {
			if(strcasecmp($referrer_host, $config['system']['hostname'] . "." . $config['system']['domain']) == 0
					|| strcasecmp($referrer_host, $config['system']['hostname']) == 0)
				$found_host = true;

			if(!empty($config['system']['webgui']['althostnames']) && !$found_host) {
				$althosts = explode(" ", $config['system']['webgui']['althostnames']);
				foreach ($althosts as $ah) {
					if(strcasecmp($referrer_host, $ah) == 0) {
						$found_host = true;
						break;
					}
				}
			}

			if(isset($config['dyndnses']['dyndns']) && is_array($config['dyndnses']['dyndns']) && !$found_host)
				foreach($config['dyndnses']['dyndns'] as $dyndns)
					if(strcasecmp($dyndns['host'], $referrer_host) == 0) {
						$found_host = true;
						break;
					}

			if(isset($config['dnsupdates']['dnsupdate']) && is_array($config['dnsupdates']['dnsupdate']) && !$found_host)
				foreach($config['dnsupdates']['dnsupdate'] as $rfc2136)
					if(strcasecmp($rfc2136['host'], $referrer_host) == 0) {
						$found_host = true;
						break;
					}

			if(!$found_host) {
				$interface_list_ips = get_configured_ip_addresses();
				foreach($interface_list_ips as $ilips) {
					if(strcasecmp($referrer_host, $ilips) == 0) {
						$found_host = true;
						break;
					}
				}
				$interface_list_ipv6s = get_configured_ipv6_addresses();
				foreach($interface_list_ipv6s as $ilipv6s) {
					if(strcasecmp($referrer_host, $ilipv6s) == 0) {
						$found_host = true;
						break;
					}
				}
				if($referrer_host == "127.0.0.1" || $referrer_host == "localhost") {
					// allow SSH port forwarded connections and links from localhost
					$found_host = true;
				}
			}
		}
		if($found_host == false) {
			if(!security_checks_disabled()) {
				display_error_form("501", "An HTTP_REFERER was detected other than what is defined in System -> Advanced (" . htmlspecialchars($_SERVER['HTTP_REFERER']) . ").  You can disable this check if needed in System -> Advanced -> Admin.");
				exit;
			}
			$security_passed = false;
		}
	} else
		$security_passed = false;
}

if (function_exists("display_error_form") && $security_passed) {
	/* Security checks passed, so it should be OK to turn them back on */
	restore_security_checks();
}
unset($security_passed);

$groupindex = index_groups();
$userindex = index_users();

function index_groups()
{
	global $config, $groupindex;

	$groupindex = array();

	if (isset($config['system']['group'])) {
		$i = 0;
		foreach($config['system']['group'] as $groupent) {
			$groupindex[$groupent['name']] = $i;
			$i++;
		}
	}

	return ($groupindex);
}

function index_users()
{
	global $config;

	if (is_array($config['system']['user'])) {
		$i = 0;
		foreach($config['system']['user'] as $userent) {
			$userindex[$userent['name']] = $i;
			$i++;
		}
	}

	return ($userindex);
}

function &getUserEntry($name)
{
	global $config, $userindex;
	if (isset($userindex[$name]))
		return $config['system']['user'][$userindex[$name]];
}

function &getUserEntryByUID($uid)
{
	global $config;

	if (is_array($config['system']['user']))
		foreach ($config['system']['user'] as & $user)
			if ($user['uid'] == $uid)
				return $user;

	return false;
}

function &getGroupEntry($name)
{
	global $config, $groupindex;

	if (isset($groupindex[$name])) {
		return $config['system']['group'][$groupindex[$name]];
	}
}

function &getGroupEntryByGID($gid)
{
	global $config;

	if (isset($config['system']['group'])) {
		foreach ($config['system']['group'] as & $group) {
			if ($group['gid'] == $gid) {
				return $group;
			}
		}
	}

	return false;
}

function get_user_privileges(&$user)
{
        $privs = $user['priv'];
        if (!is_array($privs)) {
                $privs = array();
	}

        $names = local_user_get_groups($user, true);

        foreach ($names as $name) {
                $group = getGroupEntry($name);
                if (is_array($group['priv'])) {
                        $privs = array_merge($privs, $group['priv']);
		}
        }

        return $privs;
}

function userHasPrivilege($userent, $privid = false) {

        if (!$privid || !is_array($userent))
                return false;

        $privs = get_user_privileges($userent);

        if (!is_array($privs))
                return false;

        if (!in_array($privid, $privs))
                return false;

        return true;
}

function local_backed($username, $passwd) {

	$user = getUserEntry($username);
	if (!$user)
		return false;

	if (is_account_disabled($username) || is_account_expired($username))
		return false;

	if ($user['password'])
	{
		$passwd = crypt($passwd, $user['password']);
		if ($passwd == $user['password'])
			return true;
	}

	return false;
}

function local_sync_accounts()
{
	global $config;

	/* remove local users to avoid uid conflicts */
	$fd = popen('/usr/sbin/pw usershow -a', 'r');
	if ($fd) {
		while (!feof($fd)) {
			$line = explode(':',fgets($fd));
			if ( count($line) < 3 ||  !strncmp($line[0], '_', 1) || $line[2] < 2000 || $line[2] > 65000) {
				continue;
			}
			/*
			 * If a crontab was created to user, pw userdel will be interactive and
			 * can cause issues. Just remove crontab before run it when necessary
			 */
			@unlink("/var/cron/tabs/{$line[0]}");
			mwexecf('/usr/sbin/pw userdel -n %s', $line[0]);
		}
		pclose($fd);
	}

	/* remove local groups to avoid gid conflicts */
	$gids = array();
	$fd = popen('/usr/sbin/pw groupshow -a', 'r');
	if ($fd) {
		while (!feof($fd)) {
			$line = explode(':',fgets($fd));
			if (count($line) < 3 || !strncmp($line[0], '_', 1) || $line[2] < 2000 || $line[2] > 65000 ) {
				continue;
			}
			mwexecf('/usr/sbin/pw groupdel %s', $line[2]);
		}
		pclose($fd);
	}

	/* make sure the all group exists */
	$allgrp = getGroupEntryByGID(1998);
	local_group_set($allgrp, true);

	/* sync all local users */
	if (is_array($config['system']['user'])) {
		foreach ($config['system']['user'] as $user) {
			local_user_set($user);
		}
	}

	/* sync all local groups */
	if (is_array($config['system']['group'])) {
		foreach ($config['system']['group'] as $group) {
			local_group_set($group);
		}
	}
}

function local_user_set(&$user)
{
	if (empty($user['password'])) {
		log_error(sprintf(
			gettext('There is something wrong in your config because user %s password is missing!'),
			$user['name']
		));
		return;
	}

	$user_uid = $user['uid'];
	$user_name = $user['name'];
	$user_home = "/home/{$user_name}";
	$user_shell = '/sbin/nologin';
	$user_group = 'nobody';
	$lock_account = 'lock';

	@mkdir('/home', 0755);

	/* admins access gives wheely rights */
	if (userHasPrivilege($user, 'page-all')) {
		$user_group = 'wheel';
	}

	/* configure shell type */
	if (userHasPrivilege($user, 'user-shell-access')) {
		$user_shell = '/bin/csh';
	}

	/* unlock valid shell users */
	if (!is_account_disabled($user_name) && !is_account_expired($user_name)) {
		$lock_account = 'unlock';
	}

	/* root user special handling */
	if ($user_uid == 0) {
		$user_group = 'wheel';
		$user_home = '/root';
		$user_shell = '/usr/local/etc/rc.initial';
		$lock_account = 'unlock';
	}

	/* read from pw db */
	$fd = popen("/usr/sbin/pw usershow -n {$user_name} 2>&1", "r");
	$pwread = fgets($fd);
	pclose($fd);
	$userattrs = explode(":", trim($pwread));

	/* determine add or mod */
	if (($userattrs[0] != $user['name']) || (!strncmp($pwread, 'pw:', 3))) {
		$user_op = 'useradd -m -k /usr/share/skel -o';
	} else {
		$user_op = 'usermod';
	}

	$comment = str_replace(array(':', '!', '@'), ' ', $user['descr']);
	/* add or mod pw db */
	$cmd = "/usr/sbin/pw {$user_op} -q -u {$user_uid} -n {$user_name}".
		" -g {$user_group} -s {$user_shell} -d {$user_home}".
		" -c ".escapeshellarg($comment)." -H 0 2>&1";
	$fd = popen($cmd, 'w');
	fwrite($fd, $user['password']);
	pclose($fd);

	/* create user directory if required */
	@mkdir($user_home, 0700);
	@chown($user_home, $user_name);
	@chgrp($user_home, $user_group);

	/* write out ssh authorized key file */
	if (isset($user['authorizedkeys'])) {
		@mkdir("{$user_home}/.ssh", 0700);
		@chown("{$user_home}/.ssh", $user_name);
		$keys = base64_decode($user['authorizedkeys']);
		@file_put_contents("{$user_home}/.ssh/authorized_keys", $keys);
		@chown("{$user_home}/.ssh/authorized_keys", $user_name);
	} else {
		@unlink("{$user_home}/.ssh/authorized_keys");
	}

	mwexecf('/usr/sbin/pw %s %s', array($lock_account, $user_name), true);
}

function local_user_del($user)
{
	/* remove all memberships */
	local_user_set_groups($user);

	/* delete from pw db */
	mwexecf('/usr/sbin/pw userdel -n %s -r', $user['name']);

	/* Delete user from groups needs a call to write_config() */
	local_group_del_user($user);
}

function local_user_set_password(& $user, $password)
{
	$user['password'] = crypt($password, '$6$');

	// Converts ascii to unicode.
	$astr = (string) $password;
	$ustr = '';
	for ($i = 0; $i < strlen($astr); $i++) {
		$a = ord($astr{$i}) << 8;
		$ustr.= sprintf("%X", $a);
	}

	// Generate the NT-HASH from the unicode string
       $user['nt-hash'] = bin2hex(hash("md4", $ustr));
}

function local_user_get_groups($user, $all = false)
{
	global $config;

	$groups = array();

	if (!isset($config['system']['group'])) {
		return $groups;
	}

	foreach ($config['system']['group'] as $group) {
		if (isset($group['member'])) {
			if (in_array($user['uid'], $group['member']) || ($group['name'] == "all" && $all)) {
				$groups[] = $group['name'];
			}
		}
	}

	sort($groups);

	return $groups;

}

function local_user_set_groups($user, $new_groups = null)
{
	global $config, $groupindex;

	if (!isset($config['system']['group'])) {
		return;
	}

	$cur_groups = local_user_get_groups($user, true);
	$mod_groups = array();

	if (!is_array($new_groups)) {
		$new_groups = array();
	}

	if (!is_array($cur_groups)) {
		$cur_groups = array();
	}

	/* determine which memberships to add */
	foreach ($new_groups as $groupname) {
		if (in_array($groupname,$cur_groups) || !isset($groupindex[$groupname])) {
			// continue if group is already in current list or the groupname is invalid
			continue;
		}
		$group = & $config['system']['group'][$groupindex[$groupname]];
		$group['member'][] = $user['uid'];
		$mod_groups[] = $group;
	}

	/* determine which memberships to remove */
	foreach ($cur_groups as $groupname) {
		if (in_array($groupname,$new_groups)) {
			continue;
		}
		if (!isset($config['system']['group'][$groupindex[$groupname]])) {
			continue;
		}
		$group = & $config['system']['group'][$groupindex[$groupname]];
		if (is_array($group['member'])) {
			$index = array_search($user['uid'], $group['member']);
			array_splice($group['member'], $index, 1);
			$mod_groups[] = $group;
		}
	}

	/* sync all modified groups */
	foreach ($mod_groups as $group) {
		local_group_set($group);
	}
}

function local_group_del_user($user)
{
	global $config;

	if (!isset($config['system']['group'])) {
                return;
	}

        foreach ($config['system']['group'] as $group) {
		if (isset($group['member'])) {
			foreach ($group['member'] as $idx => $uid) {
				if ($user['uid'] == $uid) {
					unset($config['system']['group']['member'][$idx]);
				}
			}
		}
	}
}

function local_group_set($group, $reset = false)
{
	$group_name = $group['name'];
	$group_gid = $group['gid'];
	$group_members = '';

	if (!$reset && !empty($group['member']) && count($group['member']) > 0) {
		$group_members = implode(',', $group['member']);
	}

	$ret = mwexecf('/usr/sbin/pw groupshow %s', $group_name, true);
	if ($ret) {
		$group_op = 'groupadd';
	} else {
		$group_op = 'groupmod';
	}

	mwexecf('/usr/sbin/pw %s %s -g %s -M %s', array($group_op, $group_name, $group_gid, $group_members));
}

function local_group_del($group)
{
	/* delete from group db */
	mwexecf('/usr/sbin/pw groupdel %s', $group['name']);
}

function ldap_test_connection($authcfg)
{
	global $config;

	if ($authcfg) {
                if (strstr($authcfg['ldap_urltype'], "Standard"))
                        $ldapproto = "ldap";
                else
                        $ldapproto = "ldaps";
                $ldapserver         = "{$ldapproto}://" . ldap_format_host($authcfg['host']);
                $ldapport           = $authcfg['ldap_port'];
		if (!empty($ldapport))
			$ldapserver .= ":{$ldapport}";
                $ldapbasedn         = $authcfg['ldap_basedn'];
                $ldapbindun         = $authcfg['ldap_binddn'];
                $ldapbindpw         = $authcfg['ldap_bindpw'];
        } else
		return false;

        /* first check if there is even an LDAP server populated */
        if(!$ldapserver)
                return false;

        /* Setup CA environment if needed. */
        ldap_setup_caenv($authcfg);

        /* connect and see if server is up */
        $error = false;
	if (!($ldap = ldap_connect($ldapserver)))
		$error = true;

        if ($error == true) {
                log_error(sprintf(gettext("ERROR!  Could not connect to server %s."), $ldapname));
                return false;
        }

	return true;
}

function ldap_setup_caenv($authcfg)
{

	unset($caref);

	if (empty($authcfg['ldap_caref']) || !strstr($authcfg['ldap_urltype'], "SSL")) {
		putenv('LDAPTLS_REQCERT=never');
		return;
	}

	$caref = lookup_ca($authcfg['ldap_caref']);
	if (!$caref) {
		log_error(sprintf(gettext("LDAP: Could not lookup CA by reference for host %s."), $authcfg['ldap_caref']));
		/* XXX: Prevent for credential leaking since we cannot setup the CA env. Better way? */
		putenv('LDAPTLS_REQCERT=hard');
		return;
	}

	@mkdir("/var/run/certs");
	@unlink("/var/run/certs/{$caref['refid']}.ca");
	file_put_contents("/var/run/certs/{$caref['refid']}.ca", base64_decode($caref['crt']));
	@chmod("/var/run/certs/{$caref['refid']}.ca", 0600);
	putenv('LDAPTLS_REQCERT=hard');
	/* XXX: Probably even the hashed link should be created for this? */
	putenv("LDAPTLS_CACERTDIR=/var/run/certs");
	putenv("LDAPTLS_CACERT=/var/run/certs/{$caref['refid']}.ca");
}

function ldap_test_bind($authcfg)
{
	global $config;

	if ($authcfg) {
                if (strstr($authcfg['ldap_urltype'], "Standard"))
                        $ldapproto = "ldap";
                else
                        $ldapproto = "ldaps";
                $ldapserver         = "{$ldapproto}://" . ldap_format_host($authcfg['host']);
                $ldapport           = $authcfg['ldap_port'];
		if (!empty($ldapport))
			$ldapserver .= ":{$ldapport}";
                $ldapbasedn         = $authcfg['ldap_basedn'];
                $ldapbindun         = $authcfg['ldap_binddn'];
                $ldapbindpw         = $authcfg['ldap_bindpw'];
                $ldapver            = $authcfg['ldap_protver'];
		if (empty($ldapbndun) || empty($ldapbindpw))
                        $ldapanon = true;
                else
                        $ldapanon = false;
	} else
		return false;

	/* first check if there is even an LDAP server populated */
        if(!$ldapserver)
                return false;

	/* Setup CA environment if needed. */
	ldap_setup_caenv($authcfg);

        /* connect and see if server is up */
        $error = false;
	if (!($ldap = ldap_connect($ldapserver)))
		$error = true;

        if ($error == true) {
                log_error(sprintf(gettext("ERROR!  Could not connect to server %s."), $ldapname));
                return false;
        }

	ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
	ldap_set_option($ldap, LDAP_OPT_DEREF, LDAP_DEREF_SEARCHING);
	ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, (int)$ldapver);

	$ldapbindun = isset($authcfg['ldap_utf8']) ? utf8_encode($ldapbindun) : $ldapbindun;
	$ldapbindpw = isset($authcfg['ldap_utf8']) ? utf8_encode($ldapbindpw) : $ldapbindpw;
	if ($ldapanon == true) {
		if (!($res = @ldap_bind($ldap))) {
			@ldap_close($ldap);
			return false;
		}
	} else if (!($res = @ldap_bind($ldap, $ldapbindun, $ldapbindpw))) {
		@ldap_close($ldap);
		return false;
	}

	@ldap_unbind($ldap);

	return true;
}

function ldap_get_user_ous($show_complete_ou=true, $authcfg)
{
	global $config;

	if(!function_exists("ldap_connect"))
		return;

	$ous = array();

	if ($authcfg) {
                if (strstr($authcfg['ldap_urltype'], "Standard"))
                        $ldapproto = "ldap";
                else
                        $ldapproto = "ldaps";
                $ldapserver         = "{$ldapproto}://" . ldap_format_host($authcfg['host']);
                $ldapport           = $authcfg['ldap_port'];
		if (!empty($ldapport))
			$ldapserver .= ":{$ldapport}";
                $ldapbasedn         = $authcfg['ldap_basedn'];
                $ldapbindun         = $authcfg['ldap_binddn'];
                $ldapbindpw         = $authcfg['ldap_bindpw'];
                $ldapver            = $authcfg['ldap_protver'];
		if (empty($ldapbindun) || empty($ldapbindpw))
                        $ldapanon = true;
                else
                        $ldapanon = false;
                $ldapname           = $authcfg['name'];
                $ldapfallback       = false;
		$ldapscope          = $authcfg['ldap_scope'];
        } else
		return false;

        /* first check if there is even an LDAP server populated */
        if(!$ldapserver) {
                log_error(gettext("ERROR!  ldap_get_user_ous() backed selected with no LDAP authentication server defined."));
                return $ous;
        }

	/* Setup CA environment if needed. */
	ldap_setup_caenv($authcfg);

	/* connect and see if server is up */
        $error = false;
	if (!($ldap = ldap_connect($ldapserver)))
		$error = true;

        if ($error == true) {
        log_error(sprintf(gettext("ERROR!  Could not connect to server %s."), $ldapname));
                return $ous;
        }

	$ldapfilter = "(|(ou=*)(cn=Users))";

	ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
	ldap_set_option($ldap, LDAP_OPT_DEREF, LDAP_DEREF_SEARCHING);
	ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, (int)$ldapver);

	$ldapbindun = isset($authcfg['ldap_utf8']) ? utf8_encode($ldapbindun) : $ldapbindun;
	$ldapbindpw = isset($authcfg['ldap_utf8']) ? utf8_encode($ldapbindpw) : $ldapbindpw;
	if ($ldapanon == true) {
                if (!($res = @ldap_bind($ldap))) {
			log_error(sprintf(gettext("ERROR! ldap_get_user_ous() could not bind anonymously to server %s."), $ldapname));
			@ldap_close($ldap);
                        return $ous;
		}
	} else if (!($res = @ldap_bind($ldap, $ldapbindun, $ldapbindpw))) {
		log_error(sprintf(gettext("ERROR! ldap_get_user_ous() could not bind to server %s."), $ldapname));
		@ldap_close($ldap);
		return $ous;
	}

	if ($ldapscope == "one")
		$ldapfunc = "ldap_list";
	else
		$ldapfunc = "ldap_search";

	$search = @$ldapfunc($ldap, $ldapbasedn, $ldapfilter);
	$info = @ldap_get_entries($ldap, $search);

	if (is_array($info)) {
		foreach ($info as $inf) {
			if (!$show_complete_ou) {
				$inf_split = explode(",", $inf['dn']);
				$ou = $inf_split[0];
				$ou = str_replace("OU=","", $ou);
				$ou = str_replace("CN=","", $ou);
			} else
				if($inf['dn'])
					$ou = $inf['dn'];
			if($ou)
				$ous[] = $ou;
		}
	}

	@ldap_unbind($ldap);

	return $ous;
}

function ldap_get_groups($username, $authcfg)
{
	global $config;

	if(!function_exists("ldap_connect"))
		return;

	if(!$username)
		return false;

	if(!isset($authcfg['ldap_nostrip_at']) && stristr($username, "@")) {
		$username_split = explode("@", $username);
		$username = $username_split[0];
	}

	if(stristr($username, "\\")) {
		$username_split = explode("\\", $username);
		$username = $username_split[0];
	}

	//log_error("Getting LDAP groups for {$username}.");
        if ($authcfg) {
                if (strstr($authcfg['ldap_urltype'], "Standard"))
                        $ldapproto = "ldap";
                else
                        $ldapproto = "ldaps";
                $ldapserver         = "{$ldapproto}://" . ldap_format_host($authcfg['host']);
                $ldapport           = $authcfg['ldap_port'];
		if (!empty($ldapport))
			$ldapserver .= ":{$ldapport}";
                $ldapbasedn         = $authcfg['ldap_basedn'];
                $ldapbindun         = $authcfg['ldap_binddn'];
                $ldapbindpw         = $authcfg['ldap_bindpw'];
                $ldapauthcont       = $authcfg['ldap_authcn'];
                $ldapnameattribute  = strtolower($authcfg['ldap_attr_user']);
                $ldapgroupattribute  = strtolower($authcfg['ldap_attr_member']);
                $ldapfilter         = "({$ldapnameattribute}={$username})";
                $ldaptype           = "";
                $ldapver            = $authcfg['ldap_protver'];
		if (empty($ldapbindun) || empty($ldapbindpw))
                        $ldapanon = true;
                else
                        $ldapanon = false;
                $ldapname           = $authcfg['name'];
                $ldapfallback       = false;
		$ldapscope          = $authcfg['ldap_scope'];
	} else
		return false;

	$ldapdn             = $_SESSION['ldapdn'];

	/*Convert attribute to lowercase.  php ldap arrays put everything in lowercase */
	$ldapgroupattribute = strtolower($ldapgroupattribute);
	$memberof = array();

        /* Setup CA environment if needed. */
        ldap_setup_caenv($authcfg);

	/* connect and see if server is up */
	$error = false;
	if (!($ldap = ldap_connect($ldapserver)))
		$error = true;

	if ($error == true) {
		log_error(sprintf(gettext("ERROR! ldap_get_groups() Could not connect to server %s."), $ldapname));
                return memberof;
        }

	ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
	ldap_set_option($ldap, LDAP_OPT_DEREF, LDAP_DEREF_SEARCHING);
	ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, (int)$ldapver);

	/* bind as user that has rights to read group attributes */
	$ldapbindun = isset($authcfg['ldap_utf8']) ? utf8_encode($ldapbindun) : $ldapbindun;
	$ldapbindpw = isset($authcfg['ldap_utf8']) ? utf8_encode($ldapbindpw) : $ldapbindpw;
	if ($ldapanon == true) {
                if (!($res = @ldap_bind($ldap))) {
			log_error(sprintf(gettext("ERROR! ldap_get_groups() could not bind anonymously to server %s."), $ldapname));
			@ldap_close($ldap);
                        return false;
		}
	} else if (!($res = @ldap_bind($ldap, $ldapbindun, $ldapbindpw))) {
		log_error(sprintf(gettext("ERROR! ldap_get_groups() could not bind to server %s."), $ldapname));
		@ldap_close($ldap);
		return memberof;
	}

	/* get groups from DN found */
	/* use ldap_read instead of search so we don't have to do a bunch of extra work */
	/* since we know the DN is in $_SESSION['ldapdn'] */
	//$search    = ldap_read($ldap, $ldapdn, "(objectclass=*)", array($ldapgroupattribute));
	if ($ldapscope == "one")
                $ldapfunc = "ldap_list";
        else
                $ldapfunc = "ldap_search";

	$search    = @$ldapfunc($ldap, $ldapdn, $ldapfilter, array($ldapgroupattribute));
	$info      = @ldap_get_entries($ldap, $search);

	$countem = $info["count"];

	if(is_array($info[0][$ldapgroupattribute])) {
		/* Iterate through the groups and throw them into an array */
		foreach ($info[0][$ldapgroupattribute] as $member) {
			if (stristr($member, "CN=") !== false) {
				$membersplit = explode(",", $member);
				$memberof[] = preg_replace("/CN=/i", "", $membersplit[0]);
			}
		}
	}

	/* Time to close LDAP connection */
	@ldap_unbind($ldap);

	$groups = print_r($memberof,true);

	return $memberof;
}

function ldap_format_host($host)
{
	return is_ipaddrv6($host) ? "[$host]" : $host ;
}

function ldap_backed($username, $passwd, $authcfg)
{
	global $config;

	if(!$username)
		return;

	if(!function_exists("ldap_connect"))
		return;

	if(!isset($authcfg['ldap_nostrip_at']) && stristr($username, "@")) {
		$username_split = explode("@", $username);
		$username = $username_split[0];
	}
	if(stristr($username, "\\")) {
		$username_split = explode("\\", $username);
		$username = $username_split[0];
	}

	if ($authcfg) {
		if (strstr($authcfg['ldap_urltype'], "Standard"))
			$ldapproto = "ldap";
		else
			$ldapproto = "ldaps";
		$ldapserver         = "{$ldapproto}://" . ldap_format_host($authcfg['host']);
		$ldapport	    = $authcfg['ldap_port'];
		if (!empty($ldapport))
			$ldapserver .= ":{$ldapport}";
                $ldapbasedn         = $authcfg['ldap_basedn'];
                $ldapbindun         = $authcfg['ldap_binddn'];
                $ldapbindpw         = $authcfg['ldap_bindpw'];
		if (empty($ldapbindun) || empty($ldapbindpw))
			$ldapanon = true;
		else
			$ldapanon = false;
                $ldapauthcont       = $authcfg['ldap_authcn'];
                $ldapnameattribute  = strtolower($authcfg['ldap_attr_user']);
                $ldapextendedqueryenabled  = $authcfg['ldap_extended_enabled'];
                $ldapextendedquery = $authcfg['ldap_extended_query'];
                $ldapfilter         = "";
                if(!$ldapextendedqueryenabled)
                {
                        $ldapfilter = "({$ldapnameattribute}={$username})";
                }
                else
                {
                        $ldapfilter =
"(&({$ldapnameattribute}={$username})({$ldapextendedquery}))";
                }
                $ldaptype           = "";
                $ldapver            = $authcfg['ldap_protver'];
		$ldapname	    = $authcfg['name'];
		$ldapscope	    = $authcfg['ldap_scope'];
	} else
		return false;

	/* first check if there is even an LDAP server populated */
	if(!$ldapserver) {
		if ($ldapfallback) {
			log_error(gettext("ERROR! ldap_backed() called with no LDAP authentication server defined.  Defaulting to local user database. Visit System -> User Manager."));
			return local_backed($username, $passwd);
		} else
			log_error(gettext("ERROR! ldap_backed() called with no LDAP authentication server defined."));

		return false;
	}

        /* Setup CA environment if needed. */
        ldap_setup_caenv($authcfg);

	ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
	ldap_set_option($ldap, LDAP_OPT_DEREF, LDAP_DEREF_SEARCHING);
	ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, (int)$ldapver);

	/* Make sure we can connect to LDAP */
	$error = false;
	if (!($ldap = ldap_connect($ldapserver)))
		$error = true;

	if ($error == true) {
		log_error(sprintf(gettext("ERROR!  Could not connect to server %s."), $ldapname));
		return false;
	}

	/* ok, its up.  now, lets bind as the bind user so we can search it */
	$error = false;
	$ldapbindun = isset($authcfg['ldap_utf8']) ? utf8_encode($ldapbindun) : $ldapbindun;
	$ldapbindpw = isset($authcfg['ldap_utf8']) ? utf8_encode($ldapbindpw) : $ldapbindpw;
	if ($ldapanon == true) {
                if (!($res = @ldap_bind($ldap)))
                        $error = true;
	} else if (!($res = @ldap_bind($ldap, $ldapbindun, $ldapbindpw)))
		$error = true;

	if ($error == true) {
		@ldap_close($ldap);
		log_error(sprintf(gettext("ERROR! Could not bind to server %s."), $ldapname));
		return false;
	}

	/* Get LDAP Authcontainers and split em up. */
	$ldac_splits = explode(";", $ldapauthcont);

	/* setup the usercount so we think we havn't found anyone yet */
	$usercount  = 0;

	/*****************************************************************/
	/*  We First find the user based on username and filter          */
	/*  Then, once we find the first occurance of that person        */
	/*  We set seesion variables to ponit to the OU and DN of the    */
	/*  Person.  To later be used by ldap_get_groups.                */
	/*  that way we don't have to search twice.                      */
	/*****************************************************************/

	/* Iterate through the user containers for search */
	foreach ($ldac_splits as $i => $ldac_split) {
		$ldac_split = isset($authcfg['ldap_utf8']) ? utf8_encode($ldac_split) : $ldac_split;
		$ldapfilter = isset($authcfg['ldap_utf8']) ? utf8_encode($ldapfilter) : $ldapfilter;
		$ldapsearchbasedn = isset($authcfg['ldap_utf8']) ? utf8_encode("{$ldac_split},{$ldapbasedn}") : "{$ldac_split},{$ldapbasedn}";
		/* Make sure we just use the first user we find */
		if ($ldapscope == "one")
			$ldapfunc = "ldap_list";
		else
			$ldapfunc = "ldap_search";
		/* Support legacy auth container specification. */
		if (stristr($ldac_split, "DC=") || empty($ldapbasedn))
			$search	 = @$ldapfunc($ldap,$ldac_split,$ldapfilter);
		else
			$search  = @$ldapfunc($ldap,$ldapsearchbasedn,$ldapfilter);
		if (!$search) {
			log_error(sprintf(gettext("Search resulted in error: %s"), ldap_error($ldap)));
			continue;
		}
		$info	 = ldap_get_entries($ldap,$search);
		$matches = $info['count'];
		if ($matches == 1){
			$userdn = $_SESSION['ldapdn'] = $info[0]['dn'];
			$_SESSION['ldapou'] = $ldac_split[$i];
			$_SESSION['ldapon'] = "true";
			$usercount = 1;
			break;
		}
	}

	if ($usercount != 1){
		@ldap_unbind($ldap);
		log_error(gettext("ERROR! Either LDAP search failed, or multiple users were found."));
		return false;
	}

	/* Now lets bind as the user we found */
	$passwd = isset($authcfg['ldap_utf8']) ? utf8_encode($passwd) : $passwd;
	if (!($res = @ldap_bind($ldap, $userdn, $passwd))) {
		log_error(sprintf(gettext('ERROR! Could not login to server %1$s as user %2$s: %3$s'), $ldapname, $username, ldap_error($ldap)));
		@ldap_unbind($ldap);
		return false;
	}

	/* At this point we are bound to LDAP so the user was auth'd okay. Close connection. */
	@ldap_unbind($ldap);

	return true;
}

function radius_backed($username, $passwd, $authcfg, &$attributes = array())
{
	global $config;
	$ret = false;

	$rauth = new Auth_RADIUS_PAP($username, $passwd);
	if ($authcfg) {
		$radiusservers = array();
		$radiusservers[0]['ipaddr'] = $authcfg['host'];
		$radiusservers[0]['port'] = $authcfg['radius_auth_port'];
		$radiusservers[0]['sharedsecret'] = $authcfg['radius_secret'];
		$radiusservers[0]['timeout'] = $authcfg['radius_timeout'];
	} else
		return false;

	/* Add a new servers to our instance */
	foreach ($radiusservers as $radsrv) {
		$timeout = (is_numeric($radsrv['timeout'])) ? $radsrv['timeout'] : 5;
		$rauth->addServer($radsrv['ipaddr'], $radsrv['port'], $radsrv['sharedsecret'], $timeout);
	}

	if (PEAR::isError($rauth->start())) {
		$retvalue['auth_val'] = 1;
		$retvalue['error'] = $rauth->getError();
	}

	// XXX - billm - somewhere in here we need to handle securid challenge/response

	/* Send request */
	$result = $rauth->send();
	if (PEAR::isError($result)) {
		$retvalue['auth_val'] = 1;
		$retvalue['error'] = $result->getMessage();
	} else if ($result === true) {
		if ($rauth->getAttributes())
			$attributes = $rauth->listAttributes();
		$retvalue['auth_val'] = 2;
		$ret = true;
	} else {
		$retvalue['auth_val'] = 3;
	}

	// close OO RADIUS_AUTHENTICATION
	$rauth->close();

	return $ret;
}

function is_account_expired($username) {
	$user = getUserEntry($username);
	if (isset($user['expires']) && !empty($user['expires'])) {
		if (strtotime("-1 day") > strtotime(date("m/d/Y",strtotime($user['expires']))))
			return true;
	}

	return false;
}

function is_account_disabled($username) {
	$user = getUserEntry($username);
	if (isset($user['disabled']))
		return true;

	return false;
}

function auth_get_authserver($name) {
        global $config;

        if (isset($config['system']['authserver']) && is_array($config['system']['authserver'])) {
                foreach ($config['system']['authserver'] as $authcfg) {
                        if ($authcfg['name'] == $name)
                                return $authcfg;
                }
        }
	if ($name == "Local Database")
		return array("name" => gettext("Local Database"), "type" => "Local Auth", "host" => $config['system']['hostname']);
}

function auth_get_authserver_list() {
        global $config;

	$list = array();

        if (isset($config['system']['authserver']) && is_array($config['system']['authserver'])) {
                foreach ($config['system']['authserver'] as $authcfg) {
			/* Add support for disabled entries? */
			$list[$authcfg['name']] = $authcfg;
                }
        }

	$list["Local Database"] = array( "name" => gettext("Local Database"), "type" => "Local Auth", "host" => $config['system']['hostname']);
	return $list;
}

function getUserGroups($username, $authcfg)
{
	global $config;

	$allowed_groups = array();
	$member_groups = array();

	switch ($authcfg['type']) {
        case 'ldap':
		$allowed_groups = @ldap_get_groups($username, $authcfg);
		break;
	case 'radius':
		break;
	default:
		$user = getUserEntry($username);
		$allowed_groups = @local_user_get_groups($user, true);
		break;
	}

        if (isset($config['system']['group'])) {
                foreach ($config['system']['group'] as $group) {
                        if (in_array($group['name'], $allowed_groups)) {
				$member_groups[] = $group['name'];
			}
		}
	}

	return $member_groups;
}

function authenticate_user($username, $password, $authcfg = NULL, &$attributes = array()) {

	if (!$authcfg) {
		return local_backed($username, $password);
	}

	$authenticated = false;
	switch($authcfg['type']) {
        case 'ldap':
                if (ldap_backed($username, $password, $authcfg))
                        $authenticated = true;
                break;
        case 'radius':
                if (radius_backed($username, $password, $authcfg, $attributes))
                        $authenticated = true;
                break;
        default:
                /* lookup user object by name */
                if (local_backed($username, $password))
                        $authenticated = true;
                break;
        }

	return $authenticated;
}
