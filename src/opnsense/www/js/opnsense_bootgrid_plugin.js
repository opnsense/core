/**
 *    Copyright (C) 2015 Deciso B.V.
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 */

/**
 * wrapper around bootgrid component to use our defaults (including scaling footer)
 * @param id
 * @param sourceUrl
 * @param options associative array containing extra bootgrid options or overwrites
 */
function stdBootgridUI(obj, sourceUrl, options) {
    // set defaults our defaults
    var gridopt = {
        ajax: true,
        selection: true,
        multiSelect: true,
        rowCount:[7,14,20,-1],
        url: sourceUrl,
        formatters: {
            "commands": function (column, row) {
                return "<button type=\"button\" class=\"btn btn-xs btn-default command-edit\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-pencil\"></span></button> " +
                    "<button type=\"button\" class=\"btn btn-xs btn-default command-copy\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-clone\"></span></button>" +
                    "<button type=\"button\" class=\"btn btn-xs btn-default command-delete\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-trash-o\"></span></button>";
            },
            "commandsWithInfo": function(column, row) {
                return "<button type=\"button\" class=\"btn btn-xs btn-default command-info\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-info-circle\"></span></button> " +
                    "<button type=\"button\" class=\"btn btn-xs btn-default command-edit\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-pencil\"></span></button>" +
                    "<button type=\"button\" class=\"btn btn-xs btn-default command-copy\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-clone\"></span></button>" +
                    "<button type=\"button\" class=\"btn btn-xs btn-default command-delete\" data-row-id=\"" + row.uuid + "\"><span class=\"fa fa-trash-o\"></span></button>";
            },
            "rowtoggle": function (column, row) {
                if (parseInt(row[column.id], 2) == 1) {
                    return "<span style=\"cursor: pointer;\" class=\"fa fa-check-square-o command-toggle\" data-value=\"1\" data-row-id=\"" + row.uuid + "\"></span>";
                } else {
                    return "<span style=\"cursor: pointer;\" class=\"fa fa-square-o command-toggle\" data-value=\"0\" data-row-id=\"" + row.uuid + "\"></span>";
                }
            },
            "boolean": function (column, row) {
                if (parseInt(row[column.id], 2) == 1) {
                    return "<span class=\"fa fa-check\" data-value=\"1\" data-row-id=\"" + row.uuid + "\"></span>";
                } else {
                    return "<span class=\"fa fa-times\" data-value=\"0\" data-row-id=\"" + row.uuid + "\"></span>";
                }
            },
        }
    };

    // merge additional options (if any)
    if (options != undefined) {
        $.each(options,  function(key, value) {
            gridopt[key] = value;
        });
    }

    // construct a new grid
    var grid = obj.bootgrid(gridopt).on("loaded.rs.jquery.bootgrid", function (e)
    {
        // scale footer on resize
        $(this).find("tfoot td:first-child").attr('colspan',$(this).find("th").length - 1);
        // invert colors if needed (check if there is a disabled field instead of an enabled field
        var inverted = $(this).find("thead th[data-column-id=disabled]").length > 0;
        $(this).find('tr[data-row-id]').each(function(index, entry){
            ['[class*="command-toggle"]', '[class*="command-boolean"]'].forEach(function (selector) {
                var selected_element = $(entry).find(selector).first();
                if (selected_element.length > 0) {
                    if ((selected_element.data("value") == "0") != inverted ) {
                        $(entry).addClass("text-muted");
                    }
                }
            });
        });

    })

    return grid;
}

/**
 * reload bootgrid, return to current selected page
 */
