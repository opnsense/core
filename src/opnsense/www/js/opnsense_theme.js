/*
 * Copyright (C) 2018 Deciso B.V.
 * Copyright (C) 2018 Franco Fichtner <franco@opnsense.org>
 * Copyright (C) 2018 René Muhr <rene@team-rebellion.net>
 * Copyright (C) 2018 Fabian Franz
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

/* hook-in additional theme functionality */

$(document).ready(function () {
    // traverse loaded css files
    var toggle_sidebar_loaded = false;
    var nomouse = "mouseenter mouseleave";
    var layer1_a = $('#mainmenu > div > a');
    var layer1_div = $('#mainmenu > div > div');
    var layer2_a = $('#mainmenu > div > div > a');
    var layer2_div = $('#mainmenu > div > div > div');

    $.each(document.styleSheets, function(sheetIndex, sheet) {
        if (sheet.href != undefined && sheet.href.match(/main\.css(\?v=\w+$)?/gm)) {
          $.each(sheet.cssRules || sheet.rules, function(ruleIndex, rule) {
              if (rule.cssText.indexOf('toggle-sidebar') >= 0) {
                  toggle_sidebar_loaded = true;
              }
          });
        }
    });

    /* disable mouseevents on toggle and resize */
    function mouse_events_off() {
        layer1_a.off(nomouse);
        layer2_a.off(nomouse);
        layer1_div.off(nomouse);
        layer2_div.off(nomouse);
    }

    /* trigger mouseevents on startup */
    function trigger_sidebar() {
        layer1_a.first().trigger("mouseenter");
        layer1_a.first().trigger("mouseleave");
        layer1_div.removeClass("in");
    }

    /* transition duration - time */
    function transition_duration(time) {
        $.fn.collapse.Constructor.TRANSITION_DURATION = time;
    }

    function opnsense_sidebar_toggle(store) {
        $("#navigation").toggleClass("col-sidebar-left");
        $("main").toggleClass("col-sm-9 col-sm-push-3 col-lg-10 col-lg-push-2 col-lg-12");
        $(".toggle-sidebar > i").toggleClass("fa-chevron-right fa-chevron-left");
        if ($("#navigation").hasClass("col-sidebar-left")) {
            $(".brand-logo").css("display", "none");
            $(".brand-icon").css("display", "inline-block");
            trigger_sidebar();
            if (store && window.sessionStorage) {
                sessionStorage.setItem('toggle_sidebar_preset', 1);
                transition_duration(0);
            }
        } else {
            $(".brand-icon").css("display", "none");
            $(".brand-logo").css("display", "inline-block");
            $("#navigation.page-side.col-xs-12.col-sm-3.col-lg-2.hidden-xs").css("width", "");
            if (store && window.sessionStorage) {
                sessionStorage.setItem('toggle_sidebar_preset', 0);
                mouse_events_off();
                transition_duration(350);
            }
        }
    }

    if (toggle_sidebar_loaded) {
        /* navigation toggle */
        $(".toggle-sidebar").click(function () {
            opnsense_sidebar_toggle(true);
            $(this).blur();
        });

        /* sidebar mouseenter */
        $("#navigation").mouseenter(function () {
            if ($("#navigation").hasClass("col-sidebar-left")) {
                transition_duration(0);
                var events = {
                    mouseenter: function() {
						if ($(this).next("div").hasClass("in")) {
							$("#navigation.col-sidebar-left").css("width", "415px");
						}
						else {
							$("#navigation.col-sidebar-left").css("width", "415px");
							$(this).trigger("click");
						}
                    },
                    mouseleave: function() {
                        $("#navigation.col-sidebar-left").css("width", "70px");
                    },
                    mousedown: function() {
                        $(this).trigger("click");
                    }
                }
                layer1_a.on(events);
                layer2_a.on(events);
                var events2 = {
                    mouseenter: function() {
                        $("#navigation.col-sidebar-left").css("width", "415px");
                        $(this).trigger("click");
                    },
                    mouseleave: function() {
                        $("#navigation.col-sidebar-left").css("width", "70px");
                    }
                }
                layer1_div.on(events2);
                layer2_div.on(events2);
            }
        });

        /* sidebar mouseleave */
        $("#mainmenu").mouseleave(function () {
            if ($("#navigation").hasClass("col-sidebar-left")) {
                layer1_a.attr("aria-expanded","false");
                layer1_a.next("div").removeClass("in");
            }
        });

        /* on resize - toggle sidebar / main navigation */
        $(window).on('resize', function(){
            var win = $(this);
            if ((win.height() < 675 || win.width() < 760) && $("#navigation").not("col-sidebar-hidden")) {
                $("#navigation").addClass("col-sidebar-hidden");
                mouse_events_off();
                if ($("#navigation").hasClass("col-sidebar-left")) {
                    opnsense_sidebar_toggle(false);
                    mouse_events_off();
                    transition_duration(350);
                }
            } else if ((win.height() >= 675 && win.width() >= 760) && $("#navigation").hasClass("col-sidebar-hidden")) {
                $("#navigation").removeClass("col-sidebar-hidden");
                transition_duration(0);
                if (window.sessionStorage && sessionStorage.getItem('toggle_sidebar_preset') == 1) {
                    opnsense_sidebar_toggle(false);
                }
            }
        });

        /* only show toggle button when style is loaded */
        $(".toggle-sidebar").show();

        /* auto-collapse if previously requested */
        if (window.sessionStorage && sessionStorage.getItem('toggle_sidebar_preset') == 1) {
            opnsense_sidebar_toggle(false);
        }
    }
});
