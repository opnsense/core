<script>
    $( document ).ready(function() {
        mapDataToFormUI({'frm_wizard':"/api/core/initial_setup/get"}).done(function(data){
            formatTokenizersUI();
            $('.selectpicker').selectpicker('refresh');
        });
        /* next pane */
        $(".action_next").click(function(){
            let target = $("#tab_" + $(this).data('next_index'));
            let this_form = $(this).closest('.tab-pane').find('form');
            saveFormToEndpoint("/api/core/initial_setup/set", 'form_root', function(){
                    target.parent().removeClass('hidden');
                    target.click();
                },
                true,
                function (data) {
                    let failed_here  = false;
                    if (data.validations && this_form) {
                        let validations = Object.keys(data.validations);
                        this_form.find('input, select').each(function(){
                            if (validations.includes($(this).attr('id'))) {
                                failed_here = true;
                            }
                        });
                    }
                    if (!failed_here) {
                        target.parent().removeClass('hidden');
                        target.click();
                    }
            });
        });
        $("#wizard\\.interfaces\\.wan\\.ipv4_type").change(function(){
            $(".wan_options").closest('tr').hide();
            $(".wan_options_" + $(this).val()).closest('tr').show();
        });

        $("#apply").click(function(){
            let this_button = $(this);
            if (this_button.hasClass('running')) {
                return;
            } else {
                this_button.find('.reload_progress').addClass("fa fa-spinner fa-pulse");
                this_button.addClass('running');
                saveFormToEndpoint("/api/core/initial_setup/configure", 'form_root', function(data){
                        this_button.removeClass('running');
                        this_button.find('.reload_progress').removeClass("fa fa-spinner fa-pulse");
                        /* redirect to index for finish page */
                        window.location = '/index.php?wizard_done';
                    },
                    false,
                    function (data) {
                        this_button.removeClass('running');
                        this_button.find('.reload_progress').removeClass("fa fa-spinner fa-pulse");
                    }
                );
            }
        });
    });
</script>

<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
{% for tabid, tab in all_tabs%}
    <li class="{% if loop.first %}active{% else %}hidden{% endif %}"><a data-toggle="tab" id="tab_{{loop.index}}" href="#{{tabid}}">{{ tab['title'] }}</a></li>
{% endfor %}
</ul>
<div class="tab-content content-box" id="form_root">
    {% for tabid, tab in all_tabs%}
    <div id="{{tabid}}" class="tab-pane fade in {% if loop.first %}active{% endif %}" style="padding-top:10px;">
        <div class="col-md-12">
            {% if tab['message'] is defined %}
                <div class="well">
                    {{ tab['message'] }}
                </div>
            {% elseif tab['form'] is defined %}
                {{ partial("layout_partials/base_form",['fields': tab['form'], 'id': 'frm_wizard-' ~ tabid ])}}
                <br/>
            {% endif %}

            {% if not loop.last %}
            <button class="btn btn-primary action_next" id="btn_next_{{loop.index}}" data-next_index="{{loop.index + 1}}">
                <b>{{ lang._('Next') }}</b>
            </button>
            {% else %}
            <button class="btn btn-primary" id="apply">
                <b>{{ lang._('Apply') }}</b><i class="reload_progress"></i>
            </button>
            {% endif %}
            <br/><br/>
        </div>
    </div>
    {% endfor %}
</div>
