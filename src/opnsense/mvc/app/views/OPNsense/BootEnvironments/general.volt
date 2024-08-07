<script>
    $(document).ready(function() {
        /* before rendering anything, check if ZFS is supported */
        ajaxGet('/api/bootenvironments/general/is_supported/', {}, function(data, status) {
            if (data && data.supported) {
                $("#grid-env").UIBootgrid({
                    get: '/api/bootenvironments/general/get/',
                    set: '/api/bootenvironments/general/set/',
                    add: '/api/bootenvironments/general/add/',
                    del: '/api/bootenvironments/general/del/',
                    search: '/api/bootenvironments/general/search',
                    commands: {
                        activate_be: {
                            method: function(event) {
                                let uuid = $(this).data("row-id") !== undefined ? $(this).data("row-id") : '';
                                ajaxCall('/api/bootenvironments/general/activate/' + uuid, {},  function(data, status){
                                    if (data.result) {
                                        $('#grid-env').bootgrid('reload');
                                    }
                                });
                            },
                            classname: 'fa fa-fw fa-check',
                            title: "{{ lang._('activate boot environment') }}",
                            sequence: 10
                        },
                    },
                    options: {
                        selection: false,
                        multiSelect: false,
                        rowSelect: false,
                        formatters: {
                            "timestamp": function (column, row) {
                                return moment.unix(row[column.id]).local().format('YYYY-MM-DD HH:mm');
                            }
                        }
                    }
                });

                $("#supported_block").show();
            } else {
                $("#unsupported_block").show();
            }
        });
    });
</script>

<ul id="maintabs" class="nav nav-tabs" data-tabs="tabs">
    <li id="beGeneral" class="active">
        <a data-toggle="tab" href="#tab_general">{{ lang._('General') }}</a>
    </li>
</ul>

<div class="tab-content content-box">
    <div id="tab_general" class="tab-pane fade in active">
        <div id="supported_block" style="display: none;">
            <table id="grid-env" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="frmGeneral">
                <thead>
                    <tr>
                        <th data-column-id="uuid" data-type="string" data-visible="false" data-identifier="true" data-sortable="false">{{ lang._('uuid') }}</th>
                        <th data-column-id="name" data-type="string" data-visible="true" data-identifier="false">{{ lang._('Name') }}</th>
                        <th data-column-id="active" data-type="string" data-visible="true" data-sortable="false">{{ lang._('Active') }}</th>
                        <th data-column-id="mountpoint" data-type="string" data-visible="true">{{ lang._('Mountpoint') }}</th>
                        <th data-column-id="size" data-type="string" data-visible="true" data-sortable="false">{{ lang._('Size') }}</th>
                        <th data-column-id="created" data-type="string" data-formatter="timestamp">{{ lang._('Created') }}</th>
                        <th data-column-id="commands" data-width="9em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6"></td>
                        <td>
                            <button data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-fw fa-plus"></span></button>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div id="unsupported_block" style="display: none;">
            <div class="container-fluid">
                <h2><i class="fa fa-fw fa-warning"></i> Oops! It looks like you're using a UFS file system.</h2>
                <p>
                    This plugin needs a ZFS file system to manage Boot Environments. Unfortunately, UFS
                    doesn't support this feature. To use Boot Environments, you'll need to switch to a
                    ZFS file system.
                </p>
                <p>
                    For more information on how to migrate to ZFS, please refer to our documentation
                    or support resources.
                </p>
            </div>
        </div>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':generalForm,'id':'frmGeneral', 'label':lang._('Edit boot environment')])}}
