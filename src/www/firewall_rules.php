<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2005 Scott Ullrich <sullrich@gmail.com>
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

/***********************************************************************************************************
 * format functions for this page
 ***********************************************************************************************************/
function firewall_rule_item_proto($filterent)
{
    // construct line ipprotocol
    if (isset($filterent['ipprotocol'])) {
        switch($filterent['ipprotocol']) {
            case "inet":
                $record_ipprotocol = "IPv4 ";
                break;
            case "inet6":
                $record_ipprotocol = "IPv6 ";
                break;
            case "inet46":
                $record_ipprotocol = "IPv4+6 ";
                break;
        }
    } else {
        // when ipprotocol is not set, pf would normally figure out the ip proto itself.
        // reconstruct ipproto depending on source/destination address.
        if (!empty($filterent['from']) && is_ipaddr(explode("/", $filterent['from'])[0])) {
            $record_ipprotocol = strpos($filterent['from'], ":") === false ? "IPv4 " :  "IPv6 ";
        } elseif (!empty($filterent['to']) && is_ipaddr(explode("/", $filterent['to'])[0])) {
            $record_ipprotocol = strpos($filterent['to'], ":") === false ? "IPv4 " :  "IPv6 ";
        } elseif (isset($filterent['source']['address'])
                    && is_ipaddr(explode("/", $filterent['source']['address'])[0])) {
            $record_ipprotocol = strpos($filterent['source']['address'], ":") === false ? "IPv4 " : "IPv6 ";
        } elseif (isset($filterent['destination']['address'])
                    && is_ipaddr(explode("/", $filterent['destination']['address'])[0])) {
            $record_ipprotocol = strpos($filterent['destination']['address'], ":") === false ? "IPv4 " : "IPv6 ";
        } else {
            $record_ipprotocol = "IPv4+6 ";
        }
    }
    $icmptypes = array(
      "" => gettext("any"),
      "echoreq" => gettext("Echo Request"),
      "echorep" => gettext("Echo Reply"),
      "unreach" => gettext("Destination Unreachable"),
      "squench" => gettext("Source Quench (Deprecated)"),
      "redir" => gettext("Redirect"),
      "althost" => gettext("Alternate Host Address (Deprecated)"),
      "routeradv" => gettext("Router Advertisement"),
      "routersol" => gettext("Router Solicitation"),
      "timex" => gettext("Time Exceeded"),
      "paramprob" => gettext("Parameter Problem"),
      "timereq" => gettext("Timestamp"),
      "timerep" => gettext("Timestamp Reply"),
      "inforeq" => gettext("Information Request (Deprecated)"),
      "inforep" => gettext("Information Reply (Deprecated)"),
      "maskreq" => gettext("Address Mask Request (Deprecated)"),
      "maskrep" => gettext("Address Mask Reply (Deprecated)")
    );
    $icmp6types = array(
      "" => gettext("any"),
      "unreach" => gettext("Destination unreachable"),
      "toobig" => gettext("Packet too big"),
      "timex" => gettext("Time exceeded"),
      "paramprob" => gettext("Invalid IPv6 header"),
      "echoreq" => gettext("Echo service request"),
      "echorep" => gettext("Echo service reply"),
      "groupqry" => gettext("Group membership query"),
      "listqry" => gettext("Multicast listener query"),
      "grouprep" => gettext("Group membership report"),
      "listenrep" => gettext("Multicast listener report"),
      "groupterm" => gettext("Group membership termination"),
      "listendone" => gettext("Multicast listener done"),
      "routersol" => gettext("Router solicitation"),
      "routeradv" => gettext("Router advertisement"),
      "neighbrsol" => gettext("Neighbor solicitation"),
      "neighbradv" => gettext("Neighbor advertisement"),
      "redir" => gettext("Shorter route exists"),
      "routrrenum" => gettext("Route renumbering"),
      "fqdnreq" => gettext("FQDN query"),
      "niqry" => gettext("Node information query"),
      "wrureq" => gettext("Who-are-you request"),
      "fqdnrep" => gettext("FQDN reply"),
      "nirep" => gettext("Node information reply"),
      "wrurep" => gettext("Who-are-you reply"),
      "mtraceresp" => gettext("mtrace response"),
      "mtrace" => gettext("mtrace messages")
    );
    if (isset($filterent['protocol']) && $filterent['protocol'] == 'icmp') {
        $result = $record_ipprotocol;
        $icmplabel = $icmptypes[$filterent['icmptype'] ?? ''] ?? $filterent['icmptype'];
        $result .= sprintf(
          '<span data-toggle="tooltip" title="ICMP type: %s">%s</span>',
          html_safe($icmplabel),
          isset($filterent['protocol']) ? strtoupper($filterent['protocol']) : '*'
        );
        return $result;
    } elseif (isset($filterent['protocol']) && $filterent['protocol'] == 'ipv6-icmp') {
        $result = $record_ipprotocol;
        $icmplabel = $icmp6types[$filterent['icmp6-type'] ?? ''] ?? $filterent['icmp6-type'];
        $result .= sprintf(
          '<span data-toggle="tooltip" title="ICMP6 type: %s">%s</span>',
          html_safe($icmplabel),
          isset($filterent['protocol']) ? strtoupper($filterent['protocol']) : '*'
        );
        return $result;
    } else {
        return $record_ipprotocol . (isset($filterent['protocol']) ? strtoupper($filterent['protocol']) : "*");
    }
}


function firewall_rule_item_icons($filterent)
{
    $result = "";
    if (empty($filterent['direction']) || $filterent['direction'] == "in") {
        $result .= sprintf(
            "<i class=\"fa fa-long-arrow-right fa-fw text-info\" data-toggle=\"tooltip\" title=\"%s\"></i>",
            gettext("in")
        );
    } elseif (!empty($filterent['direction']) && $filterent['direction'] == "out") {
        $result .= sprintf(
            "<i class=\"fa fa-long-arrow-left fa-fw\" data-toggle=\"tooltip\" title=\"%s\"></i>",
            gettext("out")
        );
    } else {
        $result .= sprintf(
            "<i class=\"fa fa-exchange fa-fw\" data-toggle=\"tooltip\" title=\"%s\"></i>",
            gettext("any")
        );
    }
    if (empty($filterent['floating']) && !isset($filterent['quick'])){
        $is_quick = true;
    } elseif (!empty($filterent['floating']) && !isset($filterent['quick'])) {
        $is_quick = false;
    } else {
        $is_quick = $filterent['quick'];
    }

    if ($is_quick) {
        $result .= sprintf(
            "<i class=\"fa fa-flash fa-fw text-warning\" data-toggle=\"tooltip\" title=\"%s\"></i>",
            gettext('first match')
        );
    } else {
      $result .= sprintf(
          "<i class=\"fa fa-flash fa-fw text-muted\" data-toggle=\"tooltip\" title=\"%s\"></i>",
          gettext('last match')
      );
    }

    return $result;
}

