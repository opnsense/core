/*
 * Copyright (C) 2015-2024 Deciso B.V.
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
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *
 * User interface shared components, requires opnsense.js for supporting functions.
 */

 /**
  * format bytes
  * @param bytes number of bytes to format
  * @param decimals decimal places
  * @return string
  */
 function byteFormat(bytes, decimals)
 {
     if (decimals === undefined) {
        decimals = 0;
     }
     const kb = 1024;
     const ndx = bytes === 0 ? 0 : Math.floor(Math.log(bytes) / Math.log(kb));
     const fileSizeTypes = ["Bytes", "KB", "MB", "GB", "TB", "PB", "EB", "ZB", "YB"];
     return (bytes / Math.pow(kb, ndx)).toFixed(decimals) + ' ' + fileSizeTypes[ndx];
 }

/**
 * save form to server
 * @param url endpoint url
 * @param formid parent id to grep input data from
 * @param callback_ok
 * @param disable_dialog don't show input validation message box on failure
 * @param callback_fail
 */
function saveFormToEndpoint(url, formid, callback_ok, disable_dialog, callback_fail)
{
    disable_dialog = disable_dialog || false;
    const data = getFormData(formid);
    ajaxCall(url, data, function (data, status) {
        if ( status === "success" ) {
            // update field validation
            handleFormValidation(formid, data['validations']);

            // if there are validation issues, update our screen and show a dialog.
            if (data['validations'] !== undefined) {
                if (!disable_dialog) {
                    const detailsid = "errorfrm" + Math.floor((Math.random() * 10000000) + 1);
                    const errorMessage = $('<div></div>');
                    errorMessage.append('Please correct validation errors in form <br />');
                    errorMessage.append('<i class="fa fa-bug pull-right" aria-hidden="true" data-toggle="collapse" '+
                                        'data-target="#'+detailsid+'" aria-expanded="false" aria-controls="'+detailsid+'"></i>');
                    errorMessage.append('<div class="collapse" id="'+detailsid+'"><hr/><pre></pre></div>');

                    // validation message box is optional, form is already updated using handleFormValidation
                    BootstrapDialog.show({
                        type:BootstrapDialog.TYPE_WARNING,
                        title: 'Input validation',
                        message: errorMessage,
                        buttons: [{
                            label: 'Dismiss',
                            action: function(dialogRef){
                                dialogRef.close();
                            }
                        }],
                        onshown: function(){
                            // set debug information
                            $("#"+detailsid + " > pre").html(JSON.stringify(data, null, 2));
                        }
                    });
                }

                if ( callback_fail !== undefined ) {
                    // execute callback function
                    callback_fail(data);
                }
            } else if ( callback_ok !== undefined ) {
                // execute callback function
                callback_ok(data);
            }
        } else if ( callback_fail !== undefined ) {
            callback_fail(data);
        }
    });
}

/**
 * standard data mapper to map json request data to forms on this page
 * @param data_get_map named array containing form names and source url's to get data from {'frm_example':"/api/example/settings/get"};
 * @param server_params parameters to send to server
 * @return promise object, resolves when all are loaded
 */
function mapDataToFormUI(data_get_map, server_params) {
    const dfObj = new $.Deferred();

    // calculate number of items for deferred object to resolve
    let data_map_seq = 1;
    let data_map_count = 0;
    $.each(data_get_map, function(){
        data_map_count += 1;
    });

    if (server_params === undefined) {
        server_params = {};
    }

    const collected_data = {};
    $.each(data_get_map, function(data_index, data_url) {
        ajaxGet(data_url,server_params , function(data, status) {
            if (status === "success") {
                $("form").each(function() {
                    if ( $(this).attr('id') && $(this).attr('id').split('-')[0] === data_index) {
                        // related form found, load data
                        setFormData($(this).attr('id'), data);
                        collected_data[$(this).attr('id')] = data;
                    }
                });
            }
            if (data_map_seq === data_map_count) {
                dfObj.resolve(collected_data);
            }
            data_map_seq += 1;
        });
    });

    return dfObj;
}

/**
 * update service status buttons in user interface
 */
