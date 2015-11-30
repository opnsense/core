/**
 *    Copyright (C) 2015 Deciso B.V.
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *    shared components to use with legacy pages
 */

/**
 * hook on change events to network inputs, to maximize the subnet to 24 on ipv4 addresses
 * @param classname: classname to hook on to, select list of netmasks
 * @param data_id: data field reference to network input field
 * @param callback: callback function if any
 */
function hook_ipv4v6(classname, data_id, callback) {
  $("."+classname).each(function(){
      var selectlist_id = $(this).attr('id');
      if ($(this).data(data_id) != undefined) {
        $("#"+$(this).data(data_id)).change(function(){
          var itemValue = $(this).val();
          $("#"+selectlist_id+" > option").each(function() {
              if (parseInt($(this).val()) > 32 && itemValue.indexOf(":") == -1 ) {
                  $(this).addClass('hidden');
              } else {
                  $(this).removeClass('hidden');
              }
          });
          if (callback != undefined) {
              callback();
          }
        });
      }
      // trigger initial onChange event
      $("#"+$(this).data(data_id)).change();
  });
}
