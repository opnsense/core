<?php

/*
 * Copyright (C) 2016 Deciso B.V.
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

/**
 * Return array of possible TOS values
 */
function filter_tos_values()
{
    $ret = array(
        '' => gettext('Do not change'),
        'lowdelay' => gettext('lowdelay'),
        'critical' => gettext('critical'),
        'inetcontrol' => gettext('inetcontrol'),
        'netcontrol' => gettext('netcontrol'),
        'throughput' => gettext('throughput'),
        'reliability' => gettext('reliability'),
        'ef' => 'EF',
    );

    foreach (array(11, 12, 13, 21, 22, 23, 31, 32, 33, 41 ,42, 43) as $val) {
        $ret["af$val"] = "AF$val";
    }

    foreach (range(0, 7) as $val) {
        $ret["cs$val"] = "CS$val";
    }

    foreach (range(0, 255) as $val) {
        $ret['0x' . dechex($val)] = sprintf('0x%02X', $val);
    }

    return $ret;
}

/**
 * fetch list of selectable networks to use in form
 */
function formNetworks()
{
    $networks = array();
    $networks["any"] = gettext("any");
    // foreach (get_configured_interface_with_descr() as $ifent => $ifdesc) {
    //     $networks[$ifent] = htmlspecialchars($ifdesc) . " " . gettext("net");
    //     $networks[$ifent."ip"] = htmlspecialchars($ifdesc). " ". gettext("address");
    // }
    return $networks;
}

$a_scrub = &config_read_array('filter', 'scrub', 'rule');

// define form fields
$config_fields = array('interface', 'proto', 'srcnot', 'src', 'srcmask', 'dstnot', 'dst', 'dstmask', 'dstport',
                       'no-df', 'random-id', 'max-mss', 'min-ttl', 'set-tos', 'descr', 'disabled', 'direction',
                       'srcport');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // input record id, if valid
    if (isset($_GET['dup']) && isset($a_scrub[$_GET['dup']]))  {
        $configId = $_GET['dup'];
        $after = $configId;
    } elseif (isset($_GET['id']) && isset($a_scrub[$_GET['id']])) {
        $id = $_GET['id'];
        $configId = $id;
    }

    $pconfig = array();
    if (isset($configId)) {
        // 1-on-1 copy of config data
        foreach ($config_fields as $fieldname) {
            if (isset($a_scrub[$configId][$fieldname])) {
                $pconfig[$fieldname] = $a_scrub[$configId][$fieldname];
            }
        }
    } else {
        /* defaults */
        $pconfig['src'] = 'any';
        $pconfig['dst'] = 'any';
    }

    // initialize empty fields
    foreach ($config_fields as $fieldname) {
        if (!isset($pconfig[$fieldname])) {
            $pconfig[$fieldname] = null;
        }
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    $pconfig = $_POST;

    // input record id, if valid
    if (isset($pconfig['id']) && isset($a_scrub[$pconfig['id']])) {
        $id = $pconfig['id'];
    }
    if (isset($pconfig['after']) && isset($a_scrub[$pconfig['after']])) {
        $after = $pconfig['after'];
    }

    // validate form input
    if (!empty($pconfig['dstport']) && $pconfig['dstport'] != 'any' && !is_portoralias($pconfig['dstport']) && !is_portrange($pconfig['dstport'])) {
        $input_errors[] = sprintf(gettext("%s doesn't appear to be a valid port number, alias or range"), $pconfig['dstport']) ;
    }
    if (!empty($pconfig['srcport']) && $pconfig['srcport'] != 'any' && !is_portoralias($pconfig['srcport']) && !is_portrange($pconfig['srcport'])) {
        $input_errors[] = sprintf(gettext("%s doesn't appear to be a valid port number, alias or range"), $pconfig['srcport']) ;
    }
    if (is_ipaddrv4($pconfig['src']) && is_ipaddrv6($pconfig['dst'])) {
        $input_errors[] = gettext("You can not use IPv6 addresses in IPv4 rules.");
    }
    if (is_ipaddrv6($pconfig['src']) && is_ipaddrv4($pconfig['dst'])) {
        $input_errors[] = gettext("You can not use IPv4 addresses in IPv6 rules.");
    }

    if (is_ipaddrv4($pconfig['src']) && $pconfig['srcmask'] > 32) {
        $input_errors[] = gettext("Invalid subnet mask on IPv4 source");
    }
    if (is_ipaddrv4($pconfig['dst']) && $pconfig['dstmask'] > 32) {
        $input_errors[] = gettext("Invalid subnet mask on IPv4 destination");
    }

    if (empty($pconfig['interface'])) {
        $input_errors[] = gettext("No interface(s) selected.");
    }

    if (!empty($pconfig['max-mss']) && filter_var($pconfig['max-mss'], FILTER_SANITIZE_NUMBER_INT) != $pconfig['max-mss']) {
        $input_errors[] = gettext("Please specify a valid number for max mss.");
    }

    if (!empty($pconfig['min-ttl']) && (filter_var($pconfig['min-ttl'], FILTER_SANITIZE_NUMBER_INT) != $pconfig['min-ttl'] ||
        $pconfig['min-ttl'] < 0 || $pconfig['min-ttl'] > 255 )) {
        $input_errors[] = gettext("Please specify a valid number for min ttl (0-255).");
    }

    if (count($input_errors) == 0) {
        $scrubent = array();
        foreach ($config_fields as $fieldname) {
            if (!empty($pconfig[$fieldname])) {
                if (is_array($pconfig[$fieldname])) {
                     $scrubent[$fieldname] = implode(',', $pconfig[$fieldname]);
                } else  {
                    $scrubent[$fieldname] = trim($pconfig[$fieldname]);
                }
            }
        }

        $scrubent['updated'] = make_config_revision_entry();

        // update or insert item
        if (isset($id)) {
            if ( isset($a_scrub[$id]['created']) && is_array($a_scrub[$id]['created']) ) {
                $scrubent['created'] = $a_scrub[$id]['created'];
            }
            $a_scrub[$id] = $scrubent;
        } else {
            $scrubent['created'] = make_config_revision_entry();
            if (isset($after)) {
                array_splice($a_scrub, $after+1, 0, array($scrubent));
            } else {
                $a_scrub[] = $scrubent;
            }
        }
        // write to config
        write_config();
        mark_subsystem_dirty('filter');

        header(url_safe('Location: /firewall_scrub.php'));
        exit;
    }
}

