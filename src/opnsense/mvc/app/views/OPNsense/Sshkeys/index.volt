{#

    Copyright (C) 2018 Fabian Franz
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.


#}

<script type="text/javascript">
$( document ).ready(function() {
    $("#grid-known-hosts").UIBootgrid(
        { 'search':'/api/sshkeys/ssh/search_known_hosts',
          'get':'/api/sshkeys/ssh/get_known_host/',
          'set':'/api/sshkeys/ssh/set_known_host/',
          'add':'/api/sshkeys/ssh/add_known_host/',
          'del':'/api/sshkeys/ssh/del_known_host/',
          'options':{selection:false, multiSelect:false}
        }
    );
    $("#grid-ssh-keys").UIBootgrid(
        { 'search':'/api/sshkeys/ssh/search_key_pair',
          'get':'/api/sshkeys/ssh/get_key_pair/',
          'set':'/api/sshkeys/ssh/set_key_pair/',
          'add':'/api/sshkeys/ssh/add_key_pair/',
          'del':'/api/sshkeys/ssh/del_key_pair/',
          'options':{selection:false, multiSelect:false}
        }
    );
});

</script>
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#known-hosts">{{ lang._('Known Hosts') }}</a></li>
    <li><a data-toggle="tab" href="#key-pairs">{{ lang._('Key Pairs') }}</a></li>
</ul>

<div class="tab-content content-box tab-content" style="padding-bottom: 1.5em;">
    <div id="known-hosts" class="tab-pane fade in active">
        <div class="alert alert-info" role="alert">
          {{ lang._('Known hosts can be used to harden SSH connections by whitelisting the public keys of servers (also known as public key pinning).') }}
        </div>
        <table id="grid-known-hosts" class="table table-responsive" data-editDialog="knownhostdlg">
          <thead>
              <tr>
                  <th data-column-id="host" data-type="string" data-visible="true">{{ lang._('Host') }}</th>
                  <th data-column-id="key_type" data-type="string" data-visible="true">{{ lang._('Key Type') }}</th>
                  <th data-column-id="commands" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
              </tr>
          </thead>
          <tbody>
          </tbody>
          <tfoot>
              <tr>
                  <td colspan="2"></td>
                  <td>
                      <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                  </td>
              </tr>
          </tfoot>
      </table>
    </div>
    <div id="key-pairs" class="tab-pane fade in">
        <table id="grid-ssh-keys" class="table table-responsive" data-editDialog="keypairdlg">
          <thead>
              <tr>
                  <th data-column-id="key_name" data-type="string" data-visible="true">{{ lang._('Name') }}</th>
                  <th data-column-id="commands" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
              </tr>
          </thead>
          <tbody>
          </tbody>
          <tfoot>
              <tr>
                  <td colspan="1"></td>
                  <td>
                      <button data-action="add" type="button" class="btn btn-xs btn-default"><span class="fa fa-plus"></span></button>
                  </td>
              </tr>
          </tfoot>
      </table>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields': known_host_form,'id':'knownhostdlg', 'label':lang._('Edit Known Host')]) }}
{{ partial("layout_partials/base_dialog",['fields': key_pair_form,'id':'keypairdlg', 'label':lang._('Edit Key Pair')]) }}
