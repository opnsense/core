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

class ResizeObserverWrapper {
    _lastWidths = {};
    _lastHeights = {};
    _observer = null;

    _debounce(f, delay = 50, ensure = true) {
        // debounce to prevent a flood of calls in a short time
        let lastCall = Number.NEGATIVE_INFINITY;
        let wait;
        let handle;
        return (...args) => {
            wait = lastCall + delay - Date.now();
            clearTimeout(handle);
            if (wait <= 0 || ensure) {
                handle = setTimeout(() => {
                    f(...args);
                    lastCall = Date.now();
                }, wait);
            }
        };
    }

    observe(elements, onSizeChanged, onInitialize) {
        this._observer = new ResizeObserver(this._debounce((entries) => {
            if (entries != undefined && entries.length > 0) {
                for (const entry of entries) {
                    const width = entry.contentRect.width;
                    const height = entry.contentRect.height;

                    let id = entry.target.id;
                    if (id.length === 0) {
                        // element has just rendered
                        onInitialize(entry.target, width, height);
                        // we're observing multiple elements of the same class, assign a unique id
                        entry.target.id = Math.random().toString(36).substring(7);
                        this._lastWidths[id] = width;
                        this._lastHeights[id] = height;
                    } else {
                        if (width !== this._lastWidths[id] || height !== this._lastHeights[id]) {
                            this._lastWidths[id] = width;
                            this._lastHeights[id] = height;
                            onSizeChanged(entry.target, width, height);
                        }
                    }
                }
            }

        }));

        elements.forEach((element) => {
            this._observer.observe(element);
        });
    }

    disconnect() {
        this._observer.disconnect();
    }
}

class WidgetManager  {
    constructor(gridStackOptions = {}, gettext = {}) {
        this.gridStackOptions = gridStackOptions;
        this.gettext = gettext;
        this.loadedModules = {}; // id -> widget module
        this.widgetTranslations = {}; // id -> translations
        this.widgetConfigurations = {}; // id -> per-widget configuration
        this.widgetClasses = {}; // id -> instantiated widget module
        this.widgetHTMLElements = {}; // id -> Element types
        this.widgetTickRoutines = {}; // id -> tick routines
        this.grid = null; // gridstack instance
        this.moduleDiff = []; // list of module ids that are allowed, but not currently rendered
        this.renderDefaultDashboard = true;
        this.resizeObserver = new ResizeObserverWrapper();
    }

    async initialize() {
        try {
            // import allowed modules and current persisted configuration
            await this._loadWidgets();
            // prepare widget markup
            this._initializeWidgets();
            // render grid and append widget markup
            this._initializeGridStack();
            // render header buttons
            this._renderHeader();
            // load all dynamic content and start tick routines
            await this._loadDynamicContent();
        } catch (error) {
            console.error('Failed initializing Widgets', error);
        }
    }

    async _loadWidgets() {
        const response = await $.ajax('/api/core/dashboard/getDashboard', {
            type: 'GET',
            dataType: 'json',
            contentType: 'application/json'
        }).then(async (data) => {
            if ('dashboard' in data && data.dashboard != null) {
                this.renderDefaultDashboard = false;
                let configuration = JSON.parse(data.dashboard);
                configuration.forEach(item => {
                    this.widgetConfigurations[item.id] = item;
                });
            }

            const promises = data.modules.map(async (item) => {
                const mod = await import('/ui/js/widgets/' + item.module);
                this.loadedModules[item.id] = mod.default;
                this.widgetTranslations[item.id] = item.translations;
            });

            // Load all modules simultaneously - this shouldn't take long
            await Promise.all(promises).catch((error) => {
                console.error('Failed to load widgets', error);
                null;
            });
        });
    }

    _initializeWidgets() {
        if ($.isEmptyObject(this.loadedModules)) {
            throw new Error('No widgets loaded');
        }

        if (!this.renderDefaultDashboard) {
            // restore
            for (const [id, configuration] of Object.entries(this.widgetConfigurations)) {
                if (id in this.loadedModules) {
                    this._createGridStackWidget(id, this.loadedModules[id], configuration);
                }
            }
        } else {
            // default
            for (const [identifier, widgetClass] of Object.entries(this.loadedModules)) {
                this._createGridStackWidget(identifier, widgetClass);
            }
        }

        this.moduleDiff = Object.keys(this.loadedModules).filter(x => !Object.keys(this.widgetConfigurations).includes(x));
    }

