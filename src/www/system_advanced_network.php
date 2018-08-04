<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.
    Copyright (C) 2005-2007 Scott Ullrich <sullrich@gmail.com>
    Copyright (C) 2008 Shrew Soft Inc. <mgrooms@shrew.net>
    Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>
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
require_once("interfaces.inc");
require_once("filter.inc");
require_once("system.inc");
require_once("util.inc");

function get_mac_address()
{
    $ip = getenv('REMOTE_ADDR');
    $mac = `/usr/sbin/arp -an | grep {$ip} | cut -d" " -f4`;
    $mac = str_replace("\n","",$mac);
    return ($mac);
}

function generate_new_duid($duid_type)
{
    switch($duid_type)
    {
        case "1": //LLT
            $mac = get_mac_address();
            $ts = time() - 946684800;
            $hts = dechex($ts);
            $timestamp = sprintf("%s",$hts);
            $timestamp_array = str_split($timestamp,2);
            $timestamp = implode(":",$timestamp_array);
            $type = "\x00\x01\x00\x01";
            while ($count < strlen($type)) {
                $new_duid .= bin2hex( $type[$count]);
                $count++;
                if ($count < strlen($type)) {
                    $new_duid .= ':';
                }
            }
            $new_duid = $new_duid.':'.$timestamp.':'.$mac;
        break;


        case "2": //LL
            $ts = time() - 946684800;
            $hts = dechex($ts);
            $timestamp = sprintf("%s",$hts);
            $timestamp_array = str_split($timestamp,2);
            $timestamp = implode(":",$timestamp_array);
            $type = "\x00\x03\x00\x01";
            while ($count < strlen($type)) {
                $new_duid .= bin2hex( $type[$count]);
                $count++;
                if ($count < strlen($type)) {
                    $new_duid .= ':';
                }
            }
            $new_duid = $new_duid.':'.$timestamp;
        break;

        case "3": //UUID
            $type = "\x00\x00\x00\x04".openssl_random_pseudo_bytes(16);
            while ($count < strlen($type)) {
                $new_duid .= bin2hex( $type[$count]);
                $count++;
                if ($count < strlen($type)) {
                    $new_duid .= ':';
                }
            }
        break;
    }
    return($new_duid);
}

function reset_dhcp6c($duid_type,$pconfig,$config)
{
    $wanif = get_real_interface('wan', "inet6");

    killbyname("dhcp6c",SIGUSR1);

    if(file_exists("/var/db/dhcp6c_duid")) {
        unlink("/var/db/dhcp6c_duid");
    }
    if(file_exists("/var/conf/dhcp6c_duid")) {
        unlink("/var/conf/dhcp6c_duid");
    }

    switch($duid_type)
    {
        case "0": // Auto

        break;

        case "1": //LLT
            write_ipv6_duid($pconfig['ipv6_duid_llt_value']);
        break;

        case "2": //LL
            write_ipv6_duid($pconfig['ipv6_duid_ll_value']);
        break;

        case "3": //UUID
            write_ipv6_duid($pconfig['ipv6_duid_uu_value']);
        break;
    }
    sleep(2);
    mwexecf('/var/etc/rtsold_%s_script.sh',$wanif);

}
function format_duid($duid)
{
    $values = explode(':', strtolower(str_replace('-', ':', $duid)));

    if (count($values) == 14) {
        array_unshift($values, '0e', '00');
    }

    array_walk($values, function(&$value) {
        $value = str_pad($value, 2, '0', STR_PAD_LEFT);
    });

    return implode(':', $values);
}

function is_duid($duid)
{
    // Duid's can be any length. Just check the format is correct.
    $values = explode(":", $duid);

    // need to get the DUID type. There are four types, in the
    // first three the type number is in byte[2] in the fourth it's
    // in byte[4]. Offset is either 0 or 2 depending if it's the read #
    // from file duid or the user input.

    $valid_duid = false;

    $duid_length = count($values);
    $test1 = hexdec($values[1]);
    $test2 = hexdec($values[3]);
    if( ($test1 == 1 && $test2 == 1 ) || ($test1 == 3 && $test2 == 1 ) || ($test1 == 0 && $test2 == 4 ) || ($test1 == 2) )
    {
        $valid_duid = true;
    }
    if($duid_length < 10 || $duid_length > 254) // Max DUID length is 128, but with the seperators it could be upto 254
    {
        $valid_duid = false;
    }

    if($valid_duid == false) {
        return false;
    }


    for ($i = 0; $i < count($values); $i++) {
        if (ctype_xdigit($values[$i]) == false) {
            return false;
        }
        if (hexdec($values[$i]) < 0 || hexdec($values[$i]) > 255) {
            return false;
        }
    }
    return true;
}

