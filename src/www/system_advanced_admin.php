<?php

/*
 * Copyright (C) 2017-2019 Franco Fichtner <franco@opnsense.org>
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2005-2010 Scott Ullrich <sullrich@gmail.com>
 * Copyright (C) 2008 Shrew Soft Inc. <mgrooms@shrew.net>
 * Copyright (C) 2007 Bill Marquette <bill.marquette@gmail.com>
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

require_once("guiconfig.inc");
require_once("filter.inc");
require_once("system.inc");

$a_group = &config_read_array('system', 'group');
$a_authmode = auth_get_authserver_list();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();
    $pconfig['webguiinterfaces'] = !empty($config['system']['webgui']['interfaces']) ? explode(',', $config['system']['webgui']['interfaces']) : array();
    $pconfig['authmode'] = !empty($config['system']['webgui']['authmode']) ? explode(',', $config['system']['webgui']['authmode']) : array();
    $pconfig['session_timeout'] = !empty($config['system']['webgui']['session_timeout']) ? $config['system']['webgui']['session_timeout'] : null;
    $pconfig['webguiproto'] = $config['system']['webgui']['protocol'];
    $pconfig['webguiport'] = $config['system']['webgui']['port'];
    $pconfig['ssl-certref'] = $config['system']['webgui']['ssl-certref'];
    $pconfig['compression'] = isset($config['system']['webgui']['compression']) ? $config['system']['webgui']['compression'] : null;
    $pconfig['ssl-ciphers'] = !empty($config['system']['webgui']['ssl-ciphers']) ? explode(':', $config['system']['webgui']['ssl-ciphers']) : array();
    $pconfig['ssl-hsts'] = isset($config['system']['webgui']['ssl-hsts']);
    $pconfig['disablehttpredirect'] = isset($config['system']['webgui']['disablehttpredirect']);
    $pconfig['httpaccesslog'] = isset($config['system']['webgui']['httpaccesslog']);
    $pconfig['disableconsolemenu'] = isset($config['system']['disableconsolemenu']);
    $pconfig['usevirtualterminal'] = isset($config['system']['usevirtualterminal']);
    $pconfig['disableintegratedauth'] = !empty($config['system']['disableintegratedauth']);
    $pconfig['sudo_allow_wheel'] = $config['system']['sudo_allow_wheel'];
    $pconfig['sudo_allow_group'] = isset($config['system']['sudo_allow_group']) ? $config['system']['sudo_allow_group'] : null;
    $pconfig['user_allow_gen_token'] = isset($config['system']['user_allow_gen_token']) ? explode(",", $config['system']['user_allow_gen_token']) : null;
    $pconfig['nodnsrebindcheck'] = isset($config['system']['webgui']['nodnsrebindcheck']);
    $pconfig['nohttpreferercheck'] = isset($config['system']['webgui']['nohttpreferercheck']);
    $pconfig['althostnames'] = $config['system']['webgui']['althostnames'];
    $pconfig['serialspeed'] = $config['system']['serialspeed'];
    $pconfig['serialusb'] = isset($config['system']['serialusb']);
    $pconfig['primaryconsole'] = $config['system']['primaryconsole'];
    $pconfig['secondaryconsole'] = $config['system']['secondaryconsole'];
    $pconfig['enablesshd'] = $config['system']['ssh']['enabled'];
    $pconfig['sshport'] = $config['system']['ssh']['port'];
    $pconfig['sshinterfaces'] = !empty($config['system']['ssh']['interfaces']) ? explode(',', $config['system']['ssh']['interfaces']) : array();
    $pconfig['ssh-kex'] = !empty($config['system']['ssh']['kex']) ? explode(',', $config['system']['ssh']['kex']) : array();
    $pconfig['ssh-ciphers'] = !empty($config['system']['ssh']['ciphers']) ? explode(',', $config['system']['ssh']['ciphers']) : array();
    $pconfig['ssh-macs'] = !empty($config['system']['ssh']['macs']) ? explode(',', $config['system']['ssh']['macs']) : array();
    $pconfig['ssh-keys'] = !empty($config['system']['ssh']['keys']) ? explode(',', $config['system']['ssh']['keys']) : array();

    /* XXX listtag "fun" */
    $pconfig['sshlogingroup'] = !empty($config['system']['ssh']['group'][0]) ? $config['system']['ssh']['group'][0] : null;
    $pconfig['sshpasswordauth'] = isset($config['system']['ssh']['passwordauth']);
    $pconfig['sshdpermitrootlogin'] = isset($config['system']['ssh']['permitrootlogin']);
    $pconfig['quietlogin'] = isset($config['system']['webgui']['quietlogin']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    $pconfig = $_POST;

    if (!empty($pconfig['webguiport']) && !is_port($pconfig['webguiport'])) {
        $input_errors[] = gettext('You must specify a valid web GUI port number.');
    }

    if (empty($pconfig['webguiproto']) || !in_array($pconfig['webguiproto'], array('http', 'https'))) {
        $input_errors[] = gettext('You must specify a valid web GUI protocol.');
    }

    if (!empty($pconfig['althostnames'])) {
        $althosts = explode(" ", $pconfig['althostnames']);
        foreach ($althosts as $ah) {
            if (!is_hostname($ah)) {
                $input_errors[] = sprintf(gettext("Alternate hostname %s is not a valid hostname."), htmlspecialchars($ah));
            }
        }
    }

    if (!empty($pconfig['sshport']) && !is_port($pconfig['sshport'])) {
        $input_errors[] = gettext('You must specify a valid SSH port number.');
    }

    if (!empty($pconfig['session_timeout']) && (!is_numeric($pconfig['session_timeout']) || $pconfig['session_timeout'] <= 0)) {
        $input_errors[] = gettext('Session timeout must be an integer value.');
    }

    if (!empty($pconfig['authmode'])) {
        foreach ($pconfig['authmode'] as $auth_mode) {
            if (!isset($a_authmode[$auth_mode])) {
                $input_errors[] = sprintf(gettext('Authentication server "%s" is invalid.'), $auth_mode);
            }
        }
    }

    if (count($input_errors) == 0) {
        $newinterfaces = !empty($pconfig['webguiinterfaces']) ? implode(',', $pconfig['webguiinterfaces']) : '';
        $newciphers = !empty($pconfig['ssl-ciphers']) ? implode(':', $pconfig['ssl-ciphers']) : '';

        $restart_webgui = $config['system']['webgui']['protocol'] != $pconfig['webguiproto'] ||
            $config['system']['webgui']['port'] != $pconfig['webguiport'] ||
            $config['system']['webgui']['ssl-certref'] != $pconfig['ssl-certref'] ||
            $config['system']['webgui']['compression'] != $pconfig['compression'] ||
            $config['system']['webgui']['ssl-ciphers'] != $newciphers ||
            $config['system']['webgui']['interfaces'] != $newinterfaces ||
            (empty($pconfig['httpaccesslog'])) != empty($config['system']['webgui']['httpaccesslog']) ||
            (empty($pconfig['ssl-hsts'])) != empty($config['system']['webgui']['ssl-hsts']) ||
            ($pconfig['disablehttpredirect'] == "yes") != !empty($config['system']['webgui']['disablehttpredirect']);

        $config['system']['webgui']['protocol'] = $pconfig['webguiproto'];
        $config['system']['webgui']['port'] = $pconfig['webguiport'];
        $config['system']['webgui']['ssl-certref'] = $pconfig['ssl-certref'];
        $config['system']['webgui']['ssl-ciphers'] = $newciphers;
        $config['system']['webgui']['interfaces'] = $newinterfaces;
        $config['system']['webgui']['compression'] = $pconfig['compression'];

        if (!empty($pconfig['ssl-hsts'])) {
            $config['system']['webgui']['ssl-hsts'] = true;
        } elseif (isset($config['system']['webgui']['ssl-hsts'])) {
            unset($config['system']['webgui']['ssl-hsts']);
        }

        if (!empty($pconfig['session_timeout'])) {
            $config['system']['webgui']['session_timeout'] = $pconfig['session_timeout'];
        } elseif (isset($config['system']['webgui']['session_timeout'])) {
            unset($config['system']['webgui']['session_timeout']);
        }

        if ($pconfig['disablehttpredirect'] == "yes") {
            $config['system']['webgui']['disablehttpredirect'] = true;
        } elseif (isset($config['system']['webgui']['disablehttpredirect'])) {
            unset($config['system']['webgui']['disablehttpredirect']);
        }

        if (!empty($pconfig['httpaccesslog'])) {
            $config['system']['webgui']['httpaccesslog'] = true;
        } elseif (isset($config['system']['webgui']['httpaccesslog'])) {
            unset($config['system']['webgui']['httpaccesslog']);
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

        if (!empty($pconfig['usevirtualterminal'])) {
            $config['system']['usevirtualterminal'] = true;
        } elseif (isset($config['system']['usevirtualterminal'])) {
            unset($config['system']['usevirtualterminal']);
        }

        if (!empty($pconfig['disableintegratedauth'])) {
            $config['system']['disableintegratedauth'] = true;
        } elseif (isset($config['system']['disableintegratedauth'])) {
            unset($config['system']['disableintegratedauth']);
        }

        if (!empty($pconfig['sudo_allow_wheel'])) {
            $config['system']['sudo_allow_wheel'] = $pconfig['sudo_allow_wheel'];
        } elseif (isset($config['system']['sudo_allow_wheel'])) {
            unset($config['system']['sudo_allow_wheel']);
        }

        if (is_numeric($pconfig['serialspeed'])) {
            $config['system']['serialspeed'] = $pconfig['serialspeed'];
        } elseif (isset($config['system']['serialspeed'])) {
            unset($config['system']['serialspeed']);
        }

        if (!empty($pconfig['serialusb'])) {
            $config['system']['serialusb'] = true;
        } elseif (isset($config['system']['serialusb'])) {
            unset($config['system']['serialusb']);
        }

        if (!empty($pconfig['primaryconsole'])) {
            $config['system']['primaryconsole'] = $pconfig['primaryconsole'];
        } elseif (isset($config['system']['primaryconsole'])) {
            unset($config['system']['primaryconsole']);
        }

        if (!empty($pconfig['secondaryconsole'])) {
            $config['system']['secondaryconsole'] = $pconfig['secondaryconsole'];
        } elseif (isset($config['system']['secondaryconsole'])) {
            unset($config['system']['secondaryconsole']);
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

        if (!empty($pconfig['althostnames'])) {
            $config['system']['webgui']['althostnames'] = $pconfig['althostnames'];
        } elseif (isset($config['system']['webgui']['althostnames'])) {
            unset($config['system']['webgui']['althostnames']);
        }

        if (!empty($pconfig['authmode'])) {
            $config['system']['webgui']['authmode'] = implode(',', $pconfig['authmode']);
        } elseif (isset($config['system']['webgui']['authmode'])) {
            unset($config['system']['webgui']['authmode']);
        }

        /* always store setting to prevent installer auto-start */
        $config['system']['ssh']['noauto'] = 1;

        $config['system']['ssh']['interfaces'] = !empty($pconfig['sshinterfaces']) ? implode(',', $pconfig['sshinterfaces']) : null;
        $config['system']['ssh']['kex'] = !empty($pconfig['ssh-kex']) ? implode(',', $pconfig['ssh-kex']) : null;
        $config['system']['ssh']['ciphers'] = !empty($pconfig['ssh-ciphers']) ? implode(',', $pconfig['ssh-ciphers']) : null;
        $config['system']['ssh']['macs'] = !empty($pconfig['ssh-macs']) ? implode(',', $pconfig['ssh-macs']) : null;
        $config['system']['ssh']['keys'] = !empty($pconfig['ssh-keys']) ? implode(',', $pconfig['ssh-keys']) : null;

        if (!empty($pconfig['enablesshd'])) {
            $config['system']['ssh']['enabled'] = 'enabled';
        } elseif (isset($config['system']['ssh']['enabled'])) {
            unset($config['system']['ssh']['enabled']);
        }

        if (!empty($pconfig['sshlogingroup'])) {
            $config['system']['ssh']['group'] = $pconfig['sshlogingroup'];
        } elseif (isset($config['system']['ssh']['group'])) {
            unset($config['system']['ssh']['group']);
        }

        if (!empty($pconfig['sudo_allow_group'])) {
            $config['system']['sudo_allow_group'] = $pconfig['sudo_allow_group'];
        } elseif (isset($config['system']['sudo_allow_group'])) {
            unset($config['system']['sudo_allow_group']);
        }

        if (!empty($pconfig['user_allow_gen_token'])) {
            $config['system']['user_allow_gen_token'] = implode(",", $pconfig['user_allow_gen_token']);
        } elseif (isset($config['system']['user_allow_gen_token'])) {
            unset($config['system']['user_allow_gen_token']);
        }

        if (!empty($pconfig['sshpasswordauth'])) {
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
            if (!empty($port)) {
                $url = "{$prot}://{$host}:{$port}/system_advanced_admin.php";
            } else {
                $url = "{$prot}://{$host}/system_advanced_admin.php";
            }
        }

        write_config();

        $savemsg = get_std_save_message();

        filter_configure();
        system_login_configure();
        system_hosts_generate();
        plugins_configure('dns');
        plugins_configure('dhcp');
        configd_run('openssh restart', true);

        if ($restart_webgui) {
            configd_run('webgui restart 3', true);
        }
    }
}

$a_cert = isset($config['cert']) ? $config['cert'] : array();
$interfaces = get_configured_interface_with_descr();

$certs_available = false;
if (count($a_cert)) {
    $certs_available = true;
}

if (empty($pconfig['webguiproto']) || !$certs_available) {
    $pconfig['webguiproto'] = "http";
}

legacy_html_escape_form_data($pconfig);
legacy_html_escape_form_data($a_group);

include("head.inc");

?>
<body>
<?php include("fbegin.inc"); ?>
<script>

$(document).ready(function() {
     $(".proto").change(function(){
         if ($("#https_proto").prop('checked')) {
             $("#webguiport").attr('placeholder', '443');
             $(".ssl_opts").show();
         } else {
             $("#webguiport").attr('placeholder', '80');
             $(".ssl_opts").hide();
         }
     });
     $(".proto").change();

     $('#webguiinterface').change(function () {
         if ($('#webguiinterface option:selected').text() == '') {
             $.webguiinterface_warned = 0;
         } else if ($.webguiinterface_warned != 1) {
             $.webguiinterface_warned = 1;
             BootstrapDialog.confirm({
                 title: '<?= html_safe(gettext('Warning!')) ?>',
                 message: '<?= html_safe(gettext('Changing the listen interfaces of the web GUI may ' .
                     'prevent you from accessing this page if you continue. It is recommended to keep ' .
                     'this set to the default unless you know what you are doing.')) ?>',
                 type: BootstrapDialog.TYPE_WARNING,
                 btnOKClass: 'btn-warning',
                 btnOKLabel: '<?= html_safe(gettext('I know what I am doing')) ?>',
                 btnCancelLabel: '<?= html_safe(gettext('Use the default')) ?>',
                 callback: function(result) {
                     if (!result) {
                         $('#webguiinterface option:selected').prop('selected', false);
                         $('#webguiinterface').selectpicker('refresh');
                         $.webguiinterface_warned = 0;
                     }
                 }
             });
         }
     });
     $.webguiinterface_warned = $('#webguiinterface option:selected').length ? 1 : 0;

 <?php
    if (isset($restart_webgui) && $restart_webgui): ?>
        BootstrapDialog.show({
            type:BootstrapDialog.TYPE_INFO,
            title: '<?= html_safe($savemsg) ?>',
            closable: false,
            onshow:function(dialogRef){
                dialogRef.setClosable(false);
                dialogRef.getModalBody().html(
                    '<?= html_safe(gettext('The web GUI is reloading at the moment, please wait...')) ?>' +
                    ' <i class="fa fa-cog fa-spin"></i><br /><br />' +
                    ' <?= html_safe(gettext('If the page does not reload go here:')) ?>' +
                    ' <a href="<?= html_safe($url) ?>" target="_blank"><?= html_safe($url) ?></a>'
                );
                setTimeout(reloadWaitNew, 20000);
            },
        });

        function reloadWaitNew () {
            $.ajax({
                url: '<?= html_safe($url); ?>',
                timeout: 1250
            }).fail(function () {
                setTimeout(reloadWaitOld, 1250);
            }).done(function () {
                window.location.assign('<?= html_safe($url); ?>');
            });
        }
        function reloadWaitOld () {
            $.ajax({
                url: '/system_advanced_admin.php',
                timeout: 1250
            }).fail(function () {
                setTimeout(reloadWaitNew, 1250);
            }).done(function () {
                window.location.assign('/system_advanced_admin.php');
            });
        }
 <?php
    unset($savemsg);
    endif;?>
  });
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
        <form method="post" name="iform" id="iform">
          <div class="content-box tab-content table-responsive __mb">
            <table class="table table-striped opnsense_standard_table_form">
              <tr>
                <td style="width:22%"><strong><?=gettext('Web GUI');?></strong></td>
                <td style="width:78%; text-align:right">
                  <small><?=gettext("full help"); ?> </small>
                  <i class="fa fa-toggle-off text-danger" style="cursor: pointer;" id="show_all_help_page"></i>
                </td>
              </tr>
              <tr>
                <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Protocol"); ?></td>
                <td>
                  <input name="webguiproto" class="proto" id="http_proto" type="radio" value="http" <?= $pconfig['webguiproto'] == "http" ? 'checked="checked"' :'' ?>/>
                  <?=gettext("HTTP"); ?>
                  &nbsp;&nbsp;&nbsp;
                  <input name="webguiproto" class="proto" id="https_proto" type="radio" value="https" <?= $pconfig['webguiproto'] == "https" ? 'checked="checked"' : '' ?> <?=$certs_available ? '' : 'disabled="disabled"' ?>/>
                  <?=gettext("HTTPS"); ?>

