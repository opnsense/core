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
        this.tickTimeout = 5; // Default tick timeout
        this.resizeHandles = "all"
        this.eventSource = null;
        this.eventSourceUrl = null;
        this.eventSourceOnData = null;
        this.cachedData = {};

        /* Connection timeout params */
        this.timeoutPeriod = 1000;
        this.retryLimit = 3;
        this.eventSourceRetryCount = 0; // retrycount for $.ajax is managed in its own scope
    }

    /* Public functions */

    getResizeHandles() {
        return this.resizeHandles;
    }

    getWidgetConfig() {
        if (this.config !== undefined && 'widget' in this.config) {
            return this.config['widget'];
        }

        return false;
    }

    setId(id) {
        this.id = id;
    }

    setTranslations(translations) {
        this.translations = translations;
    }

    /* Public virtual functions */

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
        this.closeEventSource();
    }

    onVisibilityChanged(visible) {
        if (this.eventSourceUrl !== null) {
            if (visible) {
                this.openEventSource(this.eventSourceUrl, this.eventSourceOnData);
            } else if (this.eventSource !== null) {
                this.closeEventSource();
            }
        }
    }

    /* Utility/protected functions */

    ajaxGet(url, data={}) {
        let retryLimit = this.retryLimit;
        let timeoutPeriod = this.timeoutPeriod;
        return new Promise((resolve, reject) => {
            function makeRequest() {
                $.ajax({
                    type: 'GET',
                    url: url,
                    dataType: 'json',
                    contentType: 'application/json',
                    data: data,
                    tryCount: 0,
                    retryLimit: retryLimit,
                    timeout: timeoutPeriod,
                    success: function (responseData) {
                        resolve(responseData);
                    },
                    error: function (xhr, textStatus, errorThrown) {
                        if (textStatus === 'timeout') {
                            this.tryCount++;
                            if (this.tryCount <= this.retryLimit) {
                                $.ajax(this);
                                return;
                            }
                        }
                        reject({ xhr, textStatus, errorThrown });
                    }
                });
            }

            makeRequest();
        });
    }

    dataChanged(id, data) {
        if (id in this.cachedData) {
            if (JSON.stringify(this.cachedData[id]) !== JSON.stringify(data)) {
                this.cachedData[id] = data;
                return true;
            }
        } else {
            this.cachedData[id] = data;
            return true;
        }

        return false;
    }

    setWidgetConfig(config) {
        this.config['widget'] = config;
    }

    openEventSource(url, onMessage) {
        this.closeEventSource();

        if (this.eventSourceRetryCount >= this.retryLimit) {
            return;
        }

        this.eventSourceUrl = url;
        this.eventSourceOnData = onMessage;
        this.eventSource = new EventSource(url);

        /* Unlike $.ajax, EventSource does not have a timeout mechanism */
        let timeoutHandler = setTimeout(() => {
            this.closeEventSource();
            this.eventSourceRetryCount++;
            this.openEventSource(url, onMessage);
        }, this.timeoutPeriod);

        this.eventSource.onopen = (event) => {
            clearTimeout(timeoutHandler);
            this.eventSourceRetryCount = 0;
        };

        this.eventSource.onmessage = onMessage;
        this.eventSource.onerror = (e) => {
            if (this.eventSource.readyState == EventSource.CONNECTING) {
                /* Backend closed connection due to timeout, reconnect issued by browser */
                return;
            }

            if (this.eventSource.readyState == EventSource.CLOSED) {
                this.closeEventSource();
            } else {
                console.error('Unknown error occurred during streaming operation', e);
            }
        };
    }

    closeEventSource() {
        if (this.eventSource !== null) {
            this.eventSource.close();
            this.eventSource = null;
        }
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
