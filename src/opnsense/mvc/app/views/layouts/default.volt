<!doctype html>
<!--[if IE 8 ]><html lang="en-US" class="ie ie8 lte9 lte8 no-js"><![endif]-->
<!--[if IE 9 ]><html lang="en-US" class="ie ie9 lte9 no-js"><![endif]-->
<!--[if (gt IE 9)|!(IE)]><!--><html lang="en-US" class="no-js"><!--<![endif]-->
  <head>

    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

    <meta name="robots" content="noindex, nofollow, noodp, noydir" />
    <meta name="keywords" content="" />
    <meta name="description" content="" />
    <meta name="copyright" content="" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />

    <title>{{title|default("OPNsense") }}</title>
    {% set theme_name = ui_theme|default('opnsense') %}

    <!-- include (theme) style -->
    <link href="/ui/themes/{{theme_name}}/build/css/main.css" rel="stylesheet">

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

    <!-- Favicon -->
    <link href="/ui/themes/{{theme_name}}/build/images/favicon.png" rel="shortcut icon">

    <!-- Stylesheet for fancy select/dropdown -->
    <link rel="stylesheet" type="text/css" href="/ui/themes/{{theme_name}}/build/css/bootstrap-select.css">

    <!-- bootstrap dialog -->
    <link href="/ui/themes/{{theme_name}}/build/css/bootstrap-dialog.css" rel="stylesheet" type="text/css" />

    <!-- Font awesome -->
    <link rel="stylesheet" href="/ui/css/font-awesome.min.css">

    <!-- JQuery -->
    <script type="text/javascript" src="/ui/js/jquery-3.2.1.min.js"></script>
    <script type="text/javascript" src="/ui/js/jquery-migrate-3.0.1.min.js"></script>
    <script type="text/javascript">
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
                            title: '{{ lang._('An API exception occured') }}',
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
                                menusearch_items.push({id:menu_item.Url, name:menu_item.breadcrumb});
                            }
                        });
                        $("#menu_search_box").typeahead({
                            source: menusearch_items,
                            matcher: function (item) {
                                var ar = this.query.trim()
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
            });
        </script>


        <!-- JQuery Tokenize (http://zellerda.com/projects/tokenize) -->
        <script type="text/javascript" src="/ui/js/jquery.tokenize.js"></script>
        <link rel="stylesheet" type="text/css" href="/ui/css/jquery.tokenize.css" />

        <!-- Bootgrind (grid system from http://www.jquery-bootgrid.com/ )  -->
        <link rel="stylesheet" type="text/css" href="/ui/css/jquery.bootgrid.css"/>
        <script type="text/javascript" src="/ui/js/jquery.bootgrid.js"></script>
        <script>
        /* patch translations into bootgrid library */
        Object.assign(
            jQuery.fn.bootgrid.prototype.constructor.Constructor.defaults.labels,
            {
                'all': "{{ lang._('All') }}",
                'infos': "{{ lang._('Showing %s to %s of %s entries') | format('{{ctx.start}}','{{ctx.end}}','{{ctx.total}}') }}",
                'loading': "{{ lang._('Loading...') }}",
                'noResults': "{{ lang._('No results found!') }}",
                'refresh': "{{ lang._('Refresh') }}",
                'search': "{{ lang._('Search') }}"
            }
        )
        </script>

        <!-- Bootstrap type ahead -->
        <script type="text/javascript" src="/ui/js/bootstrap3-typeahead.min.js"></script>

        <!-- OPNsense standard toolkit -->
        <script type="text/javascript" src="/ui/js/opnsense.js"></script>
        <script type="text/javascript" src="/ui/js/opnsense_ui.js"></script>
        <script type="text/javascript" src="/ui/js/opnsense_bootgrid_plugin.js"></script>
        {{javascript_include_when_exists('/ui/themes/' ~ theme_name ~ '/build/js/theme.js')}}

  </head>
  <body>
  <header class="page-head">
    <nav class="navbar navbar-default" role="navigation">
      <div class="container-fluid">
        <div class="navbar-header">
          <a class="navbar-brand" href="/">
            <img class="brand-logo" src="/ui/themes/{{theme_name}}/build/images/default-logo.png" height="30" alt="logo"/>
            <img class="brand-icon" src="/ui/themes/{{theme_name}}/build/images/icon-logo.png" height="30" alt="icon"/>
          </a>
          <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navigation">
            <span class="sr-only">{{ lang._('Toggle navigation') }}</span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
        </div>
        <div class="collapse navbar-collapse">
          <ul class="nav navbar-nav navbar-right">
            <li id="menu_messages"><a href="#">{{session_username}}@{{system_hostname}}.{{system_domain}}</a></li>
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
              <li class="__mb"><h1>{{title | default("")}}</h1></li>

              <li class="btn-group-container" id="service_status_container">
                                <!-- placeholder for service status buttons -->
              </li>
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

    </div>

        <!-- page footer -->
    <footer class="page-foot col-sm-push-3 col-lg-push-2">
      <div class="container-fluid">
        <a target="_blank" href="{{ product_website }}" class="redlnk">{{ product_name }}</a>
        (c) {{ product_copyright_years }}
        <a href="{{ product_copyright_url }}" class="tblnk">{{ product_copyright_owner }}</a>
      </div>
    </footer>

  </main>

    <!-- bootstrap script -->
  <script type="text/javascript" src="/ui/js/bootstrap.min.js"></script>
  <script type="text/javascript" src="/ui/js/bootstrap-select.min.js"></script>
    <!-- bootstrap dialog -->
    <script src="/ui/js/bootstrap-dialog.min.js"></script>
    </body>
</html>