<?php
                  if (!$certs_available) :?>
                  <br />
                  <?= sprintf(gettext("No Certificates have been defined. You must %sCreate or Import%s a Certificate before SSL can be enabled."),'<a href="system_certmanager.php">','</a>') ?>
<?php
                  endif; ?>
                </td>
              </tr>
              <tr class="ssl_opts">
                <td><a id="help_for_sslcertref" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("SSL Certificate"); ?></td>
                <td>
                  <select name="ssl-certref" class="selectpicker" data-style="btn-default">
<?php foreach ($a_cert as $cert): ?>
<?php if (isset($cert['prv'])): ?>
                    <option value="<?=$cert['refid'];?>" <?=$pconfig['ssl-certref'] == $cert['refid'] ? "selected=\"selected\"" : "";?>>
                      <?=$cert['descr'];?>
                    </option>
<?php endif ?>
<?php endforeach ?>
                  </select>
                  <div class='hidden' data-for="help_for_sslcertref">
                    <?=sprintf(
                      gettext('The %sSSL certificate manager%s can be used to ' .
                      'create or import certificates if required.'),
                      '<a href="/system_certmanager.php">', '</a>'
                    );?>
                  </div>
                </td>
              </tr>
              <tr class="ssl_opts">
                <td><a id="help_for_sslciphers" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("SSL Ciphers"); ?></td>
                <td>
                    <select name="ssl-ciphers[]" class="selectpicker" multiple="multiple" data-live-search="true" title="<?=gettext("System defaults");?>">
