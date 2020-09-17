<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2005-2007 Scott Ullrich <sullrich@gmail.com>
 * Copyright (C) 2008 Shrew Soft Inc. <mgrooms@shrew.net>
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
require_once("interfaces.inc");
require_once("filter.inc");
require_once("system.inc");

function get_mac_address($ip)
{
    $macs = array();

    exec(exec_safe('/usr/sbin/arp -an | grep %s | awk \'{ print $4 }\'', $ip), $macs);

    return !empty($macs[0]) ? $macs[0] : '';
}

function generate_new_duid($duid_type)
{
    $new_duid = '';
    switch ($duid_type) {
        case '1': //LLT
            $mac = get_mac_address(getenv('REMOTE_ADDR'));
            $ts = time() - 946684800;
            $hts = dechex($ts);
            $timestamp = sprintf("%s",$hts);
            $timestamp_array = str_split($timestamp,2);
            $timestamp = implode(":",$timestamp_array);
            $type = "\x00\x01\x00\x01";
            for ($count = 0; $count < strlen($type); ) {
                $new_duid .= bin2hex( $type[$count]);
                $count++;
                if ($count < strlen($type)) {
                    $new_duid .= ':';
                }
            }
            $new_duid = $new_duid.':'.$timestamp.':'.$mac;
            break;
        case '2': //LL - NO TIMESTAMP: Just 00:03:00:01: + Link layer address in canonical form, so says RFC.
            $mac = get_mac_address(getenv('REMOTE_ADDR'));
            $type = "\x00\x03\x00\x01";
            for ($count = 0; $count < strlen($type); ) {
                $new_duid .= bin2hex( $type[$count]);
                $count++;
                if ($count < strlen($type)) {
                    $new_duid .= ':';
                }
            }
            $new_duid = $new_duid.':'.$mac;
            break;
        case '3': //UUID
            $type = "\x00\x00\x00\x04".openssl_random_pseudo_bytes(16);
            for ($count = 0; $count < strlen($type); ) {
                $new_duid .= bin2hex( $type[$count]);
                $count++;
                if ($count < strlen($type)) {
                    $new_duid .= ':';
                }
            }
            break;
        case '4': //EN - Using Opnsense PEN!!!
            $type = "\x00\x02\x00\x00\xD2\x6D".openssl_random_pseudo_bytes(8);
            for ($count = 0; $count < strlen($type); ) {
                $new_duid .= bin2hex( $type[$count]);
                $count++;
                if ($count < strlen($type)) {
                    $new_duid .= ':';
                }
            }
            break;
        default:
            $new_duid = 'XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX';
            break;
    }

    return strtoupper($new_duid);
}

function format_duid($duid)
{
    $values = explode(':', strtoupper(str_replace('-', ':', $duid)));

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
    if (($test1 == 1 && $test2 == 1 ) || ($test1 == 3 && $test2 == 1 ) || ($test1 == 0 && $test2 == 4 ) || ($test1 == 2)) {
        $valid_duid = true;
    }

    /* max DUID length is 128, but with the separators it could be up to 254 */
    if ($duid_length < 6 || $duid_length > 254) {
        $valid_duid = false;
    }

    if ($valid_duid == false) {
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
    $duid = dhcp6c_duid_read();

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
    $pconfig['dhcp6_norelease'] = isset($config['system']['dhcp6_norelease']);
    $pconfig['dhcp6_debug'] = !isset($config['system']['dhcp6_debug']) ? '0' : $config['system']['dhcp6_debug'];
    $pconfig['ipv6duid'] = $config['system']['ipv6duid'];
    $pconfig['disablevlanhwfilter']  = !isset($config['system']['disablevlanhwfilter']) ? '0' : $config['system']['disablevlanhwfilter'];
    $pconfig['sharednet'] = isset($config['system']['sharednet']);
    $pconfig['ipv6_duid_llt_value'] = generate_new_duid('1');
    $pconfig['ipv6_duid_ll_value'] = generate_new_duid('2');
    $pconfig['ipv6_duid_uuid_value'] = generate_new_duid('3');
    $pconfig['ipv6_duid_en_value'] = generate_new_duid('4');
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    $pconfig = $_POST;

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

        if (!empty($pconfig['dhcp6_norelease'])) {
            $config['system']['dhcp6_norelease'] = $pconfig['dhcp6_norelease'];
        } elseif (isset($config['system']['dhcp6_norelease'])) {
            unset($config['system']['dhcp6_norelease']);
        }

        if (!empty($pconfig['dhcp6_debug'])) {
            $config['system']['dhcp6_debug'] = $pconfig['dhcp6_debug'];
        } elseif (isset($config['system']['dhcp6_debug'])) {
            unset($config['system']['dhcp6_debug']);
        }

        if (!empty($pconfig['ipv6duid'])) {
            $config['system']['ipv6duid'] = format_duid($pconfig['ipv6duid']);
        } elseif (isset($config['system']['ipv6duid'])) {
            unset($config['system']['ipv6duid']);
            /* clear the file as this means auto-generate */
            dhcp6c_duid_clear();
        }

        $savemsg = get_std_save_message();

        write_config();
        interface_dhcpv6_configure('duidonly', null); /* XXX refactor */
        system_arp_wrong_if();
    }
}