function updateServiceStatusUI(status)
{
    let status_html = '<span class="label label-opnsense label-opnsense-sm ';

    if (status === "running") {
        status_html += 'label-success';
    } else if (status === "stopped") {
        status_html += 'label-danger';
    } else {
        status_html += 'hidden';
    }

    status_html += '"><i class="fa fa-play fa-fw"></i></span>';

    $('#service_status_container').html(status_html);
}

/**
 * operate service status buttons in user interface
 */
function updateServiceControlUI(serviceName)
{
    if (serviceName == '') {
        return;
    }

    ajaxCall("/api/" + serviceName + "/service/status", {}, function(data) {
        let status_html = '<span class="label label-opnsense label-opnsense-sm ';
        let status_icon = '';
        let buttons = '';

        if (data['status'] === "running") {
            status_html += 'label-success';
            status_icon = 'play';
            buttons += '<span id="restartService" class="btn btn-sm btn-default"><i class="fa fa-repeat fa-fw"></i></span>';
            buttons += '<span id="stopService" class="btn btn-sm btn-default"><i class="fa fa-stop fa-fw"></span>';
        } else if (data['status'] === "stopped") {
            status_html += 'label-danger';
            status_icon = 'stop';
            buttons += '<span id="startService" class="btn btn-sm btn-default"><i class="fa fa-play fa-fw"></i></span>';
        } else {
            status_html += 'hidden';
        }

        status_html += '"><i class="fa fa-' + status_icon + ' fa-fw"></i></span>';

        $('#service_status_container').html(status_html + " " + buttons);

        if (data['widget'] !== undefined) {
            // tooltip service action widgets
            ['stop', 'start', 'restart'].forEach(function(action){
                let obj = $("#" + action + "Service");
                if (obj.length > 0) {
                    obj.tooltip({
                        'placement': 'bottom',
                        'title': data['widget']['caption_' + action]
                    });
                }
            });
        }

        const commands = ["start", "restart", "stop"];
        commands.forEach(function(command) {
            $("#" + command + "Service").click(function(){
                $('#OPNsenseStdWaitDialog').modal('show');
                ajaxCall("/api/" + serviceName + "/service/" + command, {},function() {
                    $('#OPNsenseStdWaitDialog').modal('hide');
                    ajaxCall("/api/" + serviceName + "/service/status", {}, function() {
                        updateServiceControlUI(serviceName);
                    });
                });
            });
        });
    });
}

/**
 * reformat all tokenizers on this document
 */
function formatTokenizersUI() {
    $('select.tokenize').each(function () {
        const sender = $(this);
        if (!sender.hasClass('tokenize2_init_done')) {
            // only init tokenize2 when not bound yet
            const hint = $(this).data("hint");
            const width = $(this).data("width");
            let number_of_items = 10;
            if (sender.data("size") !== undefined) {
                number_of_items = $(this).data("size");
            }
            sender.tokenize2({
                'tokensAllowCustom': $(this).data("allownew"),
                'placeholder': hint,
                'sortable': $(this).data('sortable') === true,
                'dropdownMaxItems': number_of_items
            });
            sender.parent().find('ul.tokens-container').css("width", width);

            // dropdown on focus (previous displayDropdownOnFocus)
            sender.on('tokenize:select', function(){
                $(this).tokenize2().trigger('tokenize:search', [$(this).tokenize2().input.val()]);
            });
            // bind add / remove events
            sender.on('tokenize:tokens:add', function(){
                sender.trigger("tokenize:tokens:change");
            });
            sender.on('tokenize:tokens:remove', function(){
                sender.trigger("tokenize:tokens:change");
            });

            // hook keydown -> tab to blur event
            sender.on('tokenize:deselect', function(){
                const e = $.Event("keydown");
                e.keyCode = 9;
                sender.tokenize2().trigger('tokenize:keydown', [e]);
            });

            sender.addClass('tokenize2_init_done');
        } else {
            // unbind change event while loading initial content
            sender.unbind('tokenize:tokens:change');

            // selected items
            const items = [];
            sender.find('option:selected').each(function () {
                items.push([$(this).val(), $(this).text()]);
            });

            // re-init tokenizer items
            sender.tokenize2().trigger('tokenize:clear');
            for (let i=0 ; i < items.length ; ++i) {
                sender.tokenize2().trigger('tokenize:tokens:add', items[i]);
            }
            sender.tokenize2().trigger('tokenize:select');
            sender.tokenize2().trigger('tokenize:dropdown:hide');
        }

        // propagate token changes to parent change()
        sender.on('tokenize:tokens:change', function(){
            sender.change();
        });

    });
}

