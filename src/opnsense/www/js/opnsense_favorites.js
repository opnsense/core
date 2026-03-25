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

/**
 * Menu favorites toggle handler.
 *
 * Expects the following DOM setup before this script runs:
 *   - A container element with id="favorites-config" carrying:
 *       data-add-text="..."   (translated "Add Favorite" string)
 *       data-remove-text="..." (translated "Remove Favorite" string)
 *   - A star icon with class="menu-favorite-star" in the page heading carrying:
 *       data-menu-url="..."   (menu item URL)
 *       data-menu-label="..." (display label for the favorites panel)
 *   - A collapse panel with id="Favorites" for favorite entries
 *   - A header link with id="favorites-header"
 */
$(document).ready(function () {
    var $cfg = $('#favorites-config');
    var addToFavText = $cfg.data('add-text') || 'Add Favorite';
    var removeFromFavText = $cfg.data('remove-text') || 'Remove Favorite';

    // build menu-order index from sidebar links: url -> DOM position
    // new Favorites entries are inserted in the same order as the main menu
    var menuOrderIndex = {};
    $('#mainmenu a[href]').not('#Favorites a').each(function (i) {
        var href = $(this).attr('href');
        if (href && href.charAt(0) !== '#') {
            menuOrderIndex[href] = i;
        }
    });

    // Favorites: for same-page clicks, collapse Favorites and expand the correct menu section
    // use event delegation so dynamically added entries also get this handler
    $('#Favorites').on('click', '.list-group-item', function (e) {
        if (this.pathname + this.search == window.location.pathname + window.location.search) {
            e.preventDefault();
            $('#Favorites').collapse('hide');

            // switch tab when the hash differs; click the matching tab link
            // the same way the page's own hashchange handler does
            if (this.hash && this.hash !== window.location.hash) {
                $('a[href="' + this.hash + '"]').click();
                return;
            }

            var activeItem = $('#mainmenu .list-group-item.active').not('#Favorites .list-group-item');
            if (activeItem.length) {
                activeItem.parents('.collapse').each(function () {
                    $(this).collapse('show');
                });
                var navbar_center = ($(window).height() - $(".collapse.navbar-collapse").height()) / 2;
                $('html,aside').scrollTop(activeItem.offset().top - navbar_center);
            }
        }
    });

    // handle hash-based tab pages (e.g. Firewall: Shaper with #pipes, #queues, #rules)
    // the server always picks the first tab; update the star for the actual active tab
    var $headingStar = $('header.page-content-head .menu-favorite-star');
    if ($headingStar.length && $headingStar.data('menu-url') && String($headingStar.data('menu-url')).indexOf('#') !== -1) {
        var baseLabel = $headingStar.data('menu-label'); // e.g. "Firewall: Shaper" (no tab suffix)

        var updateStarForTab = function (hash) {
            var fullUrl = window.location.pathname + hash;
            var $sidebarLink = $('#mainmenu a[href="' + fullUrl + '"]').not('#Favorites a');
            var tabName = $sidebarLink.length ? $sidebarLink.text().trim() : hash.replace('#', '');
            var label = baseLabel + ': ' + tabName;
            var $favEntry = $('#Favorites [data-menu-url="' + fullUrl + '"]');
            var isFavorited = $favEntry.length > 0;

            $headingStar.data('menu-url', fullUrl)
                 .attr('data-menu-url', fullUrl)
                 .data('menu-label', label)
                 .attr('data-menu-label', label);

            if (isFavorited) {
                $headingStar.removeClass('far').addClass('fas')
                     .attr('data-original-title', removeFromFavText).tooltip('fixTitle');
            } else {
                $headingStar.removeClass('fas').addClass('far')
                     .attr('data-original-title', addToFavText).tooltip('fixTitle');
            }
        };

        // set correct state on page load
        var initialHash = window.location.hash || '#' + String($headingStar.data('menu-url')).split('#')[1];
        updateStarForTab(initialHash);

        // update when user switches tabs
        $(document).on('shown.bs.tab', 'a[data-toggle="tab"]', function (e) {
            var hash = $(e.target).attr('href');
            if (hash && hash.charAt(0) === '#') {
                updateStarForTab(hash);
            }
        });
    }

    // handle favorite star click in page heading
    $headingStar.on('click', function (e) {
        e.stopPropagation();

        var $star = $(this);
        var menuUrl = $star.data('menu-url');
        var menuLabel = $star.data('menu-label') || '';
        var isFavorite = $star.hasClass('fas');
        var newFavoriteState = !isFavorite;

        // pre-emptively update star icon and tooltip
        $star
            .toggleClass('fas far')
            .attr('data-original-title', newFavoriteState ? removeFromFavText : addToFavText).tooltip('fixTitle').tooltip('show');

        var revertStar = function () {
            $star
                .toggleClass('fas far')
                .attr('data-original-title', isFavorite ? removeFromFavText : addToFavText).tooltip('fixTitle').tooltip('show');
        };

        $.ajax('/api/core/menu/setFavorite/', {
            type: 'POST',
            dataType: 'json',
            data: {
                menuUrl: menuUrl,
                isFavorite: newFavoriteState ? 'true' : 'false'
            },
            success: function (response) {
                if (response.result === 'saved') {
                    var $favPanel = $('#Favorites');
                    var $favHeader = $('#favorites-header');
                    if (newFavoriteState) {
                        var $newEntry = $('<a>')
                            .attr('href', menuUrl)
                            .addClass('list-group-item')
                            .attr('data-menu-url', menuUrl)
                            .text(menuLabel);
                        // insert at the correct menu-order position
                        var newOrder = menuOrderIndex[menuUrl];
                        var $insertBefore = null;
                        $favPanel.children('[data-menu-url]').each(function () {
                            if (menuOrderIndex[$(this).data('menu-url')] > newOrder) {
                                $insertBefore = $(this);
                                return false;
                            }
                        });
                        if ($insertBefore) {
                            $insertBefore.before($newEntry);
                        } else {
                            $favPanel.append($newEntry);
                        }
                        $favHeader.show();
                    } else {
                        $favPanel.find('[data-menu-url="' + menuUrl + '"]').remove();
                        if ($favPanel.children().length === 0) {
                            $favPanel.collapse('hide');
                            $favHeader.hide();
                        }
                    }
                } else {
                    revertStar();
                }
            },
            error: function () {
                revertStar();
            }
        });
    });
});
