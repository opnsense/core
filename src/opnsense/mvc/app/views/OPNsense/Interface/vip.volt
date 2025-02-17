<script>
    $( document ).ready(function() {
        $("#{{formGridVip['table_id']}}").UIBootgrid(
            {   search:'/api/interfaces/vip_settings/searchItem/',
                get:'/api/interfaces/vip_settings/getItem/',
                set:'/api/interfaces/vip_settings/setItem/',
                add:'/api/interfaces/vip_settings/addItem/',
                del:'/api/interfaces/vip_settings/delItem/',
                options:{
                    initialSearchPhrase: getUrlHash('search'),
                    requestHandler: function(request){
                        if ( $('#mode_filter').val().length > 0) {
                            request['mode'] = $('#mode_filter').val();
                        }
                        return request;
                    },
                    formatters: {
                        networkFormatter: function(column, row) {
                            return row.subnet + (row.subnet_bits ? '/' + row.subnet_bits : '');
                        }
                    },
                }
            }
        );
        $("#mode_filter").change(function(){
            $('#{{formGridVip['table_id']}}').bootgrid('reload');
        });

        $("#vip\\.mode").change(function(){
            $(".mode").closest("tr").hide();
            let show_advanced = $("#show_advanced_formDialogDialogVip").hasClass("fa-toggle-on");

            $(".mode_"+$(this).val()).each(function(){
                if (($(this).hasClass("advanced") && show_advanced) || !$(this).hasClass("advanced")) {
                    $(this).closest("tr").show();
                }
            });
            // carp button
            if ($(this).val() == 'carp') {
                $("#vip\\.vhid").css('width', '100px').addClass('btn-group');
                $(".carp_btn").show();
            } else {
                $("#vip\\.vhid").css('width', '');
                $(".carp_btn").hide();
            }

        });

        // hook mode change to "show advanced" toggle to show dependant advanced fields
        $("#show_advanced_formDialogDialogVip").click(function(e){
            $("#vip\\.mode").change();
        });

        let vhid_btn = $("<button type='button' class='btn carp_btn btn-default btn-group'>").html("{{ lang._('Select an unassigned VHID')}}");

        $("#vip\\.vhid").closest("td").prepend(
            $("<div class='btn-group'>").append(
                $("#vip\\.vhid").detach(),
                vhid_btn
            )
        );

        $("#mode_filter_container").detach().prependTo('#{{formGridVip["table_id"]}}-header > .row > .actionBar > .actions');
        /**
         * select an unassigned carp vhid
         */
        vhid_btn.click(function(){
            ajaxGet("/api/interfaces/vip_settings/get_unused_vhid", {}, function(data){
                if (data.vhid !== undefined) {
                    $("#vip\\.vhid").val(data.vhid);
                }
            });
        });

        $("#reconfigureAct").SimpleActionButton();
    });
</script>
<div class="tab-content content-box">
  <div class="hidden">
      <!-- filter per type container -->
      <div id="mode_filter_container" class="btn-group">
          <select id="mode_filter"  data-title="{{ lang._('Filter type') }}" class="selectpicker" multiple="multiple" data-width="200px">
              <option value="ipalias">{{ lang._('IP Alias') }}</option>
              <option value="carp">{{ lang._('CARP') }}</option>
              <option value="proxyarp">{{ lang._('Proxy ARP') }}</option>
          </select>
      </div>
  </div>
  {{ partial('layout_partials/base_bootgrid_table', formGridVip)}}
</div>
{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/interfaces/vip_settings/reconfigure'}) }}
{{ partial('layout_partials/base_dialog',['fields':formDialogVip,'id':formGridVip['edit_dialog_id'],'label':lang._('Edit Virtual IP')])}}
