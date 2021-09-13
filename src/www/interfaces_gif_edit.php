<?php

/*
 * Copyright (C) 2014-2015 Deciso B.V.
 * Copyright (C) 2008 Ermal Luçi
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
require_once("system.inc");
require_once("interfaces.inc");
require_once("filter.inc");

$a_gifs = &config_read_array('gifs', 'gif');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // read form data
    if (!empty($a_gifs[$_GET['id']])) {
        $id = $_GET['id'];
    }
    $pconfig = array();

    // copy fields
    $copy_fields = array('gifif', 'remote-addr', 'tunnel-remote-net', 'tunnel-local-addr', 'tunnel-remote-addr', 'descr');
    foreach ($copy_fields as $fieldname) {
        $pconfig[$fieldname] = isset($a_gifs[$id][$fieldname]) ? $a_gifs[$id][$fieldname] : null;
    }
    // bool fields
    $pconfig['link0'] = isset($a_gifs[$id]['link0']);
    $pconfig['link1'] = isset($a_gifs[$id]['link1']);

    // construct interface
    if (!empty($a_gifs[$id]['ipaddr'])) {
        $pconfig['if'] = $pconfig['if'] . '|' . $a_gifs[$id]['ipaddr'];
    } elseif (!empty($a_gifs[$id]['if'])) {
        $pconfig['if'] = $a_gifs[$id]['if'];
    } else {
        $pconfig['if'] = null;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // validate and save form data
    if (!empty($a_gifs[$_POST['id']])) {
        $id = $_POST['id'];
    }

    $input_errors = array();
    $pconfig = $_POST;

    /* input validation */
    $reqdfields = explode(" ", "if tunnel-remote-addr tunnel-remote-net tunnel-local-addr");
    $reqdfieldsn = array(gettext("Parent interface,Local address, Remote tunnel address, Remote tunnel network, Local tunnel address"));

    do_input_validation($pconfig, $reqdfields, $reqdfieldsn, $input_errors);

    if (!is_ipaddr($pconfig['tunnel-local-addr']) || !is_ipaddr($pconfig['tunnel-remote-addr']) || !is_ipaddr($pconfig['remote-addr'])) {
        $input_errors[] = gettext("The tunnel local and tunnel remote fields must have valid IP addresses.");
    }

    $alias = strstr($pconfig['if'],'|');
    if ((is_ipaddrv4($alias) && !is_ipaddrv4($pconfig['remote-addr'])) ||
        (is_ipaddrv6($alias) && !is_ipaddrv6($pconfig['remote-addr']))
    ) {
        $input_errors[] = gettext("The alias IP address family has to match the family of the remote peer address.");
    }

    foreach ($a_gifs as $gif) {
        if (isset($id) && $a_gifs[$id] === $gif) {
            continue;
        }
        /* FIXME: needs to perform proper subnet checks in the feature */
        if ($gif['if'] == $interface && $gif['tunnel-remote-addr'] == $pconfig['tunnel-remote-addr']) {
            $input_errors[] = sprintf(gettext("A gif with the network %s is already defined."), $gif['tunnel-remote-addr']);
            break;
        }
    }

    if (count($input_errors) == 0) {
        $gif = array();
        // copy fields
        $copy_fields = array('tunnel-local-addr', 'tunnel-remote-addr', 'tunnel-remote-net', 'remote-addr', 'descr', 'gifif');
        foreach ($copy_fields as $fieldname) {
            $gif[$fieldname] = $pconfig[$fieldname];
        }
        // bool fields
        $gif['link1'] = !empty($pconfig['link1']);
        $gif['link0'] = !empty($pconfig['link0']);

        // interface and optional bind address
        if (strpos($pconfig['if'], '|') !== false) {
            list($gif['if'], $gif['ipaddr']) = explode("|",$pconfig['if']);
        } else {
            $gif['if'] = $pconfig['if'];
            $gif['ipaddr'] = null;
        }

        $gif['gifif'] = interface_gif_configure($gif);
        ifgroup_setup();
        if ($gif['gifif'] == "" || !stristr($gif['gifif'], "gif")) {
            $input_errors[] = gettext("Error occurred creating interface, please retry.");
        } else {
            if (isset($id)) {
                $a_gifs[$id] = $gif;
            } else {
                $a_gifs[] = $gif;
            }
            write_config();
            $confif = convert_real_interface_to_friendly_interface_name($gif['gifif']);
            if ($confif != '') {
                interface_configure(false, $confif);
            }
            header(url_safe('Location: /interfaces_gif.php'));
            exit;
        }
    }
}

legacy_html_escape_form_data($pconfig);
include("head.inc");
?>

<body>
<script>
  $( document ).ready(function() {
    hook_ipv4v6('ipv4v6net', 'network-id');
  });
