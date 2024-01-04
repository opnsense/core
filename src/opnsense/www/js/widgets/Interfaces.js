import BaseWidget from "./BaseWidget.js";

export default class Interfaces extends BaseWidget {
    constructor() {
        super();
        this.title = 'Interfaces';
    }

    onWidgetResize(elem, width, height) {
        if (width > 450) {
            $('.interfaces-detail-container').show();
        } else {
            $('.interfaces-detail-container').hide();
        }
    }

    async getHtml() {
        let $container = $(`
<div class="interfaces-container">
    <div class="flex-container">
        <div class="interfaces-info">
            <div class="info-detail nowrap">
                <i class="fa fa-plug text-success" title="" data-toggle="tooltip" data-original-title="Up"></i>
                <span class="fa-stack">
                    <i class="fa fa-square-o fa-stack-2x"></i>
                    <i class="fa fa-tty fa-stack-1x"></i>
                </span>
                <span class="badge interface-badge">ADSL</span>
            </div>
        </div>
        <div class="container interfaces-detail-container">
            <div class="row flex-nowrap vertical-center-row">
                <div class="text-center col-xs-12 info-detail">10Gbase-T &lt;full-duplex&gt;</div>
            </div>
        </div>
        <div class="interface-info" style="margin-left: auto; margin-right: 10px;"><div class="info-detail">172.29.50.17/32</div></div>
    </div>
    <div class="flex-container">
        <div class="interfaces-info">
            <div class="info-detail nowrap">
                <i class="fa fa-plug text-success" title="" data-toggle="tooltip" data-original-title="Up"></i>
                <span class="fa-stack">
                    <i class="fa fa-square-o fa-stack-2x"></i>
                    <i class="fa fa-wifi fa-stack-1x"></i>
                </span>
                <span class="badge interface-badge">LAN</span>
            </div>
        </div>
        <div class="container interfaces-detail-container">
            <div class="row flex-nowrap vertical-center-row">
            <div class="text-center col-xs-12 info-detail">10Gbase-T &lt;full-duplex&gt;</div>
            </div>
        </div>
        <div class="interface-info" style="margin-left: auto; margin-right: 10px;"><div class="info-detail">192.168.1.1/24</div></div>
    </div>
    <div class="flex-container">
        <div class="interfaces-info">
            <div class="info-detail nowrap">
                <i class="fa fa-plug text-success" title="" data-toggle="tooltip" data-original-title="Up"></i>
                <span class="fa-stack">
                    <i class="fa fa-square-o fa-stack-2x"></i>
                    <i class="fa fa-exchange fa-stack-1x"></i>
                </span>
                <span class="badge interface-badge">MGMNT</span>
            </div>
        </div>
        <div class="interface-info" style="margin-left: auto; margin-right: 10px;"><div class="info-detail">172.18.2.10/24</div></div>
    </div>
    <div class="flex-container">
        <div class="interfaces-info">
            <div class="info-detail nowrap">
                <i class="fa fa-plug text-success" title="" data-toggle="tooltip" data-original-title="Up"></i>
                <span class="fa-stack">
                    <i class="fa fa-square-o fa-stack-2x"></i>
                    <i class="fa fa-exchange fa-stack-1x"></i>
                </span>
                <span class="badge interface-badge">PFSYNC</span>
            </div>
        </div>
        <div class="interface-info" style="margin-left: auto; margin-right: 10px;"><div class="info-detail">10.100.30.1/24</div></div>
    </div>
    <div class="flex-container">
        <div class="interfaces-info">
            <div class="info-detail nowrap">
                <i class="fa fa-plug text-success" title="" data-toggle="tooltip" data-original-title="Up"></i>
                <span class="fa-stack">
                    <i class="fa fa-square-o fa-stack-2x"></i>
                    <i class="fa fa-exchange fa-stack-1x"></i>
                </span>
                <span class="badge interface-badge">WAN</span>
            </div>
        </div>
        <div class="interface-info" style="margin-left: auto; margin-right: 10px;"><div class="info-detail">10.100.1.11/24</div></div>
    </div>
    <div class="flex-container">
        <div class="interfaces-info">
            <div class="info-detail nowrap">
                <i class="fa fa-plug text-success" title="" data-toggle="tooltip" data-original-title="Up"></i>
                <span class="fa-stack">
                    <i class="fa fa-square-o fa-stack-2x"></i>
                    <i class="fa fa-exchange fa-stack-1x"></i>
                </span>
                <span class="badge interface-badge">WAN2</span>
            </div>
        </div>
        <div class="interface-info" style="margin-left: auto; margin-right: 10px;"><div class="info-detail">None</div></div>
    </div>
</div>
        `);
        return $container;
    }

    async onMarkupRendered() {
        $('[data-toggle="tooltip"]').tooltip();
        $('.interface-badge').each(function(i, obj) {
            $(this).css('background', Chart.colorschemes.tableau.Classic10[i]);
        });
    }
}
