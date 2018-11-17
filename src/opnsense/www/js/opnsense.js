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
  * html decode text into textarea tag and return decoded value.
  *
  * @param value encoded text
  * @return string decoded text
  */
function htmlDecode(value) {
    return $("<textarea/>").html(value).text();
}


 /**
 *
 * Map input fields from given parent tag to structure of named arrays.
 *
 * @param parent tag id in dom
 * @return array
 */
function getFormData(parent) {

    var data = {};
    $( "#"+parent+"  input,#"+parent+" select,#"+parent+" textarea" ).each(function( index ) {
        if ($(this).prop('id') === undefined || $(this).prop('id') === "") {
            // we need an id.
            return;
        }
        var node = data; // target node
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
                    var separator = ",";
                    if (sourceNode.data('separator') != undefined) {
                        // select defined it's own separator
                        separator = sourceNode.data('separator');
                        if (separator.match(/#[0-9]{1,3}/g)) {
                            // use char() code
                            separator = String.fromCharCode(parseInt(separator.substr(1)));
                        }
                    }
                    // selectbox, collect selected items
                    var tmp_str = "";
                    sourceNode.children().each(function(index){
                        if ($(this).prop("selected")){
                            if (tmp_str != "") tmp_str = tmp_str + separator;
                            tmp_str = tmp_str + $(this).val();
                        }
                    });
                    node[keypart] = tmp_str;
                } else if (sourceNode.prop("type") == "checkbox") {
                    // checkbox input type
                    if (sourceNode.prop("checked")) {
                        node[keypart] = "1";
                    } else {
                        node[keypart] = "0";
                    }
                } else if (sourceNode.hasClass("json-data")) {
                    // deserialize the field content - used for JS maintained fields
                    node[keypart] = sourceNode.data('data');
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
    $( "#"+parent+"  input,#"+parent+" select,#"+parent+" span,#"+parent+" textarea" ).each(function( index ) {
        if ($(this).prop('id') == undefined || $(this).prop('id') == "") {
            // we need an id.
            return;
        }
        var node = data;
        var targetNode = $(this); // document node to fetch data to
        var keyparts = $(this).prop('id').split('.');
        $.each(keyparts,function(indx,keypart){
            if (keypart in node) {
                if (indx < keyparts.length - 1) {
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
                        targetNode.prop("checked", node[keypart] != 0);
                    } else if (targetNode.is("span")) {
                        if (node[keypart] != null) {
                            targetNode.text("");
                            targetNode.append(htmlDecode(node[keypart]));
                        }
                    } else if (targetNode.hasClass('json-data')) {
                        // if the input field is JSON data, serialize the data into the field
                        targetNode.data('data', node[keypart]);
                    } else {
                        // regular input type
                        targetNode.val(htmlDecode(node[keypart]));
                    }
                    targetNode.change();
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
    $( "#"+parent).find("*").each(function( index ) {
        if (validationErrors != undefined && $(this).prop('id') in validationErrors) {
            $("*[id*='" + $(this).prop('id') + "']").addClass("has-error");
            $("span[id='help_block_" + $(this).prop('id') + "']").text(validationErrors[$(this).prop('id')]);
        } else {
            $("*[id*='" + $(this).prop('id') + "']").removeClass("has-error");
            $("span[id='help_block_" + $(this).prop('id') + "']").text("");
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
function ajaxCall(url, sendData, callback) {
    return $.ajax({
        type: "POST",
        url: url,
        dataType:"json",
        contentType: "application/json",
        complete: function(data, status) {
            if ( callback == null ) {
                null;
            } else if ( "responseJSON" in data ) {
                callback(data['responseJSON'],status);
            } else {
                callback(data,status);
            }
        },
        data: JSON.stringify(sendData)
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
        contentType: "application/json",
        complete: function(data,status) {
            if ( callback == null ) {
                null;
            } else if ( "responseJSON" in data ) {
                callback(data['responseJSON'],status);
            } else {
                callback({},status);
            }
        },
        data: sendData
    });
}

/**
 * watch scroll position and set to last known on page load
 */
function watchScrollPosition() {
    function current_location() {
        // concat url pieces to indentify this page and parameters
        return window.location.href.replace(/\/|\:|\.|\?|\#/gi, '');
    }

    // link on scroll event handler
    if (window.sessionStorage) {
        $(window).scroll(function(){
            sessionStorage.setItem('scrollpos', current_location()+"|"+$(window).scrollTop());
        });

        // move to last known position on page load
        $( document ).ready(function() {
            var scrollpos = sessionStorage.getItem('scrollpos');
            if (scrollpos != null) {
                if (scrollpos.split('|')[0] == current_location()) {
                    $(window).scrollTop(scrollpos.split('|')[1]);
                }
            }
        });
    }
}