function firewall_rule_item_action($filterent)
{
    if (!isset($filterent['type']) && empty($filterent['disabled'])) {
        return "fa fa-play fa-fw text-success";
    } elseif (!isset($filterent['type']) && !empty($filterent['disabled'])) {
        return "fa fa-play fa-fw text-muted";
    } elseif ($filterent['type'] == "block" && empty($filterent['disabled'])) {
        return "fa fa-times fa-fw text-danger";
    } elseif ($filterent['type'] == "block" && !empty($filterent['disabled'])) {
        return "fa fa-times fa-fw text-muted";
    }  elseif ($filterent['type'] == "reject" && empty($filterent['disabled'])) {
        return "fa fa-times-circle fa-fw text-danger";
    }  elseif ($filterent['type'] == "reject" && !empty($filterent['disabled'])) {
        return "fa fa-times-circle fa-fw text-muted";
    } elseif (empty($filterent['disabled'])) {
        return "fa fa-play fa-fw text-success";
    } else {
        return "fa fa-play fa-fw text-muted";
    }
}

function firewall_rule_item_log($filterent)
{
    if (!empty($filterent['log'])) {
        return "fa fa-info-circle fa-fw text-info";
    } else {
        return "fa fa-info-circle fa-fw text-muted";
    }
}

function filter_rule_item_alias_tooltip($alias_name)
{
    $alias_descr = htmlspecialchars(get_alias_description($alias_name));
    $alias_name = htmlspecialchars($alias_name);
    $result = "<span title=\"{$alias_descr}\" data-toggle=\"tooltip\"  data-html=\"true\">";
    $result .= $alias_name . "&nbsp;";
    $result .= "</span>";
    $result .= "<a href=\"/ui/firewall/alias/index/{$alias_name}\"";
    $result .= "  title=\"". gettext("edit alias") ."\" data-toggle=\"tooltip\">";
    $result .= "<i class=\"fa fa-list\"></i>";
    $result .= "</a>";
    return $result;
}

function filter_rule_address($adr) {
  $specialnets = get_specialnets();
  if (isset($adr['any'])) {
      $padr = "*";
  } else {
      $result = [];
      $items = isset($adr['network']) ? $adr['network'] : ($adr['address'] ?? '');
      foreach (explode(',', $items) as $item) {
          if (is_alias($item)) {
            $result[] = filter_rule_item_alias_tooltip($item);
          } elseif(isset($specialnets[$item])) {
            $result[] = $specialnets[$item];
          } else {
            $result[] = $item;
          }
      }
      $tmp = array_slice($result, 0, 5);
      if (count($result) > 5) {
        $tmp[] = '[...]';
      }
      $padr = implode(' , ', $tmp);
  }


  if (isset($adr['not'])) {
      $padr = "! " . $padr;
  }

  return $padr;
}


/***********************************************************************************************************
 *
 ***********************************************************************************************************/

$a_filter = &config_read_array('filter', 'rule');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_GET['if'])) {
        $current_if = htmlspecialchars($_GET['if']);
    } else {
        $current_if = "FloatingRules";
    }
    $pconfig = $_POST;
    if (isset($pconfig['id']) && isset($a_filter[$pconfig['id']])) {
        // id found and valid
        $id = $pconfig['id'];
    }
    if (isset($pconfig['act']) && $pconfig['act'] == "apply") {
        system_cron_configure();
        filter_configure();
        clear_subsystem_dirty('filter');
        $savemsg = get_std_save_message();
    } elseif (isset($pconfig['act']) && $pconfig['act'] == 'del' && isset($id)) {
        // delete single item
        if (!empty($a_filter[$id]['associated-rule-id'])) {
            // unlink nat entry
            if (isset($config['nat']['rule'])) {
                $a_nat = &config_read_array('nat', 'rule');
                foreach ($a_nat as &$natent) {
                    if ($natent['associated-rule-id'] == $a_filter[$id]['associated-rule-id']) {
                        $natent['associated-rule-id'] = '';
                    }
                }
            }
        }
        unset($a_filter[$id]);
        write_config();
        mark_subsystem_dirty('filter');
        header(url_safe('Location: /firewall_rules.php?if=%s', array($current_if)));
        exit;
    } elseif (isset($pconfig['act']) && $pconfig['act'] == 'del_x' && isset($pconfig['rule']) && count($pconfig['rule']) > 0) {
        // delete selected rules
        foreach ($pconfig['rule'] as $rulei) {
            // unlink nat entry
            if (isset($config['nat']['rule'])) {
                $a_nat = &config_read_array('nat', 'rule');
                foreach ($a_nat as &$natent) {
                    if ($natent['associated-rule-id'] == $a_filter[$rulei]['associated-rule-id']) {
                        $natent['associated-rule-id'] = '';
                    }
                }
            }
            unset($a_filter[$rulei]);
        }
        write_config();
        mark_subsystem_dirty('filter');
        header(url_safe('Location: /firewall_rules.php?if=%s', array($current_if)));
        exit;
    } elseif (isset($pconfig['act']) && in_array($pconfig['act'], array('toggle_enable', 'toggle_disable')) && isset($pconfig['rule']) && count($pconfig['rule']) > 0) {
        foreach ($pconfig['rule'] as $rulei) {
            $a_filter[$rulei]['disabled'] = $pconfig['act'] == 'toggle_disable';
        }
        write_config();
        mark_subsystem_dirty('filter');
        header(url_safe('Location: /firewall_rules.php?if=%s', array($current_if)));
        exit;
    } elseif ( isset($pconfig['act']) && $pconfig['act'] == 'move' && isset($pconfig['rule']) && count($pconfig['rule']) > 0) {
        // move selected rules
        if (!isset($id)) {
            // if rule not set/found, move to end
            $id = count($a_filter);
        }
        $a_filter = legacy_move_config_list_items($a_filter, $id,  $pconfig['rule']);
        write_config();
        mark_subsystem_dirty('filter');
        header(url_safe('Location: /firewall_rules.php?if=%s', array($current_if)));
        exit;
    } elseif (isset($pconfig['act']) && $pconfig['act'] == 'toggle' && isset($id)) {
        // toggle item
        if(isset($a_filter[$id]['disabled'])) {
            unset($a_filter[$id]['disabled']);
        } else {
            $a_filter[$id]['disabled'] = true;
        }
        write_config();
        mark_subsystem_dirty('filter');
        $response = array("id" => $id);
        $response["new_label"] = !isset($a_filter[$id]['disabled']) ?  gettext("Disable Rule") : gettext("Enable Rule");
        $response["new_state"] = !isset($a_filter[$id]['disabled']) ;
        echo json_encode($response);
        exit;
    } elseif (isset($pconfig['act']) && $pconfig['act'] == 'log' && isset($id)) {
        // toggle logging
        if(isset($a_filter[$id]['log'])) {
            unset($a_filter[$id]['log']);
        } else {
            $a_filter[$id]['log'] = true;
        }
        write_config();
        mark_subsystem_dirty('filter');
        $response = array("id" => $id);
        $response["new_label"] = isset($a_filter[$id]['log']) ?  gettext("Disable Log") : gettext("Enable Log");
        $response["new_state"] = isset($a_filter[$id]['log']) ;
        echo json_encode($response);
        exit;
    }
}

