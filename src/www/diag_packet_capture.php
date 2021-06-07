<?php

/*
 * Copyright (C) 2014-2016 Deciso B.V.
 * Copyright (C) 2007 Scott Dale
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
require_once("interfaces.inc");

/**
 * kill tcp dump process
 */
function stop_capture()
{
    $processes_running = trim(shell_exec("/bin/ps axw -O pid= | /usr/bin/grep tcpdump | /usr/bin/grep packetcapture_ | /usr/bin/egrep -v '(pflog|grep)'"));
    foreach (explode("\n", $processes_running) as $process) {
        exec("kill ". explode(' ',$process)[0]);
    }
}

/**
 *  start capture operation
 *  @param array $option, options to pass to tpcdump (interface, promiscuous, snaplen, fam, host, proto, port)
 */
function start_capture($options)
{
      $cmd_opts = array();
      $filter_opts = array();

      if (empty($options['promiscuous'])) {
          // disable promiscuous mode
          $cmd_opts[] = '-p';
      }

      if (!empty($options['snaplen']) && is_numeric($options['snaplen'])) {
          // setup Packet Length
          $cmd_opts[] = '-s '. $options['snaplen'];
      }

      if (!empty($options['count']) && is_numeric($options['count'])) {
          // setup count
          $cmd_opts[] = '-c '. $options['count'];
      }

      if (!empty($options['fam']) && in_array($options['fam'], array('ip', 'ip6'))) {
          // filter address family
          $filter_opts[] = $options['fam'];
      }

      if (!empty($options['proto'])) {
          // filter protocol
          $filter_opts[] = $options['proto'];
      }

      if (!empty($options['host'])) {
          // filter host argument
          $filter = '';
          $prev_token = '';
          foreach (explode(' ', $options['host']) as $token) {
              if (in_array(trim($token), array('and', 'or'))) {
                  $filter .= $token;
              } elseif (is_ipaddr($token)) {
                  $filter .= "host " . $prev_token . " " . $token;
              } elseif (is_subnet($token)) {
                  $filter .= "net " . $prev_token . " " . $token;
              }
              if (trim($token) == 'not') {
                  $prev_token = 'not';
              } else {
                  $prev_token = '';
              }
              $filter .= " ";
          }

          $filter_opts[] = "( ". $filter . " )";
      }

      if (!empty($options['port'])) {
          // filter port
          $filter_opts[] = "port " . str_replace("!", "not ", $options['port']);
      }

      foreach (glob("/tmp/packetcapture_*.cap") as $filename) {
          @unlink($filename);
      }
      foreach ($options['interface'] as $key) {
          $intf = get_real_interface($key);
          if (!empty($intf)) {
              $cmd = '/usr/sbin/tcpdump ';
              $cmd .= "-i " . escapeshellarg($intf) . " ";
              $cmd .= implode(' ', $cmd_opts);
              $cmd .= " -w /tmp/packetcapture_{$intf}.cap ";
              $cmd .= " ".escapeshellarg(implode(' and ', $filter_opts));
              //delete previous packet capture if it exists
              mwexec_bg($cmd);
          }
      }
}

/**
 * check if packetcapture is running
 * @return bool
 */
function capture_running()
{
    $processcheck = (trim(shell_exec("/bin/ps axw -O pid= | /usr/bin/grep tcpdump | /usr/bin/grep  packetcapture_ | /usr/bin/egrep -v '(pflog|grep)'")));
    if (!empty($processcheck)) {
        return true;
    } else {
        return false;
    }

}

// define selectable interfaces
$interfaces = get_configured_interface_with_descr();
if (isset($config['ipsec']['enable'])) {
    $interfaces['ipsec'] = 'IPsec';
}

