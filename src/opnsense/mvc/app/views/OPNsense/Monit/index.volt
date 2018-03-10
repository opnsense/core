{#

Copyright © 2017-2018 by EURO-LOG AG
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

1.  Redistributions of source code must retain the above copyright notice,
this list of conditions and the following disclaimer.

2.  Redistributions in binary form must reproduce the above copyright notice,
this list of conditions and the following disclaimer in the documentation
and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.

#}
<script>

   $( document ).ready(function() {

      /**
      * add button 'Import System Notification'
      * can't do it via base_dialog
      */
	   $('<button class="btn btn-primary" id="btn_ImportSystemNotification" type="button" style="margin-left: 3px;">' +
	         '<b> {{ lang._('Import System Notification')}} </b>' +
	         '<i id="frm_ImportSystemNotification_progress"></i>' +
	      '</button>').insertAfter('#btn_ApplyGeneralSettings');

	   /**
      * UI functions
      */

      $('#btn_configtest').unbind('click').click(function(){
         $('#btn_configtest_progress').addClass("fa fa-spinner fa-pulse");
         ajaxCall(url="/api/monit/service/configtest", sendData={}, callback=function(data,status) {
            $('#btn_configtest_progress').removeClass("fa fa-spinner fa-pulse");
            $('#btn_configtest').blur();
            $("#responseMsg").removeClass("hidden");
            $("#responseMsg").html(data['result']);
         });
      });

      $('#btn_reload').unbind('click').click(function(){
         $('#btn_reload_progress').addClass("fa fa-spinner fa-pulse");
         ajaxCall(url="/api/monit/service/reconfigure", sendData={}, callback=function(data,status) {
            $('#btn_reload_progress').removeClass("fa fa-spinner fa-pulse");
            $('#btn_reload').blur();
            ajaxCall(url="/api/monit/service/status", sendData={}, callback=function(data,status) {
               updateServiceStatusUI(data['status']);
            });
         });
      });

      $('#btn_ImportSystemNotification').unbind('click').click(function(){
          $('#frm_ImportSystemNotification_progress').addClass("fa fa-spinner fa-pulse");
          ajaxCall(url="/api/monit/settings/notification", sendData={}, callback=function(data,status) {
             $('#frm_ImportSystemNotification_progress').removeClass("fa fa-spinner fa-pulse");
             $('#btn_ImportSystemNotification').blur();
             ajaxCall(url="/api/monit/service/status", sendData={}, callback=function(data,status) {
                mapDataToFormUI({'frm_GeneralSettings':"/api/monit/settings/get/general/"}).done(function(){
                    formatTokenizersUI();
                    $('.selectpicker').selectpicker('refresh');
                    ajaxCall(url="/api/monit/service/status", sendData={}, callback=function(data,status) {
                       updateServiceStatusUI(data['status']);
                    });
                 });
             });
          });
       });

      /**
       * general settings
       */
      mapDataToFormUI({'frm_GeneralSettings':"/api/monit/settings/get/general/"}).done(function(){
         formatTokenizersUI();
         $('.selectpicker').selectpicker('refresh');
         ajaxCall(url="/api/monit/service/status", sendData={}, callback=function(data,status) {
            updateServiceStatusUI(data['status']);
         });
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
      };
      $('#monit\\.general\\.httpdEnabled').unbind('click').click(function(){
         ShowHideGeneralFields();
      });
      $('#show_advanced_frm_GeneralSettings').click(function(){
         ShowHideGeneralFields();
      });

      $('#btn_ApplyGeneralSettings').unbind('click').click(function(){
	   $("#frm_GeneralSettings_progress").addClass("fa fa-spinner fa-pulse");
         var frm_id = 'frm_GeneralSettings';
         saveFormToEndpoint(url = "/api/monit/settings/set/general/",formid=frm_id,callback_ok=function(){
		   ajaxCall(url="/api/monit/service/status", sendData={}, callback=function(data,status) {
			   updateServiceStatusUI(data['status']);
            });
         });
         $("#"+frm_id+"_progress").removeClass("fa fa-spinner fa-pulse");
         $("#btn_ApplyGeneralSettings").blur();
      });

      /**
       * alert settings
       */
      function openAlertDialog(uuid) {
         var editDlg = "DialogEditAlert";
         var setUrl = "/api/monit/settings/set/alert/";
         var getUrl = "/api/monit/settings/get/alert/";
         var urlMap = {};
         urlMap['frm_' + editDlg] = getUrl + uuid;
         mapDataToFormUI(urlMap).done(function () {
            $('.selectpicker').selectpicker('refresh');
            clearFormValidation('frm_' + editDlg);
            $('#'+editDlg).modal({backdrop: 'static', keyboard: false});
            $('#'+editDlg).on('hidden.bs.modal', function () {
               parent.history.back();
            });
         });
      };

      $("#grid-alerts").UIBootgrid({
         'search':'/api/monit/settings/search/alert/',
         'get':'/api/monit/settings/get/alert/',
         'set':'/api/monit/settings/set/alert/',
         'add':'/api/monit/settings/set/alert/',
         'del':'/api/monit/settings/del/alert/',
         'toggle':'/api/monit/settings/toggle/alert/'
      });

      /**
       * service settings
       */

      // show hide fields according to selected service type
      function ShowHideFields(){
         var servicetype = $('#monit\\.service\\.type').val();
         $('tr[id="row_monit.service.pidfile"]').addClass('hidden');
         $('tr[id="row_monit.service.match"]').addClass('hidden');
         $('tr[id="row_monit.service.path"]').addClass('hidden');
         $('tr[id="row_monit.service.address"]').addClass('hidden');
         $('tr[id="row_monit.service.interface"]').addClass('hidden');
         $('tr[id="row_monit.service.start"]').removeClass('hidden');
         $('tr[id="row_monit.service.stop"]').removeClass('hidden');
         switch (servicetype) {
            case 'process':
               var pidfile = $('#monit\\.service\\.pidfile').val();
               var match = $('#monit\\.service\\.match').val();
               if (pidfile !== '') {
                  $('tr[id="row_monit.service.pidfile"]').removeClass('hidden');
                  $('tr[id="row_monit.service.match"]').addClass('hidden');
               } else if (match !== '') {
                  $('tr[id="row_monit.service.pidfile"]').addClass('hidden');
                  $('tr[id="row_monit.service.match"]').removeClass('hidden');
               } else {
                  $('tr[id="row_monit.service.pidfile"]').removeClass('hidden');
                  $('tr[id="row_monit.service.match"]').removeClass('hidden');
               }
               break;
            case 'host':
               $('tr[id="row_monit.service.address"]').removeClass('hidden');
               break;
            case 'network':
               var address = $('#monit\\.service\\.address').val();
               var interface = $('#monit\\.service\\.interface').val();
               if (address !== '') {
                  $('tr[id="row_monit.service.address"]').removeClass('hidden');
                  $('tr[id="row_monit.service.interface"]').addClass('hidden');
               } else if (interface !== '') {
                  $('tr[id="row_monit.service.address"]').addClass('hidden');
                  $('tr[id="row_monit.service.interface"]').removeClass('hidden');
               } else {
                  $('tr[id="row_monit.service.address"]').removeClass('hidden');
                  $('tr[id="row_monit.service.interface"]').removeClass('hidden');
               }
               break;
            case 'system':
               $('tr[id="row_monit.service.start"]').addClass('hidden');
               $('tr[id="row_monit.service.stop"]').addClass('hidden');
               break;
            default:
               $('tr[id="row_monit.service.path"]').removeClass('hidden');
         }
      };
      $('#DialogEditService').on('shown.bs.modal', function() {ShowHideFields();});
      $('#monit\\.service\\.type').on('changed.bs.select', function(e) {ShowHideFields();});
      $('#monit\\.service\\.pidfile').on('input', function() {ShowHideFields();});
      $('#monit\\.service\\.match').on('input', function() {ShowHideFields();});
      $('#monit\\.service\\.path').on('input', function() {ShowHideFields();});
      $('#monit\\.service\\.address').on('input', function() {ShowHideFields();});
      $('#monit\\.service\\.interface').on('changed.bs.select', function(e) {ShowHideFields();});

      $("#grid-services").UIBootgrid({
         'search':'/api/monit/settings/search/service/',
         'get':'/api/monit/settings/get/service/',
         'set':'/api/monit/settings/set/service/',
         'add':'/api/monit/settings/set/service/',
         'del':'/api/monit/settings/del/service/',
         'toggle':'/api/monit/settings/toggle/service/'
      });


      /**
       * service test settings
       */

      // show hide execute field
      function ShowHideExecField(){
         var actiontype = $('#monit\\.test\\.action').val();
         $('tr[id="row_monit.test.path"]').addClass('hidden');
         if (actiontype === 'exec') {
            $('tr[id="row_monit.test.path"]').removeClass('hidden');
         }
      };
      $('#DialogEditTest').on('shown.bs.modal', function() {ShowHideExecField();});
      $('#monit\\.test\\.action').on('changed.bs.select', function(e) {ShowHideExecField();});

      function openTestDialog(uuid) {
         var editDlg = "TestEditAlert";
         var setUrl = "/api/monit/settings/set/test/";
         var getUrl = "/api/monit/settings/get/test/";
         var urlMap = {};
         urlMap['frm_' + editDlg] = getUrl + uuid;
         mapDataToFormUI(urlMap).done(function () {
            $('.selectpicker').selectpicker('refresh');
            clearFormValidation('frm_' + editDlg);
            $('#'+editDlg).modal({backdrop: 'static', keyboard: false});
            $('#'+editDlg).on('hidden.bs.modal', function () {
               parent.history.back();
            });
         });
      };

      $("#grid-tests").UIBootgrid({
         'search':'/api/monit/settings/search/test/',
         'get':'/api/monit/settings/get/test/',
         'set':'/api/monit/settings/set/test/',
         'add':'/api/monit/settings/set/test/',
         'del':'/api/monit/settings/del/test/'
      });

   });
</script>

<div class="alert alert-info hidden" role="alert" id="responseMsg">

</div>

<ul class="nav nav-tabs" role="tablist" id="maintabs">
   <li class="active"><a data-toggle="tab" href="#general">{{ lang._('General Settings') }}</a></li>
   <li><a data-toggle="tab" href="#alerts">{{ lang._('Alert Settings') }}</a></li>
   <li><a data-toggle="tab" href="#services">{{ lang._('Service Settings') }}</a></li>
   <li><a data-toggle="tab" href="#tests">{{ lang._('Service Tests Settings') }}</a></li>
</ul>
<div class="tab-content content-box tab-content">
   <div id="general" class="tab-pane fade in active">
      {{ partial("layout_partials/base_form",['fields':formGeneralSettings,'id':'frm_GeneralSettings','apply_btn_id':'btn_ApplyGeneralSettings'])}}
   </div>
   <div id="alerts" class="tab-pane fade in">
      <table id="grid-alerts" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogEditAlert">
         <thead>
            <tr>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="recipient" data-width="12em" data-type="string">{{ lang._('Recipient') }}</th>
                <th data-column-id="noton" data-width="2em" data-type="string" data-align="right" data-formatter="boolean"></th>
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
                  <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                  <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
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
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
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
                    <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                    <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
                </td>
            </tr>
         </tfoot>
      </table>
   </div>
   <div class="col-md-12">
         <hr/>
         <button class="btn btn-primary" id="btn_configtest" type="button"><b>{{ lang._('Test Configuration') }}</b><i id="btn_configtest_progress" class=""></i></button>
         <button class="btn btn-primary" id="btn_reload" type="button"><b>{{ lang._('Reload Configuration') }}</b><i id="btn_reload_progress" class=""></i></button>
         <br/>
         <br/>
      </div>
</div>
{# include dialogs #}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditAlert,'id':'DialogEditAlert','label':'Edit Alert&nbsp;&nbsp;<small>NOTE: For a detailed description see monit(1) section "ALERT MESSAGES".</small>'])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditService,'id':'DialogEditService','label':'Edit Service'])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditTest,'id':'DialogEditTest','label':'Edit Test&nbsp;&nbsp;<small>NOTE: For a detailed description see monit(1) section "SERVICE TESTS".</small>'])}}
