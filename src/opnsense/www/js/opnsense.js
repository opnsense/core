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
                        node[keyparts[i]] = $(this).prop("checked");
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
                    $(this).prop("checked",node[keyparts[i]]) ;
                } else {
                    $(this).val(node[keyparts[i]]);
                }
            }
        }
    });
}
