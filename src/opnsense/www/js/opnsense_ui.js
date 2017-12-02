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
 *
 *    User interface shared components, requires opnsense.js for supporting functions.
 */

 /**
  * format bytes
  * @param bytes number of bytes to format
  * @param decimals decimal places
  * @return string
  */
 function byteFormat(bytes, decimals)
 {
     if (decimals == undefined) {
        decimals = 0;
     }
     var kb = 1024;
     var ndx = Math.floor( Math.log(bytes) / Math.log(kb) );
     var fileSizeTypes = ["Bytes", "KB", "MB", "GB", "TB", "PB", "EB", "ZB", "YB"];
     return (bytes / Math.pow(kb, ndx)).toFixed(decimals) + ' ' + fileSizeTypes[ndx];
 }

/**
 * save form to server
 * @param url endpoint url
 * @param formid parent id to grep input data from
 * @param disable_dialog don't show input validation message box on failure
 */
function saveFormToEndpoint(url,formid,callback_ok, disable_dialog) {
    disable_dialog = disable_dialog || false;
    var data = getFormData(formid);
    ajaxCall(url=url,sendData=data,callback=function(data,status){
        if ( status == "success") {
            // update field validation
            handleFormValidation(formid,data['validations']);

            // if there are validation issues, update our screen and show a dialog.
            if (data['validations'] != undefined) {
                if (!disable_dialog) {
                    var detailsid = "errorfrm"+Math.floor((Math.random() * 10000000) + 1);
                    var errorMessage = $('<div></div>');
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
            } else if ( callback_ok != undefined ) {
                // execute callback function
                callback_ok();
            }
        }
    });
}

/**
 * standard data mapper to map json request data to forms on this page
 * @param data_get_map named array containing form names and source url's to get data from {'frm_example':"/api/example/settings/get"};
 * @return promise object, resolves when all are loaded
 */
function mapDataToFormUI(data_get_map) {
    var dfObj = new $.Deferred();

    // calculate number of items for deferred object to resolve
    var data_map_seq = 1;
    var data_map_count = 0;
    $.each(data_get_map, function(){
        data_map_count += 1;
    });

    var collected_data = {};
    $.each(data_get_map, function(data_index, data_url) {
        ajaxGet(url=data_url,sendData={}, callback=function(data, status) {
            if (status == "success") {
                $("form").each(function( index ) {
                    if ( $(this).attr('id') && $(this).attr('id').split('-')[0] == data_index) {
                        // related form found, load data
                        setFormData($(this).attr('id'), data);
                        collected_data[$(this).attr('id')] = data;
                    }
                });
            }
            if (data_map_seq == data_map_count) {
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
function updateServiceStatusUI(status) {

    var status_html = '<span class="label label-opnsense label-opnsense-sm ';

    if (status == "running") {
        status_html += 'label-success';
    } else if (status == "stopped") {
        status_html += 'label-danger';
    } else {
        status_html += 'hidden';
    }

    status_html += '"><i class="fa fa-play fa-fw"/></span>';

    $('#service_status_container').html(status_html);
}

/**
 * reformat all tokenizers on this document
 */
function formatTokenizersUI(){
    // remove old tokenizers (if any)
    $('div[class="tokenize Tokenize"]').each(function(){
        $(this).remove();
    });
    $('select[class="tokenize"]').each(function(){
        if ($(this).prop("size")==0) {
            maxDropdownHeight=String(36*5)+"px"; // default number of items

        } else {
            number_of_items = $(this).prop("size");
            maxDropdownHeight=String(36*number_of_items)+"px";
        }
        hint=$(this).data("hint");
        width=$(this).data("width");
        allownew=$(this).data("allownew");
        nbDropdownElements=$(this).data("nbdropdownelements");
        maxTokenContainerHeight=$(this).data("maxheight");

        $(this).tokenize({
            displayDropdownOnFocus: true,
            newElements: allownew,
            nbDropdownElements: nbDropdownElements,
            placeholder:hint
        });
        $(this).parent().find('ul[class="TokensContainer"]').parent().css("width",width);
        $(this).parent().find('ul[class="Dropdown"]').css("max-height", maxDropdownHeight);
        if ( maxDropdownHeight != undefined ) {
            $(this).parent().find('ul[class="TokensContainer"]').css("max-height", maxTokenContainerHeight);
        }
    });
}

/**
 * clear multiselect boxes on click event, works on standard and tokenized versions
 */
function addMultiSelectClearUI() {
    $('[id*="clear-options"]').each(function() {
        $(this).click(function() {
            var id = $(this).attr("for");
            BootstrapDialog.confirm({
                title: 'Deselect or remove all items ?',
                message: 'Deselect or remove all items ?',
                type: BootstrapDialog.TYPE_DANGER,
                closable: true,
                draggable: true,
                btnCancelLabel: 'Cancel',
                btnOKLabel: 'Yes',
                btnOKClass: 'btn-primary',
                callback: function(result) {
                    if(result) {
                        if ($('select[id="' + id + '"]').hasClass("tokenize")) {
                            // trigger close on all Tokens
                            $('select[id="' + id + '"]').parent().find('ul[class="TokensContainer"]').find('li[class="Token"]').find('a').trigger("click");
                        } else {
                            // remove options from selection
                            $('select[id="' + id + '"]').find('option').prop('selected',false);
                        }
                    }
                    // In case this modal was triggered from another modal, fix focus issues
                    $('.modal').on("hidden.bs.modal", function (e) {
                        if($('.modal:visible').length)
                        {
                            $('body').addClass('modal-open');
                        }
                    });
                }
            });
        });
    });
}

function checkSessionStorageExists() {
  if ('sessionStorage' in window) {
    if (window.sessionStorage != null) {
      return true;
    }
  }
  return false;
}

/**
 * setup form help buttons
 */
function initFormHelpUI() {
    // handle help messages show/hide
    $("a[class='showhelp']").click(function (event) {
        $("*[for='" + $(this).attr('id') + "']").toggleClass("hidden show");
        event.preventDefault();
    });

    // handle all help messages show/hide
    $('[id*="show_all_help"]').click(function(event) {
        $('[id*="show_all_help"]').toggleClass("fa-toggle-on fa-toggle-off");
        $('[id*="show_all_help"]').toggleClass("text-success text-danger");
        if ($('[id*="show_all_help"]').hasClass("fa-toggle-on")) {
            if (checkSessionStorageExists()) {
                sessionStorage.setItem('all_help_preset', 1);
            }
            $('[for*="help_for"]').addClass("show");
            $('[for*="help_for"]').removeClass("hidden");
        } else {
            $('[for*="help_for"]').addClass("hidden");
            $('[for*="help_for"]').removeClass("show");
            if (checkSessionStorageExists()) {
                sessionStorage.setItem('all_help_preset', 0);
            }
        }
        event.preventDefault();
    });
    if (checkSessionStorageExists() && sessionStorage.getItem('all_help_preset') == 1) {
        // show all help messages when preset was stored
        $('[id*="show_all_help"]').toggleClass("fa-toggle-on fa-toggle-off");
        $('[id*="show_all_help"]').toggleClass("text-success text-danger");
        $('[for*="help_for"]').addClass("show");
        $('[for*="help_for"]').removeClass("hidden");
    }
}

/**
 * handle advanced show/hide
 */
function initFormAdvancedUI() {
    if (checkSessionStorageExists() && sessionStorage.getItem('show_advanced_preset') == 1) {
        // show advanced options when preset was stored
        $('[id*="show_advanced"]').toggleClass("fa-toggle-on fa-toggle-off");
        $('[id*="show_advanced"]').toggleClass("text-success text-danger");
    } else {
        $('[data-advanced*="true"]').hide(function(){
            $('[data-advanced*="true"]').after("<tr data-advanced='hidden_row'></tr>"); // the table row is added to keep correct table striping
        });
    }

    $('[id*="show_advanced"]').click(function() {
        $('[id*="show_advanced"]').toggleClass("fa-toggle-on fa-toggle-off");
        $('[id*="show_advanced"]').toggleClass("text-success text-danger");
        if ($('[id*="show_advanced"]').hasClass("fa-toggle-on")) {
            $('[data-advanced*="true"]').show();
            $('[data-advanced*="hidden_row"]').remove(); // the table row is deleted to keep correct table striping
            if (checkSessionStorageExists()) {
                sessionStorage.setItem('show_advanced_preset', 1);
            }
        } else {
            $('[data-advanced*="true"]').after("<tr data-advanced='hidden_row'></tr>").hide(); // the table row is added to keep correct table striping
            if (checkSessionStorageExists()) {
                sessionStorage.setItem('show_advanced_preset', 0);
            }
        }
    });
}

/**
 * standard dialog when information is required, wrapper around BootstrapDialog
 */
function stdDialogInform(title, message, close, callback, type) {
     var types = {
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
    BootstrapDialog.show({
        title: title,
        message: message,
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
     var types = {
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
    stdDialogConfirm('Remove', message, 'Yes', 'Cancel', callback);
}
