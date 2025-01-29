{#

OPNsense® is Copyright © 2025 by Deciso B.V.
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

<script src="{{ cache_safe('/ui/js/chart.umd.js') }}"></script>
<script src="{{ cache_safe('/ui/js/chartjs-plugin-colorschemes.min.js') }}"></script>
<script src="{{ cache_safe('/ui/js/moment-with-locales.min.js') }}"></script>
<script src="{{ cache_safe('/ui/js/chartjs-adapter-moment.min.js') }}"></script>
<script src="{{ cache_safe('/ui/js/chartjs-plugin-zoom.min.js') }}"></script>
<script src="{{ cache_safe('/ui/js/luxon.min.js') }}"></script>
<script src="{{ cache_safe('/ui/js/chartjs-scale-timestack.min.js') }}"></script>
<script src="{{ cache_safe('/ui/js/opnsense_health.js') }}"></script>

<script>

	$(document).ready(function() {
		const healthGraph = new HealthGraph('health-chart');
		$('#spinner').show();
		healthGraph.initialize().then(async () => {
			const rrdOptions = healthGraph.getRRDList();
			for (const [category, item] of Object.entries(rrdOptions.data)) {
				let $select = $(`
					<select id="health-type-${category}"
							data-category-id="${category}"
							data-title="${category[0].toUpperCase()}${category.slice(1)}"
							class="selectpicker"
							data-width="200px"
							data-live-search="true"
							data-container="body">
					</select>
				`);

				rrdOptions.data[category].forEach((sub) => {
					let optionText = sub;
					if (sub in rrdOptions.interfaces) {
						optionText = rrdOptions.interfaces[sub].descr;
					}

					let $option = $('<option>').text(optionText).val(sub);
					$select.append($option);
				})


				$select.on('changed.bs.select', async function() {
					$('#spinner').show();
					let categoryId = $(this).data('category-id');
					$(this).data('title', `${categoryId[0].toUpperCase()}${categoryId.slice(1)}`);
					let subValue = $(this).val();
					let system = `${subValue}-${categoryId}`;
					$('.selectpicker').selectpicker('refresh');
					await healthGraph.update(system);
					$('#spinner').hide();
				});

				$('#health-header').append($select);
			}

			$('.selectpicker').selectpicker('refresh');

			// initial graph render
			await healthGraph.update();
			$('#spinner').hide();


			$("#reset-zoom").click(function() {
				healthGraph.resetZoom();
			});

			$('#detail-select').change(async function() {
				$('#spinner').show();
				var selectedValue = $(this).val();
				await healthGraph.update(null, selectedValue);
				$('#spinner').hide();
			});
		}).catch((err) => {
			$('#info-disabled').show();
			$('#main').hide();
		});

	});

</script>

<style>
.spinner-overlay {
	position: absolute;
	top: 50%;
	left: 50%;
	transform: translate(-50%, -50%);
	font-size: 32px; /* Adjust size */
}
</style>

<div class="panel panel-default">
	<div id="info-disabled" class="alert alert-warning" role="alert" style="display: none;">
		{{ lang._('Local data collection is not enabled. Enable it in Reporting Settings page.') }}
		<br />
		<a href="/reporting_settings.php">{{ lang._('Go to the Reporting configuration') }}</a>
	</div>

	<div id="health-header" class="panel-heading">
	</div>

	<div id="main" class="panel-body">
		<div class="chart-container" style="position: relative; height: 60vh;">
			<canvas id="health-chart"></canvas>
			<i id="spinner" class="fa fa-spinner fa-pulse spinner-overlay" style="display: none;"></i>
		</div>
	</div>

	<div class="panel-footer">
		<button type="button" id="reset-zoom">Reset zoom</button>
		<select id="detail-select" class="form-control">
            <option value="0">Default (1 minute)</option>
            <option value="1">5 minutes</option>
            <option value="2">1 hour</option>
            <option value="3">24 hours</option>
        </select>
	</div>
</div>
