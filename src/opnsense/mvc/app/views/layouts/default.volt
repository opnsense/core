<!doctype html>
<!--[if IE 8 ]><html lang="en-US" class="ie ie8 lte9 lte8 no-js"><![endif]-->
<!--[if IE 9 ]><html lang="en-US" class="ie ie9 lte9 no-js"><![endif]-->
<!--[if (gt IE 9)|!(IE)]><!--><html lang="en-US" class="no-js"><!--<![endif]-->
  <head>

    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <meta name="robots" content="noindex, nofollow, noodp, noydir" />
    <meta name="keywords" content="" />
    <meta name="description" content="" />
    <meta name="copyright" content="" />
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1" />

    <title>{{headTitle|default("OPNsense") }} | {{system_hostname}}.{{system_domain}}</title>
    {% set theme_name = ui_theme|default('opnsense') %}

    <!-- include (theme) style -->
    <link href="{{ cache_safe('/ui/themes/%s/build/css/main.css' | format(theme_name)) }}" rel="stylesheet">

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

    <!-- legacy browser functions -->
    <script src="{{ cache_safe('/ui/js/polyfills.js') }}"></script>

    <!-- Favicon -->
    <link href="{{ cache_safe('/ui/themes/%s/build/images/favicon.png' | format(theme_name)) }}" rel="shortcut icon">

    <!-- Stylesheet for fancy select/dropdown -->
    <link rel="stylesheet" type="text/css" href="{{ cache_safe(theme_file_or_default('/css/bootstrap-select-1.13.3.css', theme_name)) }}">

    <!-- bootstrap dialog -->
    <link rel="stylesheet" type="text/css" href="{{ cache_safe(theme_file_or_default('/css/bootstrap-dialog.css', theme_name)) }}">

    <!-- Font awesome -->
    <link rel="stylesheet" href="{{ cache_safe('/ui/css/font-awesome.min.css') }}">

    <!-- JQuery -->
    <script src="/ui/js/jquery-3.5.1.min.js"></script>
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
                                menusearch_items.push({id:$('<div />').html(menu_item.Url).text(), name:menu_item.breadcrumb});
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
                    $("#menu_messages").hide();
                });
                $("#menu_search_box").focusout(function(){
                    $("#menu_search_box").css('width', '250px');
                    $("#menu_messages").show();
                });
                // enable bootstrap tooltips
                $('[data-toggle="tooltip"]').tooltip();

                // fix menu scroll position on page load
                $(".list-group-item.active").each(function(){
                    var navbar_center = ($( window ).height() - $(".collapse.navbar-collapse").height())/2;
                    $('html,aside').scrollTop(($(this).offset().top - navbar_center));
                });
            });
        </script>

        <!-- JQuery Tokenize2 (https://zellerda.github.io/Tokenize2/) -->
        <script src="{{ cache_safe('/ui/js/tokenize2.js') }}"></script>
        <link rel="stylesheet" type="text/css" href="{{ cache_safe(theme_file_or_default('/css/tokenize2.css', theme_name)) }}" rel="stylesheet" />

        <!-- Bootgrind (grid system from http://www.jquery-bootgrid.com/ )  -->
        <link rel="stylesheet" type="text/css" href="{{ cache_safe(theme_file_or_default('/css/jquery.bootgrid.css', theme_name)) }}" />
        <script src="{{ cache_safe('/ui/js/jquery.bootgrid.js') }}"></script>
        <!-- Bootstrap type ahead -->
        <script src="{{ cache_safe('/ui/js/bootstrap3-typeahead.min.js') }}"></script>

        <!-- OPNsense standard toolkit -->
        <script src="{{ cache_safe('/ui/js/opnsense.js') }}"></script>
        <script src="{{ cache_safe('/ui/js/opnsense_theme.js') }}"></script>
        <script src="{{ cache_safe('/ui/js/opnsense_ui.js') }}"></script>
        <script src="{{ cache_safe('/ui/js/opnsense_bootgrid_plugin.js') }}"></script>
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
              <form class="navbar-form" role="search">
                <div class="input-group">
                  <div class="input-group-addon"><i class="fa fa-search"></i></div>
                  <input type="text" style="width: 250px;" class="form-control" tabindex="1" data-provide="typeahead" id="menu_search_box">
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

    <!-- bootstrap script -->
    <script src="{{ cache_safe('/ui/js/bootstrap.min.js') }}"></script>
    <script src="{{ cache_safe('/ui/js/bootstrap-select.min.js') }}"></script>
    <!-- bootstrap dialog -->
    <script src="{{ cache_safe('/ui/js/bootstrap-dialog.min.js') }}"></script>
    <script>
    /* hook translations  when all JS modules are loaded*/
    Object.assign(jQuery.fn.bootgrid.prototype.constructor.Constructor.defaults.labels, {
        all: "{{ lang._('All') }}",
        infos: "{{ lang._('Showing %s to %s of %s entries') | format('{{ctx.start}}','{{ctx.end}}','{{ctx.total}}') }}",
        loading: "{{ lang._('Loading...') }}",
        noResults: "{{ lang._('No results found!') }}",
        refresh: "{{ lang._('Refresh') }}",
        search: "{{ lang._('Search') }}"
    });
    Object.assign(jQuery.fn.selectpicker.Constructor.DEFAULTS, {
        noneSelectedText: "{{ lang._('Nothing selected') }}",
        noneResultsText: "{{ lang._('No results matched {0}') }}",
        selectAllText: "{{ lang._('Select All') }}",
        deselectAllText: "{{ lang._('Deselect All') }}"
    });
    Object.assign(jQuery.fn.UIBootgrid.defaults, {
        removeWarningText: "{{ lang._('Remove selected item(s)?') }}"
    });
    Object.assign(stdDialogRemoveItem.defaults, {
        title: "{{ lang._('Remove') }}",
        accept: "{{ lang._('Yes') }}",
        decline: "{{ lang._('Cancel') }}"
    });
    </script>

  </body>
</html>
