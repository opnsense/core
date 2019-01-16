{#

Copyright © 2017-2019 by EURO-LOG AG
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
      $('#btnApplyConfig').unbind('click').click(function(){
         $('#btnApplyConfigProgress').addClass("fa fa-spinner fa-pulse");
         ajaxCall("/api/monit/service/reconfigure", {}, function(data,status) {
            $("#responseMsg").addClass("hidden");
            isSubsystemDirty();
            updateServiceControlUI('monit');
            if (data.result) {
               $("#responseMsg").html(data['result']);
               $("#responseMsg").removeClass("hidden");
            }
            $('#btnApplyConfigProgress').removeClass("fa fa-spinner fa-pulse");
            $('#btnApplyConfig').blur();
         });
      });

      /**
      * add button 'Import System Notification'
      * can't do it via base_dialog
      */
      $('<button class="btn btn-primary" id="btn_ImportSystemNotification" type="button" style="margin-left: 3px;">' +
            '<b> {{ lang._('Import System Notification')}} </b>' +
            '<i id="frm_ImportSystemNotification_progress"></i>' +
         '</button>').insertAfter('#btn_ApplyGeneralSettings');

      $('#btnImportSystemNotification').unbind('click').click(function(){
          $('#btnImportSystemNotificationProgress').addClass("fa fa-spinner fa-pulse");
          ajaxCall("/api/monit/settings/notification", {}, function(data,status) {
             $("#responseMsg").addClass("hidden");
             isSubsystemDirty();
             updateServiceControlUI('monit');
             if (data.result) {
               $("#responseMsg").html(data['result']);
               $("#responseMsg").removeClass("hidden");
            }
             $('#btnImportSystemNotificationProgress').removeClass("fa fa-spinner fa-pulse");
             $('#btnImportSystemNotification').blur();
             ajaxCall("/api/monit/service/status", {}, function(data,status) {
                mapDataToFormUI({'frm_GeneralSettings':"/api/monit/settings/get/general/"}).done(function(){
                    formatTokenizersUI();
                    $('.selectpicker').selectpicker('refresh');
                    isSubsystemDirty();
                    updateServiceControlUI('monit');
                 });
             });
         });
      });

      /**
       * general settings and syntax
       */
      mapDataToFormUI({'frm_GeneralSettings':"/api/monit/settings/get/general"}).done(function(){
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
         saveFormToEndpoint("/api/monit/settings/set/general/", frm_id, function(){
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
         $('tr[id="row_monit.service.timeout"]').addClass('hidden');
         $('tr[id="row_monit.service.address"]').addClass('hidden');
         $('tr[id="row_monit.service.interface"]').addClass('hidden');
         $('tr[id="row_monit.service.start"]').removeClass('hidden');
         $('tr[id="row_monit.service.stop"]').removeClass('hidden');
         $('tr[id="row_monit.service.depends"]').removeClass('hidden');
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
               $('tr[id="row_monit.service.depends"]').addClass('hidden');
               break;
            default:
               $('tr[id="row_monit.service.path"]').removeClass('hidden');
               $('tr[id="row_monit.service.timeout"]').removeClass('hidden');
         }
      };
      

      function SelectServiceTests(){
         var serviceType = $('#monit\\.service\\.type').val();
         $('#monit\\.service\\.tests').html('');
         $.each(serviceTests, function(index, value) {
            if ($.inArray(value.type, testSyntax.serviceTestMapping[serviceType]) !== -1) {
               $('#monit\\.service\\.tests').append('<option value="' + value.uuid + '">' + value.name + '</option>');
            }
         });
         $('.selectpicker').selectpicker('refresh');
      }
      
      /**
       * get service test definitions
       */
      var serviceTests = [];
      $('#DialogEditService').on('shown.bs.modal', function() {
         $.post("/api/monit/settings/search/test", {current: 1, rowCount: -1}, function(data, status) {
            if (status == "success") {
               serviceTests = Object.assign({}, data.rows);
               
               var serviceType = $('#monit\\.service\\.type').val();
               // filter test option list
               $('#monit\\.service\\.tests > option').each(function(index, option){
                  $.each(serviceTests, function(index, value) {
                     if (value.uuid === option.value) {
                        if ($.inArray(value.type, testSyntax.serviceTestMapping[serviceType]) === -1) {
                           option.remove();
                        }
                        return false;
                     }
                  });
               });
               $('.selectpicker').selectpicker('refresh');
            }
         });
         ShowHideFields();
      });
      $('#monit\\.service\\.type').on('changed.bs.select', function() {
         SelectServiceTests();
         ShowHideFields();
      });
      $('#monit\\.service\\.pidfile').on('input', function() {ShowHideFields();});
      $('#monit\\.service\\.match').on('input', function() {ShowHideFields();});
      $('#monit\\.service\\.path').on('input', function() {ShowHideFields();});
      $('#monit\\.service\\.timeout').on('input', function() {ShowHideFields();});
      $('#monit\\.service\\.address').on('input', function() {ShowHideFields();});
      $('#monit\\.service\\.interface').on('changed.bs.select', function() {ShowHideFields();});

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

      // parse monit.test.condition and split it into testConditionStages array
      // the testConditionStages array is used to build the #monit_test_condition_form
      var testConditionStages = [];
      function ParseTestCondition(conditionString = null) {
         var conditionType = $('#monit\\.test\\.type option:selected').html();
         // old behaviour for Custom types needs no parsing
         if (conditionType === 'Custom') {
            return;
         }
         if (conditionString === null) {
            conditionString = $('#monit\\.test\\.condition').val();
         }

         // simple array, e.g 'Existence'
         if ($.type(testSyntax.testConditionMapping[conditionType]) === 'array') {
            // defaults to the first element
            if (conditionString === '') {
               testConditionStages[0] = testSyntax.testConditionMapping[conditionType][0];
            } else {
               $.each(testSyntax.testConditionMapping[conditionType], function(index, value) {
                  var regExp = new RegExp("^" + value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'i');
                  if (conditionString !== undefined && regExp.test(conditionString)) {
                     testConditionStages[0] = value;
                     // first match is sufficient since elements are unique
                     return false;
                  }
               });
            }
         }

         // objects, e.g. 'System Resource'
         if ($.type(testSyntax.testConditionMapping[conditionType]) === 'object') {
            if (conditionString === '') {
               if ($.type(testSyntax.testConditionMapping[conditionType][0]) === 'string') {
                  testConditionStages[0] = testSyntax.testConditionMapping[conditionType][0];
               } else {
                  testConditionStages[0] = Object.keys(testSyntax.testConditionMapping[conditionType])[0];
               }
            } else {
               // get the longest key match
               var keyLength = 0;
               $.each(testSyntax.testConditionMapping[conditionType], function(key, value) {
                  if ($.isNumeric(key)) {
                     key = value;
                  }
                  // escape metacharacters with replace() e.g. ^loadavg \(1min\)
                  var regExp = new RegExp(key.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'i');
                  if (regExp.test(conditionString)) {
                     if (key.length > keyLength) {
                        keyLength = key.length;
                        testConditionStages[0] = key;
                        // no break of the $.each loop because we need the longest match here
                     }
                  }
               });
            }

            if ($.type(testSyntax.testConditionMapping[conditionType][testConditionStages[0]]) === 'object') {
               if (Object.keys(testSyntax.testConditionMapping[conditionType][testConditionStages[0]])[0] === '_OPERATOR') {
                  if (conditionString === '') {
                     testConditionStages[1] = testSyntax.operators[0];
                  } else {
                     $.each(testSyntax.operators, function(index, value) {
                        var regExp = new RegExp(value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'i');
                        if (regExp.test(conditionString)) {
                           testConditionStages[1] = value;
                           // first match is sufficient since operators are unique
                           return false;
                        }
                     });
                  }
                  if (Object.keys(testSyntax.testConditionMapping[conditionType][testConditionStages[0]]['_OPERATOR'])[0] === '_VALUE') {
                     if (conditionString === '') {
                        testConditionStages[2] = '';
                     } else {
                        if (testConditionStages[1] === undefined) {
                           testConditionStages[1] = testSyntax.operators[0];
                        }
                        var regExp = new RegExp(testConditionStages[0].replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '.*' + testConditionStages[1].replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '[^\\d]+(\\d+)');
                        var regRes = conditionString.match(regExp);
                        if($.type(regRes) === 'array') {
                           testConditionStages[2] = regRes[1];
                        } else {
                           testConditionStages[2] = '';
                        }
                     }
                     if (Object.keys(testSyntax.testConditionMapping[conditionType][testConditionStages[0]]['_OPERATOR'])[1] === '_UNIT') {
                        if (conditionString === '') {
                           var unitType = testSyntax.testConditionMapping[conditionType][testConditionStages[0]]['_OPERATOR']['_UNIT'];
                           if (unitType in testSyntax.units) {
                              testConditionStages[3] = testSyntax.units[unitType][0];
                           } else {
                              testConditionStages[3] = unitType;
                           }
                        } else {
                           var regExp = new RegExp(testConditionStages[0].replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '.*' + testConditionStages[1].replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '[^\\d]+\\d+\\s+([^\/]+)');
                           var regRes = conditionString.match(regExp);
                           var unitType = testSyntax.testConditionMapping[conditionType][testConditionStages[0]]['_OPERATOR']['_UNIT'];
                           testConditionStages[3] = undefined;
                           if($.type(regRes) === 'array') {
                              if (unitType in testSyntax.units) {
                                 $.each(testSyntax.units.unitType, function(index, value) {
                                    var regExp = new RegExp(value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'i');
                                    if (regExp.test(regRes[1])) {
                                       testConditionStages[3] = value;
                                       // first match is sufficient since units are unique
                                       return false;
                                    }
                                 });
                              } else {
                                 testConditionStages[3] = unitType;
                              }
                           }
                           if ( testConditionStages[3] === undefined) {
                              if (unitType in testSyntax.units) {
                                 testConditionStages[3] = testSyntax.units[unitType][0];
                              } else {
                                 testConditionStages[3] = unitType;
                              }
                           }
                        }
                        if (Object.keys(testSyntax.testConditionMapping[conditionType][testConditionStages[0]]['_OPERATOR'])[2] === '_RATE') {
                           testConditionStages[4] = testSyntax.testConditionMapping[conditionType][testConditionStages[0]]['_OPERATOR']['_RATE'];
                        }
                     }
                  }
               } else if (Object.keys(testSyntax.testConditionMapping[conditionType][testConditionStages[0]])[0] === '_VALUE') {
                  if (conditionString === '') {
                        testConditionStages[1] = '';
                  } else {
                     var regExp = new RegExp(testConditionStages[0].replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '.*' + testConditionStages[1].replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '[^\\s]+(.*)');
                     var regRes = conditionString.match(regExp);
                     if($.type(regRes) === 'array') {
                        testConditionStages[1] = regRes[1];
                     }
                  }
               }
            }
         }
      };

      function setStageSelectOnChange(i) {
         var stage = '#monit_test_condition_stage' + i;
         $(stage).on('changed.bs.select', function() {
            testConditionStages[i-1] = $(stage).val();
            UpdateTestConditionForm();
            $(stage).focus();
         });
      };
      
      function setStageInputOnChange(i) {
         var stage = '#monit_test_condition_stage' + i;
         $(stage).on('input', function() {
            testConditionStages[i-1] = $(stage).val();
            UpdateTestConditionForm();
            $(stage).focus();
         });
      };

      function UpdateTestConditionForm() {
         var conditionType = $('#monit\\.test\\.type option:selected').html();
         // old behaviour for custom types
         if (conditionType === 'Custom') {
            $('#monit_test_condition_form').remove();
            $('#monit_test_condition_preview').remove();
            $('input[id="monit.test.condition"]').removeClass('hidden');
            return;
         } else {
            if (!$('#monit_test_condition_form').length) {
               $('input[id="monit.test.condition"]').addClass('hidden');
               $('input[id="monit.test.condition"]').after('<div id="monit_test_condition_preview">' + $('#monit\\.test\\.condition').val() + "</div");
               $('input[id="monit.test.condition"]').after('<div id="monit_test_condition_form"></div>');
            }
         }
         var newConditionString = '';

         // Stage 1 - left operand
         $('#monit_test_condition_form').html(
            '<div class="dropdown bootsrap-select">' +
            '  <select id="monit_test_condition_stage1" class="selectpicker"></select>' +
            '</div>');
         setStageSelectOnChange(1);
         if ($.type(testSyntax.testConditionMapping[conditionType]) === 'array') {
            $.each(testSyntax.testConditionMapping[conditionType], function(index, value) {
               $('#monit_test_condition_stage1').append('<option value="' + value + '">' + value + '</option>');
            });
            $('#monit_test_condition_stage1').val(testConditionStages[0]);
            newConditionString = testConditionStages[0];
         }
         if ($.type(testSyntax.testConditionMapping[conditionType]) === 'object') {
            $.each(testSyntax.testConditionMapping[conditionType], function(key, value) {
               // for arrays use the value as option
               if ($.isNumeric(key)) {
                  key = value;
               }
               $('#monit_test_condition_stage1').append('<option value="' + key + '">' + key + '</option>');
            });
            $('#monit_test_condition_stage1').val(testConditionStages[0]);
            newConditionString = testConditionStages[0];

            // Stage 2 - operator/value
            if ($.type(testSyntax.testConditionMapping[conditionType][testConditionStages[0]]) === 'object') {
               if (Object.keys(testSyntax.testConditionMapping[conditionType][testConditionStages[0]])[0] === '_OPERATOR') {
                  // add new dropdown
                  $('#monit_test_condition_stage1').after('<select id="monit_test_condition_stage2" class="selectpicker"></select>');
                  setStageSelectOnChange(2);
                  $.each(testSyntax.operators, function(index, value) {
                     $('#monit_test_condition_stage2').append('<option value="' + value + '">' + value + '</option>');
                  });
                  if (testConditionStages[1] === undefined) {
                     ParseTestCondition(newConditionString);
                  }
                  $('#monit_test_condition_stage2').val(testConditionStages[1]);
                  newConditionString += ' ' + testConditionStages[1];

                  // Stage 3 - right operand
                  if (Object.keys(testSyntax.testConditionMapping[conditionType][testConditionStages[0]]['_OPERATOR'])[0] === '_VALUE') {
                     $('#monit_test_condition_stage2').after('<input type="text" class="form-control" id="monit_test_condition_stage3">');
                     setStageInputOnChange(3);
                     $('#monit_test_condition_stage3').val(testConditionStages[2]);
                     newConditionString += ' ' + testConditionStages[2];

                     // Stage 4 - unit
                     if (Object.keys(testSyntax.testConditionMapping[conditionType][testConditionStages[0]]['_OPERATOR'])[1] === '_UNIT') {
                        var unitType = testSyntax.testConditionMapping[conditionType][testConditionStages[0]]['_OPERATOR']['_UNIT'];
                        if (testConditionStages[3] === undefined) {
                           ParseTestCondition(newConditionString);
                        }
                        if (unitType in testSyntax.units) {
                           $('#monit_test_condition_stage3').after('<select id="monit_test_condition_stage4" class="selectpicker"></select>');
                           setStageSelectOnChange(4);
                           $.each(testSyntax.units[unitType], function(index, value) {
                              $('#monit_test_condition_stage4').append('<option value="' + value + '">' + value + '</option>');
                           });
                           if($.inArray(testConditionStages[3], testSyntax.units[unitType]) === -1) {
                              ParseTestCondition(newConditionString);
                           }
                           $('#monit_test_condition_stage4').val(testConditionStages[3]);
                           newConditionString += ' ' + testConditionStages[3];
                        } else {
                           newConditionString += ' ' + unitType;
                        }

                        // Stage 5 - rate
                        if (Object.keys(testSyntax.testConditionMapping[conditionType][testConditionStages[0]]['_OPERATOR'])[2] === '_RATE') {
                           newConditionString += testConditionStages[4];
                        }
                     }
                  }
               }
               if (Object.keys(testSyntax.testConditionMapping[conditionType][testConditionStages[0]])[0] === '_VALUE') {
                  $('#monit_test_condition_stage1').after('<input type="text" class="form-control" id="monit_test_condition_stage2">');
                  setStageInputOnChange(2);
                  if (testConditionStages[1] === undefined) {
                     testConditionStages[1] = '';
                  }
                  $('#monit_test_condition_stage2').val(testConditionStages[1]);
                  newConditionString += ' ' + testConditionStages[1];
               }
            }
         }
         $('#monit\\.test\\.condition').val(newConditionString);
         $('#monit_test_condition_preview').html($('#monit\\.test\\.condition').val());
         $('.selectpicker').selectpicker('refresh');
      };

      $('#DialogEditTest').on('shown.bs.modal', function() {
         ParseTestCondition();
         UpdateTestConditionForm();
      });

      $('#DialogEditTest').on('hide.bs.modal', function() {
         $('#monit_test_condition_form').remove();
         $('#monit_test_condition_preview').remove();
      });

      $('#monit\\.test\\.type').on('changed.bs.select', function() {
         $('#monit\\.test\\.condition').val('');
         testConditionStages = [];
         ParseTestCondition();
         UpdateTestConditionForm();
         $('#monit\\.test\\.type').focus();
      });

      $('#monit\\.test\\.action').on('changed.bs.select', function() {
         var actiontype = $('#monit\\.test\\.action').val();
         $('tr[id="row_monit.test.path"]').addClass('hidden');
         if (actiontype === 'exec') {
            $('tr[id="row_monit.test.path"]').removeClass('hidden');
         }
      });

      $("#grid-tests").UIBootgrid({
         'search':'/api/monit/settings/search/test/',
         'get':'/api/monit/settings/get/test/',
         'set':'/api/monit/settings/set/test/',
         'add':'/api/monit/settings/set/test/',
         'del':'/api/monit/settings/del/test/'
      });

      var testSyntax = {};
      ajaxGet("/api/monit/settings/get/0/0/1", {}, function(data,status) {
         if (status == "success") {
            testSyntax = Object.assign({}, data.syntax);
         }
      });

   });
</script>

<div class="alert alert-info hidden" role="alert" id="configChangedMsg">
   <button class="btn btn-primary pull-right" id="btnApplyConfig" type="button"><b>{{ lang._('Apply changes') }}</b> <i id="btnApplyConfigProgress"></i></button>
   {{ lang._('The Monit configuration has been changed') }} <br /> {{ lang._('You must apply the changes in order for them to take effect.')}}
</div>
<div class="alert alert-info hidden" role="alert" id="responseMsg"></div>

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
                     <b>{{ lang._('Save changes') }}</b><i id="btnSaveGeneralProgress"></i>
                  </button>
                  <button class="btn btn-primary" id="btnImportSystemNotification" type="button" style="margin-left: 3px;">
                     <b>{{ lang._('Import System Notification')}}</b><i id="btnImportSystemNotificationProgress"></i>
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
                <th data-column-id="type" data-type="string">{{ lang._('Type') }}</th>
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
</div>
{# include dialogs #}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditAlert,'id':'DialogEditAlert','label':'Edit Alert&nbsp;&nbsp;<small>NOTE: For a detailed description see monit(1) section "ALERT MESSAGES".</small>'])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditService,'id':'DialogEditService','label':'Edit Service'])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditTest,'id':'DialogEditTest','label':'Edit Test&nbsp;&nbsp;<small>NOTE: For a detailed description see monit(1) section "SERVICE TESTS".</small>'])}}
