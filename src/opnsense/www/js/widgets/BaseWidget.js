/*
 * Copyright (C) 2024 Deciso B.V.
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

export default class BaseWidget {
    constructor(config) {
        this.config = config;
        this.id = null;
        this.translations = {};
        this.tickTimeout = 5000; // Default tick timeout
        this.resizeHandles = "all"
    }

    getResizeHandles() {
        return this.resizeHandles;
    }

    setId(id) {
        this.id = id;
    }

    setTranslations(translations) {
        this.translations = translations;
    }

    getGridOptions() {
        // per-widget gridstack options override
        return {};
    }

    getMarkup() {
        return $("");
    }

    async onMarkupRendered() {
        return null;
    }

    onWidgetResize(elem, width, height) {
        return false;
    }

    async onWidgetTick() {
        return null;
    }

    onWidgetClose() {
        return null;
    }

    /* For testing purposes */
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    _setAlpha(color, opacity) {
        const op = Math.round(Math.min(Math.max(opacity || 1, 0), 1) * 255);
        return color + op.toString(16).toUpperCase();
    }

    _formatBytes(value, decimals = 2) {
        if (!isNaN(value) && value > 0) {
            let fileSizeTypes = ["", "K", "M", "G", "T", "P", "E", "Z", "Y"];
            let ndx = Math.floor(Math.log(value) / Math.log(1000) );
            if (ndx > 0) {
                return  (value / Math.pow(1000, ndx)).toFixed(2) + ' ' + fileSizeTypes[ndx];
            } else {
                return value.toFixed(2);
            }
        } else {
            return "";
        }
    }
}
