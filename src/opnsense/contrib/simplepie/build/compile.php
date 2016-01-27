<?php
// Set up our constants
define('SP_PATH', dirname(dirname(__FILE__)));
define('COMPILED', SP_PATH . DIRECTORY_SEPARATOR . 'SimplePie.compiled.php');

function remove_header($contents)
{
	$tokens = token_get_all($contents);
	$stripped_source = '';
	$stripped_doc = false;
	$stripped_open = false;
	foreach ($tokens as $value)
	{
		if (is_string($value))
		{
			$stripped_source .= "{$value}";
			continue;
		}
		switch ($value[0])
		{
			case T_DOC_COMMENT:
				if (!$stripped_doc)
				{
					$stripped_doc = true;
					continue 2;
				}
				break;
			case T_OPEN_TAG:
				if (!$stripped_open)
				{
					$stripped_open = true;
					continue 2;
				}
				break;
		}
		$stripped_source .= "{$value[1]}";
	}

	return $stripped_source;
}

// Start with the header
$compiled = file_get_contents(SP_PATH . '/build/header.txt');
$compiled .= "\n";

// Add the base class
$contents = file_get_contents(SP_PATH . '/library/SimplePie.php');
$compiled .= remove_header($contents) . "\n";

// Add all the files in the SimplePie directory
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(SP_PATH . '/library/SimplePie'));
foreach($files as $file_path => $info)
{
	$contents = file_get_contents($file_path);
	$compiled .= remove_header($contents) . "\n";
}

// Strip excess whitespace
$compiled = preg_replace("#\n\n\n+#", "\n\n", $compiled);

// Hardcode the build
$compiled = str_replace(
	"define('SIMPLEPIE_BUILD', gmdate('YmdHis', SimplePie_Misc::get_build()))",
	"define('SIMPLEPIE_BUILD', '" . gmdate('YmdHis', time()) . "')",
	$compiled
);

// Finally, save
file_put_contents(COMPILED, $compiled);