<?php
                    $ciphers = json_decode(configd_run("system ssl ciphers"), true);
                    if ($ciphers == null) {
                        $ciphers = array();
                    }
                    ksort($ciphers);
                    foreach ($ciphers as $cipher => $cipher_data):?>
                      <option value="<?=$cipher;?>" <?= !empty($pconfig['ssl-ciphers']) && in_array($cipher, $pconfig['ssl-ciphers']) ? 'selected="selected"' : '' ?>>
                        <?=!empty($cipher_data['description']) ? $cipher_data['description'] : $cipher;?>
                      </option>
<?php
                    endforeach;?>
                    </select>
                    <div class="hidden" data-for="help_for_sslciphers">
                      <?=gettext("Limit SSL cipher selection in case the system defaults are undesired. Note that restrictive use may lead to an inaccessible web GUI.");?>
                    </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_sslhsts" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('HTTP Strict Transport Security') ?></td>
                <td>
                  <input name="ssl-hsts" type="checkbox" value="yes" <?= empty($pconfig['ssl-hsts']) ? '' : 'checked="checked"' ?>/>
                  <?= gettext('Enable HTTP Strict Transport Security') ?>
                  <div class="hidden" data-for="help_for_sslhsts">
                    <?=gettext("HTTP Strict Transport Security (HSTS) is a web security policy mechanism that helps to protect websites against protocol downgrade attacks and cookie hijacking.");?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_webguiport" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("TCP port"); ?></td>
                <td>
                  <input name="webguiport" id="webguiport" type="text" value="<?=$pconfig['webguiport'];?>" placeholder="<?= $pconfig['webguiproto'] == 'https' ? '443' : '80' ?>" />
                  <div class="hidden" data-for="help_for_webguiport">
                    <?=gettext("Enter a custom port number for the web GUI " .
                                          "above if you want to override the default (80 for HTTP, 443 " .
                                          "for HTTPS). Changes will take effect immediately after save."); ?>
                  </div>
                </td>
              </tr>
              <tr class="ssl_opts">
                <td><a id="help_for_disablehttpredirect" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('HTTP Redirect'); ?></td>
                <td>
                  <input name="disablehttpredirect" type="checkbox" value="yes" <?= empty($pconfig['disablehttpredirect']) ? '' : 'checked="checked"';?> />
                  <?= gettext('Disable web GUI redirect rule') ?>
                  <div class="hidden" data-for="help_for_disablehttpredirect">
                    <?= gettext("When this is unchecked, access to the web GUI " .
                                        "is always permitted even on port 80, regardless of the listening port configured. " .
                                        "Check this box to disable this automatically added redirect rule.");
                                        ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_quietlogin" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Login Messages") ?></td>
                <td>
                  <input name="quietlogin" type="checkbox" value="yes" <?= empty($pconfig['quietlogin']) ? '' : 'checked="checked"' ?>/>
                  <?= gettext('Disable logging of web GUI successful logins') ?>
                  <div class="hidden" data-for="help_for_quietlogin">
                    <?=gettext("When this is checked, successful logins to the web GUI will not be logged.");?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_session_timeout" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Session Timeout') ?></td>
                <td>
                  <input class="form-control" name="session_timeout" id="session_timeout" type="text" placeholder="240" value="<?=$pconfig['session_timeout'];?>" />
                  <div class="hidden" data-for="help_for_session_timeout">
                    <?= gettext('Time in minutes to expire idle management sessions. The default is 4 hours (240 minutes).') ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_nodnsrebindcheck" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("DNS Rebind Check"); ?></td>
                <td>
                  <input name="nodnsrebindcheck" type="checkbox" value="yes" <?= empty($pconfig['nodnsrebindcheck']) ? '' : 'checked="checked"';?>/>
                  <?=gettext("Disable DNS Rebinding Checks"); ?>
                  <div class="hidden" data-for="help_for_nodnsrebindcheck">
                    <?= sprintf(gettext("When this is unchecked, your system is protected against %sDNS Rebinding attacks%s. " .
                                        "This blocks private IP responses from your configured DNS servers. Check this box to disable this protection if it interferes with " .
                                        "web GUI access or name resolution in your environment."),'<a href="http://en.wikipedia.org/wiki/DNS_rebinding">','</a>') ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_althostnames" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Alternate Hostnames") ?></td>
                <td>
                  <input name="althostnames" type="text" value="<?= $pconfig['althostnames'] ?>"/>
                  <?=gettext("Alternate Hostnames for DNS Rebinding and HTTP_REFERER Checks"); ?>
                  <div class="hidden" data-for="help_for_althostnames">
                    <?= gettext("Here you can specify alternate hostnames by which the router may be queried, to " .
                                        "bypass the DNS Rebinding Attack checks. Separate hostnames with spaces.") ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_compression" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("HTTP Compression")?></td>
                <td>
                  <select name="compression" class="selectpicker">
                      <option value="" <?=empty($pconfig['compression'])? 'selected="selected"' : '';?>>
                        <?=gettext("Off");?>
                      </option>
                      <option value="1" <?=$pconfig['compression'] == "1" ? 'selected="selected"' : '';?>>
                        <?=gettext("Low");?>
                      </option>
                      <option value="5" <?=$pconfig['compression'] == "5" ? 'selected="selected"' : '';?>>
                        <?=gettext("Medium");?>
                      </option>
                      <option value="9" <?=$pconfig['compression'] == "9" ? 'selected="selected"' : '';?>>
                        <?=gettext("High");?>
                      </option>
                  </select>
                  <div class="hidden" data-for="help_for_compression">
                    <?=gettext("Enable compression of HTTP pages and dynamic content.");?><br/>
                    <?=gettext("Transfer less data to the client for an additional cost in processing power.");?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_httpaccesslog" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Access log"); ?></td>
                <td>
                  <input name="httpaccesslog" type="checkbox" value="yes" <?= empty($pconfig['httpaccesslog']) ? '' : 'checked="checked"' ?> />
                  <?=gettext("Enable access log"); ?>
                  <div class="hidden" data-for="help_for_httpaccesslog">
                    <?=gettext("Enable access logging on the webinterface for debugging and analysis purposes.") ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_webguiinterfaces" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Listen Interfaces') ?></td>
                <td>
                  <select id="webguiinterface" name="webguiinterfaces[]" multiple="multiple" class="selectpicker" title="<?= html_safe(gettext('All (recommended)')) ?>">