    _createGridStackWidget(id, widgetClass, config = {}) {
        // merge persisted config with defaults
        config = {
            callbacks: {
                // pre-bind the updateGrid function to the widget instance
                updateGrid: this._updateGrid.bind(this, this.widgetHTMLElements[id]),
            },
            ...config,
        }
        // instantiate widget
        const widget = new widgetClass(config);
        // make id accessible to the widget, useful for traceability (e.g. data-widget-id attribute in the DOM)
        widget.setId(id);
        this.widgetClasses[id] = widget;

        if (!id in this.widgetTranslations) {
            console.error('Missing translations for widget', id);
        }

        widget.setTranslations(this.widgetTranslations[id]);
        widget.setTitle(this.widgetTranslations[id].title);

        // setup generic panels
        let content = widget.getMarkup();
        let $panel = this._makeWidget(id, widget.title, content);

        if (id in this.widgetConfigurations) {
            this.widgetConfigurations[id].content = $panel.prop('outerHTML');
        } else {
            // let each widget override settings
            const options = widget.getGridOptions();
            let gridElement = {
                content: $panel.prop('outerHTML'),
                id: id,
                ...options
            };

            // lock the system information widget
            if (id == 'systeminformation') {
                gridElement = {
                    x: 0, y: 0,
                    id: id,
                    ...gridElement,
                };
            }

            this.widgetConfigurations[id] = gridElement;
        }
    }

    // runs only once
    _initializeGridStack() {
        this.grid = GridStack.init(this.gridStackOptions);
        // before we render the grid, register the added event so we can store the Element type objects
        this.grid.on('added', (event, items) => {
            // store Elements for later use, such as update() and resizeToContent()
            items.forEach((item) => {
                this.widgetHTMLElements[item.id] = item.el;
            });
        });

        for (const event of ['disable', 'dragstop', 'dropped', 'removed', 'resizestop']) {
            this.grid.on(event, (event, items) => {
                $('#save-grid').show();
            });
        }

        // render to the DOM
        this.grid.load(Object.values(this.widgetConfigurations));

        // force the cell height of each widget to the lowest value. The grid will adjust the height
        // according to the content of the widget.
        this.grid.cellHeight(1);
    }

    _renderHeader() {
        // Serialization options
        let $btn_group = $('.btn-group-container');
        $btn_group.append($(`<button class="btn btn-primary" id="save-grid">${this.gettext.save}</button>`));
        $btn_group.append($(`
            <button class="btn btn-default" id="add_widget">
                <i class="fa fa-plus-circle fa-fw"></i>
                ${this.gettext.addwidget}
            </button>
        `));
        $btn_group.append($(`<button class="btn btn-secondary" id="restore-defaults">${this.gettext.restore}</button>`));
        $('#save-grid').hide();

        $('#save-grid').click(() => {
            //this.grid.cellHeight('auto', false);
            let items = this.grid.save(false);
            //this.grid.cellHeight(1, false);

            $.ajax({
                type: "POST",
                url: "/api/core/dashboard/saveWidgets",
                dataType: "text",
                contentType: 'text/plain',
                data: JSON.stringify(items),
                complete: function(data, status) {
                    let response = JSON.parse(data.responseText);

                    if (response['result'] == 'failed') {
                        console.error('Failed to save widgets', data);
                    }
                }
            });
        });

        $('#add_widget').click(() => {

            let $content = $('<div></div>');
            let $select = $('<select id="widget-selection" class="selectpicker" multiple="multiple"></select>');
            for (const [id, widget] of Object.entries(this.loadedModules)) {
                if (this.moduleDiff.includes(id)) {
                    $select.append($(`<option value="${id}">${this.widgetTranslations[id].title}</option>`));
                }
            }
            $content.append($select);

            BootstrapDialog.show({
                title: this.gettext.addwidget,
                draggable: true,
                message: $content,
                buttons: [{
                    label: this.gettext.add,
                    hotkey: 13,
                    action: async (dialog) => {
                        let ids = $('select', dialog.$modalContent).val();
                        let changed = false;
                        for (const id of ids) {
                            if (id in this.loadedModules) {
                                this.moduleDiff = this.moduleDiff.filter(x => x !== id);
                                // XXX make sure to account for the defaults here in time
                                this._createGridStackWidget(id, this.loadedModules[id]);
                                this.grid.addWidget(this.widgetConfigurations[id]);
                                this._onMarkupRendered(this.widgetClasses[id]);
                                changed = true;
                            }
                        }

                        if (changed) {
                            $('#save-grid').show();
                        }

                        dialog.close();
                    },
                }, {
                    label: this.gettext.cancel,
                    action: (dialog) => {
                        dialog.close();
                    }
                }],
                onshown: (dialog) => {
                    $('#widget-selection').selectpicker();
                }
            });
        });

        $('#restore-defaults').click(() => {
            ajaxGet("/api/core/dashboard/restoreDefaults", null, (response, status) => {
                if (response['result'] == 'failed') {
                    console.error('Failed to restore default widgets');
                } else {
                    window.location.reload();
                }
            });
        });
    }

