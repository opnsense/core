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
  $( document ).ready(function() {
    $("#{{formGridKeyPair['table_id']}}").UIBootgrid({
      search: '/api/ipsec/key_pairs/search_item',
      get: '/api/ipsec/key_pairs/get_item/',
      set: '/api/ipsec/key_pairs/set_item/',
      add: '/api/ipsec/key_pairs/add_item/',
      del: '/api/ipsec/key_pairs/del_item/',
    });

    // move "generate key" inside form dialog
    $("#row_keyPair\\.keyType > td:eq(1) > div:last").before($("#keygen_div").detach().show());
    // hook key generation option selection and action
    $("#keyPair\\.keyType").change(function(){
        let ktype = $(this).val();
        $("#keysize").find("option").hide();
        $("#keysize").find("option[data-type='" + ktype + "']").show();
        if (ktype == 'rsa') {
            $("#keysize").val("2048");
        } else {
            $("#keysize").val("384");
        }
        $("#keysize").selectpicker('refresh');
    });
    $("#keygen").click(function(){
        let ktype = $("#keyPair\\.keyType").val();
        let ksize = $("#keysize").val();
        ajaxGet("/api/ipsec/key_pairs/gen_key_pair/" + ktype + "/" + ksize, {}, function(data, status){
            if (data.status && data.status === 'ok') {
                $("#keyPair\\.publicKey").val(data.pubkey);
                $("#keyPair\\.privateKey").val(data.privkey);
            }
        });
    })

    $("#reconfigureAct").SimpleActionButton();
    updateServiceControlUI('ipsec');
  });
</script>

<div class="content-box">
    <span id="keygen_div" style="display:none" class="pull-right">
          <select id="keysize" class="selectpicker" data-width="100px">
              <option data-type='rsa' value="1024">1024</option>
              <option data-type='rsa' value="2048">2048</option>
              <option data-type='rsa' value="3072">3072</option>
              <option data-type='rsa' value="4096">4096</option>
              <option data-type='rsa' value="8192">8192</option>
              <option data-type='ecdsa' value="256">NIST P-256</option>
              <option data-type='ecdsa' value="384">NIST P-384</option>
              <option data-type='ecdsa' value="521">NIST P-521</option>
          </select>
          <button id="keygen" type="button" class="btn btn-secondary" title="{{ lang._('Generate new.') }}" data-toggle="tooltip">
            <i class="fa fa-fw fa-gear"></i>
          </button>
    </span>
    {{ partial('layout_partials/base_bootgrid_table', formGridKeyPair)}}
</div>

{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/ipsec/service/reconfigure'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogKeyPair,'id':formGridKeyPair['edit_dialog_id'],'label':lang._('Edit key pair')]) }}
