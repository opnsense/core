{#
 # Copyright (C) 2026 Greelan
 # Copyright (C) 2014-2016 Deciso B.V.
 # Copyright (C) 2013 Dagorlad
 # Copyright (C) 2012 Jim Pingle <jimp@pfsense.org>
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1. Redistributions of source code must retain the above copyright notice,
 #    this list of conditions and the following disclaimer.
 #
 # 2. Redistributions in binary form must reproduce the above copyright notice,
 #    this list of conditions and the following disclaimer in the documentation
 #    and/or other materials provided with the distribution.
 #
 # THIS SOFTWARE IS PROVIDED "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
 # INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 # AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 # AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 # OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 # SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 # INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 # CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 # ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 # POSSIBILITY OF SUCH DAMAGE.
 #}

<script>
    /*
     * Per-type GPS defaults. nmea is an array of sentence bits for the multi-select.
     *
     * Init commands (stored as plaintext, legacy used base64):
     *
     * Default (sponsored, probably a U-Blox):
     *   $PUBX,40,GSV,0,0,0,0*59       turn off GSV
     *   $PUBX,40,GLL,0,0,0,0*5C       turn off GLL
     *   $PUBX,40,ZDA,0,0,0,0*44       turn off ZDA
     *   $PUBX,40,VTG,0,0,0,0*5E       turn off VTG
     *   $PUBX,40,GSV,0,0,0,0*59       turn off GSV (repeated)
     *   $PUBX,40,GSA,0,0,0,0*4E       turn off GSA
     *   $PUBX,40,GGA,0,0,0,0          turn off GGA
     *   $PUBX,40,TXT,0,0,0,0          turn off TXT
     *   $PUBX,40,RMC,0,0,0,0*46       turn off RMC
     *   $PUBX,41,1,0007,0003,4800,0   set port 1 to 4800 baud
     *   $PUBX,40,ZDA,1,1,1,1          turn on ZDA all ports
     *
     * Garmin ($PGRM, most Garmin):
     *   $PGRMC,,,,,,,,,,3,,2,8*5E     enable PPS @ 100ms
     *   $PGRMC1,,1,,,,,,W,,,,,,,*30   enable WAAS
     *   $PGRMO,,3*74                  turn off all sentences
     *   $PGRMO,GPRMC,1*3D             enable RMC
     *   $PGRMO,GPGGA,1*20             enable GGA
     *   $PGRMO,GPGLL,1*26             enable GLL
     *
     * MediaTek ($PMTK, Adafruit, Fastrax, some Garmin):
     *   $PMTK225,0*2B                 normal power mode
     *   $PMTK314,1,1,0,1,...*28       enable GLL, RMC, GGA and ZDA
     *   $PMTK301,2*2E                 enable WAAS
     *   $PMTK320,0*2F                 power save off
     *   $PMTK330,0*2E                 set WGS84 datum
     *   $PMTK386,0*23                 disable static navigation (MT333X)
     *   $PMTK397,0*23                 disable static navigation (MT332X)
     *   $PMTK251,4800*14              4800 baud rate
     *
     * SiRF ($PSRF, used by many devices):
     *   fudge2: 0.704 at 4800, 0.688 at 9600, 0.640 at USB
     *   $PSRF103,00,00,01,01*25       turn on GGA
     *   $PSRF103,01,00,01,01*24       turn on GLL
     *   $PSRF103,02,00,00,01*24       turn off GSA
     *   $PSRF103,03,00,00,01*24       turn off GSV
     *   $PSRF103,04,00,01,01*24       turn on RMC
     *   $PSRF103,05,00,00,01*24       turn off VTG
     *   $PSRF100,1,4800,8,1,0*0E      set port to 4800,N,8,1
     *
     * U-Blox ($PUBX, U-Blox 5, 6, 7):
     *   $PUBX,40,GGA,1,1,1,1,0,0*5A   turn on GGA all ports
     *   $PUBX,40,GLL,1,1,1,1,0,0*5C   turn on GLL all ports
     *   $PUBX,40,GSA,0,0,0,0,0,0*4E   turn off GSA all ports
     *   $PUBX,40,GSV,0,0,0,0,0,0*59   turn off GSV all ports
     *   $PUBX,40,RMC,1,1,1,1,0,0*47   turn on RMC all ports
     *   $PUBX,40,VTG,0,0,0,0,0,0*5E   turn off VTG all ports
     *   $PUBX,40,GRS,0,0,0,0,0,0*5D   turn off GRS all ports
     *   $PUBX,40,GST,0,0,0,0,0,0*5B   turn off GST all ports
     *   $PUBX,40,ZDA,1,1,1,1,0,0*44   turn on ZDA all ports
     *   $PUBX,40,GBS,0,0,0,0,0,0*4D   turn off GBS all ports
     *   $PUBX,40,DTM,0,0,0,0,0,0*46   turn off DTM all ports
     *   $PUBX,40,GPQ,0,0,0,0,0,0*5D   turn off GPQ all ports
     *   $PUBX,40,TXT,0,0,0,0,0,0*43   turn off TXT all ports
     *   $PUBX,40,THS,0,0,0,0,0,0*54   turn off THS all ports (U-Blox 6)
     *   $PUBX,41,1,0007,0003,4800,0*13 set port 1 to 4800 baud
     *
     * SureGPS ($PMTK, Sure Electronics SKG16B):
     *   nmea=1 (RMC only), needs 9600 baud
     *   $PMTK225,0*2B                 normal power mode
     *   $PMTK314,1,1,0,1,...*2D       enable GLL, RMC, GGA, GSV and ZDA
     *   $PMTK301,2*2E                 enable WAAS
     *   $PMTK397,0*23                 disable static navigation
     *   $PMTK102*31                   warm start
     *   $PMTK313,1*2E                 enable SBAS
     *   $PMTK513,1*28                 enable SBAS ranging
     *   $PMTK319,0*25                 set SBAS to auto mode
     *   $PMTK527,0.00*00             set antenna swap threshold
     *   $PMTK251,9600*17             9600 baud rate
     */
    const gpsDefaults = {
        'default': {nmea: [], speed: '0', fudge1: '0.155', fudge2: '', initcmd:
            '$PUBX,40,GSV,0,0,0,0*59\n$PUBX,40,GLL,0,0,0,0*5C\n$PUBX,40,ZDA,0,0,0,0*44\n' +
            '$PUBX,40,VTG,0,0,0,0*5E\n$PUBX,40,GSV,0,0,0,0*59\n$PUBX,40,GSA,0,0,0,0*4E\n' +
            '$PUBX,40,GGA,0,0,0,0\n$PUBX,40,TXT,0,0,0,0\n$PUBX,40,RMC,0,0,0,0*46\n' +
            '$PUBX,41,1,0007,0003,4800,0\n$PUBX,40,ZDA,1,1,1,1\n'},
        'garmin':  {nmea: [], speed: '0', fudge1: '', fudge2: '0.600', initcmd:
            '$PGRMC,,,,,,,,,,3,,2,8*5E\n$PGRMC1,,1,,,,,,W,,,,,,,*30\n$PGRMO,,3*74\n' +
            '$PGRMO,GPRMC,1*3D\n$PGRMO,GPGGA,1*20\n$PGRMO,GPGLL,1*26\n'},
        'mediatek': {nmea: [], speed: '0', fudge1: '', fudge2: '0.400', initcmd:
            '$PMTK225,0*2B\n$PMTK314,1,1,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,1,0*28\n' +
            '$PMTK301,2*2E\n$PMTK320,0*2F\n$PMTK330,0*2E\n$PMTK386,0*23\n' +
            '$PMTK397,0*23\n$PMTK251,4800*14\n'},
        'sirf':    {nmea: [], speed: '0', fudge1: '', fudge2: '0.704', initcmd:
            '$PSRF103,00,00,01,01*25\n$PSRF103,01,00,01,01*24\n$PSRF103,02,00,00,01*24\n' +
            '$PSRF103,03,00,00,01*24\n$PSRF103,04,00,01,01*24\n$PSRF103,05,00,00,01*24\n' +
            '$PSRF100,1,4800,8,1,0*0E\n'},
        'ublox':   {nmea: [], speed: '0', fudge1: '', fudge2: '0.400', initcmd:
            '$PUBX,40,GGA,1,1,1,1,0,0*5A\n$PUBX,40,GLL,1,1,1,1,0,0*5C\n' +
            '$PUBX,40,GSA,0,0,0,0,0,0*4E\n$PUBX,40,GSV,0,0,0,0,0,0*59\n' +
            '$PUBX,40,RMC,1,1,1,1,0,0*47\n$PUBX,40,VTG,0,0,0,0,0,0*5E\n' +
            '$PUBX,40,GRS,0,0,0,0,0,0*5D\n$PUBX,40,GST,0,0,0,0,0,0*5B\n' +
            '$PUBX,40,ZDA,1,1,1,1,0,0*44\n$PUBX,40,GBS,0,0,0,0,0,0*4D\n' +
            '$PUBX,40,DTM,0,0,0,0,0,0*46\n$PUBX,40,GPQ,0,0,0,0,0,0*5D\n' +
            '$PUBX,40,TXT,0,0,0,0,0,0*43\n$PUBX,40,THS,0,0,0,0,0,0*54\n' +
            '$PUBX,41,1,0007,0003,4800,0*13\n'},
        'suregps': {nmea: ['1'], speed: '16', fudge1: '', fudge2: '0.407', initcmd:
            '$PMTK225,0*2B\n$PMTK314,1,1,0,1,0,5,0,0,0,0,0,0,0,0,0,0,0,1,0*2D\n' +
            '$PMTK301,2*2E\n$PMTK397,0*23\n$PMTK102*31\n$PMTK313,1*2E\n' +
            '$PMTK513,1*28\n$PMTK319,0*25\n$PMTK527,0.00*00\n$PMTK251,9600*17\n'},
        'generic': {nmea: [], speed: '0', fudge1: '', fudge2: '0.400', initcmd: ''}
    };

    function applyGpsDefaults() {
        const gpsType = $('#ntpd\\.gps\\.type').val();
        const preset = gpsDefaults[gpsType];
        $('#ntpd\\.gps\\.nmea').val(preset.nmea);
        $('#ntpd\\.gps\\.speed').val(preset.speed);
        $('#ntpd\\.gps\\.fudge1').val(preset.fudge1);
        $('#ntpd\\.gps\\.fudge2').val(preset.fudge2);
        $('#ntpd\\.gps\\.initcmd').val(preset.initcmd);
        $('#ntpd\\.gps\\.stratum').val('');
        $('#ntpd\\.gps\\.refid').val('');
        $('#ntpd\\.gps\\.flag1').prop('checked', true);
        $('#ntpd\\.gps\\.flag2').prop('checked', false);
        $('#ntpd\\.gps\\.flag3').prop('checked', true);
        $('#ntpd\\.gps\\.flag4').prop('checked', false);
        $('#ntpd\\.gps\\.subsec').prop('checked', false);
        $('.selectpicker').selectpicker('refresh');
    }

    $( document ).ready(function() {
        let data_get_map = {'frm_settings':"/api/ntpd/settings/get"};

        /* keep the leap second file chooser and the checksum calculator with the field they fill */
        $('#leapfile_box').detach().insertAfter($('#ntpd\\.leapsec'));
        $('#nmeachk_box').detach().insertAfter($('#ntpd\\.gps\\.initcmd'));
        mapDataToFormUI(data_get_map).done(function(){
            /* vendor hints next to the GPS types in the list */
            const gpsTypeHints = {
                'garmin': '{{ lang._('$PGRM... Most Garmin') }}',
                'mediatek': '{{ lang._('$PMTK... Adafruit, Fastrax, some Garmin and others') }}',
                'sirf': '{{ lang._('$PSRF... Used by many devices') }}',
                'ublox': '{{ lang._('$PUBX... U-Blox 5, 6 and probably 7') }}',
                'suregps': '{{ lang._('$PMTK... Sure Electronics SKG16B') }}'
            };
            $('#ntpd\\.gps\\.type option').each(function() {
                const hint = gpsTypeHints[$(this).val()];
                if (hint) $(this).attr('data-subtext', hint);
            });
            $('.selectpicker').selectpicker('refresh');
            updateServiceControlUI('ntpd');
            updateClientModeAlert();

            /* hide serial port settings when no serial ports are detected (only the "None" option) */
            if ($('#ntpd\\.gps\\.port option').length <= 1) {
                $('#ntpd\\.gps\\.port').closest('tr').hide();
                $('#ntpd\\.gps\\.speed').closest('tr').hide();
            }
            if ($('#ntpd\\.pps\\.port option').length <= 1) {
                $('#ntpd\\.pps\\.port').closest('tr').hide();
            }

            /* bind after loading, setFormData triggers change on every field */
            $('#ntpd\\.clientmode').change(updateClientModeAlert);

            /* preferring the GPS clock and not using it are mutually exclusive */
            $('#ntpd\\.gps\\.prefer').change(function() {
                $('#ntpd\\.gps\\.noselect').prop('checked', !$(this).is(':checked'));
            });
            $('#ntpd\\.gps\\.noselect').change(function() {
                $('#ntpd\\.gps\\.prefer').prop('checked', !$(this).is(':checked'));
            });

            let previousGpsType = $('#ntpd\\.gps\\.type').val();
            $('#ntpd\\.gps\\.type').change(function() {
                const sel = $(this);
                const newType = sel.val();
                if (newType === 'custom') {
                    /* Custom keeps current field values as-is */
                    previousGpsType = newType;
                    return;
                }
                BootstrapDialog.confirm({
                    title: '{{ lang._('GPS type changed') }}',
                    message: '{{ lang._('Reset fields to the defaults for this GPS type?') }}',
                    type: BootstrapDialog.TYPE_WARNING,
                    btnOKLabel: '{{ lang._('Yes') }}',
                    btnOKClass: 'btn-warning',
                    btnCancelLabel: '{{ lang._('Cancel') }}',
                    callback: function(result) {
                        if (result) {
                            applyGpsDefaults();
                            previousGpsType = newType;
                        } else {
                            sel.val(previousGpsType);
                            sel.selectpicker('refresh');
                        }
                    }
                });
            });
        });

        /* init the server grid when its tab is first shown */
        let serverGridInitialized = false;
        function initServerGrid() {
            if (serverGridInitialized) return;
            serverGridInitialized = true;
            $("#{{formGridServer['table_id']}}").UIBootgrid({
                'search':'/api/ntpd/settings/searchServer/',
                'get':'/api/ntpd/settings/getServer/',
                'set':'/api/ntpd/settings/setServer/',
                'add':'/api/ntpd/settings/addServer/',
                'del':'/api/ntpd/settings/delServer/',
                'toggle':'/api/ntpd/settings/toggleServer/'
            });
        }

        /* enforce pool checkbox for *.pool.ntp.org hostnames, restore the previous choice for other names */
        function updatePoolField() {
            let hostname = $("#server\\.hostname").val() || '';
            let isPool = /\.pool\.ntp\.org$/.test(hostname);
            let $pool = $("#server\\.pool");
            if (isPool && !$pool.prop('disabled')) {
                $pool.data('previous', $pool.prop('checked'));
                $pool.prop('checked', true).prop('disabled', true);
            } else if (!isPool && $pool.prop('disabled')) {
                $pool.prop('checked', $pool.data('previous') === true).prop('disabled', false);
            }
        }
        $("#{{formGridServer['edit_dialog_id']}}").on('shown.bs.modal', function() {
            updatePoolField();
            /* a stored pool.ntp.org name holds no choice of its own */
            $("#server\\.pool").data('previous', false);
            $("#server\\.hostname").on('input.poolcheck', updatePoolField);
        }).on('hidden.bs.modal', function() {
            $("#server\\.hostname").off('input.poolcheck');
            $("#server\\.pool").prop('disabled', false);
        });

        function updateClientModeAlert() {
            if ($('#ntpd\\.clientmode').is(':checked')) {
                $('#clientmode_alert').show();
            } else {
                $('#clientmode_alert').hide();
            }
        }

        /* NMEA checksum calculator (XOR of characters between $ and *) */
        $('#calcnmeachk').click(function() {
            const cmd = $('#nmeastring').val() || '';
            let checksum = 0;
            for (let i = 0; i < cmd.length; i++) {
                checksum ^= cmd.charCodeAt(i);
            }
            let hexsum = checksum.toString(16).toUpperCase();
            if (hexsum.length < 2) {
                hexsum = ('00' + hexsum).slice(-2);
            }
            $('#nmeachecksum').text(hexsum);
        });

        /* leap second configuration file upload: read the file client-side and populate the leapsec textarea */
        $('#leapfile').on('change', function() {
            const file = this.files && this.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#ntpd\\.leapsec').val(e.target.result);
            };
            reader.readAsText(file);
        });

        $("#reconfigureAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();
                saveFormToEndpoint("/api/ntpd/settings/set", 'form_root', function () { dfObj.resolve(); }, false, function () {
                    /* show the tab holding the first invalid field */
                    const pane = $('#form_root .has-error').first().closest('.tab-pane').attr('id');
                    if (pane) {
                        $('#maintabs a[href="#' + pane + '"]').tab('show');
                    }
                    dfObj.reject();
                });
                return dfObj;
            }
        });

        /* update URL hash on tab change and lazy-init grids */
        $('#maintabs a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            history.replaceState(null, null, e.target.hash);
            if (e.target.hash === '#servers') {
                initServerGrid();
            }
        });
        /* activate tab defined in URL hash, also when only the hash changes (menu entries) */
        function showHashTab() {
            if (window.location.hash) {
                $('#maintabs a[href="' + window.location.hash + '"]').tab('show');
            }
        }
        $(window).on('hashchange', showHashTab);
        showHashTab();
        /* inline tab links within content */
        $(document).on('click', '.inline-tab-link', function(e) {
            e.preventDefault();
            $('#maintabs a[href="' + $(this).attr('href') + '"]').tab('show');
        });
    });
