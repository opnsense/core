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

{#
 # This is a partial for an 'onoff' field, which is very similar to a radio button
 # with the 'button-group' built-in style, however, only includes two pre-defined
 # buttons: On, Off
 #
 # Example Usage in an XML:
 #  <field>
 #      <id>status</id>
 #      <label>dnscrypt-proxy status</label>
 #      <type>status</type>
 #      <style>label-opnsense</style>
 #      <labels>
 #          <success>clean</success>
 #          <danger>dirty</danger>
 #      </labels>
 #  </field>
 #
 # Example Model definition:
 #  <status type=".\PluginStatusField">
 #      <configdcmd>dnscryptproxy state</configdcmd>
 #  </status>
 #
 # Example partial call in a Volt tempalte:
 # {{ partial("OPNsense/Dnscryptproxy/layout_partials/fields/status",[
 #     this_field':this_field,
 #     'this_field_id':this_field_id
 # ]) }}
 #
 # Expects to be passed
 # this_field_id         The id of the field, includes model name. Example: settings.enabled
 # this_field       The field itself.
 # this_field.style A style to use by default.
 #
 # Available CSS styles to use:
 # label-primary
 # label-success
 # label-info
 # label-warning
 # label-danger
 # label-opnsense
 # label-opnsense-sm
 # label-opnsense-xs
 #}

        <input
            id="{{ this_field_id }}"
            type="text"
            class="form-control hidden">
            {{ (this_field.style|default('') == "classic") ?
            '<label id="lbl_'~this_field.id~'"></label><br>' : '' }}
        <label class="input-group-btn form-control"
               style="display: inline;">
            <label class="btn btn-default"
                   id="btn_{{ this_field_id }}_select"
{# XXX replace this with a builtin functionality. #}
{%      if this_field.style == "classic" %}
                    style="
                        padding: 2px;
                        padding-bottom: 3px;
                        width: 100%;"
{%      endif %}>
{# XXX Figure out how to attach a tooltip here #}
{# if we're using classic style, don't add icons. field may be overloaded,
    supposed to be css class(es) for other fields #}
{# XXX should be replaced with "builtin" functionality. #}
{%      if this_field.style|default("") != "classic" %}
                <i class="fa fa-fw fa-folder-o"
                   id="inpt_{{ this_field_id }}_icon">
                </i>
                <i id="inpt_{{ this_field_id }}_progress">
                </i>
{%      endif %}
                <input
                    type="file"
                    class="form-control
                        {{ (this_field.style|default("") != "classic") ?
                            'hidden' : '' }}"
                    for="{{ this_field_id }}"
                    accept="text/plain">
            </label>
        </label>
{%      if this_field.style|default("") != "classic" %}
{# if we're using classic style, no need to display this box
   Explicit style is used here for alignment with the downloadbox
   button, and matching the size of the button.
   This input element gets no id to prevent getFormData() from
   picking it up, using 'for' attr to identify. #}
{# XXX should replace with a pre-built/built-in style. #}
        <input
            class="form-control"
            type="text"
            readonly=""
            for="{{ this_field_id }}"
            style="height: 34px;
                   display: inline-block;
                   width: 161px;
                   vertical-align: middle;
                   margin-left: 3px;"
        >
{%      endif %}
{# This if statement is just to get the spacing between the
   download/upload buttons to be consistent #}
{# XXX should replace with a pre-built/built-in style. #}
{%      if this_field.style|default("") == "classic" %}
        &nbsp
{%      endif %}
        <button
            class="btn btn-default"
            type="button"
            id="btn_{{ this_field_id }}_upload"
            title="{{ lang._('%s')|format('Upload selected file')}}"
            data-toggle="tooltip"
        >
            <i class="fa fa-fw fa-upload"></i>
        </button>
        <button
            class="btn btn-default"
            type="button"
            id="btn_{{ this_field_id }}_download"
            title="{{ lang._('%s')|format('Download')}}"
            data-toggle="tooltip"
        >
            <i class="fa fa-fw fa-download"></i>
        </button>
        <button
            class="btn btn-danger"
            type="button"
            id="btn_{{ this_field_id }}_remove"
            title="{{ lang._('%s')|format('Remove')}}"
            data-toggle="tooltip"
        >
            <i class="fa fa-fw fa-trash-o"></i>
        </button>


<script>
{#/* =============================================================================
 # managefile: file selection
 # =============================================================================
 # Catching when a file is selected for upload.
 #
 # Requires creation of "this_namespace" object earlier in script.
 #
 # I think this mostly came from the Web Proxy plugin.
*/#}
{%  if this_field.api.upload %}
$("input[for=" + $.escapeSelector("{{ this_field_id }}") + "][type=file]").change(function(evt) {
{#/*      Check browser compatibility */#}
    if (window.File && window.FileReader && window.FileList && window.Blob) {
         var file_event = evt.target.files[0];
{#/*     If a file has been selected, let's get the content and file name. */#}
        if (file_event) {
            var reader = new FileReader();
            reader.onload = function(readerEvt) {
{#/*            Store these in our namespace for use in the upload function.
                This namespace was created at the beginning of the script section. */#}
                this_namespace.upload_file_content = readerEvt.target.result;
                this_namespace.upload_file_name = file_event.name;
{#/*                  Set the value of the input box we created to store the file name. */#}
                if ($("label[id='lbl_" + $.escapeSelector("{{ this_field_id }}") + "']").length){
                    $("label[id='lbl_" +
                      $.escapeSelector("{{ this_field_id }}") +
                      "']").text("{{ lang._('Current') }}: " +
                      this_namespace.upload_file_name);
                } else {
                    $("#" + $.escapeSelector("{{ this_field_id }}") +
                      ",input[for=" + $.escapeSelector("{{ this_field_id }}") +
                      "][type=text]").val(this_namespace.upload_file_name);
                }
            };
{#/*        Actually get the file, explicitly reading as text. */#}
            reader.readAsText(file_event);
        }
    } else {
{#/*    Maybe do something else if support isn't available for this API. */#}
        alert("{{ lang._('Your browser does not appear to support the HTML5 File API.') }}");
    }
});
{#/* Attach to the ready event for the field to trigger and update to the value of the visible elements. */#}
$('#' + $.escapeSelector('{{ this_field_id }}')).change(function(e){
    var file_name = $('#' + $.escapeSelector('{{ this_field_id }}')).val();
{#/*      Modern style */#}
    if ($('label[id="lbl_' + $.escapeSelector('{{ this_field_id }}') + '"]').length) {
        $('label[id="lbl_' + $.escapeSelector('{{ this_field_id }}') + '"]').text("Current: " + file_name);
    }
{#/*      Classic style */#}
    if ($('input[for="' + $.escapeSelector('{{ this_field_id }}') + '"][type=text]').length) {
        $('input[for="' + $.escapeSelector('{{ this_field_id }}') + '"][type=text]').val(file_name);
    }
});
{%  endif %}
{#/*
 # =============================================================================
 # managefile: file upload
 # =============================================================================
 # Upload activity of the selected file.
 #
 # Requires creation of "this_namespace" object earlier in script.
*/#}
{%  if this_field.api.upload %}
$("#btn_" + $.escapeSelector("{{ this_field_id }}" + "_upload")).click(function(){
{#/* Check that we have the file content. */#}
    if (this_namespace.upload_file_content) {
        ajaxCall("{{ field.api.upload }}", {'content': this_namespace.upload_file_content,'target': '{{ this_field_id }}'}, function(data,status) {
            if (data['error'] !== undefined) {
{#/*                      error saving */#}
                    stdDialogInform(
                        "{{ lang._('Status') }}: " + data['status'],
                        data['error'],
                        "{{ lang._('OK') }}",
                        function(){},
                        "warning"
                    );
            } else {
{#/*            Clear the file content since we're done, then save, reload, and tell user. */#}
                this_namespace.upload_file_content = null;
                saveFormAndReconfigure($("#btn_" + $.escapeSelector("{{ this_field_id }}" + "_upload")));
                stdDialogInform(
                    "{{ lang._('File Upload') }}",
                    "{{ lang._('Upload of ') }}"+ this_namespace.upload_file_name + "{{ lang._(' was successful.') }}",
                    "Ok"
                );
{#/*            No error occurred, so let set the setting for storage in the config. */#}
                $("#" + $.escapeSelector("{{ this_field_id }}")).val(this_namespace.upload_file_name);
            }
        });
    }
});
{%  endif %}
{#/*
 # =============================================================================
 # managefile: file download
 # =============================================================================
 # Download activity of the file that was uploaded.
*/#}
{%  if this_field.api.download %}
$("#btn_" + $.escapeSelector("{{ this_field_id }}") + "_download").click(function(){
    window.open('{{ this_field.api.download }}/{{ this_field_id }}');
{#/*    # Use blur() to force the button to lose focus.
        # This addresses a UI bug where after clicking the button, and after dismissing
        # the save dialog (either save or cancel), upon returning to the browser window
        # the button lights up, and displays the tooltip. It then gets stuck like that
        # after the user clicks somewhere in the browser window.
        # This appears to only happen on the download activity. */#}
    $(this).blur()
});
{%          endif %}
{#/*
 # =============================================================================
 # managefile: file remove
 # =============================================================================
 # Removing a file that was uploaded.
 #
 # Dialog structure came from the web proxy plugin.
*/#}
{%  if this_field.api.remove %}
$("#btn_" + $.escapeSelector("{{ this_field_id }}") + "_remove").click(function() {
    BootstrapDialog.show({
        type:BootstrapDialog.TYPE_DANGER,
        title: "{{ lang._('Remove File') }} ",
        message: "{{ lang._('Are you sure you want to remove this file?') }}",
        buttons: [{
            label: "{{ lang._('Yes') }}",
            cssClass: 'btn-primary',
            action: function(dlg){
                dlg.close();
                ajaxCall("{{ this_field.api.remove }}", {'field': '{{ this_field_id }}'}, function(data,status) {
                    if (data['error'] !== undefined) {
                        stdDialogInform(
                            data['error'],
                            "{{ lang._('API Returned:') }}\n" + data['status'],
                            "{{ lang._('OK') }}",
                            function(){},
                            "warning"
                        );
                    } else {
                        if ($("label[id='lbl_" + $.escapeSelector("{{ this_field_id }}") + "']").length){
                            $("label[id='lbl_" + $.escapeSelector("{{ this_field_id }}") + "']").text("{{ lang._('Current: ') }}");
                        } else {
                            $("#" + $.escapeSelector("{{ this_field_id }}") +
                              ",input[for=" + $.escapeSelector("{{ this_field_id }}") +
                              "][type=text]").val("");
                        }
                        saveFormAndReconfigure($("#btn_" + $.escapeSelector("{{ this_field_id }}") + "_remove"));
                        stdDialogInform(
                            "{{ lang._('Remove file') }}",
                            "{{ lang._('Remove file was successful.') }}",
                            "{{ lang._('Ok') }}"
                        );
                    }
                });
            }
        }, {
            label: "{{ lang._('No') }}",
            action: function(dlg){
                dlg.close();
            }
        }]
    });
});
{%  endif %}
</script>