/* read duid from disk or return blank DUID string */
function read_duid()
{
    $duid = '';
    $count = 0;
    if (file_exists('/var/db/dhcp6c_duid')) {
        $filesize = filesize('/var/db/dhcp6c_duid');
        if ($fd = fopen('/var/db/dhcp6c_duid', 'r')) {

            $buffer = fread($fd, $filesize);
            $duid_length = hexdec(bin2hex($buffer[0])); // This is the length of the duid, NOT the file

            while ($count < $duid_length) {
            $duid .= bin2hex($buffer[$count+2]); // Offset by 2 bytes
                $count++;
                if ($count < $duid_length) {
                    $duid .= ':';
                }
            }
        fclose($fd);
        }
    }

    if (!is_duid($duid)) {
        $duid = 'XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX';
    }

    return $duid;
}


$duid = read_duid();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();
    $pconfig['disablechecksumoffloading'] = isset($config['system']['disablechecksumoffloading']);
    $pconfig['disablesegmentationoffloading'] = isset($config['system']['disablesegmentationoffloading']);
    $pconfig['disablelargereceiveoffloading'] = isset($config['system']['disablelargereceiveoffloading']);
    $pconfig['ipv6duid'] = $config['system']['ipv6duid'];
    if (!isset($config['system']['disablevlanhwfilter'])) {
      $pconfig['disablevlanhwfilter'] = '0';
    } else {
      $pconfig['disablevlanhwfilter'] = $config['system']['disablevlanhwfilter'];
    }
    $pconfig['sharednet'] = isset($config['system']['sharednet']);
    $pconfig['ipv6_duid_type'] = $config['system']['ipv6_duid_type'];
    $pconfig['ipv6_duid_en_value'] = $config['system']['ipv6_duid_en_value'];
    $pconfig['ipv6_duid_ll_value'] = $config['system']['ipv6_duid_ll_value'];
    $pconfig['ipv6_duid_llt_value'] = $config['system']['ipv6_duid_llt_value'];
    $pconfig['ipv6_duid_uu_value'] = $config['system']['ipv6_duid_uu_value'];


} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    $pconfig = $_POST;

    if (isset($_POST['submit'])) {

        if (!empty($pconfig['ipv6duid']) && !is_duid($pconfig['ipv6duid'])) {
            $input_errors[] = gettext('A valid DUID must be specified.');
        }

        if (!count($input_errors)) {
            if (!empty($pconfig['sharednet'])) {
                $config['system']['sharednet'] = true;
            } elseif (isset($config['system']['sharednet'])) {
                unset($config['system']['sharednet']);
            }

            if (!empty($pconfig['disablechecksumoffloading'])) {
                $config['system']['disablechecksumoffloading'] = true;
            } elseif (isset($config['system']['disablechecksumoffloading'])) {
                unset($config['system']['disablechecksumoffloading']);
            }

            if (!empty($pconfig['disablesegmentationoffloading'])) {
                $config['system']['disablesegmentationoffloading'] = true;
            } elseif (isset($config['system']['disablesegmentationoffloading'])) {
                unset($config['system']['disablesegmentationoffloading']);
            }

            if (!empty($pconfig['disablelargereceiveoffloading'])) {
                $config['system']['disablelargereceiveoffloading'] = true;
            } elseif (isset($config['system']['disablelargereceiveoffloading'])) {
                unset($config['system']['disablelargereceiveoffloading']);
            }

            if (!empty($pconfig['disablevlanhwfilter'])) {
                $config['system']['disablevlanhwfilter'] = $pconfig['disablevlanhwfilter'];
            } elseif (isset($config['system']['disablevlanhwfilter'])) {
                unset($config['system']['disablevlanhwfilter']);
            }
            
            unset($config['system']['ipv6_duid_llt_value']);
            unset($config['system']['ipv6_duid_ll_value']);
            unset($config['system']['ipv6_duid_uu_value']);
             
            $config['system']['ipv6_duid_type'] = $pconfig['ipv6_duid_type'];
            
            switch($pconfig['ipv6_duid_type'])
            {
                case "1":                
                    $config['system']['ipv6_duid_llt_value'] = $pconfig['ipv6_duid_llt_value'];
                    break;
                case "2":
                    $config['system']['ipv6_duid_ll_value'] = $pconfig['ipv6_duid_ll_value'];
                    break;
                case "3":
                    $config['system']['ipv6_duid_uu_value'] = $pconfig['ipv6_duid_uu_value'];
                    break;
            }

            if (!empty($pconfig['ipv6duid'])) {
                $config['system']['ipv6duid'] = format_duid($pconfig['ipv6duid']);
            } elseif (isset($config['system']['ipv6duid'])) {
                unset($config['system']['ipv6duid']);
                /* clear the file as this means auto-generate */
                @unlink('/var/db/dhcp6c_duid');
            }


            $savemsg = get_std_save_message();

            write_config();
            system_arp_wrong_if();
        }
    } else if(isset($_POST['NewDUID'])) {

       switch($pconfig['ipv6_duid_type']) {
           case "1":
             $config['system']['ipv6_duid_ll_value'] = $pconfig['ipv6_duid_llt_value'] = generate_new_duid($pconfig['ipv6_duid_type']);
           break;
           case "2":
             $config['system']['ipv6_duid_ll_value'] = $pconfig['ipv6_duid_ll_value'] = generate_new_duid($pconfig['ipv6_duid_type']);
           break;
           case "3":
             $config['system']['ipv6_duid_uu_value'] = $pconfig['ipv6_duid_uu_value'] = generate_new_duid($pconfig['ipv6_duid_type']);
           break;
        }
    } else if(isset($_POST['dhcp6c_restart'])) {

        reset_dhcp6c($pconfig['ipv6_duid_type'],$pconfig,$config);
    }

}

