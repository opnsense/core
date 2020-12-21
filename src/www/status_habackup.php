<?php

/*
 * Copyright (C) 2016 Deciso B.V.
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
require_once("XMLRPC_Client.inc") ;

function xmlrpc_exec($method, $params=array(), $debug=false)
{
    global $config;
    $synchronizeto = null;
    $hasync = $config['hasync'];
    if (is_ipaddrv6($hasync['synchronizetoip'])) {
        $hasync['synchronizetoip'] = "[{$hasync['synchronizetoip']}]";
    }

    if (!empty($hasync['synchronizetoip'])) {
        // determine target url
        if (substr($hasync['synchronizetoip'],0, 4) == 'http') {
            // URL provided
            if (substr($hasync['synchronizetoip'], strlen($hasync['synchronizetoip'])-1, 1) == '/') {
                $synchronizeto = $hasync['synchronizetoip']."xmlrpc.php";
            } else {
                $synchronizeto = $hasync['synchronizetoip']."/xmlrpc.php";
            }
        } elseif (!empty($config['system']['webgui']['protocol'])) {
            // no url provided, assume the backup is using the same settings as our box.
            $port = $config['system']['webgui']['port'];
            if (!empty($port)) {
                $synchronizeto =  $config['system']['webgui']['protocol'] . '://'.$hasync['synchronizetoip'].':'.$port."/xmlrpc.php";
            } else {
                $synchronizeto =  $config['system']['webgui']['protocol'] . '://'.$hasync['synchronizetoip']."/xmlrpc.php" ;
            }
        }

        $username = empty($hasync['username']) ? "root" : $hasync['username'];
        $client = new SimpleXMLRPC_Client($synchronizeto,240);
        $client->debug=$debug;
        $client->setCredentials($username, $hasync['password']);
        if ($client->query($method, $params)) {
            return $client->getResponse();
        }
    }
    return false;
}

function get_xmlrpc_backup_version()
{
    return xmlrpc_exec('opnsense.firmware_version');
}

function get_xmlrpc_services()
{
    return xmlrpc_exec('opnsense.list_services');
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST["action"])) {
        switch ($_POST["action"]) {
            case 'stop':
                $result=xmlrpc_exec('opnsense.stop_service', array("service" => $_POST['service'], "id" => $_POST['id']));
                echo json_encode(array("response" => $result));
                break;
            case 'start':
                $result=xmlrpc_exec('opnsense.start_service', array("service" => $_POST['service'], "id" => $_POST['id']));
                echo json_encode(array("response" => $result));
                break;
            case 'restart':
                $result=xmlrpc_exec('opnsense.restart_service', array("service" => $_POST['service'], "id" => $_POST['id']));
                echo json_encode(array("response" => $result));
                break;
            case 'reload_templates':
                xmlrpc_exec('opnsense.configd_reload_all_templates');
                echo json_encode(array("status" => "done"));
                break;
            case 'exec_sync':
                configd_run('filter sync');
                echo json_encode(array("status" => "done"));
                break;
        }
    }
    exit;
}

$carp_backup_version = get_xmlrpc_backup_version();
include("head.inc");
?>

<body>
<script>
    //<![CDATA[
    $( document ).ready(function() {
        function perform_actions_reload(todo)
        {
            var this_element = todo.shift();
            this_element['handle'].parent().hide();
            if (this_element['handle'].parent().data('busy-id') != "") {
                $("#"+this_element['handle'].parent().data('busy-id')).show();
            }
            $.post(window.location,this_element['params'], function(data) {
                if (this_element['handle'].parent().data('busy-id') != "") {
                    $("#"+this_element['handle'].parent().data('busy-id')).hide();
                    $("#"+this_element['handle'].parent().data('busy-id')+"_done").show();
                }
                // refresh page after last service action
                if (todo.length > 0) {
                    perform_actions_reload(todo);
                } else {
                    location.reload(true);
                }
            });
        }
        $(".xmlrpc_srv_status_act").click(function(event){
            event.preventDefault();
            var todo = [];
            if ($(this).data('service_action') == 'restart_all') {
                $(this).parent().hide(); // hide button
                // reload all services
                $(".xmlrpc_srv_status_act").each(function(){
                    if ($(this).data('service_action') == 'restart') {
                        let params = {};
                        params['action'] = $(this).data('service_action');
                        params['service'] = $(this).data('service_name');
                        params['id'] = $(this).data('service_id');
                        todo.push({'handle': $(this), 'params': params});
                    }
                });
            } else if ($(this).data('service_action') != undefined) {
                // reload single service
                let params = {};
                params['action'] = $(this).data('service_action');
                params['service'] = $(this).data('service_name');
                params['id'] = $(this).data('service_id');
                todo.push({'handle': $(this), 'params': params});
            }
            // reload all templates first
            $("#action_exec_sync").hide();
            $("#action_exec_sync_spinner").show();
            $.post(window.location, {action: 'exec_sync'}, function(data) {
                $("#action_exec_sync_done").show();
                $("#action_exec_sync_spinner").hide();
                $("#action_templates").show();
                $.post(window.location, {action: 'reload_templates'}, function(data) {
                    $("#action_templates").hide();
                    $("#action_templates_done").show();
                    if (todo.length > 0) {
                        perform_actions_reload(todo);
                    }
                });
            });
        });
    });
</script>

<?php include("fbegin.inc"); ?>

<section class="page-content-main">
  <div class="container-fluid">
    <div class="row">
<?php
        if ($carp_backup_version === false):?>
        <?=print_info_box(gettext('The backup firewall is not accessible or not configured.'));?>
<?php
        elseif (!is_array($carp_backup_version)):?>
        <?=print_info_box(gettext('The backup firewall is not accessible (check user credentials).'));?>
<?php
        else:?>
        <section class="col-xs-12">
          <div class="content-box">
              <div class="table-responsive">
                <table class="table table-condensed">
                  <thead>
                      <tr>
                          <th colspan="3"><?=gettext("Backup firewall versions");?></th>
                      </tr>
                      <tr>
                          <th><?=gettext("Firmware");?></th>
                          <th><?=gettext("Base");?></th>
                          <th><?=gettext("Kernel");?></th>
                      </tr>
                  </thead>
                  <tbody>
                      <tr>
                          <td><?=$carp_backup_version['firmware']['version'];?></td>
                          <td><?=$carp_backup_version['base']['version'];?></td>
                          <td><?=$carp_backup_version['kernel']['version'];?></td>
                      </tr>
                  </tbody>
                </table>
              </div>
            </div>
        </section>
        <section class="col-xs-12">
            <div class="content-box">
                <div class="table-responsive">
                    <table class="table table-condensed table-hover">
                        <thead>
                            <tr>
                                <th colspan="4"><?=gettext("Backup services");?></th>
                            </tr>
                            <tr>
                                <th><?=gettext("Service");?></th>
                                <th><?=gettext("Description");?></th>
                                <th><?=gettext("Status");?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                          <tr>
                              <td>
                                  <?=gettext("Synchronize");?>
                              </td>
                              <td>
                                  <?=gettext("Synchronize config to backup");?>
                              </td>
                              <td>
                                <span id="action_exec_sync" class="btn btn-xs btn-default xmlrpc_srv_status_act">
                                    <i  data-toggle="tooltip"
                                        title="<?=gettext('Synchronize config to backup');?>"
                                        class="fa fa-cloud-upload fa-fw">
                                    </i>
                                </span>
                                <div id="action_exec_sync_spinner" style="display:none;">
                                    <i class="fa fa-spinner fa-pulse" aria-hidden="true"></i>
                                </div>
                                <div id="action_exec_sync_done" style="display:none;">
                                    <i class="fa fa-check" aria-hidden="true"></i>
                                </div>
                              </td>
                              <td></td>
                          </tr>
                          <tr>
                              <td><?=gettext("Templates");?></td>
                              <td><?=gettext("Generate configuration templates");?></td>
                              <td>
                                  <div id="action_templates" style="display:none;">
                                      <i class="fa fa-spinner fa-pulse" aria-hidden="true"></i>
                                  </div>
                                  <div id="action_templates_done" style="display:none;">
                                      <i class="fa fa-check" aria-hidden="true"></i>
                                  </div>
                              </td>
                              <td></td>
                          </tr>

<?php
                        $xmlrpc_services = get_xmlrpc_services();
                        $xmlrpc_services = empty($xmlrpc_services) ? array() : $xmlrpc_services;
                        foreach ($xmlrpc_services as $sequence => $service):?>
                            <tr>
                                <td><?=$service['name'];?></td>
                                <td><?=$service['description'];?></td>
                                <td>
                                    <div data-busy-id="action_<?=$sequence;?>">
                                        <span class="btn btn-xs btn-<?=!empty($service['status']) ? 'success' : 'danger' ?>">
                                          <i class="fa fa-<?=!empty($service['status']) ? 'play' : 'stop' ?> fa-fw"></i>
                                        </span>
<?php
                                        if (!empty($service['status'])):?>
                                        <span
                                            data-service_action="restart"
                                            data-service_id="<?=!empty($service['id']) ? $service['id'] : "";?>"
                                            data-service_name="<?=$service['name'];?>"
                                            data-toggle="tooltip"
                                            title="<?=sprintf(gettext('Restart %sService'), $service['name']);?>"
                                            class="btn btn-xs btn-default xmlrpc_srv_status_act fa fa-refresh fa-fw">
                                        </span>
<?php
                                          if (empty($service['nocheck'])):?>
                                        <span
                                            data-service_action="stop"
                                            data-service_id="<?=!empty($service['id']) ? $service['id'] : "";?>"
                                            data-service_name="<?=$service['name'];?>"
                                            data-toggle="tooltip"
                                            title="<?=sprintf(gettext('Stop %sService'), $service['name']);?>"
                                            class="btn btn-xs btn-default xmlrpc_srv_status_act fa fa-stop">
                                        </span>
<?php
                                          endif;
                                        else:?>
                                        <span
                                            data-service_action="start"
                                            data-service_id="<?=!empty($service['id']) ? $service['id'] : "";?>"
                                            data-service_name="<?=$service['name'];?>"
                                            data-toggle="tooltip"
                                            title="<?=sprintf(gettext('Start %sService'), $service['name']);?>"
                                            class="btn btn-xs btn-default xmlrpc_srv_status_act fa fa-play">
                                        </span>
<?php
                                        endif;?>
                                    </div>
                                    <div id="action_<?=$sequence;?>" style="display:none;">
                                        <i class="fa fa-spinner fa-pulse" aria-hidden="true"></i>
                                    </div>
                                    <div id="action_<?=$sequence;?>_done" style="display:none;">
                                        <i class="fa fa-check" aria-hidden="true"></i>
                                    </div>
                                </td>
                                <td></td>
                            </tr>
<?php
                        endforeach;?>
                            <tr>
                                <td><?=gettext("all (*)");?></td>
                                <td></td>
                                <td>
                                    <div data-sequence="">
                                        <span
                                            data-service_action="restart_all"
                                            data-service_name="all"
                                            data-toggle="tooltip"
                                            title="<?=gettext('Restart all services');?>"
                                            class="btn btn-xs btn-default xmlrpc_srv_status_act fa fa-refresh fa-fw">
                                        </span>
                                    </div>
                                </td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

<?php
        endif;?>
    </div>
  </div>
</section>

<?php include("foot.inc"); ?>
