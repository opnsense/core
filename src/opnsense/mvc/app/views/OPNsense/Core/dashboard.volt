{#
 # Copyright (c) 2024 Deciso B.V.
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1. Redistributions of source code must retain the above copyright notice,
 #    this list of conditions and the following disclaimer.
 #
 # 2. Redistributions in binary form must reproduce the above copyright notice,
 #    this list of conditions and the following disclaimer in the documentation
 #    and/or other materials provided with the distribution.
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

{% set theme_name = ui_theme|default('opnsense') %}
<!-- required for gridstack calculations -->
<link href="{{ cache_safe('/ui/css/gridstack.min.css') }}" rel="stylesheet">
<!-- required for any amount of columns < 12 -->
<link href="{{ cache_safe('/ui/css/gridstack-extra.min.css') }}" rel="stylesheet">
<!-- gridstack core -->
<script src="{{ cache_safe('/ui/js/gridstack-all.min.js') }}"></script>
<script src="{{ cache_safe('/ui/js/opnsense_widget_manager.js') }}"></script>
<script src="{{ cache_safe('/ui/js/moment-with-locales.min.js') }}"></script>
<script src="{{ cache_safe('/ui/js/chart.min.js') }}"></script>
<script src="{{ cache_safe('/ui/js/chartjs-plugin-streaming.min.js') }}"></script>
<script src="{{ cache_safe('/ui/js/chartjs-plugin-colorschemes.js') }}"></script>
<script src="{{ cache_safe('/ui/js/chartjs-adapter-moment.js') }}"></script>
<script src="{{ cache_safe('/ui/js/smoothie.js') }}"></script>
<link rel="stylesheet" type="text/css" href="{{ cache_safe(theme_file_or_default('/css/dashboard.css', theme_name)) }}" rel="stylesheet" />

<script>
$( document ).ready(function() {
    let widgetManager = new WidgetManager({
        float: false,
        column: 5,
        margin: 10,
        alwaysShowResizeHandle: false,
        sizeToContent: true,
        resizable: {
            handles: 'all'
        }
    }, {
        'save': "{{ lang._('Save') }}",
        'restore': "{{ lang._('Restore default layout') }}",
        'addwidget': "{{ lang._('Add Widget') }}",
        'add': "{{ lang._('Add') }}",
        'cancel': "{{ lang._('Cancel') }}",
    });
    widgetManager.initialize();
});
</script>

<div class="grid-stack"></div>