$selected_if = 'FloatingRules';
if (isset($_GET['if'])) {
    $selected_if = htmlspecialchars($_GET['if']);
}

include("head.inc");

$a_filter_raw = config_read_array('filter', 'rule');
legacy_html_escape_form_data($a_filter);
?>
<body>
<script>
$( document ).ready(function() {
  // link delete buttons
  $(".act_delete").click(function(event){
    event.preventDefault();
    var id = $(this).attr("id").split('_').pop(-1);
    if (id != 'x') {
      // delete single
      BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
        title: "<?= gettext("Rules");?>",
        message: "<?=gettext("Do you really want to delete this rule?");?>",
        buttons: [{
                  label: "<?= gettext("No");?>",
                  action: function(dialogRef) {
                      dialogRef.close();
                  }}, {
                  label: "<?= gettext("Yes");?>",
                  action: function(dialogRef) {
                    $("#id").val(id);
                    $("#action").val("del");
                    $("#iform").submit()
                }
              }]
    });
    } else {
      // delete selected
      BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
        title: "<?= gettext("Rules");?>",
        message: "<?=gettext("Do you really want to delete the selected rules?");?>",
        buttons: [{
                  label: "<?= gettext("No");?>",
                  action: function(dialogRef) {
                      dialogRef.close();
                  }}, {
                  label: "<?= gettext("Yes");?>",
                  action: function(dialogRef) {
                    $("#id").val("");
                    $("#action").val("del_x");
                    $("#iform").submit()
                }
              }]
      });
    }
  });

  // enable/disable selected
  $(".act_toggle_enable").click(function(event){
    event.preventDefault();
    BootstrapDialog.show({
      type:BootstrapDialog.TYPE_DANGER,
      title: "<?= gettext("Rules");?>",
      message: "<?=gettext("Enable selected rules?");?>",
      buttons: [{
                label: "<?= gettext("No");?>",
                action: function(dialogRef) {
                    dialogRef.close();
                }}, {
                label: "<?= gettext("Yes");?>",
                action: function(dialogRef) {
                  $("#id").val("");
                  $("#action").val("toggle_enable");
                  $("#iform").submit()
              }
            }]
    });
  });
  $(".act_toggle_disable").click(function(event){
    event.preventDefault();
    BootstrapDialog.show({
      type:BootstrapDialog.TYPE_DANGER,
      title: "<?= gettext("Rules");?>",
      message: "<?=gettext("Disable selected rules?");?>",
      buttons: [{
                label: "<?= gettext("No");?>",
                action: function(dialogRef) {
                    dialogRef.close();
                }}, {
                label: "<?= gettext("Yes");?>",
                action: function(dialogRef) {
                  $("#id").val("");
                  $("#action").val("toggle_disable");
                  $("#iform").submit()
              }
            }]
    });
  });

  // link move buttons
  $(".act_move").click(function(event){
    event.preventDefault();
    var id = $(this).attr("id").split('_').pop(-1);
    $("#id").val(id);
    $("#action").val("move");
    $("#iform").submit();
  });

  // link apply button
  $("#btn_apply").click(function(event){
    event.preventDefault();
    $("#action").val("apply");
    $("#iform").submit();
  });

  // link toggle buttons
  $(".act_toggle").click(function(event){
      event.preventDefault();
      let target = $(this);
      target.addClass('fa-spinner fa-pulse');
      let id = target.attr("id").split('_').pop(-1);
      $.ajax("firewall_rules.php",{
          type: 'post',
          cache: false,
          dataType: "json",
          data: {'act': 'toggle', 'id': id},
          success: function(response) {
              target.prop('title', response['new_label']).tooltip('fixTitle').tooltip('hide');
              target.removeClass('fa-spinner fa-pulse');
              if (response['new_state']) {
                  target.removeClass('text-muted').addClass(target.hasClass('fa-play') ? 'text-success' : 'text-danger');
              } else {
                  target.removeClass('text-success text-danger').addClass('text-muted');
              }
              $("#fw-alert-box").removeClass("hidden");
              $(".fw-alert-messages").addClass("hidden");
              $("#fw-alert-changes").removeClass("hidden");
          },
          error: function () {
              target.removeClass('fa-spinner fa-pulse');
          }
      });
  });

   // link log buttons
  $(".act_log").click(function(event){
      event.preventDefault();
      let target = $(this);
      target.removeClass('fa-info-circle').addClass('fa-spinner fa-pulse');
      let id = target.attr("id").split('_').pop(-1);
      $.ajax("firewall_rules.php",{
          type: 'post',
          cache: false,
          dataType: "json",
          data: {'act': 'log', 'id': id},
          success: function(response) {
              target.prop('title', response['new_label']).tooltip('fixTitle').tooltip('hide');
              target.removeClass('fa-spinner fa-pulse').addClass('fa-info-circle');
              if (response['new_state']) {
                  target.removeClass('text-muted').addClass('text-info');
              } else {
                  target.removeClass('text-info').addClass('text-muted');
              }
              $("#fw-alert-box").removeClass("hidden");
              $(".fw-alert-messages").addClass("hidden");
              $("#fw-alert-changes").removeClass("hidden");
          },
          error: function () {
              target.removeClass('fa-spinner fa-pulse').addClass('fa-info-circle');
          }
      });
  });

  // watch scroll position and set to last known on page load
  watchScrollPosition();

  // select All
  $("#selectAll").click(function(){
      $(".rule_select:not(:disabled)").prop("checked", $(this).prop("checked"));
  });

  // move category block
  $("#category_block").detach().appendTo($(".page-content-head > .container-fluid > .list-inline"));
  $("#category_block").addClass("pull-right");

  $("#btn_inspect").click(function(){
      let mode = $(this).data('mode');
      if (mode === 'stats') {
            $(".view-stats").hide();
            $(".view-info").show();
            $(this).removeClass('active');
            $(this).data('mode', 'info');
      } else {
            $(".view-stats").show();
            $(".view-info").hide();
            $(this).addClass('active');
            $(this).data('mode', 'stats');
            $.ajax('api/firewall/filter_util/rule_stats', {
                success: function(response) {
                    if (response.status == 'ok') {
                        let fileSizeTypes = ["", "K", "M", "G", "T", "P", "E", "Z", "Y"];
                        $.each(response.stats, function(index, value) {
                            $("#" + index + "_evaluations").text(value.evaluations);
                            $("#" + index + "_states").text(value.states);
                            $("#" + index + "_packets").text(value.packets);
                            if (value.bytes > 0) {
                                let ndx = Math.floor(Math.log(value.bytes) / Math.log(1000));
                                $("#" + index + "_bytes").text(
                                    (value.bytes / Math.pow(1000, ndx)).toFixed(2) + ' ' + fileSizeTypes[ndx]
                                );
                            } else {
                                $("#" + index + "_bytes").text("0");
                            }
                        });
                    }
                }
            });
      }
      $(this).blur();
  });

  // hook category functionality
  hook_firewall_categories();

  // our usual zebra striping doesn't respect hidden rows, hook repaint on .opnsense-rules change() and fire initially
  $(".opnsense-rules > tbody > tr").each(function(){
      // save zebra color
      let tr_color = $(this).children(0).css("background-color");
      if (tr_color != 'transparent' && !tr_color.includes('(0, 0, 0')) {
          $("#fw_category").data('stripe_color', tr_color);
      }
  });
  $(".opnsense-rules").removeClass("table-striped");
  $(".opnsense-rules").change(function(){
      $(".opnsense-rules > tbody > tr:visible").each(function (index) {
          $(this).css("background-color", "inherit");
          if ( index % 2 == 0) {
              $(this).css("background-color", $("#fw_category").data('stripe_color'));
          }
      });
      $(".expand_type").each(function(){
          let tmp = $(this).data('type');
          if ($("tr."+tmp+"-rule").length > 0) {
              $("#expand-"+tmp+"-rules").show();
              $("#"+tmp+"-rule-count").text($("tr."+tmp+"-rule").length);
          }
          if ($(this).hasClass('is_collapsed')) {
              $("tr."+tmp+"-rule").hide();
          }
      });
  });

  $(".expand_type").each(function(){
      $("#expand-"+$(this).data('type')).click(function(event){
          event.preventDefault();
          $(this).parent().parent().toggleClass('is_collapsed');
          // trigger category change as this will show/hide all "rule" rows (and fires an event on .opnsense-rules )
          $("#fw_category").change();
      });
  });

  // tooltip interface list
  $(".interface_tooltip").tooltip({
      html: true,
      placement: 'bottom',
      title: function(){
<?php
        $iflist = [];
        foreach (legacy_config_get_interfaces() as $intf => $payload) {
          $iflist[$intf] = $payload['descr'];
        }
        echo "    let descriptions = JSON.parse('". json_encode($iflist) . "');";?>
          let this_interfaces = [];
          $.each($(this).data('interfaces').split(','), function(idx, intf) {
              if (descriptions[intf]) {
                  this_interfaces.push(descriptions[intf]);
              } else {
                  // strikeout non existing interface (show, but mark)
                  this_interfaces.push('<s>'+intf+'</s>');
              }
          });
          return this_interfaces.join('<br/>');
      }
  });
  //
});
</script>
<style>
    .view-stats {
        display: none;
    }
    .button-th {
        width: 150px;
    }
    .opnsense-rules > tbody > tr > td {
        padding-left:5px;
        padding-right:5px;
    }

    .expand_type {
        font-style: italic;
    }
