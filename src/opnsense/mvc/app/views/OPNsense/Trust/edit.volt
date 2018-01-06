{#
Copyright (C) 2017 Smart-Soft

All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

1. Redistributions of source code must retain the above copyright notice,
   this list of conditions and the following disclaimer.

2. Redistributions in binary form must reproduce the above copyright
   notice, this list of conditions and the following disclaimer in the
   documentation and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
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

<script type="text/javascript">
    $(document).ready(function () {
        $("#grid-crl-certs").UIBootgrid(
            {
                search: '/api/trust/revocation/searchCerts/{{ uuid }}',
                add: '/api/trust/revocation/addCert/{{ uuid }}/',
                get: '/api/trust/revocation/getCert/{{ uuid }}/',
                del: '/api/trust/revocation/delCert/',
                options: {
                    formatters: {
                        "commands": function (column, row) {
                            return "<button type=\"button\" class=\"btn btn-xs btn-default command-delete\" data-row-id=\"" + $("<div/>").text(row.uuid).html() + "\" title=\"{{ lang._('Delete this certificate from the CRL') }}\" alt=\"{{ lang._('Delete this certificate from the CRL') }}\"><span class=\"fa fa-trash-o\"></span></button>";
                        },
                    }
                }
            }
        );
    })
    ;
</script>
{{ lang._('Currently Revoked Certificates for CRL') }} {{ descr }}
<div id="crl-certs">
    <table id="grid-crl-certs" class="table table-condensed table-hover table-striped table-responsive"
           data-editDialog="RevocationCert">
        <thead>
        <tr>
            <th data-column-id="Name" data-type="string">{{ lang._('Certificate Name') }}</th>
            <th data-column-id="Reason" data-type="string">{{ lang._('Revocation Reason') }}</th>
            <th data-column-id="Revoked" data-type="string">{{ lang._('Revoked At') }}</th>
            <th data-column-id="commands" data-width="7em" data-formatter="commands"
                data-sortable="false">{{ lang._('Commands') }}</th>
        </tr>
        </thead>
        <tbody>
        </tbody>
        <tfoot>
        <tr>
            <td></td>
            <td>
                <button data-action="add" type="button" class="btn btn-xs btn-default"><span
                            class="fa fa-plus"></span></button>
            </td>
        </tr>
        </tfoot>
    </table>
</div>
{{ partial("layout_partials/base_dialog",['fields':revocationCert,'id':'RevocationCert','label':lang._('Revoke a Certificate')]) }}