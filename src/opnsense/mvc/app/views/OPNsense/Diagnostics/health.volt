{#
 # Copyright (c) 2025 Deciso B.V.
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
 # THIS SOFTWARE IS PROVIDED “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES,
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

	$(document).ready(function() {
		const healthGraph = new HealthGraph('health-chart');
		$('#spinner').show();
		healthGraph.initialize().then(async () => {
			const rrdOptions = healthGraph.getRRDList();
			for (const [category, subitems] of Object.entries(rrdOptions.data)) {

                let $select = $('#health-category-select');
                let $option = $('<option>', {
                    value: category,
                    text: category[0].toUpperCase() + category.slice(1)
                });
                $option.appendTo($select);
			}

            $('#health-category-select').on('changed.bs.select', async function() {
                $subselect = $('#health-subcategory-select');
                $subselect.empty();
                const selectedCategory = $(this).val();

                rrdOptions.data[selectedCategory].forEach((sub) => {
                    let optionText = sub;
                    if (sub in rrdOptions.interfaces) {
                        optionText = rrdOptions.interfaces[sub].descr;
                    }
                    let $option = $('<option>', {
                        value: sub,
                        text: optionText,
                    }).appendTo($subselect);
                });

                $('#health-subcategory-select').selectpicker('refresh');
                // trigger first selection
                $('#health-subcategory-select').val(rrdOptions.data[selectedCategory][0]).trigger('changed.bs.select');
            });


            $('#health-subcategory-select').on('changed.bs.select', async function() {
                $('#spinner').show();
                let sub = $(this).val();
                let category = $('#health-category-select').val();
                let system = `${sub}-${category}`;
                await healthGraph.update(system);
                $('#spinner').hide();
            });

			$('.selectpicker').selectpicker('refresh');

            // trigger event for first category
            $('#health-category-select').val(Object.keys(rrdOptions.data)[0]).trigger('changed.bs.select');

			$("#reset-zoom").click(function() {
				healthGraph.resetZoom();
			});

            $("#export").click(function() {
                healthGraph.exportData();
            })

			$('#detail-select').change(async function() {
				$('#spinner').show();
				await healthGraph.update(null, $(this).val());
				$('#spinner').hide();
			});

            $("#stacked-select").change(async function() {
				$('#spinner').show();
				await healthGraph.update(null, null, $(this).is(':checked'));
				$('#spinner').hide();
			});

		}).catch((err) => {
			$('#info-disabled').show();
			$('#main').hide();
		});

	});

</script>

<style>
.centered {
    display: flex;
    justify-content: center;
    align-items: center;
}

.label-select-pair {
    margin: 0 15px;
    text-align: center;
}

.label-select-pair label {
    display: block;
    margin-bottom: 5px;
}

.spinner-overlay {
	position: absolute;
	top: 50%;
	left: 50%;
	transform: translate(-50%, -50%);
	font-size: 32px;
}

.mb-2 {
    margin-bottom: 0.5rem;
}
</style>

<div class="panel panel-default">
	<div id="info-disabled" class="alert alert-warning" role="alert" style="display: none;">
		{{ lang._('Local data collection is not enabled. Enable it in Reporting Settings page.') }}
		<br />
		<a href="/reporting_settings.php">{{ lang._('Go to the Reporting configuration') }}</a>
	</div>

	<div id="health-header" class="panel-heading centered">
        <button id="reset-zoom" class="btn btn-primary" style="align-self: flex-end;">{{ lang._('Reset zoom') }}</button>

        <div class="label-select-pair">
            <label for="health-category-select"><b>{{ lang._('Category') }}</b></label>
            <select id="health-category-select" class="selectpicker" data-width="200px" data-container="body"></select>
        </div>
        <div class="label-select-pair">
            <label for="health-subcategory-select"><b>{{ lang._('Subject') }}</b></label>
            <select id="health-subcategory-select" class="selectpicker" data-width="200px" data-live-search="true" data-container="body"></select>
        </div>

        <div class="label-select-pair">
            <label for="detail-select"><b>{{ lang._('Granularity') }}</b></label>
            <select id="detail-select" class="selectpicker" data-width="200px">
                <option value="0">{{ lang._('%d minute (Default)') | format('1') }}</option>
                <option value="1">{{ lang._('%d minutes') | format('5') }}</option>
                <option value="2">{{ lang._('%d hour') | format('1') }}</option>
                <option value="3">{{ lang._('%d hours') | format('24') }}</option>
            </select>
        </div>

        <div class="label-select-pair" style="height: 55px">
            <label for="stacked-select"><b>{{ lang._('Stacked') }}</b></label>
            <input id="stacked-select" type="checkbox"/>
        </div>

        <button id="export" class="btn btn-default" data-toggle="tooltip" data-original-title="{{ lang._('Export current selection as CSV')}}" style="align-self: flex-end;">
            <span class="fa fa-cloud-download"></span>
        </button>
    </div>

	<div id="main" class="panel-body">
		<div class="chart-container" style="position: relative; height: 60vh;">
			<canvas id="health-chart"></canvas>
			<i id="spinner" class="fa fa-spinner fa-pulse spinner-overlay" style="display: none;"></i>
		</div>
	</div>

	<div class="panel-footer">
	</div>
</div>