foreach (array('server', 'client') as $mode) {
    if (isset($config['openvpn']["openvpn-{$mode}"])) {
        foreach ($config['openvpn']["openvpn-{$mode}"] as $id => $setting) {
            if (!isset($setting['disable'])) {
                $interfaces['ovpn' . substr($mode, 0, 1) . $setting['vpnid']] = gettext("OpenVPN") . " ".$mode.": ".htmlspecialchars($setting['description']);
            }
        }
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['download'])) {
        // download capture file
        foreach (glob("/tmp/packetcapture_*.cap") as $filename) {
            $bfilename = basename($filename);
            if ($_GET['download'] === $bfilename) {
                header("Content-Type: application/octet-stream");
                header("Content-Disposition: attachment; filename={$bfilename}");
                header("Content-Length: ".filesize($filename));
                header('Content-Transfer-Encoding: binary');
                $file = fopen($filename, 'rb');
                while(!feof($file)) {
                    print(fread($file, 32 * 1024));
                    ob_flush();
                }
                fclose($file);
                break;
            }
        }
        exit;
    } elseif (!empty($_GET['view'])) {
        $result = [];
        foreach (glob("/tmp/packetcapture_*.cap") as $filename) {
            $intf = explode(".", substr(basename($filename), 14))[0];
            $intf_key = convert_real_interface_to_friendly_interface_name($intf);
            $intf_name = !empty($interfaces[$intf_key]) ? $interfaces[$intf_key] : $intf_key;
            $result[$intf] = ['name' => $intf_name, 'content' => []];
            // download capture contents
            if (!empty($_GET['dnsquery'])) {
                //if dns lookup is checked
                $disabledns = "";
            } else {
                //if dns lookup is unchecked
                $disabledns = "-n";
            }
            $detail_args = "";
            switch (!empty($_GET['detail']) ? $_GET['detail'] : null) {
                case "full":
                    $detail_args = "-vv -e";
                    break;
                case "high":
                    $detail_args = "-vv";
                    break;
                case "medium":
                    $detail_args = "-v";
                    break;
                case "normal":
                default:
                    $detail_args = "-q";
                    break;
            }
            $dump_output = array();
            exec("/usr/sbin/tcpdump {$disabledns} {$detail_args} -r {$filename} |  /usr/bin/tail -n 5000", $dump_output);
            // reformat raw output to 1 packet per array item
            foreach ($dump_output as $line) {
                if ($line[0] == ' ' && count($result) > 0) {
                    $result[$intf]['content'][count($result)-1] .= "\n" . $line;
                } else {
                    $result[$intf]['content'][] = $line;
                }
            }
        }
        echo json_encode($result);
        exit;
    } else {
        // set form defaults
        $pconfig = array();
        $pconfig['interface'] = ["wan"];
        $pconfig['promiscuous'] = null;
        $pconfig['fam'] = null;
        $pconfig['proto'] = null;
        $pconfig['host'] = null;
        $pconfig['port'] = null;
        $pconfig['snaplen'] = null;
        $pconfig['count'] = 100;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    $pconfig = $_POST;

    if (!empty($_POST['start'])) {
        if (empty($pconfig['interface'])) {
            $input_errors[] = gettext("No interface selected");
        } else {
            foreach ($pconfig['interface'] as $key) {
                if (!array_key_exists($key, $interfaces)) {
                    $input_errors[] = sprintf(gettext("Invalid interface %s."), $key);
                }
            }
        }
        if ($pconfig['fam'] !== "" && $pconfig['fam'] !== "ip" && $pconfig['fam'] !== "ip6") {
            $input_errors[] = gettext("Invalid address family.");
        }
        $protos = array('icmp', 'icmp6', 'tcp', 'udp', 'arp', 'carp', 'esp',
                        '!icmp', '!icmp6', '!tcp', '!udp', '!arp', '!carp', '!esp');
        if ($pconfig['proto'] !== "" && !in_array(ltrim(trim($pconfig['proto']), '!'), $protos)) {
            $input_errors[] = gettext("Invalid protocol.");
        }

        if (!empty($pconfig['host'])) {
            foreach (explode(' ', $pconfig['host']) as $token) {
                if (!in_array(trim($token), array('and', 'or','not')) && !is_ipaddr($token) && !is_subnet($token) ) {
                    $input_errors[] = sprintf(gettext("A valid IP address or CIDR block must be specified. [%s]"), $token);
                }
            }
        }
        if (!empty($pconfig['port']) && !is_port(ltrim(trim($pconfig['port']), 'not'))) {
            $input_errors[] = gettext("Invalid value specified for port.");
        }
        if (!empty($pconfig['snaplen']) && (!is_numeric($pconfig['snaplen']) || $snaplen < 0)) {
            $input_errors[] = gettext("Invalid value specified for packet length.");
        }
        if (!empty($pconfig['count']) && (!is_numeric($pconfig['count']) || $count < 0)) {
            $input_errors[] = gettext("Invalid value specified for packet count.");
        }
        if (count($input_errors) == 0) {
            start_capture($pconfig);
        }
    } elseif (!empty($pconfig['stop'])) {
        stop_capture();
    } elseif (!empty($pconfig['remove'])) {
        foreach (glob("/tmp/packetcapture_*.cap") as $filename) {
            @unlink($filename);
        }
        header(url_safe('Location: /diag_packet_capture.php'));
        exit;
    }
}

