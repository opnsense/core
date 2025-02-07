<section class="page-content-main">
    <div class="content-box">
        <div class="col-md-12">
            <br/>
{% if edit_alert_ids %}
{%     for id in edit_alert_ids %}
            <div id="{{ id }}" class="alert alert-info" style="display: none" role="alert">
                {{ lang._('After changing settings, please remember to apply them.') }}
            </div>
{%     endfor %}
{% endif %}
            <button class="btn btn-primary" id="reconfigureAct"
{% if data_endpoint %}
                    data-endpoint="{{ data_endpoint }}"
{% endif %}
                    data-label="{{ lang._(data_label|default('Apply')) }}"
{% if data_service_widget %}
                    data-service-widget="{{ data_service_widget }}"
{% endif %}
                    data-error-title="{{ lang._(data_error_title|default('Error reconfiguring service.')) }}"
                    type="button">
            </button>
            <br/><br/>
        </div>
    </div>
</section>
