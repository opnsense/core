<?php

/*
 * Copyright (C) 2014-2016 Deciso B.V.
 * Copyright (C) 2013 Dagorlad
 * Copyright (C) 2012 Jim Pingle <jimp@pfsense.org>
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
require_once("plugins.inc.d/ntpd.inc");

config_read_array('ntpd', 'gps');

$copy_fields = array('port', 'type', 'speed', 'nmea', 'fudge1', 'fudge2', 'stratum', 'prefer', 'noselect',
                     'flag1', 'flag2', 'flag3', 'flag4', 'subsec', 'refid', 'initcmd');
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pconfig = array();
    foreach ($copy_fields as $fieldname) {
        if (isset($config['ntpd']['gps'][$fieldname])) {
            $pconfig[$fieldname] = $config['ntpd']['gps'][$fieldname];
        } else {
            $pconfig[$fieldname] = null;
        }
    }
}  elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pconfig = $_POST;
    $input_errors = array();
    if (!empty($pconfig['stratum']) && ($pconfig['stratum'] > 17 || $pconfig['stratum'] < 0 || !is_numeric($pconfig['stratum']))) {
        $input_errors[] = gettext("Clock stratum must be a number in the range 0..16");
    }
    // swap prefer
    $pconfig['prefer'] = empty($pconfig['prefer']) ? "on" : "";

    if (count($input_errors) == 0) {
        $gps = array();
        foreach ($copy_fields as $fieldname) {
            if (!empty($pconfig[$fieldname])) {
                $gps[$fieldname] = $pconfig[$fieldname];
            }
        }
        $gps['initcmd']= base64_encode($gps['initcmd']);
        $config['ntpd']['gps'] = $gps;
        write_config("Updated NTP GPS Settings");
        ntpd_configure_do();
        header(url_safe('Location: /services_ntpd_gps.php'));
        exit;
    }
}

$service_hook = 'ntpd';
legacy_html_escape_form_data($pconfig);
include("head.inc");
?>

<body>

<script>
//<![CDATA[
  $( document ).ready(function() {
    $("#gpsprefer").click(function(){
        if ($(this).prop('checked')) {
            $("#gpsselect").prop('checked', false);
        } else {
            $("#gpsselect").prop('checked', true);
        }
    });
    $("#gpsselect").click(function(){
        if ($(this).prop('checked')) {
            $("#gpsprefer").prop('checked', false);
        } else {
            $("#gpsprefer").prop('checked', true);
        }
    });
    $("#showgpsinitbox").click(function(event){
        $("#showgpsinitbox").parent().hide();
        $("#showgpsinit").show();
    });
  });
/*
init commands are Base64 encoded
Default =  #Sponsored, probably a Ublox
    $PUBX,40,GSV,0,0,0,0*59
    $PUBX,40,GLL,0,0,0,0*5C
    $PUBX,40,ZDA,0,0,0,0*44
    $PUBX,40,VTG,0,0,0,0*5E
    $PUBX,40,GSV,0,0,0,0*59
    $PUBX,40,GSA,0,0,0,0*4E
    $PUBX,40,GGA,0,0,0,0
    $PUBX,40,TXT,0,0,0,0
    $PUBX,40,RMC,0,0,0,0*46
    $PUBX,41,1,0007,0003,4800,0
    $PUBX,40,ZDA,1,1,1,1

Generic =          #do nothing

Garmin =  #most Garmin
    $PGRMC,,,,,,,,,,3,,2,4*52  #enable PPS @ 100ms
    $PGRMC1,,1,,,,,,W,,,,,,,*30  #enable WAAS
    $PGRMO,,3*74      #turn off all sentences
    $PGRMO,GPRMC,1*3D    #enable RMC
    $PGRMO,GPGGA,1*20    #enable GGA
    $PGRMO,GPGLL,1*26    #enable GLL

MediaTek =  #Adafruit, Fastrax, some Garmin and others
    $PMTK225,0*2B      #normal power mode
    $PMTK314,1,1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,1,0*28  #enable GLL, RMC, GGA and ZDA
    $PMTK301,2*2E      #enable WAAS
    $PMTK320,0*2F      #power save off
    $PMTK330,0*2E      #set WGS84 datum
    $PMTK386,0*23      #disable static navigation (MT333X)
    $PMTK397,0*23      #disable static navigation (MT332X)
    $PMTK251,4800*14    #4800 baud rate

SiRF =    #used by many devices
    $PSRF103,00,00,01,01*25    #turn on GGA
    $PSRF103,01,00,01,01*24    #turn on GLL
    $PSRF103,02,00,00,01*24    #turn off GSA
    $PSRF103,03,00,00,01*24    #turn off GSV
    $PSRF103,04,00,01,01*24    #turn on RMC
    $PSRF103,05,00,00,01*24    #turn off VTG
    $PSRF100,1,4800,8,1,0*0E  #set port to 4800,N,8,1

U-Blox =  #U-Blox 5, 6 and probably 7
    $PUBX,40,GGA,1,1,1,1,0,0*5A  #turn on GGA all ports
    $PUBX,40,GLL,1,1,1,1,0,0*5C  #turn on GLL all ports
    $PUBX,40,GSA,0,0,0,0,0,0*4E  #turn off GSA all ports
    $PUBX,40,GSV,0,0,0,0,0,0*59  #turn off GSV all ports
    $PUBX,40,RMC,1,1,1,1,0,0*47  #turn on RMC all ports
    $PUBX,40,VTG,0,0,0,0,0,0*5E  #turn off VTG all ports
    $PUBX,40,GRS,0,0,0,0,0,0*5D  #turn off GRS all ports
    $PUBX,40,GST,0,0,0,0,0,0*5B  #turn off GST all ports
    $PUBX,40,ZDA,1,1,1,1,0,0*44  #turn on ZDA all ports
    $PUBX,40,GBS,0,0,0,0,0,0*4D  #turn off GBS all ports
    $PUBX,40,DTM,0,0,0,0,0,0*46  #turn off DTM all ports
    $PUBX,40,GPQ,0,0,0,0,0,0*5D  #turn off GPQ all ports
    $PUBX,40,TXT,0,0,0,0,0,0*43  #turn off TXT all ports
    $PUBX,40,THS,0,0,0,0,0,0*54  #turn off THS all ports (U-Blox 6)
    $PUBX,41,1,0007,0003,4800,0*13  # set port 1 to 4800 baud

SureGPS =    #Sure Electronics SKG16B
    $PMTK225,0*2B
    $PMTK314,1,1,0,1,0,5,0,0,0,0,0,0,0,0,0,0,0,1,0*2D
    $PMTK301,2*2E
    $PMTK397,0*23
    $PMTK102*31
    $PMTK313,1*2E
    $PMTK513,1*28
    $PMTK319,0*25
    $PMTK527,0.00*00
    $PMTK251,9600*17  #really needs to work at 9600 baud

*/

  function set_gps_default() {
    //This handles a new config and also a reset to a defined default config
    var gpsdef = new Object();
    //stuff the JS object as needed for each type
    switch($("#gpstype").val()) {
      case "Default":
        gpsdef['nmea'] = 0;
        gpsdef['speed'] = 0;
        gpsdef['fudge1'] = "0.155";
        gpsdef['fudge2'] = "";
        gpsdef['inittxt'] = "JFBVQlgsNDAsR1NWLDAsMCwwLDAqNTkNCiRQVUJYLDQwLEdMTCwwLDAsMCwwKjVDDQokUFVCWCw0MCxaREEsMCwwLDAsMCo0NA0KJFBVQlgsNDAsVlRHLDAsMCwwLDAqNUUNCiRQVUJYLDQwLEdTViwwLDAsMCwwKjU5DQokUFVCWCw0MCxHU0EsMCwwLDAsMCo0RQ0KJFBVQlgsNDAsR0dBLDAsMCwwLDANCiRQVUJYLDQwLFRYVCwwLDAsMCwwDQokUFVCWCw0MCxSTUMsMCwwLDAsMCo0Ng0KJFBVQlgsNDEsMSwwMDA3LDAwMDMsNDgwMCwwDQokUFVCWCw0MCxaREEsMSwxLDEsMQ0K";
        break;

      case "Garmin":
        gpsdef['nmea'] = 0;
        gpsdef['speed'] = 0;
        gpsdef['fudge1'] = "";
        gpsdef['fudge2'] = "0.600";
        gpsdef['inittxt'] = "JFBHUk1DLCwsLCwsLCwsLDMsLDIsOCo1RQ0KJFBHUk1DMSwsMSwsLCwsLFcsLCwsLCwsKjMwDQokUEdSTU8sLDMqNzQNCiRQR1JNTyxHUFJNQywxKjNEDQokUEdSTU8sR1BHR0EsMSoyMA0KJFBHUk1PLEdQR0xMLDEqMjYNCg==";
        break;

      case "Generic":
        gpsdef['nmea'] = 0;
        gpsdef['speed'] = 0;
        gpsdef['fudge1'] = "";
        gpsdef['fudge2'] = "0.400";
        gpsdef['inittxt'] = "";
        break;

      case "MediaTek":
        gpsdef['nmea'] = 0;
        gpsdef['speed'] = 0;
        gpsdef['fudge1'] = "";
        gpsdef['fudge2'] = "0.400";
        gpsdef['inittxt'] = "JFBNVEsyMjUsMCoyQg0KJFBNVEszMTQsMSwxLDAsMSwwLDAsMCwwLDAsMCwwLDAsMCwwLDAsMCwwLDEsMCoyOA0KJFBNVEszMDEsMioyRQ0KJFBNVEszMjAsMCoyRg0KJFBNVEszMzAsMCoyRQ0KJFBNVEszODYsMCoyMw0KJFBNVEszOTcsMCoyMw0KJFBNVEsyNTEsNDgwMCoxNA0K";
        break;

      case "SiRF":
        gpsdef['nmea'] = 0;
        gpsdef['speed'] = 0;
        gpsdef['fudge1'] = "";
        gpsdef['fudge2'] = "0.704"; //valid for 4800, 0.688 @ 9600, 0.640 @ USB
        gpsdef['inittxt'] = "JFBTUkYxMDMsMDAsMDAsMDEsMDEqMjUNCiRQU1JGMTAzLDAxLDAwLDAxLDAxKjI0DQokUFNSRjEwMywwMiwwMCwwMCwwMSoyNA0KJFBTUkYxMDMsMDMsMDAsMDAsMDEqMjQNCiRQU1JGMTAzLDA0LDAwLDAxLDAxKjI0DQokUFNSRjEwMywwNSwwMCwwMCwwMSoyNA0KJFBTUkYxMDAsMSw0ODAwLDgsMSwwKjBFDQo=";
        break;

      case "U-Blox":
        gpsdef['nmea'] = 0;
        gpsdef['speed'] = 0;
        gpsdef['fudge1'] = "";
        gpsdef['fudge2'] = "0.400";
        gpsdef['inittxt'] = "JFBVQlgsNDAsR0dBLDEsMSwxLDEsMCwwKjVBDQokUFVCWCw0MCxHTEwsMSwxLDEsMSwwLDAqNUMNCiRQVUJYLDQwLEdTQSwwLDAsMCwwLDAsMCo0RQ0KJFBVQlgsNDAsR1NWLDAsMCwwLDAsMCwwKjU5DQokUFVCWCw0MCxSTUMsMSwxLDEsMSwwLDAqNDcNCiRQVUJYLDQwLFZURywwLDAsMCwwLDAsMCo1RQ0KJFBVQlgsNDAsR1JTLDAsMCwwLDAsMCwwKjVEDQokUFVCWCw0MCxHU1QsMCwwLDAsMCwwLDAqNUINCiRQVUJYLDQwLFpEQSwxLDEsMSwxLDAsMCo0NA0KJFBVQlgsNDAsR0JTLDAsMCwwLDAsMCwwKjREDQokUFVCWCw0MCxEVE0sMCwwLDAsMCwwLDAqNDYNCiRQVUJYLDQwLEdQUSwwLDAsMCwwLDAsMCo1RA0KJFBVQlgsNDAsVFhULDAsMCwwLDAsMCwwKjQzDQokUFVCWCw0MCxUSFMsMCwwLDAsMCwwLDAqNTQNCiRQVUJYLDQxLDEsMDAwNywwMDAzLDQ4MDAsMCoxMw0K";
        break;

      case "SureGPS":
        gpsdef['nmea'] = 1;
        gpsdef['speed'] = 16;
        gpsdef['fudge1'] = "";
        gpsdef['fudge2'] = "0.407";
        gpsdef['inittxt'] = "JFBNVEsyMjUsMCoyQg0KJFBNVEszMTQsMSwxLDAsMSwwLDUsMCwwLDAsMCwwLDAsMCwwLDAsMCwwLDEsMCoyRA0KJFBNVEszMDEsMioyRQ0KJFBNVEszOTcsMCoyMw0KJFBNVEsxMDIqMzENCiRQTVRLMzEzLDEqMkUNCiRQTVRLNTEzLDEqMjgNCiRQTVRLMzE5LDAqMjUNCiRQTVRLNTI3LDAuMDAqMDANCiRQTVRLMjUxLDk2MDAqMTcNCg==";
        break;
      default:
        return;
    }
    //then update the html and set the common stuff
    $("#gpsnmea").val(gpsdef['nmea']);
    $("#gpsspeed").val(gpsdef['speed']);
    $("#gpsfudge1").val(gpsdef['fudge1']);
    $("#gpsfudge2").val(gpsdef['fudge2']);
    $("#gpsstratum").val("");
    $("#gpsrefid").val("");
    $("#gpsspeed").val(gpsdef['speed']);
    $('#gpsflag1').prop('checked', true);
    $('#gpsflag2').prop('checked', false);
    $('#gpsflag3').prop('checked', true);
    $('#gpsflag4').prop('checked', false);
    $('#gpssubsec').prop('checked', false);
    $("#gpsinitcmd").val(atob(gpsdef['inittxt']));
    $("#gpsnmea").change();
    $('.selectpicker').selectpicker('refresh');
  }

  $( document ).ready(function() {
      $("#gpsnmea").change(function(){
          var nmea_id = 0;
          $("#gpsnmea option:selected").each(function(){
            nmea_id = nmea_id + parseInt($(this).val());
          });
          $("#nmea").val(nmea_id);
      });
      $("#gpstype").change(set_gps_default);

      // compute a NMEA checksum derived from the public domain function at http://www.hhhh.org/wiml/proj/nmeaxor.html
      $("#calcnmeachk").click(function(){
          var cmd = $("#nmeastring").val();
          // Compute the checksum by XORing all the character values in the string.
          var checksum = 0;
          for (var i = 0; i < cmd.length; i++) {
            checksum = checksum ^ cmd.charCodeAt(i);
          }
          // Convert it to hexadecimal (base-16, upper case, most significant byte first).
          var hexsum = Number(checksum).toString(16).toUpperCase();
          if (hexsum.length < 2) {
            hexsum = ("00" + hexsum).slice(-2);
          }
          // Display the result
          $("#nmeachecksum").text(hexsum);
      });
  });

