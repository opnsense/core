<?php

/*
    Copyright (C) 2014 Deciso B.V.
    Copyright (C) 2004 Scott Ullrich <sullrich@gmail.com>
    Copyright (C) 2009 Ermal Luçi
    Copyright (C) 2010 Jim Pingle <jimp@pfsense.org>
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

function update_alias_names_upon_change($section, $field, $new_alias_name, $origname, $field_separator=null)
{
    global $config;
    if (!empty($origname) && !empty($new_alias_name)) {
        // find section, return if not found
        $sectionref = &config_read_array();
        foreach ($section as $sectionname) {
            if (!empty($sectionref[$sectionname]) && is_array($sectionref[$sectionname])) {
                $sectionref = &$sectionref[$sectionname];
            } else {
                return;
            }
        }
        // traverse all found sections
        foreach($sectionref as $itemkey => $item) {
            // locate field within structure
            $fieldref = &$sectionref[$itemkey];
            foreach($field as $fieldname) {
                if (!empty($fieldref[$fieldname])) {
                    $fieldref = &$fieldref[$fieldname];
                } else {
                    unset($fieldref);
                    break;
                }
            }
            // if field is found, check and replace
            if (isset($fieldref) && !is_array($fieldref)) {
                if ($fieldref == $origname) {
                    $fieldref = $new_alias_name;
                } elseif ($field_separator != null) {
                    // field contains more then one value
                    $parts = explode($field_separator, $fieldref);
                    foreach ($parts as &$part) {
                        if ($part == $origname) {
                            $part = $new_alias_name;
                        }
                    }
                    $new_field_value = implode($field_separator, $parts);
                    if ($new_field_value != $fieldref) {
                        $fieldref = $new_field_value;
                    }
                }
            }
        }
    }
}

/**
 * generate simple country selection list for geoip
 */
function geoip_countries()
{
    $result = array();
    foreach (explode("\n", file_get_contents('/usr/local/opnsense/contrib/tzdata/iso3166.tab')) as $line) {
        $line = trim($line);
        if (strlen($line) > 3 && substr($line, 0, 1) != '#') {
          $code = substr($line, 0, 2);
          $name = trim(substr($line, 2, 9999));
          $result[$code] = $name;
        }
    }
    uasort($result, function($a, $b) {return strcasecmp($a, $b);});
    return $result;
}

function geoip_regions()
{
    $result = array();
    foreach (explode("\n", file_get_contents('/usr/local/opnsense/contrib/tzdata/zone.tab')) as $line) {
        if (strlen($line) > 0 && substr($line, 0, 1) == '#' ) {
            continue;
        }
        $line = explode("\t", $line);
        if (empty($line[0]) || strlen($line[0]) != 2) {
            continue;
        }
        if (empty($line[2]) || strpos($line[2], '/') === false) {
            continue;
        }
        $result[$line[0]] = explode('/', $line[2])[0];
    }
    return $result;
}

$a_aliases = &config_read_array('aliases', 'alias');

