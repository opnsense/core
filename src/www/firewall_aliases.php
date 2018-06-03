<?php

/*
    Copyright (C) 2014 Deciso B.V.
    Copyright (C) 2004 Scott Ullrich <sullrich@gmail.com>
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

function find_alias_type($type)
{
    $types = array(
        'host' => gettext('Host(s)'),
        'network' => gettext('Network(s)'),
        'port' => gettext('Port(s)'),
        'url' => gettext('URL (IPs)'),
        'url_ports' => gettext('URL (Ports)'),
        'urltable' => gettext('URL Table (IPs)'),
        'urltable_ports' => gettext('URL Table (Ports)'),
    );

    if (isset($types[$type])) {
        return $types[$type];
    }

    return $type;
}

function find_alias_reference($section, $field, $origname, &$is_alias_referenced, &$referenced_by)
{
    global $config;
    if (!$origname || $is_alias_referenced) {
        return;
    }

    $sectionref = &config_read_array();
    foreach($section as $sectionname) {
        if (is_array($sectionref) && isset($sectionref[$sectionname])) {
            $sectionref = &$sectionref[$sectionname];
        } else {
            return;
        }
    }

    if (is_array($sectionref)) {
        foreach($sectionref as $itemkey => $item) {
            $fieldfound = true;
            $fieldref = &$sectionref[$itemkey];
            foreach($field as $fieldname) {
                if (is_array($fieldref) && isset($fieldref[$fieldname])) {
                    $fieldref = &$fieldref[$fieldname];
                } else {
                    $fieldfound = false;
                    break;
                }
            }
            if ($fieldfound && $fieldref == $origname) {
                $is_alias_referenced = true;
                $referenced_by = '';
                if (is_array($item)) {
                    if (isset($item['descr'])) {
                        $referenced_by .= $item['descr'];
                    } else {
                        $referenced_by .= implode(',', $section) . ' / '. implode(',', $field);
                    }
                }
                break;
            }
        }
    }
}

function alias_used_recursive($origname)
{
    global $config;
    if (!empty($config['aliases']['alias'])) {
        foreach($config['aliases']['alias'] as $alias) {
            // exclude geoips and urltypes, they don't support nesting.
            if ($alias['type'] != 'geoip' && !preg_match("/urltable/i",$alias['type'])) {
                if ($origname == $alias['address']) {
                    return empty($alias['description']) ? $alias['name'] : $alias['description'];
                }
            }
        }
    }
    return null;
}

$a_aliases = &config_read_array('aliases', 'alias');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['apply'])) {
        /* reload all components that use aliases */
        // strictly we should only reload if a port alias has changed
        filter_configure();
        // flush alias contents to disk and update pf tables
        configd_run('template reload OPNsense/Filter');
        configd_run('filter refresh_aliases', true);
        $savemsg = get_std_save_message();
        clear_subsystem_dirty('aliases');
    } elseif (isset($_POST['act']) && $_POST['act'] == "del") {
        if (isset($_POST['id']) && isset($a_aliases[$_POST['id']])) {
            // perform validation
            /* make sure rule is not being referenced by any nat or filter rules */
            $is_alias_referenced = false;
            $referenced_by = false;
            $alias_name = $a_aliases[$_POST['id']]['name'];
            // Firewall rules
            find_alias_reference(array('filter', 'rule'), array('source', 'address'), $alias_name, $is_alias_referenced, $referenced_by);
            find_alias_reference(array('filter', 'rule'), array('destination', 'address'), $alias_name, $is_alias_referenced, $referenced_by);
            find_alias_reference(array('filter', 'rule'), array('source', 'port'), $alias_name, $is_alias_referenced, $referenced_by);
            find_alias_reference(array('filter', 'rule'), array('destination', 'port'), $alias_name, $is_alias_referenced, $referenced_by);
            // NAT Rules
            find_alias_reference(array('nat', 'rule'), array('source', 'address'), $alias_name, $is_alias_referenced, $referenced_by);
            find_alias_reference(array('nat', 'rule'), array('source', 'port'), $alias_name, $is_alias_referenced, $referenced_by);
            find_alias_reference(array('nat', 'rule'), array('destination', 'address'), $alias_name, $is_alias_referenced, $referenced_by);
            find_alias_reference(array('nat', 'rule'), array('destination', 'port'), $alias_name, $is_alias_referenced, $referenced_by);
            find_alias_reference(array('nat', 'rule'), array('target'), $alias_name, $is_alias_referenced, $referenced_by);
            find_alias_reference(array('nat', 'rule'), array('local-port'), $alias_name, $is_alias_referenced, $referenced_by);
            // NAT 1:1 Rules
            //find_alias_reference(array('nat', 'onetoone'), array('external'), $alias_name, $is_alias_referenced, $referenced_by);
            //find_alias_reference(array('nat', 'onetoone'), array('source', 'address'), $alias_name, $is_alias_referenced, $referenced_by);
            find_alias_reference(array('nat', 'onetoone'), array('destination', 'address'), $alias_name, $is_alias_referenced, $referenced_by);
            // NAT Outbound Rules
            find_alias_reference(array('nat', 'outbound', 'rule'), array('source', 'network'), $alias_name, $is_alias_referenced, $referenced_by);
            find_alias_reference(array('nat', 'outbound', 'rule'), array('sourceport'), $alias_name, $is_alias_referenced, $referenced_by);
            find_alias_reference(array('nat', 'outbound', 'rule'), array('destination', 'address'), $alias_name, $is_alias_referenced, $referenced_by);
            find_alias_reference(array('nat', 'outbound', 'rule'), array('dstport'), $alias_name, $is_alias_referenced, $referenced_by);
            find_alias_reference(array('nat', 'outbound', 'rule'), array('target'), $alias_name, $is_alias_referenced, $referenced_by);

            // Alias in an alias, only for selected types
            $alias_recursive_used = alias_used_recursive($alias_name);
            if  ($alias_recursive_used != null) {
                $is_alias_referenced = true;
                $referenced_by = $alias_recursive_used;
            }
            // Load Balancer
            find_alias_reference(array('load_balancer', 'lbpool'),         array('port'), $alias_name, $is_alias_referenced, $referenced_by);
            find_alias_reference(array('load_balancer', 'virtual_server'), array('port'), $alias_name, $is_alias_referenced, $referenced_by);
            // Static routes
            find_alias_reference(array('staticroutes', 'route'), array('network'), $alias_name, $is_alias_referenced, $referenced_by);
            if ($is_alias_referenced) {
                $savemsg = sprintf(gettext("Cannot delete alias. Currently in use by %s"), $referenced_by);
            } else {
                configd_run("filter kill table {$alias_name}");
                unset($a_aliases[$_POST['id']]);
                write_config();
                mark_subsystem_dirty('aliases');
                header(url_safe('Location: /firewall_aliases.php'));
                exit;
            }
        }
    }
}

