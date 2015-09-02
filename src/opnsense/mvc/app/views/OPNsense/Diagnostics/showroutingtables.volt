{#

OPNsense® is Copyright © 2014 – 2015 by Deciso B.V.
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
<div id="filterdiv"></div>

<div id="routingt"></div>

<script>

var routingdata = undefined;

function escape_and_create_tag(tag, content)
{
  return ('<' + tag + '>' + ($('<' + tag + '></' + tag + '>').text(content).html()) + '</' + tag + '>');
}

function escape(string) {
  return $('<p></p>').text(string).html();
}

window.routingstrings = {};
window.routingstrings['Destination'] = '{{ lang._('Destination')|escape_js }}';
window.routingstrings['Gateway'] = '{{ lang._('Gateway')|escape_js }}';
window.routingstrings['Flags'] = '{{ lang._('Flags')|escape_js }}';
window.routingstrings['Use'] = '{{ lang._('Use')|escape_js }}';
window.routingstrings['Mtu'] = '{{ lang._('MTU')|escape_js }}';
window.routingstrings['Netif'] = '{{ lang._('Interface')|escape_js }}';
window.routingstrings['Expire'] = '{{ lang._('Expire')|escape_js }}';

window.routingstrings['No data found.'] = '{{ lang._('No data found.')|escape_js }}';

function translate_string(str) {
  if (str in window.routingstrings)
    str = window.routingstrings[str];
  return str;
}

function filter_table(data,filter) {
  keys = Object.keys(filter);
  arr = [];
  data.forEach(function(row) {
    var pass = true;
    keys.forEach(function(key){
      var regex = new RegExp(filter[key], 'i');
      if (key in row)
      {
        if (!row[key].match(regex))
          pass = false;
      }
      else
      {
        pass = false;
      }
    });
    if (pass) {
      arr.push(row);
    }
  });
  return arr;
}

window.routefilter = {}

function update_filter() {
  var configuration = {}
  $('.myfiltertable input').each(function(idx, value) {
    configuration[value.name.substring(6,value.name.length)] = value.value;
  });
  window.routefilter = configuration;
  update_gui(window.routingdata);
}


function create_filter_form(data) {
  if ($('#filterdiv').html() != "")
    return 0;
  tables = Object.keys(data);
  keylist = [];
  tables.forEach(function(table) {
    keys = Object.keys(data[table][0]);
    keys.forEach(function(key) {
      if ((jQuery.inArray(key, keylist)) == -1)
        keylist.push(key)
    });
  });
  // create form
  var mform = '<h1>Filter</h1><table class="table table-striped myfiltertable">';
  keylist.forEach(function(key) {
    mform += '<tr><td><label for="filter' + escape(key) + '">' + escape(translate_string(key)) + '</label></td><td><input type="text" name="filter' + escape(key) + '" id="filter' + escape(key) + '" /></td></tr>';
  });
  mform += '</table>';
  $('#filterdiv').html(mform);
  $('.myfiltertable input').keyup(update_filter);
  //return mform;
}



function update_gui(data) {
  keys = Object.keys(data);
  var res_string = "";
  keys.forEach(function (key) {
    dat = filter_table(data[key],window.routefilter);
    res_string += escape_and_create_tag("h1",key);
    if (dat.length > 0)
    {
      res_string += '<table class="table table-striped"><thead><tr>';
      titles = Object.keys(dat[0]);
      titles.forEach(function (title) {
        res_string += escape_and_create_tag('th',translate_string(title));
      });
      res_string += "</tr></thead><tbody>";
      dat.forEach(function (line) {
        res_string += "<tr>";
        titles.forEach(function (title) {
        //
          res_string += escape_and_create_tag("td", line[title]);
        });
        res_string += '</tr>';
      });
      res_string += "</tbody></table>";
    }
    else
    {
      res_string += "<div>" + translate_string('No data found.') + "</div>";
    }
    window.debug = res_string;
    $('#routingt').html(res_string);
  });
}

function refresh()
{
  $.get( "/api/diagnostics/index/loadroutingtable", function( data ) {
    routingdata = data;
    update_gui(data);
    create_filter_form(data);
  });
}

setInterval(refresh() , 10000);
</script>
