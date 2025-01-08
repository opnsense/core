{#
 # Copyright (c) 2023 Deciso B.V.
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
 # THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
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

<style>
    .diff_record {
        white-space: pre-wrap;
        font-family: monospace;
        border: none !important;
        padding-top: 0 !important;
        padding-bottom: 0 !important;
        word-break: break-all;
    }
</style>
<script>
    'use strict';
    function get_backups(){
        let provider = $("#providers").val();
        $("#host_processing").show();
        ajaxGet('/api/core/backup/backups/' + provider, {}, function(data, status){
            let target1 = $("#backups1").empty();
            let target2 = $("#backups2").empty();
            if (data.items && Object.keys(data.items).length > 1) {
                Object.keys(data.items).forEach(function(key) {
                    let record = data.items[key];
                    let payload = $("<div>");
                    payload.append(record.time_iso, "&nbsp;", record.username, "<br/>");
                    payload.append($("<small/>").text(record.description));
                    target1.append($("<option/>").attr('value', record.id).attr('data-content', payload.html()));
                    target2.append($("<option/>").attr('value', record.id).attr('data-content', payload.html()));
                });
            }
            $(".backups").selectpicker('refresh');
            $("#backups1").change();
            $("#host_processing").hide();
            if ($("#backups1 option").length === 0) {
                $("#diff_tfoot").show();
            } else {
                $("#diff_tfoot").hide();
            }
        });
        if (provider === 'this' ) {
            $(".only_local").show();
        } else {
            $(".only_local").hide();
        }
    }
    $( document ).ready(function () {
        ajaxGet('/api/core/backup/providers', {}, function(data, status){
            let selected_host = "{{selected_host}}";
            if (data.items && Object.keys(data.items).length > 1) {
                let target = $("#providers").empty();
                Object.keys(data.items).forEach(function(key) {
                    let opt = $("<option/>").attr('value', key).text(data.items[key].description);
                    if (selected_host == key) {
                        opt.attr('selected', 'selected');
                    }
                    target.append(opt);
                });
                target.selectpicker('refresh');
            }
            $("#providers").change(get_backups);
            $("#providers").change();
            if ($("#providers > option").length > 1) {
                $("#providers_pane").show();
            }
        });

        $("#backups1").change(function(){
            let target = $("#backups2");
            target.val($("#backups1").val());
            if ($('#backups2 option').length > target[0].selectedIndex) {
                target[0].selectedIndex = target[0].selectedIndex + 1;
            }
            target.selectpicker('refresh');
            target.change();
        });

        $("#backups2").change(function(){
            let provider = $("#providers").val();
            let url = '/api/core/backup/diff/'+provider+'/'+$("#backups1").val()+'/'+$("#backups2").val();
            ajaxGet(url, {}, function(data, status){
                let target = $("#diff_records").empty();
                if (data.items && data.items.length > 1) {
                    for (let i=0 ; i < data.items.length ; ++i) {
                        let $tr = $("<tr/>");
                        let $td = $("<td class='diff_record'/>").append($("<div>").html(data.items[i]).text());
                        let color = '#000000';
                        switch (data.items[i][0]) {
                            case '+':
                                color = '#3bbb33';
                                $td.addClass('diff_record_plus');
                                break;
                            case '-':
                                color = '#c13928';
                                $td.addClass('diff_record_minus');
                                break;
                            case '@':
                                color = '#3bb9c3';
                                $td.addClass('diff_record_at');
                                break;
                            default:
                                $td.addClass('diff_record_default');
                                break;
                        }
                        $td.css('color', color);
                        target.append($tr.append($td));
                    }
                }
            });
        });

        $("#act_revert").click(function(event){
            event.preventDefault();
            if (!$("#backups1").val()) {
                return;
            }
            BootstrapDialog.show({
                type: BootstrapDialog.TYPE_WARNING,
                title: "{{ lang._('Confirmation required') }}",
                message: "{{ lang._('Restore from Configuration Backup') }} <br/>" + $("#backups1 option:selected").data('content'),
                buttons: [{
                label: "{{ lang._('No') }}",
                action: function(dialogRef) {
                    dialogRef.close();
                }}, {
                    label: "{{ lang._('Yes') }}",
                    cssClass: 'btn-warning',
                    action: function(dialogRef) {
                        ajaxCall("/api/core/backup/revert_backup/" + $("#backups1").val(),{}, function(){
                            dialogRef.close();
                            get_backups();
                        });
                    }
                }]
            });
        });
        $("#act_remove").click(function(event){
            event.preventDefault();
            if (!$("#backups1").val()) {
                return;
            }
            BootstrapDialog.show({
                type: BootstrapDialog.TYPE_WARNING,
                title: "{{ lang._('Confirmation required') }}",
                message: "{{ lang._('Remove Configuration Backup') }} <br/>" + $("#backups1 option:selected").data('content'),
                buttons: [{
                label: "{{ lang._('No') }}",
                action: function(dialogRef) {
                    dialogRef.close();
                }}, {
                    label: "{{ lang._('Yes') }}",
                    cssClass: 'btn-warning',
                    action: function(dialogRef) {
                        ajaxCall("/api/core/backup/delete_backup/" + $("#backups1").val(),{}, function(){
                            $(".backups").find('[value="'+$("#backups1").val()+'"]').remove();
                            $("#backups1").selectpicker('refresh');
                            $("#backups1").change();
                            dialogRef.close();
                        });
                    }
                }]
            });
        });
        $("#act_download").click(function(event){
            event.preventDefault();
            if (!$("#backups1").val()) {
                return;
            }
            let url = '/api/core/backup/download/' + $("#providers").val() + '/' + $("#backups1").val();
            let link = document.createElement("a");
            $(link).click(function(e) {
                e.preventDefault();
                window.location.href = url;
            });
            $(link).click();
        });
    });

</script>

<div class="tab-content content-box __mb" id="providers_pane" style="display: none;">
    <div class="row">
        <div class="col-xs-12">
            <table class="table table-condensed table-striped">
                <thead>
                    <tr>
                        <th>{{ lang._('Host')}}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <select id="providers" class="selectpicker">
                                <option value="this" selected>{{ lang._('This Firewall')}}</option>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="tab-content content-box __mb">
    <div class="row">
        <div class="col-xs-12">
            <table class="table table-condensed table-striped">
                <thead>
                    <tr>
                        <th>
                            {{ lang._('Backups (compare)')}}
                            <i id="host_processing" class="fa fa-fw fa-spinner fa-pulse" style="display: none;"></i>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="height: 70px;">
                        <td>
                            <select class="selectpicker backups" id="backups1"></select>
                            <select class="selectpicker backups" id="backups2"></select>
                            <div>
                                <a id="act_revert" class="only_local btn btn-default btn-xs" data-toggle="tooltip" title="{{ lang._('Revert to this configuration')}}">
                                    <i class="fa fa-sign-in fa-fw"></i>
                                  </a>
                                  <a id="act_remove"  class="only_local btn btn-default btn-xs" data-toggle="tooltip" title="{{ lang._('Remove this backup')}}">
                                    <i class="fa fa-trash fa-fw"></i>
                                  </a>
                                  <a id="act_download" class="btn btn-default btn-xs" data-toggle="tooltip" title="{{ lang._('Download this backup')}}">
                                    <i class="fa fa-download fa-fw"></i>
                                  </a>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="tab-content content-box">
    <div class="row">
        <div class="col-xs-12">
            <table class="table table-condensed">
                <thead>
                    <tr>
                        <th >{{ lang._('Changes between selected versions')}}</th>
                    </tr>
                </thead>
                <tbody id="diff_records">
                </tbody>
                <tfoot id="diff_tfoot" style="display: none;">
                    <tr>
                        <td>{{ lang._('No backups available')}}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