legacy_html_escape_form_data($pconfig);

include("head.inc");

?>

<body>
<?php include("fbegin.inc"); ?>
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
      <form method="post" name="iform" id="iform">
        <div class="content-box tab-content table-responsive __mb">
          <table class="table table-striped opnsense_standard_table_form">
              <tr>
                <td style="width:22%"><strong><?= gettext('Hardware Offloading') ?></strong></td>
                <td style="width:78%; text-align:right">
                  <small><?=gettext("full help"); ?> </small>
                  <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                </td>
              </tr>
              <tr>
                <td colspan="2">
                  <?=gettext("The following options are disabled by default because some NICs and/or drivers are broken. Enabling a hardware offloading option may completely block network connectivity when IPS mode is enabled in Services – Intrusion Detection – Administration."); ?>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_disablechecksumoffloading" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Hardware CRC offloading"); ?></td>
                <td>
                  <input name="disablechecksumoffloading" type="checkbox" id="disablechecksumoffloading" value="yes" <?= !empty($pconfig['disablechecksumoffloading']) ? "" :"checked=\"checked\"";?> />
                  <div class="hidden" data-for="help_for_disablechecksumoffloading">
                    <?=gettext("Choose whether to process checksums in hardware. Note that when this is enabled, a packet capture will see empty (all zero) or flag incorrect packet checksums. This is expected behavior with hardware checksum handling."); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_disablesegmentationoffloading" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Hardware TSO"); ?></td>
                <td>
                  <input name="disablesegmentationoffloading" type="checkbox" id="disablesegmentationoffloading" value="yes" <?= !empty($pconfig['disablesegmentationoffloading']) ? "" :"checked=\"checked\"";?>/>
                  <div class="hidden" data-for="help_for_disablesegmentationoffloading">
                    <?=gettext("Choose whether to process outgoing TCP segmentation in hardware, also known as TCP segmentation offloading (TSO, TSO4, TSO6)."); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_disablelargereceiveoffloading" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Hardware LRO"); ?></td>
                <td>
                  <input name="disablelargereceiveoffloading" type="checkbox" id="disablelargereceiveoffloading" value="yes" <?= !empty($pconfig['disablelargereceiveoffloading']) ? "" :"checked=\"checked\"";?>/>
                  <div class="hidden" data-for="help_for_disablelargereceiveoffloading">
                    <?=gettext("Choose whether to process incoming TCP segmentation in hardware, also known as large receive offloading (LRO)."); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_disablevlanhwfilter" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("VLAN Hardware Processing"); ?></td>
                <td>
                  <select name="disablevlanhwfilter" class="selectpicker">
                      <option value="0" <?=$pconfig['disablevlanhwfilter'] == "0" ? "selected=\"selected\"" : "";?> >
                        <?=gettext("Enable");?>
                      </option>
                      <option value="1" <?=$pconfig['disablevlanhwfilter'] == "1" ? "selected=\"selected\"" : "";?> >
                        <?=gettext("Disable"); ?>
                      </option>
                      <option value="2" <?=$pconfig['disablevlanhwfilter'] == "2" ? "selected=\"selected\"" : "";?> >
                        <?=gettext("Default");?>
                      </option>
                  </select>
                  <div class="hidden" data-for="help_for_disablevlanhwfilter">
                    <?= gettext('Choose whether to process VLAN tagging, filtering, outgoing TCP segmentation (TSO) and checksums in hardware. Setting this to \'Default\' means that each NIC will retain its own default setting.') ?>
                  </div>
                </td>
              </tr>
            </table>
          </div>
          <div class="content-box tab-content table-responsive __mb">
            <table class="table table-striped opnsense_standard_table_form">
              <tr>
                <td style="width:22%"><strong><?= gettext('Logging') ?></strong></td>
                <td style="width:78%"></td>
              </tr>
              <tr>
                <td><a id="help_for_sharednet" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("ARP"); ?></td>
                <td>
                  <input name="sharednet" type="checkbox" id="sharednet" value="yes" <?= !empty($pconfig['sharednet']) ? "" :"checked=\"checked\"";?>/>
                  <strong><?=gettext("Log ARP address changes"); ?></strong>
                  <div class="hidden" data-for="help_for_sharednet">
                    <?=gettext("Choose whether to make a log entry in the main system log when an IP address moves to a different MAC address."); ?>
                  </div>
                </td>
              </tr>
            </table>
          </div>
          <div class="content-box tab-content table-responsive __mb">
            <table class="table table-striped opnsense_standard_table_form">
              <tr>
                <td style="width:22%"><strong><?= gettext('IPv6 DHCP') ?></strong></td>
                <td style="width:78%"></td>
              </tr>
              <tr>
                <td><a id="help_for_dhcp6_norelease" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Prevent release') ?></td>
                <td>
                  <input name="dhcp6_norelease" type="checkbox" id="dhcp6_norelease" value="yes" <?= !empty($pconfig['dhcp6_norelease']) ? 'checked="checked"' : '' ?> />
                  <div class="hidden" data-for="help_for_dhcp6_norelease">
                    <?= gettext('Do not send a release message on client exit to prevent the release of an allocated address or prefix on the server.') ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_dhcp6_debug" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Log level') ?></td>
                <td>
                  <select name="dhcp6_debug" size="3" class="selectpicker" data-style="btn-default" id="dhcp6_debug">
