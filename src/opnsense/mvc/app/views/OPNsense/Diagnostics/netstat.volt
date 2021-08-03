{#
 # Copyright (c) 2020 Deciso B.V.
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

      /**
       * jqtree expects a list + dict type structure, transform key value store into expected output
       * https://mbraak.github.io/jqTree/#general
       */
      function dict_to_tree(node, path) {
          // some entries are lists, try use a name for the nodes in that case
          let node_name_keys = ['name', 'interface-name'];
          let result = [];
          if ( path === undefined) {
              path = "";
          } else {
              path = path + ".";
          }
          for (key in node) {
              if (typeof node[key] === "function") {
                  continue;
              }
              let item_path = path + key;
              if (node[key] instanceof Object) {
                  let node_name = key;
                  for (idx=0; idx < node_name_keys.length; ++idx) {
                      if (/^(0|[1-9]\d*)$/.test(node_name) && node[key][node_name_keys[idx]] !== undefined) {
                          node_name = node[key][node_name_keys[idx]];
                          break;
                      }
                  }
                  result.push({
                      name: node_name,
                      id: item_path,
                      children: dict_to_tree(node[key], item_path)
                  });
              } else {
                  result.push({
                      name: key,
                      value: node[key],
                      id: item_path
                  });
              }
          }
          return result;
      }

      function update_tree(endpoint, target)
      {
          ajaxGet(endpoint, {}, function (data, status) {
              if (status == "success") {
                  let $tree = $(target);
                  if ($(target + ' > ul').length == 0) {
                      $tree.tree({
                          data: dict_to_tree(data),
                          autoOpen: false,
                          dragAndDrop: false,
                          selectable: false,
                          closedIcon: $('<i class="fa fa-plus-square-o"></i>'),
                          openedIcon: $('<i class="fa fa-minus-square-o"></i>'),
                          onCreateLi: function(node, $li) {
                              if (node.value !== undefined) {
                                  $li.find('.jqtree-element').append(
                                      '&nbsp; <strong>:</strong> &nbsp;' + node.value
                                  );
                              }
                              if (node.selected) {
                                  $li.addClass("node-selected");
                              } else {
                                  $li.removeClass("node-selected");
                              }
                          }
                      });
                      // initial view, collapse first level if there's only one node
                      if (Object.keys(data).length == 1) {
                          for (key in data) {
                              $tree.tree('openNode', $tree.tree('getNodeById', key));
                          }
                      }
                  } else {
                      let curent_state = $tree.tree('getState');
                      $tree.tree('loadData', dict_to_tree(data));
                      $tree.tree('setState', curent_state);
                  }
              }
          });
      }

      $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
          $(".tab-icon").removeClass("fa-refresh");
          if ($("#"+e.target.id).data('tree-target') !== undefined) {
              $("#"+e.target.id).unbind('click').click(function(){
                  update_tree($("#"+e.target.id).data('tree-endpoint'), "#" + $("#"+e.target.id).data('tree-target'));
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
       * delayed live-search tree view
       */
      let apply_tree_search_timer = null;
      $(".tree_search").keyup(function(){
          let sender = $(this);
          clearTimeout(apply_tree_search_timer);
          apply_tree_search_timer = setTimeout(function(){
              let searchTerm = sender.val().toLowerCase();
              let target = $("#"+sender.attr('for'));
              let tree = target.tree("getTree");
              let selected = [];
              if (tree !== null) {
                  tree.iterate((node) => {
                      let matched = false;
                      if (searchTerm !== "") {
                          matched = node.name.toLowerCase().includes(searchTerm);
                          if (!matched && typeof node.value === 'string') {
                              matched = node.value.toLowerCase().includes(searchTerm);
                          }
                      }
                      node["selected"] = matched;

                      if (matched) {
                          selected.push(node);
                          if (node.isFolder()) {
                              node.is_open = true;
                          }
                          let parent = node.parent;
                          while (parent) {
                              parent.is_open = true;
                              parent = parent.parent;
                          }
                      } else if (node.isFolder()) {
                          node.is_open = false;
                      }

                      return true;
                  });
                  target.tree("refresh");
                  if (selected.length > 0) {
                      target.tree('scrollToNode', selected[0]);
                  }
              }
          }, 500);
      });

      // update history on tab state and implement navigation
      let selected_tab = window.location.hash != "" ? window.location.hash : "#interfaces";
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
  .searchbox {
    margin: 8px;
  }

  .node-selected {
      font-weight: bolder;
  }
</style>
<link rel="stylesheet" type="text/css" href="{{ cache_safe(theme_file_or_default('/css/jqtree.css', ui_theme|default('opnsense'))) }}">
<script src="{{ cache_safe('/ui/js/tree.jquery.min.js') }}"></script>

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
<div class="tab-content content-box">
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
