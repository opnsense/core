/**
 * Map input fields from given parent tag to structure of named arrays.
 *
 * @param parent tag id in dom
 * @return array
 */
function getFormData(parent) {

    data = {};
    $( "#"+parent+"  input" ).each(function( index ) {
            node = data ;
            keyparts = $(this).attr('id').split('.');
            for (var i in keyparts) {
                if (!(keyparts[i] in node)) {
                    node[keyparts[i]] = {};
                }
                if (i < keyparts.length - 1 ) {
                    node = node[keyparts[i]];
                } else {
                    if ($(this).prop("type") == "checkbox") {
                        if ($(this).prop("checked")) {
                            node[keyparts[i]] = 1 ;
                        } else {
                            node[keyparts[i]] = 0 ;
                        }
                    } else {
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
    $( "#"+parent+"  input" ).each(function( index ) {
        node = data ;
        keyparts = $(this).attr('id').split('.');
        for (var i in keyparts) {
            if (!(keyparts[i] in node)) {
                break;
            }
            if (i < keyparts.length - 1 ) {
                node = node[keyparts[i]];
            } else {
                if ($(this).prop("type") == "checkbox") {
                    if (node[keyparts[i]] != 0) {
                        $(this).prop("checked",true) ;
                    } else {
                        $(this).prop("checked",false) ;
                    }

                } else {
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
                callback({},status);
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