<?php foreach ($interfaces as $iface => $ifacename): ?>
                      <option value="<?= html_safe($iface) ?>" <?= !empty($pconfig['webguiinterfaces']) && in_array($iface, $pconfig['webguiinterfaces']) ? 'selected="selected"' : '' ?>><?= html_safe($ifacename) ?></option>
<?php endforeach ?>
                  </select>
                  <div class="hidden" data-for="help_for_webguiinterfaces">
                    <?= gettext('Only accept connections from the selected interfaces. Leave empty to listen globally. Use with care.') ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_nohttpreferercheck" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("HTTP_REFERER enforcement"); ?></td>
                <td>
                  <input name="nohttpreferercheck" type="checkbox" value="yes" <?= empty($pconfig['nohttpreferercheck']) ? '' : 'checked="checked"' ?> />
                  <?=gettext("Disable HTTP_REFERER enforcement check"); ?>
                  <div class="hidden" data-for="help_for_nohttpreferercheck">
                    <?=sprintf(gettext("When this is unchecked, access to the web GUI " .
                                        "is protected against HTTP_REFERER redirection attempts. " .
                                        "Check this box to disable this protection if you find that it interferes with " .
                                        "web GUI access in certain corner cases such as using external scripts to interact with this system. More information on HTTP_REFERER is available from %sWikipedia%s."),
                                        '<a target="_blank" href="http://en.wikipedia.org/wiki/HTTP_referrer">','</a>') ?>
                  </div>
                </td>
              </tr>
            </table>
          </div>
          <div class="content-box tab-content table-responsive __mb">
            <table class="table table-striped opnsense_standard_table_form">
              <tr>
                <td style="width:22%"><strong><?= gettext('Secure Shell') ?></strong></td>
                <td style="width:78%"></td>
              </tr>
              <tr>
                <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Secure Shell Server"); ?></td>
                <td>
                  <input name="enablesshd" type="checkbox" value="yes" <?= empty($pconfig['enablesshd']) ? '' : 'checked="checked"' ?> />
                  <?=gettext("Enable Secure Shell"); ?>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_sshlogingroup" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Login Group') ?></td>
                <td>
                  <select name="sshlogingroup" class="selectpicker">
                      <option value=""><!-- do not translate: -->wheel</option>
