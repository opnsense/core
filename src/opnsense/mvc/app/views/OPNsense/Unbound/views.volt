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

<style>
    #infosection {
        margin: 1em;
    }
    #viewsinfosection {
        margin: 1em;
    }
    .help-block {
        display: none;
    }
    .help-block.show {
        display: block !important;
    }

    /* Fix tab alignment when help toggle is present */
    #help_toggle_container {
        vertical-align: bottom;
        line-height: inherit;
        display: flex;
        align-items: center;
        white-space: nowrap;
    }

    #help_toggle_container.hidden {
        display: none !important;
    }

    #help_toggle_container small,
    #help_toggle_container a {
        vertical-align: baseline;
    }

    /* Style the help toggle link to match background and remove hover effects */
    #help_toggle_container a {
        background-color: transparent !important;
        border: none !important;
        text-decoration: none !important;
        color: inherit !important;
    }

    #help_toggle_container a:hover,
    #help_toggle_container a:focus,
    #help_toggle_container a:active {
        background-color: transparent !important;
        text-decoration: none !important;
        color: inherit !important;
        outline: none !important;
        box-shadow: none !important;
    }
</style>

 <script>
    $( document ).ready(function() {
        function updateViewFilter() {
            ajaxGet('/api/unbound/hosts/listViews', {}, function(data, status){
                if (status === "success" && data.rows !== undefined) {
                    $('#view_filter').empty();
                    for (let i=0; i < data.rows.length ; ++i) {
                        let row = data.rows[i];
                        $("#view_filter").append($("<option/>").val(row.uuid).html(row.name));
                    }
                    $('#view_filter').selectpicker('refresh');

                    // Ensure change event is attached (only once)
                    $("#view_filter").off('change.viewfilter').on('change.viewfilter', function(){
                        $("#{{formGridHost['table_id']}}").bootgrid('reload');
                    });
                }
            });
        }

        const grid_hosts = $("#{{formGridHost['table_id']}}").UIBootgrid({
                search: '/api/unbound/hosts/searchHost/',
                get: '/api/unbound/hosts/getHost/',
                set: '/api/unbound/hosts/setHost/',
                add: '/api/unbound/hosts/addHost/',
                del: '/api/unbound/hosts/delHost/',
                toggle: '/api/unbound/hosts/toggleHost/',
                options:{
                    initialSearchPhrase: getUrlHash('search'),
                    requestHandler: function(request){
                        if ( $('#view_filter').val().length > 0) {
                            request['views'] = $('#view_filter').val();
                        }
                        return request;
                    }
            }
        });
        grid_hosts.on("loaded.rs.jquery.bootgrid", function (e){
            // Populate filter dropdown on initial load
            if ($("#view_filter > option").length == 0) {
                updateViewFilter();
            }
        });

        $("#{{formGridView['table_id']}}").UIBootgrid({
            search: '/api/unbound/views/searchView/',
            get: '/api/unbound/views/getView/',
            set: '/api/unbound/views/setView/',
            add: '/api/unbound/views/addView/',
            del: '/api/unbound/views/delView/',
            toggle: '/api/unbound/views/toggleView/'
        });

        $("#{{formGridSubnet['table_id']}}").UIBootgrid({
            search: '/api/unbound/subnets/searchSubnet/',
            get: '/api/unbound/subnets/getSubnet/',
            set: '/api/unbound/subnets/setSubnet/',
            add: '/api/unbound/subnets/addSubnet/',
            del: '/api/unbound/subnets/delSubnet/',
            toggle: '/api/unbound/subnets/toggleSubnet/'
        });

        $("#reconfigureAct").SimpleActionButton({
            onAction: function(data, status) {
                // Refresh tables and view filter after successful apply
                if (status === "success") {
                    $("#{{formGridHost['table_id']}}").bootgrid('reload');
                    $("#{{formGridSubnet['table_id']}}").bootgrid('reload');
                    updateViewFilter();
                }
            }
        });

        /**
         * Quick view filter on top
         */
        $("#filter_container").detach().prependTo('#{{formGridHost["table_id"]}}-header > .row > .actionBar > .actions');

        // update history on tab state and implement navigation
        if (window.location.hash != "") {
            $('a[href="' + window.location.hash + '"]').click()
        }
        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            history.pushState(null, null, e.target.hash);
        });
        $(window).on('hashchange', function(e) {
            $('a[href="' + window.location.hash + '"]').click()
        });

        // Full help toggle - follow OPNsense standard pattern
        $("#views_help_toggle").click(function(event) {
            console.log("Help toggle clicked"); // Debug log
            $(this).toggleClass("fa-toggle-on fa-toggle-off");
            $(this).toggleClass("text-success text-danger");
            if ($(this).hasClass("fa-toggle-on")) {
                console.log("Turning help ON"); // Debug log
                if (window.sessionStorage) {
                    sessionStorage.setItem('all_help_preset', 1);
                }
                $('.help-block').addClass("show").removeClass("hidden");
            } else {
                console.log("Turning help OFF"); // Debug log
                $('.help-block').addClass("hidden").removeClass("show");
                if (window.sessionStorage) {
                    sessionStorage.setItem('all_help_preset', 0);
                }
            }
            event.preventDefault();
            return false;
        });

        // Restore help state from session storage - only show if explicitly set to 1
        if (window.sessionStorage && sessionStorage.getItem('all_help_preset') === "1") {
            console.log("Restoring help state from storage"); // Debug log
            $("#views_help_toggle").toggleClass("fa-toggle-on fa-toggle-off").toggleClass("text-success text-danger");
            $('.help-block').addClass("show").removeClass("hidden");
        }
    });