legacy_html_escape_form_data($a_aliases);
$main_buttons = array(
    array('href' => 'firewall_aliases_edit.php', 'label' => gettext('Add a new alias')),
);

include("head.inc");

?>
<body>
<script>
$( document ).ready(function() {
  // link delete buttons
  $(".act_delete").click(function(){
    var id = $(this).attr("id").split('_').pop(-1);
    BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
        title: "<?= gettext("Aliases");?>",
        message: "<?=gettext("Do you really want to delete this alias? All elements that still use it will become invalid (e.g. filter rules)!");?>",
        buttons: [{
                label: "<?= gettext("No");?>",
                action: function(dialogRef) {
                  dialogRef.close();
                }}, {
                  label: "<?= gettext("Yes");?>",
                  action: function(dialogRef) {
                    $("#delId").val(id);
                    $("#iform").submit()
                }
        }]
    });
  });
});
</script>
<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
<?php if (isset($savemsg)) print_info_box($savemsg); ?>
<?php if (is_subsystem_dirty('aliases')): ?>
<?php print_info_box_apply(gettext("The alias list has been changed.") . "<br />" . gettext("You must apply the changes in order for them to take effect."));?>
<?php endif; ?>
        <section class="col-xs-12">
          <div class="content-box">
            <form method="post" name="iform" id="iform">
              <input type="hidden" name="tab" value="<?=$selected_tab;?>" />
              <input type="hidden" name="id" value="" id="delId"/>
              <input type="hidden" name="act" value="del"/>
                <div class="table-responsive">
                  <table class="table table-striped">
                    <tr>
                      <td><?=gettext("Name"); ?></td>
                      <td><?=gettext("Type"); ?></td>
                      <td><?=gettext("Description"); ?></td>
                      <td><?=gettext("Values"); ?></td>
                      <td>&nbsp;</td>
                    </tr>
<?php
                    uasort($a_aliases, function($a, $b) {
                        return strnatcmp($a['name'], $b['name']);
                    });

                    foreach ($a_aliases as $i=> $alias){
?>
                    <tr>
                      <td ondblclick="document.location='firewall_aliases_edit.php?id=<?=$i;?>';">
                        <?=$alias['name'];?>
                      </td>
<?php
                        $alias_values = '';
                        if (!empty($alias["url"])) {
                            $alias_values = $alias["url"];
                        } elseif (isset($alias["aliasurl"])) {
                            $alias_values = implode(", ", array_slice($alias['aliasurl'], 0, 5));
                            if (count($alias['aliasurl']) > 5) {
                                $alias_values .= "...";
                            }
                        } else {
                            $alias_values = implode(", ", array_slice(explode(" ", $alias['address']), 0, 5));
                            if (count(explode(" ", $alias['address'])) > 5) {
                                $alias_values .= "...";
                            }
                      }
?>
                      <td ondblclick="document.location='firewall_aliases_edit.php?id=<?=$i;?>';">
                        <?= find_alias_type($alias['type']) ?>
                      </td>
                      <td ondblclick="document.location='firewall_aliases_edit.php?id=<?=$i;?>';">
                        <?= $alias['descr'] ?>
                      </td>
                      <td ondblclick="document.location='firewall_aliases_edit.php?id=<?=$i;?>';">
                        <?= $alias_values ?>
                      </td>
                      <td>
                        <a href="firewall_aliases_edit.php?id=<?=$i;?>" title="<?=gettext("Edit alias"); ?>" class="btn btn-default btn-xs"><span class="fa fa-pencil"></span></a>
                        <a id="del_<?=$i;?>" title="<?=gettext("delete alias"); ?>" class="act_delete btn btn-default btn-xs"><span class="fa fa-trash text-muted"></span></a>
                      </td>
                    </tr>
<?php
                    } // foreach
?>
                  <tr>
                    <td colspan="5">
                      <?=gettext("Aliases act as placeholders for real hosts, networks or ports. They can be used to minimize the number of changes that have to be made if a host, network or port changes. You can enter the name of an alias instead of the host, network or port in all fields that have a red background. The alias will be resolved according to the list above. If an alias cannot be resolved (e.g. because you deleted it), the corresponding element (e.g. filter/NAT/shaper rule) will be considered invalid and skipped."); ?>
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