</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#general">{{ lang._('General') }}</a></li>
    <li><a data-toggle="tab" href="#servers">{{ lang._('Time Servers') }}</a></li>
    <li><a data-toggle="tab" href="#gps">{{ lang._('GPS') }}</a></li>
    <li><a data-toggle="tab" href="#pps">{{ lang._('PPS') }}</a></li>
</ul>

<div class="tab-content content-box" id="form_root">
    <!-- General -->
    <div id="general" class="tab-pane fade in active">
        <div id="clientmode_alert" class="alert alert-info" role="alert" style="display: none; margin: 16px 16px 0 16px;">
            <b>{{ lang._('Client mode is active:') }}</b>
            {{ lang._('The NTP daemon will sync the clock once and then exit. Its service status is not checked in this mode. A cron job restarts it daily.') }}
        </div>
        {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_settings-general'])}}
        <div id="leapfile_box">
            <br/>
            <label for="leapfile">{{ lang._('Or select a leap second configuration file to upload:') }}</label>
            <input type="file" id="leapfile"/>
        </div>
    </div>
    <!-- Time Servers -->
    <div id="servers" class="tab-pane fade">
        {{ partial("layout_partials/base_bootgrid_table", formGridServer)}}
        <div style="padding: 10px;">
            {{ lang._('For best results, three to five servers should be configured here.') }}
            {{ lang._('When no servers are specified and no GPS or PPS clock is configured, NTP will be completely disabled.') }}
        </div>
    </div>
    <!-- GPS -->
    <div id="gps" class="tab-pane fade">
        {{ partial("layout_partials/base_form",['fields':gpsForm,'id':'frm_settings-gps'])}}
        <hr/>
        <div style="padding: 10px;">
            {{ lang._('A GPS connected via a serial port may be used as a reference clock for NTP. If the GPS also supports PPS and is properly configured and connected, that GPS may also be used as a Pulse Per Second clock reference. NOTE: a USB GPS may work, but is not recommended due to USB bus timing issues.') }}
            <br/><br/>
            {{ lang._('For the best results, NTP should have at least three sources of time. So it is best to configure at least 2 servers under %sTime Servers%s to minimize clock drift if the GPS data is not valid over time. Otherwise ntpd may only use values from the unsynchronized local clock when providing time to clients.')|format('<a href="#servers" class="inline-tab-link">', '</a>') }}
        </div>
        <div id="nmeachk_box">
            <br/>
            <strong>{{ lang._('NMEA checksum calculator') }}</strong><br/><br/>
            {{ lang._('Enter the text between "$" and "*" of a NMEA command string.') }}<br/><br/>
            <div class="row" style="max-width: 348px;">
                <div class="col-xs-12">
                    <div class="input-group">
                        <input name="nmeastring" type="text" id="nmeastring"/>
                        <span class="input-group-btn">
                            <label class="btn btn-primary" id="calcnmeachk">{{ lang._('Calculate') }}</label>
                        </span>
                    </div>
                </div>
            </div>
            <br/>
            {{ lang._('Checksum:') }} <span id="nmeachecksum">{{ lang._('Please click "Calculate" to get a result.') }}</span>
        </div>
    </div>
    <!-- PPS -->
    <div id="pps" class="tab-pane fade">
        {{ partial("layout_partials/base_form",['fields':ppsForm,'id':'frm_settings-pps'])}}
        <hr/>
        <div style="padding: 10px;">
            {{ lang._('Devices with a Pulse Per Second output such as radios that receive a time signal from DCF77 (DE), JJY (JP), MSF (GB) or WWVB (US) may be used as a PPS reference for NTP.') }}
            {{ lang._('A serial GPS may also be used, but the serial GPS driver would usually be the better option.') }}
            {{ lang._('A PPS signal only provides a reference to the change of a second, so at least one other source to number the seconds is required.') }}
            <br/><br/>
            <strong>{{ lang._('Note') }}:</strong>
            {{ lang._('At least 3 servers should be configured under %sTime Servers%s to reliably supply the time of each PPS pulse.')|format('<a href="#servers" class="inline-tab-link">', '</a>') }}
        </div>
    </div>
</div>
{{ partial("layout_partials/base_dialog",['fields':formDialogEditServer,'id':formGridServer['edit_dialog_id'],'label':lang._('Edit time server')])}}
{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/ntpd/service/reconfigure', 'data_service_widget': 'ntpd', 'data_error_title': lang._('Error reconfiguring NTP daemon')}) }}
