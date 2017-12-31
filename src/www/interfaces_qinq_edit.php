<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.
    Copyright (C) 2009 Ermal Luçi
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

$a_qinqs = &config_read_array('qinqs', 'qinqentry');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = 0;
    if (isset($_GET['id']) && !empty($a_qinqs[$_GET['id']])) {
        $id = $_GET['id'];
    }
    $pconfig['if'] = isset($a_qinqs[$id]['if']) ? $a_qinqs[$id]['if'] : null;
    $pconfig['tag'] = isset($a_qinqs[$id]['tag']) ? $a_qinqs[$id]['tag'] : null;
    $pconfig['members'] = isset($a_qinqs[$id]['members']) ? explode(' ', $a_qinqs[$id]['members']) : array();
    $pconfig['descr'] = isset($a_qinqs[$id]['descr']) ? $a_qinqs[$id]['descr'] : null;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // validate / save form data
    if (isset($_POST['id']) && !empty($a_qinqs[$_POST['id']])) {
        $id = $_POST['id'];
    }
    $input_errors = array();
    $pconfig = $_POST;

    if (empty($pconfig['tag'])) {
        $input_errors[] = gettext("First level tag cannot be empty.");
    }
    if (isset($id) && $a_qinqs[$id]['tag'] != $pconfig['tag']) {
        $input_errors[] = gettext("You are editing an existing entry and modifying the first level tag is not allowed.");
    }
    if (isset($id) && $a_qinqs[$id]['if'] != $pconfig['if']) {
        $input_errors[] = gettext("You are editing an existing entry and modifying the interface is not allowed.");
    }

    if (!isset($id)) {
        foreach ($a_qinqs as $qinqentry) {
            if ($qinqentry['tag'] == $pconfig['tag'] && $qinqentry['if'] == $pconfig['if']) {
                $input_errors[] = gettext("QinQ level already exists for this interface, edit it!");
            }
        }
        if (isset($config['vlans']['vlan'])) {
            foreach ($config['vlans']['vlan'] as $vlan) {
                if ($vlan['tag'] == $pconfig['tag'] && $vlan['if'] == $pconfig['if']) {
                    $input_errors[] = gettext("A normal VLAN exists with this tag please remove it to use this tag for QinQ first level.");
                }
            }
        }
    }

    if (count($input_errors) == 0) {
        $qinqentry = array();
        $qinqentry['if'] = $pconfig['if'];
        $qinqentry['tag'] = $pconfig['tag'];
        $qinqentry['members'] = implode(" ", $pconfig['members']);
        $qinqentry['descr'] = $pconfig['descr'];
        $qinqentry['vlanif'] = "{$pconfig['if']}_{$pconfig['tag']}";

        if (isset($id)) {
            $omembers = explode(" ", $a_qinqs[$id]['members']);
            $delmembers = array_diff($omembers, $pconfig['members']);
            $addmembers = array_diff($pconfig['members'], $omembers);

            if ((count($delmembers) > 0) || (count($addmembers) > 0)) {
                // XXX needs improvement
                $fd = fopen('/tmp/netgraphcmd', 'w');
                foreach ($delmembers as $tag) {
                    fwrite($fd, "shutdown {$qinqentry['vlanif']}h{$tag}:\n");
                    fwrite($fd, "msg {$qinqentry['vlanif']}qinq: delfilter \\\"{$qinqentry['vlanif']}{$tag}\\\"\n");
                }

                foreach ($addmembers as $member) {
                    $qinq = array();
                    $qinq['if'] = $qinqentry['vlanif'];
                    $qinq['tag'] = $member;
                    $macaddr = get_interface_mac($qinqentry['vlanif']);
                    interface_qinq2_configure($qinq, $fd, $macaddr);
                }

                fclose($fd);
                mwexec('/usr/sbin/ngctl -f /tmp/netgraphcmd');
            }
            $a_qinqs[$id] = $qinqentry;
        } else {
            interface_qinq_configure($qinqentry);
            $a_qinqs[] = $qinqentry;
        }

        write_config();
        header(url_safe('Location: /interfaces_qinq.php'));
        exit;
      }
}

include("head.inc");
?>

<body>
<?php include("fbegin.inc"); ?>
<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
      <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
      <section class="col-xs-12">
        <div class="content-box">
          <div class="table-responsive">
            <form method="post" name="iform" id="iform">
              <table class="table table-striped opnsense_standard_table_form">
                <thead>
                  <tr>
                    <td style="width:22%"><strong><?=gettext("Interface QinQ Edit");?></strong></td>
                    <td style="width:78%; text-align:right">
                      <small><?=gettext("full help"); ?> </small>
                      <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                      &nbsp;
                    </td>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td><a id="help_for_if" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Parent interface"); ?></td>
                    <td>
                      <select name="if" class="selectpicker">
<?php
                      $portlist = get_interface_list();
                      /* add LAGG interfaces */
                      if (isset($config['laggs']['lagg'])) {
                          foreach ($config['laggs']['lagg'] as $lagg) {
                              $portlist[$lagg['laggif']] = $lagg;
                          }
                      }
                      foreach ($portlist as $ifn => $ifinfo):
                        if (!is_jumbo_capable($ifn)) {
                            continue;
                        }?>
                        <option value="<?=$ifn;?>" <?=$ifn == $pconfig['if'] ? " selected=\"selected\"" : "";?>>
                          <?=htmlspecialchars($ifn);?>  ( <?= !empty($ifinfo['mac']) ? $ifinfo['mac'] :"" ;?> )
                        </option>
<?php
                      endforeach;?>
                      </select>
                      <output class="hidden" for="help_for_if">
                        <?=gettext("Only QinQ capable interfaces will be shown.");?>
                      </output>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_tag" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("First level tag");?></td>
                    <td>
                      <input name="tag" type="text" value="<?=$pconfig['tag'];?>" />
                      <output class="hidden" for="help_for_tag">
                        <?=gettext("This is the first level VLAN tag. On top of this are stacked the member VLANs defined below.");?>
                      </output>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_descr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description"); ?></td>
                    <td>
                      <input name="descr" type="text" value="<?=$pconfig['descr'];?>" />
                      <output class="hidden" for="help_for_descr">
                        <?=gettext("You may enter a description here for your reference (not parsed).");?>
                      </output>
                    </td>
                  </tr>
                  <tr>
                    <td><i class="fa fa-info-circle text-muted"></i>  <?=gettext("Member (s)");?></div></td>
                    <td>
                      <select name="members[]" multiple="multiple" data-size="10" class="selectpicker" data-live-search="true">
<?php
                      for ($vlanid =  1 ; $vlanid <= 4094 ; $vlanid++):?>
                      <option value="<?=$vlanid;?>" <?=in_array($vlanid, $pconfig['members']) ? "selected=\"selected\"" : "";?>>
                          <?=$vlanid;?>
                      </option>
<?php
                      endfor;?>
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <td>&nbsp;</td>
                    <td>
                      <input name="submit" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" />
                      <input type="button" class="btn btn-default" value="<?=gettext("Cancel");?>" onclick="window.location.href='/interfaces_qinq.php'" />
                      <?php if (isset($id) && isset($a_qinqs[$id])): ?>
                      <input name="id" type="hidden" value="<?=$id;?>" />
                      <?php endif; ?>
                    </td>
                  </tr>
                </tbody>
              </table>
            </form>
          </div>
        </div>
      </section>
    </div>
  </div>
</section>
<?php include("foot.inc"); ?>