//]]>
</script>

<?php include("fbegin.inc"); ?>
  <section class="page-content-main">
    <div class="container-fluid">
      <div class="row">
        <?php if (isset($input_errors) && count($input_errors) > 0) print_input_errors($input_errors); ?>
        <section class="col-xs-12">
          <div class="tab-content content-box col-xs-12">
            <form method="post" name="iform" id="iform">
              <div class="table-responsive">
                <table class="table table-striped opnsense_standard_table_form">
                  <thead>
                    <tr>
                      <td style="width:22%">
                        <strong><?=gettext("NTP Serial GPS Configuration"); ?></strong>
                      </td>
                      <td style="width:78%; text-align:right">
                        <small><?=gettext("full help"); ?> </small>
                        <i class="fa fa-toggle-off text-danger"  style="cursor: pointer;" id="show_all_help_page"></i>
                        &nbsp;&nbsp;
                      </td>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td><a id="help_for_gps" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext("GPS"); ?></td>
                      <td>
                        <!-- Start with the original "Default", list a "Generic" and then specific configs alphabetically -->
                        <select id="gpstype" name="type">
                          <option value="Generic" title="Generic"<?=empty($pconfig['type']) || $pconfig['type'] == 'Generic' ? " selected=\"selected\"" : "";?>><?=gettext('Generic') ?></option>
                          <option value="Default"<?=$pconfig['type'] == 'Default' ? " selected=\"selected\"" : ""; ?>><?=gettext('Default') ?></option>
                          <option value="Garmin" title="$PGRM... Most Garmin"<?=$pconfig['type'] == 'Garmin' ? " selected=\"selected\"" :"";?>><?=gettext('Garmin') ?></option>
                          <option value="MediaTek" title="$PMTK... Adafruit, Fastrax, some Garmin and others"<?=$pconfig['type'] == 'MediaTek' ? " selected=\"selected\"" :"";?>>MediaTek</option>
                          <option value="SiRF" title="$PSRF... Used by many devices"<?=$pconfig['type'] == 'SiRF' ? " selected=\"selected\"" :"";?>><?=gettext('SiRF') ?></option>
                          <option value="U-Blox" title="$PUBX... U-Blox 5, 6 and probably 7"<?=$pconfig['type'] == 'U-Blox' ? " selected=\"selected\"" : "";?>><?=gettext('U-Blox') ?></option>
                          <option value="SureGPS" title="$PMTK... Sure Electronics SKG16B"<?=$pconfig['type'] == 'SureGPS' ? " selected=\"selected\"" : "";?>><?=gettext('SureGPS') ?></option>
                          <option value="Custom"<?=$pconfig['type'] == 'Custom' ? " selected=\"selected\"" : ""; ?>><?=gettext('Custom') ?></option>
                        </select>
                        <div class="hidden" data-for="help_for_gps">
                          <?=gettext("This option allows you to select a predefined configuration.");?>
                          <br />
                          <strong><?=gettext("Note: ");?></strong><?=gettext("Select Generic if your GPS is not listed."); ?><br />
                          <strong><?=gettext("Note: ");?></strong><?=gettext("The predefined configurations assume your GPS has already been set to NMEA mode."); ?>
                        </div>
                      </td>
                    </tr>
