<?php

require_once '../autoloader.php';

function normalize_character_set($charset)
{
	return strtolower(preg_replace('/(?:[^a-zA-Z0-9]+|([^0-9])0+)/', '\1', $charset));
}

function build_character_set_list()
{
	$file = new SimplePie_File('http://www.iana.org/assignments/character-sets');
	if (!$file->success && !($file->method & SIMPLEPIE_FILE_SOURCE_REMOTE === 0 || ($file->status_code === 200 || $file->status_code > 206 && $file->status_code < 300)))
	{
		return false;
	}
	else
	{
		$data = explode("\n", $file->body);
		unset($file);
		
		foreach ($data as $line)
		{
			// New character set
			if (preg_match('/^Name:\s+(\S+)/', $line, $match))
			{
				// If we already have one, push it on to the array
				if (isset($aliases))
				{
					foreach ($aliases as &$alias)
					{
						$alias = normalize_character_set($alias);
					}
					$charsets[$preferred] = array_unique($aliases);
					natsort($charsets[$preferred]);
				}
				
				$aliases = array($match[1]);
				$preferred = $match[1];
			}
			// Another alias
			elseif (preg_match('/^Alias:\s+(\S+)(\s+\(preferred MIME name\))?\s*$/', $line, $match))
			{
				if ($match[1] !== 'None')
				{
					$aliases[] = $match[1];
					if (isset($match[2]))
					{
						$preferred = $match[1];
					}
				}
			}
		}
		
		// Compatibility replacements
		// From http://www.whatwg.org/specs/web-apps/current-work/multipage/parsing.html#misinterpreted-for-compatibility
		$compat = array(
			'EUC-KR' => 'windows-949',
			'GB2312' => 'GBK',
			'GB_2312-80' => 'GBK',
			'ISO-8859-1' => 'windows-1252',
			'ISO-8859-9' => 'windows-1254',
			'ISO-8859-11' => 'windows-874',
			'KS_C_5601-1987' => 'windows-949',
			'Shift_JIS' => 'Windows-31J',
			'TIS-620' => 'windows-874',
			//'US-ASCII' => 'windows-1252',
		);
		
		foreach ($compat as $real => $replace)
		{
			if (isset($charsets[$real]) && isset($charsets[$replace]))
			{
				$charsets[$replace] = array_merge($charsets[$replace], $charsets[$real]);
				unset($charsets[$real]);
			}
			elseif (isset($charsets[$real]))
			{
				$charsets[$replace] = $charsets[$real];
				$charsets[$replace][] = normalize_character_set($replace);
				unset($charsets[$real]);
			}
			else
			{
				$charsets[$replace][] = normalize_character_set($real);
			}
			$charsets[$replace] = array_unique($charsets[$replace]);
			natsort($charsets[$replace]);
		}
		
		// Sort it
		uksort($charsets, 'strnatcasecmp');
		
		// Check that nothing matches more than one
		$all = call_user_func_array('array_merge', $charsets);
		$all_count = array_count_values($all);
		if (max($all_count) > 1)
		{
			echo "Duplicated charsets:\n";
			foreach ($all_count as $charset => $count)
			{
				if ($count > 1)
				{
					echo "$charset\n";
				}
			}
		}
		
		// And we're done!
		return $charsets;
	}
}

function charset($charset)
{
	$normalized_charset = normalize_character_set($charset);
	if ($charsets = build_character_set_list())
	{
		foreach ($charsets as $preferred => $aliases)
		{
			if (in_array($normalized_charset, $aliases))
			{
				return $preferred;
			}
		}
		return $charset;
	}
	else
	{
		return false;
	}
}

function build_function()
{
	if ($charsets = build_character_set_list())
	{
		$return = <<<EOF
public static function encoding(\$charset)
{
	// Normalization from UTS #22
	switch (strtolower(preg_replace('/(?:[^a-zA-Z0-9]+|([^0-9])0+)/', '\\1', \$charset)))
	{

EOF;
		foreach ($charsets as $preferred => $aliases)
		{
			foreach ($aliases as $alias)
			{
				$return .= "\t\tcase " . var_export($alias, true) . ":\n";
			}
			$return .= "\t\t\treturn " . var_export($preferred, true) . ";\n\n";
		}
		$return .= <<<EOF
		default:
			return \$charset;
	}
}
EOF;
		return $return;
	}
	else
	{
		return false;
	}
}

if (php_sapi_name() === 'cli' && realpath($_SERVER['argv'][0]) === __FILE__)
{
	echo build_function();
}

?>
