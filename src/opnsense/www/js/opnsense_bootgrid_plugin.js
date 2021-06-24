/*
 * Copyright (C) 2015-2021 Deciso B.V.
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
 * reload bootgrid, return to current selected page
 */
function std_bootgrid_reload(gridId) {
    let currentpage = $("#" + gridId).bootgrid("getCurrentPage");
    $("#"+gridId).bootgrid("reload");
    // absolutely not perfect, bootgrid.reload doesn't seem to support when().done()
    setTimeout(function(){
        $('#'+gridId+'-footer  a[data-page="'+currentpage+'"]').click();
    }, 400);
}


/**
 * creates new bootgrid object and links actions to our standard templates
 * uses the following data properties to define functionality:
 *      data-editDialog : id of the edit dialog to use (  see base_dialog.volt template for details )
 *      data-action [add] : set data-action "add" to create a new record
 *      data-action [deleteSelected]: set data-action "deleteSelected" to delete selected items
 *
 * and uses the following properties (params array):
 *  search  : url to search action (GET)
 *  get     : url to get data action (GET) will be suffixed by uuid
 *  set     : url to set data action (POST) will be suffixed by uuid
 *  add     : url to create a new data record (POST)
 *  del     : url to del item action (POST) will be suffixed by uuid
 *  info    : url to get data action that will be displayed informationally suffixed by the uuid
 *
 * @param params
 * @returns {*}
 * @constructor
 */