</style>

<?php include("fbegin.inc"); ?>
  <div class="hidden">
    <div id="category_block" style="z-index:-100;">
        <select class="selectpicker hidden-xs hidden-sm hidden-md" data-live-search="true" data-size="5"  multiple title="<?=gettext("Select category");?>" id="fw_category">
        </select>
        <button id="btn_inspect" class="btn btn-default">
          <i class="fa fa-eye" aria-hidden="true"></i>
          <?=gettext("Inspect");?>
        </button>
    </div>
  </div>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php print_firewall_banner() ?>
        <div id="fw-alert-box" class="col-xs-12 <?=!is_subsystem_dirty('filter') && !isset($savemsg) ? "hidden":"";?>">
          <div class="alert alert-info" role="alert">
            <div id="fw-alert-changes" class="fw-alert-messages <?=!is_subsystem_dirty('filter') ? "hidden":"";?>">
                <label for="btn_apply">
                  <?=gettext("The firewall rule configuration has been changed.<br />You must apply the changes in order for them to take effect.");?>
                </label>
                <button id="btn_apply" class="btn btn-primary pull-right" value="Apply changes"><?=gettext("Apply changes");?></button>
            </div>
            <div id="fw-alert-message" class="fw-alert-messages <?=!isset($savemsg) ? "hidden":"";?>">
                <?=isset($savemsg) ? $savemsg : "";?>
            </div>
          </div>
        </div>
