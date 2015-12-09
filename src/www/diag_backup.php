<?php

/*
  Copyright (C) 2014 Deciso B.V.
  Copyright (C) 2004-2009 Scott Ullrich
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

/* Allow additional execution time 0 = no limit. */
ini_set('max_execution_time', '0');
ini_set('max_input_time', '0');

/* omit no-cache headers because it confuses IE with file downloads */
$omit_nocacheheaders = true;
$nocsrf = true;

require_once("guiconfig.inc");
require_once("interfaces.inc");
require_once("filter.inc");
require_once("services.inc");
require_once("rrd.inc");
require_once("system.inc");
require_once("pfsense-utils.inc");

/**
 * check if cron exists
 */
function cron_job_exists($command) {
    global $config;
    foreach($config['cron']['item'] as $item) {
        if(strstr($item['command'], $command)) {
            return true;
        }
    }
    return false;
}


function rrd_data_xml() {
    $rrddbpath = '/var/db/rrd';

    $result = "\t<rrddata>\n";
    $rrd_files = glob("{$rrddbpath}/*.rrd");
    $xml_files = array();
    foreach ($rrd_files as $rrd_file) {
        $basename = basename($rrd_file);
        $xml_file = preg_replace('/\.rrd$/', ".xml", $rrd_file);
        exec("/usr/local/bin/rrdtool dump '{$rrd_file}' '{$xml_file}'");
        $xml_data = @file_get_contents($xml_file);
        @unlink($xml_file);
        if ($xml_data !== false) {
            $result .= "\t\t<rrddatafile>\n";
            $result .= "\t\t\t<filename>{$basename}</filename>\n";
            $result .= "\t\t\t<xmldata>" . base64_encode(gzdeflate($xml_data)) . "</xmldata>\n";
            $result .= "\t\t</rrddatafile>\n";
        }
    }
    $result .= "\t</rrddata>\n";
    return $result;
}


function restore_rrddata() {
    global $config;
    foreach($config['rrddata']['rrddatafile'] as $rrd) {
        if (!empty($rrd['xmldata'])) {
            $rrd_file = "/var/db/rrd/{$rrd['filename']}";
            $xml_file = preg_replace('/\.rrd$/', ".xml", $rrd_file);
            if (file_put_contents($xml_file, gzinflate(base64_decode($rrd['xmldata']))) === false) {
                log_error("Cannot write $xml_file");
                continue;
            }
            $output = array();
            $status = null;
            exec("/usr/local/bin/rrdtool restore -f '{$xml_file}' '{$rrd_file}'", $output, $status);
            if ($status) {
                log_error("rrdtool restore -f '{$xml_file}' '{$rrd_file}' failed returning {$status}.");
                continue;
            }
            unlink($xml_file);
        } elseif (!empty($rrd['data'])) {
            // pfSense 2.0 rrd backup format
            $rrd_file = "/var/db/rrd/{$rrd['filename']}";
            $rrd_fd = fopen($rrd_file, "w");
            if (!$rrd_fd) {
                log_error("Cannot write $rrd_file");
                continue;
            }
            $data = base64_decode($rrd['data']);
            /* Try to decompress the data. */
            $dcomp = @gzinflate($data);
            if ($dcomp) {
                /* If the decompression worked, write the decompressed data */
                if (fwrite($rrd_fd, $dcomp) === false) {
                    log_error("fwrite $rrd_file failed");
                    continue;
                }
            } elseif (fwrite($rrd_fd, $data) === false) {
                  /* If the decompression failed, it wasn't compressed, so write raw data */
                  log_error("fwrite $rrd_file failed");
                  continue;
            }
            if (fclose($rrd_fd) === false) {
                log_error("fclose $rrd_file failed");
                continue;
            }
        }
    }
}

$areas = array("aliases" => gettext("Aliases"),
         "dnsmasq" => gettext("DNS Forwarder"),
         "dhcpd" => gettext("DHCP Server"),
         "dhcpdv6" => gettext("DHCPv6 Server"),
         "filter" => gettext("Firewall Rules"),
         "interfaces" => gettext("Interfaces"),
         "ipsec" => gettext("IPSEC"),
         "nat" => gettext("NAT"),
         "openvpn" => gettext("OpenVPN"),
         "pptpd" => gettext("PPTP Server"),
         "rrddata" => gettext("RRD Data"),
         "cron" => gettext("Scheduled Tasks"),
         "syslog" => gettext("Syslog"),
         "system" => gettext("System"),
         "staticroutes" => gettext("Static routes"),
         "sysctl" => gettext("System tunables"),
         "snmpd" => gettext("SNMP Server"),
         "vlans" => gettext("VLANS"),
         "wol" => gettext("Wake on LAN")
  );


