<script>
    $( document ).ready(function() {
        $("#{{formGridGroup['table_id']}}").UIBootgrid(
            {   search:'/api/firewall/group/search_item',
                get:'/api/firewall/group/get_item/',
                set:'/api/firewall/group/set_item/',
                add:'/api/firewall/group/add_item/',
                del:'/api/firewall/group/del_item/',
                options:{
                        formatters:{
                            commands: function (column, row) {
                                if (row.uuid.includes('-') === true) {
                                    // exclude buttons for internal groups
                                    return '<button type="button" class="btn btn-xs btn-default command-edit bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-pencil"></span></button> ' +
                                        '<button type="button" class="btn btn-xs btn-default command-copy bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-clone"></span></button>' +
                                        '<button type="button" class="btn btn-xs btn-default command-delete bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-trash-o"></span></button>';
                                }
                            },
                            ifname: function (column, row) {
                                return '<a href="/firewall_rules.php?if='+row.ifname+'">'+row.ifname+'</a>';
                            },
                        }
                    }
            }
        );
        $("#reconfigureAct").SimpleActionButton();
    });
</script>
<div class="tab-content content-box">
    {{ partial('layout_partials/base_bootgrid_table', formGridGroup)}}
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/firewall/group/reconfigure'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogEdit,'id':formGridGroup['edit_dialog_id'],'label':lang._('Edit Group')])}}
