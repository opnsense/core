{#

OPNsense® is Copyright © 2014 – 2015 by Deciso B.V.
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
   * service settings
   **/

  $("#grid-services").UIBootgrid({
    'search':'/api/dyndns/checkipsettings/search/service/',
    'get':'/api/dyndns/checkipsettings/get/service/',
    'set':'/api/dyndns/checkipsettings/set/service/',
    'add':'/api/dyndns/checkipsettings/set/service/',
    'del':'/api/dyndns/checkipsettings/del/service/',
    'toggle':'/api/dyndns/checkipsettings/toggle/service/'
  });

  // Determine if at least one service is enabled as the default.  Allow, but warn if not.
  function is_service_default() {
    ajaxCall(url="/api/dyndns/checkipsettings/is_default/service/", sendData={}, callback=function(data,status) {
      if (status == 'success' && data['result']) {
         $("#responseMsg").css({'background-color': '', 'color': ''});
         $("#responseMsg").addClass('hidden');
         $("#responseMsg").html('');
      } else {
         $("#responseMsg").html("<b>{{ lang._('WARNING') }}: </b>{{ lang._('There is no default check IP service enabled!') }}");
         $("#responseMsg").css({'background-color': 'yellow', 'color': 'red'});
         $("#responseMsg").removeClass('hidden');
      }
    });
  }

  // Apply changes to the DOM
  function dom_update() {
    // suppress FDS selection checkbox and command buttons, and keep unselected
    $('table#grid-services tbody tr[data-row-id="FDS"]').each(function() {
      $(this).find('td.select-cell input.select-box').addClass('hidden');
      $(this).find('td button').filter('.command-edit,.command-copy,.command-delete').addClass('hidden');
      $(this).find('td.select-cell input.select-box').prop('checked', false);
      $(this).attr('aria-selected', false);
      $(this).removeClass('active');
    });

    // suppress fa-times icon for verify ssl peer disabled
    var col_num = $('table#grid-services thead tr th[data-column-id=verifysslpeer]').index() + 1;
    $('table#grid-services tbody tr td:nth-child(' + col_num + ') span').removeClass('fa-times');

    // hyper-link each service URL
    var col_num = $('table#grid-services thead tr th[data-column-id=url]').index() + 1;
    $('table#grid-services tbody tr td:nth-child(' + col_num + ')').each(function() {
      $(this).html('<a target="Check_IP" rel="noopener noreferrer" href="'+$(this).text()+'">'+$(this).text()+'</a>');
    });
  }

  // DOM observer
  var targetNode = document.querySelector('table#grid-services tbody');
  var config = { childList: true };
  var callback = function() {
    dom_update();
    is_service_default();
  };
  var observer = new MutationObserver(callback);
  observer.observe(targetNode, config);

  $('div#services').removeClass('hidden');


  /**
   * service test
   */

  $('[data-toggle][href=#test]').click(function(){
    $('#full_help_button').addClass('hidden');
    var data_get_map = {'frm_TestSettings':"/api/dyndns/checkiptest/get/service/"};
    mapDataToFormUI(data_get_map).done(function(data){
      // place actions to run after load, for example update form styles.
        formatTokenizersUI();
        $('.selectpicker').selectpicker('refresh');
        // request service status on load and update status box
        updateServiceControlUI('checkip');
    });
  });

  $('#btn_test').unbind('click').click(function(){
    $('#btn_test_progress').addClass("fa fa-spinner fa-pulse");

    var formData = {
      'service' : $('#checkip\\.test\\.service').val(),
      'interface' : $('#checkip\\.test\\.interface').val(),
      'ipv' : $('#checkip\\.test\\.ipv').val(),
    };

    ajaxCall(url="/api/dyndns/checkipservice/test", sendData=formData, callback=function(data,status) {
      $('#btn_test_progress').removeClass("fa fa-spinner fa-pulse");
      $('#btn_test').blur();

      var resultobj = JSON.parse(data['result']);

      var responseMsg  = '' +
             '<b>Service: </b>' + resultobj.Service   + '<br/>' +
           '<b>Interface: </b>' + resultobj.Interface + '<br/>' +
          '<b>IP Version: </b>' + resultobj.IPVersion + '<br/>' +
          '<b>IP Address: </b>' + resultobj.IPAddress + '<br/>';

      $("#responseMsg").html(responseMsg);
      $("#responseMsg").css({'background-color': '', 'color': ''});
      $("#responseMsg").removeClass("hidden");
    });
  });

});

