<?php

function system_get_language_code() {
	global $config;

	// a language code, as per [RFC3066]
	$language = $config['system']['language'];
	$code = str_replace("_", "-", $language);

	if (empty($code))
		$code = "en-US"; // Set default code.

	return $code;
}


$g['theme'] = get_current_theme();

$pagetitle = gentitle( $pgtitle );

?><!doctype html>
<!--[if IE 8 ]><html lang="<?=system_get_language_code();?>" class="ie ie8 lte9 lte8 no-js"><![endif]-->
<!--[if IE 9 ]><html lang="<?=system_get_language_code();?>" class="ie ie9 lte9 no-js"><![endif]-->
<!--[if (gt IE 9)|!(IE)]><!--><html lang="<?=system_get_language_code();?>" class="no-js"><!--<![endif]-->
	<head>

		<meta charset="UTF-8" />
		<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

		<meta name="robots" content="index, follow, noodp, noydir" />
		<meta name="keywords" content="" />
		<meta name="description" content="" />
		<meta name="copyright" content="" />
		<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />

		<title><?php echo($config['system']['hostname'] . "." . $config['system']['domain'] . " - " . $pagetitle); ?></title>

		<link href="/themes/<?=$g['theme'];?>/build/css/main.css" media="screen, projection" rel="stylesheet">
		<!-- Stylesheet for fancy select/dropdown -->
		<link rel="stylesheet" type="text/css" href="/themes/<?=$g['theme'];?>/build/css/bootstrap-select.css">
		<!-- Favicon -->
		<link href="/themes/<?=$g['theme'];?>/assets/images/favicon.png" rel="shortcut icon">

		<!--[if lt IE 9]><script src="//cdnjs.cloudflare.com/ajax/libs/html5shiv/3.7.2/html5shiv.min.js"></script><![endif]-->

		<script>var theme = '<?=$g['theme'];?>'; </script>

		<script src="/themes/<?=$g['theme'];?>/build/js/main.min.js" type="text/javascript"></script>
		<!-- Fancy select with search options -->
		<script src="/themes/<?=$g['theme'];?>/build/js/bootstrap-select.min.js" type="text/javascript"></script>
		<!-- Ticker used for notices-->
		<script type="text/javascript" src="/javascript/ticker.js"></script>

		<?php
		/*
		 *	Find all javascript files that need to be included
		 *	for this page ... from the arrays ... :)
		 *	Coded by: Erik Kristensen
		 */

		$dir  = trim(basename($_SERVER['SCRIPT_FILENAME'], '.php'));
		$path = '/usr/local/www/javascript/' . $dir . '/';
		if (is_dir($path)) {
			if ($dh = opendir($path)) {
				while (($file = readdir($dh)) !== false) {
					if (is_dir($file))
						continue;
					echo "\t<script type=\"text/javascript\" src=\"/javascript/{$dir}/{$file}\"></script>\n";
				}
				closedir($dh);
			}
		}

		?>

		<?php if (basename($_SERVER["SCRIPT_FILENAME"] != "index.php") && !$allowautocomplete): ?>
		<script type="text/javascript">
		//<![CDATA[
		(function ($) {
			$("input").attr("autocomplete","off");
		})(jQuery);


		jQuery(function () {
			jQuery('[data-toggle="tooltip"]').tooltip()
		})
		//]]>
		</script>
		<? endif; ?>


		<?php if (!$closehead):?></head><? endif;?>

<?php

?>
