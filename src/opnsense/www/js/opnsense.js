/**
 * Map input fields from given parent tag to structure of named arrays.
 *
 * @param parent tag id in dom
 * @return array
 */
function getFormData(parent) {

    data = {};
    $( "#"+parent+"  input,select" ).each(function( index ) {
            node = data ;
            keyparts = $(this).attr('id').split('.');
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
                            node[keyparts[i]] = tmp_str;
                        });
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
    //alert( JSON.stringify(data['general']['interfaces']) );

    $( "#"+parent+"  input,select" ).each(function( index ) {
        node = data ;
        keyparts = $(this).attr('id').split('.');
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
        if (validationErrors != undefined && $(this).attr('id') in validationErrors) {
            $("*[for='" + $(this).attr('id') + "']").addClass("has-error");
            $("span[for='" + $(this).attr('id') + "']").text(validationErrors[$(this).attr('id')]);
        } else {
            $("*[for='" + $(this).attr('id') + "']").removeClass("has-error");
            $("span[for='" + $(this).attr('id') + "']").text("");
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


