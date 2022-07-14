<?php

function system_get_language_code() {
    global $config;
    $code = "en-US"; // Set default code.

    // a language code, as per [RFC3066]
    if (isset($config['system']['language'])) {
        $language = $config['system']['language'];
        $code = str_replace("_", "-", $language);
    }

    return $code;
}

// link menu system
$menu = new OPNsense\Base\Menu\MenuSystem();
/* XXX generating breadcrumbs requires getItems() call */
$menuSystem = $menu->getItems($_SERVER['REQUEST_URI']);
$menuBreadcrumbs = $menu->getBreadcrumbs();

if (isset($extraBreadcrumb)) {
    $menuBreadcrumbs[] = array('name' => $extraBreadcrumb);
}

$themename = html_safe(get_current_theme());

$pagetitle = html_safe(gentitle(array_reverse($menuBreadcrumbs), ' | '));
$pagetitle .= html_safe(sprintf(' | %s.%s', $config['system']['hostname'], $config['system']['domain']));

?><!doctype html>
<!--[if IE 8 ]><html lang="<?=system_get_language_code();?>" class="ie ie8 lte9 lte8 no-js"><![endif]-->
<!--[if IE 9 ]><html lang="<?=system_get_language_code();?>" class="ie ie9 lte9 no-js"><![endif]-->
<!--[if (gt IE 9)|!(IE)]><!--><html lang="<?=system_get_language_code();?>" class="no-js"><!--<![endif]-->
  <head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <meta name="robots" content="noindex, nofollow, noodp, noydir" />
    <meta name="keywords" content="" />
    <meta name="description" content="" />
    <meta name="copyright" content="" />
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1" />

    <title><?= $pagetitle ?></title>

    <!-- include (theme) style -->
    <link rel="stylesheet" type="text/css" href="<?= cache_safe("/ui/themes/{$themename}/build/css/main.css") ?>">

    <!-- TODO: move to theme style -->
    <style>
      .menu-level-3-item {
        font-size: 90%;
        padding-left: 54px !important;
      }

      .typeahead {
        overflow: hidden;
      }

      /** jquery-sortable styles **/
      body.dragging, body.dragging * {
        cursor: move !important;
      }

      .dragged {
        position: absolute;
        opacity: 0.5;
        z-index: 2000;
      }

      ol.example li.placeholder {
        position: relative;
      }
      ol.example li.placeholder:before {
        position: absolute;
      }

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

    <!-- Favicon -->
    <link rel="shortcut icon" href="<?= cache_safe("/ui/themes/{$themename}/build/images/favicon.png") ?>">

    <!-- legacy browser functions -->
    <script src="<?= cache_safe("/ui/js/polyfills.js") ?>"></script>

    <!-- Stylesheet for fancy select/dropdown -->
    <link rel="stylesheet" type="text/css" href="<?= cache_safe(get_themed_filename("/css/bootstrap-select-1.13.3.css")) ?>">

    <!-- bootstrap dialog -->
    <link rel="stylesheet" type="text/css" href="<?= cache_safe(get_themed_filename("/css/bootstrap-dialog.css")) ?>">

    <!-- Font awesome -->
    <link rel="stylesheet" type="text/css" href="<?= cache_safe("/ui/css/font-awesome.min.css") ?>">

    <!--[if lt IE 9]><script src="//cdnjs.cloudflare.com/ajax/libs/html5shiv/3.7.2/html5shiv.min.js"></script><![endif]-->

    <!-- nvd3 -->
    <link rel="stylesheet" type="text/css" href="<?= cache_safe(get_themed_filename("/css/nv.d3.css")) ?>">

    <!-- datepicker css -->
    <link rel="stylesheet" type="text/css" href="<?= cache_safe(get_themed_filename("/css/bootstrap-datepicker3.min.css")) ?>">

    <!-- JQuery -->
    <script src="/ui/js/jquery-3.5.1.min.js"></script>

    <!-- datepicker -->
    <script src="<?= cache_safe("/ui/js/bootstrap-datepicker.min.js") ?>"></script>

    <!-- d3 -->
    <script src="<?= cache_safe("/ui/js/d3.min.js") ?>"></script>

    <!-- nvd3 -->
    <script src="<?= cache_safe("/ui/js/nv.d3.min.js") ?>"></script>

    <!-- append some helper functions to integrate into the legacy code -->
    <script src="<?= cache_safe("/javascript/opnsense_legacy.js") ?>"></script>

    <!-- opnsense non legacy helper functions -->
    <script src="<?= cache_safe("/ui/js/opnsense.js") ?>"></script>

    <!-- opnsense non legacy theme support functions -->
    <script src="<?= cache_safe("/ui/js/opnsense_theme.js") ?>"></script>

    <!-- Bootstrap type ahead -->
    <script src="<?= cache_safe("/ui/js/bootstrap3-typeahead.min.js") ?>"></script>

    <script>
    //<![CDATA[
    $( document ).ready(function() {
      $('[data-toggle="tooltip"]').tooltip();
      $("input").not("[autocomplete]").attr("autocomplete","off");

      // hide empty menu items
      $('#mainmenu > div > .collapse').each(function(){
        // cleanup empty second level menu containers
        $(this).find("div.collapse").each(function(){
          if ($(this).children().length == 0 ) {
              $("#mainmenu").find('[href="#'+$(this).attr('id')+'"]').remove();
              $(this).remove();
          }
        });

        // cleanup empty first level menu items
        if ($(this).children().length == 0) {
          $("#mainmenu").find('[href="#'+$(this).attr('id')+'"]').remove();
        }
      });

      // link showhelp class behavior
      $("a[class='showhelp']").click(function (event) {
        $("*[data-for='" + $(this).attr('id') + "']").toggleClass("hidden show");
        event.preventDefault();
      });

      // handle all help messages show/hide
      $('[id*="show_all_help"]').click(function(event) {
        $('[id*="show_all_help"]').toggleClass("fa-toggle-on fa-toggle-off");
        $('[id*="show_all_help"]').toggleClass("text-success text-danger");
        if ($('[id*="show_all_help"]').hasClass("fa-toggle-on")) {
          if (window.sessionStorage) {
            sessionStorage.setItem('all_help_preset', 1);
          }
          $('[data-for*="help_for"]').addClass("show");
          $('[data-for*="help_for"]').removeClass("hidden");
        } else {
          if (window.sessionStorage) {
            sessionStorage.setItem('all_help_preset', 0);
          }
          $('[data-for*="help_for"]').addClass("hidden");
          $('[data-for*="help_for"]').removeClass("show");
        }
        event.preventDefault();
      });
      if (window.sessionStorage && sessionStorage.getItem('all_help_preset') == 1) {
          // show all help messages when preset was stored
          $('[id*="show_all_help"]').toggleClass("fa-toggle-on fa-toggle-off");
          $('[id*="show_all_help"]').toggleClass("text-success text-danger");
          $('[data-for*="help_for"]').addClass("show");
          $('[data-for*="help_for"]').removeClass("hidden");
      }

      // hide submenu items
      $('#mainmenu .list-group-item').click(function(){
        if($(this).attr('href').substring(0,1) == '#') {
            $('#mainmenu .list-group-item').each(function(){
                if ($(this).attr('aria-expanded') == 'true' && $(this).data('parent') != '#mainmenu') {
                  $("#"+$(this).attr('href').substring(1,999)).collapse('hide');
                }
            });
        }
      });

      $(".list-group-item.active").each(function(){
          var navbar_center = ($( window ).height() - $(".collapse.navbar-collapse").height())/2;
          $('html,aside').scrollTop(($(this).offset().top - navbar_center));
      });

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
                  title: 'System Status',
                  message: function(dialog) {
                      let $message = updateStatusDialog(dialog, status);
                      return $message;
                  },
                  buttons: [{
                      label: 'Close',
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
                      menusearch_items.push({id:$('<div/>').html(menu_item.Url).text(), name:menu_item.breadcrumb});
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

      //change search input size on focus() to fit results
      $("#menu_search_box").focus(function(){
          $("#menu_search_box").css('width', '450px');
          $("#system_status").hide();
      });
      $("#menu_search_box").focusout(function(){
          $("#menu_search_box").css('width', '250px');
          $("#system_status").show();
      });
      // convert input form tables for better mobile experience
      hook_stacked_form_tables(".opnsense_standard_table_form");
    });
    //]]>
    </script>
<?php if (file_exists("/usr/local/opnsense/www/themes/{$themename}/build/js/theme.js")): ?>
    <script src="<?= cache_safe("/ui/themes/{$themename}/build/js/theme.js") ?>"></script>
<?php endif ?>
</head>