<?php
          $interface_has_rules = false;
          foreach ($a_filter as $i => $filterent) {
            if ((!isset($filterent['floating']) && $selected_if == $filterent['interface']) ||
              ((isset($filterent['floating']) || empty($filterent['interface'])) && $selected_if == 'FloatingRules')) {
              $interface_has_rules = true;
              break;
            }
          } ?>
<?php if (!$interface_has_rules): ?>
<?php if ($selected_if == 'FloatingRules'): ?>
        <?php print_info_box(gettext('No floating rules are currently defined. Floating rules are ' .
          'not bound to a single interface and can therefore be used to span ' .
          'policies over multiple networks at the same time.')) ?>
<?php else: ?>
        <?php print_info_box(sprintf(gettext('No %s rules are currently defined. All incoming connections ' .
          'on this interface will be blocked until you add a pass rule. Exceptions for automatically generated ' .
          'rules may apply.'),
          !empty($config['interfaces'][$selected_if]['descr']) ?
          $config['interfaces'][$selected_if]['descr'] : strtoupper($selected_if))) ?>
<?php endif ?>
<?php endif ?>
        <section class="col-xs-12">
          <div class="content-box">
            <form action="firewall_rules.php?if=<?=$selected_if;?>" method="post" name="iform" id="iform">
              <input type="hidden" id="id" name="id" value="" />
              <input type="hidden" id="action" name="act" value="" />
              <div class="table-responsive">
                <table class="table table-condensed table-striped opnsense-rules">
                  <tbody>
                    <tr>
                      <td><input type="checkbox" id="selectAll"></td>
                      <td>&nbsp;</td>
                      <td class="view-info"><strong><?= gettext('Protocol') ?></strong></td>
                      <td class="view-info"><strong><?= gettext('Source') ?></strong></td>
                      <td class="view-info hidden-xs hidden-sm"><strong><?= gettext('Port') ?></strong></td>
                      <td class="view-info hidden-xs hidden-sm"><strong><?= gettext('Destination') ?></strong></td>
                      <td class="view-info hidden-xs hidden-sm"><strong><?= gettext('Port') ?></strong></td>
                      <td class="view-info hidden-xs hidden-sm"><strong><?= gettext('Gateway') ?></strong></td>
                      <td class="view-info hidden-xs hidden-sm"><strong><?= gettext('Schedule') ?></strong></td>
                      <td class="view-info">
                          <i class="fa fa-fw fa-sitemap"  data-toggle="tooltip" title="<?= html_safe(gettext('Number of interfaces this rule applies to'));?>"></i>
                      </td>
                      <td class="view-stats hidden-xs hidden-sm"><strong><?= gettext('Evaluations') ?></strong></td>
                      <td class="view-stats hidden-xs hidden-sm"><strong><?= gettext('States') ?></strong></td>
                      <td class="view-stats"><strong><?= gettext('Packets') ?></strong></td>
                      <td class="view-stats"><strong><?= gettext('Bytes') ?></strong></td>
                      <td class="text-nowrap">
                        <strong><?= gettext('Description') ?></strong>
                        <i class="fa fa-question-circle" data-toggle="collapse" data-target=".rule_md5_hash" ></i>
                      </td>
                      <td class="text-nowrap button-th">
                        <a href="<?= url_safe('firewall_rules_edit.php?if=%s', array($selected_if)) ?>" class="btn btn-primary btn-xs" data-toggle="tooltip" title="<?= html_safe(gettext('Add')) ?>" aria-label="<?= html_safe(gettext('Add')) ?>">
                          <i class="fa fa-plus fa-fw"></i>
                        </a>
                        <button id="move_<?= count($a_filter) ?>" name="move_<?= count($a_filter) ?>_x" data-toggle="tooltip" title="<?= html_safe(gettext('Move selected rules to end')) ?>" class="act_move btn btn-default btn-xs" aria-label="<?=gettext("Move selected rules to end"); ?>">
                          <i class="fa fa-arrow-left fa-fw"></i>
                        </button>
                        <button id="del_x" title="<?= html_safe(gettext('Delete selected')) ?>" data-toggle="tooltip" class="act_delete btn btn-default btn-xs" aria-label="<?=gettext("delete selected"); ?>">
                          <i class="fa fa-trash fa-fw"></i>
                        </button>
                        <button title="<?= html_safe(gettext('Enable selected')) ?>" data-toggle="tooltip" class="act_toggle_enable btn btn-default btn-xs" aria-label="<?=gettext("Enable selected"); ?>">
                          <i class="fa fa-check-square-o fa-fw"></i>
                        </button>
                        <button title="<?= html_safe(gettext('Disable selected')) ?>" data-toggle="tooltip" class="act_toggle_disable btn btn-default btn-xs">
                          <i class="fa fa-square-o fa-fw"></i>
                        </button>
                      </td>
                  </tr>