function std_bootgrid_reload(gridId) {
    var currentpage = $("#"+gridId).bootgrid("getCurrentPage");
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
    return this.each((function(){
        var gridId = $(this).attr('id');
        var gridParams = params;
        if (gridParams != undefined) {
            if (gridParams['search'] != undefined) {
                // create new bootgrid component and link source
                var grid = stdBootgridUI($(this), gridParams['search'],gridParams['options']);

                // edit dialog id to use ( see base_dialog.volt template for details)
                var editDlg = $(this).attr('data-editDialog');

                // link edit and delete event buttons
                grid.on("loaded.rs.jquery.bootgrid", function(){

                    // info item
                    grid.find(".command-info").on("click", function(e) {
                        if(gridParams['info'] != undefined) {
                            var uuid=$(this).data("row-id");
                            ajaxGet(url=gridParams['info'] + uuid,
                                sendData={}, callback=function(data, status) {
                                    if(status == 'success') {
                                        var title = data['title'] || "Information";
                                        var message = data['message'] || "A Message";
                                        var close = data['close'] || "Close";
                                        stdDialogInform(title, message, close, undefined, "info");
                                    }
                                });
                        } else {
                            console.log("[grid] action info missing");
                        }
                    }).end();

                    // edit item
                    grid.find(".command-edit").on("click", function(e)
                    {
                        if (editDlg != undefined && gridParams['get'] != undefined) {
                            var uuid = $(this).data("row-id");
                            var urlMap = {};
                            urlMap['frm_' + editDlg] = gridParams['get'] + uuid;
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
                                    if (gridParams['set'] != undefined) {
                                        saveFormToEndpoint(url=gridParams['set']+uuid,
                                            formid='frm_' + editDlg, callback_ok=function(){
                                                $("#"+editDlg).modal('hide');
                                                std_bootgrid_reload(gridId);
                                            }, true);
                                    } else {
                                        console.log("[grid] action set missing")
                                    }
                                });
                                $('#'+editDlg).trigger('opnsense_bootgrid_mapped', ['edit']);
                            });
                        } else {
                            console.log("[grid] action get or data-editDialog missing")
                        }
                    }).end();

                    // copy item, save as new
                    grid.find(".command-copy").on("click", function(e)
                    {
                        if (editDlg != undefined && gridParams['get'] != undefined) {
                            var uuid = $(this).data("row-id");
                            var urlMap = {};
                            urlMap['frm_' + editDlg] = gridParams['get'] + uuid;
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
                                    if (gridParams['add'] != undefined) {
                                        saveFormToEndpoint(url=gridParams['add'],
                                            formid='frm_' + editDlg, callback_ok=function(){
                                                $("#"+editDlg).modal('hide');
                                                std_bootgrid_reload(gridId);
                                            }, true);
                                    } else {
                                        console.log("[grid] action add missing")
                                    }
                                });
                                $('#'+editDlg).trigger('opnsense_bootgrid_mapped', ['copy']);
                            });
                        } else {
                            console.log("[grid] action get or data-editDialog missing")
                        }
                    }).end();


                    // delete item
                    grid.find(".command-delete").on("click", function(e)
                    {
                        if (gridParams['del'] != undefined) {
                            var uuid=$(this).data("row-id");
                            // XXX must be replaced, cannot translate
                            stdDialogRemoveItem('Remove selected item?',function() {
                                ajaxCall(url=gridParams['del'] + uuid,
                                    sendData={},callback=function(data,status){
                                        // reload grid after delete
                                        $("#"+gridId).bootgrid("reload");
                                    });
                            });
                        } else {
                            console.log("[grid] action del missing")
                        }
                    }).end();

                    // toggle item
                    grid.find(".command-toggle").on("click", function(e)
                    {
                        if (gridParams['toggle'] != undefined) {
                            var uuid=$(this).data("row-id");
                            $(this).addClass("fa-spinner fa-pulse");
                            ajaxCall(url=gridParams['toggle'] + uuid,
                                sendData={},callback=function(data,status){
                                    // reload grid after delete
                                    std_bootgrid_reload(gridId);
                                });
                        } else {
                            console.log("[grid] action toggle missing")
                        }
                    }).end();
                });

                // link Add new to child button with data-action = add
                $(this).find("*[data-action=add]").click(function(){
                    if ( gridParams['get'] != undefined && gridParams['add'] != undefined) {
                        var urlMap = {};
                        urlMap['frm_' + editDlg] = gridParams['get'];
                        mapDataToFormUI(urlMap).done(function(){
                            // update selectors
                            formatTokenizersUI();
                            $('.selectpicker').selectpicker('refresh');
                            // clear validation errors (if any)
                            clearFormValidation('frm_' + editDlg);
                            $('#'+editDlg).trigger('opnsense_bootgrid_mapped', ['add']);
                        });

                        // show dialog for edit
                        $('#'+editDlg).modal({backdrop: 'static', keyboard: false});
                        //
                        $("#btn_"+editDlg+"_save").unbind('click').click(function(){
                            saveFormToEndpoint(url=gridParams['add'],
                                formid='frm_' + editDlg, callback_ok=function(){
                                    $("#"+editDlg).modal('hide');
                                    $("#"+gridId).bootgrid("reload");
                                }, true);
                        });
                    }  else {
                        console.log("[grid] action add missing")
                    }
                });

                // link delete selected items action
                $(this).find("*[data-action=deleteSelected]").click(function(){
                    if ( gridParams['del'] != undefined) {
                        // XXX must be replaced, cannot translate
                        stdDialogRemoveItem("Remove selected items?",function(){
                            var rows =$("#"+gridId).bootgrid('getSelectedRows');
                            if (rows != undefined){
                                var deferreds = [];
                                $.each(rows, function(key,uuid){
                                    deferreds.push(ajaxCall(url=gridParams['del'] + uuid, sendData={},null));
                                });
                                // refresh after load
                                $.when.apply(null, deferreds).done(function(){
                                    std_bootgrid_reload(gridId);
                                });
                            }
                        });
                    } else {
                        console.log("[grid] action del missing")
                    }
                });

                return grid;
            }
        }
    }));
};
