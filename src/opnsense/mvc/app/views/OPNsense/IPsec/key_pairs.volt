{#
 # Copyright (C) 2019 Pascal Mathis <mail@pascalmathis.com>
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without
 # modification, are permitted provided that the following conditions are met:
 #
 # 1. Redistributions of source code must retain the above copyright notice,
 #    this list of conditions and the following disclaimer.
 #
 # 2. Redistributions in binary form must reproduce the above copyright
 #   notice, this list of conditions and the following disclaimer in the
 #    documentation and/or other materials provided with the distribution.
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
 #}

<script>
  $(function () {
    const $applyLegacyConfig = $('#applyLegacyConfig');
    const $applyLegacyConfigProgress = $('#applyLegacyConfigProgress');
    const $responseMsg = $('#responseMsg');
    const $dirtySubsystemMsg = $('#dirtySubsystemMsg');

    // Helper method to fetch the current status of the legacy subsystem for viewing/hiding the "pending changes" alert
    function updateLegacyStatus() {
      ajaxCall('/api/ipsec/legacy-subsystem/status', {}, function (data, status) {
        if (data['isDirty']) {
          $responseMsg.addClass('hidden');
          $dirtySubsystemMsg.removeClass('hidden');
        } else {
          $dirtySubsystemMsg.addClass('hidden');
        }
      });
    }

    // Apply config in legacy subsystem
    $applyLegacyConfig.on('click', function (e) {
      e.preventDefault();

      $applyLegacyConfig.prop('disabled', true);
      $applyLegacyConfigProgress.addClass('fa fa-spinner fa-pulse');

      ajaxCall('/api/ipsec/legacy-subsystem/applyConfig', {}, function (data, status) {
        // Preliminarily hide the "pending changes" alert and display the response message if available
        if (data['message']) {
          $dirtySubsystemMsg.addClass('hidden');
          $responseMsg.removeClass('hidden').text(data['message']);
        }

        // Reset the state of the "apply changes" button
        $applyLegacyConfig.prop('disabled', false);
        $applyLegacyConfigProgress.removeClass('fa fa-spinner fa-pulse');

        // Fetch the current legacy subsystem status to ensure changes have been processed
        updateLegacyStatus();
        updateServiceControlUI('ipsec');
      });
    });

    // Initialize grid for displaying and manipulating key pairs
    const $grid = $('#grid-key-pairs').UIBootgrid({
      search: '/api/ipsec/key-pairs/searchItem',
      get: '/api/ipsec/key-pairs/getItem/',
      set: '/api/ipsec/key-pairs/setItem/',
      add: '/api/ipsec/key-pairs/addItem/',
      del: '/api/ipsec/key-pairs/delItem/',
    });

    // Refresh status of legacy subsystem when grid has changed
    $grid.on('loaded.rs.jquery.bootgrid', updateLegacyStatus);
    updateServiceControlUI('ipsec');
  });
</script>

<div class="alert alert-info alert-dismissible hidden" role="alert" id="responseMsg"></div>
<div class="alert alert-info hidden" role="alert" id="dirtySubsystemMsg">
    <button class="btn btn-primary pull-right" type="button" id="applyLegacyConfig">
        <i id="applyLegacyConfigProgress" class=""></i>
        {{ lang._('Apply changes') }}
    </button>
    <div>
        {{ lang._('The IPsec tunnel configuration has been changed.') }}<br/>
        {{ lang._('You must apply the changes in order for them to take effect.') }}
    </div>
</div>
<div class="content-box">
    <table id="grid-key-pairs" class="table table-condensed table-hover table-striped" data-editDialog="DialogKeyPair">
        <thead>
        <tr>
            <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
            <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
            <th data-column-id="keyType" data-width="20em" data-type="string">{{ lang._('Key Type') }}</th>
            <th data-column-id="keySize" data-width="20em" data-type="number">{{ lang._('Key Size') }}</th>
            <th data-column-id="keyFingerprint" data-type="string">{{ lang._('Key Fingerprint') }}</th>
            <th data-column-id="commands" data-width="7em" data-formatter="commands"
                data-sortable="false">{{ lang._('Commands') }}</th>
        </tr>
        </thead>
        <tbody></tbody>
        <tfoot>
        <tr>
            <td></td>
            <td>
                <button data-action="add" type="button" class="btn btn-xs btn-primary">
                    <span class="fa fa-fw fa-plus"></span>
                </button>
                <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default">
                    <span class="fa fa-fw fa-trash-o"></span>
                </button>
            </td>
        </tr>
        </tfoot>
    </table>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogKeyPair,'id':'DialogKeyPair','label':lang._('Edit key pair')]) }}
