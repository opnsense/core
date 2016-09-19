<?php

/*
    Copyright (C) 2014-2015 Deciso B.V.
    Copyright (C) 2005-2007 Scott Ullrich
    Copyright (C) 2008 Shrew Soft Inc
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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();
    $pconfig['disablechecksumoffloading'] = isset($config['system']['disablechecksumoffloading']);
    $pconfig['disablesegmentationoffloading'] = isset($config['system']['disablesegmentationoffloading']);
    $pconfig['disablelargereceiveoffloading'] = isset($config['system']['disablelargereceiveoffloading']);
    if (!isset($config['system']['disablevlanhwfilter'])) {
      $pconfig['disablevlanhwfilter'] = '0';
    } else {
      $pconfig['disablevlanhwfilter'] = $config['system']['disablevlanhwfilter'];
    }
    $pconfig['sharednet'] = isset($config['system']['sharednet']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;

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

    if (!empty($_POST['disablelargereceiveoffloading'])) {
        $config['system']['disablelargereceiveoffloading'] = true;
    } elseif (isset($config['system']['disablelargereceiveoffloading'])) {
        unset($config['system']['disablelargereceiveoffloading']);
    }

    if (!empty($_POST['disablevlanhwfilter'])) {
        $config['system']['disablevlanhwfilter'] = $pconfig['disablevlanhwfilter'];
    } elseif(isset($config['system']['disablevlanhwfilter'])) {
        unset($config['system']['disablevlanhwfilter']);
    }

    write_config();
    system_arp_wrong_if();
}

legacy_html_escape_form_data($pconfig);

include("head.inc");

?>

<body>
  <?php include("fbegin.inc"); ?>

<!-- row -->
<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
<?php
    if (isset($savemsg)) {
        print_info_box($savemsg);
    }
?>
    <section class="col-xs-12">
      <div class="content-box tab-content table-responsive">
        <form method="post" name="iform" id="iform">
          <table class="table table-striped opnsense_standard_table_form">
              <tr>
                <td width="22%"><strong><?= gettext('Network Interfaces') ?></strong></td>
                <td width="78%" align="right">
                  <small><?=gettext("full help"); ?> </small>
                  <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page" type="button"></i>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_disablechecksumoffloading" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Hardware CRC"); ?></td>
                <td>
                  <input name="disablechecksumoffloading" type="checkbox" id="disablechecksumoffloading" value="yes" <?= !empty($pconfig['disablechecksumoffloading']) ? "checked=\"checked\"" :"";?> />
                  <strong><?=gettext("Disable hardware checksum offload"); ?></strong>
                  <div class="hidden" for="help_for_disablechecksumoffloading">
                    <?=gettext("Checking this option will disable hardware checksum offloading. Checksum offloading is broken in some hardware, particularly some Realtek cards. Rarely, drivers may have problems with checksum offloading and some specific NICs."); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_disablesegmentationoffloading" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Hardware TSO"); ?></td>
                <td>
                  <input name="disablesegmentationoffloading" type="checkbox" id="disablesegmentationoffloading" value="yes" <?= !empty($pconfig['disablesegmentationoffloading']) ? "checked=\"checked\"" :"";?>/>
                  <strong><?=gettext("Disable hardware TCP segmentation offload"); ?></strong><br />
                  <div class="hidden" for="help_for_disablesegmentationoffloading">
                    <?=gettext("Checking this option will disable hardware TCP segmentation offloading (TSO, TSO4, TSO6). This offloading is broken in some hardware drivers, and may impact performance with some specific NICs."); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_disablelargereceiveoffloading" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("Hardware LRO"); ?></td>
                <td>
                  <input name="disablelargereceiveoffloading" type="checkbox" id="disablelargereceiveoffloading" value="yes" <?= !empty($pconfig['disablelargereceiveoffloading']) ? "checked=\"checked\"" :"";?>/>
                  <strong><?=gettext("Disable hardware large receive offload"); ?></strong><br />
                  <div class="hidden" for="help_for_disablelargereceiveoffloading">
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
                  <div class="hidden" for="help_for_disablevlanhwfilter">
                    <?=gettext("Checking this option will disable VLAN hardware filtering. This offloading is broken in some hardware drivers, and may impact performance with some specific NICs."); ?>
                  </div>
                </td>
              </tr>
              <tr>
                <td><a id="help_for_sharednet" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("ARP Handling"); ?></td>
                <td>
                  <input name="sharednet" type="checkbox" id="sharednet" value="yes" <?= !empty($pconfig['sharednet']) ? "checked=\"checked\"" :"";?>/>
                  <strong><?=gettext("Suppress ARP messages"); ?></strong><br />
                  <div class="hidden" for="help_for_sharednet">
                    <?=gettext("This option will suppress ARP log messages when multiple interfaces reside on the same broadcast domain"); ?>
                  </div>
                </td>
              </tr>
            <tr>
              <td>&nbsp;</td>
              <td><input name="Submit" type="submit" class="btn btn-primary" value="<?=gettext("Save");?>" /></td>
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
