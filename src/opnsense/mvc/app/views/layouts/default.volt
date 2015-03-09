<!doctype html>
<!--[if IE 8 ]><html lang="en-US" class="ie ie8 lte9 lte8 no-js"><![endif]-->
<!--[if IE 9 ]><html lang="en-US" class="ie ie9 lte9 no-js"><![endif]-->
<!--[if (gt IE 9)|!(IE)]><!--><html lang="en-US" class="no-js"><!--<![endif]-->
	<head>

		<meta charset="UTF-8" />
		<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

		<meta name="robots" content="index, follow, noodp, noydir" />
		<meta name="keywords" content="" />
		<meta name="description" content="" />
		<meta name="copyright" content="" />
		<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />

		<title>{{title|default("OPNsense") }}</title>

        <!-- include (theme) style -->
		<link href="/ui/themes/opnsense/build/css/main.css" media="screen, projection" rel="stylesheet">

		<!-- Favicon -->
		<link href="/ui/themes/opnsense/build/images/favicon.png" rel="shortcut icon">

        <!-- Stylesheet for fancy select/dropdown -->
        <link rel="stylesheet" type="text/css" href="/ui/themes/opnsense/build/css/bootstrap-select.css">

        <!-- Font awesome -->
        <link rel="stylesheet" href="/ui/css/font-awesome.min.css">

		<!-- JQuery -->
		<script type="text/javascript" src="/ui/js/jquery-1.11.2.min.js"></script>
		<script type="text/javascript">
            // setup default scripting after page loading.
            $( document ).ready(function() {
                // hook into jquery ajax requests to ensure csrf handling.
                $.ajaxSetup({
                    'beforeSend': function(xhr) {
                        xhr.setRequestHeader("X-CSRFToken", "{{ csrf_token }}" );
                        xhr.setRequestHeader("X-CSRFTokenKey", "{{ csrf_tokenKey }}" );
                    }
                });

                // handle help messages show/hide
                $("a[class='showhelp']").click(function () {
                    $("*[for='" + $(this).attr('id') + "']").toggleClass("hidden show");
                });
            });

        </script>

        <!-- bootstrap dialog -->
        <link href="/ui/themes/opnsense/build/css/bootstrap-dialog.css" rel="stylesheet" type="text/css" />

        <!-- OPNsense standard toolkit -->
        <script type="text/javascript" src="/ui/js/opnsense.js"></script>
        <script type="text/javascript" src="/ui/js/opnsense_ui.js"></script>

	</head>
	<body>
	<header class="page-head">
		<nav class="navbar navbar-default" role="navigation">
			<div class="container-fluid">
				<div class="navbar-header">
					<a class="navbar-brand" href="/">
						<img class="brand-logo" src="/ui/themes/opnsense/build/images/default-logo.png" height="30" width="150"/>
						<img class="brand-icon" src="/ui/themes/opnsense/build/images/icon-logo.png" height="30" width="29"/>
					</a>
					<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navigation">
						<span class="sr-only">Toggle navigation</span>
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
					</button>
				</div>

				<div class="collapse navbar-collapse">
					<ul class="nav navbar-nav navbar-right">
						<li id="menu_messages">
							<a href="#">{{title|default("OPNsense") }}</a>					</li>
						<li></li><li></li><li></li>


						<li><a href="/help.php?page=firewall_virtual_ip.php" target="_blank" title="Help for items on this page">Help</a></li>
						<li class="active"><a href="/index.php?logout">Logout</a></li>
					</ul>

				</div>
			</div>
		</nav>
	</header>

	<main class="page-content col-sm-10 col-sm-push-2 ">

		<!-- menu system -->
		{{ partial("layout_partials/base_menu_system") }}

		<div class="row">
            <!-- page header -->
			<header class="page-content-head">
				<div class="container-fluid">
						<ul class="list-inline">
							<li class="__mb"><h1>{{title | default("")}}</h1></li>

							<li class="btn-group-container">
								<!-- <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modal_widgets"><span class="glyphicon glyphicon-plus-sign __iconspacer"></span>Add widget</button> -->
							</li>
						</ul>
				</div>
			</header>
            <!-- page content -->
			<section class="page-content-main">
				<div class="container-fluid">
                    <div class="row">
                        <section class="col-xs-12">
                            <div id="messageregion"></div>
                                {{ content() }}
                        </section>
                    </div>
				</div>
			</section>

		</div>

        <!-- page footer -->
		<footer class="page-foot col-sm-push-2">
			<div class="container-fluid">
				<a target="_blank" href="https://www.opnsense.org/?gui22" class="redlnk">OPNsense</a> is &copy;2014 - 2015 by <a href="http://www.deciso.com" class="tblnk">Deciso B.V.</a> All Rights Reserved.
				[<a href="/license.php" class="tblnk">view license</a>]
			</div>
		</footer>

	</main>

    <!-- bootstrap script -->
	<script type="text/javascript" src="/ui/js/bootstrap.min.js"></script>
	<script type="text/javascript" src="/ui/js/bootstrap-select.min.js"></script>
    <!-- bootstrap dialog -->
    <script src="/ui/js/bootstrap-dialog.js"></script>

	</body>
</html>