legacy_html_escape_form_data($pconfig);
include("head.inc");
?>
<body>
  <script>
    $( document ).ready(function() {
        $("#view").click(function(){
          $.ajax("diag_packet_capture.php",{
              type: 'get',
              cache: false,
              dataType: "json",
              data: {view: 'view', 'dnsquery': $("#dnsquery:checked").val() ,'detail': $("#detail").val()},
              success: function(response) {
                var html = [];
                $.each(response, function(intf, data){
                  $.each(data['content'], function(idx, line){
                      html.push(
                        $("<tr>").append(
                          $("<td>").append(
                            $("<span>").text(data['name']),
                            $("<br>"),
                            $("<small>").text(intf),
                          )
                        ).append(
                           $("<td>").text(line)
                        )
                      );
                  });
                });
                $("#capture_output").empty().append(html);
                $("#capture").removeClass('hidden');
                // scroll to capture output
                $('html, body').animate({
                  scrollTop: $("#capture").offset().top
                }, 2000);
              }
          });
        });
    });
</script>

<?php
include("fbegin.inc");
?>

<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
      <section class="col-xs-12">
        <div class="content-box">
          <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
          <div class="table-responsive">
            <form method="post" name="iform" id="iform">
              <table class="table table-striped opnsense_standard_table_form">
                <thead>
                  <tr>
                    <td style="width:22%"><strong><?=gettext("Packet capture");?></strong></td>
                    <td style="width:78%; text-align:right">
                      <small><?=gettext("full help"); ?> </small>
                      <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                      &nbsp;
                    </td>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td><a id="help_for_if" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Interface");?></td>
                    <td>
                      <select name="interface[]" class="selectpicker" multiple="multiple">
<?php
                      foreach ($interfaces as $iface => $ifacename): ?>
                        <option value="<?=$iface;?>" <?=in_array($iface, $pconfig['interface']) ? "selected=\"selected\"" : ""; ?>>
                          <?=$ifacename;?>
                        </option>
