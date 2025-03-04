{#
 # Copyright (c) 2019 Deciso B.V.
 # Copyright (c) 2019 Michael Muenz <m.muenz@gmail.com>
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

<script>
   $(document).ready(function() {
       var data_get_map = {'frm_dnsbl_settings':"/api/unbound/settings/get"};
       let init_state = null;
       mapDataToFormUI(data_get_map).done(function(data){
           formatTokenizersUI();
           $('.selectpicker').selectpicker('refresh');
           init_state = $('.safesearch').is(':checked');
       });

       $("#reconfigureAct").SimpleActionButton({
          onPreAction: function() {
              const dfObj = new $.Deferred();
              let safesearch_changed = !($('.safesearch').is(':checked') == init_state);
              init_state = $('.safesearch').is(':checked');
              saveFormToEndpoint("/api/unbound/settings/set", 'frm_dnsbl_settings', function(){
                  if (safesearch_changed) {
                      /* Restart Unbound and apply the DNSBL after it has finished */
                      ajaxCall('/api/unbound/service/reconfigure', {}, function(data,status) {
                          dfObj.resolve();
                      });
                  } else {
                      dfObj.resolve();
                  }
              }, true, function () { dfObj.reject(); });
              return dfObj;
          }
      });

      updateServiceControlUI('unbound');
   });
</script>

<div class="content-box __mb">
    {{ partial("layout_partials/base_form",['fields':dnsblForm,'id':'frm_dnsbl_settings'])}}
</div>
{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/unbound/service/dnsbl'}) }}