if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();
    $pconfig['GDriveEnabled'] = isset($config['system']['remotebackup']['GDriveEnabled']) ? $config['system']['remotebackup']['GDriveEnabled'] : null;
    $pconfig['GDriveEmail'] = isset($config['system']['remotebackup']['GDriveEmail']) ? $config['system']['remotebackup']['GDriveEmail'] : null;
    $pconfig['GDriveP12key'] = isset($config['system']['remotebackup']['GDriveP12key']) ? $config['system']['remotebackup']['GDriveP12key'] : null;
    $pconfig['GDriveFolderID'] = isset($config['system']['remotebackup']['GDriveFolderID']) ? $config['system']['remotebackup']['GDriveFolderID'] : null;
    $pconfig['GDriveBackupCount'] = isset($config['system']['remotebackup']['GDriveBackupCount']) ? $config['system']['remotebackup']['GDriveBackupCount'] : null;
    $pconfig['GDrivePassword'] = isset($config['system']['remotebackup']['GDrivePassword']) ? $config['system']['remotebackup']['GDrivePassword'] : null;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_errors = array();
    $pconfig = $_POST;

    if (!empty($_POST['restore'])) {
        $mode = "restore";
    } elseif (!empty($_POST['download'])) {
        $mode = "download";
    } elseif (!empty($_POST['setup_gdrive'])) {
        $mode = "setup_gdrive";
    } else {
        $mode = false;
    }

    if ($mode == "download") {
        if (!empty($_POST['encrypt']) && (empty($_POST['encrypt_password']) || empty($_POST['encrypt_passconf']))) {
            $input_errors[] = gettext("You must supply and confirm the password for encryption.");
        } elseif (!empty($_POST['encrypt']) && $_POST['encrypt_password'] != $_POST['encrypt_passconf']) {
            $input_errors[] = gettext("The supplied 'Password' and 'Confirm' field values must match.");
        }
        if (count($input_errors) == 0) {
            $host = "{$config['system']['hostname']}.{$config['system']['domain']}";
            $name = "config-{$host}-".date("YmdHis").".xml";
            $data = "";

            if(empty($_POST['backuparea'])) {
                /* backup entire configuration */
                $data = file_get_contents('/conf/config.xml');
            } elseif ($_POST['backuparea'] === "rrddata") {
                $data = rrd_data_xml();
                $name = "{$_POST['backuparea']}-{$name}";
            } else {
                /* backup specific area of configuration */
                $data = backup_config_section($_POST['backuparea']);
                $name = "{$_POST['backuparea']}-{$name}";
            }

            /*
             *  Backup RRD Data
             */
            if ($_POST['backuparea'] !== "rrddata" && empty($_POST['donotbackuprrd'])) {
                $rrd_data_xml = rrd_data_xml();
                $closing_tag = "</opnsense>";
                $data = str_replace($closing_tag, $rrd_data_xml . $closing_tag, $data);
            }

            if (!empty($_POST['encrypt'])) {
                $data = encrypt_data($data, $_POST['encrypt_password']);
                tagfile_reformat($data, $data, "config.xml");
            }

            $size = strlen($data);
            header("Content-Type: application/octet-stream");
            header("Content-Disposition: attachment; filename={$name}");
            header("Content-Length: $size");
            if (isset($_SERVER['HTTPS'])) {
                header('Pragma: ');
                header('Cache-Control: ');
            } else {
                header("Pragma: private");
                header("Cache-Control: private, must-revalidate");
            }
            echo $data;
            exit;
        }
    } elseif ($mode == "restore") {
        // unpack data and perform validation
        $data = null;
        if (!empty($_POST['decrypt']) && (empty($_POST['decrypt_password']) || empty($_POST['decrypt_passconf']))) {
            $input_errors[] = gettext("You must supply and confirm the password for decryption.");
        } elseif (!empty($_POST['decrypt']) && $_POST['decrypt_password'] != $_POST['decrypt_passconf']) {
            $input_errors[] = gettext("The supplied 'Password' and 'Confirm' field values must match.");
        }
        /* read the file contents */
        if (is_uploaded_file($_FILES['conffile']['tmp_name'])) {
            $data = file_get_contents($_FILES['conffile']['tmp_name']);
            if(empty($data)) {
                log_error(sprintf(gettext("Warning, could not read file %s"), $_FILES['conffile']['tmp_name']));
                $input_errors[] = sprintf(gettext("Warning, could not read file %s"), $_FILES['conffile']['tmp_name']);
            }
        } else {
            $input_errors[] = gettext("The configuration could not be restored (file upload error).");
        }

        if (!empty($_POST['decrypt'])) {
            if (!tagfile_deformat($data, $data, "config.xml")) {
                $input_errors[] = gettext("The uploaded file does not appear to contain an encrypted OPNsense configuration.");
            }
            $data = decrypt_data($data, $_POST['decrypt_password']);
        }

        if(!empty($_POST['restorearea']) && !stristr($data, "<" . $_POST['restorearea'] . ">")) {
            /* restore a specific area of the configuration */
            $input_errors[] = gettext("You have selected to restore an area but we could not locate the correct xml tag.");
        }

        if (count($input_errors) == 0) {
            if(stristr($data, "<m0n0wall>")) {
                log_error(gettext("Upgrading m0n0wall configuration to OPNsense."));
                /* m0n0wall was found in config.  convert it. */
                $data = str_replace("m0n0wall", "pfsense", $data);
                $m0n0wall_upgrade = true;
            }
            if (!empty($_POST['restorearea'])) {
                if (!restore_config_section($_POST['restorearea'], $data)) {
                    $input_errors[] = gettext("You have selected to restore an area but we could not locate the correct xml tag.");
                } else {
                    if (!empty($config['rrddata'])) {
                        restore_rrddata();
                        unset($config['rrddata']);
                        write_config();
                        convert_config();
                    }
                    filter_configure();
                    $savemsg = gettext("The configuration area has been restored.  You may need to reboot the firewall.");
                }
            } else {
                /* restore the entire configuration */
                $filename = $_FILES['conffile']['tmp_name'];
                file_put_contents($filename, $data);
                $cnf = OPNsense\Core\Config::getInstance();
                if ($cnf->restoreBackup($filename)) {
                    /* this will be picked up by /index.php */
                    mark_subsystem_dirty("restore");
                    $config = parse_config();
                    /* extract out rrd items, unset from $config when done */
                    if($config['rrddata']) {
                        restore_rrddata();
                        unset($config['rrddata']);
                        write_config();
                        convert_config();
                    }
                    if($m0n0wall_upgrade) {
                        if(!empty($config['system']['gateway'])) {
                            $config['interfaces']['wan']['gateway'] = $config['system']['gateway'];
                        }
                        /* optional if list */
                        $ifdescrs = get_configured_interface_list(true, true);
                        /* remove special characters from interface descriptions */
                        if(is_array($ifdescrs)) {
                            foreach($ifdescrs as $iface) {
                                $config['interfaces'][$iface]['descr'] = preg_replace('/[^a-z_0-9]/i','',$config['interfaces'][$iface]['descr']);
                            }
                            /* check for interface names with an alias */
                            foreach($ifdescrs as $iface) {
                                if(is_alias($config['interfaces'][$iface]['descr'])) {
                                    // Firewall rules
                                    $origname = $config['interfaces'][$iface]['descr'];
                                    $newname  = $config['interfaces'][$iface]['descr'] . "Alias";
                                    update_alias_names_upon_change(array('filter', 'rule'), array('source', 'address'), $newname, $origname);
                                    update_alias_names_upon_change(array('filter', 'rule'), array('destination', 'address'), $newname, $origname);
                                    // NAT Rules
                                    update_alias_names_upon_change(array('nat', 'rule'), array('source', 'address'), $newname, $origname);
                                    update_alias_names_upon_change(array('nat', 'rule'), array('destination', 'address'), $newname, $origname);
                                    update_alias_names_upon_change(array('nat', 'rule'), array('target'), $newname, $origname);
                                    // Alias in an alias
                                    update_alias_names_upon_change(array('aliases', 'alias'), array('address'), $newname, $origname);
                                }
                            }
                        }
                        // Reset configuration version to something low
                        // in order to force the config upgrade code to
                        // run through with all steps that are required.
                        $config['system']['version'] = "1.0";
                        // Deal with descriptions longer than 63 characters
                        for ($i = 0; isset($config["filter"]["rule"][$i]); $i++) {
                            if(count($config['filter']['rule'][$i]['descr']) > 63) {
                                $config['filter']['rule'][$i]['descr'] = substr($config['filter']['rule'][$i]['descr'], 0, 63);
                            }
                        }
                        // Move interface from ipsec to enc0
                        for ($i = 0; isset($config["filter"]["rule"][$i]); $i++) {
                            if($config['filter']['rule'][$i]['interface'] == "ipsec") {
                                $config['filter']['rule'][$i]['interface'] = "enc0";
                            }
                        }
                        // Convert icmp types
                        // http://www.openbsd.org/cgi-bin/man.cgi?query=icmp&sektion=4&arch=i386&apropos=0&manpath=OpenBSD+Current
                        for ($i = 0; isset($config["filter"]["rule"][$i]); $i++) {
                            if($config["filter"]["rule"][$i]['icmptype']) {
                                switch($config["filter"]["rule"][$i]['icmptype']) {
                                    case "echo":
                                        $config["filter"]["rule"][$i]['icmptype'] = "echoreq";
                                        break;
                                    case "unreach":
                                        $config["filter"]["rule"][$i]['icmptype'] = "unreach";
                                        break;
                                    case "echorep":
                                        $config["filter"]["rule"][$i]['icmptype'] = "echorep";
                                        break;
                                    case "squench":
                                        $config["filter"]["rule"][$i]['icmptype'] = "squench";
                                        break;
                                    case "redir":
                                        $config["filter"]["rule"][$i]['icmptype'] = "redir";
                                        break;
                                    case "timex":
                                        $config["filter"]["rule"][$i]['icmptype'] = "timex";
                                        break;
                                    case "paramprob":
                                        $config["filter"]["rule"][$i]['icmptype'] = "paramprob";
                                        break;
                                    case "timest":
                                        $config["filter"]["rule"][$i]['icmptype'] = "timereq";
                                        break;
                                    case "timestrep":
                                        $config["filter"]["rule"][$i]['icmptype'] = "timerep";
                                        break;
                                    case "inforeq":
                                        $config["filter"]["rule"][$i]['icmptype'] = "inforeq";
                                        break;
                                    case "inforep":
                                        $config["filter"]["rule"][$i]['icmptype'] = "inforep";
                                        break;
                                    case "maskreq":
                                        $config["filter"]["rule"][$i]['icmptype'] = "maskreq";
                                        break;
                                    case "maskrep":
                                        $config["filter"]["rule"][$i]['icmptype'] = "maskrep";
                                        break;
                                }
                            }
                        }
                        write_config();
                        convert_config();
                        $savemsg = gettext("The m0n0wall configuration has been restored and upgraded to OPNsense.");
                    }
                    setup_serial_port();
                } else {
                    $input_errors[] = gettext("The configuration could not be restored.");
                }
            }
        }
    } elseif ( $mode == "setup_gdrive" ){
        if (!isset($config['system']['remotebackup'])) {
            $config['system']['remotebackup'] = array() ;
        }
        $config['system']['remotebackup']['GDriveEnabled'] = $_POST['GDriveEnabled'];
        $config['system']['remotebackup']['GDriveEmail'] =   $_POST['GDriveEmail'] ;
        $config['system']['remotebackup']['GDriveFolderID'] = $_POST['GDriveFolderID'];
        $config['system']['remotebackup']['GDrivePassword'] = $_POST['GDrivePassword'];
        if (is_numeric($_POST['GDriveBackupCount'])) {
            $config['system']['remotebackup']['GDriveBackupCount'] = $_POST['GDriveBackupCount'];
        } else {
            $config['system']['remotebackup']['GDriveBackupCount'] = 30;
        }

        if ( $_POST['GDrivePasswordConfirm'] != $_POST['GDrivePassword'] ) {
            // log error, but continue
            $input_errors[] = gettext("The supplied 'Password' and 'Confirm' field values must match.");
        }

        if (is_uploaded_file($_FILES['GDriveP12file']['tmp_name'])) {
            $data = file_get_contents($_FILES['GDriveP12file']['tmp_name']);
            $config['system']['remotebackup']['GDriveP12key'] = base64_encode($data);
        } elseif ($config['system']['remotebackup']['GDriveEnabled'] != "on") {
            unset($config['system']['remotebackup']['GDriveP12key']);
        }

        write_config();
        // test / perform backup
        try {
            $filesInBackup = backup_to_google_drive() ;
            $cron_job = "/usr/local/opnsense/scripts/remote_backup.php";
            if (!cron_job_exists($cron_job)) {
                // initial cron job install
                install_cron_job($cron_job,true,0,1);
            }
        } catch (Exception $e) {
            $filesInBackup = array() ;
        }

        if (count($filesInBackup) == 0) {
             $input_errors[] = gettext("Google Drive communication failure");
        } else {
             $input_messages = gettext("Backup succesfull, current filelist:");
            foreach ($filesInBackup as $filename => $file) {
                 $input_messages = $input_messages . "<br>" . $filename ;
            }
        }
    }
}

