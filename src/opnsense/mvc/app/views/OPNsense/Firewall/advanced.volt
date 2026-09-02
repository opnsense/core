{#
 # Copyright (c) 2026 Deciso B.V.
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
        function updateScrubOptions() {
            $('.scrub_option').closest('tr').toggle($('#filter\\.advanced\\.scrub\\.enabled').is(':checked'));
        }

        mapDataToFormUI({'frm_advanced': '/api/firewall/advanced/get'}).done(function() {
            updateScrubOptions();
        });

        $('#filter\\.advanced\\.scrub\\.enabled').change(updateScrubOptions);

        $('#reconfigureAct').SimpleActionButton({
            onPreAction: function() {
                const deferred = new $.Deferred();
                saveFormToEndpoint(
                    '/api/firewall/advanced/set',
                    'frm_advanced',
                    function() { deferred.resolve(); },
                    true,
                    function() { deferred.reject(); }
                );
                return deferred;
            }
        });
    });
</script>

<div class="content-box">
    {{ partial('layout_partials/base_form', ['fields': formAdvanced, 'id': 'frm_advanced']) }}
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/firewall/advanced/reconfigure'}) }}
