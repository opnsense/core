{##
 # OPNsense® is Copyright © 2022 by Deciso B.V.
 # Copyright (C) 2022 agh1467@protonmail.com
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

{##
 # Builds input dialog for bootgrids, uses the following parameters (as associative array):
 #
 # this_grid['target']              :   id of the dialog's parent field.
 # this_grid.dialog          :   array dialog defined for the bootgrid
 # this_grid.dialog['label'] :   Label for the dialog
 # this_grid.dialog['field'] :   array of fields for this dialog
 #
 # msgzone_width, hasSaveBtn https://github.com/opnsense/core/commit/4c736c65060c926ecc9eb7539b93454559e9d2d4
 #}
{%      set this_grid_id = get_xml_prop(this_grid, 'id') %}
{%      set dialog_elements = this_grid.dialog %}
{%      set dialog_label = get_xml_prop(dialog_elements, 'label') %}
{#      Find if there are help supported or advanced field on this page #}
{%      set dialog_help = false %}
{%      set dialog_advanced = false %}

{# Grab the node from the dialog element. #}
{%      set grid_id = get_xml_prop(this_grid, 'id') %}
{# <?php $model = explode(".", $grid_id)[1]; ?> #}

{%  set help = this_grid.xpath('//*/field/help') ? true : false %}
{%  set advanced = this_grid.xpath('//*/field/advanced') ? true : false %}

{# The id here has to match the same value as is populated in data-editDialog attribute on the bootgrid table. #}
<div class="modal fade"
     id="bootgrid_dialog_{{ this_grid_id }}"
     tabindex="-1"
     role="dialog"
     aria-labelledby="{{ this_grid_id }}_Label"
     aria-hidden="true">
    <div class="modal-backdrop fade in"></div>
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button"
                        class="close"
                        data-dismiss="modal"
                        aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title"
                    id="{{ this_grid_id }}_Label">
                    {{ dialog_label|default('Edit') }}
                </h4>
            </div>
            <div class="modal-body">
{#              Must match what's defined in data-editDialog attribute on bootgrid table: params['set']+uuid, 'frm_' + editDlg, function(){ #}
                <form id="frm_bootgrid_dialog_{{ this_grid_id }}">
{{      partial("layout_partials/base_table",[
                'this_part': this_grid.dialog.children(),
                'model': grid_id,
                'lang': lang,
                'params': params
]) }}
                </form>
            </div>
            <div class="modal-footer">
{# XXX Need to comment where this flag is set from. #}
{%      if hasSaveBtn|default('true') == 'true' %}
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ lang._('Cancel') }}</button>
                <button type="button" class="btn btn-primary" id="btn_bootgrid_dialog_{{ this_grid_id }}_save">{{ lang._('Save') }} <i id="btn_bootgrid_dialog_{{ this_grid_id }}_save_progress" class=""></i></button>
{%      else %}
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ lang._('Close') }}</button>
{%      endif %}
            </div>
        </div>
    </div>
</div>

{# Clean up the node value as it shouldn't leave this dialog. #}
{%      set params['node'] = '' %}
