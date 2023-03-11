{##
 # OPNsense® is Copyright © 2022 by Deciso B.V.
 # Copyright (C) 2022 agh1467@protonmail.com
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1.  Redistributions of source code must retain the above copyright notice,
 #     this list of conditions and the following disclaimer.
 #
 # 2.  Redistributions in binary form must reproduce the above copyright notice,
 #     this list of conditions and the following disclaimer in the documentation
 #     and/or other materials provided with the distribution.
 #
 # THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 # INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 # AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 # AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 # OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 # SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 # INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 # CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 # ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 # POSSIBILITY OF SUCH DAMAGE.
 # #}
{#/*
    # Toggle function is for enabling or disabling field(s)
    # This will disable an entire row (make things greyed out)
    # takes care of at least text boxes, checkboxes, and dropdowns.
    # It uses the *= wildcard, so take care with the field name.
    # Field should be the id of an object or the prefix/suffix
    # for a set of objects.
*/#}
function toggle (id, type, toggle) {
    var efield = $.escapeSelector(id);
    if (type == "field") {
{#/*        # This might need further refinement, selects the row matching field id,
        # uses .find() to select descendants, .addBack() to select itself. */#}
        var selected_row = $('tr[id=row_' + efield + ']')
        var selected = selected_row.find('div,[id*=' + efield + '],[data-id*=' + efield + '],[name*=' + efield + '],[class^="select-box"],[class^="btn"],[class^="search-field"],ul[class^="tokens-container"]').addBack();
        if (toggle == "disabled") {
{#/*            # Disable entire row related to a field */#}
            selected.addClass("disabled");
            selected.prop({
                "readonly": true,
                "disabled": true
            });
{#/*            # This element needs to be specially hidden because it is for some reason
            # hidden when tokenizer creates the element. This is the target element
            # <li class="token-search" style="width: 15px; display: none;"><input autocomplete="off"></li> */#}
            selected.find('li[class="token-search"]').hide();
{#/*            # Disable the Clear All link below dropdown boxes,
            # the toggle column on grids (Enabled column),
            # and the tokens in a tokenized field. */#}
            selected.find('a[id^="clear-options_"],[class*="command-toggle"],li[class="token"]').css("pointer-events","none");
            $('input[id=' + efield + ']').trigger("change");
        } else if (toggle == "enabled") {
{#/*            # Disable entire row related to a field */#}
            selected.removeClass("disabled");
            selected.prop({
                "readonly": false,
                "disabled": false
            });
{#/*            # This element needs to be specially shown because it is for some reason
            # hidden when tokenizer creates the element. This is the target element
            # <li class="token-search" style="width: 15px; display: none;"><input autocomplete="off"></li>*/#}
            selected.find('li[class="token-search"]').show();
{#/*            # Enable the Clear All link below dropdown boxes,
            # the toggle column on grids (Enabled column),
            # and the tokens in a tokenized field.*/#}
            selected.find('a[id^="clear-options_"],[class*="command-toggle"],li[class="token"]').css("pointer-events","auto");
{#/*            # Trigger a field change to trigger a toggle of any dependent fields (i.e. fields that this field enables) */#}
            var selected_field = $('input[id=' + efield + ']')
            $('input[id=' + efield + ']').trigger("change");
        } else if (toggle == "hidden") {
{#/*            # Do a nice fade out with a hide once done,
            # and add dummy row for striping. */#}
            selected_row.fadeOut(400, function() {
                selected_row.after('<tr class="dummy_row" style="display: none"></tr>');
            });
        } else if (toggle == "visible") {
{#/*            # Do a nice fade in instead of a show() pop */#}
            selected_row.fadeIn(200, function() {
                selected_row.next("tr[class=dummy_row]").remove();
            });
        }
    } else if (["tab", "box"].includes(type)) {
        if (toggle == "hidden") {
{#/*            # Use a fadeOut instead of hide() for a nice effect. */#}
            $("#" + efield).fadeOut();
        } else if (toggle == "visible") {
{#/*            # Use a fadeIn instead of show() for a nice effect. */#}
            $("#" + efield).fadeIn();
        }
    } else if (["button"].includes(type)) {
        if (toggle == "hidden") {
            $("button[id=" + efield + "]").hide();
        } else if (toggle == "visible") {
            $("button[id=" + efield + "]").show();
        }
    } else {
{#/* Catch all for any other types, just try all the things and maybe something will work.. */#}
        var selected = $(type + '[id=' + efield + "]");
        if (toggle == "hidden") {
            selected.hide();
        } else if (toggle == "visible") {
            selected.show();
        } else if (toggle == "enabled") {
            selected.addClass("disabled");
            selected.prop({
                "readonly": true,
                "disabled": true
            });
        } else if (toggle == "disabled") {
            selected.removeClass("disabled");
            selected.prop({
                "readonly": false,
                "disabled": false
            });
        }
    }
}

{#/*
 # =====================================================================================================================
 # Button Functions
 # =====================================================================================================================
*/#}
{#/* Make a button look busy, and disable it to prevent extra clicks. */#}
function busyButton(this_btn) {
    this_btn.find('[id$="_progress"]').addClass("fa fa-spinner fa-pulse");
    this_btn.addClass("disabled");
}
{#/* Make a button clear from busy state, re-enable it. */#}
function clearButton(this_btn) {
    this_btn.find('[id$="_progress"]').removeClass("fa fa-spinner fa-pulse");
    this_btn.removeClass("disabled");
}
{#/* Make a button clear from busy state, re-enable it, includes toggle for Apply Changes visibility. */#}
function clearButtonAndToggle(this_btn) {
    clearButton(this_btn);
    toggleApplyChanges();
}

{#/*
 # =====================================================================================================================
 # Configuration Activities
 # =====================================================================================================================
*/#}
{#/*
 # This function is designed to take a selected form DOM, a defferred object, and supports callbacks for pass and fail.
 #
 # This uses the selctor method and relies on the HTML data. Since we have XML data to drive the model, I'm not sure
 # this approach is necessary anymore.
 #
 # For example as we have the data_get_map, a data_set_map could be created just the same. It could be referenced by model name.
 #
*/#}
    function saveForm(form, dfObj, this_callback_ok, this_callback_fail){
        var this_frm = form;
        var frm_id = this_frm.attr("id");
        var frm_title = this_frm.attr("data-title");
        var frm_model = this_frm.attr("data-model");

{#/*    # It's possible for a form to exist without a data-model, exclude them. */#}
        if (frm_model) {
            var api_url="/api/{{ plugin_api_name }}/" + frm_model + "/set";
            saveFormToEndpoint(
                url=api_url,
                formid=frm_id,
                callback_ok=
                    function(data, status){
                        dfObj.resolve();
                        if (typeof this_callback_ok === "function") {
                            this_callback_ok();
                        }
                    },
                false,
                callback_fail=
                    function(data, status){
                        dfObj.reject();
                        if (typeof this_callback_fail === "function") {
                            this_callback_fail();
                        }
                    }
            );
        } else {
                dfObj.reject();
                this_callback_fail();
        }
    }

{#/*
    # Basic function to save the form, and reconfigure after saving
    # displays a dialog if there is some issue */#}
function saveFormAndReconfigure(element){
    const dfObj = new $.Deferred();
    var this_frm = $(element).closest("form");
    var frm_id = this_frm.attr("id");
    var frm_title = this_frm.attr("data-title");
    var frm_model = this_frm.attr("data-model");
    var api_url="/api/{{ plugin_api_name }}/" + frm_model + "/set";

{#/*    # set progress animation when saving */#}
    $("#" + frm_id + "_progress").addClass("fa fa-spinner fa-pulse");

    saveFormToEndpoint(url=api_url, formid=frm_id, callback_ok=function(){
        ajaxCall(url="/api/{{ plugin_api_name }}/service/reconfigure", sendData={}, callback=function(data,status){
{#/*            # when done, disable progress animation. */#}
            $("#" + frm_id + "_progress").removeClass("fa fa-spinner fa-pulse");

            if (status != "success" || data['status'] != 'ok' ) {
                ajaxDataDialog(data, frm_title);
            } else {
                ajaxCall(url="/api/{{ plugin_api_name }}/service/status", sendData={}, callback=function(data,status) {
                    updateServiceStatusUI(data['status']);
                    dfObj.resolve();
                });
            }
        });
    });
    return dfObj;
}

{#/*
 # =====================================================================================================================
 # Service Activities
 # =====================================================================================================================
*/#}
{#/* XXX Needs description. */#}
{#/* XXX This is probably a button activity, and the reconfigure activity could probably be broken out. */#}
function reconfigureService(button, dfObj, callback_after, params){
    var frm_title = '{{ plugin_label }}';

    busyButton(button);

    var api_url = "/api/{{ plugin_api_name }}/service/reconfigure";
    ajaxCall(url=api_url, sendData={}, callback=function(data, status){
        if (status != "success" || data['status'] != 'ok' ) {
            ajaxDataDialog(data, frm_title);
        } else {
            if (callback_after !== undefined) {
                callback_after.apply(this, params);
            }
            var api_url = "/api/{{ plugin_api_name }}/service/status";
            ajaxCall(url=api_url, sendData={}, callback=function(data, status) {
                updateServiceStatusUI(data['status']);
                dfObj.resolve();
            });
        }
    });
    return dfObj;
}


{#/*
 # =====================================================================================================================
 # UI Activities
 # =====================================================================================================================
*/#}
