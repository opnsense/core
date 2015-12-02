<?php

/*
  Copyright (C) 2014-2015 Deciso B.V.
  Copyright (C) 2005-2010 Scott Ullrich
  Copyright (C) 2008 Shrew Soft Inc
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

require_once("guiconfig.inc");
require_once("filter.inc");
require_once("system.inc");
require_once("unbound.inc");
require_once("pfsense-utils.inc");
require_once("services.inc");

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();
    $pconfig['webguiproto'] = $config['system']['webgui']['protocol'];
    $pconfig['webguiport'] = $config['system']['webgui']['port'];
    $pconfig['ssl-certref'] = $config['system']['webgui']['ssl-certref'];
    $pconfig['disablehttpredirect'] = isset($config['system']['webgui']['disablehttpredirect']);
    $pconfig['disableconsolemenu'] = isset($config['system']['disableconsolemenu']);
    $pconfig['noantilockout'] = isset($config['system']['webgui']['noantilockout']);
    $pconfig['nodnsrebindcheck'] = isset($config['system']['webgui']['nodnsrebindcheck']);
    $pconfig['nohttpreferercheck'] = isset($config['system']['webgui']['nohttpreferercheck']);
    $pconfig['enable_xdebug'] = isset($config['system']['webgui']['enable_xdebug']) ;
    $pconfig['loginautocomplete'] = isset($config['system']['webgui']['loginautocomplete']);
    $pconfig['althostnames'] = $config['system']['webgui']['althostnames'];
    $pconfig['enableserial'] = $config['system']['enableserial'];
    $pconfig['serialspeed'] = $config['system']['serialspeed'];
    $pconfig['primaryconsole'] = $config['system']['primaryconsole'];
    $pconfig['enablesshd'] = $config['system']['ssh']['enabled'];
    $pconfig['sshport'] = $config['system']['ssh']['port'];
    $pconfig['passwordauth'] = isset($config['system']['ssh']['passwordauth']);
    $pconfig['sshdpermitrootlogin'] = isset($config['system']['ssh']['permitrootlogin']);
    $pconfig['quietlogin'] = isset($config['system']['webgui']['quietlogin']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $input_errors = array();
  $pconfig = $_POST;

  /* input validation */
  if (!empty($pconfig['webguiport'])) {
      if (!is_port($pconfig['webguiport'])) {
          $input_errors[] = gettext("You must specify a valid webConfigurator port number");
      }
  }

  if (!empty($pconfig['althostnames'])) {
      $althosts = explode(" ", $pconfig['althostnames']);
      foreach ($althosts as $ah) {
          if (!is_hostname($ah)) {
              $input_errors[] = sprintf(gettext("Alternate hostname %s is not a valid hostname."), htmlspecialchars($ah));
          }
      }
  }

  if (!empty($pconfig['sshport'])) {
      if (!is_port($pconfig['sshport'])) {
          $input_errors[] = gettext("You must specify a valid port number");
      }
  }

  if (count($input_errors) ==0) {
      // flag web ui for restart
      if ($config['system']['webgui']['protocol'] != $pconfig['webguiproto'] ||
          $config['system']['webgui']['port'] != $pconfig['webguiport'] ||
          $config['system']['webgui']['ssl-certref'] != $pconfig['ssl-certref'] ||
          ($pconfig['disablehttpredirect'] == "yes") != !empty($config['system']['webgui']['disablehttpredirect'])
          ) {
          $restart_webgui = true;
      } else {
          $restart_webgui = false;
      }

      $config['system']['webgui']['protocol'] = $pconfig['webguiproto'];
      $config['system']['webgui']['port'] = $pconfig['webguiport'];
      $config['system']['webgui']['ssl-certref'] = $pconfig['ssl-certref'];

      if ($pconfig['disablehttpredirect'] == "yes") {
          $config['system']['webgui']['disablehttpredirect'] = true;
      } elseif (isset($config['system']['webgui']['disablehttpredirect'])) {
          unset($config['system']['webgui']['disablehttpredirect']);
      }
      if ($pconfig['quietlogin'] == "yes") {
          $config['system']['webgui']['quietlogin'] = true;
      } elseif (isset($config['system']['webgui']['quietlogin'])) {
          unset($config['system']['webgui']['quietlogin']);
      }

      if ($pconfig['disableconsolemenu'] == "yes") {
          $config['system']['disableconsolemenu'] = true;
      } elseif (isset($config['system']['disableconsolemenu'])) {
          unset($config['system']['disableconsolemenu']);
      }

      if ($pconfig['noantilockout'] == "yes") {
          $config['system']['webgui']['noantilockout'] = true;
      } elseif (isset($config['system']['webgui']['noantilockout'])) {
          unset($config['system']['webgui']['noantilockout']);
      }

      if ($pconfig['enableserial'] == "yes") {
          $config['system']['enableserial'] = true;
      } elseif (isset($config['system']['enableserial'])) {
          unset($config['system']['enableserial']);
      }

      if (is_numeric($pconfig['serialspeed'])) {
          $config['system']['serialspeed'] = $pconfig['serialspeed'];
      } elseif (isset($config['system']['serialspeed'])) {
          unset($config['system']['serialspeed']);
      }

      if (!empty($pconfig['primaryconsole'])) {
          $config['system']['primaryconsole'] = $pconfig['primaryconsole'];
      } elseif (isset($config['system']['primaryconsole'])) {
          unset($config['system']['primaryconsole']);
      }

      if ($pconfig['nodnsrebindcheck'] == "yes") {
          $config['system']['webgui']['nodnsrebindcheck'] = true;
      } elseif (isset($config['system']['webgui']['nodnsrebindcheck'])) {
          unset($config['system']['webgui']['nodnsrebindcheck']);
      }

      if ($pconfig['nohttpreferercheck'] == "yes") {
          $config['system']['webgui']['nohttpreferercheck'] = true;
      } elseif (isset($config['system']['webgui']['nohttpreferercheck'])) {
          unset($config['system']['webgui']['nohttpreferercheck']);
      }

      if ($pconfig['enable_xdebug'] == "yes") {
          $config['system']['webgui']['enable_xdebug'] = true;
      } elseif (isset($config['system']['webgui']['enable_xdebug'])) {
          unset($config['system']['webgui']['enable_xdebug']);
      }

      if ($pconfig['loginautocomplete'] == "yes") {
          $config['system']['webgui']['loginautocomplete'] = true;
      } elseif (isset($config['system']['webgui']['loginautocomplete'])) {
          unset($config['system']['webgui']['loginautocomplete']);
      }

      if (!empty($pconfig['althostnames'])) {
          $config['system']['webgui']['althostnames'] = $pconfig['althostnames'];
      } elseif (isset($config['system']['webgui']['althostnames'])) {
          unset($config['system']['webgui']['althostnames']);
      }

      if (empty($config['system']['ssh']['enabled']) != empty($pconfig['enablesshd']) ||
          empty($config['system']['ssh']['passwordauth']) != empty($pconfig['passwordauth']) ||
          $config['system']['ssh']['port'] != $pconfig['sshport'] ||
          empty($config['system']['ssh']['permitrootlogin']) != empty($pconfig['sshdpermitrootlogin'])
          ) {
            $restart_sshd = true;
      } else {
          $restart_sshd = false;
      }

      if (!empty($pconfig['enablesshd'])) {
          $config['system']['ssh']['enabled'] = 'enabled';
      } elseif (isset($config['system']['ssh']['enabled'])) {
          unset($config['system']['ssh']['enabled']);
      }

      if (!empty($pconfig['passwordauth'])) {
          $config['system']['ssh']['passwordauth'] = true;
      } elseif (isset($config['system']['ssh']['passwordauth'])) {
          unset($config['system']['ssh']['passwordauth']);
      }

      if (!empty($pconfig['sshport'])) {
          $config['system']['ssh']['port'] = $_POST['sshport'];
      } elseif (isset($config['system']['ssh']['port'])) {
          unset($config['system']['ssh']['port']);
      }

      if (!empty($pconfig['sshdpermitrootlogin'])) {
          $config['system']['ssh']['permitrootlogin'] = true;
      } elseif (isset($config['system']['ssh']['permitrootlogin'])) {
          unset($config['system']['ssh']['permitrootlogin']);
      }

      if ($restart_webgui) {
          global $_SERVER;
          $http_host_port = explode("]", $_SERVER['HTTP_HOST']);
          /* IPv6 address check */
          if (strstr($_SERVER['HTTP_HOST'], "]")) {
              if (count($http_host_port) > 1) {
                  array_pop($http_host_port);
                  $host = str_replace(array("[", "]"), "", implode(":", $http_host_port));
                  $host = "[{$host}]";
              } else {
                  $host = str_replace(array("[", "]"), "", implode(":", $http_host_port));
                  $host = "[{$host}]";
              }
          } else {
              list($host) = explode(":", $_SERVER['HTTP_HOST']);
          }
          $prot = $config['system']['webgui']['protocol'];
          $port = $config['system']['webgui']['port'];
          if ($port) {
              $url = "{$prot}://{$host}:{$port}/system_advanced_admin.php";
          } else {
              $url = "{$prot}://{$host}/system_advanced_admin.php";
          }
      }

      write_config();

      $retval = filter_configure();
      $savemsg = get_std_save_message();

      if ($restart_webgui) {
          $savemsg .= sprintf("<br />" . gettext("One moment...redirecting to %s in 20 seconds."), $url);
      }

      setup_serial_port();
      system_hosts_generate();
      // Restart DNS in case dns rebinding toggled
      if (isset($config['dnsmasq']['enable'])) {
          services_dnsmasq_configure();
      } elseif (isset($config['unbound']['enable']))
          services_unbound_configure();
  }
}


