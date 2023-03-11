{##
 #
 # OPNsense® is Copyright © 2022 – 2018 by Deciso B.V.
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
 # THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
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

{##
 # This is the main template for this plugin, and service as the base for all
 # of its pages. All of the other volt templates extend this one, allowing
 # for easy updates, and a consistent user experience.
 #
 # There are variables that should be provided in the view.
 # These are commonly set in the calling controller.
 #
 # Variables:
 # plugin_safe_name string           a safe name for the plugin
 #                                   that doesn't include any unusual characters.
 # plugin_label     string           a plain language label for the plugin
 #                                   for use in dialog titles, and such
 # this_xml         SimpleXMLObject  this is the SimpleXMLObject of the form to render
 #                                   commonly set by the calling controller
 #}

<?php ob_start(); ?>
{# Include javascript functions for use throughout. #}
<script>
{% include "js/functions.volt" %}

{% block data_get_map %}
{% include "js/data_get_map.volt" %}
{% endblock %}
</script>

{# Pull in our macro definitions. #}
{% include "_macros.volt" %}
{# Define some styles. #}
{% include "_styles.volt" %}

{% block body %}
{# Build the entire page including:
    tab headers,
    tabs content (include fields and bootgrids),
    and all bootgrid dialogs #}
{{ build_xml(this_xml, lang, [
    'plugin_api_name': plugin_api_name,
    'plugin_safe_name': plugin_safe_name,
    'plugin_label':plugin_label]) }}
{% endblock %}

<script>
$( document ).ready(function() {

{#/*
    Add in any dynamic script content, functions, and other stuff. */#}
{%   include "_script.volt" %}

{#/* If no dialog message is defined, skip loading the dialog box at all. Maybe not the best place for this. */#}
{%  if this_xml['loading_dialog_msg'] %}
{#/*
    Conditionally display this dialog only if we actually have data to load in mapDataToFormUI() */#}
    if ($('[data-model][data-model-endpoint]').length) {
        BootstrapDialog.show({
            title: 'Loading settings',
            closable: false,
            message:
                '{{ lang._('%s') | format(this_xml['loading_dialog_msg']) }}' +
                '&nbsp&nbsp<i class="fa fa-cog fa-spin"></i>'
            });
    }
{%  endif %}

{% block script %}
{% endblock %}

{#/*
    # Adds a hash tag to the URL for tabs, for example: http://opnsense/ui/plugin/settings#subtab_schedules
    # update history on tab state and implement navigation From the firewall plugin */#}
    if (window.location.hash != "") {
        $('a[href="' + window.location.hash + '"]').click();
    }

    $('.nav-tabs a').on('shown.bs.tab', function (e) {
        history.pushState(null, null, e.target.hash);
    });

{#/*
    # Update the service controls any time the page is loaded.
    # This makes a call to /api/{{ plugin_api_name }}/service/status */#}
    updateServiceControlUI('{{ plugin_api_name }}');

});
</script>

{# Clean up the blank lines in html output, probably inefficient, but makes things look nice when debugging. #}
<?php  echo join("\n", array_filter(array_map(function ($i) { $o = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "", $i); if (!empty(trim($o))) {return $o;} }, explode("\n", ob_get_clean()))));  ?>
