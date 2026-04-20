/*
 * Copyright (C) 2026 Greelan
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
    var $cfg = $('#favorites-config');
    var addText = $cfg.data('add-text') || 'Add Favorite';
    var removeText = $cfg.data('remove-text') || 'Remove Favorite';
    var favorites = $cfg.data('favorites') || [];
    var $favPanel = $('#Favorites');
    var $favHeader = $('a[href="#Favorites"]');

    var getRefClass = function ($el) {
        return ($el.attr('class') || '').split(/\s+/).find(function (c) {
            return c.indexOf('menu_ref_') === 0;
        });
    };

    var findReal = function (refClass) {
        return $('#mainmenu .' + refClass).not('#Favorites .' + refClass);
    };

    var menuOrder = {};
    $('#mainmenu a.list-group-item').not('#Favorites a').each(function (i) {
        var cls = getRefClass($(this));
        if (cls) menuOrder[cls] = i;
    });

    $favPanel.children('a.list-group-item').each(function () {
        var $entry = $(this), cls = getRefClass($entry), $real = cls ? findReal(cls) : $();
        $($entry.attr('href')).remove();
        if (!$real.length) {
            $entry.remove();
            return;
        }
        $entry.text(new MenuItem($real).breadcrumb()).removeAttr('data-toggle').attr('href', '#');
    });

    $favPanel.children('a.list-group-item').sort(function (a, b) {
        return (menuOrder[getRefClass($(a))] || 0) - (menuOrder[getRefClass($(b))] || 0);
    }).appendTo($favPanel);

    if ($favPanel.children('a.list-group-item').length === 0) {
        $favHeader.hide();
    }

    $favPanel.on('click', '.list-group-item', function (e) {
        e.preventDefault();
        var cls = getRefClass($(this));
        if (cls) {
            $favPanel.collapse('hide');
            findReal(cls)[0]?.click();
        }
    });

    var $star = $('header.page-content-head .menu-favorite-star');
    if (!$star.length) return;

    var refreshStar = function (url) {
        var fav = favorites.indexOf(url) !== -1;
        $star.data('menu-url', url).attr('data-menu-url', url)
             .toggleClass('fas', fav).toggleClass('far', !fav)
             .attr('data-original-title', fav ? removeText : addText).tooltip('fixTitle');
    };

    var initialUrl = String($star.data('menu-url') || '');
    if (initialUrl.indexOf('#') !== -1) {
        refreshStar(window.location.pathname + (window.location.hash || '#' + initialUrl.split('#')[1]));
        $(document).on('shown.bs.tab', 'a[data-toggle="tab"]', function (e) {
            var href = $(e.target).attr('href');
            if (href && href.charAt(0) === '#') refreshStar(window.location.pathname + href);
        });
    }

    $star.on('click', function (e) {
        e.stopPropagation();
        var menuUrl = $(this).data('menu-url');
        var wasFav = $(this).hasClass('fas'), nowFav = !wasFav;

        $star.toggleClass('fas far')
             .attr('data-original-title', nowFav ? removeText : addText)
             .tooltip('fixTitle').tooltip('show');

        var revert = function () {
            $star.toggleClass('fas far')
                 .attr('data-original-title', wasFav ? removeText : addText)
                 .tooltip('fixTitle').tooltip('show');
        };

        $.ajax('/api/core/menu/setFavorite/', {
            type: 'POST',
            dataType: 'json',
            data: { menuUrl: menuUrl, isFavorite: nowFav ? 'true' : 'false' },
            success: function (response) {
                if (response.result !== 'saved') { revert(); return; }
                var $real = $('#mainmenu a[href="' + menuUrl + '"]');
                var refClass = $real.length ? getRefClass($real) : null;
                if (!refClass) return;
                if (nowFav) {
                    var $newEntry = $('<a>').attr('href', '#')
                        .addClass('list-group-item ' + refClass)
                        .text(new MenuItem($real).breadcrumb());
                    var $before = null;
                    $favPanel.children('a.list-group-item').each(function () {
                        var cls = getRefClass($(this));
                        if (cls && menuOrder[cls] > menuOrder[refClass]) {
                            $before = $(this);
                            return false;
                        }
                    });
                    $before ? $before.before($newEntry) : $favPanel.append($newEntry);
                    $favHeader.show();
                    favorites.push(menuUrl);
                } else {
                    $favPanel.children('.' + refClass).remove();
                    favorites.splice(favorites.indexOf(menuUrl), 1);
                    if ($favPanel.children('a.list-group-item').length === 0) {
                        $favPanel.collapse('hide');
                        $favHeader.hide();
                    }
                }
            },
            error: revert
        });
    });
});
