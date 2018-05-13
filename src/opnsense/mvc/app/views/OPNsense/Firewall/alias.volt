<script>
    $( document ).ready(function() {
        $("#grid-aliases").UIBootgrid(
            {   search:'/api/firewall/alias/searchItem',
                get:'/api/firewall/alias/getItem/',
                set:'/api/firewall/alias/setItem/',
                add:'/api/firewall/alias/addItem/',
                del:'/api/firewall/alias/delItem/',
                toggle:'/api/firewall/alias/toggleItem/'
            }
        );
    });
</script>

<section class="page-content-main">
    <div class="container-fluid">
        <div class="row">
            <section class="col-xs-12">
                <div class="content-box">
                    <table id="grid-aliases" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogAlias">
                        <thead>
                        <tr>
                            <th data-column-id="uuid" data-type="string" data-identifier="true"  data-visible="false">{{ lang._('ID') }}</th>
                            <th data-column-id="enabled" data-width="6em" data-type="string" data-formatter="rowtoggle">{{ lang._('Enabled') }}</th>
                            <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
                            <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
                            <th data-column-id="commands" data-width="7em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        </tbody>
                        <tfoot>
                        <tr>
                            <td></td>
                            <td>
                                <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                                <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-trash-o"></span></button>
                            </td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        </div>
    </div>
</section>

{# include dialogs #}
{{ partial("layout_partials/base_dialog",['fields':formDialogAlias,'id':'DialogAlias','label':lang._('Edit Alias')])}}
