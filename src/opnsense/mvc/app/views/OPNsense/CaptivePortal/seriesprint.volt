{#

OPNsense® is Copyright © 2014 – 2015 by Deciso B.V.
Copyright (C) 2015 Fabian Franz
Copyright (C) 2015 Deciso B.V.
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
#}<!DOCTYPE html>
<html>

  <head>
        <meta charset="UTF-8">
        <title>{{ title }}</title>
        <meta name="description" content="Print Sheet of Vouchers">
        <meta name="author" content="OPNsense">
        <meta name="keywords" content="vouchers">
        <link href="/ui/themes/{{ui_theme|default('opnsense')}}/build/css/main.css" media="screen, projection" rel="stylesheet">
        <link rel="stylesheet" type="text/css" href="/ui/themes/{{ui_theme|default('opnsense')}}/build/css/bootstrap-select.css">
        <style>
            @media print {
              .noprint {
                display: none;
              }
            }
            .noprint, .nospace {
              margin: 0px;
              padding: 0px;
            }
            .voucher_title {
                font-weight: bold;
                text-align: center;
                font-size: 16px;
                padding-top: 20px;
            }
            /* border for cutting */
            td {
                padding-top: 5px;
            }
            .voucher_start {
                /*margin-left: 20px;*/
            }
            .nopagebreak {
                page-break-inside: avoid;
            }
            .voucher {
                font-weight: bold;
            }
            td {
                text-align: center;
                /*border: 1px solid;*/
            }
            body {
                margin: 0px;
                padding: 0px;
            }
        </style>
        <script type="text/javascript" src="/ui/js/lodash.min.js"></script>
        <script type="text/javascript" src="/ui/js/jquery-1.11.2.min.js"></script>
        <script>
        template = "\
          <table>\
    <% _.each(_.range(0,vouchers.length, layout), function (voucher_num) { %>\
    <tbody class=\"nopagebreak\">\
      <tr>\
        <% _.each(_.range(1, layout + 1), function() { %>\
          <td colspan=\"2\" class=\"voucher_title\"><%- title  %></td>\
    <% }); %>\
      </tr>\
      <tr>\
        <% _.each(_.range(1, layout + 1), function() { %>\
        <td rowspan=\"3\" class=\"voucher_start\"><img src=\"<%- image %>\" alt=\"logo\" /></td>\
        <td><%- text_before_code %></td>\
        <% }); %>\
        <tr>\
          <% _.each(_.range(0, layout), function(i) { %>\
            <td class=\"voucher\"><%- vouchers[voucher_num + i] %></td>\
          <% }); %>\
        </tr>\
        <tr>\
          <% _.each(_.range(1, layout + 1), function() { %>\
            <td><%- text_after_code  %></td>\
          <% }); %>\
        </tr>\
        </tbody>\
  <%\
  });\
  %>\
  </table>";
        </script>
  </head>
  <body>
    <div id="formdiv" class="noprint nospace">
      <nav class="navbar navbar-inverse">
        <div class="container-fluid">
          <div class="navbar-header">
            <a class="navbar-brand" href="/">
              <img alt="OPNsense" src="/ui/themes/opnsense/build/images/default-logo.png">
            </a>
          </div>
          <h1 class="navbar-text">{{ title }}</h1>
         </div>
      </nav>
      <table class="table table-striped">
        <tr>
          <td><label for="fieldtitle">{{ lang._('Title') }}</label></td>
          <td><input type="text" id="fieldtitle" value="{{ lang._('VOUCHER For Internet Access') }}" /></td>
        </tr>
        <tr>
          <td><label for="fieldtextfefore">{{ lang._('Text before code') }}</label></td>
          <td><input type="text" id="fieldtextfefore" value="{{ lang._('Connect to our network, open a and enter this code:') }}" /></td>
        </tr>
        <tr>
          <td><label for="fieldtextafter">{{ lang._('Text after code') }}</label></td>
          <td><input type="text" id="fieldtextafter" value="{{ lang._('Thank you for using our Network') }}" /></td>
        </tr>
        <tr>
          <td></td>
          <td><button id="backbtn" class="btn">{{ lang._('Back') }}</button><button id="preparebtn" class="btn">{{ lang._('Load') }}</button></td>
        </tr>
      </table>
    </div>
    <div id="voucherdiv" class="nospace"></div>
  <script>
    var compiled_template = _.template(template);
    $(document).ready(function() {
        var rollid = '{{ roll_number|escape_js }}';
        var cpzone = '{{ zone|escape_js  }}';
        var queryurl = "/services_captiveportal_vouchers.php?zone=" + cpzone + "&act=csv&id=" + rollid;
        $('#preparebtn').click(function () {
            jQuery.get(queryurl,function (data) {
                filecontent = data.split("\n");
                vouchers = _.map( _.filter(filecontent, function (element) {
                    if (element[0]) {
                        return element[0] != "#";
                    } else {
                        return false;
                    }
                }), function (voucher_raw) {
                    return voucher_raw.replace(/"/g,'').trim();
                });
                var config = {
                    counter: 0,
                    layout:3,
                    vouchers:  vouchers,
                    image: '/ui/themes/opnsense/build/images/default-logo.png',
                    text_before_code: $('#fieldtextfefore').val(),
                    text_after_code: $('#fieldtextafter').val(),
                    title: $('#fieldtitle').val() }
                result = compiled_template(config);
                $('#voucherdiv').html(result);
              });
            });
        $('#backbtn').click(function (){ history.back(); });
        });
    </script>
  </body>
</html>
