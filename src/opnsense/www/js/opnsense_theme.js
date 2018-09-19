/**
 *    Copyright (C) 2018 Deciso B.V.
 *    Copyright (C) 2018 Team Rebellion
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *    hook-in additional theme functionality
 */

$(document).ready(function () {
    // traverse loaded css files
    var toggle_sidebar_loaded = false;
    $.each(document.styleSheets, function(sheetIndex, sheet) {
        if (sheet.href != undefined && sheet.href.endsWith('main.css')){
          $.each(sheet.cssRules || sheet.rules, function(ruleIndex, rule) {
              if (rule.cssText.indexOf('toggle-sidebar') >= 0) {
                  toggle_sidebar_loaded = true;
              }
          });
        }
    });

    if (toggle_sidebar_loaded) {
        /** navigation toggle **/
        $(".toggle-sidebar").click(function () {
            $("#navigation").toggleClass("col-sidebar-left");
            $("main").toggleClass("col-sm-9 col-sm-push-3 col-lg-10 col-lg-push-2 col-lg-12");
            $(".toggle-sidebar > i").toggleClass("fa-chevron-right fa-chevron-left");
            if ($("#navigation").hasClass("col-sidebar-left")) {
                $(".brand-logo").css("display", "none");
                $(".brand-icon").css("display", "inline-block");
            } else {
                $(".brand-icon").css("display", "none");
                $(".brand-logo").css("display", "inline-block");
                $("#navigation.page-side.col-xs-12.col-sm-3.col-lg-2.hidden-xs").css("width", "");
            }
            $(this).blur();
        });
        /** sidebar mouseevents **/
        $("#navigation").hover(function () {
            if ($("#navigation").hasClass("col-sidebar-left")) {
                $("#navigation > div > nav > #mainmenu > div > div").on({
                    mouseout: function() {$("#navigation.col-sidebar-left").css("width", "70px"); },
                    mouseover: function() {$("#navigation.col-sidebar-left").css("width", "380px"); }
                });
                $("#navigation > div > nav > #mainmenu > div > a").on({
                    mouseout: function() {$("#navigation.col-sidebar-left").css("width", "70px"); },
                    mouseover: function() {$("#navigation.col-sidebar-left").css("width", "380px"); }
                });
            }
        });

        /** on resize - toggle sidebar / main navigation **/
        $(window).on('resize', function(){
            var win = $(this);
            if (((win.height() <= 669) && $("#navigation").hasClass("col-sidebar-left"))) {
                $("main").toggleClass("col-sm-9 col-sm-push-3 col-lg-10 col-lg-push-2 col-lg-12");
                $("#navigation").toggleClass("col-sidebar-left");
                $(".toggle-sidebar > i").toggleClass("fa-chevron-right fa-chevron-left");
                $(".brand-icon").css("display", "none");
                $(".brand-logo").css("display", "inline-block");
                $("#navigation.page-side.col-xs-12.col-sm-3.col-lg-2.hidden-xs").css("width", "");
            } else if ((win.width() <= 768) && $("#navigation").hasClass("col-sidebar-left")) {
                $("main").toggleClass("col-sm-9 col-sm-push-3 col-lg-10 col-lg-push-2 col-lg-12");
                $("#navigation").toggleClass("col-sidebar-left");
                $(".toggle-sidebar > i").toggleClass("fa-chevron-right fa-chevron-left");
                $(".brand-logo").css("display", "none");
                $(".brand-icon").css("display", "inline-block");
                $("#navigation.page-side.col-xs-12.col-sm-3.col-lg-2.hidden-xs").css("width", "");
            } else if (((win.width() <= 768) && $("#navigation").not("col-sidebar-left"))
                        || ((win.width() >= 768) && $("#navigation").hasClass("col-sidebar-left"))) {
                $(".brand-logo").css("display", "none");
                $(".brand-icon").css("display", "inline-block"); }
            else if ((win.width() >= 768) && $("#navigation").not("col-sidebar-left")){
                $("#navigation").addClass("in");
                $(".brand-icon").css("display", "none");
                $(".brand-logo").css("display", "inline-block");
            }
        });
        // only show toggle button when style is loaded
        $(".toggle-sidebar").show();
    }
});
