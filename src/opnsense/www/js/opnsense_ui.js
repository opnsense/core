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
                    // validation message box is optional, form is already updated using handleFormValidation
                    BootstrapDialog.show({
                        type:BootstrapDialog.TYPE_WARNING,
                        title: 'Input validation',
                        message: 'Please correct validation errors in form',
                        buttons: [{
                            label: 'Dismiss',
                            action: function(dialogRef){
                                dialogRef.close();
                            }
                        }]

                    });
                }
            } else if ( callback_ok != undefined ) {
                // execute callback function
                callback_ok();
            }

        } else {
            // error handling, show internal errors
            // Normally the form should only return validation issues, if other things go wrong throw an error.
            BootstrapDialog.show({
                type: BootstrapDialog.TYPE_ERROR,
                title: 'save',
                message: 'Unable to save data, an internal error occurred.<br> ' +
                'Response from server was: <br> <small>'+JSON.stringify(data)+'</small>'
            });
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
                    if ( $(this).attr('id').split('-')[0] == data_index) {
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

    var status_html = '';

    if (status == "running") {
        status_html += '<span class="btn btn-success">' ;
    } else if (status == "stopped") {
        status_html += '<span class="btn btn-danger">' ;
    } else {
        status_html += '<span class="btn">' ;
    }

    status_html += '<span class="glyphicon glyphicon-play"  data-placement="bottom"></span> </span>&nbsp;';

    $('#service_status_container').html(status_html);
}

/**
 * reformat all tokenizers on this document
 */
function formatTokenizersUI(){
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
        maxTokenContainerHeight=$(this).data("maxheight");

        $(this).tokenize({
            displayDropdownOnFocus: true,
            newElements: allownew,
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
                }
            });
        });
    });
}

/**
 * setup form help buttons
 */
function initFormHelpUI() {
    // handle help messages show/hide
    $("a[class='showhelp']").click(function () {
        $("*[for='" + $(this).attr('id') + "']").toggleClass("hidden show");
    });

    // handle all help messages show/hide
    $('[id*="show_all_help"]').click(function() {
        $('[id*="show_all_help"]').toggleClass("fa-toggle-on fa-toggle-off");
        $('[id*="show_all_help"]').toggleClass("text-success text-danger");
        if ($('[id*="show_all_help"]').hasClass("fa-toggle-on")) {
            $('[for*="help_for"]').addClass("show");
            $('[for*="help_for"]').removeClass("hidden");
        } else {
            $('[for*="help_for"]').addClass("hidden");
            $('[for*="help_for"]').removeClass("show");
        }
    });
}

/**
 * handle advanced show/hide
 */
function initFormAdvancedUI() {
    $('[data-advanced*="true"]').hide(function(){
        $('[data-advanced*="true"]').after("<tr data-advanced='hidden_row'></tr>"); // the table row is added to keep correct table striping
    });
    $('[id*="show_advanced"]').click(function() {
        $('[id*="show_advanced"]').toggleClass("fa-toggle-on fa-toggle-off");
        $('[id*="show_advanced"]').toggleClass("text-success text-danger");
        if ($('[id*="show_advanced"]').hasClass("fa-toggle-on")) {
            $('[data-advanced*="true"]').show();
            $('[data-advanced*="hidden_row"]').remove(); // the table row is deleted to keep correct table striping
        } else {
            $('[data-advanced*="true"]').after("<tr data-advanced='hidden_row'></tr>").hide(); // the table row is added to keep correct table striping
        }
    });
}

/**
 * standard remove items dialog, wrapper around BootstrapDialog
 */
function stdDialogRemoveItem(message, callback) {
    BootstrapDialog.confirm({
        title: 'Remove',
        message: message,
        type:BootstrapDialog.TYPE_WARNING,
        btnCancelLabel: 'Cancel',
        btnOKLabel: 'Yes',
        btnOKClass: 'btn-primary',
        callback: function(result) {
            if(result) {
                callback();
            }
        }
    });
}