include("head.inc");
?>

<body>
<?php include("fbegin.inc"); ?>

<script type="text/javascript">
//<![CDATA[
$( document ).ready(function() {
    // show encryption password
    $("#encryptconf").change(function(event){
        event.preventDefault();
        if ($("#encryptconf").prop('checked')) {
            $("#encrypt_opts").removeClass("hidden");
        } else {
            $("#encrypt_opts").addClass("hidden");
        }
    });

    // show decryption password
    $("#decryptconf").change(function(event){
        event.preventDefault();
        if ($("#decryptconf").prop('checked')) {
            $("#decrypt_opts").removeClass("hidden");
        } else {
            $("#decrypt_opts").addClass("hidden");
        }
    });

    $("#backuparea").change(function(event){
        if ($("#backuparea").val() == "rrddata") {
            $("#dotnotbackuprrd").prop('disabled', true);
        } else {
            $("#dotnotbackuprrd").prop('disabled', false);
        }
    });

});


function decrypt_change() {
    if (!document.iform.decrypt.checked) {
        document.getElementById("decrypt_opts").style.display="none";
    } else {
        document.getElementById("decrypt_opts").style.display="";
    }
}
//]]>
</script>

<form action="diag_backup.php" method="post">
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (isset($savemsg)) print_info_box($savemsg); ?>
        <?php if (is_subsystem_dirty('restore')): ?><br/>
        <form action="reboot.php" method="post">
        <input name="Submit" type="hidden" value="Yes" />
        <?php print_info_box(gettext("The firewall configuration has been changed.") . "<br />" . gettext("The firewall is now rebooting."));?><br />
        </form>
        <?php endif; ?>
        <?php if ($input_messages) print_info_box($input_messages); ?>
        <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
        <section class="col-xs-12">
          <section class="__mb">
            <div class="content-box">
              <header class="content-box-head container-fluid">
                <h3><?=gettext('Download')?></h3>
              </header>
              <div class="content-box-main">
                <div class="table-responsive">
                  <table class="table table-striped">
                    <tbody>
                      <tr>
                        <td>
                          <?=gettext("Click this button to download the system configuration in XML format."); ?><br /><br />
                          <?=gettext("Backup area:");?>
                          <select name="backuparea" id="backuparea">
                            <option value=""><?=gettext("ALL");?></option>
