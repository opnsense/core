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
        this.runtimeOptions = {}; // runtime changes to the gridstack options that are to be persisted
        this.gettext = gettext;
        this.loadedModules = {}; // id -> widget module
        this.widgetTranslations = {}; // id -> translations
        this.widgetConfigurations = {}; // id -> per-widget configuration
        this.widgetClasses = {}; // id -> instantiated widget module
        this.widgetHTMLElements = {}; // id -> Element types
        this.widgetTickRoutines = {}; // id -> tick routines
        this.grid = null; // gridstack instance
        this.moduleDiff = []; // list of module ids that are allowed, but not currently rendered
        this.resizeObserver = new ResizeObserverWrapper();
    }

    async initialize() {
        // render header buttons, make sure this always runs so a user can clear the grid if necessary
        this._renderHeader();

        try {
            // import allowed modules and current persisted configuration
            await this._loadWidgets();
            // prepare widget markup
            this._initializeWidgets();
            // render grid and append widget markup
            this._initializeGridStack();
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
            try {
                let configuration = JSON.parse(data.dashboard);
                configuration.widgets.forEach(item => {
                    this.widgetConfigurations[item.id] = item;
                });

                this.runtimeOptions = configuration.options;
            } catch (error) {
                // persisted config likely out of date, reset to defaults
                this._restoreDefaults(false);
            }

            const promises = data.modules.map(async (item) => {
                const mod = await import('/ui/js/widgets/' + item.module + '?t='+Date.now());
                this.loadedModules[item.id] = mod.default;
                this.widgetTranslations[item.id] = item.translations;
            });

            // Load all modules simultaneously - this shouldn't take long
            const results = await Promise.all(promises.map(p => p.catch(e => e)));
            const errors = results.filter(result => (result instanceof Error));
            if (errors.length > 0) console.error('Failed to load one or more widgets:', errors);
        });
    }

    _initializeWidgets() {
        if ($.isEmptyObject(this.loadedModules)) {
            throw new Error('No widgets loaded');
        }

        for (const [id, configuration] of Object.entries(this.widgetConfigurations)) {
            if (id in this.loadedModules) {
                try {
                    this._createGridStackWidget(id, this.loadedModules[id], configuration);
                } catch (error) {
                    console.error('Failed to create widget', id, error);
                }
            }
        }

        this.moduleDiff = Object.keys(this.loadedModules).filter(x => !Object.keys(this.widgetConfigurations).includes(x));
    }

    _createGridStackWidget(id, widgetClass, persistedConfig = {}) {
        // merge persisted config with defaults
        let config = {
            callbacks: {
                // pre-bind the updateGrid function to the widget instance
                updateGrid: () => {
                    this._updateGrid.call(this, this.widgetHTMLElements[id])
                }
            },
            ...persistedConfig,
        }

        // instantiate widget
        const widget = new widgetClass(config);
        // make id accessible to the widget, useful for traceability (e.g. data-widget-id attribute in the DOM)
        widget.setId(id);
        this.widgetClasses[id] = widget;

        document.addEventListener('visibilitychange', (e) => {
            this.widgetClasses[id].onVisibilityChanged(!document.hidden);
        });

        if (!id in this.widgetTranslations) {
            console.error('Missing translations for widget', id);
        }

        widget.setTranslations(this.widgetTranslations[id]);

        // setup generic panels
        let content = widget.getMarkup();
        let $panel = this._makeWidget(id, this.widgetTranslations[id].title, content);

        const options = widget.getGridOptions();
        const gridElement = {
            content: $panel.prop('outerHTML'),
            id: id,
            minW: 2, // force a minimum width of 2 unless specified otherwise
            ...config,
            ...options
        };

        this.widgetConfigurations[id] = gridElement;
    }

    // runs only once
    _initializeGridStack() {
        let runtimeConfig = {}

        // translate runtime options to gridstack options
        if (this.runtimeOptions.gridLocked) {
            runtimeConfig.disableMove = true;
            runtimeConfig.disableResize = true;
        }

        this.grid = GridStack.init({...this.gridStackOptions, ...runtimeConfig});
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

        if (this.runtimeOptions.gridLocked) {
            $('#lock-grid').trigger('click');
            $('#lock-grid').trigger('mouseup');
            $('#save-grid').hide();
        }
    }

    _renderHeader() {
        // Serialization options
        let $btn_group = $('.btn-group-container');

        // Append Save button and directly next to it, a hidden spinner
        $btn_group.append($(`
            <button class="btn btn-primary" id="save-grid">
                <span id="save-btn-text" class="show">${this.gettext.save}</span>
                <span id="icon-container">
                    <i class="fa fa-spinner fa-spin hide" id="save-spinner" style="font-size: 14px;"></i>
                    <i class="fa fa-check checkmark hide" id="save-check" style="font-size: 14px;"></i>
                </span>
            </button>
        `));
        $btn_group.append($(`
            <button class="btn btn-default" id="add_widget">
                <i class="fa fa-plus-circle fa-fw"></i>
                ${this.gettext.addwidget}
            </button>
        `));
        $btn_group.append($(`<button class="btn btn-secondary" id="restore-defaults">${this.gettext.restore}</button>`));
        $btn_group.append($(`
            <button class="btn btn-secondary" id="lock-grid">
                <i class="fa fa-lock" style="font-size: 14px;"></i>
            </button>
        `));

        // Initially hide the save button
        $('#save-grid').hide();

        // Click event for save button
        $('#save-grid').click(async () => {
            await this._saveDashboard();
        });

        $('#add_widget').click(() => {

            let $content = $('<div></div>');
            let $select = $('<select id="widget-selection" data-container="body" class="selectpicker" multiple="multiple"></select>');

            // Sort options
            let options = [];
            for (const [id, widget] of Object.entries(this.loadedModules)) {
                if (this.moduleDiff.includes(id)) {
                    options.push({
                        value: id,
                        text: this.widgetTranslations[id].title
                    });
                }
            }
            options.sort((a, b) => a.text.localeCompare(b.text));
            options.forEach(option => {
                $select.append($(`<option value="${option.value}">${option.text}</option>`));
            });

            $content.append($select);

            BootstrapDialog.show({
                title: this.gettext.addwidget,
                draggable: true,
                animate: false,
                message: $content,
                buttons: [{
                    label: this.gettext.add,
                    hotkey: 13,
                    action: (dialog) => {
                        let ids = $('select', dialog.$modalContent).val();
                        let changed = false;
                        for (const id of ids) {
                            if (id in this.loadedModules) {
                                this.moduleDiff = this.moduleDiff.filter(x => x !== id);
                                // XXX make sure to account for the defaults here in time
                                this._createGridStackWidget(id, this.loadedModules[id]);
                                this.grid.addWidget(this.widgetConfigurations[id]);
                                this._onMarkupRendered(this.widgetClasses[id]);
                                this._updateGrid(this.widgetHTMLElements[id]);
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
                onshown: function (dialog) {
                    $('#widget-selection').selectpicker();
                },
                onhide: function (dialog) {
                    $('#widget-selection').selectpicker('destroy');
                }
            });
        });

        $('#restore-defaults').click(() => {
            this._restoreDefaults();
        });

        $('#lock-grid').on('click', () => {
            $('#lock-grid').toggleClass('btn-pressed');

            if ($('#lock-grid').hasClass('btn-pressed')) {
                this.grid.enableMove(false);
                this.grid.enableResize(false);
                $('.widget-content').css('cursor', 'default');
                $('.close-handle').hide();
                $('.edit-handle').hide();
                this.runtimeOptions.gridLocked = true;
            } else {
                this.grid.enableMove(true);
                this.grid.enableResize(true);
                $('.widget-content').css('cursor', 'grab');
                $('.close-handle').show();
                $('.edit-handle').show();
                this.runtimeOptions.gridLocked = false;
            }

            $('#save-grid').show();
        });

        $('#lock-grid').mouseup(function() {
            $(this).blur();
        });
    }

    /* Executes all widget post-render callbacks asynchronously and in "parallel".
     * No widget should wait on other widgets, and therefore the
     * individual widget tick() callbacks are not bound to a master timer,
     * this has the benefit of making it configurable per widget.
     */
    async _loadDynamicContent() {
        // map to an array of context-bound _onMarkupRendered functions and their associated widget ids
        let tasks = Object.entries(this.widgetClasses).map(([id, widget]) => {
            return {
                id,
                func: this._onMarkupRendered.bind(this, widget)
            };
        });

        let functions = tasks.map(({ id, func }) => {
            return () => new Promise((resolve) => {
                resolve(func().then(result => ({ result, id })).catch(error => this._displayError(id, error)));
            });
        });

        // Fire away
        await Promise.all(functions.map(f => f()));
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

        // retrieve widget-specific options
        if (widget.isConfigurable()) {
            let $editHandle = $(`
                <div id="edit-handle-${widget.id}" class="edit-handle">
                    <i class="fa fa-pencil"></i>
                </div>
            `);
            $(`#close-handle-${widget.id}`).before($editHandle);

            if (this.runtimeOptions.gridLocked) {
                $editHandle.hide();
            }

            $editHandle.on('click', async (event) => {
                await this._renderOptionsForm(widget);
            });
        }

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
            }
        );

        // start the widget-specific tick routine
        let onWidgetTick = widget.onWidgetTick.bind(widget);
        try {
            await onWidgetTick();
            this._updateGrid(this.widgetHTMLElements[widget.id]);
        } catch (error) {
            this._displayError(widget.id, error);
        }
        const interval = setInterval(async () => {
            try {
                await onWidgetTick();
                this._updateGrid(this.widgetHTMLElements[widget.id]);
            } catch (error) {
                this._displayError(widget.id, error);
            }
        }, widget.tickTimeout * 1000);
        // store the reference to the tick routine so we can clear it later on widget removal
        this.widgetTickRoutines[widget.id] = interval;
    }

    _displayError(widgetId, error) {
        console.error(`Failed to load content for widget: ${widgetId}, Error:`, error);

        const widget =  $(`.widget-${widgetId} > .widget-content > .panel-divider`);
        widget.nextAll().remove()
        widget.after(`
            <div class="widget-error">
                <i class="fa fa-exclamation-circle text-danger"></i>
                <br/>
                ${this.gettext.failed}
            </div>
        `);
        this._updateGrid(this.widgetHTMLElements[widgetId]);
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
                <div class="widget-header-left"></div>
                <div id="${identifier}-title" class="widget-title"><b>${title}</b></div>
                <div class="widget-command-container">
                    <div id="close-handle-${identifier}" class="close-handle">
                        <i class="fa fa-times fa-xs"></i>
                    </div>
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

    _restoreDefaults(confirmDialog = true) {
        if (confirmDialog) {
            if (!confirm(this.gettext.restoreconfirm)) {
                return;
            }
        }
        $.ajax({type: "POST", url: "/api/core/dashboard/restoreDefaults"}).done((response) => {
            if (response['result'] == 'failed') {
                console.error('Failed to restore default widgets');
            } else {
                window.location.reload();
            }
        });
    }

    async _saveDashboard() {
        // Show the spinner when the save operation starts
        $('#save-btn-text').toggleClass("show hide");
        $('#save-spinner').addClass('show');
        $('#save-grid').prop('disabled', true);

        let items = this.grid.save(false);
        items = await Promise.all(items.map(async (item) => {
            let widgetConfig = await this.widgetClasses[item.id].getWidgetConfig();
            if (widgetConfig) {
                item['widget'] = widgetConfig;
            }

            // XXX the gridstack save() behavior is inconsistent with the responsive columnWidth option,
            // as the calculation will return impossible values for the x, y, w and h attributes.
            // For now, the gs-{x,y,w,h} attributes are a better representation of the grid for layout persistence
            let elem = $(this.widgetHTMLElements[item.id]);
            item.x = parseInt(elem.attr('gs-x')) ?? 1;
            item.y = parseInt(elem.attr('gs-y')) ?? 1;
            item.w = parseInt(elem.attr('gs-w')) ?? 1;
            item.h = parseInt(elem.attr('gs-h')) ?? 1;

            delete item['callbacks'];
            return item;
        }));

        const payload = {
            options: {...this.runtimeOptions},
            widgets: items
        };

        $.ajax({
            type: "POST",
            url: "/api/core/dashboard/saveWidgets",
            dataType: "text",
            contentType: 'text/plain',
            data: JSON.stringify(payload),
            complete: (data, status) => {
                setTimeout(() => {
                    let response = JSON.parse(data.responseText);

                    if (response['result'] == 'failed') {
                        console.error('Failed to save widgets', data);
                        $('#save-grid').prop('disabled', false);
                        $('#save-spinner').removeClass('show').addClass('hide');
                        $('#save-btn-text').removeClass('hide').addClass('show');
                    } else {
                        $('#save-spinner').removeClass('show').addClass('hide');
                        $('#save-check').toggleClass("hide show");
                        setTimeout(() => {
                            // Hide the save button upon successful save
                            $('#save-grid').hide();
                            $('#save-check').toggleClass("show hide");
                            $('#save-btn-text').toggleClass("hide show");
                            $('#save-grid').prop('disabled', false);
                        }, 500)
                    }
                }, 300); // Artificial delay to give more feedback on button click
            }
        });
    }

    async _renderOptionsForm(widget) {
        let $content = $(`<div class="widget-options"></div>`);

        // parse widget options
        const options = await widget.getWidgetOptions();
        for (const [key, value] of Object.entries(options)) {
            let $option = $(`<div class="widget-option-container"></div>`);
            switch (value.type) {
                case 'select_multiple':
                    let $select = $(`<select class="widget_optionsform_selectpicker"
                                     id="${value.id}"
                                     data-container="body"
                                     class="selectpicker"
                                     multiple="multiple"></select>`);

                    for (const option of value.options) {
                        $select.append($(`<option value="${option.value}" ${option.selected ? 'selected' : ''}>${option.value}</option>`));
                    }

                    if (value.options.every(obj => !obj.selected)) {
                        // No selection, apply the default.
                        $select.val(value.default);
                    }

                    $option.append($(`<div><b>${value.title}</b></div>`));
                    $option.append($select);
                    break;
                default:
                    console.error('Unknown option type', value.type);
                    continue;
            }

            $content.append($option);
        }

        // present widget options
        BootstrapDialog.show({
            title: this.gettext.options,
            draggable: true,
            animate: false,
            message: $content,
            buttons: [{
                label: this.gettext.ok,
                hotkey: 13,
                action: (dialog) => {
                    let values = {};
                    for (const [key, value] of Object.entries(options)) {
                        switch (value.type) {
                            case 'select_multiple':
                                values[key] = $(`#${value.id}`).val();
                                if (values[key].count === 0) {
                                    values[key] = value.default;
                                }
                                break;
                            default:
                                console.error('Unknown option type', value.type);
                        }
                    }

                    widget.setWidgetConfig(values);
                    widget.onWidgetOptionsChanged(values);
                    $('#save-grid').show();
                    dialog.close();
                }
            }, {
                label: this.gettext.cancel,
                action: (dialog) => {
                    dialog.close();
                }
            }],
            onshown: function(dialog) {
                $('.widget_optionsform_selectpicker').selectpicker();
            },
            onhide: function(dialog) {
                $('.widget_optionsform_selectpicker').selectpicker('destroy');
            }
        });
    }

    _onWidgetClose(id) {
        clearInterval(this.widgetTickRoutines[id]);
        this.widgetClasses[id].onWidgetClose();
        this.grid.removeWidget(this.widgetHTMLElements[id]);
        this.moduleDiff.push(id);
    }
}
