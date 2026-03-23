<section class="page-content-main" style="padding: 15px 0 0">
    <div class="content-box grid-bottom-reserve">
        <div class="col-md-12">
            <br/>
            <button class="btn btn-primary" id="{{ button_id|default('reconfigureAct') }}"
                    data-endpoint="{{ data_endpoint }}"
                    data-label="{{ lang._(data_label|default('Apply')) }}"
                    data-error-title="{{ lang._(data_error_title|default('Error reconfiguring service.')) }}"
{% if data_service_widget is defined %}
                    data-service-widget="{{ data_service_widget }}"
{% endif %}
{% if data_grid_reload is defined %}
                    data-grid-reload="{{ data_grid_reload }}"
{% endif %}
                    type="button">
            </button>
            <br/><br/>
        </div>
    </div>
</section>

<div id="{{ message_id|default('change_message_base_form') }}" class="alert alert-info" style="display: none" role="alert">
    {{ lang._(data_change_message_content | default('After changing settings, please remember to apply them.')) }}
</div>