legacy_html_escape_form_data($pconfig);

include("head.inc");
?>

<body>
  <script>
  $( document ).ready(function() {
      // select / input combination, link behaviour
      // when the data attribute "data-other" is selected, display related input item(s)
      // push changes from input back to selected option value
      $('[for!=""][for]').each(function(){
          var refObj = $("#"+$(this).attr("for"));
          if (refObj.is("select")) {
              // connect on change event to select box (show/hide)
              refObj.change(function(){
                if ($(this).find(":selected").attr("data-other") == "true") {
                    // show related controls
                    $('*[for="'+$(this).attr("id")+'"]').each(function(){
                      if ($(this).hasClass("selectpicker")) {
                        $(this).selectpicker('show');
                      } else {
                        $(this).removeClass("hidden");
                      }
                    });
                } else {
                    // hide related controls
                    $('*[for="'+$(this).attr("id")+'"]').each(function(){
                      if ($(this).hasClass("selectpicker")) {
                        $(this).selectpicker('hide');
                      } else {
                        $(this).addClass("hidden");
                      }
                    });
                }
              });
              // update initial
              refObj.change();

              // connect on change to input to save data to selector
              if ($(this).attr("name") == undefined) {
                $(this).change(function(){
                    var otherOpt = $('#'+$(this).attr('for')+' > option[data-other="true"]') ;
                    otherOpt.attr("value",$(this).val());
                });
              }
          }
      });

      $("#proto").change(function() {
          // lock src/dst ports on other then tcp/udp
          if ($("#proto").val() == 'tcp' || $("#proto").val() == 'udp' || $("#proto").val() == 'tcp/udp') {
              $("#dstport").prop('disabled', false);
              $("#srcport").prop('disabled', false);
          } else {
              $("#dstport optgroup:last option:first").prop('selected', true);
              $("#dstport").prop('disabled', true);
              $("#srcport").prop('disabled', true);
          }
          $("#dstport").selectpicker('refresh');
          $("#dstport").change();
          $("#srcport").selectpicker('refresh');
          $("#srcport").change();
      });
      $("#proto").change();

      if ($("#srcport").val() != "") {
          $("#show_srcport").show();
          $("#show_srcport_adv").parent().hide();
      }
      $("#show_srcport_adv").click(function(){
          $("#show_srcport").show();
          $("#show_srcport_adv").parent().hide();
      });

      // IPv4/IPv6 select
      hook_ipv4v6('ipv4v6net', 'network-id');
  });

  </script>
  <?php include("fbegin.inc"); ?>
    <section class="page-content-main">
      <div class="container-fluid">
        <div class="row">
          <form method="post" name="iform" id="iform">
            <input type='hidden' name="id" value="<?=isset($id) ? $id:''?>" />
            <input name="after" type="hidden" value="<?=isset($after) ? $after :'';?>" />
            <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
            <section class="col-xs-12">
              <div class="content-box">
                <div class="table-responsive">
                  <table class="table table-striped opnsense_standard_table_form">
                  <tr>
                    <td style="width:22%"><strong><?=gettext("Edit Firewall scrub rule");?></strong></td>
                    <td style="width:78%;text-align:right">
                      <small><?=gettext("full help"); ?> </small>
                      <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                    </td>
                  </tr>
                  <tr>
                    <td style="width:22%"><a id="help_for_disabled" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Disabled"); ?></td>
                    <td style="width:78%">
                      <input name="disabled" type="checkbox" id="disabled" value="yes" <?= !empty($pconfig['disabled']) ? "checked=\"checked\"" : ""; ?> />
                      <div class="hidden" data-for="help_for_disabled">
                        <strong><?=gettext("Disable this rule"); ?></strong><br />
                        <?=gettext("Set this option to disable this rule without removing it from the list."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_interface" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Interface");?></td>
                    <td>
                      <select name="interface[]" title="<?=gettext("Select interfaces...");?>" multiple="multiple" class="selectpicker" data-live-search="true" data-size="5" <?=!empty($pconfig['associated-rule-id']) ? "disabled" : "";?>>
