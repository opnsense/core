{#
 # Copyright (c) 2019 Deciso B.V.
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1.  Redistributions of source code must retain the above copyright notice,
 # this list of conditions and the following disclaimer.
 #
 # 2.  Redistributions in binary form must reproduce the above copyright notice,
 # this list of conditions and the following disclaimer in the documentation
 # and/or other materials provided with the distribution.
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

<p><strong>{{ lang._('Are you sure you want to power off the system?') }}</strong></p>

<button id="do-halt" class="btn btn-primary">{{ lang._('Yes') }}</button>
<a href="/" class="btn btn-default">{{ lang._('No') }}</a>

<script>
    'use strict';

    $(document).ready(function() {
        $('#do-halt').on('click', function() {
            BootstrapDialog.show({
                type:BootstrapDialog.TYPE_INFO,
                title: '{{ lang._('Your device is powering off') }}',
                closable: false,
                message: '{{ lang._('The system is powering off now.') }}',
                onshow: function (dialogRef) {
                    ajaxCall('/api/core/system/halt');
                },
            });
        });
    });
</script>