$pconfig = array();
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id']) && is_numericint($_GET['id']) && isset($a_aliases[$_GET['id']])) {
        $id = $_GET['id'];
        foreach (array("name", "detail", "address", "type", "descr", "updatefreq", "aliasurl", "url", "proto") as $fieldname) {
            if (isset($a_aliases[$id][$fieldname])) {
                $pconfig[$fieldname] = $a_aliases[$id][$fieldname];
            } else {
                $pconfig[$fieldname] = null;
            }
        }
        if (!empty($pconfig['updatefreq'])) {
            // split update frequency (ttl) in days and hours
            $pconfig['updatefreq_hours'] = round(((float)$pconfig['updatefreq'] - (int)$pconfig['updatefreq']) * 24, 2);
            $pconfig['updatefreq'] = (int)$pconfig['updatefreq'];
        }
    } elseif (isset($_GET['name'])) {
        // search alias by name
        foreach ($a_aliases as $alias_id => $alias_data) {
            if (strtolower($alias_data['name']) == strtolower(trim($_GET['name']))) {
                $id = $alias_id;
                break;
            }
        }
        // initialize form fields, when not found present empty form
        foreach (array("name", "detail", "address", "type", "descr", "updatefreq", "aliasurl", "url", "proto") as $fieldname) {
            if (isset($id) && isset($a_aliases[$id][$fieldname])) {
                $pconfig[$fieldname] = $a_aliases[$id][$fieldname];
            } else {
                $pconfig[$fieldname] = null;
            }
        }
    } else {
        // init empty
        $init_fields = array("name", "detail", "address", "type", "descr", "updatefreq", "url", "proto");
        foreach ($init_fields as $fieldname) {
            $pconfig[$fieldname] = null;
        }
    }
    // handle different detail input types
    if (!empty($pconfig['aliasurl'])) {
        $pconfig['host_url'] = is_array($pconfig['aliasurl']) ? $pconfig['aliasurl'] : array($pconfig['aliasurl']);
    } elseif (!empty($pconfig['url'])) {
        $pconfig['host_url'] = array($pconfig['url']);
    } elseif (!empty($pconfig['address'])) {
        $pconfig['host_url'] = explode(" ", $pconfig['address']);
    } else {
        $pconfig['host_url'] = array();
    }
    $pconfig['detail'] = !empty($pconfig['detail']) ? explode("||", $pconfig['detail']) : array();
    $pconfig['proto'] = !empty($pconfig['proto']) ? explode(',', $pconfig['proto']) : array("IPv4");
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    if (isset($_POST['id']) && is_numericint($_POST['id']) && isset($a_aliases[$_POST['id']])) {
        $id = $_POST['id'];
    }

    foreach ($pconfig['detail'] as &$detailDescr) {
        if (empty($detailDescr)) {
            $detailDescr = sprintf(gettext("Entry added %s"), date('r'));
        } else {
            // trim and strip pipes
            $detailDescr = trim(str_replace('|',' ' , $detailDescr));
        }
    }

    if (isset($pconfig['submit'])) {
        $input_errors = array();
        // validate data

        if (empty($pconfig['host_url'])) {
            $input_errors[] = gettext('At least one alias entry must be supplied.');
        } else {
            $country_codes = array_keys(geoip_countries());
            foreach ($pconfig['host_url'] as &$detail_entry) {
                $ipaddr_count = 0;
                $domain_alias_count = 0;
                foreach (explode('-', $detail_entry) as $tmpaddr) {
                    if (is_ipaddr($tmpaddr)) {
                        $ipaddr_count++;
                    } elseif (trim($tmpaddr) != "") {
                        $domain_alias_count++;
                    }
                }
                if ($pconfig['type'] == 'host') {
                    if ($ipaddr_count > 1) {
                        $input_errors[] = sprintf(gettext('Entry "%s" seems to contain a list of addresses, please use a network type alias to define ranges.'), $detail_entry);
                    } elseif (!is_domain($detail_entry) && !is_ipaddr($detail_entry) && !is_alias($detail_entry)) {
                        $input_errors[] = sprintf(gettext('Entry "%s" is not a valid hostname or IP address.'), $detail_entry);
                    }
                } elseif ($pconfig['type'] == 'port') {
                    $detail_entry = str_replace("-", ":", $detail_entry);
                    if (!is_port($detail_entry) && !is_portrange($detail_entry) && !is_alias($detail_entry)) {
                        $input_errors[] = sprintf(gettext('Entry "%s" is not a valid port number.'), $detail_entry);
                    }
                } elseif ($pconfig['type'] == 'geoip') {
                    if (!in_array($detail_entry, $country_codes)) {
                        $input_errors[] = sprintf(gettext('Entry "%s" is not a valid country code.'), $detail_entry);
                    }
                } elseif ($pconfig['type'] == 'network') {
                    if (!is_alias($detail_entry) && !is_ipaddr($detail_entry) && !is_subnet($detail_entry)
                      && !($ipaddr_count == 2 && $domain_alias_count == 0)) {
                        $input_errors[] = sprintf(gettext('Entry "%s" is not a valid network or IP address.'), $detail_entry);
                    }
                }
            }
        }

        /* Check for reserved keyword names */
        $reserved_keywords = array();

        if (isset($config['load_balancer']['lbpool'])) {
            foreach ($config['load_balancer']['lbpool'] as $lbpool) {
                $reserved_keywords[] = $lbpool['name'];
            }
        }

        $reserved_ifs = get_configured_interface_list(false, true);
        $reserved_keywords = array_merge($reserved_keywords, $reserved_ifs, $reserved_table_names);

        foreach ($reserved_keywords as $rk) {
            if ($rk == $pconfig['name']) {
                $input_errors[] = sprintf(gettext("Cannot use a reserved keyword as alias name %s"), $rk);
            }
        }

        /* check for name interface description conflicts */
        foreach ($config['interfaces'] as $interface) {
            if ($interface['descr'] == $pconfig['name']) {
                $input_errors[] = gettext("An interface description with this name already exists.");
                break;
            }
        }

        $valid = is_validaliasname($pconfig['name']);
        if ($valid === false) {
            $input_errors[] = sprintf(gettext('The name must be less than 32 characters long and may only consist of the following characters: %s'), 'a-z, A-Z, 0-9, _');
        } elseif ($valid === null) {
            $input_errors[] = sprintf(gettext('The name cannot be the internally reserved keyword "%s".'), $pconfig['name']);
        }

        foreach (array('updatefreq', 'updatefreq_hours') as $fieldname) {
            if (!empty($pconfig[$fieldname]) && !is_numeric($pconfig[$fieldname])) {
                $input_errors[] = gettext("Expiration should be a number");
                break;
            }
        }

        /* check for name conflicts */
        if (empty($a_aliases[$id])) {
            foreach ($a_aliases as $alias) {
                if ($alias['name'] == $_POST['name']) {
                    $input_errors[] = gettext("An alias with this name already exists.");
                    break;
                }
            }
        }

        /* user may not change type */
        if (isset($id) && $pconfig['type'] != $a_aliases[$id]['type']) {
            $input_errors[] = gettext("Alias type may not be changed for an existing alias.");
        }

        if ($pconfig['type'] == 'urltable') {
            if (empty($pconfig['host_url'][0]) || !is_URL($pconfig['host_url'][0])) {
                $input_errors[] = gettext("You must provide a valid URL.");
            }
        }

        if (count($input_errors) == 0) {
            // save to config
            $confItem = array();
            foreach (array("name", "type", "descr", "updatefreq") as $fieldname) {
                if (!empty($pconfig[$fieldname])) {
                    $confItem[$fieldname] = $pconfig[$fieldname];
                }
            }
            if (!empty($pconfig['updatefreq_hours'])) {
                // append hours
                $confItem['updatefreq'] = !empty($confItem['updatefreq']) ? $confItem['updatefreq'] : 0;
                $confItem['updatefreq'] +=  ((float)$pconfig['updatefreq_hours']) / 24;
            }
            // fix form type conversions ( list to string, as saved in config )
            // -- fill in default row description and make sure separators are removed
            if (strpos($pconfig['type'],'urltable') !== false) {
                $confItem['url'] = $pconfig['host_url'][0];
            } elseif (strpos($pconfig['type'],'url') !== false) {
                $confItem['aliasurl'] = $pconfig['host_url'];
            } else {
                $confItem['address'] = implode(' ', $pconfig['host_url']);
            }
            //
            $confItem['detail'] = implode('||', $pconfig['detail']);

            // proto is only for geoip selection
            if ($pconfig['type'] == 'geoip') {
                $confItem['proto'] = !empty($pconfig['proto']) ? implode(',', $pconfig['proto']) : array("IPv4");
            }

            /*   Check to see if alias name needs to be
             *   renamed on referenced rules and such
             */
            if (isset($id) && $pconfig['name'] <> $pconfig['origname']) {
                // Firewall rules
                $origname = $pconfig['origname'];
                update_alias_names_upon_change(array('filter', 'rule'), array('source', 'address'), $pconfig['name'], $origname);
                update_alias_names_upon_change(array('filter', 'rule'), array('destination', 'address'), $pconfig['name'], $origname);
                update_alias_names_upon_change(array('filter', 'rule'), array('source', 'port'), $pconfig['name'], $origname);
                update_alias_names_upon_change(array('filter', 'rule'), array('destination', 'port'), $pconfig['name'], $origname);
                // NAT Rules
                update_alias_names_upon_change(array('nat', 'rule'), array('source', 'address'), $pconfig['name'], $origname);
                update_alias_names_upon_change(array('nat', 'rule'), array('source', 'port'), $pconfig['name'], $origname);
                update_alias_names_upon_change(array('nat', 'rule'), array('destination', 'address'), $pconfig['name'], $origname);
                update_alias_names_upon_change(array('nat', 'rule'), array('destination', 'port'), $pconfig['name'], $origname);
                update_alias_names_upon_change(array('nat', 'rule'), array('target'), $pconfig['name'], $origname);
                update_alias_names_upon_change(array('nat', 'rule'), array('local-port'), $pconfig['name'], $origname);
                // NAT 1:1 Rules
                update_alias_names_upon_change(array('nat', 'onetoone'), array('destination', 'address'), $pconfig['name'], $origname);
                // NAT Outbound Rules
                update_alias_names_upon_change(array('nat', 'advancedoutbound', 'rule'), array('source', 'network'), $pconfig['name'], $origname);
                update_alias_names_upon_change(array('nat', 'advancedoutbound', 'rule'), array('sourceport'), $pconfig['name'], $origname);
                update_alias_names_upon_change(array('nat', 'advancedoutbound', 'rule'), array('destination', 'address'), $pconfig['name'], $origname);
                update_alias_names_upon_change(array('nat', 'advancedoutbound', 'rule'), array('dstport'), $pconfig['name'], $origname);
                update_alias_names_upon_change(array('nat', 'advancedoutbound', 'rule'), array('target'), $pconfig['name'], $origname);
                // Alias in an alias
                update_alias_names_upon_change(array('aliases', 'alias'), array('address'), $pconfig['name'], $origname, ' ');
            }


            // save to config
            if (isset($id)) {
                $a_aliases[$id] = $confItem;
            } else {
                $a_aliases[] = $confItem;
            }
            // Sort list
            $a_aliases = msort($a_aliases, "name");

            write_config();
            // post save actions
            mark_subsystem_dirty('aliases');

            header(url_safe('Location: /firewall_aliases.php'));
            exit;
        }
    }
}