$.fn.UIBootgrid = function (params) {
    let this_grid = this;

    /**
     *  register commands
     */
    this.getCommands = function() {
        return {
            "command-add": {
                method: this_grid.command_add,
                requires: ['get', 'set']
            },
            "command-edit": {
                method: this_grid.command_edit,
                requires: ['get', 'set']
            },
            "command-delete": {
                method: this_grid.command_delete,
                requires: ['del']
            },
            "command-copy": {
                method: this_grid.command_copy,
                requires: ['get', 'set']
            },
            "command-info": {
                method: this_grid.command_info,
                requires: ['get']
            },
            "command-toggle": {
                method: this_grid.command_toggle,
                requires: ['toggle']
            },
            "command-delete-selected": {
                method: this_grid.command_delete_selected,
                requires: ['del']
            }
        };
    };

    /**
     * construct new bootgrid
     */
    this.construct = function() {
        // set defaults
        let gridopt = {
            ajax: true,
            selection: true,
            multiSelect: true,
            rowCount:[7,14,20,50,100,-1],
            url: params['search'],
            useRequestHandlerOnGet: false,
            formatters: {
                "commands": function (column, row) {
                    return '<button type="button" class="btn btn-xs btn-default command-edit bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-pencil"></span></button> ' +
                        '<button type="button" class="btn btn-xs btn-default command-copy bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-clone"></span></button>' +
                        '<button type="button" class="btn btn-xs btn-default command-delete bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-trash-o"></span></button>';
                },
                "commandsWithInfo": function(column, row) {
                    return '<button type="button" class="btn btn-xs btn-default command-info bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-info-circle"></span></button> ' +
                        '<button type="button" class="btn btn-xs btn-default command-edit bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-pencil"></span></button>' +
                        '<button type="button" class="btn btn-xs btn-default command-copy bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-clone"></span></button>' +
                        '<button type="button" class="btn btn-xs btn-default command-delete bootgrid-tooltip" data-row-id="' + row.uuid + '"><span class="fa fa-fw fa-trash-o"></span></button>';
                },
                "rowtoggle": function (column, row) {
                    if (parseInt(row[column.id], 2) === 1) {
                        return '<span style="cursor: pointer;" class="fa fa-fw fa-check-square-o command-toggle bootgrid-tooltip" data-value="1" data-row-id="' + row.uuid + '"></span>';
                    } else {
                        return '<span style="cursor: pointer;" class="fa fa-fw fa-square-o command-toggle bootgrid-tooltip" data-value="0" data-row-id="' + row.uuid + '"></span>';
                    }
                },
                "boolean": function (column, row) {
                    if (parseInt(row[column.id], 2) === 1) {
                        return "<span class=\"fa fa-fw fa-check\" data-value=\"1\" data-row-id=\"" + row.uuid + "\"></span>";
                    } else {
                        return "<span class=\"fa fa-fw fa-times\" data-value=\"0\" data-row-id=\"" + row.uuid + "\"></span>";
                    }
                },
            },
            onBeforeRenderDialog: null
        };

        // merge additional options (if any)
        if (params['options'] !== undefined) {
            $.each(params['options'],  function(key, value) {
                gridopt[key] = value;
            });
        }

        if (gridopt.useRequestHandlerOnGet) {
            this_grid.requestHandler = gridopt.requestHandler;
        } else {
            this_grid.requestHandler = null;
        }
        this_grid.onBeforeRenderDialog = gridopt.onBeforeRenderDialog;

        if ($(this_grid).data('store-selection') === true && window.localStorage) {
            // fetch last selected rowcount, sort on top so it will be the current active selection
            let grid_id = window.location.pathname + '#' + this_grid.attr('id');
            let count = parseInt(window.localStorage.getItem(grid_id+"_items")) ;
            if (count !== null) {
                if (Array.isArray(gridopt.rowCount)) {
                    let index = gridopt.rowCount.indexOf(count);
                    if (index > -1) {
                        gridopt.rowCount.splice(index, 1);
                        gridopt.rowCount.unshift(count);
                    }
                }
            }
        }

        // construct a new grid
        return this_grid.bootgrid(gridopt).on("loaded.rs.jquery.bootgrid", function (e) {
            // scale footer on resize
            $(this).find("tfoot td:first-child").attr('colspan',$(this).find("th").length - 1);
            // invert colors if needed (check if there is a disabled field instead of an enabled field
            let inverted = $(this).find("thead th[data-column-id=disabled]").length > 0;
            $(this).find('tr[data-row-id]').each(function(index, entry){
                ['[class*="command-toggle"]', '[class*="command-boolean"]'].forEach(function (selector) {
                    let selected_element = $(entry).find(selector).first();
                    if (selected_element.length > 0) {
                        if ((selected_element.data("value") == "0") !== inverted ) {
                            $(entry).addClass("text-muted");
                        }
                    }
                });
            });

        });
    };

    this.show_edit_dialog = function(event, endpoint) {
        const dfObj = new $.Deferred();
        let editDlg = this_grid.attr('data-editDialog');
        let urlMap = {};
        let server_params = undefined;

        urlMap['frm_' + editDlg] = endpoint;
        if (this_grid.requestHandler !== null) {
            // our requestHandler returns a JSON object, convert it back first
            server_params = this_grid.requestHandler({});
        }
        mapDataToFormUI(urlMap, server_params).done(function(payload){
            // update selectors
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
            // clear validation errors (if any)
            clearFormValidation('frm_' + editDlg);
            // show dialog
            $('#'+editDlg).modal({backdrop: 'static', keyboard: false});
            if (this_grid.onBeforeRenderDialog) {
                this_grid.onBeforeRenderDialog(payload).done(function(){
                    dfObj.resolve();
                });
            } else {
                dfObj.resolve();
            }
        });
        return dfObj;
    };

    /**
     * add event
     */
    this.command_add = function(event) {
        let editDlg = this_grid.attr('data-editDialog');
        if (editDlg !== undefined) {
            let saveDlg = $("#btn_"+editDlg+"_save").unbind('click');
            this_grid.show_edit_dialog(event, params['get']).done(function(){
                $('#'+editDlg).trigger('opnsense_bootgrid_mapped', ['add']);
                saveDlg.click(function(){
                    saveFormToEndpoint(params['add'], 'frm_' + editDlg, function(){
                            $("#"+editDlg).modal('hide');
                            std_bootgrid_reload(this_grid.attr('id'));
                            this_grid.showSaveAlert(event);
                        }, true);
                });
            });
        } else {
            console.log("[grid] action get or data-editDialog missing")
        }
    };

    /**
     * animate alert when saved
     */
    this.showSaveAlert = function(event) {
        let editAlert = this_grid.attr('data-editAlert');
        if (editAlert !== undefined) {
            $("#"+editAlert).slideDown(1000, function(){
                setTimeout(function(){
                    $("#"+editAlert).slideUp(2000);
                }, 2000);
            });
        }
    };

    /**
     * edit event
     */
    this.command_edit = function(event) {
        let editDlg = this_grid.attr('data-editDialog');
        if (editDlg !== undefined) {
            let uuid = $(this).data("row-id");
            let saveDlg = $("#btn_"+editDlg+"_save").unbind('click');
            this_grid.show_edit_dialog(event, params['get'] + uuid).done(function(){
                saveDlg.unbind('click').click(function(){
                    saveFormToEndpoint(params['set']+uuid, 'frm_' + editDlg, function(){
                            $("#"+editDlg).modal('hide');
                            std_bootgrid_reload(this_grid.attr('id'));
                            this_grid.showSaveAlert(event);
                        }, true);
                });
                $('#'+editDlg).trigger('opnsense_bootgrid_mapped', ['edit']);
            });
        } else {
            console.log("[grid] action get or data-editDialog missing")
        }
    };

    /**
     * delete event
     */
    this.command_delete = function(event) {
        let uuid=$(this).data("row-id");
        stdDialogRemoveItem($.fn.UIBootgrid.defaults.removeWarningText,function() {
            ajaxCall(params['del'] + uuid, {},function(data,status){
                // reload grid after delete
                std_bootgrid_reload(this_grid.attr('id'));
            });
        });
    };

    /**
     * delete selected event
     */
    this.command_delete_selected = function(event) {
        stdDialogRemoveItem($.fn.UIBootgrid.defaults.removeWarningText,function(){
            const rows = $("#" + this_grid.attr('id')).bootgrid('getSelectedRows');
            if (rows !== undefined){
                const deferreds = [];
                $.each(rows, function(key,uuid){
                    deferreds.push(ajaxCall(params['del'] + uuid, {},null));
                });
                // refresh after load
                $.when.apply(null, deferreds).done(function(){
                    std_bootgrid_reload(this_grid.attr('id'));
                });
            }
        });
    };

    /**
     * copy event
     */
    this.command_copy = function(event) {
        const editDlg = this_grid.attr('data-editDialog');
        if (editDlg !== undefined) {
            const uuid = $(this).data("row-id");
            const urlMap = {};
            urlMap['frm_' + editDlg] = params['get'] + uuid + "?fetchmode=copy";
            mapDataToFormUI(urlMap).done(function () {
                // update selectors
                formatTokenizersUI();
                $('.selectpicker').selectpicker('refresh');
                // clear validation errors (if any)
                clearFormValidation('frm_' + editDlg);

                // show dialog for pipe edit
                $('#'+editDlg).modal({backdrop: 'static', keyboard: false});
                // define save action
                $("#btn_"+editDlg+"_save").unbind('click').click(function(){
                    saveFormToEndpoint(params['add'], 'frm_' + editDlg, function(){
                            $("#"+editDlg).modal('hide');
                            std_bootgrid_reload(this_grid.attr('id'));
                            this_grid.showSaveAlert(event);
                        }, true);
                });
                $('#'+editDlg).trigger('opnsense_bootgrid_mapped', ['copy']);
            });
        } else {
            console.log("[grid] action get or data-editDialog missing")
        }
    };

    /**
     * info event
     */
    this.command_info = function(event) {
        const uuid = $(this).data("row-id");
        ajaxGet(params['info'] + uuid, {}, function(data, status) {
            if(status === 'success') {
                const title = data['title'] || "Information";
                const message = data['message'] || "A Message";
                const close = data['close'] || "Close";
                stdDialogInform(title, message, close, undefined, "info");
            }
        });
    };

    /**
     * toggle event
     */
    this.command_toggle = function(event) {
        const uuid = $(this).data("row-id");
        $(this).addClass("fa-spinner fa-pulse");
        ajaxCall(params['toggle'] + uuid, {},function(data,status){
            // reload grid after delete
            std_bootgrid_reload(this_grid.attr('id'));
        });
    };

    /**
     * load previous selections
     */
    this.load_selection = function() {
        if ($(this_grid).data('store-selection') === true && window.localStorage) {
            const grid_id = window.location.pathname + '#' + this_grid.attr('id');
            try {
                const settings = JSON.parse(window.localStorage.getItem(grid_id));
                if (settings != null) {
                    $.each(settings, function(field, value){
                        $('#'+ this_grid.attr('id')).find('[data-column-id="' +field+ '"]').data('visible', value);
                    });
                }
            } catch (e) {
            }
        }
    };

    /**
     * store selections when data-store-selection=true
     */
    this.store_selection = function() {
        if ($(this_grid).data('store-selection') === true && window.localStorage) {
            const grid_id = window.location.pathname + '#' + this_grid.attr('id');
            // hook event handler to catch changing column selections
            $("#"+this_grid.attr('id')+"-header .dropdown-item-checkbox").unbind('click').click(function () {
                let settings = {};
                try {
                    settings = JSON.parse(window.localStorage.getItem(grid_id));
                    if (settings == null) {
                        settings = {};
                    }
                } catch (e) {
                    settings = {};
                }
                if ($(this).attr('name') !== undefined) {
                    settings[$(this).attr('name')] = $(this).is(':checked');
                }
                window.localStorage.setItem(grid_id, JSON.stringify(settings));
            });
            // hook event handler to catch changing row counters
            $("#"+this_grid.attr('id')+"-header .dropdown-item-button").unbind('click').click(function () {
                window.localStorage.setItem(grid_id+"_items", $(this).data('action'));
            });
        }
    };

    /**
     * init bootgrids
     */
    return this.each((function(){
        // since we start using simple class selectors for our commands, we need to make sure "add" and
        // "delete selected" actions are properly marked
        $(this).find("*[data-action=add]").addClass('command-add bootgrid-tooltip');
        $(this).find("*[data-action=deleteSelected]").addClass('command-delete-selected bootgrid-tooltip');

        if (params !== undefined && params['search'] !== undefined) {
            // load previous selections when enabled
            this_grid.load_selection();
            // create new bootgrid component and link source
            const grid = this_grid.construct();

            // edit dialog id to use ( see base_dialog.volt template for details)
            const editDlg = $(this).attr('data-editDialog');

            // link edit and delete event buttons
            grid.on("loaded.rs.jquery.bootgrid", function(){
                // toggle all automated tooltips
                $(this).find(".bootgrid-tooltip").each(function (index) {
                    if ($(this).attr('title') !== undefined) {
                        // keep this tooltip
                    } else if ($(this).hasClass('command-add')) {
                        $(this).attr('title', $.fn.UIBootgrid.defaults.addText);
                    } else if ($(this).hasClass('command-delete-selected')) {
                        $(this).attr('title', $.fn.UIBootgrid.defaults.deleteSelectedText);
                    } else if ($(this).hasClass('command-edit')) {
                        $(this).attr('title', $.fn.UIBootgrid.defaults.editText);
                    } else if ($(this).hasClass('command-toggle')) {
                        if ($(this).data('value') == 1) {
                            $(this).attr('title', $.fn.UIBootgrid.defaults.disableText);
                        } else {
                            $(this).attr('title', $.fn.UIBootgrid.defaults.enableText);
                        }
                    } else if ($(this).hasClass('command-delete')) {
                        $(this).attr('title', $.fn.UIBootgrid.defaults.deleteText);
                    } else if ($(this).hasClass('command-info')) {
                        $(this).attr('title', $.fn.UIBootgrid.defaults.infoText);
                    } else if ($(this).hasClass('command-copy')) {
                        $(this).attr('title', $.fn.UIBootgrid.defaults.cloneText);
                    } else {
                        $(this).attr('title', 'Error: no tooltip match');
                    }
                    $(this).tooltip();
                });

                // hook all events
                const commands = this_grid.getCommands();
                Object.keys(commands).map(function (k) {
                    let has_option = true;
                    for (let i=0; i < commands[k]['requires'].length; i++) {
                        if (!(commands[k]['requires'][i] in params)) {
                            has_option = false;
                        }
                    }
                    if (has_option) {
                        grid.find("."+k).unbind('click').on("click", commands[k].method);
                    } else if ($("."+k).length > 0) {
                        console.log("not all requirements met to link " + k);
                    }
                });
                // store selections when enabled
                this_grid.store_selection();
            });

            return grid;
        }
    }));
};

$.fn.UIBootgrid.defaults = { /* translations are rendered in the default page template */ };
