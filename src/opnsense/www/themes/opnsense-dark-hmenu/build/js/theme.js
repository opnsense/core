/*
 * Horizontal menu theme behavior for OPNsense.
 * Replaces the sidebar toggle/collapse logic with horizontal dropdown support.
 */

$(document).ready(function () {
    var hmenu = $('#horizontal-menu');
    if (!hmenu.length) return;

    /* hide the sidebar toggle button (not applicable for horizontal menu) */
    $('.toggle-sidebar').hide();

    /* remove empty dropdown/flyout containers */
    $('#mainmenu .hmenu-dropdown, #mainmenu .hmenu-flyout').each(function () {
        if ($(this).children().length === 0) {
            $(this).parent().remove();
        }
    });

    var hmenuNav = hmenu.find('.hmenu-nav');
    var hmenuToggle = hmenu.find('.hmenu-toggle');

    /* Mobile hamburger toggle */
    hmenuToggle.on('click', function () {
        hmenuNav.toggleClass('hmenu-open');
    });

    /* Touch support: first tap opens dropdown, second tap follows link */
    if ('ontouchstart' in window) {
        hmenu.on('click', '.hmenu-has-children > a', function (e) {
            var $parent = $(this).parent();
            var $sub = $parent.children('.hmenu-dropdown, .hmenu-flyout');

            if ($sub.length && !$sub.is(':visible')) {
                e.preventDefault();
                e.stopPropagation();
                /* close siblings */
                $parent.siblings('.hmenu-has-children').find('.hmenu-dropdown, .hmenu-flyout').hide();
                $sub.show();
            }
        });

        /* close menus on outside tap */
        $(document).on('touchstart', function (e) {
            if (!$(e.target).closest('#horizontal-menu').length) {
                hmenu.find('.hmenu-dropdown, .hmenu-flyout').hide();
            }
        });
    }

    /* Edge detection: reposition dropdowns that overflow viewport */
    hmenu.on('mouseenter', '.hmenu-item', function () {
        var $dropdown = $(this).children('.hmenu-dropdown, .hmenu-flyout');
        if (!$dropdown.length) return;

        var rect = $dropdown[0].getBoundingClientRect();
        var viewportWidth = $(window).width();

        /* horizontal overflow */
        if (rect.right > viewportWidth) {
            if ($dropdown.hasClass('hmenu-flyout')) {
                $dropdown.css({ left: 'auto', right: '100%' });
            } else {
                $dropdown.css({ left: 'auto', right: '0' });
            }
        }

        /* vertical overflow */
        var viewportHeight = $(window).height();
        if (rect.bottom > viewportHeight) {
            var overflow = rect.bottom - viewportHeight + 8;
            $dropdown.css('margin-top', -overflow + 'px');
        }
    });

    hmenu.on('mouseleave', '.hmenu-item', function () {
        $(this).children('.hmenu-dropdown, .hmenu-flyout').css({
            left: '',
            right: '',
            'margin-top': ''
        });
    });
});