<?php
                    /* Probing would be nice, but much more complex. Would need to listen to each port for 1s+ and watch for strings. */
                    $serialports = glob("/dev/cua?[0-9]{,.[0-9]}", GLOB_BRACE);
                    if (!empty($serialports)):?>
                    <tr>
                      <td><a id="help_for_gpsport" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Serial port') ?></td>
                      <td>
                        <select name="port" class="selectpicker">
                          <option value=""><?=gettext("none");?></option>
<?php
                        foreach ($serialports as $port):?>
                          <option value="<?=substr($port,5);?>" <?=substr($port,5) === $pconfig['port'] ? 'selected="selected"' : "";?>>
                            <?=substr($port,5);?>
                          </option>
<?php
                          endforeach; ?>
                        </select>
                        <div class="hidden" data-for="help_for_gpsport">
                          <?=gettext("All serial ports are listed, be sure to pick the port with the GPS attached."); ?>
                        </div>
                        <br/>
                        <?=gettext("Serial port baud rate."); ?><br />
                        <select id="gpsspeed" name="speed" class="selectpicker">
                          <option value="0" <?=empty($pconfig['speed']) ? " selected=\"selected\"" : ""; ?>>4800</option>
                          <option value="16" <?=$pconfig['speed'] === '16' ? " selected=\"selected\"" : "";?>>9600</option>
                          <option value="32" <?=$pconfig['speed'] === '32' ? " selected=\"selected\"" : "";?>>19200</option>
                          <option value="48" <?=$pconfig['speed'] === '48' ? " selected=\"selected\"" : "";?>>38400</option>
                          <option value="64" <?=$pconfig['speed'] === '64' ? " selected=\"selected\"" : "";?>>57600</option>
                          <option value="80" <?=$pconfig['speed'] === '80' ? " selected=\"selected\"" : "";?>>115200</option>
                        </select>
                        <div class="hidden" data-for="help_for_gpsport">
                          <?=gettext("Note: A higher baud rate is generally only helpful if the GPS is sending too many sentences. It is recommended to configure the GPS to send only one sentence at a baud rate of 4800 or 9600."); ?>
                        </div>
                      </td>
                    </tr>
