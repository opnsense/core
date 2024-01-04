import BaseWidget from "./BaseWidget.js";

export default class InterfaceStatistics extends BaseWidget {
    constructor() {
        super();
        this.title = 'Interface Statistics';
    }

    onWidgetResize(elem, width, height) {
        let chart = Chart.getChart('int-stats');
        if (chart !== undefined) {
            if (width > 600) {
                chart.options.plugins.legend.display = true;
            } else {
                chart.options.plugins.legend.display = false;
            }
        }
    }

    async getHtml() {
        let $div = $(`<div class="chart-container-interface-statistics"></div>`);
        $div.append($(`<div><div class="canvas-container" style="position: relative; padding: 1.5rem; margin-bottom: 10px;"><canvas id="int-stats"></canvas></div></div>`));
        $div.append($(`<button id="btn-switch-chart-style" data-style="single">Change</button>`));
        return $div;
    }

    _setAlpha(color, opacity) {
        const op = Math.round(Math.min(Math.max(opacity || 1, 0), 1) * 255);
        return color + op.toString(16).toUpperCase();
    }

    _calculateCutoff(data, percentileThreshold) {
        const sortedData = data.map(entry => entry.data);
        const cutoffIndex = Math.ceil(percentileThreshold * sortedData.length);
        const lowValues = data.filter(entry => entry.data <= sortedData[cutoffIndex - 1]);
        const highValues = data.filter(entry => entry.data > sortedData[cutoffIndex - 1]);
        /* XXX: any of these two can be empty!! */
        return [highValues, lowValues];
    }

    _getIndexedLogarithmicData(objects, labels, scaleFactor) {
        let data = Array(labels.length).fill(null);
        for (const item of objects) {
            let idx = labels.indexOf(item.label);
            let value = item.data == 0 ? 1 : Math.floor(scaleFactor * Math.log(item.data) / Math.LN10);
            data[idx] = value;
        }
        return data;
    }

    _getIndexedColorData(objects, labels, alpha = 1) {
        let data = Array(labels.length).fill(null);
        for (const item of objects) {
            let idx = labels.indexOf(item.label);
            data[idx] = this._setAlpha(item.color, alpha);
        }
        return data;
    }

    _formatData(data, doCutOff = false) {
        /* XXX: we likely need to do a general cut off at ~20 interfaces, marking all others "other" */
        let sortedData = data.sort((a,b) => b.data - a.data);
        let labels = sortedData.map(entry => entry.label);
        let formattedData = doCutOff ? this._calculateCutoff(sortedData, 0.5) : [sortedData];
        let datasets= [];

        let i = 0.6;
        for (const set of formattedData) {
            datasets.push({
                label: 'dataset',
                data: this._getIndexedLogarithmicData(set, labels, 3),
                fill: true,
                // we need all colors to be present, even if they are not used
                // otherwise the legend will not display all colors, therefore
                // use the "data" variable
                backgroundColor: this._getIndexedColorData(data, labels),
                hoverBackgroundColor: this._getIndexedColorData(data, labels, 0.5),
                borderWidth: 2,
                //cutout: i
                weight: i,
                hoverOffset: 5
            })

            i = 0.4;
        }


        return {
            labels: labels,
            datasets: datasets,
            split: formattedData
        }
    }

    async onMarkupRendered() {
        Chart.defaults.font.size = 12;
        let $int_stats = $(`#int-stats`);
        let int_ctx = $int_stats[0].getContext('2d');

        let colors = Chart.colorschemes.tableau.Classic10;

        let data = [
            {label: "ADSL", data:0, color: colors[0]},
            {label: "LAN", data: 16000000, color: colors[1]},
            {label: "MGMNT", data: 20000, color: colors[2]},
            {label: "PFSYNC", data: 400000, color: colors[3]},
            {label: "WAN", data: 16000000, color: colors[4]},
            {label: "WAN2", data: 5000, color: colors[5]},
            {label: "VLAN1", data:0, color: colors[6]},
            {label: "VLAN2_test_long_name", data: 16000000, color: colors[7]},
            {label: "VLAN3", data: 99999999999, color: colors[8]},
            {label: "VLAN4", data: 1500012300, color: colors[9]},
            {label: "VLAN5", data: 12449581, color: colors[0]},
            {label: "VLAN6", data: 245635678345, color: colors[1]},
            {label: "VLAN7", data:10, color: colors[2]},
            {label: "VLAN8", data: 23345235, color: colors[3]},
            {label: "VLAN9", data: 12237884, color: colors[4]},
            {label: "VLAN10", data: 13089374512, color: colors[5]},
            {label: "VLAN11", data: 124908123, color: colors[6]},
            {label: "VLAN12", data: 4000, color: colors[7]},
            {label: "VLAN13", data: 23345235, color: colors[8]},
            {label: "VLAN14", data: 12237884, color: colors[9]},
            {label: "VLAN15", data: 13089374512, color: colors[0]},
            {label: "VLAN16", data: 124908123, color: colors[1]},
            {label: "VLAN17", data: 4000, color: colors[2]},
        ];

        let formattedData = this._formatData(data, true);

        const config_int = {
            type: 'doughnut',
            data: formattedData,
            options: {
                cutout: '30%',
                maintainAspectRatio: true,
                responsive: true,
                aspectRatio: 2,
                plugins: {
                    legend: {
                        display: false,
                        position: 'left',
                        title: 'Traffic',
                        onHover: (event, legendItem) => {
                            let i = 0;
                            for (const set of event.chart.data.split) {
                                let result = set.find(item => item.label === legendItem.text);
                                if (result !== undefined) {
                                    break;
                                }
                                i++;
                            }
                            const activeElement = {
                              datasetIndex: i,
                              index: legendItem.index
                            };
                            int_chart.setActiveElements([activeElement]);
                            int_chart.tooltip.setActiveElements([activeElement]);
                            int_chart.update();
                        }
                    },
                    tooltip: {
                        callbacks: {
                           label: function(tooltipItem) {
                                const entry = data.find(item => item.label === tooltipItem.label);
                                let result = [
                                    `${tooltipItem.label}: ${entry ? entry.data : 0}`,
                                    `Bytes in: some value`,
                                    `Bytes out: some value`,
                                    'Packets in: some value',
                                    'Packets out: some value',
                                    'Errors in: some value',
                                    'Errors out: some value',
                                    'Collisions: some value',
                                ];
                                return result;
                            }
                        }
                    },
                }
            },
        };

        let int_chart = new Chart(int_ctx, config_int);

        $('#btn-switch-chart-style').on('click', (function() {
            let style = $(this).data('style');
            if (style === 'multiple' || style === undefined) {
                $(this).data('style', 'single'); 
                $(this).text('Change to multiple');
                int_chart.data = this._formatData(data, false);
                int_chart.options.cutout = '50%';
                int_chart.update();
            } else {
                $(this).data('style', 'multiple');
                $(this).text('Change to single');
                int_chart.data = this._formatData(data, true);
                int_chart.options.cutout = '30%';
                int_chart.update();
            }
        }).bind(this)); // bind the class context to the function
     }
}
