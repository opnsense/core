<style>
    .apply-button-row {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    #change_message_success {
        display: none;
        margin: 0;
        padding: 6px 12px;
    }
</style>

<section class="page-content-main">
    <div class="content-box">
        <div class="col-md-12">
            <br/>
            <div id="{{ message_id|default('change_message_base_form') }}" class="alert alert-info" style="display: none" role="alert">
                {{ lang._('After changing settings, please remember to apply them.') }}
            </div>
            <div class="apply-button-row">
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
                <div id="change_message_success" class="alert alert-info" role="alert">
                    {{ lang._('Changes applied successfully.') }}
                </div>
            </div>
            <br/>
        </div>
    </div>
</section>
