{#
 # Copyright (c) 2023 Deciso B.V.
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
        let grid_service = $("#grid-service").UIBootgrid({
            search:'/api/core/service/search',
            options:{
                multiSelect: false,
                selection: false,
                formatters:{
                    commands: function (column, row) {
                        if (row['locked']) {
                            return '<button type="button" class="btn btn-xs btn-default command-restart" data-toggle="tooltip" title="{{ lang._('Restart') }}" data-row-id="' + row.id + '"><span class="fa fa-repeat fa-fw"></span></button>';
                        } else if (row['running']) {
                            return '<button type="button" class="btn btn-xs btn-default command-restart" data-toggle="tooltip" title="{{ lang._('Restart') }}" data-row-id="' + row.id + '"><span class="fa fa-repeat fa-fw"></span></button>' +
                                '<button type="button" class="btn btn-xs btn-default command-stop" data-toggle="tooltip" title="{{ lang._('Stop') }}" data-row-id="' + row.id + '"><span class="fa fa-stop fa-fw"></span></button>';
                        } else {
                            return '<button type="button" class="btn btn-xs btn-default command-start" data-toggle="tooltip" title="{{ lang._('Start') }}" data-row-id="' + row.id + '"><span class="fa fa-play fa-fw"></span></button>';
                        }
                    },
                    status: function (column, row) {
                        if (row['running']) {
                            return '<span class="label label-opnsense label-opnsense-xs label-success" data-toggle="tooltip" title="{{ lang._('Running') }}"><i class="fa fa-play fa-fw"></i></span>';
                        } else {
                            return '<span class="label label-opnsense label-opnsense-xs label-danger" data-toggle="tooltip" title="{{ lang._('Stopped') }}"><i class="fa fa-stop fa-fw"></i></span>';
                        }
                    }
                }
            }
        });
        grid_service.on('loaded.rs.jquery.bootgrid', function () {
            $('[data-toggle="tooltip"]').tooltip({container: 'body', trigger: 'hover'});
            $('.command-stop').click(function () {
                $(this).toggleClass('disabled');
                $(this).children().toggleClass('fa-stop fa-spinner fa-pulse');
                ajaxCall("/api/core/service/stop/" + $(this).data('row-id'), {}, function () {
                    $('#grid-service').bootgrid('reload');
                });
            });
            $('.command-start').click(function () {
                $(this).toggleClass('disabled');
                $(this).children().toggleClass('fa-start fa-spinner fa-pulse');
                ajaxCall("/api/core/service/start/" + $(this).data('row-id'), {}, function () {
                    $('#grid-service').bootgrid('reload');
                });
            });
            $('.command-restart').click(function () {
                $(this).toggleClass('disabled');
                $(this).children().toggleClass('fa-repeat fa-spinner fa-pulse');
                ajaxCall("/api/core/service/restart/" + $(this).data('row-id'), {}, function () {
                    $('#grid-service').bootgrid('reload');
                });
            });
        });
    });

</script>

<div class="tab-content content-box __mb">
    <table id="grid-service" class="table table-condensed table-hover table-striped table-responsive">
        <thead>
          <tr>
            <th data-column-id="id" data-type="string" data-sortable="false" data-identifier="true" data-visible="false">{{ lang._('ID') }}</th>
            <th data-column-id="running" data-type="string" data-width="100" data-formatter="status" data-sortable="false"></th>
            <th data-column-id="name" data-type="string">{{ lang._('Name') }}</th>
            <th data-column-id="description" data-type="string">{{ lang._('Description') }}</th>
            <th data-column-id="locked" data-type="string" data-sortable="false" data-visible="false"></th>
            <th data-column-id="commands" data-width="100" data-formatter="commands" data-sortable="false"></th>
          </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
</div>
