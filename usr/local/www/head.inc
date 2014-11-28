<?php
/*
	pfSense_MODULE:	header
*/

$g['theme'] = get_current_theme();

$pagetitle = gentitle( $pgtitle );

?>
<!doctype html>
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
		
		<link href="/themes/opnsense/build/css/main.css" media="screen, projection" rel="stylesheet">
	
		<link href="/themes/opnsense/build/img/icons/favicon.ico" rel="shortcut icon">
		<link href="/themes/opnsense/build/img/icons/touch.png" rel="apple-touch-icon-precomposed">
		
		<!--[if lt IE 9]><script src="//cdnjs.cloudflare.com/ajax/libs/html5shiv/3.7.2/html5shiv.min.js"></script><![endif]-->
		
		<script>var theme = '<?=$g['theme'];?>'; </script>
		
		<script src="/themes/opnsense/build/js/main.min.js" type="text/javascript"></script>
		
		<script type="text/javascript" src="/javascript/sorttable.js"></script>
		<!-- <script type="text/javascript" src="/javascript/ticker.js"></script> -->
		
		<!--<script type=\"text/javascript\" src=\"/themes/<?php echo $g['theme']; ?>/loader.js\"></script> -->
		
		<?php if($_GET['enablefirebuglite']): ?>
			<script type=\"text/javascript\" src=\"/javascript/pi.js\"></script>
			<script type=\"text/javascript\" src=\"/javascript/firebug-lite.js\"></script>
		<?php endif; ?>
		
		<script type="text/javascript" src="/javascript/scriptaculous/prototype.js"></script>
		<script type="text/javascript" src="/javascript/scriptaculous/scriptaculous.js"></script>
		<script type="text/javascript" src="/javascript/scriptaculous/effects.js"></script>
		<script type="text/javascript" src="/javascript/scriptaculous/dragdrop.js"></script>
		
		<?php if(file_exists("{$g['www_path']}/javascript/global.js")):?>
		<script type="text/javascript" src="/javascript/global.js"></script>
		<?php endif; ?>
	
		<?php
		/*
		 *	Find all javascript files that need to be included
		 *	for this page ... from the arrays ... :)
		 *	Coded by: Erik Kristensen
		 */
	
		$dir  = trim(basename($_SERVER["SCRIPT_FILENAME"], '.php'));
		$path = "{$g['www_path']}/javascript/" . $dir . "/";
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
		//]]>
		</script>
		<? endif; ?>


		<?php if (!$closehead):?></head><? endif;?>
	
<?php
	
/*  If this page is being remotely managed then do not allow the loading of the contents. */
if($config['remote_managed_pages']['item']) {
	foreach($config['remote_managed_pages']['item'] as $rmp) {
		if($rmp == $_SERVER['SCRIPT_NAME']) {
			//include("fbegin.inc");
			print_info_box_np("This page is currently being managed by a remote machine.");
			//include("fend.inc");
			exit;
		}
	}	
}
?>