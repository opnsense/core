{#
 # Copyright (c) 2014-2015 Deciso B.V.
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

    $( document ).ready(function() {
        /**
         * inline open dialog, go back to previous page on exit
         */
        function openDialog(uuid) {
            var editDlg = "DialogEdit";
            var setUrl = "/api/unbound/settings/setDot/";
            var getUrl = "/api/unbound/settings/getDot/";
            var urlMap = {};
            urlMap['frm_' + editDlg] = getUrl + uuid;
            mapDataToFormUI(urlMap).done(function () {
                // update selectors
                $('.selectpicker').selectpicker('refresh');
                // clear validation errors (if any)
                clearFormValidation('frm_' + editDlg);
                // show
                $('#'+editDlg).modal({backdrop: 'static', keyboard: false});
                $('#'+editDlg).on('hidden.bs.modal', function () {
                    // go back to previous page on exit
                    parent.history.back();
                });
            });


            // define save action
            $("#btn_"+editDlg+"_save").unbind('click').click(function(){
                saveFormToEndpoint(setUrl+uuid, 'frm_' + editDlg, function(){
                    // do reconfigure of unbound after save (because we're leaving back to the sender)
                    ajaxCall("/api/unbound/service/reconfigure", {}, function(data,status) {
                        $("#"+editDlg).modal('hide');
                    });
                }, true);
            });

        }
        /*************************************************************************************************************
         * link grid actions
         *************************************************************************************************************/

        $("#grid-dot").UIBootgrid(
                {   'search':'/api/unbound/settings/searchDot',
                    'get':'/api/unbound/settings/getDot/',
                    'set':'/api/unbound/settings/setDot/',
                    'add':'/api/unbound/settings/addDot/',
                    'del':'/api/unbound/settings/delDot/',
                    'toggle':'/api/unbound/settings/toggleDot/'
                }
        );

        {% if (selected_uuid|default("") != "") %}
            openDialog('{{selected_uuid}}');
        {% endif %}

        /*************************************************************************************************************
         * Commands
         *************************************************************************************************************/

        /**
         * Reconfigure unbound - activate changes
         */
        $("#reconfigureAct").SimpleActionButton();

	updateServiceControlUI('unbound');
    });

</script>


<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#grid-dot">{{ lang._('Servers') }}</a></li>
</ul>
<div class="tab-content content-box">
    <div id="dot" class="tab-pane fade in active">
        <table id="grid-dot" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogEdit">
            <thead>
            <tr>
                <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                <th data-column-id="server" data-type="string">{{ lang._('Address') }}</th>
                <th data-column-id="port" data-type="int">{{ lang._('Port') }}</th>
                <th data-column-id="verify" data-type="int">{{ lang._('Hostname') }}</th>
                <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Edit') }} | {{ lang._('Delete') }}</th>
                <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
            </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
            <tr>
                <td></td>
                <td>
                    <button data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-fw fa-plus"></span></button>
                    <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-fw fa-trash-o"></span></button>
                </td>
            </tr>
            </tfoot>
        </table>
    </div>
    <div class="col-md-12">
        <hr/>
        <button class="btn btn-primary" id="reconfigureAct"
                data-endpoint='/api/unbound/service/reconfigure'
                data-label="{{ lang._('Apply') }}"
                data-error-title="{{ lang._('Error reconfiguring unbound') }}"
                type="button"
        ></button>
        <br/><br/>
    </div>
</div>

{# include dialog #}
{{ partial("layout_partials/base_dialog",['fields':formDialogEdit,'id':'DialogEdit','label':lang._('Edit server')])}}