<?php
                    foreach (legacy_config_get_interfaces(array("enable" => true)) as $iface => $ifaceInfo): ?>
                        <option value="<?=$iface;?>"
                            <?= !empty($pconfig['interface']) && (
                                  $iface == $pconfig['interface'] ||
                                  // match multiple interfaces
                                  (!is_array($pconfig['interface']) && in_array($iface, explode(',', $pconfig['interface']))) ||
                                  (is_array($pconfig['interface']) && in_array($iface, $pconfig['interface']))
                                ) ? 'selected="selected"' : ''; ?>>
                          <?=htmlspecialchars($ifaceInfo['descr']);?>
                        </option>
<?php
                    endforeach; ?>
                        </select>
                        <div class="hidden" data-for="help_for_interface">
                          <?=gettext("Choose on which interface packets must come in to match this rule.");?>
                        </div>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Direction");?></td>
                    <td>
                      <select name="direction" class="selectpicker" data-live-search="true" data-size="5" >
                        <option value="" <?= empty($pconfig['direction']) ? "selected=\"selected\"" : "" ?>>
                          <?=gettext("Any");?>
                        </option>
                        <option value="in" <?= $pconfig['direction'] == 'in' ? "selected=\"selected\"" : "" ?>>
                          <?=gettext("In");?>
                        </option>
                        <option value="out" <?= $pconfig['direction'] == 'out' ? "selected=\"selected\"" : "" ?>>
                          <?=gettext("Out");?>
                        </option>
                      </select>
                    </td>
                  <tr>
                  <tr>
                    <td><a id="help_for_protocol" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Protocol");?></td>
                    <td>
                      <select name="proto" id="proto" class="selectpicker" data-live-search="true" data-size="5" >
<?php
                      foreach (get_protocols() as $proto): ?>
                        <option value="<?=strtolower($proto);?>" <?= strtolower($proto) == $pconfig['proto'] ? "selected=\"selected\"" :""; ?>>
                          <?=$proto;?>
                        </option>
