<?php

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

    echo $application->handle($_SERVER['REQUEST_URI'])->getContent();
} catch (\Error | \Exception $e) {
    if (!is_a($e, 'OPNsense\Base\UserException')) {
        error_log($e);
    }

    $response = [
        'errorMessage' => $e->getMessage(),
        'errorTrace' => $e->getTraceAsString(),
    ];

    if (method_exists($e, 'getTitle')) {
        $response['errorTitle'] = $e->getTitle();
    } else {
        $response['errorTitle'] = gettext('An API exception occurred');
        $response['errorMessage'] = $e->getFile() . ':' . $e->getLine() . ': ' . $response['errorMessage'];
    }

    header('HTTP', true, 500);
    header("Content-Type: application/json;charset=utf-8");

    echo json_encode($response, JSON_UNESCAPED_SLASHES);
}
