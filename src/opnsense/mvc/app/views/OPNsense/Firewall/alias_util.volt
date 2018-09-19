<script>
    $( document ).ready(function() {
        // list alias tables on load, link related events when loaded
        ajaxGet("/api/firewall/alias_util/aliases/", {}, function(data, status) {
            if (status == "success") {
                $.each(data, function(key, value) {
                    $('#tablename').append($("<option/>").attr("value", value).text(value));
                });
                $('#tablename').selectpicker('refresh');
                // link change event, change grid content
                $('#tablename').change(function(){
                    $("#alias_content").bootgrid('destroy');
                    $("#alias_content > tbody").empty();
                    ajaxGet("/api/firewall/alias_util/list/" + $(this).val(), {}, function(data, status) {
                        if (status == "success") {
                            $.each(data, function(key, value) {
                                $("#alias_content > tbody").append(
                                    $("<tr/>").append($("<td/>").text(value)).append($("<td/>"))
                                );
                            });
                        }
                        var grid = $("#alias_content").bootgrid({
                            ajax: false,
                            selection: false,
                            multiSelect: false,
                            formatters: {
                                commands: function (column, row)
                                {
                                    return "<button type=\"button\" class=\"btn btn-xs btn-default delete-ip\" data-row-id=\"" + row.ip + "\"><span class=\"fa fa-trash-o\"></span></button>";
                                }
                            }
                        });
                        grid.on("loaded.rs.jquery.bootgrid", function(){
                            grid.find(".delete-ip").on("click", function(e) {
                                ajaxCall(
                                    "/api/firewall/alias_util/delete/"+$('#tablename').val(),
                                    {'address': $(this).data('row-id')},
                                    function(){
                                        $('#tablename').change();
                                });
                            });
                        });
                    });
                });
                $('#tablename').change();
            }
        });

        // flush table.. first ask user if it's ok to do so..
        $("#flushtable").click(function(event){
          event.preventDefault()
          BootstrapDialog.show({
            type:BootstrapDialog.TYPE_DANGER,
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

        // update bogons
        $("#update_bogons").click(function(event){
            event.preventDefault()
            $("#update_bogons_progress").addClass("fa fa-spinner fa-pulse");
            ajaxCall("/api/firewall/alias_util/update_bogons", {}, function(){
                $("#update_bogons_progress").removeClass("fa fa-spinner fa-pulse");
            });
        });

        // refresh
        $("#refresh").click(function(){
            $('#tablename').change();
        });

    });
</script>

<section class="page-content-main">
    <div class="container-fluid">
        <div class="row">
            <section class="col-xs-12">
                    <select id="tablename" class="selectpicker" data-width="auto" data-live-search="true">
                    </select>
                    <button class="btn btn-default" id="refresh">
                        <i class="fa fa-refresh" aria-hidden="true"></i>
                    </button>
                    <button class="btn btn-default" id="flushtable">
                        {{ lang._('Flush') }}
                    </button>
                    <button class="btn btn-default pull-right" id="update_bogons"><i id="update_bogons_progress" class=""></i>
                      {{ lang._('Update bogons') }}
                    </button>
            </section>
            <section class="col-xs-12">
                <div class="content-box">
                    <div class="table-responsive">
                        <table class="table table-striped" id="alias_content">
                            <thead>
                                <tr>
                                    <th data-column-id="ip" data-type="string"  data-identifier="true">{{ lang._('IP Address') }}</th>
                                    <th data-column-id="idx" data-formatter="commands"></th>
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