</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#views">{{ lang._('Views') }}</a></li>
    <li><a data-toggle="tab" href="#subnets">{{ lang._('Subnets') }}</a></li>
    <li><a data-toggle="tab" href="#hosts">{{ lang._('Hosts') }}</a></li>
    <li class="pull-right" id="help_toggle_container">
        <a href="#"><small>full help</small> <i class="fa fa-toggle-off text-danger" id="views_help_toggle"></i></a>
    </li>
</ul>

<div class="tab-content content-box">
    <div id="views" class="tab-pane fade in active">
        {{ partial('layout_partials/base_bootgrid_table', formGridView)}}
        <div id="viewsinfosection" class="tab-content col-xs-12 __mb help-block">
            {{ lang._('Views make it possible to send specific DNS answers based on the IP address of the client.') }}
            {{ lang._('To take effect a view must be associated with a subnet.') }}
        </div>
    </div>
    <div id="subnets" class="tab-pane fade in">
        {{ partial('layout_partials/base_bootgrid_table', formGridSubnet)}}
        <div id="infosection" class="tab-content col-xs-12 __mb help-block">
            {{ lang._('Multiple subnets may be associated with the same view. Subnet ranges should not overlap.') }}
        </div>
    </div>
    <div id="hosts" class="tab-pane fade in">
        <div class="hidden">
            <!-- filter per view container -->
            <div id="filter_container" class="btn-group">
                <select id="view_filter"  data-title="{{ lang._('All Views') }}" class="selectpicker" data-live-search="true" data-size="5"  multiple data-width="200px">
                </select>
            </div>
        </div>
        {{ partial('layout_partials/base_bootgrid_table', formGridHost)}}
        <div id="hostsinfosection" class="tab-content col-xs-12 __mb help-block">
            {{ lang._('Split View DNS Hosts entries are scoped to subnets via views.') }}
            {{ lang._('Split View DNS Hosts entries are separate from global "Overrides" hosts.') }}
            {{ lang._('"Overrides" hosts cannot be associated with a view.') }}
        </div>
    </div>
</div>
{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/unbound/service/reconfigure'}) }}
{# include dialogs #}
{{ partial("layout_partials/base_dialog",['fields':formDialogView,'id':formGridView['edit_dialog_id'],'label':lang._('Edit view')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogSubnet,'id':formGridSubnet['edit_dialog_id'],'label':lang._('Edit subnet')])}}
{{ partial("layout_partials/base_dialog",['fields':formDialogHost,'id':formGridHost['edit_dialog_id'],'label':lang._('Edit host')])}}