legacy_html_escape_form_data($pconfig);
include("head.inc");

?>

<body>
<script>
 $( document ).ready(function() {
      $("#ipv6_duid_type").change(function(){
          $(".ipv6_duid_auto").addClass("hidden");
          $(".ipv6_duid_llt").addClass("hidden");
          $(".ipv6_duid_ll").addClass("hidden");
          $(".ipv6_duid_uu").addClass("hidden");
          $(".ipv6_restart_dhcp6c").addClass("hidden");
          $(".ipv6_gen_new_duid").addClass("hidden");
          switch($('#ipv6_duid_type option:selected').val()) {
            case "0":
              $(".ipv6_duid_auto").removeClass("hidden");
              $(".ipv6_restart_dhcp6c").removeClass("hidden");
              break;
            case "1":
              $(".ipv6_duid_llt").removeClass("hidden");
              $(".ipv6_gen_new_duid").removeClass("hidden");
              $(".ipv6_restart_dhcp6c").removeClass("hidden");
              break;
             case "2":
              $(".ipv6_duid_ll").removeClass("hidden");
              $(".ipv6_restart_dhcp6c").removeClass("hidden");
              $(".ipv6_restart_dhcp6c").removeClass("hidden");
              break;
            case "3":
              $(".ipv6_duid_uu").removeClass("hidden");
              $(".ipv6_gen_new_duid").removeClass("hidden");
              $(".ipv6_restart_dhcp6c").removeClass("hidden");
              break;
          }
      });
      $("#ipv6_duid_type").change(); // trigger initial

 });
</script>

  <?php include("fbegin.inc"); ?>

<!-- row -->
<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
<?php
    if (isset($input_errors) && count($input_errors)) {
        print_input_errors($input_errors);
    }
    if (isset($savemsg)) {
        print_info_box($savemsg);
    }
