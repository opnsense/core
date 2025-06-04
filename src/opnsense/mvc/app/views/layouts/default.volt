<!doctype html>
<html lang="{{ langcode|safe }}" class="no-js">
  <head>

    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <meta name="robots" content="noindex, nofollow" />
    <meta name="keywords" content="" />
    <meta name="description" content="" />
    <meta name="copyright" content="" />
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1" />
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">

    <title>{{headTitle|default("OPNsense") }} | {{system_hostname}}.{{system_domain}}</title>
    {% set theme_name = ui_theme|default('opnsense') %}

    <!-- Favicon -->
    <link href="{{ cache_safe('/ui/themes/%s/build/images/favicon.png' | format(theme_name)) }}" rel="shortcut icon">

    <!-- css imports -->
    {% for filename in css_files -%}
    <link href="{{ cache_safe(theme_file_or_default(filename, theme_name)) }}" rel="stylesheet">
    {% endfor %}

    <!-- TODO: move to theme style -->
    <style>
      .menu-level-3-item {
        font-size: 90%;
        padding-left: 54px !important;
      }
      .typeahead {
        overflow: hidden;
      }
    </style>

    <!-- script imports -->
    {% for filename in javascript_files -%}
    <script src="{{ cache_safe(filename) }}"></script>
    {% endfor %}

    <script>
            // setup default scripting after page loading.
            $( document ).ready(function() {
                // hook into jquery ajax requests to ensure csrf handling.
                $.ajaxSetup({
                    'beforeSend': function(xhr) {
                        xhr.setRequestHeader("X-CSRFToken", "{{ csrf_token }}" );
                    }
                });
                // propagate ajax error messages
                $( document ).ajaxError(function( event, request ) {
                    if (request.responseJSON != undefined && request.responseJSON.errorMessage != undefined) {
                        BootstrapDialog.show({
                            type: BootstrapDialog.TYPE_DANGER,
                            title: request.responseJSON.errorTitle,
                            message:request.responseJSON.errorMessage,
                            buttons: [{
                                label: '{{ lang._('Close') }}',
                                action: function(dialogItself){
                                    dialogItself.close();
                                }
                            }]
                        });
                    }
                });

                // hide empty menu items
                $('#mainmenu > div > .collapse').each(function () {
                    // cleanup empty second level menu containers
                    $(this).find("div.collapse").each(function () {
                        if ($(this).children().length == 0) {
                            $("#mainmenu").find('[href="#' + $(this).attr('id') + '"]').remove();
                            $(this).remove();
                        }
                    });

                    // cleanup empty first level menu items
                    if ($(this).children().length == 0) {
                        $("#mainmenu").find('[href="#' + $(this).attr('id') + '"]').remove();
                    }
                });
                // hide submenu items
                $('#mainmenu .list-group-item').click(function(){
                    if($(this).attr('href').substring(0,1) == '#') {
                        $('#mainmenu .list-group-item').each(function(){
                            if ($(this).attr('aria-expanded') == 'true'  && $(this).data('parent') != '#mainmenu') {
                                $("#"+$(this).attr('href').substring(1,999)).collapse('hide');
                            }
                        });
                    }
                });

                initFormHelpUI();
                initFormAdvancedUI();
                addMultiSelectClearUI();

                updateSystemStatus();

                // Register collapsible table headers
                $('.table').on('click', 'thead', function(event) {
                    let collapse = $(event.currentTarget).next();
                    let id = collapse.attr('class');
                    if (collapse != undefined && id !== undefined && id === "collapsible") {
                        let icon = $('> tr > th > div > i', event.currentTarget);
                        if (collapse.is(':hidden')) {
                            collapse.toggle(0);
                            collapse.css('display', '');
                            icon.toggleClass("fa-angle-right fa-angle-down");
                            return;
                        }
                        icon.toggleClass("fa-angle-down fa-angle-right");
                        $('> tr > td', collapse).toggle(0);
                    }
                });

                // hook in live menu search
                $.ajax("/api/core/menu/search/", {
                    type: 'get',
                    cache: false,
                    dataType: "json",
                    data: {},
                    error : function (jqXHR, textStatus, errorThrown) {
                        console.log('menu.search : ' +errorThrown);
                    },
                    success: function (data) {
                        var menusearch_items = [];
                        $.each(data,function(idx, menu_item){
                            if (menu_item.Url != "") {
                                menusearch_items.push({
                                    id:$('<div/>').html(menu_item.Url).text(),
                                    name: $("<div/>").html(menu_item.breadcrumb).text()
                                });
                            }
                        });
                        $("#menu_search_box").typeahead({
                            source: menusearch_items,
                            matcher: function (item) {
                                var ar = this.query.trim();
                                if (ar == "") {
                                    return false;
                                }
                                ar = ar.toLowerCase().split(/\s+/);
                                if (ar.length == 0) {
                                    return false;
                                }
                                var it = this.displayText(item).toLowerCase();
                                for (var i = 0; i < ar.length; i++) {
                                    if (it.indexOf(ar[i]) == -1) {
                                        return false;
                                    }
                                }
                                return true;
                            },
                            afterSelect: function(item){
                                // (re)load page
                                if (window.location.href.split("#")[0].indexOf(item.id.split("#")[0]) > -1 ) {
                                    // same url, different hash marker
                                    window.location.href = item.id;
                                    window.location.reload();
                                } else {
                                    window.location.href = item.id;
                                }
                            }
                        });
                    }
                });

                // change search input size on focus() to fit results
                $("#menu_search_box").focus(function(){
                    $("#menu_search_box").css('width', '450px');
                    $("#system_status").hide();
                });
                $("#menu_search_box").focusout(function(){
                    $("#menu_search_box").css('width', '250px');
                    $("#system_status").show();
                });
                // enable bootstrap tooltips
                $('[data-toggle="tooltip"]').tooltip();

                // fix menu scroll position on page load
                $(".list-group-item.active").each(function(){
                    var navbar_center = ($( window ).height() - $(".collapse.navbar-collapse").height())/2;
                    $('html,aside').scrollTop(($(this).offset().top - navbar_center));
                });
                // prevent form submits on mvc pages
                $("form").submit(function() {
                    return false;
                });

                /* overwrite clipboard paste behavior and trim before paste */
                $("input").on('paste', function(e) {
                    let clipboard_data = e.originalEvent.clipboardData.getData("text/plain").trim();
                    if (clipboard_data.length > 0) {
                        e.preventDefault();
                        document.execCommand('insertText', false, clipboard_data);
                    }
                });

            });
        </script>

        <!-- theme JS -->
        <script src="{{ cache_safe(theme_file_or_default('/js/theme.js', theme_name)) }}"></script>
  </head>
  <body>
  <header class="page-head">
    <nav class="navbar navbar-default">
      <div class="container-fluid">
        <div class="navbar-header">
          <a class="navbar-brand" href="/">
            {% if file_exists(["/usr/local/opnsense/www/themes/",theme_name,"/build/images/default-logo.svg"]|join("")) %}
                <img class="brand-logo" src="{{ cache_safe('/ui/themes/%s/build/images/default-logo.svg' | format(theme_name)) }}" height="30" alt="logo"/>
            {% else %}
                <img class="brand-logo" src="{{ cache_safe('/ui/themes/%s/build/images/default-logo.png' | format(theme_name)) }}" height="30" alt="logo"/>
            {% endif %}
            {% if file_exists(["/usr/local/opnsense/www/themes/",theme_name,"/build/images/icon-logo.svg"]|join("")) %}
                <img class="brand-icon" src="{{ cache_safe('/ui/themes/%s/build/images/icon-logo.svg' | format(theme_name)) }}" height="30" alt="icon"/>
            {% else %}
                <img class="brand-icon" src="{{ cache_safe('/ui/themes/%s/build/images/icon-logo.png' | format(theme_name)) }}" height="30" alt="icon"/>
            {% endif %}
          </a>
          <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navigation">
            <span class="sr-only">{{ lang._('Toggle navigation') }}</span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
        </div>
        <button class="toggle-sidebar" data-toggle="tooltip right" title="{{ lang._('Toggle sidebar') }}" style="display:none;"><i class="fa fa-chevron-left"></i></button>
        <div class="collapse navbar-collapse">
          <ul class="nav navbar-nav navbar-right">
            <li id="menu_messages">
              <span class="navbar-text">{{session_username}}@{{system_hostname}}.{{system_domain}}</span>
            </li>
            <li>
              <span class="navbar-text" style="margin-left: 0">
                <i id="system_status" data-toggle="tooltip left" title="{{ lang._('Show system status') }}" style="cursor:pointer" class="fa fa-circle text-muted"></i>
              </span>
            </li>
            <li>
              <form class="navbar-form" role="search">
                <div class="input-group">
                  <div class="input-group-addon"><i class="fa fa-search"></i></div>
                  <input type="text" style="width: 250px;" class="form-control" tabindex="1" data-provide="typeahead" id="menu_search_box" autocomplete="off">
                </div>
              </form>
            </li>
          </ul>
        </div>
      </div>
    </nav>
  </header>

  <main class="page-content col-sm-9 col-sm-push-3 col-lg-10 col-lg-push-2">
      <!-- menu system -->
      {{ partial("layout_partials/base_menu_system") }}
      <div class="row">
        <!-- page header -->
        <header class="page-content-head">
          <div class="container-fluid">
            <ul class="list-inline">
              <li><h1>{{title | default("")}}</h1></li>
              <li class="btn-group-container" id="service_status_container"></li>
            </ul>
          </div>
        </header>

        <!-- page content -->
        <section class="page-content-main">
          <div class="container-fluid">
            <div class="row">
                <!-- notification banner dynamically inserted here (opnsense_status.js) -->

                <section class="col-xs-12">
                    <div id="messageregion"></div>
                        {{ content() }}
                </section>
            </div>
          </div>
        </section>
        <!-- page footer -->
        <footer class="page-foot">
          <div class="container-fluid">
            <a target="_blank" href="{{ product_website }}">{{ product_name }}</a> (c) {{ product_copyright_years }}
            <a target="_blank" href="{{ product_copyright_url }}">{{ product_copyright_owner }}</a>
          </div>
        </footer>
      </div>
    </main>

    <!-- dialog "wait for (service) action" -->
    <div class="modal fade" id="OPNsenseStdWaitDialog" tabindex="-1" data-backdrop="static" data-keyboard="false">
      <div class="modal-backdrop fade in"></div>
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-body">
            <p><strong>{{ lang._('Please wait...') }}</strong></p>
            <div class="progress">
               <div class="progress-bar progress-bar-info progress-bar-striped active" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width:100%"></div>
             </div>
          </div>
        </div>
      </div>
    </div>

    <script>
        $.extend(jQuery.fn.UIBootgrid.translations, {
            add: "{{ lang._('Add') }}",
            deleteSelected: "{{ lang._('Delete selected') }}",
            edit: "{{ lang._('Edit') }}",
            disable: "{{ lang._('Disable') }}",
            enable: "{{ lang._('Enable') }}",
            delete: "{{ lang._('Delete') }}",
            info: "{{ lang._('Info') }}",
            clone: "{{ lang._('Clone') }}",
            all: "{{ lang._('All') }}",
            search: "{{ lang._('Search') }}",
            removeWarning: "{{ lang._('Remove selected item(s)?') }}",
            noresultsfound: "{{ lang._('No results found') }}",
            refresh: "{{ lang._('Refresh') }}",
            infosTotal: "{{ lang._('Showing %s to %s of %s entries') | format('{{ctx.start}}','{{ctx.end}}','{{ctx.totalRows}}') }}",
            infos: "{{ lang._('Showing %s to %s') | format('{{ctx.start}}','{{ctx.end}}') }}",
            resetGrid: "{{ lang._('Reset grid layout') }}"
        });
    </script>

  </body>
</html>
