{#
 # Copyright (c) 2020-2024 Deciso B.V.
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or withoutmodification,
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

<script>
    $( document ).ready(function() {
      $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
          $(".tab-icon").removeClass("fa-refresh");
          if ($("#"+e.target.id).data('tree-target') !== undefined) {
              $("#"+e.target.id).unbind('click').click(function(){
                ajaxGet($("#"+e.target.id).data('tree-endpoint'), {}, function (data, status) {
                    if (status == "success") {
                        update_tree(data, "#" + $("#"+e.target.id).data('tree-target'));
                    }
                });
              });
              if (!$("#"+e.target.id).hasClass("event-hooked")) {
                  $("#"+e.target.id).addClass("event-hooked")
                  $("#"+e.target.id).click();
              }
              $("#"+e.target.id).find(".tab-icon").addClass("fa-refresh");
          }

          $(window).trigger('resize');
      });

      /**
       * resize tree height
       */
      $(window).on('resize', function() {
          let new_height = $(".page-foot").offset().top -
                           ($(".page-content-head").offset().top + $(".page-content-head").height()) - 160;
          $(".treewidget").height(new_height);
          $(".treewidget").css('max-height', new_height + 'px');
      });


      /**
       * hook delayed live-search tree view
       */
      $(".tree_search").keyup(tree_delayed_live_search);

      // update history on tab state and implement navigation
      let selected_tab = window.location.hash != "" ? window.location.hash : "#{{default_tab}}";
      $('a[href="' +selected_tab + '"]').click();
      $('.nav-tabs a').on('shown.bs.tab', function (e) {
          history.pushState(null, null, e.target.hash);
      });
      $(window).on('hashchange', function(e) {
          $('a[href="' + window.location.hash + '"]').click()
      });
    });
</script>

<style>
    #treeview .bootstrap-dialog-body {
        overflow-x: auto;
    }

    #treeview .modal-dialog,
    #treeview .modal-content {
        height: 80%;
    }

    #treeview .modal-body {
        height: calc(100% - 120px);
        overflow-y: scroll;
    }

    @media (min-width: 768px) {
        #treeview .modal-dialog {
            width: 90%;
        }
    }

    #treeview .searchbox {
        margin: 8px;
    }

    #treeview .node-selected {
        font-weight: bolder;
    }
</style>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
{% for tab in tabs %}
    <li>
      <a data-toggle="tab" href="#{{tab['name']}}" id="{{tab['name']}}_tab"
         data-tree-target="{{tab['name']}}Tree"
         data-tree-endpoint="{{tab['endpoint']}}">
          {{tab['caption']}} <i class="fa tab-icon "></i>
      </a>
    </li>
{% endfor %}
</ul>
<div class="tab-content content-box" id="treeview">
{% for tab in tabs %}
    <div id="{{tab['name']}}" class="tab-pane fade in active">
      <div class="row">
          <section class="col-xs-12">
            <div class="content-box">
                <div class="searchbox">
                    <input
                        id="{{tab['name']}}Search"
                        type="text"
                        for="{{tab['name']}}Tree"
                        class="tree_search"
                        placeholder="{{ lang._('search')}}"
                    ></input>
                </div>
                <div class="treewidget" style="padding: 8px; overflow-y: scroll; height:400px;" id="{{tab['name']}}Tree"></div>
              </div>
          </section>
      </div>
    </div>
{% endfor %}
</div>
