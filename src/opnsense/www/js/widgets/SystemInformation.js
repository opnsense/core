import BaseTableWidget from "./BaseTableWidget.js";

export default class SystemInformation extends BaseTableWidget {
    constructor() {
        super();
        this.title = 'System Information';
    }

    async getHtml() {
        let options = {
            headerPosition: 'left'
        }

        let data = [
            {'Name': 'OPNsense.development'},
            {'Versions': 'OPNsense 7a5f89e24972d3f55181e7108cff13ba8f122978-amd64<br/>FreeBSD 13.2-RELEASE-p7<br/>OpenSSL 1.1.1w'},
            {'Updates': 'Click to check for updates'},
            {'CPU type': 'QEMU Virtual CPU version 2.5+ (4 cores, 4 threads)'},
            {'Key': ['Value', 'Another value']},
            {'Last config change': 'Thu Feb 22 9:38:17 CET 2024'},
            {'Uptime': '8 days 00:48:39'},
        ];

        this.setTableData(options, data);
        return super.getHtml();
    }
}
