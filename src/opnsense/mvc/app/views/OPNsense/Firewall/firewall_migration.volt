
<script>
    $(document).ready(function() {
        $("#remove_rules").click(function(){
          BootstrapDialog.show({
              type:BootstrapDialog.TYPE_WARNING,
              title: '{{ lang._('Flush') }}',
              message: "{{ lang._('Are you sure you want to remove all legacy firewall rules.') }}",
              buttons: [{
                  label: '{{ lang._('Yes') }}',
                  action: function(dialogRef){
                      dialogRef.close();
                      $("#flushAct_progress").addClass("fa fa-spinner fa-pulse");
                      ajaxCall("/api/firewall/migration/flush", {}, function(data,status) {
                            window.location = '/ui/firewall/filter/';
                      });

                  }
              },{
                  label: '{{ lang._('No') }}',
                  action: function(dialogRef){
                      dialogRef.close();
                  }
              }]
          });
        });
    });
</script>
<style>
    div.miglist {
        counter-reset: list-number;
        margin:10px;
    }
    div.miglist div:before {
        counter-increment: list-number;
        content: counter(list-number);

        margin-right: 10px;
        margin-bottom:10px;
        width:30px;
        height:30px;
        display:inline-flex;
        align-items:center;
        justify-content: center;
        font-size:14px;
        background-color:#d7385e;
        border-radius:50%;
        color:#fff;
    }
 </style>
<pre>
{{ lang._('
    To switch from the legacy rules to the new rules interface, a migration is needed.
    As this can be a risky operation, manual intervention is required.

    This module assist you in moving your rules to the new application and offers pointers to
    various components available to guide you through the process.

    When using a ZFS based setup, you can use snapshots to revert back to the old situation when accidents happen,
    The other option is to use configuration history to undo changes or backup your configuration [1].

    To prevent being locked out during the process, enable the anti-lockout rule and make sure to access the machine
    via your LAN interface [2].

    With all preparations in place, we can export the rules into a format our new rules interface understands [3].

    {tip} Use a tool like Microsoft Excel to inspect and modify rules in the csv file before importing them or when certain validations fail.

    Now we can import the exsiting rules into the new user interface [4].

    After validating the rules are as expected, you can remove all legacy rules via [5] which forwards you to the new rules page after completion.

') }}
</pre>
<div class="tab-content content-box">
    <div class="miglist">
        <div> <i class="fa fa-fw fa-book"></i>
              <a target="_new" href="https://docs.opnsense.org/manual/snapshots.html">{{ lang._('Snapshots')}} /
              <a target="_new" href="https://docs.opnsense.org/manual/backups.html#history">{{ lang._('Configuration history')}}</a>
        </div>
        <div>
            <i class="fa fa-fw fa-check"></i>
            <a target="_new" href="/system_advanced_firewall.php">{{ lang._('Deselect anti-lockout in advanced settings')}}</a>
        </div>
        <div>
            <i class="fa fa-fw fa-file-csv"></i>
            <a href="/api/firewall/migration/download_rules" >{{ lang._('Export current rules')}}</a>
        </div>
        <div>
            <i class="fa fa-fw fa-upload"></i>
            <a target="_new" href="/ui/firewall/filter/" >{{ lang._('Import rules using the button in the grid footer')}}</a>
        </div>
        <div>
            <i class="fa fa-fw fa-trash"></i>
            <a id="remove_rules" style="cursor: pointer;">{{ lang._('Remove all legacy rules')}}</a>
        </div>
    </div>
</div>
