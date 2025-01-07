{#
 # Copyright (c) 2025 Deciso B.V.
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
        $("#{{formGridTunable['table_id']}}").UIBootgrid(
            {   search:'/api/core/tunables/search_item/',
                get:'/api/core/tunables/get_item/',
                set:'/api/core/tunables/set_item/',
                add:'/api/core/tunables/add_item/',
                del:'/api/core/tunables/del_item/',
                options: {
                    formatters: {
                        "tunable_type": function (column, row) {
                            let retval = "{{ lang._('environment')}}";
                            switch (row[column.id]) {
                                case 'w':
                                    retval = "{{ lang._('runtime')}}";
                                    break;
                                case 't':
                                    retval = "{{ lang._('boot-time')}}";
                                    break;
                                case 'y':
                                    retval = "{{ lang._('read-only')}}";
                                    break;
                            }
                            return retval;
                        }
                    }
                }
            }
        );
        $("#reset_defaults").click(function(event){
            event.preventDefault();
            BootstrapDialog.show({
                type:BootstrapDialog.TYPE_DANGER,
                title: "{{ lang._('Tunable') }}",
                message: "{{ lang._('Are you sure you want to reset all tunables back to factory defaults?')}}",
                buttons: [
                    {
                        label: "{{ lang._('No') }}",
                        action: function(dialogRef) {
                            dialogRef.close();
                        }
                    },
                    {
                        label: "{{ lang._('Yes') }}",
                        action: function(dialogRef) {
                            ajaxCall('/api/core/tunables/reset', {}, function(){
                                dialogRef.close();
                                $('#grid').bootgrid('reload');
                            });
                        }
                    }
                ]
            });
        });
        $("#{{formGridTunable['table_id']}} > tfoot > tr > td:eq(0)").append($("#reset_defaults").detach());

        $("#reconfigureAct").SimpleActionButton();
    });
</script>
<div class="hidden">
    <!-- moved into tfoot after load -->
    <button id="reset_defaults" class="btn btn-danger btn-xs" data-toggle="tooltip" title="{{ lang._('Default') }}">
        <i class="fa fa-trash-o fa-fw"></i>
    </button>
</div>
<div class="tab-content content-box">
    {{ partial('layout_partials/base_bootgrid_table', formGridTunable)}}
    <div class="col-md-12">
        <div id="{{formGridTunable['edit_alert_id']}}" class="alert alert-info" style="display: none" role="alert">
            {{ lang._('After changing settings, please remember to apply them with the button below') }}
        </div>
        <hr/>
        <button class="btn btn-primary" id="reconfigureAct"
                data-endpoint='/api/core/tunables/reconfigure'
                data-label="{{ lang._('Apply') }}"
                data-error-title="{{ lang._('Error reconfiguring Tunables') }}"
                type="button"
        ></button>
        <br/><br/>
    </div>
</div>


{{ partial("layout_partials/base_dialog",['fields':formDialogTunable,'id':formGridTunable['edit_dialog_id'],'label':lang._('Edit Tunable')])}}
