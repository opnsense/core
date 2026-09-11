/*
 * Copyright (C) 2026 Deciso B.V.
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

class MenuItem {
    constructor(node) {
        this.href = node.attr("href");
        this._obj = node;
        this.breadcrumb();
    }

    breadcrumb() {
        let result = [this._obj.text().trim()];
        let this_ref = this._obj;
        let parent_div = this_ref.closest('div');
        while (parent_div && parent_div.attr('id')) {
            let container = $("a[href='#" + parent_div.attr('id')+"']");
            result.push(container.text().trim());
            parent_div = parent_div.parent().closest('div');
        }
        result.reverse()
        return result.join(': ');
    }
}

class MenuSystem {
    constructor() {
        this._menusystem = $("#mainmenu");
    };

    * walk (){
        for (const node of this._menusystem.find("a.list-group-item").toArray()) {
            const element = $(node);
            const href = element.attr("href");
            if (href && !href.startsWith("#")) {
                /* only yield leaves */
                yield new MenuItem(element);
            }
        }
    };
}

/* wire any ".menu-pin": pinning keeps its panel expanded, persisted via set_pinned */
$(document).ready(function () {
    const $navigation = $('#navigation');

    $('.menu-pin').each(function () {
        const $pin = $(this);
        const $panel = $($pin.closest('a[data-toggle="collapse"]').attr('href'));
        const menuId = $pin.data('menu-id');
        const pinText = $pin.data('pin-text') || 'Pin';
        const unpinText = $pin.data('unpin-text') || 'Unpin';

        if (!$panel.length || !menuId) {
            return;
        }

        const setPinned = function (pinned) {
            $pin.toggleClass('pinned', pinned)
                .attr('data-original-title', pinned ? unpinText : pinText)
                .tooltip('fixTitle').tooltip('show');
        };

        /* don't let the accordion collapse a pinned panel, except as a fly-out in the icon-only sidebar */
        $panel.on('hide.bs.collapse', function (e) {
            /* hide events of nested submenus bubble up, those may still collapse */
            if (e.target === this && $pin.hasClass('pinned') && !$navigation.hasClass('col-sidebar-left')) {
                e.preventDefault();
            }
        });

        $pin.on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const nowPinned = !$pin.hasClass('pinned');

            setPinned(nowPinned);
            if (nowPinned) {
                $panel.collapse('show');
            }

            $.ajax('/api/core/menu/set_pinned/', {
                type: 'POST',
                dataType: 'json',
                data: { menuId: menuId, isPinned: nowPinned ? '1' : '0' },
                success: function (response) {
                    if (response.result !== 'saved') {
                        setPinned(!nowPinned);
                    }
                },
                error: function () {
                    setPinned(!nowPinned);
                }
            });
        });
    });
});