<?php foreach ($a_group as $group): ?>
                      <option value="<?= html_safe($group['name']) ?>" <?= $pconfig['sshlogingroup'] == $group['name'] ? 'selected="selected"' : '' ?>><!-- do not translate: -->wheel, <?= html_safe($group['name']) ?></option>
<?php endforeach ?>
                  </select>
                  <div class="hidden" data-for="help_for_sshlogingroup">
                    <?= gettext('Select the allowed groups for remote login. The "wheel" group is always set for recovery purposes and an additional local group can be selected at will. Do not yield remote access to non-adminstrators as every user can access system files using SSH or SFTP.') ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_sshdpermitrootlogin" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Root Login") ?></td>
                <td>
                  <input name="sshdpermitrootlogin" type="checkbox" value="yes" <?= empty($pconfig['sshdpermitrootlogin']) ? '' : 'checked="checked"' ?> />
                  <?=gettext("Permit root user login"); ?>
                  <div class="hidden" data-for="help_for_sshdpermitrootlogin">
                    <?= gettext(
                      'Root login is generally discouraged. It is advised ' .
                      'to log in via another user and switch to root afterwards.'
                    ) ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_sshpasswordauth" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext("Authentication Method") ?></td>
                <td>
                  <input name="sshpasswordauth" type="checkbox" value="yes" <?= empty($pconfig['sshpasswordauth']) ? '' : 'checked="checked"' ?> />
                  <?=gettext("Permit password login"); ?>
                  <div class="hidden" data-for="help_for_sshpasswordauth">
                    <?=sprintf(gettext("When disabled, authorized keys need to be configured for each %sUser%s that has been granted secure shell access."),
                              '<a href="system_usermanager.php">', '</a>') ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_sshport" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("SSH port"); ?></td>
                <td>
                  <input name="sshport" type="text" value="<?=$pconfig['sshport'];?>" placeholder="22" />
                  <div class="hidden" data-for="help_for_sshport">
                    <?=gettext("Leave this blank for the default of 22."); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_sshinterfaces" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Listen Interfaces') ?></td>
                <td>
                  <select name="sshinterfaces[]" multiple="multiple" class="selectpicker" title="<?= html_safe(gettext('All (recommended)')) ?>">
