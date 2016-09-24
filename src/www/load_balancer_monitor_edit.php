<?php

/*
      Copyright (C) 2014-2016 Deciso B.V.
      Copyright (C) 2008 Bill Marquette <bill.marquette@gmail.com>.
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
require_once("services.inc");
require_once("interfaces.inc");

$rfc2616 = array(
    100 => "100 Continue",
    101 => "101 Switching Protocols",
    200 => "200 OK",
    201 => "201 Created",
    202 => "202 Accepted",
    203 => "203 Non-Authoritative Information",
    204 => "204 No Content",
    205 => "205 Reset Content",
    206 => "206 Partial Content",
    300 => "300 Multiple Choices",
    301 => "301 Moved Permanently",
    302 => "302 Found",
    303 => "303 See Other",
    304 => "304 Not Modified",
    305 => "305 Use Proxy",
    306 => "306 (Unused)",
    307 => "307 Temporary Redirect",
    400 => "400 Bad Request",
    401 => "401 Unauthorized",
    402 => "402 Payment Required",
    403 => "403 Forbidden",
    404 => "404 Not Found",
    405 => "405 Method Not Allowed",
    406 => "406 Not Acceptable",
    407 => "407 Proxy Authentication Required",
    408 => "408 Request Timeout",
    409 => "409 Conflict",
    410 => "410 Gone",
    411 => "411 Length Required",
    412 => "412 Precondition Failed",
    413 => "413 Request Entity Too Large",
    414 => "414 Request-URI Too Long",
    415 => "415 Unsupported Media Type",
    416 => "416 Requested Range Not Satisfiable",
    417 => "417 Expectation Failed",
    500 => "500 Internal Server Error",
    501 => "501 Not Implemented",
    502 => "502 Bad Gateway",
    503 => "503 Service Unavailable",
    504 => "504 Gateway Timeout",
    505 => "505 HTTP Version Not Supported"
);

if (empty($config['load_balancer']['monitor_type']) || !is_array($config['load_balancer']['monitor_type'])) {
    $config['load_balancer']['monitor_type'] = array();
}
$a_monitor = &$config['load_balancer']['monitor_type'];


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id']) && !empty($a_monitor[$_GET['id']])) {
        $id = $_GET['id'];
    }
    $pconfig = array();
    foreach (array('name', 'type', 'descr') as $fieldname) {
        if (isset($id) && isset($a_monitor[$id][$fieldname])) {
            $pconfig[$fieldname] = $a_monitor[$id][$fieldname];
        } else {
            $pconfig[$fieldname] = null;
        }
    }
    if (isset($id)) {
        $pconfig['options_send'] = isset($a_monitor[$id]['options']['send']) ? $a_monitor[$id]['options']['send'] : null;
        $pconfig['options_expect'] = isset($a_monitor[$id]['options']['expect']) ? $a_monitor[$id]['options']['expect'] : null;
        $pconfig['options_path'] = isset($a_monitor[$id]['options']['path']) ? $a_monitor[$id]['options']['path'] : null;
        $pconfig['options_host'] = isset($a_monitor[$id]['options']['host']) ? $a_monitor[$id]['options']['host'] : null;
        $pconfig['options_code'] = isset($a_monitor[$id]['options']['code']) ? $a_monitor[$id]['options']['code'] : null;
    } else {
        /* option defaults */
        $pconfig['options_send'] = null;
        $pconfig['options_expect'] = null;
        $pconfig['options_path'] = '/';
        $pconfig['options_code'] = 200;
        $pconfig['options_host'] = null;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['id']) && !empty($a_monitor[$_POST['id']])) {
        $id = $_POST['id'];
    }
    $pconfig = $_POST;
    $input_errors = array();

    /* input validation */
    $reqdfields = explode(" ", "name type descr");
    $reqdfieldsn = array(gettext("Name"),gettext("Type"),gettext("Description"));

    do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);
    /* Ensure that our monitor names are unique */
    for ($i=0; isset($config['load_balancer']['monitor_type'][$i]); $i++) {
        if ($pconfig['name'] == $config['load_balancer']['monitor_type'][$i]['name'] && $i != $id) {
            $input_errors[] = gettext("This monitor name has already been used. Monitor names must be unique.");
        }
    }

    if (strpos($pconfig['name'], " ") !== false) {
        $input_errors[] = gettext("You cannot use spaces in the 'name' field.");
    }
    switch($pconfig['type']) {
        case 'icmp':
        case 'tcp':
            break;
        case 'http':
        case 'https':
            if (!empty($pconfig['options_host']) && !is_hostname($pconfig['options_host'])) {
                $input_errors[] = gettext("The hostname can only contain the characters A-Z, 0-9 and '-'.");
            }
            if (!empty($pconfig['options_code']) && !isset($rfc2616[$pconfig['options_code']])) {
                  $input_errors[] = gettext("HTTP(S) codes must be from RFC 2616.");
            }
            if (empty($pconfig['options_path'])) {
                $input_errors[] = gettext("The path to monitor must be set.");
            }
            break;
        case 'send':
            break;
    }

    if (count($input_errors) == 0) {
        $monent = array();
        $monent['name'] = $pconfig['name'];
        $monent['type'] = $pconfig['type'];
        $monent['descr'] = $pconfig['descr'];
        $monent['options'] = array();
        if($pconfig['type'] == "http" || $pconfig['type'] == "https") {
            $monent['options']['path'] = $pconfig['options_path'];
            $monent['options']['host'] = $pconfig['options_host'];
            $monent['options']['code'] = $pconfig['options_code'];
        } elseif ($pconfig['type'] == "send") {
            $monent['options']['send'] = $pconfig['options_send'];
            $monent['options']['expect'] = $pconfig['options_expect'];
        }

        if (isset($id)) {
            /* modify all pools with this name */
            for ($i = 0; isset($config['load_balancer']['lbpool'][$i]); $i++) {
                if ($config['load_balancer']['lbpool'][$i]['monitor'] == $a_monitor[$id]['name']) {
                    $config['load_balancer']['lbpool'][$i]['monitor'] = $monent['name'];
                }
            }
            $a_monitor[$id] = $monent;
        } else {
            $a_monitor[] = $monent;
        }

        mark_subsystem_dirty('loadbalancer');
        write_config();
        header(url_safe('Location: /load_balancer_monitor.php'));
        exit;
    }
}