<?php
                      endforeach; ?>
                      </select>
                      <div class="hidden" data-for="help_for_if">
                        <?=gettext("Select the interface on which to capture traffic.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_promiscuous" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Promiscuous");?></td>
                    <td>
                      <input name="promiscuous" type="checkbox" <?= !empty($pconfig['promiscuous']) ? " checked=\"checked\"" : ""; ?> />
                      <div class="hidden" data-for="help_for_promiscuous">
                        <?=gettext("If checked, the");?> <a target="_blank" href="https://www.freebsd.org/cgi/man.cgi?query=tcpdump&amp;apropos=0&amp;sektion=0&amp;manpath=FreeBSD+8.3-stable&amp;arch=default&amp;format=html"><?= gettext("packet capture")?></a> <?= gettext("will be performed using promiscuous mode.");?>
                        <br /><b><?=gettext("Note");?>: </b><?=gettext("Some network adapters do not support or work well in promiscuous mode.");?>
                      </div>
                  </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_fam" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Address Family");?></td>
                    <td>
                      <select name="fam" class="selectpicker">
                        <option value=""><?=gettext('Any') ?></option>
                        <option value="ip" <?=!empty($pconfig['fam'] == "ip") ? "selected=\"selected\"" : ""; ?>>
                          <?= gettext('IPv4 Only') ?>
                        </option>
                        <option value="ip6" <?=!empty($pconfig['fam'] == "ip6") ? "selected=\"selected\"" : ""; ?>>
                          <?= gettext('IPv6 Only') ?>
                        </option>
                      </select>
                      <div class="hidden" data-for="help_for_fam">
                        <?=gettext("Select the type of traffic to be captured, either Any, IPv4 only or IPv6 only.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_proto" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Protocol");?></td>
                    <td>
                      <select name="proto" class="selectpicker">
                        <option value=""><?=gettext('Any') ?></option>
                        <option value="icmp" <?=$pconfig['proto'] == "icmp" ? "selected=\"selected\"" : ""; ?>><?= gettext('ICMP') ?></option>
                        <option value="!icmp" <?=$pconfig['proto'] == "!icmp" ? "selected=\"selected\"" : ""; ?>><?= gettext('Exclude ICMP') ?></option>
                        <option value="icmp6" <?=$pconfig['proto'] == "icmp6" ? "selected=\"selected\"" : ""; ?>><?= gettext('ICMPv6') ?></option>
                        <option value="!icmp6" <?=$pconfig['proto'] == "!icmp6" ? "selected=\"selected\"" : ""; ?>><?= gettext('Exclude ICMPv6') ?></option>
                        <option value="tcp" <?=$pconfig['proto'] == "tcp" ? "selected=\"selected\"" : ""; ?>><?= gettext('TCP') ?></option>
                        <option value="!tcp" <?=$pconfig['proto'] == "!tcp" ? "selected=\"selected\"" : ""; ?>><?= gettext('Exclude TCP') ?></option>
                        <option value="udp" <?=$pconfig['proto'] == "udp" ? "selected=\"selected\"" : ""; ?>><?= gettext('UDP') ?></option>
                        <option value="!udp" <?=$pconfig['proto'] == "!udp" ? "selected=\"selected\"" : ""; ?>><?= gettext('Exclude UDP') ?></option>
                        <option value="arp" <?=$pconfig['proto'] == "arp" ? "selected=\"selected\"" : ""; ?>><?= gettext('ARP') ?></option>
                        <option value="!arp" <?=$pconfig['proto'] == "!arp" ? "selected=\"selected\"" : ""; ?>><?= gettext('Exclude ARP') ?></option>
                        <option value="carp" <?=$pconfig['proto'] == "carp" ? "selected=\"selected\"" : ""; ?>><?= gettext('CARP (VRRP)') ?></option>
                        <option value="!carp" <?=$pconfig['proto'] == "!carp" ? "selected=\"selected\"" : ""; ?>><?= gettext('Exclude CARP (VRRP)') ?></option>
                        <option value="esp" <?=$pconfig['proto'] == "esp" ? "selected=\"selected\"" : ""; ?>><?= gettext('ESP') ?></option>
                      </select>
                      <div class="hidden" data-for="help_for_proto">
                          <?=gettext("Select the protocol to capture, or Any.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_host" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Host Address");?></td>
                    <td>
                      <input type="text"  name="host" value="<?=$pconfig['host'];?>" />
                      <div class="hidden" data-for="help_for_host">
                        <?=gettext("This value is either the Source or Destination IP address or subnet in CIDR notation. The packet capture will look for this address in either field.");?>
                        <?=gettext("Matching can be negated by preceding the value with \"not\". Multiple IP addresses or CIDR subnets may be specified as boolean expression.");?>
                        <?=gettext("If you leave this field blank, all packets on the specified interface will be captured.");?>
                        <br/><br/><?=gettext("Example:");?> not 10.0.0.0/24 not and not 11.0.0.1
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_port" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Port");?></td>
                    <td>
                      <input type="text" name="port" value="<?=$pconfig['port'];?>" />
                      <div class="hidden" data-for="help_for_port">
                        <?=gettext("The port can be either the source or destination port. The packet capture will look for this port in either field.");?> <?=gettext("Leave blank if you do not want to filter by port.");?>
                      </div>
                  </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_snaplen" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Packet Length");?></td>
                    <td>
                      <input type="text" name="snaplen" value="<?=$pconfig['snaplen'];?>" />
                      <div class="hidden" data-for="help_for_snaplen">
                        <?=gettext("The Packet length is the number of bytes of each packet that will be captured. Default value is 0, which will capture the entire frame regardless of its size.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_count" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Count");?></td>
                    <td>
                      <input type="text" name="count" id="count" size="5" value="<?= $pconfig['count']; ?>" />
                      <div class="hidden" data-for="help_for_count">
                        <?=gettext("This is the number of packets the packet capture will grab. Default value is 100.") . "<br />" . gettext("Enter 0 (zero) for no count limit.");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td colspan="2"><b><?=gettext("View settings");?></b></td>
                  </tr>
                  <tr>
                    <td><a id="help_for_detail" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Level of Detail");?></td>
                    <td>
                      <select name="detail" class="selectpicker" id="detail">
                        <option value="normal" <?=$pconfig['detail'] == 'normal' ?  "selected=\"selected\"" : "";?> ><?=gettext("Normal");?></option>
                        <option value="medium" <?=$pconfig['detail'] == 'medium' ?  "selected=\"selected\"" : "";?> ><?=gettext("Medium");?></option>
                        <option value="high" <?=$pconfig['detail'] == 'high' ?  "selected=\"selected\"" : "";?> ><?=gettext("High");?></option>
                        <option value="full" <?=$pconfig['detail'] == 'full' ?  "selected=\"selected\"" : "";?> ><?=gettext("Full");?></option>
                      </select>
                      <div class="hidden" data-for="help_for_detail">
                        <?=gettext("This is the level of detail that will be displayed after hitting 'Stop' when the packets have been captured.") .  "<br /><b>" .
                           gettext("Note:") . "</b> " .
                           gettext("This option does not affect the level of detail when downloading the packet capture.");?>
                      </div>
                    </td>
                  </tr>

                  <tr>
                    <td><a id="help_for_dnsquery" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Reverse DNS Lookup");?></td>
                    <td>
                      <input name="dnsquery" id="dnsquery" type="checkbox"/>
                      <div class="hidden" data-for="help_for_dnsquery">
                       <?=gettext("This check box will cause the packet capture to perform a reverse DNS lookup associated with all IP addresses.");?>
                       <br /><b><?=gettext("Note");?>: </b><?=gettext("This option can cause delays for large packet captures.");?>
                     </div>
                    </td>
                  </tr>

                  <tr>
                    <td>&nbsp;</td>
                    <td>
<?php
                    if (capture_running()):?>
                      <input type="submit" class="btn" name="stop" value="<?= html_safe(gettext('Stop')) ?>"/>
<?php
                    else:?>
                      <input type="submit" class="btn" name="start" value="<?= html_safe(gettext('Start')) ?>"/>
<?php
                    if (count(glob('/tmp/packetcapture_*.cap')) > 0):?>
                      <button type="button" id="view" class="btn"> <?=gettext("View Capture");?> </button>
                      <input type="submit" class="btn" name="remove" value="<?= html_safe(gettext('Delete Capture')) ?>"/>
                    <table class="table table-condensed">
                      <thead>
                          <tr>
                              <th><?=gettext("Download Capture");?></th>
                          </tr>
                      </thead>
                      <tbody>
<?php
                    foreach (glob("/tmp/packetcapture_*.cap") as $filename):?>
                      <tr>
                        <td><a href="?download=<?=basename($filename);?>">
                          <i class="fa fa-file"></i>
                          <?=basename($filename);?>
                        </a>
                        </td>
                      </tr>
<?php
                    endforeach;?>
                      </tbody>
                    </table>
<?php
                    endif;
                    endif;?>
                    </td>
                  </tr>
                </tbody>
              </table>
            </form>
          </div>
        </div>
      </section>
      <section class="col-xs-12 hidden" id="capture">
        <div class="content-box">
          <div class="table-responsive">
            <table class="table table-condensed">
              <thead>
                  <tr>
                    <th><?=gettext("Interface");?></th>
                    <th><?=gettext("Capture output");?></th>
                  </tr>
              </thead>
              <tbody style="white-space: pre-wrap; font-family: monospace;" id="capture_output">
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </div>
  </div>
</section>
<?php
include("foot.inc");