/**
 * clear multiselect boxes on click event, works on standard and tokenized versions
 */
function addMultiSelectClearUI() {
    //enable Paste if supported
    if ((typeof navigator.clipboard === 'object') && (typeof navigator.clipboard.readText === 'function')) {
        $('.fa-paste').parent().show();
    }
    $('[id*="clear-options"]').each(function() {
        $(this).click(function() {
            const id = $(this).attr("id").replace(/_*clear-options_*/, '');
            let element = $('select[id="' + id + '"]');
            if (element.hasClass("tokenize")) {
                // trigger close on all Tokens
                element.tokenize2().trigger('tokenize:clear');
                element.change();
            } else {
                // remove options from selection
                element.find('option').prop('selected',false);
                if (element.hasClass('selectpicker')) {
                    element.selectpicker('refresh');
                }
            }
        });
    });
    $('[id*="select-options"]').each(function() {
        const id = $(this).attr("id").replace(/_*select-options_*/, '');
        $(this).click(function() {
            let element = $('select[id="' + id + '"]');
            element.find('option').prop('selected', true);
            if (element.hasClass('selectpicker')) {
                element.selectpicker('refresh');
            }
        });
    });
    $('[id*="copy-options"]').each(function() {
        $(this).click(function(e) {
            e.preventDefault();
            var currentFocus = document.activeElement;
            let src_id = $(this).attr("id").replace(/_*copy-options_*/, '');
            let element = $('select[id="' + src_id + '"]');
            let target = $("<textarea style='opacity:0;'/>").val(element.val().join('\n')) ;
            element.after(target);
            target.select().focus();
            document.execCommand("copy");
            target.remove();
            if (currentFocus && typeof currentFocus.focus === "function") {
                currentFocus.focus();
            }
        });
    });
    $('[id*="paste-options"]').each(function() {
        $(this).click(function(e) {
            e.preventDefault();
            let id = $(this).attr("id").replace(/_*paste-options_*/, '');
            let target = $('select[id="' + id + '"]');
            var cpb = navigator.clipboard.readText();
            $.when(cpb).then(function(cbtext) {
                let values = $.trim(cbtext).replace(/\n|\r/g, ",").split(",");
                $.each(values, function( index, value ) {
                     target.tokenize2().trigger('tokenize:tokens:add', [value, value, true]);
                });
                target.change(); // signal subscribers about changed data
            });
        });
    });
    /* Tokenizer <-> text for quick edits */
    $('[id*="to-text"]').each(function() {
        $(this).click(function(e) {
            e.preventDefault();
            let id = $(this).attr("id").replace(/_*to-text_*/, '');
            let source = $('div[id="select_' + id + '"]').hide().find('select');
            let destination = $('div[id="textarea_' + id + '"]').show().find('textarea');
            if (!source.hasClass('text_area_hooked')) {
                /* Switch to normal tokenizer view on change() */
                source.addClass('text_area_hooked');
                source.change(function(){
                    $('a[id="to-select_' + id + '"]').click();
                });
            }
            destination.val(source.val().join('\n'));
            destination.unbind('change').change(function(){
                source.tokenize2().trigger('tokenize:clear');
                $.each($(this).val().split("\n"), function( index, value ) {
                    source.tokenize2().trigger('tokenize:tokens:add', [value, value, true]);
                });
            });
        });
    });
    $('[id*="to-select"]').each(function() {
        $(this).click(function(e) {
            e.preventDefault();
            let id = $(this).attr("id").replace(/_*to-select_*/, '');
            $('div[id="select_' + id + '"]').show();
            $('div[id="textarea_' + id + '"]').hide();
        });
    });
}


/**
 * setup form help buttons
 */
