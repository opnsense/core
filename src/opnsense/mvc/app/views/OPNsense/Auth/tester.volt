{#
# Copyright (c) 2026 Konstantinos Spartalis (cspartalis@potatonetworks.com)
# All rights reserved.
#
# Redistribution and use in source and binary forms, with or without modification,
# are permitted provided that the following conditions are met:
#
# 1. Redistributions of source code must retain the above copyright notice,
# this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright notice,
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

<script>
    $(document).ready(function () {
        var data_get_map = { 'frm_testerSettings': "/api/auth/tester/getSettings" };
        mapDataToFormUI(data_get_map).done(function () {
            $('.selectpicker').selectpicker('refresh');
        });

        $("#frm_testerSettings").on('keydown', 'input', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                $("#btn_test").click();
            }
        });

        $("#btn_test").click(function () {
            if (!$("#frm_testerSettings_progress").hasClass("fa-spinner")) {
                $("#test_results").hide();
                $("#frm_testerSettings_progress").addClass("fa fa-spinner fa-pulse");

                saveFormToEndpoint(
                    "/api/auth/tester/test",
                    'frm_testerSettings',
                    function (data) {
                        $("#frm_testerSettings_progress").removeClass("fa fa-spinner fa-pulse");
                        let tbody = $("#test_results > tbody");
                        tbody.empty();

                        if (data.status === 'ok') {
                            tbody.append(`<tr><td colspan="2">${data.message}</td></tr>`);

                            // Render groups
                            if (data.groups?.length) {
                                tbody.append(`<tr class="info"><td colspan="2"><b>{{ lang._('Groups') }}</b>: ${data.groups.join(" ")}</td></tr>`);
                            }

                            // Render privileges
                            if (data.privileges?.length) {
                                tbody.append(`<tr class="info"><td><b>{{ lang._('Uri') }}</b></td><td><b>{{ lang._('Networks') }}</b></td></tr>`);
                                data.privileges.forEach(item => {
                                    tbody.append(`<tr><td>${item[0]}</td><td>${item[1].join(', ')}</td></tr>`);
                                });
                            }

                            // Render attributes
                            if (data.attributes && Object.keys(data.attributes).length) {
                                tbody.append(`<tr class="info"><td colspan="2"><b>{{ lang._('Attributes received from server') }}</b></td></tr>`);
                                $.each(data.attributes, (k, v) => tbody.append(`<tr><td>${k}</td><td>${v}</td></tr>`));
                            }
                        } else {
                            tbody.append(`<tr><td colspan="2" class="text-danger">{{ lang._('Authentication failed') }}</td></tr>`);
                            $.each(data.errors || {}, (k, v) => tbody.append(`<tr><td>${k}</td><td class="text-danger">${v}</td></tr>`));
                        }

                        $("#test_results").show();
                    },
                    true,
                    function () {
                        $("#frm_testerSettings_progress").removeClass("fa fa-spinner fa-pulse");
                    }
                );
            }
        });
    });
</script>

<div class="tab-content content-box col-xs-12 __mb">
    <div id="tester">
        {{ partial("layout_partials/base_form",['fields':testerForm,'id':'frm_testerSettings',
        'apply_btn_id':'btn_test', 'apply_btn_title': lang._('Test')])}}
    </div>
    <table class="table table-condensed" id="test_results" style="display:none;">
        <thead>
            <tr>
                <th colspan="2">{{ lang._('Response')}}</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
</div>