<?php
                $ifgroups = [];
                foreach (config_read_array('ifgroups', 'ifgroupentry') as $ifgroup) {
                    if (!empty($ifgroup['members']) && in_array($selected_if, preg_split('/[ |,]+/', $ifgroup['members']))) {
                        $ifgroups[] = $ifgroup['ifname'];
                    }
                }
                $fw = filter_core_get_initialized_plugin_system();
                filter_core_bootstrap($fw);
                plugins_firewall($fw);
                filter_core_rules_user($fw);
                $prev_origin = null;
                $origin_texts = [
                    'internal' => gettext('Automatically generated rules'),
                    'floating' => gettext('Floating rules'),
                    'group' => gettext('Group rules'),
                    'internal2' => gettext('Automatically generated rules (end of ruleset)'),
                    'automation'  => gettext('Rules from Automation'),
                ];
                foreach ($fw->iterateFilterRules() as $rule):
                    $is_selected = false;
                    if (empty($ifgroups) && $rule->ruleOrigin() == 'group'){
                        // group view, skip group section (groups can't be nested)
                        $is_selected = false;
                    } elseif ($rule->getInterface() == $selected_if && empty($rule->getRawRule()['interfacenot'])) {
                        // interface view and this interface is selected
                        $is_selected = true;
                    } elseif ($selected_if == "FloatingRules" && $rule->ruleOrigin() == 'floating') {
                        // floating view, skip floating
                        $is_selected = false;
                    } elseif (!empty($rule->getRawRule()['interfacenot'])) {
                        // inverted interface, all but selected
                        if (!in_array($selected_if, explode(',', $rule->getInterface()))) {
                            $is_selected = true;
                        }
                    } elseif (($rule->getInterface() == "" || strpos($rule->getInterface(), ",") !== false) && $selected_if == "FloatingRules") {
                        // floating type of rule and "floating" view
                        $is_selected = true;
                    } elseif ($rule->getInterface() == "" || !empty(array_intersect(array_merge([$selected_if], $ifgroups), explode(',', $rule->getInterface())))) {
                        // rule is floating or of group type and matches this interface
                        $is_selected = true;
                    }
                    if ($rule->isEnabled() && $is_selected && $rule->ruleOrigin() != 'interface'):
                        $intf_count = empty($rule->getInterface()) ? '*' : count(explode(',', $rule->getInterface()));
                        $filterent = $rule->getRawRule();
                        $filterent['quick'] = !isset($filterent['quick']) || $filterent['quick'];
                        legacy_html_escape_form_data($filterent);
?>
<?php
                    if ($prev_origin != $rule->ruleOrigin()):?>
                    <tr id="expand-<?=$rule->ruleOrigin();?>-rules" class="expand_type is_collapsed" data-type="<?=$rule->ruleOrigin();?>" style="display: none;">
                        <td><i class="fa fa-folder-o text-muted"></i></td>
                        <td></td>
                        <td class="view-info" colspan="2"> </td>
                        <td class="view-info hidden-xs hidden-sm" colspan="5"> </td>
                        <td colspan="2" class="view-stats hidden-xs hidden-sm"></td>
                        <td colspan="2" class="view-stats"></td>
                        <td class="view-info"></td>
                        <td><?= $origin_texts[$rule->ruleOrigin()] ?></td>
                        <td>
                            <button class="btn btn-default btn-xs" id="expand-<?=$rule->ruleOrigin();?>">
                              <i class="fa fa-chevron-circle-down" aria-hidden="true"></i>
                              <span class="badge">
                                <span id="<?=$rule->ruleOrigin();?>-rule-count"><span>
                              </span>
                            </button>
                        </td>
                    </tr>
<?php
                    endif;?>
                    <tr class="rule <?=$rule->ruleOrigin();?>-rule" style="display: none;" data-category="<?=!empty($filterent['category']) ? $filterent['category'] : "";?>">
                      <td><i class="fa fa-magic"></i></td>
                      <td>
                          <span class="<?=firewall_rule_item_action($filterent);?>"></span>
                          <?=firewall_rule_item_icons($filterent);?>
                          <i class="<?=firewall_rule_item_log($filterent);?>"></i>
                      </td>
                      <td class="view-info">
                          <?=firewall_rule_item_proto($filterent);?>
                      </td>
                      <td class="view-info">
                          <?=$rule->isUIFromNot() ? '!' : '';?>
                          <?= is_alias($rule->getUIFromAddress()) ? filter_rule_item_alias_tooltip($rule->getUIFromAddress()) : htmlspecialchars($rule->getUIFromAddress());?>
                      </td>
                      <td class="view-info hidden-xs hidden-sm">
                        <?= is_alias($rule->getUIFromPort()) ? filter_rule_item_alias_tooltip($rule->getUIFromPort()) : htmlspecialchars(pprint_port($rule->getUIFromPort()));?>
                      </td>
                      <td class="view-info hidden-xs hidden-sm">
                          <?=$rule->isUIToNot() ? '!' : '';?>
                          <?= is_alias($rule->getUIToAddress()) ? filter_rule_item_alias_tooltip($rule->getUIToAddress()) : htmlspecialchars($rule->getUIToAddress());?>
                      </td>
                      <td class="view-info hidden-xs hidden-sm">
                          <?= is_alias($rule->getUIToPort()) ? filter_rule_item_alias_tooltip($rule->getUIToPort()) : htmlspecialchars(pprint_port($rule->getUIToPort()));?>
                      </td>
                      <td class="view-info hidden-xs hidden-sm">
                        <?= $rule->getUIGateway();?>
                      </td>
                      <td class="view-info hidden-xs hidden-sm">*</td>
                      <td class="view-info">
                          <?php if ($intf_count == '*'):?>
                              <a style="cursor: pointer;" title="<?=html_safe(gettext('Affects all interfaces'));?>" data-placement='bottom' data-toggle="tooltip">
                                <?=$intf_count;?>
                              </a>
                          <?php elseif ($intf_count != '1' || $selected_if != $rule->getInterface() || $selected_if == 'FloatingRules'): ?>
                            <?= !empty($rule->getRawRule()['interfacenot']) ? '!' : '';?>
                            <a style="cursor: pointer;" class='interface_tooltip' data-interfaces="<?=$rule->getInterface();?>">
                              <?=$intf_count;?>
                            </a>
                          <?php endif; ?>
                      </td>
                      <td class="view-stats hidden-xs hidden-sm" id="<?=$rule->getLabel();?>_evaluations"><?= gettext('N/A') ?></td>
                      <td class="view-stats hidden-xs hidden-sm">
                          <a href="/ui/diagnostics/firewall/states#<?=html_safe($rule->getLabel());?>" id="<?=$rule->getLabel();?>_states" data-toggle="tooltip" title="<?=html_safe("open states view");?>" ><?=  gettext('N/A');?></a>
                      <td class="view-stats" id="<?=$rule->getLabel();?>_packets"><?= gettext('N/A') ?></td>
                      <td class="view-stats" id="<?=$rule->getLabel();?>_bytes"><?= gettext('N/A') ?></td>
                      <td class="rule-description">
                        <?=$rule->getDescr();?>
                        <div class="collapse rule_md5_hash">
                            <small><?=$filterent['label'];?></small>
                        </div>
                      </td>
                      <td>