?>
    <section class="col-xs-12">
      <div class="content-box tab-content table-responsive">
        <form method="post" name="iform" id="iform">
          <table class="table table-striped opnsense_standard_table_form">
              <tr>
                <td style="width:22%"><strong><?= gettext('Network Interfaces') ?></strong></td>
                <td style="width:78%; text-align:right">
                  <small><?=gettext("full help"); ?> </small>
                  <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_disablechecksumoffloading" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Hardware CRC"); ?></td>
                <td>
                  <input name="disablechecksumoffloading" type="checkbox" id="disablechecksumoffloading" value="yes" <?= !empty($pconfig['disablechecksumoffloading']) ? "checked=\"checked\"" :"";?> />
                  <strong><?=gettext("Disable hardware checksum offload"); ?></strong>
                  <div class="hidden" data-for="help_for_disablechecksumoffloading">
                    <?=gettext("Checking this option will disable hardware checksum offloading. Checksum offloading is broken in some hardware, particularly some Realtek cards. Rarely, drivers may have problems with checksum offloading and some specific NICs."); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_disablesegmentationoffloading" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Hardware TSO"); ?></td>
                <td>
                  <input name="disablesegmentationoffloading" type="checkbox" id="disablesegmentationoffloading" value="yes" <?= !empty($pconfig['disablesegmentationoffloading']) ? "checked=\"checked\"" :"";?>/>
                  <strong><?=gettext("Disable hardware TCP segmentation offload"); ?></strong><br />
                  <div class="hidden" data-for="help_for_disablesegmentationoffloading">
                    <?=gettext("Checking this option will disable hardware TCP segmentation offloading (TSO, TSO4, TSO6). This offloading is broken in some hardware drivers, and may impact performance with some specific NICs."); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_disablelargereceiveoffloading" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Hardware LRO"); ?></td>
                <td>
                  <input name="disablelargereceiveoffloading" type="checkbox" id="disablelargereceiveoffloading" value="yes" <?= !empty($pconfig['disablelargereceiveoffloading']) ? "checked=\"checked\"" :"";?>/>
                  <strong><?=gettext("Disable hardware large receive offload"); ?></strong><br />
                  <div class="hidden" data-for="help_for_disablelargereceiveoffloading">
                    <?=gettext("Checking this option will disable hardware large receive offloading (LRO). This offloading is broken in some hardware drivers, and may impact performance with some specific NICs."); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_disablevlanhwfilter" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("VLAN Hardware Filtering"); ?></td>
                <td>
                  <select name="disablevlanhwfilter" class="selectpicker">
                      <option value="0" <?=$pconfig['disablevlanhwfilter'] == "0" ? "selected=\"selected\"" : "";?> >
                        <?=gettext("Enable VLAN Hardware Filtering");?>
                      </option>
                      <option value="1" <?=$pconfig['disablevlanhwfilter'] == "1" ? "selected=\"selected\"" : "";?> >
                        <?=gettext("Disable VLAN Hardware Filtering"); ?>
                      </option>
                      <option value="2" <?=$pconfig['disablevlanhwfilter'] == "2" ? "selected=\"selected\"" : "";?> >
                        <?=gettext("Leave default");?>
                      </option>
                  </select>
                  <div class="hidden" data-for="help_for_disablevlanhwfilter">
                    <?= gettext('Set usage of VLAN hardware filtering. This hardware acceleration may be broken in a particular device driver, or may impact performance.') ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_sharednet" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("ARP Handling"); ?></td>
                <td>
                  <input name="sharednet" type="checkbox" id="sharednet" value="yes" <?= !empty($pconfig['sharednet']) ? "checked=\"checked\"" :"";?>/>
                  <strong><?=gettext("Suppress ARP messages"); ?></strong><br />
                  <div class="hidden" data-for="help_for_sharednet">
                    <?=gettext("This option will suppress ARP log messages when multiple interfaces reside on the same broadcast domain"); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_persistent_duid" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Stored DHCP6 Unique Identifier"); ?></td>
                <td>
                   <textarea name="ipv6duid" id="ipv6duid" rows="2" ><?=$pconfig['ipv6duid'];?></textarea>             
                  <div class="hidden" data-for="help_for_persistent_duid">
                    <a onclick="$('#ipv6duid').val('<?= html_safe(read_duid()) ?>');" href="#"><?=gettext("Insert the existing DUID here"); ?></a>
                    <?= gettext('<br>This field can be used to enter an explicit DUID for use by IPv6 DHCP clients.<br>
                        The correct format for each DUID Type is as follows, all entries to be in hex format "xx" seperated by a colon.<br>
                        LLT: 4 bytes "00:01:00:01" followed by 4 bytes Unix time e.g. "00:01:02:03", followed by six bytes of the mac address.<br>
                        LL: 4 bytes "00:03:00:01" followed by 4 bytes Unix time e.g. "00:01:02:03".<br>
                        EN: 2 bytes "00:02" followed by 4 bytes of the enterprise number e.g. "00:00:00:01", followed by a variable length identifier of hex<br>
                        values up to 122 bytes in length.<br>
                        UUID: 4 bytes "00:00:00:04" followed by 8 bytes of a unuversally unique identifier.') ?><br />

                </div>
              </td>
            </tr>
             <tr class="ipv6_duid">
              <td><a id="help_for_ipv6_duid_type" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("DHCP6 DUID Type"); ?></td>
              <td>
                <select name="ipv6_duid_type" class="selectpicker" data-style="btn-default" id="ipv6_duid_type">

<?php
                foreach(array(
                  0 => 'AUTO',
                  1 => 'DUID_LLT',
                  2 => 'DUID_LL',
                  3 => 'DUID_UUID',
                ) as $duidtype => $dtype): ?>
                  <option value="<?=$duidtype;?>" <?=$duidtype == $pconfig['ipv6_duid_type'] ? "selected=\"selected\"" : "";?>>
                      <?=$dtype;?>
                  </option>
<?php
                endforeach;?>
                </select>
                <div class="hidden" data-for="help_for_ipv6_duid_type">
                  <?=gettext("Select the dhcp6 duid type to use.<br>
                  AUTO: Generated automatically by dhcp6c. Clear DHCP Unique Identifier to cause dhcp6c to create a new DUID<br>
                  DUID-LLT: Based on Link-layer Address Plus Time.<br>
                  DUID-LL: Based on Link-layer Address.<br>
                  DUID-UUID: Based on Universally Unique IDentifier.<br>
                  A DUID-EN type may be entered manually. Refer to manual unput help for format."); ?>
                </div>
              </td>
            </tr>
            <tr class="ipv6_duid_llt">
              <td><a id="help_for_ipv6_duid_llt" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("DHCP6 LLT DUID"); ?></td>
              <td>
                <table>
                <tr>
                  <td style="width:356px;">
                    <input name="ipv6_duid_llt_value" id="ipv6_duid_llt_value" readonly type="text" value=<?=$pconfig['ipv6_duid_llt_value'];?> ></td>
                  </td>
                  <td><input name="NewDUID" type="submit" class="btn btn-primary" value="<?=gettext("New DUID");?>" /></td>
                </tr>
               </table>
                <div class="hidden" data-for="help_for_ipv6_duid_llt">
                  <?= gettext('This field displays the generated LLT DUID Type. Once generated, clicking the Restart dhcp6c button will load the generated <br>
                  duid to be written to disk and restart dhcp6c, it will NOT save the new DUID to config. In order to that also select Save') ?><br />
                </div>
              </td>
             </tr>
            <tr class="ipv6_duid_ll">
              <td><a id="help_for_ipv6_duid_ll" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("DHCP6 Link-layer DUID"); ?></td>
                <td>
                  <table>
                <tr>
                  <td style="width:356px;">
                     <input name="ipv6_duid_ll_value" id="ipv6_duid_uu_value" readonly type="text" value=<?=$pconfig['ipv6_duid_ll_value'];?> ></td>
                  <td><input name="NewDUID" type="submit" class="btn btn-primary" value="<?=gettext("New DUID");?>" /></td>
                </tr>
               </table>
                  <div class="hidden" data-for="help_for_ipv6_duid_ll">
                    <?= gettext('This field displays the generated Link-layer DUID Type .Once generated, clicking the Restart dhcp6c button will load the generated <br>
                  duid to be written to disk and restart dhcp6c, it will NOT save the new DUID to config. In order to that, also select Save') ?><br />
                </div>
              </td>
             </tr>
            <tr class="ipv6_duid_uu">
              <td><a id="help_for_ipv6_duid_uu" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("DHCP6 Universally Unique (UUID) DUID"); ?></td>
              <td>
               <table>
                <tr>
                   <td style="width:356px;">                   
                   <textarea name="ipv6_duid_uu_value" rows="2" readonly><?=$pconfig['ipv6_duid_uu_value'];?></textarea>
                  </td>
                 <td><input name="NewDUID" type="submit" class="btn btn-primary" value="<?=gettext("New DUID");?>" /></td>
               </tr>
              </table>
              <div class="hidden" data-for="help_for_ipv6_duid_uu">
                  <?= gettext('This field displays the generated UU DUID Type .Once generated, clicking the Restart dhcp6c button will load the generated <br>
                  duid to be written to disk and restart dhcp6c, it will NOT save the new DUID to config. In order to that, also select Save') ?><br />
              </div>
             </td>
            </tr>
             <tr class="ipv6_restart_dhcp6c">
              <td><a id="help_for_dhcp6forcenewduid" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Write DUID & restart dhcp6c"); ?></td>
              <td>
                <input name="dhcp6c_restart" type="submit" class="btn btn-primary" value="<?=gettext("Restart dhcp6c");?>" />
                <?=gettext("This action will have an immediate affect"); ?><br />
                <div class="hidden" data-for="help_for_dhcp6forcenewduid">
                  <?= gettext('Clicking this button will cause write the selected DUID type to disk and force dhcp6c to reload the DUID and restart.') ?>
                </div>
              </td>
            </tr>
            <tr>
              <td>&nbsp;</td>
              <td><input name="submit" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" /></td>
            </tr>
            <tr>
              <td colspan="2">
                <?=gettext("This will take effect after you reboot the machine or re-configure each interface.");?>
              </td>
            </tr>
            </table>
          </form>
        </div>
      </section>
    </div>
  </div>
</section>


<?php include("foot.inc");