$a_cert = isset($config['cert']) ? $config['cert'] : array();

$certs_available = false;
if (count($a_cert)) {
    $certs_available = true;
}

if (empty($pconfig['webguiproto']) || !$certs_available) {
    $pconfig['webguiproto'] = "http";
}
legacy_html_escape_form_data($pconfig);

$pgtitle = array(gettext("System"),gettext("Settings"),gettext("Admin Access"));
include("head.inc");
?>

<body>

<?php include("fbegin.inc"); ?>
<script type="text/javascript">
//<![CDATA[
  function prot_change() {
      if (document.iform.https_proto.checked) {
          document.getElementById("ssl_opts").style.display="";
      } else {
          document.getElementById("ssl_opts").style.display="none";
      }
  }
//]]>
</script>

<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
<?php
    if (isset($input_errors) && count($input_errors) > 0) {
        print_input_errors($input_errors);
    }
    if (isset($savemsg)) {
        print_info_box($savemsg);
    }
?>
      <section class="col-xs-12">
        <div class="content-box tab-content">
          <form action="system_advanced_admin.php" method="post" name="iform" id="iform">
            <table class="table table-striped">
              <tr>
                <td width="22%"><strong><?=gettext("webConfigurator");?></strong></td>
                <td  width="78%" align="right">
                  <small><?=gettext("full help"); ?> </small>
                  <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page" type="button"></i></a>
                </td>
              </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Protocol"); ?></td>
                  <td>
                    <input name="webguiproto" id="http_proto" type="radio" value="http" <?=$pconfig['webguiproto'] == "http" ? "checked=\"checked\"" :"";?> onclick="prot_change()" />
                    <?=gettext("HTTP"); ?>
                    &nbsp;&nbsp;&nbsp;
                    <input name="webguiproto" id="https_proto" type="radio" value="https" <?=$pconfig['webguiproto'] == "https" ? "checked=\"checked\"" :"";?> <?=!$certs_available ? "disabled=\"disabled\"": "";?> onclick="prot_change()" />
                    <?=gettext("HTTPS"); ?>

