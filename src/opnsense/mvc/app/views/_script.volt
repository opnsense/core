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
 # }

{##
 # This is a partial used to populate the <script> section of a page.
 #
 # Expects to have in the environment (scope) an array by the name of this_form.
 # This should contain an array of form XML data, created by the controller using
 # getForm().
 #
 # Expects to have all macros available in the environment.
 # views/OPNsense/<Pluginname>/_macros.volt
 #
 # Includes several universal functions, and attachments for convenience.
 #
 # All comments encapsulated in Javascript friendly notation so JS syntax
 # highlighting works correctly.
 #}

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
                        this_callback_ok();
                    },
                false,
                callback_fail=
                    function(data, status){
                        dfObj.reject();
                        this_callback_fail();
                    }
            );
        } else {
                dfObj.reject();
                this_callback_fail();
        }
    }


    function ajaxDataDialog(data, dialog_title){
        if (data['message'] != '' ) {
            var message = data['message']
        } else {
            var message = JSON.stringify(data)
        }
        BootstrapDialog.show({
            type:BootstrapDialog.TYPE_WARNING,
            title: dialog_title,
            message: message,
            draggable: true
        });
    }

{#/*
     * standard data mapper to map json request data to forms on this page
     * @param data_get_map named array containing form names and source url's to get data from {'frm_example':"/api/example/settings/get"};
     * @param server_params parameters to send to server
     * @return promise object, resolves when all are loaded
     */#}
    function mapDataToUI(server_params) {
        const dfObj = new $.Deferred();

{#/*    // calculate number of items for deferred object to resolve */#}
        let elements = $('[data-model-name][data-model-endpoint]');

        if (server_params === undefined) {
            server_params = {};
        }

        const collected_data = {};
        elements.each(function(index) {
            let model_name = $( this ).data('model-name');
            let model_endpoint = "/api/{{ plugin_api_name }}/" + $( this ).data('model-endpoint');
            let element = $(this);
            ajaxGet(model_endpoint,server_params , function(data, status) {
                if (status === "success") {
{#*/                    // related form found, load data */#}
                        setFormData(element.attr('id'), data);
                        collected_data[element.attr('id')] = data;
                }
                if (index === elements.length - 1) {
                    dfObj.resolve(collected_data);
                }
            });
        });

        return dfObj;
    }



{#/*
    # Save event handlers for all defined forms
    # This uses jquery selector to match all button elements with id starting with "save_frm_" */#}
    $('a[id^="drp_frm_"][id$="_save"],button[id^="btn_frm_"][id$="_save"]').each(function(){
        $(this).click(function() {
            const dfObj = new $.Deferred();
            var this_frm = $(this).closest("form");
            if ($(this).attr('type') == "button") {
                var this_btn = $(this);
            } else {
                var this_btn = $(this).closest('div').find('button').first();
            }

            busyButton(this_btn);

            saveForm(this_frm, dfObj);

            clearButtonAndToggle(this_btn)

            return dfObj;
        });
    });


{#/*
    # Perform save and reconfigure for single form. */#}
    $('a[id^="drp_frm_"][id$="_save_apply"],button[id*="btn_frm_"][id$="_save_apply"]').click(function() {
        const saveObj = new $.Deferred();
        const reconObj = new $.Deferred();
        var this_btn = $(this);
        var this_frm = $(this).closest("form");
        busyButton(this_btn);
        saveForm(this_frm, saveObj, reconfigureService, [this_btn, reconObj, clearButtonAndToggle, [this_btn]]);

        return { saveObj, reconObj };
    });


{#/*
    # Save event handler for the Save All button.
    # The ID should be unique and derived from the form data. */#}
    $('a[id^="drp_{{ plugin_safe_name }}_save"],button[id="btn_{{ plugin_safe_name }}_save_all"]').click(function() {
        const dfObj = new $.Deferred();
        if ($(this).attr('type') == "button") {
            var this_btn = $(this);
        } else {
            var this_btn = $(this).closest('div').find('button').first();
        }
{#/*    # Turn on the spinner animation for the button to indicate activity. */#}
        busyButton(this_btn);
        var models = $('form[id^="frm_"][data-model]').map(function() {
{#          # Create a deferred object to pass to the function and wait on. #}
            const model_dfObj = new $.Deferred();
            saveForm($(this), model_dfObj);
            return model_dfObj
        });
        $.when(...models.get()).then(function() {
            dfObj.resolve();
        });
{#/*    # Clear the button state, and trigger an Apply toggle check. */#}
        clearButtonAndToggle(this_btn)

        return dfObj;
    });

{#/*
    # Save event handler for the Save and Apply All button.
    # The ID should be unique and derived from the form data. */#}
    $('a[id^="drp_{{ plugin_safe_name }}_save_apply_all"],button[id="btn_{{ plugin_safe_name }}_save_apply_all"]').click(function() {
        const reconObj = new $.Deferred();
        var this_btn = $(this);

        busyButton(this_btn);

        var models = $('form[id^="frm_"][data-model]').map(function() {
{#          # Create a deferred object to pass to the function and wait on. #}
            const model_dfObj = new $.Deferred();
            saveForm($(this), model_dfObj);
            return model_dfObj
        });
        $.when(...models.get()).then(function() {
{#/*        # when done, disable progress animation. */#}
            reconfigureService(this_btn, reconObj, clearButtonAndToggle, [this_btn]);
            dfObj.resolve();
        });
        return recon_dfObj;
    });
