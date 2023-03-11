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
 # THIS SOFTWARE IS PROVIDED "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
 # INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 # AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 # AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 # OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 # SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 # INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 # CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 # ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 # POSSIBILITY OF SUCH DAMAGE.
 #}
{# old button
<tr>
    <td colspan="3">
        <button
            class="btn btn-primary" id="{{ node_id|default('') }}"
            data-label="{{ lang._('%s') | format(node_label) }}"
{# /usr/local/opnsense/www/js/opnsense_ui.js:SimpleActionButton() #}
{# These fields are expected by the SimpleActionButton() to label, and attach click event. #}{#
            data-endpoint="{{ node.api|default('') }}"
            data-error-title="{{ lang._('%s') | format(node.error|default('')) }}"
            data-service-widget="{{node.widget|default('')}}"
            type="button"
        ></button>
    </td>
</tr>
#}
{# XXX could we just assume that if dropdowns are defined that we'll be using a group? probably
   We could assume button group if dropdown exists. We'd need to consider a few things if so.
   Such as specification conflicts like specifying action=primary while having dropdowns.
   Maybe throw, or silently ignore?
   What about different button types? Are there other kinds other than "droptdown-toggle"?
   For now will go with explicit.
   Also button can be type="SimpleActionButton", which should be a primary style button.
 #}
{#
 # Built-in buttons:
    Save
        Only saves the configuration, it doesn't reconfigure the service.
    Save and Apply
        Do save, and also reconfigure the service.
    Save Actions (both above buttons in a drop down)
        A group dropdown button, which offers the two above options in a dropwdown list.
    Apply
        This applies a save config. This is similar to what is present on the Firewall/Alias page.

    Save button notes XXX
        For dynamic creation and assignment as a built-in, the save function is going to locate the nearist parent form,
        thus, the label, and button ids will actually have to get their info from the <model> definition. These are already
        carried into the partial in params[].
 #}
{#
<?php
$string = <<<XML
<button type="group" icon="fa fa-floppy-o" label="Save Sources Settings" id="save_actions">
    <dropdown action="save" icon="fa fa-floppy-o">Save Only</dropdown>
    <dropdown action="save_apply" icon="fa fa-floppy-o">Save and Apply</dropdown>
</button>
XML;
$xml = simplexml_load_string($string);
?>
#}
<tr>
    <td colspan="3">
{#              # We set our own style here to put the button in a place that looks good. #}
        <div class="col-md-12">
{%  for this_button in this_node.button %}
{%      set this_button_id = get_xml_prop(this_button, 'id', true) %}
{%      set this_button_type = get_xml_prop(this_button, 'type')|default('primary') %}{# Assume primary if not defined #}
{%      set this_button_label = get_xml_prop(this_button, 'label', true) %}
{%      set this_button_style = get_xml_prop(this_button, 'style') %}
{%      set this_button_icon = this_button['icon']|default('') %}
{%      if this_button_type in ['primary', 'group' ] %}
{%          if this_button_type == 'primary' %}
{# Action is required for primary buttons. #}
{%              set this_button_action = get_xml_prop(this_button, 'action', true) %}
{%          endif %}
{%          if this_button_type == 'group' %}
    <div class="btn-group"
         id="{{ this_button_id }}">
        <button type="button"
                class="btn dropdown-toggle{{ this_button_style != '' ? " " ~ this_button_style : ' btn-default' }}"
                data-toggle="dropdown">
{%          elseif this_button_type == 'primary' %}
    <button id="btn_{{ this_button_id }}_{{ this_button_action }}"
            type="button"
            class="btn{{ this_button_style != '' ? " " ~ this_button_style : ' btn-primary' }}"
{%                  if this_button_action == 'SimpleActionButton' %}
{%                      set this_button_endpoint = get_xml_prop(this_button, 'endpoint', true) %}
            data-label="{{ this_button_label }}"
            data-endpoint="{{ this_button_endpoint }}"
            data-error-title="{{ this_button.error_title }}"
            {{ this_button.service_widget is defined ?
            'data-service-widget="' ~ this_button.service_widget ~ '"' : '' }}
{%                  endif %}
    >
{%          endif %}
        <i class="{{ this_button_icon }}"></i>
        &nbsp<b>{{ lang._('%s') | format(this_button_label) }}</b>&nbsp
        <i id="btn_{{ this_button_id }}_progress"></i>
{%          if this_button_type == 'group' %}
        <i class="caret"></i>
{%          endif %}
        </button>
{%          if this_button_type == 'group' %}
{%              if this_button.dropdown %}
            <ul class="dropdown-menu" role="menu">
{%                  for this_dropdown in this_button.dropdown %}
                <li>
                    <a id="drp_{{ this_button_id }}_{{ this_dropdown['action'] }}">
                        <i class="{{ this_dropdown['icon'] }}"></i>
                        &nbsp{{ lang._('%s') | format(this_dropdown['label']|default(this_dropdown[0])) }}
                    </a>
                </li>
{%                  endfor %}
            </ul>
{%              endif %}
{%          endif %}
{%          if this_button_type == 'group' %}
    </div>{# close the button group div #}
{%          endif %}
{%      endif %}
{%  endfor %}
        </div>{# clode div for style override #}
    </td>
</tr>


<script>
{#/*
 # =============================================================================
 # buttons: process any buttons accordingly for this field.
 # =============================================================================
 # Mainly for attaching SimpleActionButton function/event.
 #
 # XXX I don't really like how the confirm_dialog built-in is located here. Maybe it should be a function by itself,
 # and stored in js/funcitons.volt for now. All we'd need to do here is pass it the values from the XML,
 # title, message, and the selected button (this_button).
*/#}
{#/* Re-running through the buttons to create scripts. This keeps the javascript separate from the HTML above. */#}
{%  for this_button in this_node.button %}
{%      set this_button_action = get_xml_prop(this_button, 'action') %}
{%      set this_button_id = get_xml_prop(this_button, 'id', true) %}
{%      if this_button_action == 'SimpleActionButton' %}
        let this_button = $('button[id="btn_' + $.escapeSelector('{{ this_button_id }}') + '_SimpleActionButton"]')
    this_button.SimpleActionButton({
{#/*    We're defining onPreAction here in order to display a confirm dialog
        before executing the button's API call. */#}
        onPreAction:
{%          if this_button['builtin'] == 'confirm_dialog' %}
        function () {
{#/*        We create a defferred object here to hold the function
            from completing before input is received from the user. */#}
            const dfObj = new $.Deferred();
{#/*        stdDialogConfirm() doesn't return the result, i.e. cal1back
            If the user clicks cancel it doesn't execute callback(), so
            so it never comes back to this function. There is no way to
            clean up the spinner on the button if the user clicks cancel.
            So we're using the wrapper BootstrapDialog.confirm() directly. */#}
            BootstrapDialog.confirm({
                title: '{{ lang._('%s')|format(this_button.confirm_title) }} ',
                message: '{{ lang._('%s')|format(this_button.confirm_message) }}',
                type: BootstrapDialog.TYPE_WARNING,
                btnOKLabel: '{{ lang._('Yes') }}',
                callback: function (result) {
                    if (result) {
{#/*                    User answered yes, we can resolve dfObj now. */#}
                        dfObj.resolve();
                    } else {
{#/*                    User answered no, clean up the spinner added by SimpleActionButton(), and then do nothing. */#}
                        this_button.find('.reload_progress').removeClass("fa fa-spinner fa-pulse");
                    }
                }
            });
{#/*        This is used to prevent the function from completeing before
            getting input from the user first. Only gets returned after
            the dialog box has been dismissed. */#}
            return dfObj;
        }
{%          else %}
            {{ this_button.onpreaction|default('undefined') }}
{%          endif %}
,
        onAction: {{ this_button.onaction|default('undefined') }}
    });
{%      endif %}
{#/* XXX This is really specific to how the save/save and apply button is intended to be structured.
     These definitely need to be changed to functions so that they're more flexible. This whole section is very specific. */#}
{%          for this_dropdown in this_button.dropdown %}
/*
{{ dump(this_dropdown) }}
*/
{%              set this_dropwdown_action = get_xml_prop(this_dropdown, 'action', true) %}
{%              if this_dropwdown_action == 'save_form' %}
        $('a[id="drp_' + $.escapeSelector('{{ this_button_id }}') + '_save_form"]').click(function() {
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
{%              elseif this_dropwdown_action == 'save_form_apply' %}
        $('a[id="drp_' + $.escapeSelector('{{ this_button_id }}') + '_save_form_apply"]').click(function() {
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
{%              endif %}
{%          endfor %}
{%  endfor %}
</script>
