{#
 # Copyright (c) 2017-2018 EURO-LOG AG
 # Copyright (c) 2019 Deciso B.V.
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
 # THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
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

   $( document ).ready(function() {
      /**
       * get the isSubsystemDirty value and print a notice
       */
      function isSubsystemDirty() {
         ajaxGet("/api/monit/settings/dirty", {}, function(data,status) {
            if (status == "success") {
               if (data.monit.dirty === true) {
                  $("#configChangedMsg").removeClass("hidden");
               } else {
                  $("#configChangedMsg").addClass("hidden");
               }
            }
         });
      }

      /**
       * chain std_bootgrid_reload from opnsense_bootgrid_plugin.js
       * to get the isSubsystemDirty state on "UIBootgrid" changes
       */
      var opn_std_bootgrid_reload = std_bootgrid_reload;
      std_bootgrid_reload = function(gridId) {
         opn_std_bootgrid_reload(gridId);
         isSubsystemDirty();
      };

      /**
       * apply changes and reload monit
       */
      $('#btnApplyConfig').SimpleActionButton({onAction: function(data, status){
          isSubsystemDirty();
      }});


      /**
       * general settings
       */
      mapDataToFormUI({'frm_GeneralSettings':"/api/monit/settings/getGeneral/"}).done(function(){
         formatTokenizersUI();
         $('.selectpicker').selectpicker('refresh');
         isSubsystemDirty();
         updateServiceControlUI('monit');
         ShowHideGeneralFields();
      });

      // show/hide httpd/mmonit options
      function ShowHideGeneralFields(){
         if ($('#monit\\.general\\.httpdEnabled')[0].checked) {
            $('tr[id="row_monit.general.httpdPort"]').removeClass('hidden');
            $('tr[id="row_monit.general.httpdAllow"]').removeClass('hidden');
            $('tr[id="row_monit.general.mmonitUrl"]').removeClass('hidden');
            $('tr[id="row_monit.general.mmonitTimeout"]').removeClass('hidden');
            $('tr[id="row_monit.general.mmonitRegisterCredentials"]').removeClass('hidden');
         } else {
            $('tr[id="row_monit.general.httpdPort"]').addClass('hidden');
            $('tr[id="row_monit.general.httpdAllow"]').addClass('hidden');
            $('tr[id="row_monit.general.mmonitUrl"]').addClass('hidden');
            $('tr[id="row_monit.general.mmonitTimeout"]').addClass('hidden');
            $('tr[id="row_monit.general.mmonitRegisterCredentials"]').addClass('hidden');
         }
         if ($('#monit\\.general\\.ssl')[0].checked) {
            $('tr[id="row_monit.general.sslversion"]').removeClass('hidden');
            $('tr[id="row_monit.general.sslverify"]').removeClass('hidden');
         } else {
            $('tr[id="row_monit.general.sslversion"]').addClass('hidden');
            $('tr[id="row_monit.general.sslverify"]').addClass('hidden');
         }
      };
      $('#monit\\.general\\.httpdEnabled').unbind('click').click(function(){
         ShowHideGeneralFields();
      });
      $('#monit\\.general\\.ssl').unbind('click').click(function(){
         ShowHideGeneralFields();
      });
      $('#show_advanced_frm_GeneralSettings').click(function(){
         ShowHideGeneralFields();
      });

      $('#btnSaveGeneral').unbind('click').click(function(){
         $("#btnSaveGeneralProgress").addClass("fa fa-spinner fa-pulse");
         var frm_id = 'frm_GeneralSettings';
         saveFormToEndpoint("/api/monit/settings/set/", frm_id, function(){
            isSubsystemDirty();
            updateServiceControlUI('monit');
         });
         $("#btnSaveGeneralProgress").removeClass("fa fa-spinner fa-pulse");
         $("#btnSaveGeneral").blur();
      });

      /**
       * alert settings
       */
      $("#grid-alerts").UIBootgrid({
         'search':'/api/monit/settings/searchAlert/',
         'get':'/api/monit/settings/getAlert/',
         'set':'/api/monit/settings/setAlert/',
         'add':'/api/monit/settings/addAlert/',
         'del':'/api/monit/settings/delAlert/',
         'toggle':'/api/monit/settings/toggleAlert/'
      });

      /**
       * service settings
       */

      // show hide fields according to selected service type
      function ShowHideFields(){
         var servicetype = $('#service\\.type').val();
         $('tr[id="row_service.pidfile"]').addClass('hidden');
         $('tr[id="row_service.match"]').addClass('hidden');
         $('tr[id="row_service.path"]').addClass('hidden');
         $('tr[id="row_service.timeout"]').addClass('hidden');
         $('tr[id="row_service.address"]').addClass('hidden');
         $('tr[id="row_service.interface"]').addClass('hidden');
         $('tr[id="row_service.start"]').removeClass('hidden');
         $('tr[id="row_service.stop"]').removeClass('hidden');
         $('tr[id="row_service.depends"]').removeClass('hidden');
         switch (servicetype) {
            case 'process':
               var pidfile = $('#service\\.pidfile').val();
               var match = $('#service\\.match').val();
               if (pidfile !== '') {
                  $('tr[id="row_service.pidfile"]').removeClass('hidden');
                  $('tr[id="row_service.match"]').addClass('hidden');
               } else if (match !== '') {
                  $('tr[id="row_service.pidfile"]').addClass('hidden');
                  $('tr[id="row_service.match"]').removeClass('hidden');
               } else {
                  $('tr[id="row_service.pidfile"]').removeClass('hidden');
                  $('tr[id="row_service.match"]').removeClass('hidden');
               }
               break;
            case 'host':
               $('tr[id="row_service.address"]').removeClass('hidden');
               break;
            case 'network':
               var address = $('#service\\.address').val();
               var interface = $('#service\\.interface').val();
               if (address !== '') {
                  $('tr[id="row_service.address"]').removeClass('hidden');
                  $('tr[id="row_service.interface"]').addClass('hidden');
               } else if (interface !== '') {
                  $('tr[id="row_service.address"]').addClass('hidden');
                  $('tr[id="row_service.interface"]').removeClass('hidden');
               } else {
                  $('tr[id="row_service.address"]').removeClass('hidden');
                  $('tr[id="row_service.interface"]').removeClass('hidden');
               }
               break;
            case 'system':
               $('tr[id="row_service.start"]').addClass('hidden');
               $('tr[id="row_service.stop"]').addClass('hidden');
               $('tr[id="row_service.depends"]').addClass('hidden');
               break;
            default:
               $('tr[id="row_service.path"]').removeClass('hidden');
               $('tr[id="row_service.timeout"]').removeClass('hidden');
         }
      };
      $('#DialogEditService').on('shown.bs.modal', function() {ShowHideFields();});
      $('#service\\.type').on('changed.bs.select', function(e) {ShowHideFields();});
      $('#service\\.pidfile').on('input', function() {ShowHideFields();});
      $('#service\\.match').on('input', function() {ShowHideFields();});
      $('#service\\.path').on('input', function() {ShowHideFields();});
      $('#service\\.timeout').on('input', function() {ShowHideFields();});
      $('#service\\.address').on('input', function() {ShowHideFields();});
      $('#service\\.interface').on('changed.bs.select', function(e) {ShowHideFields();});

      $("#grid-services").UIBootgrid({
         'search':'/api/monit/settings/searchService/',
         'get':'/api/monit/settings/getService/',
         'set':'/api/monit/settings/setService/',
         'add':'/api/monit/settings/addService/',
         'del':'/api/monit/settings/delService/',
         'toggle':'/api/monit/settings/toggleService/'
      });


      /**
       * service test settings
       */

      // show hide execute field
      function ShowHideExecField(){
         var actiontype = $('#test\\.action').val();
         $('tr[id="row_test.path"]').addClass('hidden');
         if (actiontype === 'exec') {
            $('tr[id="row_test.path"]').removeClass('hidden');
         }
      };
      $('#DialogEditTest').on('shown.bs.modal', function() {ShowHideExecField();});
      $('#test\\.action').on('changed.bs.select', function(e) {ShowHideExecField();});


      $("#grid-tests").UIBootgrid({
         'search':'/api/monit/settings/searchTest/',
         'get':'/api/monit/settings/getTest/',
         'set':'/api/monit/settings/setTest/',
         'add':'/api/monit/settings/addTest/',
         'del':'/api/monit/settings/delTest/'
      });

   });
</script>

<div class="alert alert-info hidden" role="alert" id="configChangedMsg">
   <button class="btn btn-primary pull-right" id="btnApplyConfig"
           data-endpoint='/api/monit/service/reconfigure'
           data-label="{{ lang._('Apply') }}"
           data-service-widget="monit"
           data-error-title="{{ lang._('Error reconfiguring Monit') }}"
           type="button">
   </button>
   {{ lang._('The Monit configuration has been changed') }} <br /> {{ lang._('You must apply the changes in order for them to take effect.')}}
</div>

<ul class="nav nav-tabs" role="tablist" id="maintabs">
   <li class="active"><a data-toggle="tab" href="#general">{{ lang._('General Settings') }}</a></li>
   <li><a data-toggle="tab" href="#alerts">{{ lang._('Alert Settings') }}</a></li>
   <li><a data-toggle="tab" href="#services">{{ lang._('Service Settings') }}</a></li>
   <li><a data-toggle="tab" href="#tests">{{ lang._('Service Tests Settings') }}</a></li>
</ul>
<div class="tab-content content-box">
   <div id="general" class="tab-pane fade in active">
      {{ partial("layout_partials/base_form",['fields':formGeneralSettings,'id':'frm_GeneralSettings'])}}
      <div class="table-responsive">
         <table class="table table-striped table-condensed table-responsive">
            <tr>
               <td>
                  <button class="btn btn-primary" id="btnSaveGeneral" type="button">
                     <b>{{ lang._('Save') }}</b> <i id="btnSaveGeneralProgress"></i>
                  </button>
               </td>
            </tr>
         </table>
      </div>
   </div>
   <div id="alerts" class="tab-pane fade in">
      <table id="grid-alerts" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogEditAlert">
         <thead>
            <tr>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="recipient" data-width="12em" data-type="string">{{ lang._('Recipient') }}</th>
                <th data-column-id="noton" data-width="6em" data-type="string" data-formatter="boolean">{{ lang._('Not on') }}</th>
                <th data-column-id="events" data-type="string">{{ lang._('Events') }}</th>
                <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Edit') }} | {{ lang._('Delete') }}</th>
            </tr>
         </thead>
         <tbody>
         </tbody>
         <tfoot>
            <tr>
               <td></td>
               <td>
                  <button data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-plus fa-fw"></span></button>
                  <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o fa-fw"></span></button>
               </td>
            </tr>
         </tfoot>
      </table>
   </div>
   <div id="services" class="tab-pane fade in">
      <table id="grid-services" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogEditService">
         <thead>
            <tr>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-plus fa-fw"></span></button>
                    <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o fa-fw"></span></button>
                </td>
            </tr>
         </tfoot>
      </table>
   </div>
   <div id="tests" class="tab-pane fade in">
      <table id="grid-tests" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogEditTest">
         <thead>
            <tr>
                <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                <th data-column-id="condition" data-type="string">{{ lang._('Condition') }}</th>
                <th data-column-id="action" data-type="string">{{ lang._('Action') }}</th>
                <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Edit') }} | {{ lang._('Delete') }}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-plus fa-fw"></span></button>
                    <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o fa-fw"></span></button>
                </td>
            </tr>
         </tfoot>
      </table>
   </div>
</div>
{# include dialogs #}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditAlert,'id':'DialogEditAlert','label':'Edit Alert&nbsp;&nbsp;<small>NOTE: For a detailed description see monit(1) section "ALERT MESSAGES".</small>'])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditService,'id':'DialogEditService','label':'Edit Service'])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditTest,'id':'DialogEditTest','label':'Edit Test&nbsp;&nbsp;<small>NOTE: For a detailed description see monit(1) section "SERVICE TESTS".</small>'])}}
