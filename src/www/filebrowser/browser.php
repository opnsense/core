<?php

require_once("guiconfig.inc");

/*
	pfSense_MODULE:	shell
*/
// Fetch a list of directories and files inside a given directory
function get_content($dir) {
	$dirs  = array();
	$files = array();

	clearstatcache();
	$fd = @opendir($dir);

	while($entry = @readdir($fd)) {
		if($entry == ".")                 continue;
		if($entry == ".." && $dir == "/") continue;

		if(is_dir("{$dir}/{$entry}"))
			array_push($dirs, $entry);
		else
			array_push($files, $entry);
	}

	@closedir($fd);

	natsort($dirs);
	natsort($files);

	return array($dirs, $files);
}

$path = realpath(strlen($_GET['path']) > 0 ? $_GET['path'] : "/");
if(is_file($path))
	$path = dirname($path);

// ----- header -----
?>
<table width="100%">
	<tr>
		<td class="fbHome" width="25px" align="left">
			<span onClick="jQuery('#fbTarget').val('<?=$realDir?>'); fbBrowse('/');" alt="Home" title="Home" class="glyphicon glyphicon-home"></span>
		</td>
		<td><b><?=$path;?></b></td>
		<td class="fbClose" align="right">
			<span onClick="jQuery('#fbBrowser').fadeOut();" border="0" class="glyphicon glyphicon-remove" alt="Close" title="Close" ></span>
		</td>
	</tr>
	<tr>
		<td id="fbCurrentDir" colspan="3" class="vexpl" align="left">
<?php

// ----- read contents -----
if(is_dir($path)) {
	list($dirs, $files) = get_content($path);
?>

		</td>
	</tr>
<?php
}
else {
?>
			Directory does not exist.
		</td>
	</tr>
</table>
<?php
	exit;
}

// ----- directories -----
foreach($dirs as $dir):
	$realDir = realpath("{$path}/{$dir}");
?>
	<tr>
		<td></td>
		<td class="fbDir vexpl" id="<?=$realDir;?>" align="left">
			<div onClick="jQuery('#fbTarget').val('<?=$realDir?>'); fbBrowse('<?=$realDir?>');">
				<span class="glyphicon glyphicon-folder-close text-primary"></span>
				&nbsp;<?=$dir;?>
			</div>
		</td>
		<td></td>
	</tr>
<?php
endforeach;

// ----- files -----
foreach($files as $file):
	$ext = strrchr($file, ".");

	switch ($ext) {
	   case ".css":
	   case ".html":
	   case ".xml":
		$type = "glyphicon glyphicon-globe";
		break;
	   case ".rrd":
		$type = "database";
		break;
	   case ".gif":
	   case ".jpg":
	   case ".png":
		$type = "glyphicon glyphicon-picture";
		break;
	   case ".js":
		 $type = "glyphicon glyphicon-globe";
		break;
	   case ".pdf":
		$type = "glyphicon glyphicon-book";
		break;
	   case ".inc":
	   case ".php":
		$type = "glyphicon glyphicon-globe";
		break;
	   case ".conf":
	   case ".pid":
	   case ".sh":
		$type = "glyphicon glyphicon-wrench";
		break;
	   case ".bz2":
	   case ".gz":
	   case ".tgz":
	   case ".zip":
		$type = "glyphicon glyphicon-compressed";
		break;
	   default:
		$type = "glyphicon glyphicon-cog";
	}

	$fqpn = "{$path}/{$file}";

	if(is_file($fqpn)) {
		$fqpn = realpath($fqpn);
		$size = sprintf("%.2f KiB", filesize($fqpn) / 1024);
	}
	else
		$size = "";

?>
	<tr>
		<td></td>
		<td class="fbFile vexpl" id="<?=$fqpn;?>" align="left">
			<?php $filename = str_replace("//","/", "{$path}/{$file}"); ?>
			<div onClick="jQuery('#fbTarget').val('<?=$filename?>'); loadFile(); jQuery('#fbBrowser').fadeOut();">
				<span class="<?=$type;?>"></span>
				&nbsp;<?=$file;?>
			</div>
		</td>
		<td align="right" class="vexpl">
			<?=$size;?>
		</td>
	</tr>
<?php
endforeach;
?>
</table>