</script>
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
                    <td style="width:22%"><strong><?=gettext("GIF configuration");?></strong></td>
                    <td style="width:78%; text-align:right">
                      <small><?=gettext("full help"); ?> </small>
                      <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                      &nbsp;
                    </td>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td style="width:22%"><a id="help_for_if" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Parent interface"); ?></td>
                    <td style="width:78%">
                      <select name="if" class="selectpicker" data-live-search="true">
<?php
                      $portlist = get_configured_interface_with_descr();
                      $carplist = get_configured_carp_interface_list();
                      $aliaslist = get_configured_ip_aliases_list();
                      foreach ($carplist as $cif => $carpip) {
                          $portlist[$cif] = $carpip." (".get_vip_descr($carpip).")";
                      }
                      foreach ($aliaslist as $aliasip => $aliasif) {
                          $portlist[$aliasif.'|'.$aliasip] = $aliasip." (".get_vip_descr($aliasip).")";
                      }
                      foreach ($portlist as $ifn => $ifinfo):?>
                        <option value="<?=$ifn;?>" <?=$ifn == $pconfig['if'] ? "selected=\"selected\"" : "";?>>
                            <?=htmlspecialchars($ifinfo);?>
                        </option>
<?php
                      endforeach;?>
                      </select>
                      <div class="hidden" data-for="help_for_if">
                        <?=gettext("The interface here serves as the local address to be used for the gif tunnel."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_remote-addr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("GIF remote address"); ?></td>
                    <td>
                      <input name="remote-addr" type="text" value="<?=$pconfig['remote-addr'];?>" />
                      <div class="hidden" data-for="help_for_remote-addr">
                        <?=gettext("Peer address where encapsulated gif packets will be sent. "); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_tunnel-local-addr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("GIF tunnel local address"); ?></td>
                    <td>
                      <input name="tunnel-local-addr" type="text" value="<?=$pconfig['tunnel-local-addr'];?>" />
                      <div class="hidden" data-for="help_for_tunnel-local-addr">
                        <?=gettext("Local gif tunnel endpoint"); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_tunnel-remote-addr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("GIF tunnel remote address "); ?></td>
                    <td>
                      <table class="table table-condensed">
                        <tr>
                          <td style="width:285px">
                            <input name="tunnel-remote-addr" type="text" id="tunnel-remote-addr" value="<?=$pconfig['tunnel-remote-addr'];?>" />
                          </td>
                          <td>
                            <select name="tunnel-remote-net" data-network-id="tunnel-remote-addr" class="selectpicker ipv4v6net" data-size="10" id="tunnel-remote-net" data-width="auto">
<?php
                            for ($i = 128; $i > 0; $i--):?>
                              <option value="<?=$i;?>"  <?=$i == $pconfig['tunnel-remote-net'] ? "selected=\"selected\"" : "";?> >
                                  <?=$i;?>
                              </option>
<?php
                            endfor;?>
                            </select>
                          </td>
                        </tr>
                      </table>
                      <div class="hidden" data-for="help_for_tunnel-remote-addr">
                        <?=gettext("Remote gif address endpoint. The subnet part is used for determining the network that is tunnelled."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_link0" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Route caching"); ?></td>
                    <td>
                      <input name="link0" type="checkbox" id="link0" <?=!empty($pconfig['link0']) ? "checked=\"checked\"" :"";?> />
                      <div class="hidden" data-for="help_for_link0">
                        <?=gettext("Specify if route caching can be enabled. Be careful with these settings on dynamic networks."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_link1" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("ECN friendly behavior"); ?></td>
                    <td>
                      <input name="link1" type="checkbox" id="link1" <?=!empty($pconfig['link1']) ? "checked=\"checked\"" : "";?> />
                      <div class="hidden" data-for="help_for_link1">
                        <?=gettext("Note that the ECN friendly behavior violates RFC2893. This should be used in mutual agreement with the peer."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td><a id="help_for_descr" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Description"); ?></td>
                    <td>
                      <input name="descr" type="text" value="<?=$pconfig['descr'];?>" />
                      <div class="hidden" data-for="help_for_descr">
                        <?=gettext("You may enter a description here for your reference (not parsed)."); ?>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td>&nbsp;</td>
                    <td>
                      <input type="hidden" name="gifif" value="<?=$pconfig['gifif']; ?>" />
                      <input name="Submit" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save')); ?>" />
                      <input type="button" class="btn btn-default" value="<?=html_safe(gettext('Cancel'));?>" onclick="window.location.href='/interfaces_gif.php'" />
                      <?php if (isset($id)): ?>
                      <input name="id" type="hidden" value="<?=htmlspecialchars($id);?>" />
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
