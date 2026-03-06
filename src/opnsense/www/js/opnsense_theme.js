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

$(document).ready(function () {
    // --- Cached selectors ---
    const navigation  = $('#navigation');
    const mainmenu    = $('#mainmenu');
    const layer_a     = $('#mainmenu > div > a');
    const layer_div   = $('#mainmenu > div > div');
    const layer2_a    = $('#mainmenu > div > div > a');
    const layer2_div  = $('#mainmenu > div > div > div');
    const toggle_btn  = $('.toggle-sidebar');

    const allLayers   = [layer_a, layer2_a, layer_div, layer2_div];
    const aLayers     = [layer_a, layer2_a];
    const divLayers   = [layer_div, layer2_div];

    // --- Layout measurements ---
    const itemWidth = document.querySelector('#navigation.col-sidebar-left > div > nav > #mainmenu > div > a.list-group-item')?.offsetWidth ?? 70;
    const navHeight =
        (layer_a.length * itemWidth) +
        ($('.navbar').height() + $('.page-foot').height() - $('a.list-group-item').height());

    // --- Helpers ---
    const isSidebarLeft  = () => navigation.hasClass('col-sidebar-left');
    const isSidebarHidden = () => navigation.hasClass('col-sidebar-hidden');
    const storage        = window.localStorage;

    function setTransitionDuration(ms) {
        $.fn.collapse.Constructor.TRANSITION_DURATION = ms;
    }

    function offMouseEvents() {
        allLayers.forEach(l => l.off('mouseenter mouseleave'));
    }

    function triggerSidebar() {
        layer_a.first().trigger('mouseenter').trigger('mouseleave');
        divLayers.forEach(l => l.removeClass('in'));
    }

    function closeSubmenu(el) {
        ['nextAll', 'prevAll'].forEach(dir => {
            $(el)[dir]('a').addClass('collapsed').attr('aria-expanded', 'false');
            $(el)[dir]('div').removeClass('in').attr('aria-expanded', 'false');
        });
    }

    // --- Event maps ---
    const events = {
        mouseenter() {
            const $nav    = $('#navigation.col-sidebar-left');
            const $this   = $(this);
            const $next   = $this.next('div');

            $nav.css('width', '415px');

            if (!$next.hasClass('in')) {
                const winHeight    = $(window).height();
                const divHeight    = $next.height();
                const divTop       = $this.offset().top - $(window).scrollTop();
                const currentBottom = divTop + divHeight;

                $this.trigger('click');
                closeSubmenu(this);

                if (currentBottom > winHeight - $('a.list-group-item').height()) {
                    const divPos = divHeight > divTop
                        ? -((divHeight - divTop) - $('a.list-group-item').height())
                        : 3;
                    $next.css('margin-top', -divHeight - divPos);
                }
            }
        },
        mouseleave() {
            $('#navigation.col-sidebar-left').css('width', '70px');
            layer_a.off(events).on(events);
        },
        mousedown() { $(this).trigger('click'); },
        mouseup()   { $(this).blur(); }
    };

    const events2 = {
        mouseenter() {
            $('#navigation.col-sidebar-left').css('width', '415px');
            $(this).trigger('click');
        },
        mouseleave() {
            $('#navigation.col-sidebar-left').css('width', '70px');
        }
    };

    // --- Check if toggle-sidebar CSS is loaded ---
    const toggle_sidebar_loaded = Array.from(document.styleSheets).some(sheet => {
        if (!sheet.href?.match(/main\.css(\?v=\w+$)?/)) return false;
        return Array.from(sheet.cssRules || sheet.rules || [])
            .some(rule => rule.cssText.includes('toggle-sidebar'));
    });

    // --- Sidebar toggle ---
    function opnsense_sidebar_toggle(store) {
        navigation.toggleClass('col-sidebar-left');
        $('main').toggleClass('col-sm-9 col-sm-push-3 col-lg-10 col-lg-push-2 col-lg-12');
        $('.toggle-sidebar > i').toggleClass('fa-chevron-right fa-chevron-left');

        if (isSidebarLeft()) {
            $('.brand-logo').css('display', 'none');
            $('.brand-icon').css('display', 'inline-block');
            triggerSidebar();
            if (store && storage) {
                storage.setItem('toggle_sidebar_preset', 1);
                setTransitionDuration(0);
            }
        } else {
            $('.brand-icon').css('display', 'none');
            $('.brand-logo').css('display', 'inline-block');
            $('#navigation.page-side.col-xs-12.col-sm-3.col-lg-2.hidden-xs').css('width', '');
            if (store && storage) {
                storage.setItem('toggle_sidebar_preset', 0);
                offMouseEvents();
                setTransitionDuration(350);
            }
        }
    }

    if (!toggle_sidebar_loaded) return;

    // --- Toggle button click ---
    toggle_btn.click(function () {
        opnsense_sidebar_toggle(true);
        $(this).blur();
    });

    // --- Sidebar mouseenter ---
    mainmenu.mouseenter(function () {
        if (!isSidebarLeft()) return;
        setTransitionDuration(0);
        aLayers.forEach(l => l.on(events));
        divLayers.forEach(l => l.on(events2));
    });

    // --- Sidebar mouseleave ---
    mainmenu.mouseleave(function () {
        if (!isSidebarLeft()) return;
        aLayers.forEach(l => l.attr('aria-expanded', 'false').next('div').removeClass('in'));
        divLayers.forEach(l => l.removeAttr('style'));
        layer2_a.off(events);
        layer_div.off(events2);
        layer2_div.off(events2);
    });

    // --- Window resize ---
    $(window).on('resize', function () {
        const winHeight = $(window).height();
        const winWidth  = $(window).width();
        const tooSmall  = winHeight < navHeight || winWidth < 760;

        if (tooSmall && !isSidebarHidden()) {
            navigation.addClass('col-sidebar-hidden');
            offMouseEvents();
            toggle_btn.hide();
            if (isSidebarLeft()) {
                opnsense_sidebar_toggle(false);
                offMouseEvents();
                setTransitionDuration(350);
            }
        } else if (!tooSmall && isSidebarHidden()) {
            navigation.removeClass('col-sidebar-hidden');
            setTransitionDuration(0);
            toggle_btn.show();
            if (storage && storage.getItem('toggle_sidebar_preset') == 1) {
                opnsense_sidebar_toggle(false);
            }
        }
    });

    // --- Init: check viewport on page load before showing sidebar ---
    const initHeight = $(window).height();
    const initWidth  = $(window).width();
    const tooSmallOnLoad = initHeight < navHeight || initWidth < 1000;

    if (tooSmallOnLoad) {
        navigation.addClass('col-sidebar-hidden');
        offMouseEvents();
        toggle_btn.hide();
        if (isSidebarLeft()) {
            opnsense_sidebar_toggle(false);
            offMouseEvents();
            setTransitionDuration(350);
        }
    } else {
        toggle_btn.show();
        if (storage && storage.getItem('toggle_sidebar_preset') == 1) {
            opnsense_sidebar_toggle(false);
        }
    }
});
