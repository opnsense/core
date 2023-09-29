<?php

/*
 * Copyright (C) 2020-2023 Deciso B.V.
 * Copyright (C) 2020 D. Domig
 * Copyright (C) 2022 Patrik Kernstock <patrik@kernstock.net>
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
 * THIS SOFTWARE IS PROVIDED "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
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
?>

<table class="table table-striped table-condensed" id="wg-table">
    <thead>
        <tr>
            <th><?= gettext("Instance") ?></th>
            <th><?= gettext("Peer") ?></th>
            <th><?= gettext("Public Key") ?></th>
            <th><?= gettext("Latest Handshake") ?></th>
        </tr>
    </thead>
    <tbody>
    </tbody>
    <tfoot style="display: none;">
        <tr>
            <td colspan="4"><?= gettext("No WireGuard instance defined or enabled.") ?></td>
        </tr>
    </tfoot>
</table>

<style>
    .psk_td {
        max-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        cursor: pointer;
        text-decoration: underline;
    }
</style>


<script>
$(window).on("load", function() {
    function wgUpdateStatus()
    {
        ajaxGet("/api/wireguard/service/show", {}, function(data, status) {
            let $target = $("#wg-table > tbody").empty();
            if (data.rows !== undefined && data.rows.length > 0) {
                $("#wg-table > tfoot").hide();
                for (let i=0; data.rows.length > i; ++i) {
                    let row = data.rows[i];
                    let $tr = $("<tr/>");
                    let ifname = row.ifname ? row.if + ' (' + row.ifname + ') ' : row.if;
                    $tr.append($("<td>").append(ifname));
                    $tr.append($("<td>").append(row.name));
                    $tr.append($("<td class='psk_td'>").append(row['public-key']));
                    let latest_handhake = '';
                    if (row['latest-handshake']) {
                        latest_handhake = moment.unix(row['latest-handshake']).local().format('YYYY-MM-DD HH:mm:ss');
                    }
                    $tr.append($("<td>").append(latest_handhake));
                    $target.append($tr);
                }
                $(".psk_td").each(function(){
                    $(this).tooltip({title: $(this).text(), container: 'body', trigger: 'hover'});
                });
            } else{
                $("#wg-table > tfoot").show();
            }
            setTimeout(wgUpdateStatus, 10000);
        });
    };
    wgUpdateStatus();
});
</script>