<?php
                      endforeach; ?>
                      </select>
                      <div class="hidden" data-for="help_for_protocol">
                        <?=gettext("Choose which IP protocol this rule should match.");?> <br />
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td> <a id="help_for_src_invert" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Source") . " / ".gettext("Invert");?> </td>
                    <td>
                      <input name="srcnot" type="checkbox" value="yes" <?= !empty($pconfig['srcnot']) ? "checked=\"checked\"" : "";?> />
                      <div class="hidden" data-for="help_for_src_invert">
                        <?=gettext("Use this option to invert the sense of the match."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Source"); ?></td>
                      <td>
                        <table class="table table-condensed">
                          <tr>
                            <td>
                              <select name="src" id="src" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                                <option data-other=true value="<?=$pconfig['src'];?>" <?=!is_specialnet($pconfig['src']) ? "selected=\"selected\"" : "";?>><?=gettext("Single host or Network"); ?></option>
                                <optgroup label="<?=gettext("Aliases");?>">
  <?php                        foreach (legacy_list_aliases("network") as $alias):
  ?>
                                  <option value="<?=$alias['name'];?>" <?=$alias['name'] == $pconfig['src'] ? "selected=\"selected\"" : "";?>><?=htmlspecialchars($alias['name']);?></option>
  <?php                          endforeach; ?>
                                </optgroup>
                                <optgroup label="<?=gettext("Networks");?>">
  <?php                          foreach (formNetworks() as $ifent => $ifdesc):
  ?>
                                  <option value="<?=$ifent;?>" <?= $pconfig['src'] == $ifent ? "selected=\"selected\"" : ""; ?>><?=$ifdesc;?></option>
  <?php                            endforeach; ?>
                              </optgroup>
                            </select>
                          </td>
                        </tr>
                        <tr>
                          <td>
                            <div>
                              <table style="border:0;">
                                <tbody>
                                  <tr>
                                      <td style="width:348px">
                                        <!-- updates to "other" option in  src -->
                                        <input type="text" id="src_address" for="src" value="<?=$pconfig['src'];?>" aria-label="<?=gettext("Source address");?>"/>
                                      </td>
                                      <td>
                                        <select name="srcmask" data-network-id="src_address" class="selectpicker ipv4v6net" data-size="5" id="srcmask"  data-width="auto" for="src" >
                                        <?php for ($i = 128; $i > 0; $i--): ?>
                                          <option value="<?=$i;?>" <?= $i == $pconfig['srcmask'] ? "selected=\"selected\"" : ""; ?>><?=$i;?></option>
                                        <?php endfor; ?>
                                        </select>
                                      </td>
                                  </tr>
                                </tbody>
                              </table>
                          </div>
                          </td>
                        </tr>
                      </table>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_srcport" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Source port"); ?></td>
                    <td>
                      <div>
                        <input type="button" class="btn btn-default" value="<?= html_safe(gettext('Advanced')) ?>" id="show_srcport_adv" />
                      </div>
                      <div id="show_srcport" style="display:none;">
                      <table class="table table-condensed">
                        <tbody>
                          <tr>
                            <td>
                              <select id="srcport" name="srcport" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                                <option data-other=true value="<?=$pconfig['srcport'];?>">(<?=gettext("other"); ?>)</option>
                                <optgroup label="<?=gettext("Aliases");?>">
  <?php                        foreach (legacy_list_aliases("port") as $alias):
  ?>
                                  <option value="<?=$alias['name'];?>" <?= $pconfig['srcport'] == $alias['name'] ? "selected=\"selected\"" : ""; ?>  ><?=htmlspecialchars($alias['name']);?> </option>
  <?php                          endforeach; ?>
                                </optgroup>
                                <optgroup label="<?=gettext("Well-known ports");?>">
                                  <option value="" <?= empty($pconfig['srcport']) ? "selected=\"selected\"" : ""; ?>><?=gettext("any"); ?></option>
  <?php                            foreach ($wkports as $wkport => $wkportdesc): ?>
                                  <option value="<?=$wkport;?>" <?= (string)$wkport == $pconfig['srcport'] ?  "selected=\"selected\"" : "" ;?>><?=htmlspecialchars($wkportdesc);?></option>
  <?php                            endforeach; ?>
                                </optgroup>
                              </select>
                            </td>
                          </tr>
                          <tr>
                            <td>
                              <input type="text" value="<?=$pconfig['srcport'];?>" for="srcport"> <!-- updates to "other" option in  srcport -->
                            </td>
                          </tr>
                        </tbody>
                      </table>
                      </div>
                      <div class="hidden" data-for="help_for_srcport">
                        <?=gettext("Specify the port or port range for the destination of the packet for this mapping."); ?><br/>
                        <?=gettext("To specify a range, use from:to (example 81:85).");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td> <a id="help_for_dst_invert" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Destination") . " / ".gettext("Invert");?> </td>
                    <td>
                      <input name="dstnot" type="checkbox" value="yes" <?= !empty($pconfig['dstnot']) ? "checked=\"checked\"" : "";?> />
                      <div class="hidden" data-for="help_for_dst_invert">
                        <?=gettext("Use this option to invert the sense of the match."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i> <?=gettext("Destination"); ?></td>
                    <td>
                      <table class="table table-condensed">
                        <tr>
                          <td>
                            <select name="dst" id="dst" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                              <option data-other=true value="<?=$pconfig['dst'];?>" <?=!is_specialnet($pconfig['dst']) ? "selected=\"selected\"" : "";?>><?=gettext("Single host or Network"); ?></option>
                              <optgroup label="<?=gettext("Aliases");?>">
  <?php                        foreach (legacy_list_aliases("network") as $alias):
  ?>
                                <option value="<?=$alias['name'];?>" <?=$alias['name'] == $pconfig['dst'] ? "selected=\"selected\"" : "";?>><?=htmlspecialchars($alias['name']);?></option>
  <?php                          endforeach; ?>
                              </optgroup>
                              <optgroup label="<?=gettext("Networks");?>">
  <?php                          foreach (formNetworks() as $ifent => $ifdesc):
  ?>
                                <option value="<?=$ifent;?>" <?= $pconfig['dst'] == $ifent ? "selected=\"selected\"" : ""; ?>><?=$ifdesc;?></option>
  <?php                            endforeach; ?>
                              </optgroup>
                            </select>
                          </td>
                        </tr>
                        <tr>
                          <td>
                            <table style="border:0;">
                              <tbody>
                                <tr>
                                    <td style="width:348px">
                                      <!-- updates to "other" option in  src -->
                                      <input  type="text" id="dst_address" for="dst" value="<?=$pconfig['dst'];?>" aria-label="<?=gettext("Destination address");?>"/>
                                    </td>
                                    <td>
                                      <select name="dstmask" data-network-id="dst_address" class="selectpicker ipv4v6net" data-size="5" id="dstmask"  data-width="auto" for="dst" >
                                      <?php for ($i = 128; $i > 0; $i--): ?>
                                        <option value="<?=$i;?>" <?= $i == $pconfig['dstmask'] ? "selected=\"selected\"" : ""; ?>><?=$i;?></option>
                                      <?php endfor; ?>
                                      </select>
                                    </td>
                                </tr>
                              </tbody>
                            </table>
                          </td>
                        </tr>
                      </table>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_dstport" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Destination port"); ?></td>
                    <td>
                      <table class="table table-condensed">
                        <tbody>
                          <tr>
                            <td>
                              <select id="dstport" name="dstport" class="selectpicker" data-live-search="true" data-size="5" data-width="auto">
                                <option data-other=true value="<?=$pconfig['dstport'];?>">(<?=gettext("other"); ?>)</option>
                                <optgroup label="<?=gettext("Aliases");?>">
  <?php                        foreach (legacy_list_aliases("port") as $alias):
  ?>
                                  <option value="<?=$alias['name'];?>" <?= $pconfig['dstport'] == $alias['name'] ? "selected=\"selected\"" : ""; ?>  ><?=htmlspecialchars($alias['name']);?> </option>
  <?php                          endforeach; ?>
                                </optgroup>
                                <optgroup label="<?=gettext("Well-known ports");?>">
                                  <option value="" <?= empty($pconfig['dstport']) ? "selected=\"selected\"" : ""; ?>><?=gettext("any"); ?></option>
  <?php                            foreach ($wkports as $wkport => $wkportdesc): ?>
                                  <option value="<?=$wkport;?>" <?= (string)$wkport == $pconfig['dstport'] ?  "selected=\"selected\"" : "" ;?>><?=htmlspecialchars($wkportdesc);?></option>
  <?php                            endforeach; ?>
                                </optgroup>
                              </select>
                            </td>
                          </tr>
                          <tr>
                            <td>
                              <input  type="text" value="<?=$pconfig['dstport'];?>" for="dstport"> <!-- updates to "other" option in  dstport -->
                            </td>
                          </tr>
                        </tbody>
                      </table>
                      <div class="hidden" data-for="help_for_dstport">
                        <?=gettext("Specify the port or port range for the destination of the packet for this mapping."); ?><br/>
                        <?=gettext("To specify a range, use from:to (example 81:85).");?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_descr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description"); ?></td>
                    <td>
                      <input name="descr" type="text" id="descr" size="40" value="<?=$pconfig['descr'];?>" />
                      <div class="hidden" data-for="help_for_descr">
                        <?=gettext("You may enter a description here for your reference (not parsed)."); ?>
                      </div>
                    </td>
                  </tr>
                </table>
              </div>
            </div>
          </section>
          <section class="col-xs-12">
            <div class="content-box">
              <div class="table-responsive">
                <table class="table table-striped opnsense_standard_table_form">
                  <tr>
                    <td colspan="2"><strong><?=gettext("Normalizations");?></strong></td>
                  </tr>
                  <tr>
                      <td style="width:22%"><a id="help_for_maxmss" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Max mss"); ?></td>
                      <td style="width:78%">
                          <input name="max-mss" type="text" value="<?=$pconfig['max-mss'];?>" />
                          <div class="hidden" data-for="help_for_maxmss">
                            <?=gettext("Enforces a maximum MSS for matching TCP packets."); ?>
                          </div>
                      </td>
                  </tr>
                  <tr>
                      <td style="width:22%"><i class="fa fa-info-circle text-muted"></i> <?=gettext('TOS / DSCP'); ?></td>
                      <td style="width:78%">
                          <select name="set-tos" class="selectpicker" data-size="5" data-width="auto"  data-live-search="true">