<?php
                          foreach($areas as $area => $areaname):
                              if($area !== "rrddata" && (!isset($config[$area]) || !is_array($config[$area]))) {
                                  continue;
                              };?>
                            <option value="<?=$area;?>"><?=$areaname;?></option>
<?php
                          endforeach;?>
                          </select>
                      </tr>
                      <tr>
                        <td>
                          <input name="encrypt" type="checkbox" id="encryptconf" />
                          <?=gettext("Encrypt this configuration file."); ?><br/>
                          <input name="donotbackuprrd" type="checkbox" id="dotnotbackuprrd" checked="checked" />
                          <?=gettext("Do not backup RRD data (NOTE: RRD Data can consume 4+ megabytes of config.xml space!)"); ?>
                          <div class="hidden table-responsive" id="encrypt_opts">
                            <table class="table table-condensed">
                                <tr>
                                  <td><?=gettext("Password:"); ?></td>
                                  <td><input name="encrypt_password" type="password" value="" /></td>
                                </tr>
                                <tr>
                                  <td><?=gettext("confirm:"); ?></td>
                                  <td><input name="encrypt_passconf" type="password" value="" /> </td>
                                </tr>
                            </table>
                          </div>
                          <hr/>
                          <input name="download" type="submit" class="btn btn-primary __mt" value="<?=gettext("Download configuration"); ?>" />
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </section>
          <section class="__mb">
            <div class="content-box">
              <header class="content-box-head container-fluid">
                <h3><?=gettext("Restore"); ?></h3>
              </header>
              <div class="content-box-main ">
                <div class="table-responsive">
                  <table class="table table-striped">
                    <tbody>
                      <tr>
                        <td>
                          <?=gettext("Open a"); ?> <?=$g['[product_name']?> <?=gettext("configuration XML file and click the button below to restore the configuration."); ?>
                          <br /><br />
                          <?=gettext("Restore area:"); ?>
                          <select name="restorearea" id="restorearea">
                            <option value=""><?=gettext("ALL");?></option>
