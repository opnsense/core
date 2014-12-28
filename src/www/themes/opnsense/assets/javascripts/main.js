
$(document).ready(function(){

    $('#btn-filter').click(function () {
        var btn = $(this)
        btn.button('loading')

        /*
$.ajax(...).always(function () {
            btn.button('reset')
        });
*/
    });

    $(' #system,
        #interfaces,
        #firewall,
        #services,
        #vpn,
        #status,
        #diagnostics,
        #help
    ').on('show.bs.collapse', function () {

        // remove all actives
        $("#mainmenu a.active-menu-title").removeClass('active-menu-title');
        $("#mainmenu a + div.active-menu").removeClass('active-menu');

        // remove all collaped
        $("#mainmenu .collapse.in").not(this).collapse('hide');

        $(this).prev('a').addClass('active-menu-title');
        $(this).addClass('active-menu');
    });
});