</script>

<div class="alert alert-info hidden" role="alert" id="responseMsg"></div>

<ul class="nav nav-tabs" role="tablist" id="maintabs">
  <li class="active"><a data-toggle="tab" href="#services">{{ lang._('Service Settings') }}</a></li>
  <li><a data-toggle="tab" href="#test">{{ lang._('Service Test') }}</a></li>
</ul>

<div class="tab-content content-box">

  <div id="services" class="tab-pane fade in active hidden">

    <table id="grid-services" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogEditService">
      <thead>
        <tr>
          <th data-column-id="default" data-width="6em" data-type="string" data-formatter="rowtoggle" data-css-class="text-center" data-header-css-class="text-center">{{ lang._('Default') }}</th>
          <th data-column-id="name" data-width="6em" data-type="string">{{ lang._('Name') }}</th>
          <th data-column-id="url" data-width="24em" data-type="string" data-sortable="false">{{ lang._('URL') }}</th>
          <th data-column-id="verifysslpeer" data-width="9em" data-type="string" data-formatter="boolean" data-css-class="text-center" data-header-css-class="text-center">{{ lang._('Verify SSL Peer') }}</th>
          <th data-column-id="description" data-width="15em" data-type="string">{{ lang._('Description') }}</th>
          <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
          <th data-column-id="commands" data-width="8em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
        </tr>
      </thead>
      <tbody>
      </tbody>
      <tfoot>
      <tr>
        <td colspan="6"></td>
          <td>
            <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
            <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
          </td>
        </tr>
      </tfoot>
    </table>

    <table class="table">
      <tr>
        <td><a id="help_for_services" href="#" class="showhelp"><i class="fa fa-info-circle"></i></a></td>
        <td>
          <div class="hidden" data-for="help_for_services">
              <p><?= htmlspecialchars(gettext('These services will be used to check IP addresses for Dynamic DNS services, and RFC 2136 entries that have the "Use public IP" option enabled.')) ?></p>
              <p><?= htmlspecialchars(gettext('If a check IP service with a matching interface and IP version assignment is not found. The one enabled here as the default will be used.')) ?></p>
              <p><?= htmlspecialchars(gettext('The server must return the client IP address as a string in the format of the "address capture regular expression" option that is specified in the service configuration.')) ?></p>
              <hr/>
                 <?= htmlspecialchars(gettext('Examples of address capture regular expression')) ?><br/>
                 <?= htmlspecialchars(gettext('HTML containing address:')) ?>
            <pre><?= htmlspecialchars('<body>Current IP Address: (.*)</body>') ?></pre>
                 <?= htmlspecialchars(gettext('Text only address:')) ?>
            <pre><?= htmlspecialchars('^(.*)$') ?></pre>
              <hr/>
                 <?= htmlspecialchars(gettext('Examples of server PHP')) ?><br/>
                 <?= htmlspecialchars(gettext('HTML:')) ?>
            <pre><?= htmlspecialchars('<html><head><title>Current IP Check</title></head><body>Current IP Address: <?=$_SERVER[\'REMOTE_ADDR\']?></body></html>') ?></pre>
                 <?= htmlspecialchars(gettext('Text:')) ?>
            <pre><?= htmlspecialchars('<?=$_SERVER[\'REMOTE_ADDR\']?>') ?></pre>
          </div>
        </td>
      </tr>
    </table>

  </div>

  <div id="test" class="tab-pane fade in">
    {{ partial("layout_partials/base_form",['fields':testForm,'id':'frm_TestSettings'])}}

    <div class="col-md-12">
      <hr/>
        <button style='margin-bottom: 1em;' class="btn btn-primary" id="btn_test" type="button"><b>{{ lang._('Test Service') }}</b><i id="btn_test_progress" class=""></i></button>
    </div>
  </div>

</div>

{# include dialogs #}
{{ partial("layout_partials/base_dialog",['fields':serviceForm,'id':'DialogEditService','label':'Edit Service'])}}