<?php if (!empty($rule->getRef())): ?>
                          <a href="firewall_rule_lookup.php?rid=<?=html_safe($rule->getLabel());?>" class="btn btn-default btn-xs"><i class="fa fa-fw fa-search"></i></a>
<?php endif ?>
                      </td>
                    </tr>
<?php
                    $prev_origin = $rule->ruleOrigin();
                    endif;
                endforeach;?>
<?php
                foreach ($a_filter as $i => $filterent):
                if (
                    (!isset($filterent['floating']) && $selected_if == $filterent['interface']) ||
                     (
                        (isset($filterent['floating']) || empty($filterent['interface'])) &&
                        $selected_if == 'FloatingRules'
                     )
                ):
                  // calculate a hash so we can track these records in the ruleset, new style (mvc) code will
                  // automatically provide us with a uuid, this is a workaround to provide some help with tracking issues.
                  $rule_hash = OPNsense\Firewall\Util::calcRuleHash($a_filter_raw[$i]);
                  $intf_count = empty($filterent['interface']) ? '*' : count(explode(',', $filterent['interface']));
?>
                  <tr class="rule  <?=isset($filterent['disabled'])?"text-muted":"";?>" data-category="<?=!empty($filterent['category']) ? $filterent['category'] : "";?>">
                    <td>
                      <input class="rule_select" type="checkbox" name="rule[]" value="<?=$i;?>"  />
                    </td>
                    <td>
                      <i class="act_toggle <?=firewall_rule_item_action($filterent);?>" style="cursor: pointer;" id="toggle_<?=$i;?>" data-toggle="tooltip" title="<?= html_safe(empty($filterent['disabled']) ? gettext('Disable rule') : gettext('Enable rule')) ?>"></i>
                      <?=firewall_rule_item_icons($filterent);?>
                      <i class="act_log <?= firewall_rule_item_log($filterent) ?>" style="cursor: pointer;" id="toggle_<?=$i;?>" data-toggle="tooltip" title="<?= html_safe(empty($filterent['log']) ? gettext('Enable logging') : gettext('Disable logging')) ?>"></i>
                    </td>
                    <td class="view-info">
                        <?=firewall_rule_item_proto($filterent);?>
                    </td>
                    <td class="view-info">
                      <?=filter_rule_address($filterent['source']);?>
                    </td>

                    <td class="view-info hidden-xs hidden-sm">
<?php                 if (isset($filterent['source']['port']) && is_alias($filterent['source']['port'])): ?>
                        <?=filter_rule_item_alias_tooltip($filterent['source']['port']);?>
<?php                 else: ?>
                        <?=htmlspecialchars(pprint_port(isset($filterent['source']['port']) ? $filterent['source']['port'] : null)); ?>
<?php                 endif; ?>
                    </td>

                    <td class="view-info hidden-xs hidden-sm">
                      <?=filter_rule_address($filterent['destination']);?>
                    </td>

                    <td class="view-info hidden-xs hidden-sm">
<?php                 if (isset($filterent['destination']['port']) && is_alias($filterent['destination']['port'])): ?>
                        <?=filter_rule_item_alias_tooltip($filterent['destination']['port']);?>
<?php                 else: ?>
                        <?=htmlspecialchars(pprint_port(isset($filterent['destination']['port']) ? $filterent['destination']['port'] : null)); ?>
<?php                 endif; ?>
                    </td>

                    <td class="view-info hidden-xs hidden-sm">
<?php
                       if (isset($filterent['gateway'])):?>
                      <?=isset($config['interfaces'][$filterent['gateway']]['descr']) ? htmlspecialchars($config['interfaces'][$filterent['gateway']]['descr']) : htmlspecialchars(pprint_port($filterent['gateway'])); ?>
<?php
                      else: ?>
                      *
<?php                 endif; ?>
                    </td>
                    <td class="view-info hidden-xs hidden-sm">
<?php
                      if (!empty($filterent['sched'])):?>
<?php
                        $schedule_descr = "";
                        if (isset($config['schedules']['schedule']))
                        {
                            foreach ($config['schedules']['schedule'] as $schedule)
                            {
                                if ($schedule['name'] == $filterent['sched'])
                                {
                                    $schedule_descr = (isset($schedule['descr'])) ? $schedule['descr'] : "";
                                    break;
                                }
                            }
                        }
?>
                        <span title="<?=htmlspecialchars($schedule_descr);?>" data-toggle="tooltip">
                          <?=htmlspecialchars($filterent['sched']);?>&nbsp;
                        </span>
                        <a href="/firewall_schedule_edit.php?name=<?=htmlspecialchars($filterent['sched']);?>"
                            title="<?= html_safe(gettext('Edit')) ?>" data-toggle="tooltip">
<?php
                        if (filter_get_time_based_rule_status($schedule)):?>
                          <i class="fa fa-calendar text-success"></i>
<?php
                        else:?>
                          <i class="fa fa-calendar text-muted"></i>
<?php
                        endif;?>
                        </a>
<?php
                      else: ?>
                      *
<?php
                       endif;?>
                    </td>
                    <td class="view-info">
                      <?php if ($intf_count == '*'):?>
                          <a style="cursor: pointer;" title="<?=html_safe(gettext('Affects all interfaces'));?>" data-placement='bottom' data-toggle="tooltip">
                            <?=$intf_count;?>
                          </a>
                      <?php elseif ($intf_count != '1' || $selected_if == 'FloatingRules'): ?>
                        <?= !empty($filterent['interfacenot']) ? '!' : '';?>
                        <a style="cursor: pointer;" class='interface_tooltip' data-interfaces="<?=$filterent['interface'];?>">
                          <?=$intf_count;?>
                        </a>
                      <?php endif; ?>
                    </td>
                    <td class="view-stats hidden-xs hidden-sm" id="<?=$rule_hash;?>_evaluations"><?= gettext('N/A') ?></td>
                    <td class="view-stats hidden-xs hidden-sm">
                      <a href="/ui/diagnostics/firewall/states#<?=html_safe($rule_hash);?>" id="<?=$rule_hash;?>_states" data-toggle="tooltip" title="<?=html_safe("open states view");?>" ><?= gettext('N/A') ?></a>
                    </td>
                    <td class="view-stats" id="<?=$rule_hash;?>_packets"><?= gettext('N/A') ?></td>
                    <td class="view-stats" id="<?=$rule_hash;?>_bytes"><?= gettext('N/A') ?></td>
                    <td  class="rule-description">
                      <?= $filterent['descr'] ?? '' ?>
                      <div class="collapse rule_md5_hash">
                          <small><?=$rule_hash;?></small>
                      </div>
                    </td>
                    <td>
                      <button id="move_<?=$i;?>" name="move_<?=$i;?>_x" data-toggle="tooltip" title="<?= html_safe(gettext("Move selected rules before this rule")) ?>" class="act_move btn btn-default btn-xs" aria-label="<?= html_safe(gettext("Move selected rules before this rule")) ?>">
                        <i class="fa fa-arrow-left fa-fw"></i>
                      </button>
