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
        var node = data ; // target node
        var sourceNode = $(this); // document node to fetch data from
        var keyparts = sourceNode.prop('id').split('.');
        $.each(keyparts,function(indx,keypart){
            if (!(keypart in node)) {
                node[keypart] = {};
            }
            if (indx < keyparts.length - 1 ) {
                node = node[keypart];
            } else {
                if (sourceNode.is("select")) {
                    // selectbox, collect selected items
                    var tmp_str = "";
                    sourceNode.children().each(function(index){
                        if ($(this).prop("selected")){
                            if (tmp_str != "") tmp_str = tmp_str + ",";
                            tmp_str = tmp_str + sourceNode.val();
                        }
                    });
                    node[keypart] = tmp_str;
                } else if (sourceNode.prop("type") == "checkbox") {
                    // checkbox input type
                    if (sourceNode.prop("checked")) {
                        node[keypart] = 1 ;
                    } else {
                        node[keypart] = 0 ;
                    }
                } else {
                    // regular input type
                    node[keypart] = sourceNode.val();
                }
            }
        });
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
        var targetNode = $(this); // document node to fetch data to
        var keyparts = $(this).prop('id').split('.');
        $.each(keyparts,function(indx,keypart){
            if (keypart in node) {
                if (indx < keyparts.length - 1 ) {
                    node = node[keypart];
                } else {
                    // data node found, handle per type
                    if (targetNode.is("select")) {
                        // handle select boxes
                        targetNode.empty(); // flush
                        $.each(node[keypart],function(indxItem, keyItem){
                            if (keyItem["selected"] != "0") {
                                targetNode.append("<option value='"+indxItem+"' selected>" + keyItem["value"] + " </option>");
                            } else {
                                targetNode.append("<option value='"+indxItem+"'>" + keyItem["value"] + " </option>");
                            }
                        });
                    } else if (targetNode.prop("type") == "checkbox") {
                        // checkbox type
                        if (node[keypart] != 0) {
                            targetNode.prop("checked",true) ;
                        } else {
                            targetNode.prop("checked",false) ;
                        }
                    } else {
                        // regular input type
                        targetNode.val(node[keypart]);
                    }
                }
            }
        });
    });
}


/**
 * handle form validations
 * @param parent
 * @param validationErrors
 */
function handleFormValidation(parent,validationErrors) {
    $( "#"+parent+"  input,#"+parent+" select" ).each(function( index ) {
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
 * clear form validations
 * @param parent
 */
function clearFormValidation(parent) {
    handleFormValidation(parent, {});
}

/**
 * call remote function (post request), wrapper around standard jQuery lib.
 * @param url endpoint url
 * @param sendData input structure
 * @param callback callback function
 * @return deferred object
 */
function ajaxCall(url,sendData,callback) {
    return $.ajax({
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
 * @return deferred object
 */
function ajaxGet(url,sendData,callback) {
    return $.ajax({
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
