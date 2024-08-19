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
    mouse = 'mouseenter mouseleave',
    layer_a = $('#mainmenu > div > a'),
    layer_div = $('#mainmenu > div > div'),
    layer2_a = $('#mainmenu > div > div > a'),
    layer2_div = $('#mainmenu > div > div > div'),
    navigation = $('#navigation'),
    mainmenu = $('#mainmenu'),
    countA = layer_a.length,
    footH = $('.page-foot').height(),
    headerH = $('.navbar').height(),
    li_itemH = $('a.list-group-item').height(),
    navHeight = (countA * 70) + (headerH + footH - li_itemH),
    events = {
        mouseenter: function () {
            var navigation = $('#navigation.col-sidebar-left');
            var that = $(this);
            var nextDiv = that.next('div');

            navigation.css('width', '415px');
            if (!nextDiv.hasClass('in')) {
                /* calculate coordinates for submenu */
                var winHeight = $(window).height(),
                    offsetTop = that.offset().top,
                    winscrTop = $(window).scrollTop(),
                    divHeight = nextDiv.height(),
                    divTop = offsetTop - winscrTop,
                    currentHeight = divTop + divHeight;

                that.trigger('click');
                close_submenu(this);

                /* check if submenu has enough space expanding down  - if not expand submenu up */
                if (currentHeight > (winHeight - li_itemH)) {
                    var divPos = (divHeight > divTop) ? -((divHeight - divTop) - li_itemH) : 3;
                    nextDiv.css('margin-top', -divHeight - divPos);
                }
            }
        },

        mouseleave: function () {
            $('#navigation.col-sidebar-left').css('width', '70px');
            layer_a.off(events).on(events);
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
        const layers = [layer_a, layer2_a, layer_div, layer2_div];
        layers.forEach(layer => layer.off(mouse));
    }

    /* trigger mouseevents and remove opened submenus on startup */
    function trigger_sidebar() {
        layer_a.first().trigger('mouseenter').trigger('mouseleave');
        const layers = [layer_div, layer2_div];
        layers.forEach(layer => layer.removeClass('in'));
    }

    /* menu delay - transition duration - time */
    function transition_duration(time) {
        $.fn.collapse.Constructor.TRANSITION_DURATION = time;
    }

    /* close all non-focused submenus */
    function close_submenu(r) {
        ['nextAll', 'prevAll'].forEach(direction => {
            $(r)[direction]('a').addClass('collapsed').attr('aria-expanded', 'false');
            $(r)[direction]('div').removeClass('in').attr('aria-expanded', 'false');
        });
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
                const layersWithEvents = [layer_a, layer2_a];
                const layersWithEvents2 = [layer_div, layer2_div];

                layersWithEvents.forEach(layer => layer.on(events));
                layersWithEvents2.forEach(layer => layer.on(events2));
            }
        });

        /* main function - sidebar mouseleave */
        mainmenu.mouseleave(function () {
            if (navigation.hasClass('col-sidebar-left')) {
                const layersWithAria = [layer_a, layer2_a];
                const layersToRemoveStyle = [layer_div, layer2_div];
                const layersToOffEvents = [{ layer: layer2_a, events: events }, { layer: layer_div, events: events2 }, { layer: layer2_div, events: events2 }];

                layersWithAria.forEach(layer => layer.attr('aria-expanded', 'false').next('div').removeClass('in'));
                layersToRemoveStyle.forEach(layer => layer.removeAttr('style'));
                layersToOffEvents.forEach(({ layer, events }) => layer.off(events));
           }
        });

        /* on resize - toggle sidebar/main navigation */
        $(window).on('resize', function () {
            var win = $(window),
                winHeight = win.height(),
                winWidth = win.width();

            if ((winHeight < navHeight || winWidth < 760) && navigation.not('col-sidebar-hidden')) {
                navigation.addClass('col-sidebar-hidden');
                mouse_events_off();
                toggle_btn.hide();

                if (navigation.hasClass('col-sidebar-left')) {
                    opnsense_sidebar_toggle(false);
                    mouse_events_off();
                    transition_duration(350);
                }
            } else if ((winHeight >= navHeight && winWidth >= 760) && navigation.hasClass('col-sidebar-hidden')) {
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