function initFormHelpUI() {
    // handle help messages show/hide
    $("a.showhelp").click(function (event) {
        $("*[data-for='" + $(this).attr('id') + "']").toggleClass("hidden show");
        event.preventDefault();
    });

    // handle all help messages show/hide
    let elements = $('[id*="show_all_help"]');
    elements.click(function(event) {
        $(this).toggleClass("fa-toggle-on fa-toggle-off");
        $(this).toggleClass("text-success text-danger");
        if ($(this).hasClass("fa-toggle-on")) {
            if (window.sessionStorage) {
                sessionStorage.setItem('all_help_preset', 1);
            }
            $('[data-for*="help_for"]').addClass("show").removeClass("hidden");
        } else {
            $('[data-for*="help_for"]').addClass("hidden").removeClass("show");
            if (window.sessionStorage) {
                sessionStorage.setItem('all_help_preset', 0);
            }
        }
        event.preventDefault();
    });

    if (window.sessionStorage && sessionStorage.getItem('all_help_preset') === "1") {
        // show all help messages when preset was stored
        elements.toggleClass("fa-toggle-on fa-toggle-off").toggleClass("text-success text-danger");
        $('[data-for*="help_for"]').addClass("show").removeClass("hidden");
    }
}

/**
 * handle advanced show/hide
 */
function initFormAdvancedUI() {

    // handle striped tables hidden rows
    let targetNodes = $('table.table-striped tbody');
    let config = { attributes: true, subtree: true, attributeFilter: ["style"] };
    let callback = function(mutationsList, observer) {
        mutationsList.forEach(function(mutation) {
            if (mutation.target.tagName == "TR") {
                let currentValue = mutation.target.style.display;
                if (currentValue == "") {
                    // row is visible
                    $(mutation.target).next('.dummy_row').remove();
                } else if (currentValue == "none" && !$(mutation.target).next().hasClass("dummy_row")) {
                    // row is hidden and no dummy rows after it. insert one to keep stripes order
                    $(mutation.target).after("<tr data-advanced='hidden_row' class='dummy_row'></tr>");
                }
            }
        });
    }

    observer = new MutationObserver(callback);
    // observe all striped tables on page for style changes
    targetNodes.each(function(index, node) {
        observer.observe(node, config);
    });

    //  handle "advanced mode" toggle
    let elements = $('[id*="show_advanced"]');
    if (window.sessionStorage && sessionStorage.getItem('show_advanced_preset') === 1) {
        // show advanced options when preset was stored
        elements.toggleClass("fa-toggle-on fa-toggle-off");
        elements.toggleClass("text-success text-danger");
    } else {
        $('[data-advanced*="true"]').hide();
    }

    elements.click(function() {
        elements.toggleClass("fa-toggle-on fa-toggle-off");
        elements.toggleClass("text-success text-danger");
        if (elements.hasClass("fa-toggle-on")) {
            $('[data-advanced*="true"]').show();
            if (window.sessionStorage) {
                sessionStorage.setItem('show_advanced_preset', 1);
            }
        } else {
            $('[data-advanced*="true"]').hide()
            if (window.sessionStorage) {
                sessionStorage.setItem('show_advanced_preset', 0);
            }
        }
    });
}

/**
 * standard dialog when information is required, wrapper around BootstrapDialog
 */
function stdDialogInform(title, message, close, callback, type, cssClass) {
    const types = {
        "danger": BootstrapDialog.TYPE_DANGER,
        "default": BootstrapDialog.TYPE_DEFAULT,
        "info": BootstrapDialog.TYPE_INFO,
        "primary": BootstrapDialog.TYPE_PRIMARY,
        "success": BootstrapDialog.TYPE_SUCCESS,
        "warning": BootstrapDialog.TYPE_WARNING
    };
    if (!(type in types)) {
        type = 'info';
    }
    if (cssClass === undefined) {
        cssClass = '';
    }
    BootstrapDialog.show({
        title: title,
        message: message,
        cssClass: cssClass,
        type: types[type],
        buttons: [{
            label: close,
            action: function (dialogRef) {
                if (typeof callback !== 'undefined') {
                    callback();
                }
                dialogRef.close();
            }
        }]
    });
}

/**
 * standard dialog when confirmation is required, wrapper around BootstrapDialog
 */
