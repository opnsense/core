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
 * When a type_formatter attribute exists on the input element, this will be called with the val() content first
 *
 * @param parent tag id in dom
 * @return array
 */
function getFormData(parent) {
    let data = {};
    $("#"+parent+" input,#"+parent+" select,#"+parent+" textarea" ).each(function() {
        if ($(this).prop('id') === undefined || $(this).prop('id') === "") {
            // we need an id.
            return;
        }
        var node = data; // target node
        var sourceNode = $(this); // document node to fetch data from
        var keyparts = sourceNode.prop('id').split('.');
        $.each(keyparts,function(index,keypart){
            if (!(keypart in node)) {
                node[keypart] = {};
            }
            if (index < keyparts.length - 1 ) {
                node = node[keypart];
            } else {
                if (sourceNode.is("select")) {
                    var separator = ",";
                    if (sourceNode.data('separator') !== undefined) {
                        // select defined it's own separator
                        separator = sourceNode.data('separator');
                        if (separator.match(/#[0-9]{1,3}/g)) {
                            // use char() code
                            separator = String.fromCharCode(parseInt(separator.substr(1)));
                        }
                    }
                    // selectbox, collect selected items
                    if (!Array.isArray(sourceNode.val())) {
                        node[keypart] = sourceNode.val();
                    } else {
                        node[keypart] = "";
                        $.each(sourceNode.val(), function(idx, value){
                            if (node[keypart] !== "") node[keypart] = node[keypart] + separator;
                            node[keypart] = node[keypart] + value;
                        });
                    }
                } else if (sourceNode.prop("type") === "checkbox") {
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
                    node[keypart] = sourceNode.val();
                }
                // Might need a parser to convert to the correct format
                // (attribute type_formatter as function name)
                if (sourceNode.attr('type_formatter') !== undefined && window[sourceNode.attr('type_formatter')] !== undefined) {
                    node[keypart] = window[sourceNode.attr('type_formatter')](node[keypart]);
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
    $("#"+parent+"  input,#"+parent+" select,#"+parent+" span,#"+parent+" textarea").each(function() {
        if ($(this).prop('id') === undefined || $(this).prop('id') === "") {
            // we need an id.
            return;
        }
        var node = data;
        var targetNode = $(this); // document node to fetch data to
        var keyparts = $(this).prop('id').split('.');
        $.each(keyparts,function(index,keypart){
            if (keypart in node) {
                if (index < keyparts.length - 1) {
                    node = node[keypart];
                } else {
                    // data node found, handle per type
                    if (targetNode.is("select")) {
                        // handle select boxes
                        if (targetNode.find('option').length > 0 && targetNode.hasClass("tokenize")) {
                            // when setting the same content twice to a widget, tokenize2 sorting mixes up.
                            // Ideally formatTokenizersUI() or tokenize2 should handle this better, but for now
                            // this seems like the only fix that actually works.
                            targetNode.tokenize2().trigger('tokenize:clear');
                        }
                        targetNode.empty(); // flush
                        let optgroups = [];
                        if (Array.isArray(node[keypart]) && node[keypart][0] !== undefined && node[keypart][0].key !== undefined) {
                            // key value (sorted) list
                            // (eg node[keypart][0] = {selected: 0, value: 'my item', key: 'item'})
                            for (i=0; i < node[keypart].length; ++i) {
                                let opt = $("<option>").val(htmlDecode(node[keypart][i].key)).text(node[keypart][i].value);
                                if (String(node[keypart][i].selected) !== "0") {
                                    opt.attr('selected', 'selected');
                                }
                                let optgroup = node[keypart][i].optgroup ?? '';
                                if (optgroups[optgroup] === undefined) {
                                    optgroups[optgroup] = [];
                                }
                                optgroups[optgroup].push(opt);
                            }
                        } else{
                            // default "dictionary" type select items
                            // (eg node[keypart]['item'] = {selected: 0, value: 'my item'})
                            $.each(node[keypart],function(indxItem, keyItem){
                                let opt = $("<option>").val(htmlDecode(indxItem)).text(keyItem["value"]);
                                let optgroup = keyItem.optgroup ?? '';
                                if (String(keyItem["selected"]) !== "0") {
                                    opt.attr('selected', 'selected');
                                }
                                if (optgroups[optgroup] === undefined) {
                                    optgroups[optgroup] = [];
                                }
                                optgroups[optgroup].push(opt);
                            });
                        }
                        for (const [group, items] of Object.entries(optgroups)) {
                            if (group == '' && optgroups.length <= 1) {
                                targetNode.append(items);
                            } else {
                                targetNode.append($("<optgroup/>").attr('label', group).append(items));
                            }
                        }
                    } else if (targetNode.prop("type") === "checkbox") {
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
                    } else if (targetNode.attr('type') !== 'file') {
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
function handleFormValidation(parent, validationErrors)
{
    $("#" + parent).find("[id]").each(function () {
        let target = $("*[id*='" + $(this).prop('id') + "']");
        if (validationErrors !== undefined && $(this).prop('id') in validationErrors) {
            let message = validationErrors[$(this).prop('id')];
            $("span[id='help_block_" + $(this).prop('id') + "']").empty();
            if (typeof message === 'object') {
                for (let i=0 ; i < message.length ; ++i)  {
                    $("span[id='help_block_" + $(this).prop('id') + "']").append($("<div>").text(message[i]));
                }
            } else {
                $("span[id='help_block_" + $(this).prop('id') + "']").text(message);
            }
            target.addClass("has-error");
            /* make sure to always unhide row when triggering a validation */
            if (!target.closest('tr').is(':visible')) {
                target.closest('tr').show();
            }
            /* scroll to element with validation issue */
            target[0].scrollIntoView();
        } else {
            target.removeClass("has-error");
            $("span[id='help_block_" + $(this).prop('id') + "']").empty();
        }
    });

    let tab = $("#" + parent).parent().attr('id') + '_tab';
    if (validationErrors !== undefined) {
        $('#' + tab).click();
    }
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
        type: 'POST',
        url: url,
        dataType:'json',
        contentType: 'application/json',
        complete: function(data, status) {
            if (callback != null) {
                if ('responseJSON' in data) {
                    callback(data['responseJSON'], status);
                } else {
                    callback(data, status);
                }
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
        type: 'GET',
        url: url,
        dataType:'json',
        contentType: 'application/json',
        complete: function(data,status) {
            if (callback != null) {
                if ('responseJSON' in data) {
                    callback(data['responseJSON'], status);
                } else {
                    callback({}, status);
                }
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
        // concat url pieces to identify this page and parameters
        return window.location.href.replace(/\/|\:|\.|\?|\#/gi, '');
    }

    // link on scroll event handler
    if (window.sessionStorage) {
        var $window = $(window);
        $window.scroll(function(){
            sessionStorage.setItem('scrollpos', current_location() + "|" + $window.scrollTop());
        });

        // move to last known position on page load
        $(document).ready(function() {
            var scrollpos = sessionStorage.getItem('scrollpos');
            if (scrollpos != null) {
                if (scrollpos.split('|')[0] === current_location()) {
                    $window.scrollTop(scrollpos.split('|')[1]);
                }
            }
        });
    }
}

/**
 * Simple wrapper to download a file received via an api endpoint
 * @param {*} payload
 * @param {*} filename
 * @param {*} file_type
 */
function download_content(payload, filename, file_type) {
    let a_tag = $('<a></a>').attr('href','data:application/json;charset=utf8,' + encodeURIComponent(payload))
        .attr('download', filename).appendTo('body');

    a_tag.ready(function() {
        if ( window.navigator.msSaveOrOpenBlob && window.Blob ) {
            var blob = new Blob( [ payload ], { type: file_type } );
            navigator.msSaveOrOpenBlob( blob, filename);
        } else {
            a_tag.get(0).click();
        }
    });
}
