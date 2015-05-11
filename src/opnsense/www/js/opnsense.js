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
 *    shared components
 *
 */

 /**
 *
 * Map input fields from given parent tag to structure of named arrays.
 *
 * @param parent tag id in dom
 * @return array
 */
function getFormData(parent) {

    var data = {};
    $( "#"+parent+"  input,#"+parent+" select" ).each(function( index ) {
        if ($(this).prop('id') == undefined) {
            // we need an id.
            return;
        }
        var node = data ;
        var keyparts = $(this).prop('id').split('.');
        for (var i in keyparts) {
            if (!(keyparts[i] in node)) {
                node[keyparts[i]] = {};
            }
            if (i < keyparts.length - 1 ) {
                node = node[keyparts[i]];
            } else {
                if ($(this).is("select")) {
                    // selectbox, collect selected items
                    var tmp_str = "";
                    $(this).children().each(function(index){
                        if ($(this).prop("selected")){
                            if (tmp_str != "") tmp_str = tmp_str + ",";
                            tmp_str = tmp_str + $(this).val();
                        }
                    });
                    node[keyparts[i]] = tmp_str;
                } else if ($(this).prop("type") == "checkbox") {
                    // checkbox input type
                    if ($(this).prop("checked")) {
                        node[keyparts[i]] = 1 ;
                    } else {
                        node[keyparts[i]] = 0 ;
                    }
                } else {
                    // regular input type
                    node[keyparts[i]] = $(this).val();
                }
            }
        }
    });

    return data;
}

/**
 * bind data to form, using named arrays
 *
 * for example,
 *      data = {'host':{'name':'opnsense'}}
 *      parent = 'general'
 *
 *      will search for an input tag host.name within the parent tag 'general' and fills it with the value 'opnsense'
 *
 * @param parent tag id in dom
 * @param data named array structure
 */
function setFormData(parent,data) {
    $( "#"+parent+"  input,#"+parent+" select" ).each(function( index ) {
        if ($(this).prop('id') == undefined) {
            // we need an id.
            return;
        }
        var node = data ;
        var keyparts = $(this).prop('id').split('.');
        for (var i in keyparts) {
            if (!(keyparts[i] in node)) {
                break;
            }
            if (i < keyparts.length - 1 ) {
                node = node[keyparts[i]];
            } else {
                // data node found, handle per type
                if ($(this).is("select")) {
                    // handle select boxes
                    $(this).empty(); // flush
                    for (var key in node[keyparts[i]]) {
                        if (node[keyparts[i]][key]["selected"] != "0") {
                            $(this).append("<option value='"+key+"' selected>" + node[keyparts[i]][key]["value"] + " </option>");
                        } else {
                            $(this).append("<option value='"+key+"'>" + node[keyparts[i]][key]["value"] + " </option>");
                        }
                    }
                } else if ($(this).prop("type") == "checkbox") {
                    // checkbox type
                    if (node[keyparts[i]] != 0) {
                        $(this).prop("checked",true) ;
                    } else {
                        $(this).prop("checked",false) ;
                    }
                } else {
                    // regular input type
                    $(this).val(node[keyparts[i]]);
                }
            }
        }
    });
}


/**
 * handle form validations
 * @param parent
 * @param validationErrors
 */
function handleFormValidation(parent,validationErrors) {
    $( "#"+parent+"  input" ).each(function( index ) {
        if (validationErrors != undefined && $(this).prop('id') in validationErrors) {
            $("*[for='" + $(this).prop('id') + "']").addClass("has-error");
            $("span[for='" + $(this).prop('id') + "']").text(validationErrors[$(this).prop('id')]);
        } else {
            $("*[for='" + $(this).prop('id') + "']").removeClass("has-error");
            $("span[for='" + $(this).prop('id') + "']").text("");
        }
    });
}

/**
 * call remote function (post request), wrapper around standard jQuery lib.
 * @param url endpoint url
 * @param sendData input structure
 * @param callback callback function
 */
function ajaxCall(url,sendData,callback) {
    $.ajax({
        type: "POST",
        url: url,
        dataType:"json",
        complete: function(data,status) {
            if ( callback == null ) {
                null;
            } else if ( "responseJSON" in data ) {
                callback(data['responseJSON'],status);
            } else {
                callback(data,status);
            }
        },
        data:sendData
    });
}

/**
 * retrieve json type data (GET request) from remote url
 * @param url endpoint url
 * @param sendData input structure
 * @param callback callback function
 */
function ajaxGet(url,sendData,callback) {
    $.ajax({
        type: "GET",
        url: url,
        dataType:"json",
        complete: function(data,status) {
            if ( callback == null ) {
                null;
            } else if ( "responseJSON" in data ) {
                callback(data['responseJSON'],status);
            } else {
                callback({},status);
            }
        },
        data:sendData
    });
}