<?php
                            foreach (filter_tos_values() as $tos_value => $tos_label): ?>
                            <option value="<?= $tos_value ?>" <?= $tos_value == $pconfig['set-tos'] ? 'selected="selected"' : '' ?>>
                                <?= $tos_label ?>
                            </option>
<?php
                            endforeach ?>
                          </select>
                      </td>
                  </tr>
                  <tr>
                      <td style="width:22%"><a id="help_for_minttl" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Minimum TTL') ?></td>
                      <td style="width:78%">
                          <input name="min-ttl" type="text" value="<?=$pconfig['min-ttl'];?>" />
                          <div class="hidden" data-for="help_for_minttl">
                            <?=gettext("Enforces a minimum TTL for matching IP packets."); ?>
                          </div>
                      </td>
                  </tr>
                  <tr>
                      <td style="width:22%"><a id="help_for_nodf" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Do not fragment"); ?></td>
                      <td style="width:78%">
                          <input name="no-df" type="checkbox" value="1" <?= !empty($pconfig['no-df']) ? "checked=\"checked\"" : ""; ?> />
                          <div class="hidden" data-for="help_for_nodf">
                            <?=gettext("Clears the dont-fragment bit from a matching IP packet."); ?>
                          </div>
                      </td>
                  </tr>
                  <tr>
                      <td style="width:22%"><a id="help_for_randomid" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Random ID') ?></td>
                      <td style="width:78%">
                          <input name="random-id" type="checkbox" value="1" <?= !empty($pconfig['random-id']) ? "checked=\"checked\"" : ""; ?> />
                          <div class="hidden" data-for="help_for_randomid">
                            <?=gettext("Replaces the IP identification field with random values to compensate for ".
                                       "predictable values generated by many hosts. This option only applies to packets ".
                                       "that are not fragmented after the optional fragment reassembly."); ?>
                          </div>
                      </td>
                  </tr>

                </table>
              </div>
            </div>
          </section>
          <section class="col-xs-12">
            <div class="content-box">
              <div class="table-responsive">
                <table class="table table-striped opnsense_standard_table_form">
