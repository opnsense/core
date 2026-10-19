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

(function(global) {
    "use strict";

    // -------------------------
    // Base Repository
    // Extend with an implementation that knows how to load, normalize, save and restore data
    // -------------------------

    class BaseRepository {
        constructor(baseUrl) {
            this.baseUrl = baseUrl;
        }
        async load () {}
        async loadDefaults() {}
        async save() {}
        async restoreDefaults() {}
    }

    // -------------------------
    // WidgetView
    // Owns the generic panel DOM and instance-scoped controls.
    // -------------------------

    class WidgetView {
        constructor({
            instanceId,
            typeId,
            title = '',
            link = '',
            titleVisible = true,
            configurable = false,
            cloneable = false,
            duplicateTitle = '',
            failedMessage = ''
        }) {
            this.instanceId = instanceId;
            this.typeId = typeId;
            this.title = title;
            this.link = link;
            this.titleVisible = titleVisible;
            this.configurable = configurable;
            this.cloneable = cloneable;
            this.duplicateTitle = duplicateTitle;
            this.failedMessage = failedMessage;
            this.root = null;
            this.errorVisible = false;
        }

        render(content) {
            const $panel = $('<div>').addClass(`widget widget-${this.instanceId}`).attr({
                'data-widget-instance-id': this.instanceId,
                'data-widget-type': this.typeId
            });
            const $content = $('<div>').addClass('widget-content');
            const $header = $('<div>').addClass('widget-header');
            const $commands = $('<div>').addClass('widget-command-container');

            $header.append($('<div>').addClass('widget-header-left'));
            $header.append(
                $('<div>').addClass('widget-title').attr('id', `${this.instanceId}-title`)
                    .append($('<b>').text(this.title))
            );

            if (this.link !== '') {
                $commands.append(
                    $('<div>').addClass('link-handle').attr('id', `link-handle-${this.instanceId}`)
                        .append(
                            $('<a>').attr({href: this.link, target: '_blank', rel: 'noopener'})
                                .append($('<i>').addClass('fa fa-external-link fa-xs'))
                        )
                );
            }

            if (this.configurable) {
                $commands.append(
                    $('<div>').addClass('edit-handle').attr('id', `edit-handle-${this.instanceId}`)
                        .hide()
                        .append($('<i>').addClass('fa fa-pencil'))
                );
            }

            if (this.cloneable) {
                $commands.append(
                    $('<div>').addClass('clone-handle').attr({
                        id: `clone-handle-${this.instanceId}`,
                        title: this.duplicateTitle,
                        'data-toggle': 'tooltip'
                    }).hide().append($('<i>').addClass('fa fa-clone'))
                );
            }

            $commands.append(
                $('<div>').addClass('close-handle').attr('id', `close-handle-${this.instanceId}`)
                    .hide()
                    .append($('<i>').addClass('fa fa-times fa-xs'))
            );
            $header.append($commands);

            const $divider = $('<div>').addClass('panel-divider')
                .append($('<div>').addClass('line'));
            if (!this.titleVisible) {
                $header.add($divider).addClass('title-invisible').hide();
            }

            $content.append($header, $divider, content);
            $panel.append($content);
            this.root = $panel;

            return $panel;
        }

        mount(gridElement) {
            const $gridElement = $(gridElement);
            const selector = `[data-widget-instance-id="${CSS.escape(this.instanceId)}"]`;
            this.root = $gridElement.is(selector) ? $gridElement : $gridElement.find(selector).first();

            if (this.root.length === 0) {
                throw new Error(`Widget view not found for instance: ${this.instanceId}`);
            }

            this._control('.clone-handle').tooltip({placement: 'bottom', container: 'body'});

            return this.root;
        }

        onClose(callback) {
            this._control('.close-handle').off('click.widgetView').on('click.widgetView', callback);
        }

        onEdit(callback) {
            this._control('.edit-handle').off('click.widgetView').on('click.widgetView', callback);
        }

        onClone(callback) {
            this._control('.clone-handle').off('click.widgetView').on('click.widgetView', callback);
        }

        setEditMode(enabled) {
            this._control('.close-handle').toggle(enabled);
            this._control('.edit-handle').toggle(enabled);
            this._control('.clone-handle').toggle(enabled);
            this._control('.link-handle').toggle(!enabled);
            this._content().css('cursor', enabled ? 'grab' : 'default');

            const $hiddenTitle = this.root.find('.title-invisible');
            enabled ? $hiddenTitle.css('display', '') : $hiddenTitle.hide();
        }

        showLoading() {
            if (this.root.find('.widget-spinner').length !== 0) {
                return;
            }

            this._divider().after(
                $('<div>').addClass('widget-spinner')
                    .append($('<i>').addClass('fa fa-spinner fa-spin'))
            );
        }

        hideLoading() {
            this.root.find('.widget-spinner').remove();
        }

        showError(message = this.failedMessage) {
            if (this.errorVisible) {
                return;
            }

            this.errorVisible = true;
            this.hideLoading();
            this._divider().nextAll().hide();
            this._divider().after(
                $('<div>').addClass('widget-error')
                    .append($('<i>').addClass('fa fa-exclamation-circle text-danger'))
                    .append($('<br>'))
                    .append(document.createTextNode(message))
            );
        }

        clearError() {
            if (!this.errorVisible) {
                return;
            }

            this.root.find('.widget-error').remove();
            this._divider().nextAll().show();
            this.errorVisible = false;
        }

        setLink(link) {
            this.link = link;
            this._control('.link-handle').find('a').attr('href', link);
            this._control('.link-handle').toggle(link !== '');
        }

        setTitle(title) {
            this.title = title;
            this._content().find('.widget-title b').text(title);
        }

        showOptionsDialog({options, config, title, translations, onSave}) {
            const fields = new Map();
            const $content = $('<div>').addClass('widget-options');

            for (const [key, option] of Object.entries(options)) {
                const $container = $('<div>').addClass('widget-option-container');
                const $field = this._makeOptionField(option, config[key]);
                if (!$field) {
                    console.error(`Unknown widget option type: ${option.type}`);
                    continue;
                }

                if (option.type !== 'textarea') {
                    $container.append($('<div>').append($('<b>').text(option.title)));
                }
                $container.append($field);
                $content.append($container);
                fields.set(key, {option, field: $field});
            }

            for (const {option, field} of fields.values()) {
                if (!option.dependsOn || !fields.has(option.dependsOn)) {
                    continue;
                }

                const parentField = fields.get(option.dependsOn).field;
                const updateOptions = () => {
                    const configuredValue = field.val();
                    const choices = option.optionsByValue?.[parentField.val()] ?? [];
                    field.empty();
                    for (const choice of choices) {
                        field.append($('<option>').val(choice.value).text(choice.label));
                    }
                    const availableValues = choices.map(choice => `${choice.value}`);
                    field.val(availableValues.includes(`${configuredValue}`) ? configuredValue : choices[0]?.value);
                    if (field.data('selectpicker')) {
                        field.selectpicker('refresh');
                    }
                };
                parentField.on('change.widgetOptions', updateOptions);
                updateOptions();
            }

            const hasTextarea = Object.values(options).some(option => option.type === 'textarea');
            return new Promise((resolve, reject) => {
                let settled = false;
                let submitted = false;
                let submittedValues = null;
                BootstrapDialog.show({
                    title,
                    draggable: true,
                    animate: false,
                    message: $content,
                    buttons: [{
                        label: translations.ok,
                        hotkey: hasTextarea ? undefined : 13,
                        action: dialog => {
                            submittedValues = {};
                            for (const [key, {option, field}] of fields) {
                                const value = option.type === 'checkbox' ? field.is(':checked') : field.val();
                                submittedValues[key] = option.type === 'select_multiple'
                                    ? (value?.length ? value : option.default)
                                    : (value ?? option.default);
                            }
                            submitted = true;
                            dialog.close();
                        }
                    }, {
                        label: translations.cancel,
                        action: dialog => dialog.close()
                    }],
                    onshown: () => $content.find('.widget-options-select').selectpicker(),
                    onhide: () => {
                        for (const {field} of fields.values()) {
                            field.off('.widgetOptions');
                        }
                        $content.find('.widget-options-select').selectpicker('destroy');
                        if (!settled && !submitted) {
                            settled = true;
                            resolve(false);
                        }
                    },
                    onhidden: () => {
                        if (!submitted || settled) {
                            return;
                        }

                        Promise.resolve()
                            .then(() => onSave(submittedValues))
                            .then(() => {
                                settled = true;
                                resolve(true);
                            })
                            .catch(error => {
                                settled = true;
                                reject(error);
                            });
                    }
                });
            });
        }

        destroy() {
            if (this.root) {
                this._control('.clone-handle').tooltip('destroy');
                this._control('.close-handle, .edit-handle, .clone-handle').off('.widgetView');
            }
            this.root = null;
            this.errorVisible = false;
        }

        _content() {
            return this.root.children('.widget-content');
        }

        _divider() {
            return this._content().children('.panel-divider');
        }

        _control(selector) {
            return this._content().find(selector);
        }

        _makeOptionField(option, configuredValue) {
            if (option.type === 'select' || option.type === 'select_multiple') {
                const $select = $('<select>').addClass('widget-options-select').attr('data-container', 'body');
                if (option.type === 'select_multiple') {
                    $select.attr('multiple', 'multiple');
                }
                for (const choice of option.options) {
                    $select.append($('<option>').val(choice.value).text(choice.label));
                }
                $select.val(configuredValue ?? option.default);
                return $select;
            }
            if (option.type === 'text') {
                return $('<input>').attr({type: 'text', placeholder: option.placeholder ?? ''})
                    .addClass('form-control').val(configuredValue ?? '');
            }
            if (option.type === 'textarea') {
                return $('<textarea>').attr('maxlength', option.maxlength || 8192).addClass('form-control').css({
                    'max-width': '100%',
                    resize: 'vertical',
                    'min-height': option.minHeight || '150px',
                    'box-sizing': 'border-box'
                }).val(configuredValue || '');
            }
            if (option.type === 'checkbox') {
                return $('<input>').attr('type', 'checkbox').prop('checked', configuredValue ?? option.default ?? false);
            }
            return null;
        }

    }

    // -------------------------
    // WidgetInstance
    // Represents exactly one widget occurrence. It owns the instantiated widget class and transient resources.
    // -------------------------

    class WidgetInstance {
        constructor({
            instanceId,
            typeId,
            WidgetClass,
            metadata = {},
            persistedConfig = {},
            translations = {},
            onResizeRequested = () => {}
        }) {
            if (!instanceId || !typeId || typeof WidgetClass !== 'function') {
                throw new Error('A widget instance requires an instance id, type id and widget class');
            }

            this.id = instanceId;
            this.typeId = typeId;
            this.persistedConfig = {...persistedConfig};
            this.onResizeRequested = onResizeRequested;
            this.element = null;
            this.tickTimer = null;
            this.resizeObserver = null;
            this.resizeTimer = null;
            this.started = false;
            this.destroyed = false;
            this.tickRunning = false;
            this.refreshPending = false;

            const config = {
                ...persistedConfig,
                callbacks: {
                    ...(persistedConfig.callbacks ?? {}),
                    updateGrid: () => this.requestResize(),
                    updateTitle: title => this.view?.setTitle(title)
                }
            };
            this.widget = new WidgetClass(config);
            this.widget.setId(instanceId);
            this.widget.setTranslations(metadata.translations ?? {});

            this.view = new WidgetView({
                instanceId,
                typeId,
                title: metadata.translations?.title ?? typeId,
                link: metadata.link ?? '',
                titleVisible: this.widget.isTitleVisible(),
                configurable: this.widget.isConfigurable(),
                cloneable: metadata.multiple === true,
                duplicateTitle: translations.duplicate ?? '',
                failedMessage: translations.failed ?? ''
            });
        }

        createGridItem(layout = {}) {
            const {
                callbacks,
                content,
                type,
                ...persistedGridConfig
            } = this.persistedConfig;
            const gridOptions = {...this.widget.getGridOptions()};

            if ('sizeToContent' in gridOptions && 'h' in persistedGridConfig) {
                gridOptions.sizeToContent = persistedGridConfig.h;
            }

            return {
                minW: 2,
                ...persistedGridConfig,
                ...gridOptions,
                ...layout,
                id: this.id,
                type: this.typeId,
                content: this.view.render(this.widget.getMarkup()).prop('outerHTML')
            };
        }

        mount(gridElement, {onClose, onEdit, onClone, editMode = false} = {}) {
            if (this.destroyed) {
                throw new Error(`Cannot mount destroyed widget instance: ${this.id}`);
            }

            this.element = gridElement;
            this.view.mount(gridElement);

            if (onClose) {
                this.view.onClose(() => onClose(this.id));
            }
            if (onEdit && this.widget.isConfigurable()) {
                this.view.onEdit(() => onEdit(this.id));
            }
            if (onClone && this.view.cloneable) {
                this.view.onClone(() => onClone(this.id));
            }

            this.setEditMode(editMode);
        }

        async start() {
            if (this.started || this.destroyed) {
                return;
            }
            if (this.element === null) {
                throw new Error(`Cannot start unmounted widget instance: ${this.id}`);
            }

            this.started = true;
            this.view.showLoading();

            try {
                await this.widget.onMarkupRendered();
                if (this.destroyed) {
                    return;
                }

                const observedElement = this.view.root[0].parentElement;
                const bounds = observedElement.getBoundingClientRect();
                if (this.widget.onWidgetResize(this.view.root[0], bounds.width, bounds.height)) {
                    this.requestResize();
                }

                this.view.hideLoading();
                this._startResizeObserver();
                await this.refresh();
            } catch (error) {
                this._handleError(error);
            }
        }

        async serialize() {
            return {
                id: this.id,
                type: this.typeId,
                widget: await this.widget.getWidgetConfig()
            };
        }

        async cloneConfig() {
            return structuredClone(await this.widget.getWidgetConfig());
        }

        async setWidgetConfig(config) {
            this.widget.setWidgetConfig(config);
            await this.widget.onWidgetOptionsChanged(config);
            await this.refresh();
        }

        isConfigurable() {
            return this.widget.isConfigurable();
        }

        async showOptionsDialog(translations) {
            const options = await this.widget.getWidgetOptions();
            const changed = await this.view.showOptionsDialog({
                options,
                config: await this.widget.getWidgetConfig(options),
                title: this.widget.dialogTitle || translations.options,
                translations,
                onSave: config => this.setWidgetConfig(config)
            });

            return changed;
        }

        setEditMode(enabled) {
            this.view.setEditMode(enabled);
            this.widget.onEditModeChanged?.(enabled);
        }

        onVisibilityChanged(visible) {
            if (!this.destroyed) {
                this.widget.onVisibilityChanged(visible);
            }
        }

        getResizeHandles() {
            return this.widget.getResizeHandles();
        }

        requestResize() {
            if (!this.destroyed) {
                this.onResizeRequested(this.id);
            }
        }

        async refresh() {
            if (this.destroyed) {
                return;
            }

            clearTimeout(this.tickTimer);
            this.tickTimer = null;
            if (this.tickRunning) {
                this.refreshPending = true;
                return;
            }

            await this._runTick();
        }

        async destroy() {
            if (this.destroyed) {
                return;
            }

            this.destroyed = true;
            this.started = false;
            clearTimeout(this.tickTimer);
            this.tickTimer = null;
            this.refreshPending = false;
            this.resizeObserver?.disconnect();
            this.resizeObserver = null;
            clearTimeout(this.resizeTimer);
            this.resizeTimer = null;

            try {
                await this.widget.onWidgetClose();
            } finally {
                this.view.destroy();
                this.element = null;
            }
        }

        _startResizeObserver() {
            let lastWidth = null;
            let lastHeight = null;
            const observedElement = this.view.root[0].parentElement;

            this.resizeObserver = new ResizeObserver(entries => {
                clearTimeout(this.resizeTimer);
                this.resizeTimer = setTimeout(() => {
                    if (this.destroyed) {
                        return;
                    }

                    for (const entry of entries) {
                        const size = entry.borderBoxSize?.[0];
                        const width = size ? size.inlineSize : entry.target.getBoundingClientRect().width;
                        const height = entry.contentRect.height;

                        if (width === lastWidth && height === lastHeight) {
                            continue;
                        }
                        lastWidth = width;
                        lastHeight = height;

                        if (this.widget.onWidgetResize(this.view.root[0], width, height)) {
                            this.requestResize();
                        }
                    }
                }, 50);
            });
            this.resizeObserver.observe(observedElement, {box: 'border-box'});
        }

        async _runTick() {
            if (this.destroyed || this.tickRunning) {
                return;
            }

            this.tickRunning = true;
            try {
                await this.widget.onWidgetTick();
                if (!this.destroyed) {
                    this.view.clearError();
                    this.requestResize();
                }
            } catch (error) {
                this._handleError(error);
            } finally {
                this.tickRunning = false;
                if (this.refreshPending && !this.destroyed) {
                    this.refreshPending = false;
                    global.queueMicrotask(() => this.refresh());
                } else if (!this.destroyed) {
                    this.tickTimer = setTimeout(
                        () => this.refresh(),
                        this.widget.tickTimeout * 1000
                    );
                }
            }
        }

        _handleError(error) {
            if (this.destroyed) {
                return;
            }

            console.error(`Failed to load content for widget: ${this.id}, Error:`, error);
            this.view.showError();
            this.requestResize();
        }

    }

    // -------------------------
    // WidgetManager
    // Owns the collection, type registry, GridStack, layouts and user-level operations
    // -------------------------

    class WidgetManager {
        constructor(repository, gridOptions = {}, translations = {}, selectors = {}) {
            if (!(repository instanceof BaseRepository)) {
                throw new Error('WidgetManager requires a repository');
            }

            this.repository = repository;
            this.gridOptions = gridOptions;
            this.translations = translations;
            this.selectors = {
                grid: '.grid-stack',
                controls: '.btn-group-container',
                ...selectors
            };

            this.types = new Map();
            this.instances = new Map();
            this.options = {};
            this.layouts = {};
            this.grid = null;
            this.currentColumnCount = null;
            this.editMode = false;
            this.suppressDirty = false;
            this.controls = {};
            this.pendingResizeIds = new Set();
            this.resizeFrame = null;
            this.gridResizeObserver = null;
            this.gridResizeTimer = null;
            this.lastGridWidth = null;
            this.visibilityHandler = () => this._notifyVisibilityChanged();
        }

        async initialize() {
            this._renderControls();
            this.suppressDirty = true;

            try {
                const dashboard = await this.repository.load();
                this.options = dashboard.options;
                this.layouts = dashboard.layouts;

                await this._loadTypes(dashboard.modules);
                this._initializeGrid();

                await this._initializeInstances(dashboard.widgets);

                document.addEventListener('visibilitychange', this.visibilityHandler);
                this.resizeAll();
            } catch (error) {
                console.error('Failed initializing widgets', error);
            } finally {
                this.suppressDirty = false;
            }
        }

        async addInstance(typeId, persistedConfig = {}, {markDirty = true} = {}) {
            const instance = this._createInstance(typeId, persistedConfig);

            let element = null;
            try {
                const layout = this.layouts[this.currentColumnCount]?.[instance.id] ?? {};
                element = this.grid.addWidget(instance.createGridItem(layout));
                this._mountInstance(instance, element);
                await instance.start();
            } catch (error) {
                this.instances.delete(instance.id);
                await instance.destroy();
                if (element) {
                    this.grid.removeWidget(element);
                }
                throw error;
            }

            if (markDirty) {
                this._markDirty();
            }

            return instance;
        }

        async cloneInstance(sourceId) {
            const source = this.instances.get(sourceId);
            if (!source) {
                throw new Error(`Widget instance not found: ${sourceId}`);
            }
            if (this.types.get(source.typeId)?.multiple !== true) {
                throw new Error(`Widget type is not repeatable: ${source.typeId}`);
            }

            const node = source.element?.gridstackNode ?? {};
            const clone = await this.addInstance(source.typeId, {
                id: this._createInstanceId(source.typeId),
                widget: await source.cloneConfig(),
                w: node.w,
                h: node.h,
                autoPosition: true
            });
            if (clone && node.w) {
                this.grid.update(clone.element, {w: node.w});
            }
            return clone;
        }

        async removeInstance(instanceId) {
            const instance = this.instances.get(instanceId);
            if (!instance) {
                return;
            }

            const element = instance.element;
            await instance.destroy();
            this.instances.delete(instanceId);

            if (element) {
                this.grid.removeWidget(element);
            }
            for (const layout of Object.values(this.layouts)) {
                delete layout[instanceId];
            }
            this._markDirty();
        }

        async editInstance(instanceId) {
            const instance = this.instances.get(instanceId);
            if (instance?.isConfigurable() && await instance.showOptionsDialog(this.translations)) {
                this._markDirty();
            }
        }

        resizeInstance(instanceId) {
            if (!this.instances.has(instanceId)) {
                return;
            }

            this.pendingResizeIds.add(instanceId);
            if (this.resizeFrame === null) {
                this.resizeFrame = global.requestAnimationFrame(() => {
                    const instanceIds = [...this.pendingResizeIds];
                    this.pendingResizeIds.clear();
                    this.resizeFrame = null;

                    for (const id of instanceIds) {
                        const element = this.instances.get(id)?.element;
                        if (element) {
                            this.grid.resizeToContent(element);
                        }
                    }
                });
            }
        }

        resizeAll() {
            for (const instance of this.instances.values()) {
                this.resizeInstance(instance.id);
            }
        }

        setEditMode(enabled) {
            this.editMode = Boolean(enabled);
            this.grid.enableMove(this.editMode);
            this.grid.enableResize(this.editMode);

            for (const instance of this.instances.values()) {
                instance.setEditMode(this.editMode);
            }

            this.controls.edit?.toggleClass('active', this.editMode);
            this.controls.add?.toggle(this.editMode);
            this.controls.restore?.toggle(this.editMode);
            this.resizeAll();
        }

        async save() {
            this._setSaving(true);

            try {
                const gridItems = new Map(
                    this.grid.save(false).map(item => [item.id, item])
                );
                const columnCount = this.grid.getColumn();
                this.layouts[columnCount] = {};

                const widgets = await Promise.all([...this.instances.values()].map(async instance => {
                    const {content, callbacks, ...gridConfig} = gridItems.get(instance.id) ?? {};
                    const serialized = await instance.serialize();
                    this.layouts[columnCount][instance.id] = this._readLayout(instance);

                    return {
                        ...gridConfig,
                        ...serialized
                    };
                }));

                this._pruneLayouts();
                await this.repository.save({
                    options: {...this.options},
                    widgets,
                    layouts: this.layouts
                });
                this._showSaveSuccess();
                this.setEditMode(false);
            } catch (error) {
                console.error('Failed to save widgets', error);
                this._setSaving(false);
                throw error;
            }
        }

        async restoreDefaults() {
            const dashboard = await this.repository.loadDefaults();
            const previousSuppressDirty = this.suppressDirty;
            let restored = false;

            this.suppressDirty = true;
            try {
                await this._loadTypes(dashboard.modules);

                if (this.resizeFrame !== null) {
                    global.cancelAnimationFrame(this.resizeFrame);
                    this.resizeFrame = null;
                }
                this.pendingResizeIds.clear();

                await Promise.all([...this.instances.values()].map(instance => instance.destroy()));
                this.instances.clear();
                this.grid.removeAll();

                this.options = dashboard.options;
                this.layouts = dashboard.layouts;
                await this._initializeInstances(dashboard.widgets);
                this.setEditMode(true);
                restored = true;
            } finally {
                this.suppressDirty = previousSuppressDirty;
                if (restored) {
                    this._markDirty();
                }
            }
        }

        async destroy() {
            document.removeEventListener('visibilitychange', this.visibilityHandler);
            await Promise.all([...this.instances.values()].map(instance => instance.destroy()));
            this.instances.clear();
            this.types.clear();
            this.pendingResizeIds.clear();
            if (this.resizeFrame !== null) {
                global.cancelAnimationFrame(this.resizeFrame);
                this.resizeFrame = null;
            }
            this.gridResizeObserver?.disconnect();
            this.gridResizeObserver = null;
            clearTimeout(this.gridResizeTimer);
            this.gridResizeTimer = null;
            this.grid?.destroy(false);
            this.grid = null;
            for (const control of Object.values(this.controls)) {
                control.off?.('.widgetManager');
            }
        }

        async _loadTypes(modules) {
            const availableTypeIds = new Set(modules.map(metadata => metadata.id));
            for (const typeId of this.types.keys()) {
                if (!availableTypeIds.has(typeId)) {
                    this.types.delete(typeId);
                }
            }

            await Promise.all(modules.map(async metadata => {
                const existingType = this.types.get(metadata.id);
                if (existingType) {
                    this.types.set(metadata.id, {
                        ...metadata,
                        WidgetClass: existingType.WidgetClass
                    });
                    return;
                }

                try {
                    const module = await import(`/ui/js/widgets/${metadata.module}?t=${Date.now()}`);
                    this.types.set(metadata.id, {
                        ...metadata,
                        WidgetClass: module.default
                    });
                } catch (error) {
                    console.error(`Could not import widget module: ${metadata.module}`, error);
                }
            }));
        }

        async _initializeInstances(configurations) {
            const gridItems = [];

            for (const config of configurations) {
                if (!this.types.has(config.type)) {
                    console.error(`Widget type not available: ${config.type}`);
                    continue;
                }

                try {
                    const instance = this._createInstance(config.type, config);
                    const layout = this.layouts[this.currentColumnCount]?.[instance.id] ?? {};
                    gridItems.push(instance.createGridItem(layout));
                } catch (error) {
                    console.error(`Could not prepare widget instance: ${config.id}`, error);
                }
            }

            // Loading the complete set at once lets GridStack resolve the saved
            // coordinates independently of widget startup and content sizing.
            this.grid.load(gridItems);
            const elements = new Map(this.grid.getGridItems().map(element => [element.gridstackNode.id, element]));
            const startTasks = [];

            for (const instance of this.instances.values()) {
                const element = elements.get(instance.id);
                if (!element) {
                    console.error(`Grid element not found for widget instance: ${instance.id}`);
                    await instance.destroy();
                    this.instances.delete(instance.id);
                    continue;
                }

                try {
                    this._mountInstance(instance, element);
                    startTasks.push(instance.start());
                } catch (error) {
                    console.error(`Could not mount widget instance: ${instance.id}`, error);
                    await instance.destroy();
                    this.instances.delete(instance.id);
                    this.grid.removeWidget(element);
                }
            }

            await Promise.all(startTasks);
        }

        _createInstance(typeId, persistedConfig = {}) {
            const type = this.types.get(typeId);
            if (!type) {
                throw new Error(`Widget type not loaded: ${typeId}`);
            }

            const instanceId = persistedConfig.id ?? this._createInstanceId(typeId);
            if (this.instances.has(instanceId)) {
                throw new Error(`Widget instance already exists: ${instanceId}`);
            }

            const instance = new WidgetInstance({
                instanceId,
                typeId,
                WidgetClass: type.WidgetClass,
                metadata: type,
                persistedConfig: {...persistedConfig, id: instanceId, type: typeId},
                translations: this.translations,
                onResizeRequested: id => this.resizeInstance(id)
            });
            this.instances.set(instanceId, instance);
            return instance;
        }

        _mountInstance(instance, element) {
            instance.mount(element, {
                onClose: id => this.removeInstance(id).catch(error =>
                    console.error(`Could not remove widget instance: ${id}`, error)
                ),
                onEdit: id => this.editInstance(id).catch(error =>
                    console.error(`Could not edit widget instance: ${id}`, error)
                ),
                onClone: id => this.cloneInstance(id).catch(error =>
                    console.error(`Could not duplicate widget instance: ${id}`, error)
                ),
                editMode: this.editMode
            });
            this._configureResizeHandles(instance);
        }

        _initializeGrid() {
            this.grid = GridStack.init(this.gridOptions, this.selectors.grid);
            this.currentColumnCount = this.grid.getColumn();
            this.grid.cellHeight(1);

            for (const eventName of ['dragstop', 'dropped', 'removed', 'resizestop']) {
                this.grid.on(eventName, () => this._markDirty());
            }

            this.grid.on('change', () => this._syncResponsiveLayout());

            this.gridResizeObserver = new ResizeObserver(entries => {
                const entry = entries[entries.length - 1];
                const width = entry.contentRect.width;
                if (width === this.lastGridWidth) {
                    return;
                }

                this.lastGridWidth = width;
                clearTimeout(this.gridResizeTimer);
                this.gridResizeTimer = setTimeout(() => {
                    this.grid.checkDynamicColumn();
                    this._syncResponsiveLayout();
                }, this.grid.opts.cellHeightThrottle ?? 100);
            });
            this.gridResizeObserver.observe(this.grid.el);
        }

        _configureResizeHandles(instance) {
            $(instance.element).attr('gs-resize-handles', instance.getResizeHandles());
            instance.element.gridstackNode._initDD = false;
            this.grid.resizable(instance.element, true);
        }

        _applyLayout(columnCount) {
            const layout = this.layouts[columnCount];
            if (!layout) {
                this.grid.compact();
                return;
            }

            const items = [...this.instances.values()].map(instance => ({
                ...(layout[instance.id] ?? this._readLayout(instance)),
                id: instance.id
            }));
            this.grid.load(items);
        }

        _syncResponsiveLayout() {
            const columnCount = this.grid.getColumn();
            if (columnCount === this.currentColumnCount) {
                return;
            }

            this.currentColumnCount = columnCount;
            this._applyLayout(columnCount);
        }

        _readLayout(instance) {
            const $element = $(instance.element);
            const node = instance.element.gridstackNode ?? {};
            const numberAttribute = (name, fallback) => {
                const value = Number.parseInt($element.attr(`gs-${name}`), 10);
                return Number.isFinite(value) ? value : fallback;
            };

            return {
                id: instance.id,
                x: numberAttribute('x', node.x ?? 0),
                y: numberAttribute('y', node.y ?? 0),
                w: numberAttribute('w', node.w ?? 1),
                h: numberAttribute('h', node.h ?? 1)
            };
        }

        _pruneLayouts() {
            const instanceIds = new Set(this.instances.keys());
            for (const layout of Object.values(this.layouts)) {
                for (const instanceId of Object.keys(layout)) {
                    if (!instanceIds.has(instanceId)) {
                        delete layout[instanceId];
                    }
                }
            }
        }

        _createInstanceId(typeId) {
            if (!this.instances.has(typeId)) {
                return typeId;
            }

            const suffix = global.crypto?.randomUUID?.() ??
                `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`;
            return `${typeId}-${suffix}`;
        }

        _notifyVisibilityChanged() {
            const visible = !document.hidden;
            for (const instance of this.instances.values()) {
                instance.onVisibilityChanged(visible);
            }
        }

        _markDirty() {
            if (!this.suppressDirty) {
                this.controls.save?.show();
            }
        }

        _renderControls() {
            const $container = $(this.selectors.controls);
            if ($container.length === 0) {
                return;
            }

            const $saveText = $('<span>').attr('id', 'save-btn-text').text(this.translations.save);
            const $spinner = $('<i>').addClass('fa fa-spinner fa-spin hide').attr('id', 'save-spinner');
            const $check = $('<i>').addClass('fa fa-check checkmark hide').attr('id', 'save-check');
            const $save = $('<button>').addClass('btn btn-primary').attr('id', 'save-grid')
                .append($saveText, $('<span>').attr('id', 'icon-container').append($spinner, $check))
                .hide();
            const $add = this._makeControlButton('add_widget', 'btn btn-default', 'fa fa-plus-circle fa-fw', this.translations.addwidget).hide();
            const $restore = this._makeControlButton('restore-defaults', 'btn btn-secondary', 'fa fa-window-restore fa-fw', this.translations.restore).hide();
            const $edit = this._makeControlButton('edit-grid', 'btn btn-secondary', 'fa fa-pencil fa-fw', this.translations.edit);
            const $group = $('<div>').addClass('btn-group').append($add, $restore, $edit);

            $container.append($save, $group);
            $add.add($restore).add($edit).tooltip({placement: 'bottom', container: 'body'});
            $save.on('click.widgetManager', () => this.save().catch(() => {}));
            $add.on('click.widgetManager', () => this._showAddDialog());
            $restore.on('click.widgetManager', () => this._showRestoreDialog());
            $edit.on('click.widgetManager', () => this.setEditMode(!this.editMode));
            $edit.on('mouseup.widgetManager', event => $(event.currentTarget).blur());

            this.controls = {save: $save, saveText: $saveText, spinner: $spinner, check: $check, add: $add, restore: $restore, edit: $edit};
        }

        _makeControlButton(id, className, iconClass, title) {
            return $('<button>').addClass(className).attr({id, title, 'data-toggle': 'tooltip'})
                .append($('<i>').addClass(iconClass));
        }

        _showAddDialog() {
            const availableTypes = [...this.types.values()]
                .filter(type => type.multiple === true ||
                    ![...this.instances.values()].some(instance => instance.typeId === type.id))
                .sort((left, right) =>
                    (left.translations?.title ?? left.id).localeCompare(right.translations?.title ?? right.id)
                );
            const $select = $('<select>').addClass('selectpicker').attr({
                'data-container': 'body',
                multiple: 'multiple'
            });

            for (const type of availableTypes) {
                const instanceCount = [...this.instances.values()]
                    .filter(instance => instance.typeId === type.id).length;
                const title = type.translations?.title ?? type.id;
                $select.append(
                    $('<option>').val(type.id).text(
                        type.multiple === true && instanceCount > 0 ? `${title} (${instanceCount})` : title
                    )
                );
            }

            let submittedTypeIds = [];
            BootstrapDialog.show({
                title: this.translations.addwidget,
                draggable: true,
                animate: false,
                message: $('<div>').append($select),
                buttons: [{
                    label: this.translations.add,
                    hotkey: 13,
                    action: dialog => {
                        submittedTypeIds = $select.val() ?? [];
                        dialog.close();
                    }
                }, {
                    label: this.translations.cancel,
                    action: dialog => dialog.close()
                }],
                onshown: () => $select.selectpicker(),
                onhide: () => $select.selectpicker('destroy'),
                onhidden: () => {
                    if (submittedTypeIds.length === 0) {
                        return;
                    }

                    Promise.resolve()
                        .then(() => Promise.all(submittedTypeIds.map(typeId => this.addInstance(typeId))))
                        .catch(error => console.error('Could not add widget', error));
                }
            });
        }

        _showRestoreDialog() {
            BootstrapDialog.show({
                title: this.translations.restore,
                draggable: true,
                animate: false,
                message: this.translations.restoreconfirm,
                buttons: [{
                    label: this.translations.ok,
                    hotkey: 13,
                    action: dialog => {
                        dialog.close();
                        this.restoreDefaults().catch(error => {
                            console.error('Failed to restore default widgets', error);
                        });
                    }
                }, {
                    label: this.translations.cancel,
                    action: dialog => dialog.close()
                }]
            });
        }

        _setSaving(saving) {
            this.controls.save?.prop('disabled', saving);
            this.controls.spinner?.toggleClass('hide', !saving).toggleClass('show', saving);
            this.controls.saveText?.toggleClass('hide', saving).toggleClass('show', !saving);
        }

        _showSaveSuccess() {
            this.controls.spinner?.removeClass('show').addClass('hide');
            this.controls.check?.removeClass('hide').addClass('show');
            global.setTimeout(() => {
                this.controls.save?.hide();
                this.controls.check?.removeClass('show').addClass('hide');
                this.controls.saveText?.removeClass('hide').addClass('show');
                this.controls.save?.prop('disabled', false);
            }, 500);
        }
    }

    // -------------------------
    // Public API
    // -------------------------

    global.Widgets = {
        BaseRepository,
        WidgetManager
    }
})(window);