    /* Executes all widget post-render callbacks asynchronously and in "parallel".
     * No widget should wait on other widgets, and therefore the
     * individual widget tick() callbacks are not bound to a master timer,
     * this has the benefit of making it configurable per widget.
     */
     async _loadDynamicContent() {
        // map to an array of context-bound _onMarkupRendered functions
        let fns = Object.values(this.widgetClasses).map((widget) => {
            return this._onMarkupRendered.bind(this, widget);
        });
        // convert each _onMarkupRendered(widget) to a promise
        let promises = fns.map(func => new Promise(resolve => resolve(func())));
        // fire away
        await Promise.all(promises).catch((error) => {
            console.error('Failed to load dynamic content', error);
            null;
        });
    }

    // Executed for each widget; starts the widget-specific tick routine.
    async _onMarkupRendered(widget) {
        // click handler for widget removal.
        $(`#close-handle-${widget.id}`).click((event) => {
            this._onWidgetClose(widget.id);
        });

        // load the widget dynamic content, make sure to bind the widget context to the callback
        let onMarkupRendered = widget.onMarkupRendered.bind(widget);
        // show a spinner while the widget is loading
        let $selector = $(`.widget-${widget.id} > .widget-content > .panel-divider`);
        $selector.after($(`<div class="widget-spinner spinner-${widget.id}"><i class="fa fa-spinner fa-spin"></i></div>`));
        await onMarkupRendered();
        $(`.spinner-${widget.id}`).remove();

        // XXX this code enforces per-widget resize handle definitions, which isn't natively
        // supported by GridStack.
        $(this.widgetHTMLElements[widget.id]).attr('gs-resize-handles', widget.getResizeHandles());
        this.widgetHTMLElements[widget.id].gridstackNode._initDD = false;
        this.grid.resizable(this.widgetHTMLElements[widget.id], true);

        // trigger initial widget resize and start observing resize events
        this.resizeObserver.observe(
            [document.querySelector(`.widget-${widget.id}`)],
            (elem, width, height) => {
                for (const subclass of elem.className.split(" ")) {
                    let id = subclass.split('-')[1];
                    if (id in this.widgetClasses) {
                        if (this.widgetClasses[id].onWidgetResize(elem, width, height)) {
                            this._updateGrid(elem.parentElement.parentElement);
                        }
                    }
                }
            },
            (elem, width, height) => {
                widget.onWidgetResize(this.widgetHTMLElements[widget.id], width, height);
                this._updateGrid(elem.parentElement.parentElement);
            }
        );

        // start the widget-specific tick routine
        let onWidgetTick = widget.onWidgetTick.bind(widget);
        await onWidgetTick();
        const interval = setInterval(async () => {
            await onWidgetTick();
            this._updateGrid(this.widgetHTMLElements[widget.id]);
        }, widget.tickTimeout);
        // store the reference to the tick routine so we can clear it later on widget removal
        this.widgetTickRoutines[widget.id] = interval;
    }

    // Recalculate widget/grid dimensions
    _updateGrid(elem = null) {
        if (elem !== null) {
            this.grid.resizeToContent(elem);
        } else {
            for (const item of this.grid.getGridItems()) {
                this.grid.resizeToContent(item);
            }
        }
    }

    // Generic widget panels
    _makeWidget(identifier, title, content) {
        let $panel = $(`<div class="widget widget-${identifier}"></div>`);
        let $content = $(`<div class="widget-content"></div>`);
        let $header = $(`
            <div class="widget-header">
                <div></div>
                <div>${title}</div>
                <div id="close-handle-${identifier}" class="close-handle">
                    <i class="fa fa-times fa-xs"></i>
                </div>
            </div>
        `);
        $content.append($header);
        let $divider = $(`<div class="panel-divider"><div class="line"></div></div></div>`);
        $content.append($divider);
        $content.append(content);
        $panel.append($content);

        return $panel;
    }

    _onWidgetClose(id) {
        clearInterval(this.widgetTickRoutines[id]);
        this.widgetClasses[id].onWidgetClose();
        this.grid.removeWidget(this.widgetHTMLElements[id]);
        this.moduleDiff.push(id);
    }
}