legacy_html_escape_form_data($pconfig);

include("head.inc");

?>
<body>
<?php
  include("fbegin.inc");
?>
<script>
  $( document ).ready(function() {
    /**
     * remove host/port row or clear values on last entry
     */
    function removeRow() {
        if ( $('#detailTable > tbody > tr').length == 1 ) {
            $('#detailTable > tbody > tr:last > td > input').each(function(){
              $(this).val("");
            });
        } else {
            $(this).parent().parent().remove();
        }
    }

    /**
     * link alias typeahead to input, only return items not already on this form.
     */
    function addFieldTypeAhead() {
        $(".fld_detail").typeahead({
            source: document.all_aliases[$("#typeSelect").val()],
            matcher: function(item){
                var used = false;
                $(".fld_detail").each(function(){
                    if (item == $(this).val()) {
                        used = true;
                    }
                });
                if (used) {
                    return false;
                } else {
                    return ~item.toLowerCase().indexOf(this.query)
                }
            }
        });
    }

    // add new detail record
    $("#addNew").click(function(){
        // copy last row and reset values
        $('#detailTable > tbody').append('<tr>'+$('#detailTable > tbody > tr:last').html()+'</tr>');
        $('#detailTable > tbody > tr:last > td > input').each(function(){
          $(this).val("");
        });
        $(".act-removerow").click(removeRow);
        // link typeahead to new item
        addFieldTypeAhead();
    });

    $(".act-removerow").click(removeRow);

    function toggleType() {
      if ($("#typeSelect").val() == 'urltable' ) {
        $("#updatefreq").removeClass('hidden');
        $("#updatefreq_hours").removeClass('hidden');
        $("#updatefreqHeader").removeClass('hidden');
        $("#addNew").addClass('hidden');
        $('#detailTable > tbody > tr:gt(0)').remove();
        $('.act-removerow').addClass('hidden');
      } else if ($("#typeSelect").val() == 'urltable_ports') {
        $("#updatefreq").addClass('hidden');
        $("#updatefreq_hours").addClass('hidden');
        $("#updatefreqHeader").addClass('hidden');
        $("#addNew").addClass('hidden');
        $('#detailTable > tbody > tr:gt(0)').remove();
        $('.act-removerow').addClass('hidden');
      } else {
        $("#updatefreq").addClass('hidden');
        $("#updatefreq_hours").addClass('hidden');
        $("#updatefreqHeader").addClass('hidden');
        $("#addNew").removeClass('hidden');
        $('.act-removerow').removeClass('hidden');
      }
      $("#detailTable").removeClass('hidden');
      $("#proto").addClass("hidden");
      $(".geoip_table").addClass("hidden");
      $(".not_geoip_table").removeClass("hidden");
      $(".host_url").removeAttr("disabled");
      $(".geoip_item").attr("disabled", "disabled");
      switch($("#typeSelect").val()) {
          case 'urltable':
              $("#detailsHeading1").html("<?=gettext("URL");?>");
              break;
          case 'urltable_ports':
              $("#detailsHeading1").html("<?=gettext("URL");?>");
              break;
          case 'url':
              $("#detailsHeading1").html("<?=gettext("URL");?>");
              break;
          case 'url_ports':
              $("#detailsHeading1").html("<?=gettext("URL");?>");
              break;
          case 'host':
              $("#detailsHeading1").html("<?=gettext("Host(s)");?>");
              break;
          case 'network':
              $("#detailsHeading1").html("<?=gettext("Network(s)");?>");
              break;
          case 'port':
              $("#detailsHeading1").html("<?=gettext("Port(s)");?>");
              break;
          case 'geoip':
              $("#proto").removeClass("hidden");
              $(".geoip_table").removeClass("hidden");
              $(".not_geoip_table").addClass("hidden");
              $(".host_url").attr("disabled", "disabled");
              $(".geoip_item").removeAttr("disabled");
              break;
          case 'external':
              $("#detailTable").addClass('hidden');
              break;
      }
      $(".fld_detail").typeahead("destroy");
      addFieldTypeAhead();
    }

    $("#typeSelect").change(function(){
        toggleType();
    });

    // collect all known aliases per type
    document.all_aliases = {};
    $("#aliases > option").each(function(){
        if (document.all_aliases[$(this).data('type')] == undefined) {
            document.all_aliases[$(this).data('type')] = [];
        }
        document.all_aliases[$(this).data('type')].push($(this).val())
    });

    $('.region_toggle').click(function () {
        $('.region_' + $(this).attr('data')).click();
    });

    toggleType();
  });
