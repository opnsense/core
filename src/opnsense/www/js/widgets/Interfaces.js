import BaseTableWidget from "./BaseTableWidget.js";

export default class Interfaces extends BaseTableWidget {
    constructor() {
        super();
        this.title = 'Interfaces';
    }

    async getHtml() {
        let options = {
            headerPosition: 'none'
        }

        let data = [];
        for (const int of this.test_interfaces) {
            let row = [];
            row.push(`
                <div class="interface-info if-name">
                    <i class="fa fa-plug text-success" title="" data-toggle="tooltip" data-original-title="Up"></i>
                    <span class="fa-stack">
                        <i class="fa fa-square-o fa-stack-2x"></i>
                        <i class="fa fa-tty fa-stack-1x"></i>
                    </span>
                    <span class="badge interface-badge">${int}</span>
                </div>
            `);

            row.push(`
                <div class="interface-info-detail">
                    <div>10Gbase-T &lt;full-duplex&gt;</div>
                </div>
            `);

            row.push(`
                <div class="interface-info" style="margin-left: auto; margin-right: 10px;">
                    <div class="info-detail">172.29.50.17/32</div>
                </div>
            `)

            data.push(row);
        }

        this.setTableData(options, data);
        return super.getHtml();
    }

    async onMarkupRendered() {
        $('[data-toggle="tooltip"]').tooltip();
        $('.interface-badge').each(function(i, obj) {
            $(this).css('background', Chart.colorschemes.tableau.Classic10[i]);
        });
    }

    onWidgetResize(elem, width, height) {
        super.onWidgetResize(elem, width, height);
        if (width > 450) {
            $('.interface-info-detail').parent().show();
            $('.if-name').css('justify-content', 'initial');
        } else {
            $('.interface-info-detail').parent().hide();
            $('.if-name').css('justify-content', 'center');
        }

        // XXX: we may return true here to force an update of the widget
        // element. However, this function is called pretty often and
        // may slow things down, so the generic pattern here may be
        // to introduce some state
        //return true;
    }
}