<?php foreach(['0' => gettext('Standard'), '1' => gettext('Info'), '2' => gettext('Debug')] as $dhcp6cdebuglevel => $dhcp6cdebugvalue): ?>
                    <option value="<?= html_safe($dhcp6cdebuglevel) ?>" <?= $pconfig['dhcp6_debug'] == $dhcp6cdebuglevel ? 'selected="selected"' : '' ?>>
                      <?= html_safe($dhcp6cdebugvalue) ?>
                    </option>
<?php endforeach ?>
                  </select>
                  <div class="hidden" data-for="help_for_dhcp6_debug">
                    <?= gettext('Modify log level for IPv6 clients. Info will give status, interface leases and addresses. Debug will give full diagnostics.') ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_persistent_duid" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("DHCP Unique Identifier"); ?></td>
                <td>
                  <textarea name="ipv6duid" id="ipv6duid" rows="2" ><?=$pconfig['ipv6duid'];?></textarea>
                  <input name="ipv6_duid_llt_value" type="hidden" value="<?= html_safe($pconfig['ipv6_duid_llt_value']) ?>">
                  <input name="ipv6_duid_ll_value" type="hidden" value="<?= html_safe($pconfig['ipv6_duid_ll_value']) ?>">
                  <input name="ipv6_duid_uuid_value" type="hidden" value="<?= html_safe($pconfig['ipv6_duid_uuid_value']) ?>">
                  <input name="ipv6_duid_en_value" type="hidden" value="<?= html_safe($pconfig['ipv6_duid_en_value']) ?>">
                  <a onclick="$('#ipv6duid').val('<?= html_safe($duid) ?>');" href="#"><?= gettext('Insert the existing DUID') ?></a><br/>
                  <a onclick="$('#ipv6duid').val('<?= html_safe($pconfig['ipv6_duid_llt_value']) ?>');" href="#"><?= gettext('Insert a new LLT DUID') ?></a><br/>
                  <a onclick="$('#ipv6duid').val('<?= html_safe($pconfig['ipv6_duid_ll_value']) ?>');" href="#"><?= gettext('Insert a new LL DUID') ?></a><br/>
                  <a onclick="$('#ipv6duid').val('<?= html_safe($pconfig['ipv6_duid_uuid_value']) ?>');" href="#"><?= gettext('Insert a new UUID DUID') ?></a><br/>
                  <a onclick="$('#ipv6duid').val('<?= html_safe($pconfig['ipv6_duid_en_value']) ?>');" href="#"><?= gettext('Insert a new EN DUID') ?></a><br/>
                  <a onclick="$('#ipv6duid').val('');" href="#"><?= gettext('Clear the existing DUID') ?></a><br/>
                  <div class="hidden" data-for="help_for_persistent_duid">
                    <?= gettext('This field can be used to enter an explicit DUID for use by IPv6 DHCP clients.') ?><br/>
                    <?= gettext('The correct format for each DUID type is as follows, all entries to be in hex format "xx" separated by a colon.') ?><br/>
                    <?= gettext('LLT: 4 bytes "00:01:00:01" followed by 4 bytes Unix time e.g. "00:01:02:03", followed by six bytes of the MAC address.') ?><br/>
                    <?= gettext('LL: 4 bytes "00:03:00:01" followed by 4 bytes Unix time e.g. "00:01:02:03".') ?><br/>
                    <?= gettext('UUID: 4 bytes "00:00:00:04" followed by 8 bytes of a universally unique identifier.') ?><br/>
                    <?= gettext('EN: 2 bytes "00:02" followed by 4 bytes of the enterprise number e.g. "00:00:00:01", ' .
                            'followed by a variable length identifier of hex values up to 122 bytes in length.') ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td>&nbsp;</td>
                <td><button name="submit" type="submit" class="btn btn-primary" value="yes"><?= gettext('Save') ?></button></td>
              </tr>
              <tr>
                <td colspan="2">
                  <?= gettext('This will take effect after you reboot the machine or reconfigure each interface.') ?>
                </td>
              </tr>
            </table>
          </div>
        </form>
      </section>
    </div>
  </div>
</section>
<?php

include("foot.inc");