<?php foreach ($interfaces as $iface => $ifacename): ?>
                      <option value="<?= html_safe($iface) ?>" <?= !empty($pconfig['sshinterfaces']) && in_array($iface, $pconfig['sshinterfaces']) ? 'selected="selected"' : '' ?>><?= html_safe($ifacename) ?></option>
<?php endforeach ?>
                  </select>
                  <div class="hidden" data-for="help_for_sshinterfaces">
                    <?= gettext('Only accept connections from the selected interfaces. Leave empty to listen globally. Use with care.') ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_sshkex" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Key exchange algorithms"); ?></td>
                <td>
                    <select name="ssh-kex[]" class="selectpicker" multiple="multiple" data-live-search="true" title="<?=gettext("System defaults");?>">
<?php
                    $options = json_decode(configd_run("openssh query kex"), true);
                    foreach ($options = empty($options) ? array() : $options as $option):?>
                      <option value="<?=$option;?>" <?= !empty($pconfig['ssh-kex']) && in_array($option, $pconfig['ssh-kex']) ? 'selected="selected"' : '' ?>>
                        <?=$option;?>
                      </option>
<?php
                    endforeach;?>
                    </select>
                    <div class="hidden" data-for="help_for_sshkex">
                      <?=gettext("The key exchange methods that are used to generate per-connection keys");?>
                    </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_sshciphers" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Ciphers"); ?></td>
                <td>
                    <select name="ssh-ciphers[]" class="selectpicker" multiple="multiple" data-live-search="true" title="<?=gettext("System defaults");?>">
