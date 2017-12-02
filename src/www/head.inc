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

$pagetitle = gentitle(array_reverse($menuBreadcrumbs), ' | ');
$pagetitle .= html_safe(sprintf(' | %s.%s', $config['system']['hostname'], $config['system']['domain']));

?><!doctype html>
<!--[if IE 8 ]><html lang="<?=system_get_language_code();?>" class="ie ie8 lte9 lte8 no-js"><![endif]-->
<!--[if IE 9 ]><html lang="<?=system_get_language_code();?>" class="ie ie9 lte9 no-js"><![endif]-->
<!--[if (gt IE 9)|!(IE)]><!--><html lang="<?=system_get_language_code();?>" class="no-js"><!--<![endif]-->
  <head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

    <meta name="robots" content="noindex, nofollow, noodp, noydir" />
    <meta name="keywords" content="" />
    <meta name="description" content="" />
    <meta name="copyright" content="" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />

    <title><?= $pagetitle ?></title>

    <!-- include (theme) style -->
    <link href="/ui/themes/<?= $themename ?>/build/css/main.css" rel="stylesheet">

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
    </style>

    <!-- Favicon -->
    <link href="/ui/themes/<?= $themename ?>/build/images/favicon.png" rel="shortcut icon">

    <!-- Stylesheet for fancy select/dropdown -->
    <link rel="stylesheet" type="text/css" href="/ui/themes/<?= $themename ?>/build/css/bootstrap-select.css">

    <!-- bootstrap dialog -->
    <link href="/ui/themes/<?= $themename ?>/build/css/bootstrap-dialog.css" rel="stylesheet" type="text/css" />

    <!-- Font awesome -->
    <link rel="stylesheet" href="/ui/css/font-awesome.min.css">

    <!--[if lt IE 9]><script src="//cdnjs.cloudflare.com/ajax/libs/html5shiv/3.7.2/html5shiv.min.js"></script><![endif]-->

    <!-- nvd3 -->
    <link rel="stylesheet" href="/ui/css/nv.d3.css">

    <!-- datepicker css -->
    <link  href="/ui/css/bootstrap-datepicker3.min.css" rel="stylesheet">

    <!-- JQuery -->
    <script type="text/javascript" src="/ui/js/jquery-3.2.1.min.js"></script>
    <script type="text/javascript" src="/ui/js/jquery-migrate-3.0.1.min.js"></script>

    <!-- datepicker -->
    <script src="/ui/js/bootstrap-datepicker.min.js"></script>

    <!-- d3 -->
    <script type="text/javascript" src="/ui/js/d3.min.js"></script>

    <!-- nvd3 -->
    <script type="text/javascript" src="/ui/js/nv.d3.min.js"></script>

    <!-- append some helper functions to integrate into the legacy code -->
    <script type="text/javascript" src="/javascript/opnsense_legacy.js"></script>

    <!-- opnsense non legacy helper functions -->
    <script type="text/javascript" src="/ui/js/opnsense.js"></script>

    <!-- Bootstrap type ahead -->
    <script type="text/javascript" src="/ui/js/bootstrap3-typeahead.min.js"></script>

    <script type="text/javascript">
    //<![CDATA[
    $( document ).ready(function() {
      $('[data-toggle="tooltip"]').tooltip();
      $("input").attr("autocomplete","off");
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
        $("*[for='" + $(this).attr('id') + "']").toggleClass("hidden show");
        event.preventDefault();
      });

      // handle all help messages show/hide
      $('[id*="show_all_help"]').click(function(event) {
        $('[id*="show_all_help"]').toggleClass("fa-toggle-on fa-toggle-off");
        $('[id*="show_all_help"]').toggleClass("text-success text-danger");
        if ($('[id*="show_all_help"]').hasClass("fa-toggle-on")) {
          if (checkSessionStorageExists()) {
            sessionStorage.setItem('all_help_preset', 1);
          }
          $('[for*="help_for"]').addClass("show");
          $('[for*="help_for"]').removeClass("hidden");
        } else {
          if (checkSessionStorageExists()) {
            sessionStorage.setItem('all_help_preset', 0);
          }
          $('[for*="help_for"]').addClass("hidden");
          $('[for*="help_for"]').removeClass("show");
        }
        event.preventDefault();
      });
      if (checkSessionStorageExists() && sessionStorage.getItem('all_help_preset') == 1) {
          // show all help messages when preset was stored
          $('[id*="show_all_help"]').toggleClass("fa-toggle-on fa-toggle-off");
          $('[id*="show_all_help"]').toggleClass("text-success text-danger");
          $('[for*="help_for"]').addClass("show");
          $('[for*="help_for"]').removeClass("hidden");
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
      // Side menu navigation for long item lists, when top level items tend to scroll above visible the area
      // they will clip on top of the menu system. Scroll out will move them back into their normal positions.
      $("#navigation").scroll(function(){
          var collapsed_menu_item = $("#mainmenu .collapse.in").first();
          if (collapsed_menu_item.position().top < 5) {
              var top_pos = parseInt($("header").height());
              $("#mainmenu .collapse.in").each(function(){
                  var menuref = $('a[href="#'+$(this).attr('id')+'"]');
                  if (!menuref.hasClass('mainmenu_clipped_on_top')) {
                      menuref.addClass('mainmenu_clipped_on_top');
                      menuref.data('__original_style__', {
                        'z-index': menuref.css('z-index'),
                        'width': menuref.css('width'),
                        'position': menuref.css('position'),
                        'background': menuref.css('background'),
                        'top': menuref.css('top')
                      });
                  }
                  menuref.css('z-index', 10);
                  menuref.css('top', top_pos);
                  menuref.css('width', collapsed_menu_item.outerWidth() + 'px');
                  menuref.css('position', 'fixed');
                  menuref.css('background', '#fff');
                  top_pos += menuref.outerHeight();
              });
          } else {
              // scroll-back, move menu items back in normal location
              $(".mainmenu_clipped_on_top").each(function(){
                  var menuref = $(this);
                  $.each(menuref.data('__original_style__'), function(key, value){
                      menuref.css(key, value);
                  });
                  $(this).removeClass('mainmenu_clipped_on_top');
              });
          }
      });
      // Side menu navigation, always force selected item in center when the menu contains more items then fits the view area
      $(".list-group-item.active").each(function(){
          var navbar_center = ($( window ).height() - $(".collapse.navbar-collapse").height())/2;
          var x = $(this).offset().top - navbar_center;
          $('html,aside').scrollTop(x);
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
      // convert input form tables for better mobile experience
      hook_stacked_form_tables(".opnsense_standard_table_form");
    });
    //]]>
    </script>
<?php
    if (file_exists("/usr/local/opnsense/www/themes/".$themename."/build/js/theme.js")):?>
    <script type="text/javascript" src="/ui/themes/<?=$themename?>/build/js/theme.js"></script>
<?php
    endif;?>
</head>