function stdDialogConfirm(title, message, accept, decline, callback, type) {
    const types = {
        "danger": BootstrapDialog.TYPE_DANGER,
        "default": BootstrapDialog.TYPE_DEFAULT,
        "info": BootstrapDialog.TYPE_INFO,
        "primary": BootstrapDialog.TYPE_PRIMARY,
        "success": BootstrapDialog.TYPE_SUCCESS,
        "warning": BootstrapDialog.TYPE_WARNING
    };
    if (!(type in types)) {
        type = 'warning';
    }
    BootstrapDialog.confirm({
        title: title,
        message: message,
        type: types[type],
        btnCancelLabel: decline,
        btnOKLabel: accept,
        btnOKClass: 'btn-' + type,
        callback: function(result) {
            if (result) {
                callback();
            }
        }
    });
}

/**
 * wrapper for backwards compatibility (do not use)
 */
function stdDialogRemoveItem(message, callback) {
    stdDialogConfirm(
        stdDialogRemoveItem.defaults.title,  message, stdDialogRemoveItem.defaults.accept,
        stdDialogRemoveItem.defaults.decline, callback
    );
}

stdDialogRemoveItem.defaults = {
    'title': 'Remove',
    'accept': 'Yes',
    'decline': 'Cancel'
};


/**
 *  Action button, expects the following data attributes on the widget
 *      data-endpoint='/path/to/my/endpoint'
 *      data-label="Apply text"
 *      data-service-widget="service" (optional service widget to signal)
 *      data-error-title="My error message"
 */
$.fn.SimpleActionButton = function (params) {
    let this_button = this;
    this.construct = function () {
        let label_content = '<b>' + this_button.data('label') + '</b> <i class="reload_progress">';
        this_button.html(label_content);
        this_button.on('click', function () {
            this_button.find('.reload_progress').addClass("fa fa-spinner fa-pulse");
            let pre_action = function () {
                return (new $.Deferred()).resolve();
            }
            if (params && params.onPreAction) {
                pre_action = params.onPreAction;
            }
            pre_action().done(function () {
                ajaxCall(this_button.data('endpoint'), {}, function (data, status) {
                    let data_status = typeof data == 'object' && 'status' in data ? data['status'] : '';
                    if (params && params.onAction) {
                        params.onAction(data, status);
                    }
                    if ((status != "success" || (data_status.toLowerCase().trim() != 'ok')) && data_status !== '') {
                          BootstrapDialog.show({
                              type: BootstrapDialog.TYPE_WARNING,
                              title: this_button.data('error-title'),
                              message: data['status_msg'] ? data['status_msg'] : data['status'],
                              draggable: true
                          });
                    }
                    this_button.find('.reload_progress').removeClass("fa fa-spinner fa-pulse");
                    if (this_button.data('service-widget')) {
                        updateServiceControlUI(this_button.data('service-widget'));
                    }
                    if (this_button.data('grid-reload')) {
                        std_bootgrid_reload(this_button.data('grid-reload'));
                    }
                });
            }).fail(function () {
                this_button.find('.reload_progress').removeClass("fa fa-spinner fa-pulse");
            });
        });
    }

    return this.each(function () {
        const button = this_button.construct();
        return button;
    });
}


/**
 *  File upload dialog, constructs a modal, asks for a file to upload and sets {'payload': ..,, 'filename': ...}
 *  to specified endpoint. Endppoint response should contain validation errors including messages and row sequences,
 *  specified as :
 *  {
 *     validations: [
 *          {
 *            'sequence': X << sequence number,  first data record starts at 0
 *            'message': '' << validation message
 *          }
 *     ]
 *  }
 *
 *      data-endpoint='/path/to/my/endpoint'
 *      data-title="Apply text"
 */
