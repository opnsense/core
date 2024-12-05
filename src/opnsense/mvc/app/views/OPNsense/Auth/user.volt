{#
 # Copyright (c) 2024 Deciso B.V.
 # All rights reserved.
 #
 # Redistribution and use in source and binary forms, with or without modification,
 # are permitted provided that the following conditions are met:
 #
 # 1. Redistributions of source code must retain the above copyright notice,
 #    this list of conditions and the following disclaimer.
 #
 # 2. Redistributions in binary form must reproduce the above copyright notice,
 #    this list of conditions and the following disclaimer in the documentation
 #    and/or other materials provided with the distribution.
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
    'use strict';

    $( document ).ready(function () {
        let grid_user = $("#grid-user").UIBootgrid({
            search:'/api/auth/user/search/',
            get:'/api/auth/user/get/',
            add:'/api/auth/user/add/',
            set:'/api/auth/user/set/',
            del:'/api/auth/user/del/',
            commands: {
                copy: {
                    classname: undefined
                },
                certs: {
                    method: function(event){
                        let refid = $(this).data("row-id") !== undefined ? $(this).data("row-id") : '';
                        ajaxGet('/api/auth/user/get/' + refid, {}, function(data){
                            if (data.user) {
                                window.location ='/ui/trust/cert/#user=' + data.user.name ;
                            }
                        });
                    },
                    classname: 'fa fa-fw fa-certificate',
                    title: "{{ lang._('Search certificates by username') }}",
                    sequence: 15
                },
                apikey: {
                    method: function(event){
                        let refid = $(this).data("row-id") !== undefined ? $(this).data("row-id") : '';
                        stdDialogConfirm(
                            '{{ lang._('API key') }}',
                            '{{ lang._('Generate and download API key?') }}',
                            '{{ lang._('Yes') }}', '{{ lang._('Cancel') }}', function () {
                                ajaxGet('/api/auth/user/get/' + refid, {}, function(data){
                                    if (data.user) {
                                        let username = data.user.name;
                                        ajaxCall('/api/auth/user/add_api_key/' + username, {}, function(data){
                                            const payload = 'key='+data.key +'\n' + 'secret='+data.secret +'\n';
                                            let filename = data.hostname + '_' + username + '_apikey.txt';
                                            download_content(payload, filename, 'text/plain;charset=utf8');
                                            $("#grid-apikey").bootgrid('reload');
                                        });
                                    }
                                });
                            }
                        );

                    },
                    classname: 'fa fa-fw fa-ticket',
                    title: "{{ lang._('Create and download API key for this user') }}",
                    sequence: 15
                }
            },
            options: {
                formatters: {
                    username: function (column, row) {
                        let container = $("<div/>");
                        let item = $('<span/>').addClass('fa fa-user');
                        if (row.disabled == '1') {
                            item.addClass('text-muted');
                        } else if (row.is_admin === '1') {
                            item.addClass('text-danger');
                        } else {
                            item.addClass('text-info');
                        }
                        container.append(item);
                        container.append('&nbsp;');
                        container.append(row.name);
                        if (row.shell_warning === '1') {
                            container.append('&nbsp;');
                            container.append(
                                $('<span data-toggle="tooltip"/>').addClass(
                                    'fa fa-warning bootgrid-tooltip'
                                ).prop(
                                    'title', "{{ lang._('The login shell for this non-admin user is not active for security reasons.') }}"
                                )
                            );
                        }
                        return container.html();
                    }
                }
            }
        });

        let grid_apikey = $("#grid-apikey").UIBootgrid({
            search:'/api/auth/user/search_api_key/',
            del:'/api/auth/user/del_api_key/',
            datakey: 'id'
        });

        /**
         * OTP field markup
         **/
        $(".otp_seed").each(function(){
            let that = $(this);
            let new_container = $("<div/>");
            new_container.append(
                '<input id="user.otp_uri" class="hidden"/>',
                '<div id="otp_qrcode" class="otp_default_hidden">',
                '<button class="btn btn-secondary otp_default_hidden" title="{{ lang._('new')}}" id="otp_new_seed"><i class="fa fa-fw fa-gear"></i></button>',
                '<button class="btn btn-primary" id="otp_unhide_seed">{{ lang._('show')}}</button>'
            );

            let target = that.closest('td');
            new_container.append(that.detach());
            target.append(new_container);
            $(".otp_default_hidden").hide();
            $("#otp_unhide_seed").click(function(){
                $("#otp_unhide_seed").hide();
                $(".otp_default_hidden").show();
            });
            $("#otp_new_seed").tooltip().click(function(){
                ajaxGet('/api/auth/user/new_otp_seed', {}, function(data){
                    if (data.seed) {
                        $("#user\\.otp_seed").val(data.seed);
                        $('#otp_qrcode').empty().qrcode(data.otp_uri_template.replace('|USER|', $("#user\\.name").val()));
                    }
                });
            });
        });

        /**
         * field change events
         **/
        $("#user\\.otp_uri").change(function(){
            $("#otp_unhide_seed").show();
            $(".otp_default_hidden").hide();
            $('#otp_qrcode').empty();
            if ($("#user\\.otp_uri").val()) {
                $('#otp_qrcode').qrcode($("#user\\.otp_uri").val());
            }
        });

        $('.datepicker').datepicker({format: 'mm/dd/yyyy'});
        /* format  authorizedkeys */
        $("#user\\.authorizedkeys").css('max-width', 'inherit').prop('wrap', 'off');
        $("#frm_DialogUser > div > table > colgroup > col:eq(1)").removeClass('col-md-4').addClass('col-md-6')
        $("#frm_DialogUser > div > table > colgroup > col:eq(2)").removeClass('col-md-5').addClass('col-md-3')
    });

</script>
<style>
    input.otp_seed {
        width: 290px;
        float: right;
    }
    .tooltip-inner {
        max-width: 1000px !important;
    }
    .modal-dialog.modal-lg {
        width:70% !important;
    }

</style>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" href="#user">{{ lang._('Users') }}</a></li>
    <li><a data-toggle="tab" href="#apikeys" id="tab_apikeys"> {{ lang._('ApiKeys') }} </a></li>
</ul>
<div class="tab-content content-box">
    <div id="user" class="tab-pane fade in active">
        <table id="grid-user" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogUser">
            <thead>
                <tr>
                    <th data-column-id="uuid" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="name" data-type="string" data-formatter="username">{{ lang._('Name') }}</th>
                    <th data-column-id="email" data-type="string" data-visible="false">{{ lang._('Email') }}</th>
                    <th data-column-id="comments" data-type="string" data-visible="false">{{ lang._('Comments') }}</th>
                    <th data-column-id="language" data-type="string" data-visible="false">{{ lang._('Language') }}</th>
                    <th data-column-id="group_memberships" data-type="string">{{ lang._('Groups') }}</th>
                    <th data-column-id="descr" data-type="string">{{ lang._('Description') }}</th>
                    <th data-column-id="commands" data-width="10em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td>
                        <button data-action="add" type="button" class="btn btn-xs btn-primary"><span class="fa fa-fw fa-plus"></span></button>
                        <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-fw fa-trash-o"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    <div id="apikeys" class="tab-pane fade in">
        <table id="grid-apikey" class="table table-condensed table-hover table-striped table-responsive" data-editDialog="DialogUser">
            <thead>
                <tr>
                    <th data-column-id="id" data-type="string" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
                    <th data-column-id="username" data-type="string">{{ lang._('Username') }}</th>
                    <th data-column-id="key" data-type="string">{{ lang._('Api key') }}</th>
                    <th data-column-id="commands" data-width="11em" data-formatter="commands" data-sortable="false">{{ lang._('Commands') }}</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td>
                        <button data-action="deleteSelected" type="button" class="btn btn-xs btn-default"><span class="fa fa-fw fa-trash-o"></span></button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{ partial("layout_partials/base_dialog",['fields':formDialogEditUser,'id':'DialogUser','label':lang._('Edit User')])}}