<?php
                    $options = json_decode(configd_run("openssh query cipher"), true);
                    foreach ($options = empty($options) ? array() : $options as $option):?>
                      <option value="<?=$option;?>" <?= !empty($pconfig['ssh-ciphers']) && in_array($option, $pconfig['ssh-ciphers']) ? 'selected="selected"' : '' ?>>
                        <?=$option;?>
                      </option>
<?php
                    endforeach;?>
                    </select>
                    <div class="hidden" data-for="help_for_sshciphers">
                      <?=gettext("The ciphers to encrypt the connection");?>
                    </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_sshmacs" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("MACs"); ?></td>
                <td>
                    <select name="ssh-macs[]" class="selectpicker" multiple="multiple" data-live-search="true" title="<?=gettext("System defaults");?>">
<?php
                    $options = json_decode(configd_run("openssh query mac"), true);
                    foreach ($options = empty($options) ? array() : $options as $option):?>
                      <option value="<?=$option;?>" <?= !empty($pconfig['ssh-macs']) && in_array($option, $pconfig['ssh-macs']) ? 'selected="selected"' : '' ?>>
                        <?=$option;?>
                      </option>
<?php
                    endforeach;?>
                    </select>
                    <div class="hidden" data-for="help_for_sshmacs">
                      <?=gettext("The message authentication codes used to detect traffic modification");?>
                    </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_sshkeys" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Host key algorithms"); ?></td>
                <td>
                    <select name="ssh-keys[]" class="selectpicker" multiple="multiple" data-live-search="true" title="<?=gettext("System defaults");?>">
<?php
                    $options = json_decode(configd_run("openssh query key"), true);
                    foreach ($options = empty($options) ? array() : $options as $option):?>
                      <option value="<?=$option;?>" <?= !empty($pconfig['ssh-keys']) && in_array($option, $pconfig['ssh-keys']) ? 'selected="selected"' : '' ?>>
                        <?=$option;?>
                      </option>
<?php
                    endforeach;?>
                    </select>
                    <div class="hidden" data-for="help_for_sshkeys">
                      <?=gettext("Specifies the host	key algorithms that the	server offers");?>
                    </div>
                </td>
              </tr>
            </table>
          </div>
          <div class="content-box tab-content table-responsive __mb">
            <table class="table table-striped opnsense_standard_table_form">
              <tr>
                <td style="width:22%"><strong><?= gettext('Console') ?></strong></td>
                <td style="width:78%"></td>
              </tr>
              <tr>
                <td><i class="fa fa-info-circle text-muted"></i> <?= gettext('Console driver') ?></td>
                <td>
                  <input name="usevirtualterminal" type="checkbox" value="yes" <?= empty($pconfig['usevirtualterminal']) ? '' : 'checked="checked"' ?>  />
                  <?= gettext('Use the virtual terminal driver (vt)') ?>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_primaryconsole" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Primary Console")?></td>
                <td>
                  <select name="primaryconsole" id="primaryconsole" class="selectpicker">
<?php foreach (system_console_types() as $console_key => $console_type): ?>
                    <option value="<?= html_safe($console_key) ?>" <?= $pconfig['primaryconsole'] == $console_key ? 'selected="selected"' : '' ?>><?= $console_type['name'] ?></option>
<?php endforeach ?>
                  </select>
                  <div class="hidden" data-for="help_for_primaryconsole">
                    <?=gettext("Select the primary console. This preferred console will show boot script output.") ?>
                    <?=gettext("All consoles display OS boot messages, console messages, and the console menu."); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_secondaryconsole" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Secondary Console")?></td>
                <td>
                  <select name="secondaryconsole" id="secondaryconsole" class="selectpicker">
                    <option value="" <?= empty($pconfig['secondaryconsole']) ? 'selected="selected"' : '' ?>><?= gettext('None') ?></option>
<?php foreach (system_console_types() as $console_key => $console_type): ?>
                    <option value="<?= html_safe($console_key) ?>" <?= $pconfig['secondaryconsole'] == $console_key ? 'selected="selected"' : '' ?>><?= $console_type['name'] ?></option>