<?php
                    endif;?>
                    <tr>
                      <!-- 1 = RMC, 2 = GGA, 4 = GLL, 8 = ZDA or ZDG -->
                      <td><a id="help_for_nmea" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('NMEA sentences') ?></td>
                      <td>
                        <input type="hidden" name="nmea" value="<?=$pconfig['nmea'];?>" id="nmea">
                        <select id="gpsnmea" multiple="multiple" class="selectpicker">
                          <option value="0" <?=empty($pconfig['nmea']) ? " selected=\"selected\"" : ""; ?>><?=gettext("All");?></option>
                          <option value="1" <?=intval($pconfig['nmea']) & 1 ? " selected=\"selected\"" : "";?>><?=gettext("RMC");?></option>
                          <option value="2" <?=intval($pconfig['nmea']) & 2 ? " selected=\"selected\"" : "";?>><?=gettext("GGA");?></option>
                          <option value="4" <?=intval($pconfig['nmea']) & 4 ? " selected=\"selected\"" : "";?>><?=gettext("GLL");?></option>
                          <option value="8" <?=intval($pconfig['nmea']) & 8 ? " selected=\"selected\"" : "";?>><?=gettext("ZDA or ZDG");?></option>
                        </select>
                        <div class="hidden" data-for="help_for_nmea">
                          <?=gettext("By default NTP will listen for all supported NMEA sentences. Here one or more sentences to listen for may be specified."); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_fudge1" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?= gettext('Fudge time 1 (seconds)') ?></td>
                      <td>
                        <input name="fudge1" type="text" id="gpsfudge1" min="-1" max="1" size="20" value="<?=$pconfig['fudge1'];?>" />
                        <div class="hidden" data-for="help_for_fudge1">
                          <?= gettext("Fudge time 1 is used to specify the GPS PPS signal offset (default: 0.0).") ?>
                        </div>
                    </tr>
                    <tr>
                      <td><a id="help_for_fudge2" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Fudge time 2 (seconds)');?></td>
                      <td>
                        <input name="fudge2" type="text" id="gpsfudge2" min="-1" max="1" size="20" value="<?=$pconfig['fudge2'];?>" />
                        <div class="hidden" data-for="help_for_fudge2">
                          <?= gettext("Fudge time 2 is used to specify the GPS time offset (default: 0.0).") ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_stratum" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Stratum') ?></td>
                      <td>
                        <input name="stratum" type="text" id="gpsstratum"  value="<?=$pconfig['stratum'];?>" />
                        <div class="hidden" data-for="help_for_stratum">
                          <?=gettext("(0-16)");?><br />
                          <?=gettext("This may be used to change the GPS Clock stratum (default: 0). This may be useful if, for some reason, you want ntpd to prefer a different clock"); ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext('Flags') ?></td>
                      <td>
                        <table class="table table-condensed">
                          <tr>
                            <td colspan="2">
                              <?=gettext("Normally there should be no need to change these options from the defaults."); ?><br />
                            </td>
                          </tr>
                          <tr>
                            <td>
                              <input name="prefer" type="checkbox" id="gpsprefer" <?=empty($pconfig['prefer']) ? " checked=\"checked\"" : ""; ?> />
                            </td>
                            <td>
                              <?=gettext("NTP should prefer this clock (default: enabled)."); ?>
                            </td>
                          </tr>
                          <tr>
                            <td>
                              <input name="noselect" type="checkbox" id="gpsselect" <?=!empty($pconfig['noselect']) ? " checked=\"checked\"" : ""; ?> />
                            </td>
                            <td>
                              <?=gettext("NTP should not use this clock, it will be displayed for reference only (default: disabled)."); ?>
                            </td>
                          </tr>
                          <tr>
                            <td>
                              <input name="flag1" type="checkbox" id="gpsflag1"<?=!empty($pconfig['flag1']) ? " checked=\"checked\"" : ""; ?> />
                            </td>
                            <td>
                              <?=gettext("Enable PPS signal processing (default: enabled)."); ?>
                            </td>
                          </tr>
                          <tr>
                            <td>
                              <input name="flag2" type="checkbox" id="gpsflag2"<?=!empty($pconfig['flag2']) ? " checked=\"checked\"" : ""; ?> />
                            </td>
                            <td>
                              <?=gettext("Enable falling edge PPS signal processing (default: rising edge)."); ?>
                            </td>
                          </tr>
                          <tr>
                            <td>
                              <input name="flag3" type="checkbox" id="gpsflag3"<?=!empty($pconfig['flag3']) ? " checked=\"checked\"" : ""; ?> />
                            </td>
                            <td>
                              <?=gettext("Enable kernel PPS clock discipline (default: enabled)."); ?>
                            </td>
                          </tr>
                          <tr>
                            <td>
                              <input name="flag4" type="checkbox" id="gpsflag4"<?=!empty($pconfig['flag4']) ? " checked=\"checked\"" : ""; ?> />
                            </td>
                            <td>
                              <?=gettext("Obscure location in timestamp (default: unobscured)."); ?>
                            </td>
                          </tr>
                          <tr>
                            <td>
                              <input name="subsec" type="checkbox" id="gpssubsec"<?=!empty($pconfig['subsec']) ? " checked=\"checked\"" : ""; ?> />
                            </td>
                            <td>
                              <?= gettext("Log the sub-second fraction of the received time stamp (default: Not logged).") ?>
                            </td>
                          </tr>
                        </table>
                      </td>
                    </tr>
                    <tr>
                      <td><a id="help_for_refid" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a> <?=gettext('Clock ID') ?></td>
                      <td>
                        <input name="refid" type="text" id="gpsrefid" value="<?=$pconfig['refid'];?>" />
                        <div class="hidden" data-for="help_for_refid">
                          <?=gettext("(1 to 4 characters)");?><br />
                          <?=gettext("This may be used to change the GPS Clock ID (default: GPS).") ?>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td><i class="fa fa-info-circle text-muted"></i> <?=gettext('GPS Initialization') ?></td>
                      <td>
                        <div >
                          <input class="btn btn-xs btn-default" type="button" id="showgpsinitbox" value="<?= html_safe(gettext('Advanced')) ?>" /> - <?=gettext("Show GPS Initialization commands");?>
                        </div>
                        <div id="showgpsinit" style="display:none">
                          <textarea name="initcmd" class="formpre" id="gpsinitcmd" cols="65" rows="7"><?=base64_decode($pconfig['initcmd']);?></textarea><br />
                          <?=gettext("Note: Commands entered here will be sent to the GPS during initialization. ".
                                     "Please read and understand your GPS documentation before making any changes here.");?><br /><br />
                          <strong><?=gettext("NMEA checksum calculator");?></strong><br /><br />
                          <?=gettext('Enter the text between "$" and "*" of a NMEA command string.');?><br /><br />
                          <div class="row" style="max-width: 348px">
                            <div class="col-xs-12">
                              <div class="input-group">
                                <input name="nmeastring" type="text" id="nmeastring" />
                                <span class="input-group-btn">
                                  <label class="btn btn-primary" id="calcnmeachk"><?= gettext('Calculate') ?></label>
                                </span>
                              </div>
                            </div>
                          </div><br />
                          <?= gettext("Checksum:") ?> <span id="nmeachecksum"><?=gettext("Please click the \"Calculate\" to get a result.");?></span>
                        </div>
                      </td>
                    </tr>
                    <tr>
                      <td>&nbsp;</td>
                      <td>
                      <input name="Submit" type="submit" class="btn btn-primary" value="<?=html_safe(gettext('Save'));?>" />
                      </td>
                    </tr>
                  </tbody>
                  <tfoot>
                    <tr>
                      <td colspan="2">
                        <?=gettext('A GPS connected via a serial port may be used as a reference clock for NTP. If the GPS also supports PPS and is properly configured, and connected, that GPS may also be used as a Pulse Per Second clock reference. NOTE: a USB GPS may work, but is not recommended due to USB bus timing issues.') ?>
                        <br />
                        <br /><?=sprintf(gettext("For the best results, NTP should have at least three sources of time. So it is best to configure at least 2 servers under %sServices: Network Time%s to minimize clock drift if the GPS data is not valid over time. Otherwise ntpd may only use values from the unsynchronized local clock when providing time to clients."),'<a href="services_ntpd.php">','</a>') ?>
                      </td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </form>
          </div>
        </section>
      </div>
    </div>
  </section>
<?php include("foot.inc"); ?>