$service_hook = 'relayd';

include("head.inc");
legacy_html_escape_form_data($pconfig);
$types = array("icmp" => gettext("ICMP"), "tcp" => gettext("TCP"), "http" => gettext("HTTP"), "https" => gettext("HTTPS"), "send" => gettext("Send/Expect"));
?>

<body>
<?php include("fbegin.inc"); ?>
  <script type="text/javascript">
    $( document ).ready(function() {
        $("#monitor_type").change(function(){
            switch ($(this).val()) {
                case 'http':
                case 'https':
                  $("#type_details_send").hide();
                  $("#type_details_http").show();
                  $("#type_details").show();
                  break;
                  case 'send':
                  $("#type_details_send").show();
                  $("#type_details_http").hide();
                  $("#type_details").show();
                  break;
                default:
                  $("#type_details").hide();
                  break;
            }
        });
        $("#monitor_type").change();
    });
  </script>

  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
          <section class="col-xs-12">
            <div class="content-box">
              <form name="iform" method="post" id="iform">
                <div class="table-responsive">
                  <table class="table table-striped opnsense_standard_table_form">
                    <tr>
                      <td width="22%"><strong><?=gettext("Edit Monitor entry"); ?></strong></td>
                      <td width="78%" align="right">
                        <small><?=gettext("full help"); ?> </small>
                        <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page" type="button"></i>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Name"); ?></td>
                      <td>
                        <input name="name" type="text" value="<?=$pconfig['name'];?>" />
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Description"); ?></td>
                      <td>
                        <input name="descr" type="text" value="<?=$pconfig['descr'];?>"/>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Type"); ?></td>
                      <td>
                        <select id="monitor_type" name="type" class="selectpicker">
                          <option value="icmp" <?=$pconfig['type'] == "icmp" ? " selected=\"selected\"" : "";?> >
                            <?=gettext("ICMP");?>
                          </option>
                          <option value="tcp" <?=$pconfig['type'] == "tcp" ? " selected=\"selected\"" : "";?> >
                            <?=gettext("TCP");?>
                          </option>
                          <option value="http" <?=$pconfig['type'] == "http" ? " selected=\"selected\"" : "";?> >
                            <?=gettext("HTTP");?>
                          </option>
                          <option value="https" <?=$pconfig['type'] == "https" ? " selected=\"selected\"" : "";?> >
                            <?=gettext("HTTPS");?>
                          </option>
                          <option value="send" <?=$pconfig['type'] == "send" ? " selected=\"selected\"" : "";?> >
                            <?=gettext("Send/Expect");?>
                          </option>
                        </select>
                    </td>
                  </tr>
                  <tr id="type_details" style="display:none;">
                      <td></td>
                      <td>
                        <div id="type_details_send">
                          <table class="table table-condensed">
                            <tr>
                              <td><?=gettext("Send string"); ?></td>
                              <td>
                                <input name="options_send" type="text" value="<?=$pconfig['options_send'];?>"/>
                              </td>
                            </tr>
                            <tr>
                              <td><?=gettext("Expect string"); ?></td>
                              <td>
                                <input name="options_expect" type="text" value="<?=$pconfig['options_expect'];?>"/>
                              </td>
                            </tr>
                          </table>
                        </div>
                        <div id="type_details_http">
                          <table class="table table-condensed">
                            <tr>
                              <td><?=gettext("Path"); ?></td>
                              <td>
                                <input name="options_path" type="text" value="<?=$pconfig['options_path'];?>"/>
                              </td>
                            </tr>
                            <tr>
                              <td><?=gettext("Host"); ?></td>
                              <td>
                                <input name="options_host" type="text" value="<?=$pconfig['options_host'];?>" />
                                <small><?=gettext("Hostname for Host: header if needed."); ?></small>
                              </td>
                            </tr>
                            <tr>
                              <td><?=gettext("HTTP Code"); ?></td>
                              <td>
                                <select name="options_code">
<?php
                                foreach($rfc2616 as $code => $message):?>
                                  <option value="<?=$code;?>" <?=$pconfig['options_code'] == $code ? " selected=\"selected\"" :"" ;?>>
                                    <?=$message;?>
                                  </option>
<?php
                                endforeach;?>
                                </select>
                              </td>
                            </tr>
                          </table>
                        </div>
                      </td>
                  </tr>
                  <tr>
                    <td valign="top">&nbsp;</td>
                    <td width="78%">
                      <input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save"); ?>" />
                      <input type="button" class="btn btn-default" value="<?=gettext("Cancel");?>" onclick="window.location.href='<?=(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/load_balancer_monitor.php');?>'" />
                      <?php if (isset($id)): ?>
                      <input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
                      <?php endif; ?>
                    </td>
                  </tr>
                </table>
              </div>
            </form>
          </div>
        </section>
      </div>
    </div>
  </section>
<?php include("foot.inc"); ?>