<?php
                    if (!$certs_available) :?>
                    <br />
                    <?=gettext("No Certificates have been defined. You must"); ?>
                    <a href="system_certmanager.php"><?=gettext("Create or Import"); ?></a>
                    <?=gettext("a Certificate before SSL can be enabled."); ?>
<?php
                    endif; ?>
                  </td>
                </tr>
                <tr id="ssl_opts">
                  <td><a id="help_for_sslcertref" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("SSL Certificate"); ?></td>
                  <td>
                    <select name="ssl-certref" class="formselect selectpicker" data-style="btn-default">
<?php
                    foreach ($a_cert as $cert) :?>
                      <option value="<?=$cert['refid'];?>" <?=$pconfig['ssl-certref'] == $cert['refid'] ? "selected=\"selected\"" : "";?>>
                        <?=$cert['descr'];?>
                      </option>
<?php
                    endforeach;?>
                    </select>
                    <div class='hidden' for="help_for_sslcertref">
                      <?=sprintf(
                        gettext('The %sSSL certificate manager%s can be used to ' .
                        'create or import certificates if required.'),
                        '<a href="/system_certmanager.php">', '</a>'
                      );?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_webguiport" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("TCP port"); ?></td>
                  <td>
                    <input name="webguiport" type="text" value="<?=$pconfig['webguiport'];?>" />
                    <div class="hidden" for="help_for_webguiport">
                      <?=gettext("Enter a custom port number for the webConfigurator " .
                                            "above if you want to override the default (80 for HTTP, 443 " .
                                            "for HTTPS). Changes will take effect immediately after save."); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_disablehttpredirect" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("WebGUI redirect"); ?></td>
                  <td width="78%">
                    <input name="disablehttpredirect" type="checkbox" value="yes" <?=!empty($pconfig['disablehttpredirect']) ? "checked=\"checked\"" : "";?> />
                    <strong><?=gettext("Disable webConfigurator redirect rule"); ?></strong>
                    <div class="hidden" for="help_for_disablehttpredirect">
                      <?= gettext("When this is unchecked, access to the webConfigurator " .
                                          "is always permitted even on port 80, regardless of the listening port configured. " .
                                          "Check this box to disable this automatically added redirect rule. ");
                                          ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_loginautocomplete" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("WebGUI Login Autocomplete"); ?></td>
                  <td>
                    <input name="loginautocomplete" type="checkbox" value="yes" <?= !empty($pconfig['loginautocomplete']) ? "checked=\"checked\"" : "";?> />
                    <strong><?=gettext("Enable webConfigurator login autocomplete"); ?></strong>
                    <div class="hidden" for="help_for_loginautocomplete">
                      <?= gettext("When this is checked, login credentials for the webConfigurator " .
                                          "may be saved by the browser. While convenient, some security standards require this to be disabled. " .
                                          "Check this box to enable autocomplete on the login form so that browsers will prompt to save credentials (NOTE: Some browsers do not respect this option). ");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_quietlogin" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("WebGUI login messages"); ?></td>
                  <td>
                    <input name="quietlogin" type="checkbox" value="yes" <?=!empty($pconfig['quietlogin']) ? "checked=\"checked\"" : ""; ?>/>
                    <strong><?=gettext("Disable logging of webConfigurator successful logins"); ?></strong>
                    <div class="hidden" for="help_for_quietlogin">
                      <?=gettext("When this is checked, successful logins to the webConfigurator " .
                                          "will not be logged.");?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_noantilockout" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Anti-lockout"); ?></td>
                  <td>
                    <input name="noantilockout" type="checkbox" value="yes" <?=!empty($pconfig['noantilockout'])? "checked=\"checked\"" : "";?>/>
                    <strong><?=gettext("Disable webConfigurator anti-lockout rule"); ?></strong>
                    <div class="hidden" for="help_for_noantilockout">
                      <?php printf(gettext("When this is unchecked, access to the webConfigurator " .
                                          "on the %s interface is always permitted, regardless of the user-defined firewall " .
                                          "rule set. Check this box to disable this automatically added rule, so access " .
                                          "to the webConfigurator is controlled by the user-defined firewall rules " .
                                          "(ensure you have a firewall rule in place that allows you in, or you will " .
                                          "lock yourself out!)"), (!empty($config['interfaces']['lan']) ? "LAN" : "WAN")); ?>
                      <em> <?=gettext("Hint: the &quot;Set interface(s) IP address&quot; option in the console menu resets this setting as well."); ?> </em>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_nodnsrebindcheck" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("DNS Rebind Check"); ?></td>
                  <td>
                    <input name="nodnsrebindcheck" type="checkbox" value="yes" <?=!empty($pconfig['nodnsrebindcheck']) ? "checked=\"checked\"" : "";?>/>
                    <strong><?=gettext("Disable DNS Rebinding Checks"); ?></strong>
                    <div class="hidden" for="help_for_nodnsrebindcheck">
                      <?= gettext("When this is unchecked, your system " .
                                          "is protected against <a href=\"http://en.wikipedia.org/wiki/DNS_rebinding\">DNS Rebinding attacks</a>. " .
                                          "This blocks private IP responses from your configured DNS servers. Check this box to disable this protection if it interferes with " .
                                          "webConfigurator access or name resolution in your environment. "); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_althostnames" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Alternate Hostnames"); ?></td>
                  <td>
                    <input name="althostnames" type="text" value="<?=$pconfig['althostnames'];?>"/>
                    <strong><?=gettext("Alternate Hostnames for DNS Rebinding and HTTP_REFERER Checks"); ?></strong>
                    <div class="hidden" for="help_for_althostnames">
                      <?=gettext("Here you can specify alternate hostnames by which the router may be queried, to " .
                                          "bypass the DNS Rebinding Attack checks. Separate hostnames with spaces."); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_nohttpreferercheck" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Browser HTTP_REFERER enforcement"); ?></td>
                  <td>
                    <input name="nohttpreferercheck" type="checkbox" value="yes" <?= !empty($pconfig['nohttpreferercheck']) ? "checked=\"checked\"" : "";?> />
                    <strong><?=gettext("Disable HTTP_REFERER enforcement check"); ?></strong>
                    <div class="hidden" for="help_for_nohttpreferercheck">
                      <?=gettext("When this is unchecked, access to the webConfigurator " .
                                          "is protected against HTTP_REFERER redirection attempts. " .
                                          "Check this box to disable this protection if you find that it interferes with " .
                                          "webConfigurator access in certain corner cases such as using external scripts to interact with this system. More information on HTTP_REFERER is available from <a target='_blank' href='http://en.wikipedia.org/wiki/HTTP_referrer'>Wikipedia</a>."); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_xdebug" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Enable XDebug"); ?></td>
                  <td width="78%">
                    <input name="enable_xdebug" type="checkbox" value="yes"  <?=!empty($pconfig['enable_xdebug']) ? "checked=\"checked\"" : "";?> />
                    <strong><?=gettext("Enable debugger / profiler (developer mode, do not enable in production environment)"); ?></strong>
                    <div class="hidden" for="help_for_xdebug">
                      <?php echo gettext("When this is checked, php XDebug will be enabled and profiling output can be analysed using webgrind which will be available at [this-url]/webgrind/"); ?>
                      <br />
                      <?php echo gettext("For more information about XDebug profiling and how to enable it for your requests, please visit http://www.xdebug.org/docs/all_settings#profiler_enable_trigger"); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <th colspan="2"><?=gettext("Secure Shell"); ?></th>
                </tr>
                <tr>
                  <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Secure Shell Server"); ?></td>
                  <td>
                    <input name="enablesshd" type="checkbox" value="yes" <?=!empty($pconfig['enablesshd']) ? "checked=\"checked\"" : "";?> />
                    <strong><?=gettext("Enable Secure Shell"); ?></strong>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_sshdpermitrootlogin" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Root Login"); ?></td>
                  <td>
                    <input name="sshdpermitrootlogin" type="checkbox" value="yes" <?=!empty($pconfig['sshdpermitrootlogin']) ? "checked=\"checked\"" : "";?> />
                    <strong><?=gettext("Permit root user login"); ?></strong>
                    <div class="hidden" for="help_for_sshdpermitrootlogin">
                      <?= gettext(
                        'Root login is generally discouraged. It is advised ' .
                        'to log in via another user and switch to root afterwards.'
                      ) ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_passwordauth" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Authentication Method"); ?></td>
                  <td>
                    <input name="passwordauth" type="checkbox" value="yes" <?=!empty($pconfig['passwordauth']) ? "checked=\"checked\"" : "";?> />
                    <strong><?=gettext("Permit password login"); ?></strong>
                    <div class="hidden" for="help_for_passwordauth">
                      <?=gettext("When disabled, authorized keys need to be configured for each"); ?>
                      <a href="system_usermanager.php"><?=gettext("user"); ?></a>
                      <?=gettext("that has been granted secure shell access."); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_sshport" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("SSH port"); ?></td>
                  <td width="78%">
                    <input name="sshport" type="text"  value="<?=$pconfig['sshport'];?>"/>
                    <div class="hidden" for="help_for_sshport">
                      <?=gettext("Leave this blank for the default of 22."); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <th colspan="2"><?=gettext("Serial Communications"); ?></th>
                </tr>
                <tr>
                  <td><a id="help_for_enableserial" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Serial Terminal"); ?></td>
                  <td width="78%">
                    <input name="enableserial" type="checkbox" id="enableserial" value="yes" <?=!empty($pconfig['enableserial']) ? "checked=\"checked\"" : "";?> />
                    <strong><?=gettext("Enables the first serial port with 115200/8/N/1 by default, or another speed selectable below."); ?></strong>
                    <div class="hidden" for="help_for_enableserial">
                      <?=gettext("Note:  This will redirect the console output and messages to the serial port. You can still access the console menu from the internal video card/keyboard. A <b>null modem</b> serial cable or adapter is required to use the serial console."); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_serialspeed" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Serial Speed")?></td>
                  <td>
                    <select name="serialspeed" id="serialspeed" class="formselect selectpicker">
                      <option value="115200" <?=$pconfig['serialspeed'] == "115200" ? "selected=\"selected\"" : "";?>>115200</option>
                      <option value="57600" <?=$pconfig['serialspeed'] == "57600" ? "selected=\"selected\"" : "";?>>57600</option>
                      <option value="38400" <?=$pconfig['serialspeed'] == "38400" ? "selected=\"selected\"" : "";?>>38400</option>
                      <option value="19200" <?=$pconfig['serialspeed'] == "19200" ? "selected=\"selected\"" : "";?>>19200</option>
                      <option value="14400" <?=$pconfig['serialspeed'] == "14400" ? "selected=\"selected\"" : "";?>>14400</option>
                      <option value="9600" <?=$pconfig['serialspeed'] == "9600" ? "selected=\"selected\"" : "";?>>9600</option>
                    </select> <?=gettext("bps");?>
                    <div class="hidden" for="help_for_serialspeed">
                      <?=gettext("Allows selection of different speeds for the serial console port."); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_primaryconsole" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Primary Console")?></td>
                  <td width="78%">
                    <select name="primaryconsole" id="primaryconsole" class="formselect selectpicker">
                      <option value="serial"   <?=$pconfig['primaryconsole'] == "serial" ? "selected=\"selected\"" : "";?>>
                        <?=gettext("Serial Console");?>
                      </option>
                      <option value="video"  <?=$pconfig['primaryconsole'] == "video" ? "selected=\"selected\"" : "";?>>
                        <?=gettext("VGA Console");?>
                      </option>
                    </select>
                    <div class="hidden" for="help_for_primaryconsole">
                      <?=gettext("Select the preferred console if multiple consoles are present. The preferred console will show OPNsense boot script output. All consoles display OS boot messages, console messages, and the console menu."); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <th colspan="2"><?=gettext("Console Options"); ?></th>
                </tr>
                <tr>
                  <td><a id="help_for_disableconsolemenu" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Console menu"); ?></td>
                  <td width="78%">
                    <input name="disableconsolemenu" type="checkbox"  value="yes" <?= !empty($pconfig['disableconsolemenu']) ? "checked=\"checked\"" :"";?>  />
                    <strong><?=gettext("Password protect the console menu"); ?></strong>
                    <div class="hidden" for="help_for_disableconsolemenu">
                      <?=gettext("Changes to this option will take effect after a reboot."); ?>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td colspan="2" class="list" height="12">&nbsp;</td>
                </tr>
                <tr>
                  <td width="22%" valign="top">&nbsp;</td>
                  <td width="78%"><input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" /></td>
                </tr>
            </table>
          </form>
                </div>
            </section>
        </div>
  </div>
</section>

<script type="text/javascript">
//<![CDATA[
  prot_change();
//]]>
</script>

<?php
if ($restart_webgui) {
    echo "<meta http-equiv=\"refresh\" content=\"20;url={$url}\" />";
}
?>

<?php include("foot.inc"); ?>

<?php
if ($restart_sshd) {
    killbyname("sshd");
    log_error(gettext("secure shell configuration has changed. Stopping sshd."));

    if ($config['system']['ssh']['enabled']) {
        log_error(gettext("secure shell configuration has changed. Restarting sshd."));
        configd_run("sshd restart");
    }
}
if ($restart_webgui) {
    ob_flush();
    flush();
    log_error(gettext("webConfigurator configuration has changed. Restarting webConfigurator."));
    mwexec_bg('/usr/local/etc/rc.restart_webgui 2');
}
