(function() {
    function themeSwitcher(){
        let theme_name = 'opnsense';
        if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            theme_name = 'opnsense-dark';
        }
        let links = document.getElementsByTagName('link');
        let imgs = document.getElementsByTagName('img');
        for (let i=0; i < links.length; i++) {
            if (links[i].href && links[i].href.indexOf('/ui/themes/') !== -1) {
                links[i].href = links[i].href.replace(/\/ui\/themes\/[^\/]+\//, '/ui/themes/' + theme_name + '/');
            }
        }

        for (let i=0; i < imgs.length; i++) {
            if (imgs[i].src && imgs[i].src.indexOf('/ui/themes/') !== -1) {
                imgs[i].src = imgs[i].src.replace(/\/ui\/themes\/[^\/]+\//, '/ui/themes/' + theme_name + '/');
            }
        }
        /* D3 needs a resize event, but it likely doesn't harm to fire one in all cases after changing references */
        window.dispatchEvent(new Event('resize'));
    }
    
    if (window.matchMedia) {
        document.addEventListener('DOMContentLoaded',themeSwitcher);
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', themeSwitcher);
    }
})();