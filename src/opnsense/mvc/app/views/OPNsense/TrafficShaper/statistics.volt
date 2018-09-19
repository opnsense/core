{#

This file is Copyright © 2018 by Michael Muenz <m.muenz@gmail.com>
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

1.  Redistributions of source code must retain the above copyright notice,
    this list of conditions and the following disclaimer.

2.  Redistributions in binary form must reproduce the above copyright notice,
    this list of conditions and the following disclaimer in the documentation
    and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
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

<!-- Navigation bar -->
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#showsched">{{ lang._('Scheduler') }}</a></li>
    <li><a data-toggle="tab" href="#showqueue">{{ lang._('Queues') }}</a></li>
</ul>

<div class="tab-content content-box tab-content">
    <div id="showsched" class="tab-pane fade in active">
      <pre id="listshowsched"></pre>
    </div>
    <div id="showqueue" class="tab-pane fade in">
      <pre id="listshowqueue"></pre>
    </div>
</div>

<script>

// Put API call into a function, needed for auto-refresh
function update_showsched() {
    ajaxCall(url="/api/trafficshaper/statistics/showsched", sendData={}, callback=function(data,status) {
        $("#listshowsched").text(data['response']);
    });
}

function update_showqueue() {
    ajaxCall(url="/api/trafficshaper/statistics/showqueue", sendData={}, callback=function(data,status) {
        $("#listshowqueue").text(data['response']);
    });
}

$( document ).ready(function() {

    // Call function update_showsched and update_showqueue with a auto-refresh of 1 second
    setInterval(update_showsched, 1000);
    setInterval(update_showqueue, 1000);

});
</script>
