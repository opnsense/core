(function() {
    function getThemeName() {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'opnsense-dark' : 'opnsense';
    }

    function switchLinks(theme_name) {
        let links = document.getElementsByTagName('link');
        for (let i = 0; i < links.length; i++) {
            if (links[i].href && links[i].href.indexOf('/ui/themes/') !== -1) {
                links[i].href = links[i].href.replace(/\/ui\/themes\/[^\/]+\//, '/ui/themes/' + theme_name + '/');
            }
        }
    }

    function switchImages(theme_name) {
        let imgs = document.getElementsByTagName('img');
        for (let i = 0; i < imgs.length; i++) {
            if (imgs[i].src && imgs[i].src.indexOf('/ui/themes/') !== -1) {
                imgs[i].src = imgs[i].src.replace(/\/ui\/themes\/[^\/]+\//, '/ui/themes/' + theme_name + '/');
            }
        }
    }

    function themeSwitcher() {
        let theme_name = getThemeName();
        switchLinks(theme_name);
        switchImages(theme_name);
        window.dispatchEvent(new Event('resize'));
    }

    if (window.matchMedia) {
        switchLinks(getThemeName());

        document.addEventListener('DOMContentLoaded', function() {
            let theme_name = getThemeName();
            switchLinks(theme_name);
            switchImages(theme_name);
            window.dispatchEvent(new Event('resize'));
        });

        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', themeSwitcher);
    }
})();