</script>
<!-- push all available (nestable) aliases in a hidden select box -->
<select class="hidden" id="aliases">
<?php
    if (!empty($config['aliases']['alias'])):
      foreach ($config['aliases']['alias'] as $alias):
        if ($alias['type'] == 'network' || $alias['type'] == 'host' || $alias['type'] == 'port'):?>
        <option data-type="<?=$alias['type'];?>" value="<?=$alias['name'];?>"></option>
<?php
        endif;
      endforeach;
    endif;
?>
</select>

  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
<?php  if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
        <section class="col-xs-12">
          <div class="content-box tab-content">
            <form method="post" name="iform" id="iform">
              <table class="table table-striped opnsense_standard_table_form">
                <tr>
                  <td style="width:22%"><strong><?=gettext("Alias Edit");?></strong></td>
                  <td style="width:78%; text-align:right">
                    <small><?=gettext("full help"); ?> </small>
                    <i class="fa fa-toggle-off text-danger" style="cursor: pointer;" id="show_all_help_page"></i>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_name" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Name"); ?></td>
                  <td>
                    <input name="origname" type="hidden" id="origname" class="form-control unknown" size="40" value="<?=$pconfig['name'];?>" />
                    <?php if (isset($id)): ?>
                      <input name="id" type="hidden" value="<?=$id;?>" />
                    <?php endif; ?>
                    <input name="name" type="text" id="name" class="form-control unknown" size="40" maxlength="31" value="<?=$pconfig['name'];?>" />
                    <output class="hidden" for="help_for_name">
                      <?=gettext('The name of the alias may only consist of the characters "a-z, A-Z, 0-9 and _". Aliases can be nested using this name.'); ?>
                    </output>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_description" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description"); ?></td>
                  <td>
                    <input name="descr" type="text" class="form-control unknown" id="descr" size="40" value="<?=$pconfig['descr'];?>" />
                    <output class="hidden" for="help_for_description">
                      <?=gettext("You may enter a description here for your reference (not parsed)."); ?>
                    </output>
                  </td>
                </tr>
                <tr>
                  <td><a id="help_for_type" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Type"); ?></td>
                  <td>
                    <select  name="type" class="form-control" id="typeSelect">
                      <option value="host" <?=$pconfig['type'] == "host" ? "selected=\"selected\"" : ""; ?>><?=gettext("Host(s)"); ?></option>
                      <option value="network" <?=$pconfig['type'] == "network" ? "selected=\"selected\"" : ""; ?>><?=gettext("Network(s)"); ?></option>
                      <option value="port" <?=$pconfig['type'] == "port" ? "selected=\"selected\"" : ""; ?>><?=gettext("Port(s)"); ?></option>
                      <option value="url" <?=$pconfig['type'] == "url" ? "selected=\"selected\"" : ""; ?>><?=gettext("URL (IPs)");?></option>
                      <option value="url_ports" <?=$pconfig['type'] == "url_ports" ? "selected=\"selected\"" : ""; ?>><?=gettext("URL (Ports)");?></option>
                      <option value="urltable" <?=$pconfig['type'] == "urltable" ? "selected=\"selected\"" : ""; ?>><?=gettext("URL Table (IPs)"); ?></option>
                      <option value="urltable_ports" <?=$pconfig['type'] == "urltable_ports" ? "selected=\"selected\"" : ""; ?>><?=gettext("URL Table (Ports)"); ?></option>
                      <option value="geoip" <?=$pconfig['type'] == "geoip" ? "selected=\"selected\"" : ""; ?>><?=gettext("GeoIP"); ?></option>
                      <option value="external" <?=$pconfig['type'] == "external" ? "selected=\"selected\"" : ""; ?>><?=gettext("External (advanced)"); ?></option>
                    </select>
                    <div id="proto" class="hidden">
                      <small><?=gettext("Protocol");?></small><br/>
                      <select name="proto[]" multiple="multiple" class="selectpicker">
                        <option value="IPv4" <?= in_array("IPv4", $pconfig['proto']) ? "selected=\"selected\"" : ""; ?>><?=gettext("IPv4");?></option>
                        <option value="IPv6" <?= in_array("IPv6", $pconfig['proto']) ? "selected=\"selected\"" : ""; ?>><?=gettext("IPv6");?></option>
                      </select>
                    </div>
                    <output class="hidden" for="help_for_type">
                      <span class="text-info">
                        <?=gettext("Hosts")?><br/>
                      </span>
                      <small>
                        <?=gettext("Enter as many hosts as you would like. Hosts must be specified by their IP address or fully qualified domain name (FQDN). FQDN hostnames are periodically re-resolved and updated. If multiple IPs are returned by a DNS query, all are used.");?>
                        <br/>
                      </small>
                      <span class="text-info">
                        <?=gettext("Networks")?><br/>
                      </span>
                      <small>
                        <?=gettext("Networks are specified in CIDR format. Select the CIDR suffix that pertains to each entry. /32 specifies a single IPv4 host, /128 specifies a single IPv6 host, /24 in IPv4 corresponds to 255.255.255.0, /64 specifies commonly used IPv6 network, etc. Hostnames (FQDNs) may also be specified, using /32 for IPv4 and /128 for IPv6.");?>
                        <br/>
                      </small>
                      <span class="text-info">
                        <?=gettext("Ports")?><br/>
                      </span>
                      <small>
                        <?=gettext("Enter as many ports as you wish. Port ranges can be expressed by separating with a colon.");?>
                        <br/>
                      </small>
                      <span class="text-info">
                        <?=gettext("URLs")?><br/>
                      </span>
                      <small>
                        <?=gettext("Enter an URL containing a large number of IPs, ports or subnets. After saving the lists will be downloaded and scheduled for automatic updates when a frequency is provided.");?>
                        <br/>
                      </small>
                      <span class="text-info">
                        <?=gettext("GeoIP")?><br/>
                      </span>
                      <small>
                        <?=gettext("Select contries to be resolved to IPs. IPv4 and IPv6 can be mixed.");?>
                        <br/>
                      </small>
                      <span class="text-info">
                        <?=gettext("External (advanced)")?><br/>
                      </span>
                      <small>
                        <?=gettext("Managed externally, the contents of this alias type could be managed by other scripts or services. ".
                                  "OPNsense only makes sure the alias exists and leaves the contents alone");?>
                      </small>
                    </output>
                  </td>
                </tr>
                <tr>
                  <td><div id="addressnetworkport"><i class="fa fa-info-circle text-muted"></i> <?= gettext('Aliases') ?></div></td>
                  <td>
                    <table class="table table-striped table-condensed not_geoip_table" id="detailTable">
                      <thead>
                        <tr>
                          <th></th>
                          <th id="detailsHeading1"><?=gettext("Network"); ?></th>
                          <th id="detailsHeading3"><?=gettext("Description"); ?></th>
                          <th colspan="2" id="updatefreqHeader" ><?=gettext("Alias Expiration. (days + hours)");?></th>
                        </tr>
                      </thead>
                      <tbody>
