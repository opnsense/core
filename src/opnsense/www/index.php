<?php

error_reporting(E_ALL);

/**
 * search for a themed filename or return distribution standard
 * @param string $url relative url
 * @param array $theme theme name
 * @return string
 */
function view_fetch_themed_filename($url, $theme)
{
    $search_pattern = array(
        "/themes/{$theme}/build/",
        "/"
    );
    foreach ($search_pattern as $pattern) {
        $filename = __DIR__ . "{$pattern}{$url}";
        if (file_exists($filename)) {
            return str_replace("//", "/", "/ui{$pattern}{$url}");
        }
    }
    return $url; // not found, return source
}

/**
 * check if file exists, wrapper around file_exists() so services.php can define other implementation for local testing
 * @param string $filename to check
 * @return boolean
 */
function view_file_exists($filename)
{
    return file_exists($filename);
}

/**
 * return appended version string with a hash for proper caching for currently installed version
 * @param string $url to make cache-safe
 * @return string
 */
function view_cache_safe($url)
{
    $myview = \Phalcon\DI::getDefault()->get('view');

    if (!empty($myview->product_hash)) {
        return "{$url}?v={$myview->product_hash}";
    }

    return $url;
}

try {
    /**
     * Read the configuration
     */
    $config = include __DIR__ . "/../mvc/app/config/config.php";

    /**
     * Read auto-loader
     */
    include __DIR__ . "/../mvc/app/config/loader.php";

    /**
     * Read services
     */
    include __DIR__ . "/../mvc/app/config/services.php";

    /**
     * Handle the request
     */
    $application = new \Phalcon\Mvc\Application($di);

    echo $application->handle()->getContent();
} catch (\Exception $e) {
    echo $e->getMessage();
}