<?php
                          foreach($areas as $area => $areaname):?>
                            <option value="<?=$area;?>"><?=$areaname;?></option>
<?php
                          endforeach;?>
                          </select>
                        </td>
                      </tr>
                      <tr>
                        <td>
                          <input name="conffile" type="file" id="conffile" />
                          <input name="decrypt" type="checkbox" id="decryptconf"/>
                          <?=gettext("Configuration file is encrypted."); ?>
                          <div class="hidden table-responsive" id="decrypt_opts">
                            <table class="table table-condensed">
                                <tr>
                                  <td><?=gettext("Password:"); ?></td>
                                  <td><input name="decrypt_password" type="password" value="" /></td>
                                </tr>
                                <tr>
                                  <td><?=gettext("confirm:"); ?></td>
                                  <td><input name="decrypt_passconf" type="password" value="" /> </td>
                                </tr>
                            </table>
                          </div>
                          <hr/>
                          <input name="restore" type="submit" class="btn btn-primary" id="restore" value="<?=gettext("Restore configuration"); ?>" />
                          <hr/>
                          <p><strong><span class="text-danger"><?=gettext("Note:"); ?> <?=gettext("The firewall will reboot after restoring the configuration."); ?></span></strong></p>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </section>
        <section class="__mb">
          <div class="content-box">
            <header class="content-box-head container-fluid">
              <h3><?=gettext("Google Drive"); ?></h3>
            </header>
            <div class="content-box-main ">
              <div class="table-responsive">
                <table class="table table-striped ">
                  <thead>
                    <th class="col-sm-1"></th>
                    <th class="col-sm-3"></th>
                  </thead>
                  <tbody>
                   <tr>
                     <td><?=gettext("Enable"); ?> </td>
                     <td>
                       <input name="GDriveEnabled" type="checkbox" <?=!empty($pconfig['GDriveEnabled']) ? "checked" : "";?> >
                     </td>
                   </tr>
                   <tr>
                     <td><?=gettext("Email Address"); ?> </td>
                     <td>
                       <input name="GDriveEmail" value="<?=$pconfig['GDriveEmail'];?>" type="text">
                     </td>
                   </tr>
                   <tr>
                     <td><?=gettext("P12 key"); ?> <?=!empty($pconfig['GDriveP12key']) ? gettext("(replace)") : gettext("(not loaded)"); ?> </td>
                     <td>
                       <input name="GDriveP12file" type="file">
                     </td>
                   </tr>
                   <tr>
                     <td><?=gettext("Folder ID"); ?> </td>
                     <td>
                       <input name="GDriveFolderID" value="<?=$pconfig['GDriveFolderID'];?>" type="text">
                     </td>
                   </tr>
                   <tr>
                     <td><?=gettext("Backup Count"); ?> </td>
                     <td>
                       <input name="GDriveBackupCount" value="<?=$pconfig['GDriveBackupCount'];?>"  type="text">
                     </td>
                   </tr>
                   <tr>
                     <td colspan=2><?=gettext("Password protect your data"); ?> :</td>
                   </tr>
                   <tr>
                     <td><?=gettext("Password :"); ?></td>
                     <td>
                       <input name="GDrivePassword" type="password" value="<?=$pconfig['GDrivePassword'];?>" />
                     </td>
                   </tr>
                   <tr>
                     <td><?=gettext("Confirm :"); ?></td>
                     <td>
                       <input name="GDrivePasswordConfirm" type="password" value="<?=$pconfig['GDrivePassword'];?>" />
                     </td>
                   </tr>
                   <tr>
                     <td>
                       <input name="setup_gdrive" class="btn btn-primary" id="Gdrive" value="<?=gettext("Setup/Test Google Drive");?>" type="submit">
                     </td>
                     <td></td>
                   </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </section>
      </section>
    </div>
  </div>
</section>
</form>

<?php include("foot.inc"); ?>

<?php
if (is_subsystem_dirty('restore')) {
  system_reboot();
}
