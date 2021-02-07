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
    var toggle_sidebar_loaded = false,
    $window = $(window),
    winHeight = $(window).height(),
    mouse = 'mouseenter mouseleave',
    layer1_a = $('#mainmenu > div > a'),
    layer1_div = $('#mainmenu > div > div'),
    layer2_a = $('#mainmenu > div > div > a'),
    layer2_div = $('#mainmenu > div > div > div'),
    navigation = $('#navigation'),
    mainmenu = $('#mainmenu'),
    countA = $('#mainmenu > div > a').length,
    footH = $('.page-foot').height(),
    headerH = $('.navbar-header').height(),
    navHeight = (countA * 70) + ((footH + headerH) - (20 + countA)),
    events = {
        mouseenter: function () {
            $('#navigation.col-sidebar-left').css('width', '415px');
            var that = $(this);
            if (that.next('div').hasClass('in')) {
                /* no action needed */
            } else {
                var offsetTop = that.offset().top;
                var winscrTop = $window.scrollTop();
                var divHeight = that.next('div').height();
                var divTop = (offsetTop - winscrTop);
                var currentHeight = (divTop + divHeight);
                var thatTrigger = that.trigger('click');
                close_submenu(this);
                if (currentHeight > winHeight) {
                    var result = that.next('div').css('margin-top', -divHeight - (that.is(layer1_a) ? 3 : 0));
                }
            }
        },
        mouseleave: function () {
            $('#navigation.col-sidebar-left').css('width', '70px');
            layer1_a.off(events).on(events);
        },
        mousedown: function () {
            $(this).trigger('click');
        },
        mouseup: function () {
            $(this).blur();
        }
    },
    events2 = {
        mouseenter: function () {
            $('#navigation.col-sidebar-left').css('width', '415px');
            $(this).trigger('click');
        },
        mouseleave: function () {
            $('#navigation.col-sidebar-left').css('width', '70px');
        }
    };

    $.each(document.styleSheets, function (sheetIndex, sheet) {
        if (sheet.href !== null && sheet.href.match(/main\.css(\?v=\w+$)?/gm)) {
            $.each(sheet.cssRules || sheet.rules, function (ruleIndex, rule) {
                if (rule.cssText.indexOf('toggle-sidebar') >= 0) {
                    toggle_sidebar_loaded = true;
                }
            });
        }
    });

    /* disable mouseevents on toggle and resize */
    function mouse_events_off() {
        layer1_a.off(mouse);
        layer2_a.off(mouse);
        layer1_div.off(mouse);
        layer2_div.off(mouse);
    }

    /* trigger mouseevents and remove opened submenus on startup */
    function trigger_sidebar() {
        layer1_a.first().trigger('mouseenter').trigger('mouseleave');
        layer1_div.removeClass('in');
        layer2_div.removeClass('in');
    }

    /* menu delay - transition duration - time */
    function transition_duration(time) {
        $.fn.collapse.Constructor.TRANSITION_DURATION = time;
    }

    /* close all non-focused submenus */
    function close_submenu(r) {
        $(r).nextAll('a').addClass('collapsed').attr('aria-expanded', 'false');
        $(r).prevAll('a').addClass('collapsed').attr('aria-expanded', 'false');
        $(r).nextAll('div').removeClass('in').attr('aria-expanded', 'false');
        $(r).prevAll('div').removeClass('in').attr('aria-expanded', 'false');
    }

    function opnsense_sidebar_toggle(store) {
        navigation.toggleClass('col-sidebar-left');
        $('main').toggleClass('col-sm-9 col-sm-push-3 col-lg-10 col-lg-push-2 col-lg-12');
        $('.toggle-sidebar > i').toggleClass('fa-chevron-right fa-chevron-left');
        if (navigation.hasClass('col-sidebar-left')) {
            $('.brand-logo').css('display', 'none');
            $('.brand-icon').css('display', 'inline-block');
            trigger_sidebar();
            if (store && window.localStorage) {
                localStorage.setItem('toggle_sidebar_preset', 1);
                transition_duration(0);
            }
        } else {
            $('.brand-icon').css('display', 'none');
            $('.brand-logo').css('display', 'inline-block');
            $('#navigation.page-side.col-xs-12.col-sm-3.col-lg-2.hidden-xs').css('width', '');
            if (store && window.localStorage) {
                localStorage.setItem('toggle_sidebar_preset', 0);
                mouse_events_off();
                transition_duration(350);
            }
        }
    }

    if (toggle_sidebar_loaded) {
        var toggle_btn = $('.toggle-sidebar');
        /* navigation toggle */
        toggle_btn.click(function () {
            opnsense_sidebar_toggle(true);
            $(this).blur();
        });

        /* main function - sidebar mouseenter */
        mainmenu.mouseenter(function () {
            if (navigation.hasClass('col-sidebar-left')) {
                transition_duration(0);
                layer1_a.on(events);
                layer2_a.on(events);
                layer1_div.on(events2);
                layer2_div.on(events2);
            }
        });

        /* main function - sidebar mouseleave */
        mainmenu.mouseleave(function () {
            if (navigation.hasClass('col-sidebar-left')) {
                layer1_a.attr('aria-expanded', 'false').next('div').removeClass('in');
                layer2_a.attr('aria-expanded', 'false').next('div').removeClass('in');
                layer1_div.removeAttr('style');
                layer2_div.removeAttr('style');
                layer2_a.off(events);
                layer1_div.off(events2);
                layer2_div.off(events2);
            }
        });

        /* on resize - toggle sidebar/main navigation */
        $(window).on('resize', function () {
            var win = $(this);
            winHeight = win.height();
            if ((win.height() < navHeight || win.width() < 760) && navigation.not('col-sidebar-hidden')) {
                navigation.addClass('col-sidebar-hidden');
                mouse_events_off();
                toggle_btn.hide();
                if (navigation.hasClass('col-sidebar-left')) {
                    opnsense_sidebar_toggle(false);
                    mouse_events_off();
                    transition_duration(350);
                }
            } else if ((win.height() >= navHeight && win.width() >= 760) && navigation.hasClass('col-sidebar-hidden')) {
                navigation.removeClass('col-sidebar-hidden');
                transition_duration(0);
                toggle_btn.show();
                if (window.localStorage && localStorage.getItem('toggle_sidebar_preset') == 1) {
                    opnsense_sidebar_toggle(false);
                }
            }
        });

        /* only show toggle button when style is loaded */
        toggle_btn.show();

        /* auto-collapse if previously requested */
        if (window.localStorage && localStorage.getItem('toggle_sidebar_preset') == 1) {
            opnsense_sidebar_toggle(false);
        }
    }
});
