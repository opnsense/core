import BaseWidget from "./BaseWidget.js";

export default class Firewall extends BaseWidget {
    constructor() {
        super();
        this.title = 'Firewall';
    }

    async onWidgetTick() {
        let max_len = 10;
        let log_length = $('.flex-table-container').children().length;

        let mock_data = [
            {intf: this._getRandomInterface(), dir: 'out', src: this._getRandomIP('ipv4'), dst: this._getRandomIP('ipv4'), dst_port: this._getRandomPort(), action: 'pass'},
            {intf: this._getRandomInterface(), dir: 'out', src: this._getRandomIP('ipv4'), dst: this._getRandomIP('ipv4'), dst_port: this._getRandomPort(), action: 'pass'}
        ];
        let rows = this._getLogRows(mock_data);

        if (log_length >= max_len) {
            $('.flex-table-container').children().slice((log_length - mock_data.length) % max_len, log_length).remove();
        }

        for (const row of rows) {
            $('.flex-table-container').prepend(row);
        }

        let interfaces = this.test_interfaces;
        $('.fw-log-intf-badge').each(function(){
            let intf = $(this).text();
            $(this).css('background', Chart.colorschemes.tableau.Classic10[interfaces.indexOf(intf)]);
        });
    }

    _getRandomIP(inet = null, random46 = false) {
        if (random46) {
            inet = Math.random() < 0.5 ? 'ipv4' : 'ipv6';
        }

        if (inet === 'ipv4') {
            return Array.from({ length: 4 }, () => Math.floor(Math.random() * 256)).join('.');
        } else {
            const groups = Array.from({ length: 8 }, () => Math.floor(Math.random() * 65536).toString(16));
            return groups.join(':');
        }
    }

    _getRandomPort() {
        return Math.floor(Math.random() * (65535 - 1024 + 1) + 1024);
    }

    _getRandomInterface() {
        return this.test_interfaces[Math.floor(Math.random() * this.test_interfaces.length)];
    }

    _getLogRows(data, i) {
        let rows = [];
        for (const entry of data) {
            let $row = $(`
            <div class="flex-table-row">
                <div class="row-item">
                    <i class="fa fa-check" style="color: green"></i>
                </div>
                <div class="row-item"><span class="badge fw-log-intf-badge">${entry.intf}</span></div>
                <div class="row-item">
                    ${entry.src}<i class="fa fa-long-arrow-right"></i>${entry.dst}
                </div>
                <div class="row-item">${entry.dst_port}</div>
            </div>
            `);
            rows.push($row);
        }

        return rows;
    }

    onWidgetResize(elem, width, height) {
        if (width > 450) {
            $('.fw-log-container').show();
        } else {
            $('.fw-log-container').hide();
        }
    }

    async getHtml() {
        let $container = $(`<div></div>`);
        let $target = $(`<div class="canvas-container" style="position: relative; padding: 1.5rem; margin-bottom: 10px;"><canvas id="firewall-pie-chart"></canvas></div>`);
        
        let $log = $(`
<div class="fw-log-container">
    <div class="flex-table">
        <div class="flex-table-container">
        </div>
    </div>
</div>
        `);
        $container.append($target);
        $container.append($log);
        return $container;
    }

    async onMarkupRendered() {
        await this.sleep(10000);

        let $target = $('#firewall-pie-chart');
        let ctx = $target[0].getContext('2d');

        const DATA_COUNT = 5;
        const NUMBER_CFG = {count: DATA_COUNT, min: 0, max: 100};

        const data = {
            labels: ['Pass', 'Block', 'Reject'],
            datasets: [
                {
                    label: 'Dataset 1',
                    data: [9865, 4322, 2200],
                    // backgroundColor: '#d94f00',
                }
            ]
        };
        const config = {
            type: 'doughnut',
            data: data,
            options: {
                maintainAspectRatio: true,
                aspectRatio: 2,
                scaleShowLabels: false,
                tooltipEvents: [],
                scales: {
                    x: {
                        display: false
                    }
                },
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: false,
                        text: 'Chart.js Doughnut Chart'
                    }
                }
            },
        };

        let chart = new Chart(ctx, config);
    }
}