<?php endforeach ?>
                  </select>
                  <div class="hidden" data-for="help_for_secondaryconsole">
                    <?=gettext("Select the secondary console if multiple consoles are present."); ?>
                    <?=gettext("All consoles display OS boot messages, console messages, and the console menu."); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_serialspeed" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Serial Speed")?></td>
                <td>
                  <select name="serialspeed" id="serialspeed" class="selectpicker">
                    <option value="115200" <?=$pconfig['serialspeed'] == "115200" ? 'selected="selected"' : '' ?>>115200</option>
                    <option value="57600" <?=$pconfig['serialspeed'] == "57600" ? 'selected="selected"' : '' ?>>57600</option>
                    <option value="38400" <?=$pconfig['serialspeed'] == "38400" ? 'selected="selected"' : '' ?>>38400</option>
                    <option value="19200" <?=$pconfig['serialspeed'] == "19200" ? 'selected="selected"' : '' ?>>19200</option>
                    <option value="14400" <?=$pconfig['serialspeed'] == "14400" ? 'selected="selected"' : '' ?>>14400</option>
                    <option value="9600" <?=$pconfig['serialspeed'] == "9600" ? 'selected="selected"' : '' ?>>9600</option>
                  </select>
                  <div class="hidden" data-for="help_for_serialspeed">
                    <?=gettext("Allows selection of different speeds for the serial console port."); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><i class="fa fa-info-circle text-muted"></i> <?= gettext('USB-based serial') ?></td>
                <td>
                  <input name="serialusb" type="checkbox" value="yes" <?= empty($pconfig['serialusb']) ? '' : 'checked="checked"' ?>  />
                  <?= gettext('Use USB-based serial ports') ?>
                </td>
              </tr>
              <tr>
                <td><i class="fa fa-info-circle text-muted"></i> <?= gettext("Console menu") ?></td>
                <td>
                  <input name="disableconsolemenu" type="checkbox" value="yes" <?= empty($pconfig['disableconsolemenu']) ? '' : 'checked="checked"' ?>  />
                  <?=gettext("Password protect the console menu"); ?>
                </td>
              </tr>
            </table>
          </div>
          <div class="content-box tab-content table-responsive __mb">
            <table class="table table-striped opnsense_standard_table_form">
              <tr>
                <td style="width:22%"><strong><?= gettext('Authentication') ?></strong></td>
                <td style="width:78%"></td>
              </tr>
              <tr>
                <td><a id="help_for_authmode" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Server') ?></td>
                <td>
                  <select name="authmode[]" multiple="multiple" class="selectpicker" data-style="btn-default">
<?php foreach ($a_authmode as $auth_key => $auth_server): ?>
                    <option value="<?= html_safe($auth_key) ?>" <?= !empty($pconfig['authmode']) && in_array($auth_key, $pconfig['authmode']) ? 'selected="selected"' : '' ?>>
                      <?= html_safe($auth_server['name']) ?>
                    </option>
<?php endforeach ?>
                  </select>
                  <div class="hidden" data-for="help_for_authmode">
                    <?= gettext('Select one or more authentication servers to validate user credentials against. ' .
                        'Multiple servers can make sense with remote authentication methods to provide a fallback ' .
                        'during connectivity issues. When nothing is specified the default of "Local Database" is used.') ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td></td>
                <td>
                  <input name="disableintegratedauth" type="checkbox" value="yes" <?= empty($pconfig['disableintegratedauth']) ? '' : 'checked="checked"' ?>  />
                  <?=gettext("Disable integrated authentication"); ?>
                  <div class="hidden" data-for="help_for_authmode">
                    <?= gettext('When set, console login, SSH, and other system services can only use standard UNIX account authentication.') ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_sudo_allow_wheel" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Sudo') ?></td>
                <td>
                  <select name="sudo_allow_wheel" id="sudo_allow_wheel" class="selectpicker">
                    <option value="" <?= empty($pconfig['sudo_allow_wheel']) ? 'selected="selected"' : '' ?>><?= gettext('Disallow') ?></option>
                    <option value="1" <?= $pconfig['sudo_allow_wheel'] == 1 ? 'selected="selected"' : '' ?>><?= gettext('Ask password') ?></option>
                    <option value="2" <?= $pconfig['sudo_allow_wheel'] == 2 ? 'selected="selected"' : '' ?>><?= gettext('No password') ?></option>
                  </select>
                  <div class="hidden" data-for="help_for_sudo_allow_wheel">
                    <?= gettext('Permit sudo usage for administrators with shell access.') ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td></td>
                <td>
                  <select name="sudo_allow_group" class="selectpicker">
                      <option value=""><!-- do not translate: -->wheel</option>
<?php foreach ($a_group as $group): ?>
                      <option value="<?= html_safe($group['name']) ?>" <?= $pconfig['sudo_allow_group'] == $group['name'] ? 'selected="selected"' : '' ?>><!-- do not translate: -->wheel, <?= html_safe($group['name']) ?></option>
<?php endforeach ?>
                  </select>
                  <div class="hidden" data-for="help_for_sudo_allow_wheel">
                    <?= gettext('Select the allowed groups for sudo usage. The "wheel" group is always set for recovery purposes and an additional local group can be selected at will.') ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_user_allow_gen_token" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('User OTP seed') ?></td>
                <td>
                  <select name="user_allow_gen_token[]" multiple=multiple class="selectpicker">
<?php foreach ($a_group as $group): ?>
                      <option value="<?= html_safe($group['name']) ?>" <?= in_array($group['name'], $pconfig['user_allow_gen_token']) ? 'selected="selected"' : '' ?>>
                        <?= html_safe($group['name']) ?>
                      </option>
<?php endforeach ?>
                  </select>
                  <div class="hidden" data-for="help_for_user_allow_gen_token">
                    <?= gettext('Permit users to generate their own OTP seed in the password page.') ?>
                  </div>
                </td>
              </tr>
            </table>
          </div>
          <div class="content-box tab-content table-responsive">
            <table class="table table-striped opnsense_standard_table_form">
              <tr>
                <td style="width:22%"></td>
                <td style="width:78%"><input name="Submit" type="submit" class="btn btn-primary" value="<?= html_safe(gettext('Save')) ?>" /></td>
              </tr>
            </table>
          </div>
        </form>
      </section>
    </div>
  </div>
</section>
<?php include("foot.inc"); ?>
