<script>
    'use strict';
    $( document ).ready(function() {
        // list alias tables on load, link related events when loaded
        ajaxGet('/api/firewall/alias_util/aliases/', {}, function(data, status) {
            if (status === "success") {
                $.each(data, function(key, value) {
                    $('#tablename').append($("<option/>").attr("value", value).text(value));
                });
                $('#tablename').selectpicker('refresh');
                // link change event, change grid content
                $('#tablename').change(function(){
                    $('#alias_content').bootgrid('destroy');
                    let grid = $('#alias_content').UIBootgrid({
                        search: '/api/firewall/alias_util/list/' + $(this).val(),
                        options: {
                            rowCount: [20, 50, 100, 200, -1],
                            formatters: {
                                commands: function (column, row) {
                                    return '<button type="button" class="btn btn-xs btn-default delete-ip bootgrid-tooltip" title="{{ lang._('Delete') }}" data-row-id="' + row.ip + '"><span class="fa fa-fw fa-trash-o"></span></button>';
                                },
                            }
                        }
                    });
                    grid.on('loaded.rs.jquery.bootgrid', function () {
                        grid.find('.delete-ip').on('click', function (e) {
                            ajaxCall(
                                '/api/firewall/alias_util/delete/' + $('#tablename').val(),
                                {'address': $(this).data('row-id')},
                                function () {
                                    std_bootgrid_reload('alias_content')
                                });
                        });
                        // header labels
                        $("span.fa-long-arrow-right").attr('title', "{{lang._('in')}}").tooltip();
                        $("span.fa-long-arrow-left").attr('title', "{{lang._('out')}}").tooltip();
                        $("span.fa-times").attr('title', "{{lang._('block')}}").tooltip();
                        $("span.fa-play").attr('title', "{{lang._('pass')}}").tooltip();

                    });
                });
                $('#tablename').change();
            }
        });

        // flush table.. first ask user if it's ok to do so..
        $("#flushtable").on('click', function(event){
          event.preventDefault();
          BootstrapDialog.show({
            type: BootstrapDialog.TYPE_DANGER,
            title: "{{ lang._('Tables') }}",
            message: "{{ lang._('Do you really want to flush this table?') }}",
            buttons: [{
              label: "{{ lang._('No') }}",
              action: function(dialogRef) {
                dialogRef.close();
              }}, {
                label: "{{ lang._('Yes') }}",
                action: function(dialogRef) {
                    ajaxCall("/api/firewall/alias_util/flush/"+$('#tablename').val(),{}, function(){
                        $('#tablename').change();
                        dialogRef.close();
                    });
                }
              }]
          });
        });

        $("#btn_quick_add").on('click', function(){
            ajaxCall("/api/firewall/alias_util/add/"+$('#tablename').val(),{'address':$("#quick_add").val()},function(){
                $("#quick_add").val("");
                $('#tablename').change();
            });

        });

        // update bogons
        $("#update_bogons").on('click', function(event){
            event.preventDefault();
            $("#update_bogons_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall("/api/firewall/alias_util/update_bogons", {}, function(){
                $("#update_bogons_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });

        $('#find_references').on('click', function (event) {
            BootstrapDialog.show({
                type: BootstrapDialog.TYPE_DEFAULT,
                title: '{{ lang._('Find references') }}',
                message: '<p>{{ lang._('Enter an IP address to show in which aliases it is used.') }}</p>' +
                         '<div class="input-group" style="display: block;">' +
                             '<input id="ip" type="text" class="form-control"/>' +
                             '<span class="input-group-btn">' +
                                 '<button class="btn btn-default" id="ip-search"><span class="fa fa-search"></span></button>' +
                             '</span>' +
                         '</div>' +
                         '<div id="ip-results" style="margin-top: 15px;"></div>',
                buttons: [{
                    label: '{{ lang._('Close') }}',
                    cssClass: 'btn-primary',
                    action: function(dialogRef) {
                        dialogRef.close();
                    }
                }],
                onshown: function(dialogRef) {
                    // Remove all pre-existing event listeners, just to be sure.
                    $('#ip-search').off();
                    $('#ip-search').on('click', function(event) {
                        if (!$("#ip-search > span").hasClass('fa-search')) {
                            // already searching
                            return;
                        }
                        let ip = $('#ip').val();
                        $("#ip-search > span").removeClass('fa-search').addClass("fa-spinner fa-pulse");
                        ajaxCall('/api/firewall/alias_util/find_references', {'ip': ip}, function(data, status) {
                            if (status !== 'success' || data['status'] !== 'ok') {
                                $('#ip-results').html(
                                    '<div class="alert alert-warning">' +
                                    '{{ lang._('Error while fetching matching aliases:') }}' + ' ' + data['status'] +
                                    '</div>');
                            } else if (data.matches === null || data.matches.length === 0) {
                                $('#ip-results').html(
                                    '<div class="alert alert-info">' +
                                    '{{ lang._('No matches for this IP.') }}' +
                                    '</div>');
                            } else {
                                $('#ip-results').html('<div id="ip-results-list" class="list-group"></div>');
                                data.matches.forEach(function (alias) {
                                    let item = $('<a>')
                                        .addClass('list-group-item list-group-item-border')
                                        .text(alias)
                                        .css('cursor', 'pointer')
                                        .on('click', function() {
                                            $('#tablename').val($(this).text()).change();
                                            dialogRef.close();
                                        });
                                    $('#ip-results-list').append(item);
                                });
                            }
                            $("#ip-search > span").removeClass('fa-spinner fa-pulse').addClass("fa-search");
                        });
                    });
                }
            });
        });

        // refresh
        $("#refresh").on('click', function(){
            $('#tablename').change();
        });

    });
</script>
<style type="text/css">
    /* On upstream Bootstrap, these properties are set in list-group-item.*/
    .list-group-item-border {
        border: 1px solid #ddd;
    }

    .list-group-item-border:first-child {
        border-top-left-radius: 4px;
        border-top-right-radius: 4px;
    }

    .list-group-item-border:last-child {
        border-bottom-left-radius: 4px;
        border-bottom-right-radius: 4px;
    }
    .btn.pull-right {
        margin-left: 3px;
    }

</style>

<section class="page-content-main">
    <div class="container-fluid">
        <div class="row">
            <section class="col-xs-12">
                <div class="row">
                  <div class="col-xs-4">
                      <select id="tablename" class="selectpicker" data-width="auto" data-live-search="true">
                      </select>
                      <button class="btn btn-default" id="refresh">
                          <i class="fa fa-refresh" aria-hidden="true"></i>
                      </button>
                      <button class="btn btn-default" id="flushtable">
                          {{ lang._('Flush') }}
                      </button>
                  </div>
                  <div class="col-xs-4">
                    <div class="input-group">
                      <span class="input-group-btn">
                        <button class="btn btn-default" type="button" id="btn_quick_add">
                            <i class="fa fa-plus" aria-hidden="true"></i>
                        </button>
                      </span>
                      <input type="text" class="form-control" id="quick_add" placeholder="{{ lang._('Quick add address') }}">
                    </div>
                  </div>
                  <div class="col-xs-4">
                      <button class="btn btn-default pull-right" id="update_bogons"><i id="update_bogons_progress" class=""></i>
                        {{ lang._('Update bogons') }}
                      </button>
                      <button class="btn btn-default pull-right" id="find_references" title="{{ lang._('Look up which aliases match a certain IP address') }}">
                          <span class="fa fa-search"></span> {{ lang._('Find references') }}
                      </button>
                  </div>
                </div>
            </section>
            <section class="col-xs-12">
                <div class="content-box">
                    <div class="table-responsive">
                        <table class="table table-striped" id="alias_content" data-store-selection="true">
                            <thead>
                                <tr>
                                    <th data-column-id="ip" data-type="string"  data-identifier="true">{{ lang._('IP Address') }}</th>
                                    <th data-column-id="in_block_p" data-type="numeric" data-visible="false">
                                      <span class="fa fa-fw fa-long-arrow-right text-info"></span><span class="fa fa-fw fa-times text-danger"></span>
                                      <br/><small>{{lang._('packets')}}</small>
                                    </th>
                                    <th data-column-id="in_block_b" data-type="numeric" data-visible="false">
                                      <span class="fa fa-fw fa-long-arrow-right text-info"></span><span class="fa fa-fw fa-times text-danger"></span>
                                      <br/><small>{{lang._('bytes')}}</small>
                                    </th>
                                    <th data-column-id="in_pass_p" data-type="numeric">
                                      <span class="fa fa-fw fa-long-arrow-right text-info"></span><span class="fa fa-fw fa-play text-success"></span>
                                      <br/><small>{{lang._('packets')}}</small>
                                    </th>
                                    <th data-column-id="in_pass_b" data-type="numeric">
                                      <span class="fa fa-fw fa-long-arrow-right text-info"></span><span class="fa fa-fw fa-play text-success"></span>
                                      <br/><small>{{lang._('bytes')}}</small>
                                    </th>
                                    <th data-column-id="out_block_p" data-type="numeric" data-visible="false">
                                      <span class="fa fa-fw fa-long-arrow-left text-info"></span><span class="fa fa-fw fa-times text-danger"></span>
                                      <br/><small>{{lang._('packets')}}</small>
                                    </th>
                                    <th data-column-id="out_block_b" data-type="numeric" data-visible="false">
                                      <span class="fa fa-fw fa-long-arrow-left text-info"></span><span class="fa fa-fw fa-times text-danger"></span>
                                      <br/><small>{{lang._('bytes')}}</small>
                                    </th>
                                    <th data-column-id="out_pass_p" data-type="numeric">
                                      <span class="fa fa-fw fa-long-arrow-left text-info"></span><span class="fa fa-fw fa-play text-success"></span>
                                      <br/><small>{{lang._('packets')}}</small>
                                    </th>
                                    <th data-column-id="out_pass_b" data-type="numeric">
                                      <span class="fa fa-fw fa-long-arrow-left text-info"></span><span class="fa fa-fw fa-play text-success"></span>
                                      <br/><small>{{lang._('bytes')}}</small>
                                    </th>
                                    <th data-column-id="commands" data-formatter="commands"></th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </div>
</section>
