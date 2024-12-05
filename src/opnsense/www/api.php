<?php

function error_output($http_code, $e, $user_message)
{
    $response = [];
    if (!file_exists('/var/run/development')) {
        $response['errorMessage'] = $user_message;
    } else {
        $response['errorMessage'] = $e->getMessage();
        $response['errorTrace'] = $e->getTraceAsString();
    }
    if (method_exists($e, 'getTitle')) {
        $response['errorTitle'] = $e->getTitle();
    }
    if (!headers_sent()) {
        header('HTTP', true, $http_code);
        header("Content-Type: application/json;charset=utf-8");
    }
    echo json_encode($response, JSON_UNESCAPED_SLASHES);
}


try {
    $config = include __DIR__ . "/../mvc/app/config/config.php";
    include __DIR__ . "/../mvc/app/config/loader.php";

    set_error_handler(function ($errno, $errmsg, $errfile, $errline) {
        if (!(error_reporting() & $errno)) {
            // not in our error reporting level, bail.
            return false;
        }
        throw new ErrorException($errmsg, 0, $errno, $errfile, $errline);
    });

    $router = new OPNsense\Mvc\Router('/api/', 'Api');
    $response = $router->routeRequest($_SERVER['REQUEST_URI'], [
            'action' => 'indexAction',
    ]);

    if (!$response->isSent()) {
        $response->send();
    }
} catch (\OPNsense\Base\UserException $e) {
    error_output(500, $e, $e->getMessage());
} catch (\OPNsense\Mvc\Exceptions\DispatchException $e) {
    error_output(404, $e, gettext('Endpoint not found'));
} catch (\Error | \Exception $e) {
    error_output(500, $e, gettext('Unexpected error, check log for details'));
    error_log($e);
}