<?php
                      foreach (!empty($pconfig['host_url']) ? $pconfig['host_url'] : array("") as $aliasid => $aliasurl):?>
                        <tr>
                          <td>
                            <div style="cursor:pointer;" class="act-removerow btn btn-default btn-xs" alt="remove"><span class="glyphicon glyphicon-minus"></span></div>
                          </td>
                          <td>
                            <input type="text" class="host_url fld_detail" name="host_url[]" value="<?=$aliasurl;?>"/>
                          </td>
                          <td>
                            <input type="text" class="form-control" name="detail[]" value="<?= isset($pconfig['detail'][$aliasid])?$pconfig['detail'][$aliasid]:"";?>">
                          </td>
                          <td>
<?php
                            if ($aliasid == 0):?>
                            <input type="text" class="form-control input-sm" id="updatefreq"  name="updatefreq" value="<?=$pconfig['updatefreq'];?>" >
<?php
                            endif;?>
                          </td>
                          <td>
<?php
                            if ($aliasid == 0):?>
                            <input type="text" class="form-control input-sm hidden" id="updatefreq_hours"  name="updatefreq_hours" value="<?=$pconfig['updatefreq_hours'];?>" >
<?php
                            endif;?>
                          </td>
                        </tr>
<?php
                      endforeach;?>
                      </tbody>
                      <tfoot>
                        <tr>
                          <td colspan="4">
                            <div id="addNew" style="cursor:pointer;" class="btn btn-default btn-xs" alt="add"><span class="glyphicon glyphicon-plus"></span></div>
                          </td>
                        </tr>
                      </tfoot>
                    </table>