<?php
                    $has_created_time = (isset($a_scrub[$id]['created']) && is_array($a_scrub[$id]['created']));
                    $has_updated_time = (isset($a_scrub[$id]['updated']) && is_array($a_scrub[$id]['updated']));
                    if ($has_created_time || $has_updated_time):
?>
                    <tr>
                      <td colspan="2"><strong><?=gettext("Rule Information");?></strong></td>
                    </tr>
<?php
                    if ($has_created_time): ?>
                    <tr>
                      <td style="width:22%"><?=gettext("Created");?></td>
                      <td style="width:78%">
                        <?= date(gettext('n/j/y H:i:s'), $a_scrub[$id]['created']['time']) ?> (<?= $a_scrub[$id]['created']['username'] ?>)
                      </td>
                    </tr>
<?php
                    endif;
                    if ($has_updated_time):?>
                    <tr>
                      <td><?=gettext("Updated");?></td>
                      <td>
                        <?= date(gettext('n/j/y H:i:s'), $a_scrub[$id]['updated']['time']) ?> (<?= $a_scrub[$id]['updated']['username'] ?>)
                      </td>
                    </tr>
<?php
                    endif;
                    endif; ?>
                    <tr>
                      <td>&nbsp;</td>
                      <td>
                        <input name="Submit" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save')); ?>" />
                        <input type="button" class="btn btn-default" value="<?=html_safe(gettext('Cancel'));?>" onclick="window.location.href='/firewall_scrub.php'" />
                      </td>
                    </tr>
                  </table>
                </div>
              </div>
            </section>
          </form>
        </div>
      </div>
    </section>
<?php include("foot.inc"); ?>