$.fn.SimpleFileUploadDlg = function (params) {
    let this_button = this;

    this.construct = function () {
        this_button.click(function(){
            let content = $("<div/>");
            let fileinp = $("<input type='file'/>");
            let error_output = $("<textarea style='display:none; max-width:100%; height:200px;'/>");
            let doinp = $('<button style="display:none" type="button" class="btn btn-xs"/>');
            doinp.append($('<span class="fa fa-fw fa-check"></span>'));

            content.append(
                $("<table/>").append(
                    $("<tr/>").append(
                        $("<td style='width:200px;'/>").append(fileinp),
                        $("<td/>").append(doinp)
                    ),
                    $("<tr/>").append($("<td colspan='2' style='height:10px;'/>"))
                )
            );
            content.append(error_output);
            fileinp.change(function(evt) {
                if (evt.target.files[0]) {
                    var reader = new FileReader();
                    reader.onload = function(readerEvt) {
                        doinp.data('payload', readerEvt.target.result);
                        doinp.data('filename', fileinp.val().split('\\').pop());
                        doinp.show();
                    };
                    reader.readAsBinaryString(evt.target.files[0]);
                }
            });
            let dialog = BootstrapDialog.show({
                title: this_button.data('title'),
                type: BootstrapDialog.TYPE_DEFAULT,
                message: content
            });
            doinp.click(function(){
                let eparams =  {
                    'payload': $(this).data('payload'),
                    'filename': $(this).data('filename')
                };
                ajaxCall(this_button.data('endpoint'), eparams, function (data, status) {
                    if (params && params.onAction) {
                        params.onAction(data, status);
                    }
                    if (data.validations && data.validations.length > 0) {
                        // When validation errors are returned, write to textarea including original data lines.
                        let output = [];
                        let records = params.payload.split('\n');
                        records.shift();
                        for (r=0; r < records.length; ++r) {
                            let found = false;
                            for (i=0; i < data.validations.length ; ++i) {
                                if (r == data.validations[i].sequence) {
                                    if (!found) {
                                        output.push(records[data.validations[i].sequence]);
                                        found = true;
                                    }
                                    output.push('!! ' + data.validations[i].message);
                                }
                            }
                        }
                        error_output.val(output.join('\n')).show();
                    } else {
                        dialog.close();
                    }
                });
            });
        });
    }

    return this.each(function () {
        const button = this_button.construct();
        return button;
    });
}

/**
 * Changes an input to a selector with manual input option.
 * Expects the following structure:
 *      {
 *          group_name: {
 *              label: 'this items label',
 *              items: {                    << omit to mark the manual (empty) input
 *                  key: 'value',
 *              }
 *          }
 *      }
 * @param {*} params data structure to use for the select picker
 */
$.fn.replaceInputWithSelector = function (data, multiple=false) {
    let that = this;
    this.new_item = function() {
        let $div = $("<div/>");
        let $table = $('<table style="max-width: 348px"/>');
        let $select = $('<select data-live-search="true" data-size="5" data-width="348px"/>');
        if (multiple) {
            $select.attr('multiple', 'multiple');
        }
        $table.append(
            $("<tr/>").append(
                $("<td/>").append($select)
            )
        );
        $table.append(
            $("<tr/>").append(
                $("<td/>").append($('<input style="display:none;" type="text"/>'))
            )
        );
        $div.append($table);
        return $div;
    }

    this.construct = function () {
        let options = [];
        Object.keys(data).forEach((key, idx) => {
            if (data[key].items !== undefined) {
                let optgrp = $("<optgroup/>").attr('label', data[key].label);
                Object.keys(data[key].items).forEach((key2, idx2) => {
                    let this_item = data[key].items[key2];
                    optgrp.append($("<option/>").val(key2).text(this_item));
                });
                options.push(optgrp);
            } else {
                options.push($("<option/>").val('').text(data[key].label));
            }
        });
        let $target = that.new_item();
        $(this).replaceWith($target);
        let $this_input = $target.find('input');
        let $this_select = $target.find('select');
        for (i=0; i < options.length; ++i) {
            $this_select.append(options[i].clone());
        }
        $this_select.attr('for', $(this).attr('id')).selectpicker();
        $this_select.change(function(){
            let $value = $(this).val();
            if (Array.isArray($value)) {
                $value = $value.filter(value => value !== '').join(',');
            }
            if ($value !== '') {
                $this_input.val($value);
                $this_input.hide();
            } else {
                $this_input.show();
            }
        });
        $this_input.attr('id', $(this).attr('id'));
        $this_input.change(function(){
            if (multiple) {
                $this_select.val($(this).val().split(','));
            } else {
                $this_select.val($(this).val());
            }
            if ($(this).val() === '' || $this_select.val() === null || $this_select.val() == '') {
                $this_select.val('');
                $this_input.show();
            } else {
                $this_input.hide();
            }
            $this_select.selectpicker('refresh');
        });
        $this_input.show();
    }

    return this.each(function () {
        return $.proxy(that.construct, $(this))();
    });
}