<?php
$where = geoip_regions();
$unique = array_unique($where);
uasort($unique , function($a, $b) {return strcasecmp($a, $b);});
foreach ($unique as $region): ?>
                    <table class="table table-striped table-condensed geoip_table">
                      <tr>
                        <td colspan="3">
                          <input type="checkbox" class="region_toggle" data="<?= html_safe($region) ?>" />
                          <strong><?= $region ?> (<?= gettext('toggle all') ?>)</strong>
                        </td>
                      </tr>
                      <tr>
<?php $i = 0; foreach (geoip_countries() as $code => $name):
    if ($region == $where[$code]): ?>
                        <td>
                          <input type="checkbox" name="host_url[]" class="geoip_item region_<?= html_safe($region) ?>" value="<?= html_safe($code) ?>" <?= in_array($code, $pconfig['host_url']) ? 'checked="checked"' : '' ?>/>
                          <strong><?= $name ?></strong>
                        </td>
<?php $i += 1;
if ($i % 3 == 0): ?>
                      </tr>
                      <tr>
<?php endif;
endif;
endforeach; ?>
<?php for ($i %= 3; $i < 3; $i += 1): ?>
                        </td><td>
<?php
endfor; ?>
                        </td>
                      </tr>
                    </table>
                    <br class="geoip_table"/>
<?php endforeach; ?>
                  </td>
                </tr>
                <tr>
                  <td>&nbsp;</td>
                  <td>
                    <input id="submit" name="submit" type="submit" class="btn btn-primary" value="<?=gettext("Save"); ?>" />
                    <input type="button" class="btn btn-default" value="<?=gettext("Cancel");?>" onclick="window.location.href='/firewall_aliases.php'" />
                  </td>
                </tr>
              </table>
            </form>
          </div>
        </section>
      </div>
    </div>
  </section>
<?php include("foot.inc"); ?>
