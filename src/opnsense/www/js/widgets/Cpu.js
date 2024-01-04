import BaseWidget from "./BaseWidget.js";

export default class Cpu extends BaseWidget {
    constructor() {
        super();
        this.title = 'CPU Usage';
        this.line = null;
    }

    async getHtml() {
        let $container = $(`<div></div>`);
        let $target = $(`<div class="canvas-container"><canvas id="cpu-usage" style="width: 80%; height: 30px;"></canvas></div>`);
        $container.append($target);
        return $container;
    }

    async onWidgetTick() {
        if (this.line !== null) {
            this.line.append(Date.now(), Math.random());
        }
    }

    async onMarkupRendered() {
        let smoothie = new SmoothieChart({
            responsive: true,
            millisPerPixel:50,
            tooltip: true,
            labels: {
                fillStyle: '#000000',
            },
            grid: {
                strokeStyle:'rgba(119,119,119,0.12)',
                verticalSections:4,
                millisPerLine:5000,
                fillStyle: 'transparent'
            }
        });
        smoothie.streamTo(document.getElementById("cpu-usage"), 5000);
        this.line = new TimeSeries();

        smoothie.addTimeSeries(this.line, {
            lineWidth: 3,
            strokeStyle: '#d94f00'
        });


    }
}
