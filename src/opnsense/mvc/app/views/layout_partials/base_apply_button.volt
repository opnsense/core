<section class="page-content-main">
    <div class="content-box grid-bottom-reserve">
        <div class="col-md-12 __mt __mb" style="display: flex; align-items: center;">
            <button class="btn btn-primary __mr" id="{{ button_id|default('reconfigureAct') }}"
                    data-endpoint="{{ data_endpoint }}"
                    data-label="{{ lang._(data_label|default('Apply')) }}"
                    data-error-title="{{ lang._(data_error_title|default('Error reconfiguring service.')) }}"
{% if data_service_widget is defined %}
                    data-service-widget="{{ data_service_widget }}"
{% endif %}
{% if data_grid_reload is defined %}
                    data-grid-reload="{{ data_grid_reload }}"
{% endif %}
                    data-message-id="{{ message_id|default('change_message_base_form') }}"
                    type="button">
            </button>
            <div id="{{ message_id|default('change_message_base_form') }}" class="text-danger" style="display: none">
                <i class="fa fa-fw fa-exclamation-triangle"></i>
                {{ lang._(data_change_message_content | default('After changing settings, please remember to apply them.')) }}
            </div>
        </div>
    </div>
</section>
