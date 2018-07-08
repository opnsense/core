<?php

error_reporting(E_ALL);

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
    include __DIR__ . "/../mvc/app/config/services_api.php";

    /**
     * Handle the request
     */
    $application = new \Phalcon\Mvc\Application($di);

    echo $application->handle()->getContent();
} catch (\Exception $e) {
    $response = array();
    $response['errorMessage'] = $e->getMessage();
    if (method_exists($e, 'getTitle')) {
        $response['errorTitle'] = $e->getTitle();
    } else {
        $response['errorTitle'] = gettext("An API exception occured");
    }
    header('HTTP', true, 500);
    header("Content-Type: application/json;charset=utf-8");
    echo json_encode($response, JSON_UNESCAPED_SLASHES);
    error_log($e);
}