<?php if (empty($filterent['associated-rule-id'])): ?>
                      <a href="firewall_rules_edit.php?if=<?=$selected_if;?>&id=<?=$i;?>" data-toggle="tooltip" title="<?= html_safe(gettext('Edit')) ?>" aria-label="<?= html_safe(gettext('Edit')) ?>" class="btn btn-default btn-xs">
                        <i class="fa fa-pencil fa-fw"></i>
                      </a>
                      <a href="firewall_rules_edit.php?if=<?=$selected_if;?>&dup=<?=$i;?>" class="btn btn-default btn-xs" data-toggle="tooltip" title="<?= html_safe(gettext('Clone')) ?>" aria-label="<?= html_safe(gettext('Clone')) ?>">
                        <i class="fa fa-clone fa-fw"></i>
                      </a>
<?php endif ?>
                      <a id="del_<?=$i;?>" title="<?= html_safe(gettext('Delete')) ?>" data-toggle="tooltip"  class="act_delete btn btn-default btn-xs" aria-label="<?= html_safe(gettext('Delete')) ?>">
                        <i class="fa fa-trash fa-fw"></i>
                      </a>
                    </td>
                  </tr>
<?php
                  endif;
                  endforeach;
                  $i++;
?>
                </tbody>
              </table>
              <table class="table table-condensed table-striped opnsense-rules">
                <tbody>
                  <tr class="hidden-xs hidden-sm">
                    <td>
                      <table style="width:100%; border:0;">
                        <tr>
                          <td style="width:16px"><span class="fa fa-play text-success"></span></td>
                          <td style="width:100px"><?=gettext("pass");?></td>
                          <td style="width:14px"></td>
                          <td style="width:16px"><span class="fa fa-times text-danger"></span></td>
                          <td style="width:100px"><?=gettext("block");?></td>
                          <td style="width:14px"></td>
                          <td style="width:16px"><span class="fa fa-times-circle text-danger"></span></td>
                          <td style="width:100px"><?=gettext("reject");?></td>
                          <td style="width:14px"></td>
                          <td style="width:16px"><span class="fa fa-info-circle text-info"></span></td>
                          <td style="width:100px"><?=gettext("log");?></td>
                          <td style="width:16px"><span class="fa fa-long-arrow-right text-info"></span></td>
                          <td style="width:100px"><?=gettext("in");?></td>
                          <td style="width:16px"><span class="fa fa-flash text-warning"></span></td>
                          <td style="width:100px"><?=gettext("first match");?></td>
                        </tr>
                        <tr>
                          <td><span class="fa fa-play text-muted"></span></td>
                          <td class="nowrap"><?=gettext("pass (disabled)");?></td>
                          <td>&nbsp;</td>
                          <td><span class="fa fa-times text-muted"></span></td>
                          <td class="nowrap"><?=gettext("block (disabled)");?></td>
                          <td>&nbsp;</td>
                          <td><span class="fa fa-times-circle text-muted"></span></td>
                          <td class="nowrap"><?=gettext("reject (disabled)");?></td>
                          <td>&nbsp;</td>
                          <td style="width:16px"><span class="fa fa-info-circle text-muted"></span></td>
                          <td class="nowrap"><?=gettext("log (disabled)");?></td>
                          <td style="width:16px"><span class="fa fa-long-arrow-left"></span></td>
                          <td style="width:100px"><?=gettext("out");?></td>
                          <td style="width:16px"><span class="fa fa-flash text-muted"></span></td>
                          <td style="width:100px"><?=gettext("last match");?></td>
                        </tr>
                      </table>
                    </td>
                  </tr>
                  <tr class="hidden-xs hidden-sm">
                    <td>
                      <i class="fa fa-calendar fa-fw text-success"></i>
                      <i class="fa fa-calendar fa-fw text-muted"></i>
                      <?= gettext('Active/Inactive Schedule (click to view/edit)') ?></td>
                    </td>
                  </tr>
                  <tr class="hidden-xs hidden-sm">
                    <td>
                      <i class="fa fa-list fa-fw text-primary"></i>
                      <?= gettext('Alias (click to view/edit)') ?>
                    </td>
                  </tr>
                  <tr class="hidden-xs hidden-sm">
                    <td>
<?php if ('FloatingRules' != $selected_if): ?>
                      <?= sprintf(gettext('%s rules are evaluated on a first-match basis by default (i.e. ' .
                        'the action of the first rule to match a packet will be executed). ' .
                        'This means that if you use block rules, you will have to pay attention ' .
                        'to the rule order. Everything that is not explicitly passed is blocked ' .
                        'by default.'), !empty($config['interfaces'][$selected_if]['descr']) ?
                        $config['interfaces'][$selected_if]['descr'] : strtoupper($selected_if)) ?>
<?php else: ?>
                        <?= gettext('Floating rules are evaluated on a first-match basis (i.e. ' .
                        'the action of the first rule to match a packet will be executed) only ' .
                        'if the "quick" option is checked on a rule. Otherwise they will only apply if no ' .
                        'other rules match. Pay close attention to the rule order and options ' .
                        'chosen. If no rule here matches, the per-interface or default rules are used.') ?>
<?php endif ?>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </form>
        </div>
      </section>
    </div>
  </div>
</section>
<?php include("foot.inc"); ?>
