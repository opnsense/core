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

                function updateStatusDialog(dialog, status, subjectRef = null) {
                    let $message = $(
                        '<div class="row">' +
                            '<div class="col-md-6">' +
                                '<div class="list-group" id="list-tab" role="tablist" style="margin-bottom: 0">' +
                                '</div>' +
                            '</div>' +
                            '<div class="col-md-6">' +
                                '<div class="tab-content" id="nav-tabContent">' +
                                '</div>' +
                            '</div>'+
                        '</div>'
                    );
                    for (let subject in status.data) {
                        if (subject === 'System') {
                            continue;
                        }
                        let statusObject = status.data[subject];
                        let dismissNeeded = true;

                        if (status.data[subject].status == "OK") {
                            dismissNeeded = false;
                        }
                        let formattedSubject = subject.replace(/([A-Z])/g, ' $1').trim();
                        let $listItem = $(
                            '<a class="list-group-item list-group-item-border" data-toggle="list" href="#list-' + subject + '" role="tab" style="outline: 0">' +
                                 formattedSubject +
                                 '<span class="' + statusObject.icon + '" style="float: right"></span>' +
                            '</a>'
                        );
                        let referral = statusObject.logLocation ? 'Click <a href="' + statusObject.logLocation + '">here</a> for more information.' : ''
                        let $pane = $(
                            '<div class="tab-pane fade" id="list-' + subject + '" role="tabpanel"><p>' + statusObject.message + ' ' + referral + '</p>' +
                            '</div>'
                        );

                        $message.find('#list-tab').append($listItem);
                        $message.find('#nav-tabContent').append($pane);

                        if (subjectRef) {
                            $message.find('#list-tab a[href="#list-' + subjectRef + '"]').addClass('active').tab('show').siblings().removeClass('active');
                            $pane.addClass('active in').siblings().removeClass('active in');
                        } else {
                            $message.find('#list-tab a:first-child').addClass('active').tab('show');
                            $message.find('#nav-tabContent div:first-child').addClass('active in');
                        }

                        $message.find('#list-tab a[href="#list-' + subject + '"]').on('click', function(e) {
                            e.preventDefault();
                            $(this).tab('show');
                            $(this).toggleClass('active').siblings().removeClass('active');
                        });

                        if (dismissNeeded) {
                            let $button = $('<div><button id="dismiss-'+ subject + '" type="button" class="btn btn-link btn-sm" style="padding: 0px;">Dismiss</button></div>');
                            $pane.append($button);
                        }

                        $message.find('#dismiss-' + subject).on('click', function(e) {
                            $.ajax('/api/core/system/dismissStatus', {
                                type: 'post',
                                data: {'subject': subject},
                                dialogRef: dialog,
                                subjectRef: subject,
                                success: function() {
                                    updateStatus().then((data) => {
                                        let newStatus = parseStatus(data);
                                        let $newMessage = updateStatusDialog(this.dialogRef, newStatus, this.subjectRef);
                                        this.dialogRef.setType(newStatus.severity);
                                        this.dialogRef.setMessage($newMessage);
                                        $('#system_status').attr("class", newStatus.data['System'].icon);
                                    });
                                }
                            });
                        });
                    }
                    return $message;
                }

                function parseStatus(data) {
                    let status = {};
                    let severity = BootstrapDialog.TYPE_SUCCESS;
                    $.each(data, function(subject, statusObject) {
                        if (subject == 'System') {
                            switch (statusObject.status) {
                                case "Error":
                                    $('#system_status').toggleClass("fa fa-exclamation-triangle text-danger");
                                    severity = BootstrapDialog.TYPE_DANGER;
                                    statusObject.icon = 'fa fa-exclamation-triangle text-danger'
                                    break;
                                case "Warning":
                                    $('#system_status').toggleClass("fa fa-exclamation-triangle text-warning");
                                    severity = BootstrapDialog.TYPE_WARNING;
                                    statusObject.icon = 'fa fa-exclamation-triangle text-warning';
                                    break;
                                case "Notice":
                                    $('#system_status').toggleClass("fa fa-check-circle text-info");
                                    severity = BootstrapDialog.TYPE_INFO;
                                    statusObject.icon = 'fa fa-check-circle text-info';
                                    break;
                                default:
                                    $('#system_status').toggleClass('fa fa-check-circle text-success');
                                    statusObject.icon = 'fa fa-check-circle text-success';
                                    break;
                            }
                        } else {
                            switch (statusObject.status) {
                                case "Error":
                                    statusObject.icon = 'fa fa-exclamation-triangle text-danger'
                                    break;
                                case "Warning":
                                    statusObject.icon = 'fa fa-exclamation-triangle text-warning';
                                    break;
                                case "Notice":
                                    statusObject.icon = 'fa fa-check-circle text-info';
                                    break;
                                default:
                                    statusObject.icon = 'fa fa-check-circle text-success';
                                    break;
                            }
                        }
                    });
                    status.severity = severity;
                    status.data = data;

                    return status;
                }

                function updateStatus() {
                    return $.ajax("/api/core/system/status", {
                        type: 'get',
                        dataType: "json",
                        error : function (jqXHR, textStatus, errorThrown) {
                            console.log('system.status : ' +errorThrown);
                        }
                    });
                }

                updateStatus().then((data) => {
                    let status = parseStatus(data);

                    $("#system_status").click(function() {
                        BootstrapDialog.show({
                            type: status.severity,
                            title: '{{ lang._('System Status')}}',
                            message: function(dialog) {
                                let $message = updateStatusDialog(dialog, status);
                                return $message;
                            },
                            buttons: [{
                                label: '{{ lang._('Close') }}',
                                cssClass: 'btn-primary',
                                action: function(dialogRef) {
                                    dialogRef.close();
                                }
                            }],
                        });
                    });
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
            <li id="menu_user">
              <span class="navbar-text">{{session_username}}@{{system_hostname}}.{{system_domain}}</span>
            </li>
            <li>
              <span class="navbar-text" style="margin-left: 0">
                <i id="system_status" data-toggle="tooltip left" title="Show system status" style="cursor:pointer"></i>
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
    $.extend(jQuery.fn.bootgrid.prototype.constructor.Constructor.defaults.labels, {
        all: "{{ lang._('All') }}",
        infos: "{{ lang._('Showing %s to %s of %s entries') | format('{{ctx.start}}','{{ctx.end}}','{{ctx.total}}') }}",
        loading: "{{ lang._('Loading...') }}",
        noResults: "{{ lang._('No results found!') }}",
        refresh: "{{ lang._('Refresh') }}",
        search: "{{ lang._('Search') }}"
    });
    $.extend(jQuery.fn.selectpicker.Constructor.DEFAULTS, {
        noneSelectedText: "{{ lang._('Nothing selected') }}",
        noneResultsText: "{{ lang._('No results matched {0}') }}",
        selectAllText: "{{ lang._('Select All') }}",
        deselectAllText: "{{ lang._('Deselect All') }}"
    });
    $.extend(jQuery.fn.UIBootgrid.defaults, {
        removeWarningText: "{{ lang._('Remove selected item(s)?') }}",
        editText: "{{ lang._('Edit') }}",
        cloneText: "{{ lang._('Clone') }}",
        deleteText: "{{ lang._('Delete') }}",
        addText: "{{ lang._('Add') }}",
        infoText: "{{ lang._('Info') }}",
        enableText: "{{ lang._('Enable') }}",
        disableText: "{{ lang._('Disable') }}",
        deleteSelectedText: "{{ lang._('Delete selected') }}"
    });
    $.extend(stdDialogRemoveItem.defaults, {
        title: "{{ lang._('Remove') }}",
        accept: "{{ lang._('Yes') }}",
        decline: "{{ lang._('Cancel') }}"
    });
    </script>

  </body>
</html>
