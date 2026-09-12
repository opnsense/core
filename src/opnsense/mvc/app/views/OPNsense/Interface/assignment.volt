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
    $( document ).ready(function() {
        $("#{{formGridAssignment['table_id']}}").UIBootgrid(
            {   search:'/api/interfaces/assignment/search_item/',
                get:'/api/interfaces/assignment/get_item/',
                set:'/api/interfaces/assignment/set_item/',
                add:'/api/interfaces/assignment/add_item/',
                del:'/api/interfaces/assignment/del_item/',
                options: {
                    formatters: {
                        statusformatter: function (column, row) {
                            return $("<div/>").addClass(row[column.id])[0];
                        },
                        ipv4typeformatter: function (column, row) {
                            return row.type4 === 'staticv4' ? row.ipaddr : row['%type4'];
                        },
                        ipv6typeformatter: function (column, row) {
                            return row.type6 === 'staticv6' ? row.ipaddrv6 : row['%type6'];
                        },
                    }
                }
            }
        );
        $("#reconfigureAct").SimpleActionButton();

        $('select.ipoption').change(function(){
            let this_id = $(this).attr('id').split('.')[1];
            let this_value =  $(this).val();
            let show_advanced = $("#show_advanced_dialog_dialogAssignment").hasClass('fa-toggle-on');
            $("."+this_id).closest('tr').hide();
            $("."+this_id + '_' + $(this).val()).each(function(){
                let tr = $(this).closest("tr");
                if ((tr.data('advanced') && show_advanced) || !tr.data('advanced')) {
                    tr.show();
                }
            });
        });

        $("#interface\\.hw_settings_overwrite").change(function(){
            if ($(this).is(':checked')) {
                $(".hw_settings_overwrite").closest('tr').show();
            } else {
                $(".hw_settings_overwrite").closest('tr').hide();
            }
        });

    });
</script>
<div class="tab-content content-box">
    {{ partial('layout_partials/base_bootgrid_table', formGridAssignment)}}
</div>
{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/interfaces/assignment/reconfigure'}) }}
{{ partial('layout_partials/base_dialog',['fields':formDialogAssignment,'id':formGridAssignment['edit_dialog_id'],'label':lang._('Edit Assignment')])}}
